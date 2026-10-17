#!/bin/bash
# =============================================================================
# vps1-rpkd-siapkan.sh — persiapan RPKD Kotabaru (OPPKPKE) di vps1 (setelah
# vps1-rpkd-nft.sh):
#
#   1. Salin folder docker/ repo oppkpke_apps (diunggah lewat WinSCP) ke
#      /opt/rpkd/src/docker. vps1 tidak memegang kredensial GitHub.
#   2. Folder data /srv/rpkd (pemilik & mode di bawah).
#   3. Secret MySQL (dibuat hanya kalau belum ada; tidak dicetak).
#   4. Alat host: rpkd-deploy, rpkd-migrasi, rpkd-uji-asap, rpkd-env-siapkan,
#      rpkd-admin-pertama; konfigurasi vps1-cd.
#
# Aman dijalankan ulang (juga untuk sinkron folder docker/ setelah ada perubahan).
# Jalankan: sudo bash vps1-rpkd-siapkan.sh ~/rpkd-docker
# (~/rpkd-docker = salinan folder docker/ dari repo oppkpke_apps di laptop)
#
# UID: 33 = www-data (PHP), 101 = nginx (edge), 999 = mysql.
# =============================================================================
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "Jalankan sebagai root (sudo)." >&2; exit 1; }
ASAL="${1:?Pemakaian: sudo bash vps1-rpkd-siapkan.sh <folder docker/ hasil unggahan>}"
SRC=/opt/rpkd/src
D=/root/rpkd; install -d -m 0700 "$D"
if [ -z "${RPKD_DI_TEE:-}" ]; then
    RPKD_DI_TEE=1 bash "$0" "$@" 2>&1 | tee -a "$D/siapkan-$(date +%Y%m%d-%H%M%S).txt"
    exit "${PIPESTATUS[0]}"
fi
langkah() { printf '\n=== %s ===\n' "$*"; }
echo "vps1-rpkd-siapkan versi 2026-10-17a"

# -----------------------------------------------------------------------------
langkah "1. Salin folder docker/ ke $SRC/docker"
# -----------------------------------------------------------------------------
for f in compose.yaml nginx/rpkd.conf mysql/conf.d/rpkd.cnf mysql/init/01-akun.sh cd/rpkd.conf \
         host/rpkd-deploy host/rpkd-migrasi host/rpkd-uji-asap host/rpkd-env-siapkan host/rpkd-admin-pertama \
         host/vps1-rpkd-tarik-image.sh host/vps1-rpkd-nyalakan.sh; do
    [ -f "$ASAL/$f" ] || { echo "FATAL: $ASAL/$f tidak ada. Unggah SELURUH folder docker/ dari repo oppkpke_apps." >&2; exit 1; }
done
install -d -m 0755 /opt/rpkd "$SRC"
rm -rf "$SRC/docker.baru"
cp -r "$ASAL" "$SRC/docker.baru"
find "$SRC/docker.baru" -type f -exec sed -i 's/\r$//' {} +          # CRLF dari Windows
chown -R root:root "$SRC/docker.baru"
find "$SRC/docker.baru" -type d -exec chmod 0755 {} +
find "$SRC/docker.baru" -type f -exec chmod 0644 {} +
chmod 0755 "$SRC/docker.baru"/host/*
# mysql/init/*.sh TETAP 0644: entrypoint MySQL hanya me-source file yang TIDAK executable.
[ -d "$SRC/docker" ] && mv "$SRC/docker" "$SRC/docker.lama-$(date +%Y%m%d-%H%M%S)"
mv "$SRC/docker.baru" "$SRC/docker"
( cd "$SRC/docker" && find . -type f -print0 | sort -z | xargs -0 sha256sum ) > "$SRC/docker.sha256"
echo "Disalin: $(wc -l < "$SRC/docker.sha256") file -> $SRC/docker"
# Simpan 3 salinan lama terakhir saja.
# shellcheck disable=SC2012  # nama folder buatan skrip ini sendiri (docker.lama-<waktu>)
mapfile -t lama < <(ls -1dt "$SRC"/docker.lama-* 2>/dev/null | tail -n +4)
[ ${#lama[@]} -eq 0 ] || rm -rf -- "${lama[@]}"

# -----------------------------------------------------------------------------
langkah "2. Folder /srv/rpkd"
# -----------------------------------------------------------------------------
install -d -m 0750 /srv/rpkd /srv/rpkd/secrets /srv/rpkd/secrets/tls /srv/rpkd/deploy
install -d -m 0700 /srv/rpkd/backup
install -d -o 33 -g 33 -m 0750 /srv/rpkd/storage /srv/rpkd/storage/app
install -d -o 999 -g 999 -m 0700 /srv/rpkd/mysql
install -d -m 0755 /srv/rpkd/public /srv/rpkd/public/blue /srv/rpkd/public/green /srv/rpkd/nginx
chown root:101 /srv/rpkd/secrets/tls; chmod 0750 /srv/rpkd/secrets/tls
# shellcheck disable=SC2016  # $host dan $rpkd_color adalah variabel Nginx, bukan shell
[ -f /srv/rpkd/nginx/active.conf ] || printf 'map $host $rpkd_color { default green; }\n' > /srv/rpkd/nginx/active.conf
chmod 0644 /srv/rpkd/nginx/active.conf
echo "Folder siap."

# -----------------------------------------------------------------------------
langkah "3. Secret MySQL (dibuat hanya kalau belum ada)"
# -----------------------------------------------------------------------------
buat_secret() {
    local f="/srv/rpkd/secrets/$1"
    if [ -s "$f" ]; then echo "  ada   : $1"; return; fi
    ( umask 077; openssl rand -hex 20 | tr -d '\n' > "$f" )
    chown 999:999 "$f"; chmod 0400 "$f"
    echo "  dibuat: $1 ($(wc -c < "$f") karakter)"
}
for s in mysql_root mysql_rpkd_app mysql_rpkd_migrasi mysql_rpkd_backup; do buat_secret "$s"; done
echo "Nilai secret tidak dicetak. Simpan di password manager kalau perlu: sudo cat /srv/rpkd/secrets/<nama>"

# -----------------------------------------------------------------------------
langkah "4. Alat host"
# -----------------------------------------------------------------------------
for path in "$SRC"/docker/host/rpkd-*; do
    t="$(basename "$path")"
    install -m 0700 -o root -g root "$path" "/usr/local/sbin/$t"
    echo "  terpasang: /usr/local/sbin/$t"
done
install -d -m 0755 /etc/vps1-cd/apps
install -m 0644 -o root -g root "$SRC/docker/cd/rpkd.conf" /etc/vps1-cd/apps/rpkd.conf
[ -f /etc/vps1-cd/apps/rpkd.freeze ] || install -m 0644 -o root -g root /dev/null /etc/vps1-cd/apps/rpkd.freeze
echo "  terpasang: /etc/vps1-cd/apps/rpkd.conf"
echo "  (vps1-cd baru memantau rpkd setelah deploy pertama manual: vps1-rpkd-nyalakan.sh)"

[ -f /srv/rpkd/deploy/compose.env ] || { echo "RPKD_TAG=belum-ada" > /srv/rpkd/deploy/compose.env; chmod 0640 /srv/rpkd/deploy/compose.env; }

LAMA="$(docker ps --format '{{.Names}}' --filter 'label=com.docker.compose.project=rpkd' 2>/dev/null | tr '\n' ' ')"
if [ -n "$LAMA" ]; then
    echo
    echo "PERHATIAN: container rpkd yang sedang jalan masih membaca folder docker/ LAMA (bind mount): $LAMA"
    echo "  MySQL: vps1-rpkd-nyalakan.sh membuatnya ulang otomatis kalau konfigurasinya berubah."
    echo "  Nginx edge: bash /opt/pmb/src/docker/edge/vps1-edge.sh sinkron"
fi

langkah "SELESAI"
echo "Berikutnya (urutan di docs/DEPLOY-VPS1.md):"
echo "  1. .env Laravel : rpkd-env-siapkan"
echo "  2. sertifikat   : origin.pem, origin.key, aop-ca.pem ke /srv/rpkd/secrets/tls/ (root:101 0640)"
echo "  3. image        : bash $SRC/docker/host/vps1-rpkd-tarik-image.sh <commit 40 karakter>"
echo "  4. data lama    : bash $SRC/docker/host/vps1-rpkd-nyalakan.sh --hanya-mysql ; rpkd-impor-db <dump>"
echo "  5. nyalakan     : bash $SRC/docker/host/vps1-rpkd-nyalakan.sh"
