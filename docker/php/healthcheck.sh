#!/bin/sh
# Liveness php-fpm: minta /ping langsung ke FPM lewat FastCGI, harus "pong".
# Murah (tanpa Laravel, tanpa database), aman dipanggil Docker setiap 15 detik.
# Kesiapan aplikasi (database, storage) dicek terpisah lewat GET /up dan rpkd-uji-asap saat deploy.
set -eu
SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
    cgi-fcgi -bind -connect 127.0.0.1:9000 2>/dev/null | grep -q pong
