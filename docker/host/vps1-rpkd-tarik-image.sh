#!/bin/bash
# =============================================================================
# vps1-rpkd-tarik-image.sh — tarik image rpkd-php hasil CI dari ghcr.io ke vps1.
# Dipanggil vps1-cd (image sudah ditarik, login dilewati) atau manual untuk
# pemasangan pertama.
#
#   - Image yang ditarik adalah image yang SUDAH lolos SEMUA job CI (tag rilis-<commit>).
#   - Login ghcr.io memakai token GitHub SEMENTARA (classic, hanya read:packages,
#     kedaluwarsa 1 hari), diketik tersembunyi. Kredensial disimpan di folder
#     sementara (DOCKER_CONFIG) yang dihapus di akhir; tidak ada yang tertinggal
#     di /root/.docker.
#   - Revisi image dicocokkan dengan commit; isi image diperiksa singkat
#     (tidak ada .env, dump SQL, token, kunci).
#   - compose.env diisi RPKD_TAG dan RPKD_TAG_GREEN = 12 karakter pertama commit
#     (hanya kalau aplikasi belum jalan; sesudahnya versi baru lewat rpkd-deploy).
#
# Jalankan: sudo bash /opt/rpkd/src/docker/host/vps1-rpkd-tarik-image.sh <commit 40 karakter>
#
# Repo pribadi github.com/harya12x/oppkpke_apps: paket ghcr ikut privat. Token
# classic dengan scope read:packages dari akun harya12x (atau akun yang diberi akses).
# =============================================================================
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "Jalankan sebagai root (sudo)." >&2; exit 1; }
SHA="${1:?Pemakaian: vps1-rpkd-tarik-image.sh <commit sha 40 karakter dari main yang CI-nya hijau> [--paksa]}"
PAKSA="${2:-}"
case "$PAKSA" in ""|--paksa) ;; *) echo "Opsi tidak dikenal: $PAKSA" >&2; exit 2 ;; esac
[[ "$SHA" =~ ^[0-9a-f]{40}$ ]] || { echo "Commit harus 40 karakter hex (salin lengkap dari GitHub)." >&2; exit 1; }
OWNER="${RPKD_GHCR_OWNER:-harya12x}"
N=rpkd-php
TAG="${SHA:0:12}"
ENVF=/srv/rpkd/deploy/compose.env
CATATAN="/srv/rpkd/deploy/images-$TAG.txt"
D=/root/rpkd; install -d -m 0700 "$D"
if [ -z "${RPKD_DI_TEE:-}" ]; then
    RPKD_DI_TEE=1 bash "$0" "$@" 2>&1 | tee -a "$D/tarik-$(date +%Y%m%d-%H%M%S).txt"
    exit "${PIPESTATUS[0]}"
fi
langkah() { printf '\n=== %s ===\n' "$*"; }
[ -d /srv/rpkd/deploy ] || { echo "/srv/rpkd/deploy belum ada (jalankan vps1-rpkd-siapkan.sh)." >&2; exit 1; }

rev="$(docker image inspect -f '{{index .Config.Labels "org.opencontainers.image.revision"}}' "$N:$TAG" 2>/dev/null || true)"

# Kredensial registry hanya di folder sementara ini.
DOCKER_CONFIG="$(mktemp -d /root/.docker-sementara.XXXXXX)"
export DOCKER_CONFIG
trap 'docker logout ghcr.io >/dev/null 2>&1 || true; rm -rf "$DOCKER_CONFIG"' EXIT

if [ "$rev" = "$SHA" ]; then
    langkah "1-2. Image $N:$TAG sudah ada di vps1 (revisi cocok), login dan tarik dilewati"
else
    # -------------------------------------------------------------------------
    langkah "1. Login ghcr.io (token sementara)"
    # -------------------------------------------------------------------------
    read -r -p "Username GitHub: " GH_USER
    [[ "$GH_USER" =~ ^[A-Za-z0-9-]+$ ]] || { echo "Username tidak valid." >&2; exit 1; }
    read -r -s -p "Token GitHub (read:packages, tidak tampil di layar): " GH_TOKEN; echo
    [ -n "$GH_TOKEN" ] || { echo "Token kosong." >&2; exit 1; }
    printf '%s' "$GH_TOKEN" | docker login ghcr.io -u "$GH_USER" --password-stdin >/dev/null \
        || { unset GH_TOKEN; echo "Login ditolak. Cek token (read:packages) dan akses ke paket rpkd-php." >&2; exit 1; }
    unset GH_TOKEN
    echo "Login berhasil (kredensial hanya di $DOCKER_CONFIG, dihapus di akhir)."

    # -------------------------------------------------------------------------
    langkah "2. Tarik image commit $TAG"
    # -------------------------------------------------------------------------
    ref="ghcr.io/$OWNER/$N:sha-$SHA"
    # Tag rilis-<commit> hanya ada kalau SEMUA job CI hijau (job "Rilis").
    # sha-<commit> bisa terbit sebelum PHPUnit/migrasi selesai.
    if ! docker manifest inspect "ghcr.io/$OWNER/$N:rilis-$SHA" >/dev/null 2>&1; then
        if [ "$PAKSA" = --paksa ]; then
            echo "PERINGATAN: commit ini BELUM lolos semua job CI (tag rilis-$SHA tidak ada); --paksa dipakai."
        else
            echo "Commit $TAG belum lolos semua job CI (tag rilis-$SHA tidak ada di ghcr)." >&2
            echo "Tunggu CI hijau, atau (darurat, sadar risikonya) tambahkan --paksa." >&2
            exit 1
        fi
    fi
    echo "Menarik $ref ..."
    docker pull -q "$ref" >/dev/null || { echo "Gagal menarik $ref (CI untuk commit ini sudah push ke ghcr?)." >&2; exit 1; }
    rev="$(docker image inspect -f '{{index .Config.Labels "org.opencontainers.image.revision"}}' "$ref")"
    [ "$rev" = "$SHA" ] || { echo "Label revisi ($rev) tidak sama dengan commit $SHA." >&2; exit 1; }
    docker tag "$ref" "$N:$TAG"
    docker logout ghcr.io >/dev/null
    echo "Logout ghcr.io."
fi
digest="$(docker image inspect -f '{{index .RepoDigests 0}}' "$N:$TAG" 2>/dev/null || echo "-")"
echo "  $N:$TAG  $digest"

# -----------------------------------------------------------------------------
langkah "3. Pemeriksaan isi image"
# -----------------------------------------------------------------------------
gagal=0
# Nilai rahasia di ARG/ENV tersimpan di metadata layer. Yang dilaporkan hanya NAMA
# variabelnya. Dikecualikan (publik): GPG_KEY(S) image resmi, *_FILE (path, bukan nilai).
nama="$(docker history --no-trunc --format '{{.CreatedBy}}' "$N:$TAG" \
    | grep -oE '[A-Z0-9_]*(PASSWORD|SECRET|TOKEN|_KEY)[A-Z0-9_]*=[^[:space:]$"'"'"']{6,}' \
    | cut -d= -f1 | grep -vxE 'GPG_KEYS?|[A-Z0-9_]*_FILE' | sort -u || true)"
if [ -n "$nama" ]; then
    echo "  BAHAYA: riwayat build memuat nilai untuk: $(tr '\n' ' ' <<< "$nama")"; gagal=1
fi
TEMUAN="$(docker run --rm --network none --user 33:33 --entrypoint sh "$N:$TAG" -c '
    cd /var/www/html || exit 0
    for f in .env .env.backup .env.production .env.testing auth.json; do [ -e "$f" ] && echo "$f"; done
    find . \( -path ./vendor -o -path ./node_modules \) -prune -o -type f \
        \( -name "*.sql" -o -name "*.sql.gz" -o -name "*.sqlite" -o -name "*.dump" -o -name ".token" -o -name "*.pem" -o -name "*.key" \) -print 2>/dev/null | head -20
    true')"
if [ -n "$TEMUAN" ]; then echo "  BAHAYA: isi image tidak sesuai:"; printf '    %s\n' "$TEMUAN"; gagal=1; fi
if [ "$gagal" = 1 ]; then
    echo "compose.env TIDAK diubah. Jangan pakai image ini." >&2; exit 1
fi
echo "  Bersih: tidak ada .env, dump SQL/SQLite, token, atau kunci di image."
printf '%s:%s %s\n' "$N" "$TAG" "$digest" > "$CATATAN"

# -----------------------------------------------------------------------------
langkah "4. compose.env"
# -----------------------------------------------------------------------------
# Aplikasi sudah jalan: versi baru dipasang lewat rpkd-deploy (blue/green),
# bukan dengan menimpa tag green di sini (itu akan mengganti versi yang sedang
# melayani pengguna pada "docker compose up" berikutnya).
if docker ps --format '{{.Names}}' | grep -E '^rpkd-app-(blue|green)-1$' >/dev/null; then
    echo "Aplikasi rpkd sudah jalan: compose.env TIDAK diubah."
    echo "Digest dicatat di $CATATAN"
    langkah "SELESAI"
    echo "Pasang versi ini (blue/green, tanpa putus): rpkd-deploy $SHA"
    exit 0
fi
[ -f "$ENVF" ] || { echo "RPKD_TAG=belum-ada" > "$ENVF"; chmod 0640 "$ENVF"; }
cp -p "$ENVF" "$ENVF.bak-$(date +%Y%m%d-%H%M%S)"
sed -i -E '/^(RPKD_TAG|RPKD_TAG_GREEN)=/d' "$ENVF"
printf 'RPKD_TAG=%s\nRPKD_TAG_GREEN=%s\n' "$TAG" "$TAG" >> "$ENVF"
echo "RPKD_TAG=$TAG, RPKD_TAG_GREEN=$TAG (cadangan compose.env lama disimpan)."
echo "Digest dicatat di $CATATAN"

langkah "SELESAI"
echo "Berikutnya: bash /opt/rpkd/src/docker/host/vps1-rpkd-nyalakan.sh"
