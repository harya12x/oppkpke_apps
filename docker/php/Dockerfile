# syntax=docker/dockerfile:1
# =============================================================================
# rpkd-php — satu image untuk app (php-fpm), worker (queue:work), scheduler, dan
# migrasi OPPKPKE / RPKD Kotabaru (Laravel 13, PHP 8.3).
#
# Dibangun oleh CI (.github/workflows/ci.yml) dari checkout bersih; context = root repo:
#   docker build -f docker/php/Dockerfile -t rpkd-php:ci .
#
# Prinsip: image hanya berisi kode + aset statis. .env dan storage/ (lampiran
# chat, import Excel, log) dipasang saat runtime (secret file & volume).
# Dockerfile lama di root repo (single stage, node ikut di image) diganti file ini.
# =============================================================================
ARG PHP_IMAGE=php:8.3-fpm-trixie
ARG COMPOSER_IMAGE=composer:2.8
ARG NODE_IMAGE=node:22-bookworm-slim

# -----------------------------------------------------------------------------
# 0. Composer binary
# -----------------------------------------------------------------------------
FROM ${COMPOSER_IMAGE} AS composer

# -----------------------------------------------------------------------------
# 1. Base runtime: PHP 8.3-FPM + ekstensi + paket sistem
# -----------------------------------------------------------------------------
FROM ${PHP_IMAGE} AS base

# Ekstensi di luar bawaan image resmi (dom, xml, mbstring, fileinfo, dll. sudah ada):
#   pdo_mysql  MySQL                 gd     dompdf, phpspreadsheet (gambar)
#   zip        phpspreadsheet        intl   format angka/tanggal
#   bcmath     angka anggaran        pcntl  worker berhenti rapi saat deploy (SIGTERM)
#   opcache    performa              exif   orientasi gambar
# $savedAptMark sengaja tanpa kutip (daftar paket; pola resmi image PHP).
# hadolint ignore=SC2086
RUN set -eux; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libfreetype-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
        libzip-dev libicu-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip intl bcmath pcntl opcache exif; \
    apt-mark auto '.*' > /dev/null; \
    [ -z "$savedAptMark" ] || apt-mark manual $savedAptMark > /dev/null; \
    find /usr/local -type f -executable -exec ldd '{}' ';' \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
        | sort -u \
        | xargs -r dpkg-query --search \
        | awk 'sub(":$", "", $1) { print $1 }' \
        | sort -u \
        | xargs -r apt-mark manual > /dev/null; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    # Runtime: cgi-fcgi (health check php-fpm), sertifikat CA (SMTP/HTTPS keluar).
    apt-get install -y --no-install-recommends libfcgi-bin ca-certificates; \
    rm -rf /var/lib/apt/lists/*; \
    for e in pdo_mysql pcntl gd intl zip bcmath exif dom xml mbstring fileinfo; do php -m | grep -qix "$e"; done; \
    php -m | grep -qi 'zend opcache'; \
    command -v cgi-fcgi

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/php.ini        /usr/local/etc/php/conf.d/zz-rpkd.ini
COPY docker/php/www.conf       /usr/local/etc/php-fpm.d/zz-rpkd.conf
COPY docker/php/entrypoint.sh  /usr/local/bin/rpkd-entrypoint
COPY docker/php/healthcheck.sh /usr/local/bin/rpkd-healthcheck
RUN chmod 0755 /usr/local/bin/rpkd-entrypoint /usr/local/bin/rpkd-healthcheck

WORKDIR /var/www/html

# -----------------------------------------------------------------------------
# 2. Dependensi Composer (tanpa dev). Dipisah supaya layer cache awet.
# -----------------------------------------------------------------------------
FROM base AS vendor
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
        --no-interaction --no-progress

# -----------------------------------------------------------------------------
# 3. Aset frontend (Vite 8 + Tailwind 4). laravel-vite-plugin/fonts mengunduh
#    font Instrument Sans saat build dan menyajikannya dari 'self' (cocok dengan CSP).
# -----------------------------------------------------------------------------
FROM ${NODE_IMAGE} AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
# @source di resources/css/app.css memindai view paginasi Laravel.
COPY --from=vendor /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
     ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build && test -f public/build/manifest.json

# -----------------------------------------------------------------------------
# 4. Image akhir
# -----------------------------------------------------------------------------
FROM base AS runtime

COPY --from=vendor /var/www/html/vendor ./vendor
COPY --chown=root:root . .
COPY --from=assets /app/public/build ./public/build
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN set -eux; \
    composer dump-autoload --no-dev --optimize --no-scripts --no-interaction; \
    php artisan package:discover --ansi \
        || echo "PERINGATAN: package:discover gagal saat build; diulang di entrypoint"; \
    # Varian logo (sidebar, ikon tab, PDF) dibuat dari config app.logo → logo baru ikut rilis.
    # Gagal pun aman: App\Support\Brand otomatis memakai file logo sumber.
    php artisan brand:sync --ansi \
        || echo "PERINGATAN: brand:sync gagal saat build; logo memakai file sumber"; \
    rm /usr/bin/composer; \
    # Setara `php artisan storage:link`; root filesystem read-only saat runtime.
    ln -sfn ../storage/app/public public/storage; \
    # Kerangka storage/ dan bootstrap/cache disimpan di /opt/rpkd; saat runtime
    # storage/ adalah volume dan bootstrap/cache tmpfs (diisi entrypoint).
    mkdir -p /opt/rpkd storage bootstrap/cache; \
    cp -a storage /opt/rpkd/storage-skel; \
    cp -a bootstrap/cache /opt/rpkd/bootstrap-cache; \
    # Kode milik root, read-only bagi www-data.
    chown -R root:root /var/www/html; \
    chmod -R a+rX,go-w /var/www/html

ENV APP_ENV=production

USER 33:33
EXPOSE 9000
HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=3 \
    CMD ["rpkd-healthcheck"]
ENTRYPOINT ["rpkd-entrypoint"]
CMD ["php-fpm", "--nodaemonize"]
