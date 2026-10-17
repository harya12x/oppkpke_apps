# docker/ — RPKD Kotabaru (OPPKPKE) di vps1

Semua yang dibutuhkan vps1 untuk menjalankan aplikasi ini. Langkah lengkap:
[`docs/DEPLOY-VPS1.md`](../docs/DEPLOY-VPS1.md).

| Path | Isi |
|---|---|
| `php/` | image `rpkd-php` (Dockerfile multi-stage, entrypoint, php.ini, pool FPM) |
| `compose.yaml` | project `rpkd`: app-blue/green, worker, scheduler, migrasi, mysql |
| `mysql/` | konfigurasi MySQL 8.4 dan akun `rpkd_app` / `rpkd_migrasi` / `rpkd_backup` |
| `nginx/rpkd.conf` | situs untuk Nginx edge bersama (disalin `vps1-edge.sh` dari repo PMB) |
| `cd/rpkd.conf` | konfigurasi `vps1-cd` (tarik tag `rilis`, persetujuan Telegram) |
| `host/` | skrip root di vps1: `rpkd-deploy`, `rpkd-migrasi`, `rpkd-uji-asap`, `rpkd-env-siapkan`, `rpkd-admin-pertama`, `vps1-rpkd-*.sh` |

`docker-compose.yml`, `Dockerfile`, dan `nginx/default.conf` lama (server sebelum
vps1) tidak dipakai di sini.

Setelah isi folder ini berubah: unggah ulang ke vps1, `vps1-rpkd-siapkan.sh <folder>`,
dan kalau `nginx/rpkd.conf` berubah: `bash /opt/pmb/src/docker/edge/vps1-edge.sh sinkron`.
