#!/bin/sh
# =============================================================================
# Entrypoint rpkd-php — dipakai app (php-fpm), worker, scheduler, dan migrasi.
# Berjalan sebagai www-data (uid 33). TIDAK menjalankan migrasi: itu tugas
# service "migrasi" (rpkd-deploy), dengan akun database yang boleh DDL.
# =============================================================================
set -eu
APP=/var/www/html
cd "$APP"

log() { echo "[rpkd-entrypoint] $*" >&2; }

# 1. File .env wajib ada. Ia di-mount read-only dari secret file di host.
if [ ! -s "$APP/.env" ]; then
    log "FATAL: $APP/.env tidak ada atau kosong. Mount secret rpkd.env ke path ini."
    exit 78
fi

# 2. storage/ adalah volume bersama (web + worker + scheduler).
if [ ! -w "$APP/storage" ]; then
    log "FATAL: storage/ tidak bisa ditulis oleh uid $(id -u). Di host: chown -R 33:33 <folder storage>"
    exit 73
fi
cp -Rn /opt/rpkd/storage-skel/. "$APP/storage/" 2>/dev/null || true
for d in app/public app/private framework/cache/data framework/sessions \
         framework/testing framework/views logs; do
    mkdir -p "$APP/storage/$d"
done

# 3. Hasil kompilasi Blade per container (tmpfs /tmp), bukan di volume bersama,
#    supaya dua versi aplikasi yang hidup bersamaan saat deploy tidak saling timpa.
: "${VIEW_COMPILED_PATH:=/tmp/views}"
export VIEW_COMPILED_PATH
mkdir -p "$VIEW_COMPILED_PATH"

# 4. bootstrap/cache adalah tmpfs per container: isi dari hasil build.
cp -Rn /opt/rpkd/bootstrap-cache/. "$APP/bootstrap/cache/" 2>/dev/null || true
if [ ! -f "$APP/bootstrap/cache/packages.php" ]; then
    log "packages.php belum ada, menjalankan package:discover"
    php artisan package:discover --ansi
fi

# 5. Cache konfigurasi. Setelah config:cache, env() di luar folder config/
#    mengembalikan null (perilaku production Laravel).
if [ "${RPKD_SKIP_CACHE:-0}" != "1" ]; then
    php artisan config:cache --ansi
    php artisan event:cache --ansi
    php artisan view:cache --ansi
    if ! php artisan route:cache --ansi; then
        log "PERINGATAN: route:cache gagal (nama route ganda?). Berjalan tanpa route cache."
        php artisan route:clear --ansi || true
    fi
fi

log "siap: $*"
exec "$@"
