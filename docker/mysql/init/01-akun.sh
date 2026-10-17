# shellcheck shell=bash
# =============================================================================
# Akun MySQL RPKD dengan hak minimum. Dijalankan oleh entrypoint image mysql saat
# datadir masih kosong (file ini di-SOURCE, jadi harus mode 0644, bukan 0755),
# dan bisa diulang kapan saja:
#   docker exec rpkd-mysql-1 bash /docker-entrypoint-initdb.d/01-akun.sh
#
# Password dibaca dari secret file (alfanumerik, minimal 32 karakter).
# Subnet 172.30.22.0/24 = jaringan "backend" di compose.yaml.
#
#   rpkd_app      aplikasi web, worker, scheduler: hanya DML
#   rpkd_migrasi  php artisan migrate (service "migrasi", dipanggil rpkd-migrasi): DDL
#   rpkd_backup   mysqldump dari dalam container (rpkd-migrasi, cadangan malam)
# =============================================================================
set -eo pipefail
SECRET_DIR="${RPKD_SECRET_DIR:-/run/secrets}"
baca_secret() { tr -d '\r\n' < "$SECRET_DIR/$1"; }

if ! declare -F docker_process_sql >/dev/null; then
    docker_process_sql() { MYSQL_PWD="$(baca_secret mysql_root)" mysql -uroot "$@"; }
fi

APP_PW="$(baca_secret mysql_rpkd_app)"
MIG_PW="$(baca_secret mysql_rpkd_migrasi)"
BACKUP_PW="$(baca_secret mysql_rpkd_backup)"

for pw in "$APP_PW" "$MIG_PW" "$BACKUP_PW"; do
    if ! [[ "$pw" =~ ^[A-Za-z0-9]{32,}$ ]]; then
        echo "FATAL: password akun MySQL harus alfanumerik, minimal 32 karakter" >&2
        exit 1
    fi
done

docker_process_sql --database=mysql <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`rpkd\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

    CREATE USER IF NOT EXISTS 'rpkd_app'@'172.30.22.%' IDENTIFIED BY '${APP_PW}';
    ALTER USER 'rpkd_app'@'172.30.22.%' IDENTIFIED BY '${APP_PW}';
    GRANT SELECT, INSERT, UPDATE, DELETE, CREATE TEMPORARY TABLES, EXECUTE, SHOW VIEW
        ON \`rpkd\`.* TO 'rpkd_app'@'172.30.22.%';

    CREATE USER IF NOT EXISTS 'rpkd_migrasi'@'172.30.22.%' IDENTIFIED BY '${MIG_PW}';
    ALTER USER 'rpkd_migrasi'@'172.30.22.%' IDENTIFIED BY '${MIG_PW}';
    GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES,
          CREATE TEMPORARY TABLES, LOCK TABLES, CREATE VIEW, SHOW VIEW, TRIGGER, EXECUTE
        ON \`rpkd\`.* TO 'rpkd_migrasi'@'172.30.22.%';

    CREATE USER IF NOT EXISTS 'rpkd_backup'@'localhost' IDENTIFIED BY '${BACKUP_PW}';
    ALTER USER 'rpkd_backup'@'localhost' IDENTIFIED BY '${BACKUP_PW}';
    GRANT SELECT, SHOW VIEW, TRIGGER, EVENT, LOCK TABLES ON \`rpkd\`.* TO 'rpkd_backup'@'localhost';
    GRANT PROCESS, SHOW_ROUTINE ON *.* TO 'rpkd_backup'@'localhost';
EOSQL

unset APP_PW MIG_PW BACKUP_PW
echo "Akun rpkd_app, rpkd_migrasi, rpkd_backup siap (password sesuai file secret)."
