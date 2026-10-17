#!/bin/bash
# =============================================================================
# vps1-rpkd-nyalakan.sh — pemasangan PERTAMA RPKD di vps1 (warna green). Tidak
# membuka apa pun ke internet: situs baru bisa diakses publik setelah edge bersama
# disinkron dan DNS dipindah ke Cloudflare.
#
#   1. Prasyarat: image (vps1-rpkd-tarik-image.sh), rpkd.env (rpkd-env-siapkan).
#   2. MySQL: nyala, sehat, akun rpkd_app/rpkd_migrasi/rpkd_backup sesuai secret.
#      --hanya-mysql: berhenti di sini, untuk impor data lama dulu (rpkd-impor-db).
#   3. Migrasi: rpkd-migrasi <tag> (dump + php artisan migrate --force). Database
#      kosong: semua tabel dibuat. Data impor: hanya migration yang belum tercatat.
#   4. Aset publik versi ini -> /srv/rpkd/public/green.
#   5. app-green; tunggu sehat; uji /up, database, storage, uji asap, koneksi keluar.
#   6. Worker + scheduler (database sendiri, tidak berbagi jadwal dengan server lama).
#
# Sesudah ini, versi baru dipasang vps1-cd (Telegram) / rpkd-deploy, bukan skrip ini.
# Jalankan: sudo bash /opt/rpkd/src/docker/host/vps1-rpkd-nyalakan.sh [--hanya-mysql]
# Aman diulang selama trafik masih di green.
# =============================================================================
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "Jalankan sebagai root (sudo)." >&2; exit 1; }
DOCKER_DIR=/opt/rpkd/src/docker
ENVF=/srv/rpkd/deploy/compose.env
HANYA_MYSQL=0
for a in "$@"; do
    case "$a" in
        --hanya-mysql) HANYA_MYSQL=1 ;;
        *) echo "Opsi tidak dikenal: $a" >&2; exit 2 ;;
    esac
done
D=/root/rpkd; install -d -m 0700 "$D"
if [ -z "${RPKD_DI_TEE:-}" ]; then
    RPKD_DI_TEE=1 bash "$0" "$@" 2>&1 | tee -a "$D/nyalakan-$(date +%Y%m%d-%H%M%S).txt"
    exit "${PIPESTATUS[0]}"
fi
langkah() { printf '\n=== %s ===\n' "$*"; }
dc() { docker compose --project-directory "$DOCKER_DIR" -f "$DOCKER_DIR/compose.yaml" --env-file "$ENVF" "$@"; }
tunggu_sehat() {   # tunggu_sehat <container> <detik>
    local c="$1" batas="$2" s="-"
    for _ in $(seq 1 $((batas / 3))); do
        s="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$c" 2>/dev/null || echo -)"
        case "$s" in healthy) echo "  $c: sehat"; return 0 ;; unhealthy|exited|dead) break ;; esac
        sleep 3
    done
    echo "  $c: $s. Log terakhir:"; docker logs --tail 40 "$c" 2>&1 | sed 's/^/    /'; return 1
}
sql() { docker exec -i rpkd-mysql-1 sh -c 'MYSQL_PWD="$(cat /run/secrets/mysql_root)" exec mysql -uroot "$@"' sh "$@"; }

# -----------------------------------------------------------------------------
langkah "1. Prasyarat"
# -----------------------------------------------------------------------------
[ -f "$DOCKER_DIR/compose.yaml" ] || { echo "$DOCKER_DIR belum ada (vps1-rpkd-siapkan.sh)." >&2; exit 1; }
[ -f "$ENVF" ] || { echo "$ENVF belum ada (vps1-rpkd-siapkan.sh)." >&2; exit 1; }
for s in mysql_root mysql_rpkd_app mysql_rpkd_migrasi mysql_rpkd_backup rpkd.env rpkd-migrasi.env; do
    [ -s "/srv/rpkd/secrets/$s" ] || { echo "/srv/rpkd/secrets/$s belum ada (vps1-rpkd-siapkan.sh / rpkd-env-siapkan)." >&2; exit 1; }
done
TAG="$(awk -F= '$1=="RPKD_TAG" {print $2}' "$ENVF" | tail -1)"
if [ "$HANYA_MYSQL" = 0 ]; then
    if [ -z "$TAG" ] || [ "$TAG" = belum-ada ]; then
        echo "RPKD_TAG belum diisi. Jalankan vps1-rpkd-tarik-image.sh dulu." >&2; exit 1
    fi
    docker image inspect "rpkd-php:$TAG" >/dev/null 2>&1 || { echo "Image rpkd-php:$TAG tidak ada. Jalankan vps1-rpkd-tarik-image.sh." >&2; exit 1; }
    echo "Image: rpkd-php:$TAG"
else
    # compose.yaml butuh RPKD_TAG terisi walau hanya MySQL yang dinyalakan.
    [ -n "$TAG" ] || echo "RPKD_TAG=belum-ada" >> "$ENVF"
fi
dc config --quiet
AKTIF="$(sed -nE 's/.*default[[:space:]]+(blue|green)[[:space:]]*;.*/\1/p' /srv/rpkd/nginx/active.conf 2>/dev/null | head -1)"
if [ "${AKTIF:-green}" != green ]; then
    echo "Trafik rpkd sekarang di $AKTIF (dikelola rpkd-deploy). Skrip ini hanya untuk pemasangan awal." >&2
    echo "Update versi: rpkd-deploy <commit 40 karakter>. Keadaan: rpkd-deploy status" >&2
    exit 1
fi

# -----------------------------------------------------------------------------
langkah "2. MySQL"
# -----------------------------------------------------------------------------
dc up -d mysql >/dev/null
tunggu_sehat rpkd-mysql-1 240
h="$(cat "$DOCKER_DIR"/mysql/init/*.sh "$DOCKER_DIR"/mysql/conf.d/*.cnf | sha256sum)"
c="$(docker exec rpkd-mysql-1 sh -c 'cat /docker-entrypoint-initdb.d/*.sh /etc/mysql/conf.d/*.cnf' 2>/dev/null | sha256sum)"
if [ "$h" != "$c" ]; then
    echo "Konfigurasi MySQL di container berbeda dengan $DOCKER_DIR; container dibuat ulang (data tetap)."
    dc up -d --force-recreate mysql >/dev/null
    tunggu_sehat rpkd-mysql-1 240
fi
docker exec rpkd-mysql-1 bash /docker-entrypoint-initdb.d/01-akun.sh
JML="$(echo "SELECT COUNT(*) FROM mysql.user WHERE (user IN ('rpkd_app','rpkd_migrasi') AND host='172.30.22.%') OR (user='rpkd_backup' AND host='localhost');" | sql -N)"
[ "$JML" = 3 ] || { echo "FATAL: akun rpkd_app/rpkd_migrasi/rpkd_backup tidak lengkap ($JML/3)." >&2; exit 1; }
TABEL="$(echo "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='rpkd';" | sql -N)"
echo "MySQL sehat; tiga akun ada; database rpkd berisi $TABEL tabel. Workbench: SSH ke vps1, MySQL 127.0.0.1:3308."
if [ "$HANYA_MYSQL" = 1 ]; then
    langkah "SELESAI (--hanya-mysql)"
    echo "Impor data lama : rpkd-impor-db <dump.sql|dump.sql.gz>"
    echo "Lalu jalankan skrip ini lagi tanpa --hanya-mysql."
    exit 0
fi

# -----------------------------------------------------------------------------
langkah "3. Migrasi skema"
# -----------------------------------------------------------------------------
rpkd-migrasi "$TAG"

# -----------------------------------------------------------------------------
langkah "4. Aset publik (green)"
# -----------------------------------------------------------------------------
BARU=/srv/rpkd/public/green.baru
rm -rf "$BARU"; install -d -m 0755 "$BARU"
docker run --rm --network none --user 0:0 --entrypoint sh -v "$BARU:/out" "rpkd-php:$TAG" \
    -c 'cp -a /var/www/html/public/. /out/'
find "$BARU" -type l -delete
chown -R root:root "$BARU"; chmod -R a+rX,go-w "$BARU"
[ -f "$BARU/build/manifest.json" ] || { echo "build/manifest.json tidak ada di aset; image rusak?" >&2; exit 1; }
rm -rf /srv/rpkd/public/green.lama
[ -d /srv/rpkd/public/green ] && mv /srv/rpkd/public/green /srv/rpkd/public/green.lama
mv "$BARU" /srv/rpkd/public/green
rm -rf /srv/rpkd/public/green.lama
echo "Aset publik versi $TAG terpasang ($(find /srv/rpkd/public/green -type f | wc -l) file)."

# -----------------------------------------------------------------------------
langkah "5. app-green"
# -----------------------------------------------------------------------------
sed -i -E '/^RPKD_TAG_GREEN=/d' "$ENVF"; echo "RPKD_TAG_GREEN=$TAG" >> "$ENVF"
dc --profile green up -d app-green >/dev/null
tunggu_sehat rpkd-app-green-1 240
gagal=0
cek() { if [ "$1" = 0 ]; then echo "  OK    $2"; else echo "  GAGAL $2"; gagal=1; fi; }

UP="$(docker exec rpkd-app-green-1 sh -c 'SCRIPT_FILENAME=/var/www/html/public/index.php SCRIPT_NAME=/index.php \
        REQUEST_URI=/up QUERY_STRING= REQUEST_METHOD=GET SERVER_NAME=rpkdkabkotabaru.id SERVER_PORT=443 \
        SERVER_PROTOCOL=HTTP/1.1 HTTP_HOST=rpkdkabkotabaru.id HTTP_USER_AGENT=rpkd-nyalakan REMOTE_ADDR=127.0.0.1 HTTPS=on \
        cgi-fcgi -bind -connect 127.0.0.1:9000' 2>&1 || true)"
r=0; { ! grep -qiE '^Status: [45]' <<< "$UP" && grep -qi 'application up' <<< "$UP"; } || r=1; cek "$r" "GET /up lewat php-fpm"
r=0; rpkd-uji-asap rpkd-app-green-1 || r=1; cek "$r" "rpkd-uji-asap (halaman login, database, migration lengkap)"
r=0; docker exec rpkd-app-green-1 sh -c 'f=storage/logs/.uji-tulis; : > "$f" && rm -f "$f"' 2>/dev/null || r=1; cek "$r" "storage bisa ditulis"
if docker logs rpkd-app-green-1 2>&1 | grep 'route:cache gagal' >/dev/null; then
    echo "  INFO  route:cache gagal (aplikasi jalan tanpa route cache; lebih lambat)"
else
    echo "  OK    route:cache"
fi
KELUAR="$(docker exec rpkd-app-green-1 php -r '$c=stream_context_create(["http"=>["timeout"=>8]]); echo @file_get_contents("https://www.cloudflare.com/cdn-cgi/trace", false, $c) !== false ? "ok" : "gagal";' 2>&1 || true)"
if [ "$KELUAR" = ok ]; then echo "  OK    koneksi keluar HTTPS dari br-rpkd-out"
else echo "  INFO  koneksi keluar HTTPS gagal (vps1-rpkd-nft.sh sudah dijalankan?). Hanya dibutuhkan untuk SMTP."; fi

if [ "$gagal" = 1 ]; then
    echo "Ada pemeriksaan yang gagal. Log: docker logs rpkd-app-green-1 ; /srv/rpkd/storage/logs/" >&2
    exit 1
fi

# -----------------------------------------------------------------------------
langkah "6. Worker dan scheduler"
# -----------------------------------------------------------------------------
dc --profile jadwal up -d worker scheduler >/dev/null
sleep 5
docker ps -a --filter label=com.docker.compose.project=rpkd --format '{{.Names}} {{.Status}}' | grep -E 'worker|scheduler' | sed 's/^/  /'
if docker ps -a --filter label=com.docker.compose.project=rpkd --format '{{.Names}} {{.Status}}' \
     | grep -E '(worker|scheduler).*(Restarting|Exited)' >/dev/null; then
    echo "Worker/scheduler bermasalah: docker logs rpkd-worker-1 / rpkd-scheduler-1" >&2; exit 1
fi

langkah "SELESAI"
dc --profile green --profile jadwal ps --format 'table {{.Service}}\t{{.Status}}'
echo "Berikutnya:"
echo "  - Edge  : bash /opt/pmb/src/docker/edge/vps1-edge.sh perbarui   (edge bergabung ke rpkd_edge)"
echo "  - Akun  : data impor -> rpkd-akun-lemah (lalu --ganti kalau ada akun berpassword umum)"
echo "            database kosong -> rpkd-admin-pertama <email> [--dengan-data-master]"
echo "  - Uji lewat tunnel (port 9443) sebelum DNS dipindah: docs/DEPLOY-VPS1.md"
