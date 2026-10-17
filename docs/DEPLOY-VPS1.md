# Deploy OPPKPKE (rpkdkabkotabaru.id) di vps1

Dokumen ini menggantikan `docs/SETUP-VPS.md` (server lama, deploy lewat SSH dari
GitHub Actions). Di vps1 aplikasi ini disebut **rpkd**, dengan pola yang sama
dengan PMB dan SIA: image dari CI, deploy ditarik vps1 sendiri, persetujuan lewat
Telegram, blue/green tanpa putus.

## Ringkasan

| Bagian | Di vps1 |
|---|---|
| Kode | image `ghcr.io/harya12x/rpkd-php` (CI, tag `rilis` hanya kalau semua job hijau) |
| Pintu masuk | Nginx edge bersama (`edge-nginx-1`, port 443, hanya IP Cloudflare), situs `docker/nginx/rpkd.conf` |
| Aplikasi | `rpkd-app-blue` / `rpkd-app-green` (php-fpm 8.3, read-only, uid 33) |
| Latar | `rpkd-worker-1` (import Excel, email), `rpkd-scheduler-1` |
| Database | `rpkd-mysql-1` (MySQL 8.4, database `rpkd`, Workbench lewat SSH ke `127.0.0.1:3308`) |
| Akun DB | `rpkd_app` (DML saja), `rpkd_migrasi` (DDL, hanya saat migrate), `rpkd_backup` (baca) |
| Data host | `/srv/rpkd/{secrets,storage,mysql,public,nginx,backup,deploy}` |
| Jaringan | `br-rpkd-edge/back/out/mgmt` = `172.30.21-24.0/24`; keluar internet hanya dari `br-rpkd-out` (443, 587) |
| Deploy | `vps1-cd` (Telegram) → `rpkd-deploy` → `rpkd-migrasi` (dump + `php artisan migrate`) → blue/green → pantau 5 menit |
| Backup | dump sebelum tiap migrasi di `/srv/rpkd/backup` (20 terakhir) + `vps1-backup` harian (restic) |

Database bisa dimulai kosong, atau diisi dump `oppkpke_db` lama lewat
`rpkd-impor-db` (langkah 3). Dump lama sudah memuat semua migration kecuali
migration dasar `0001_01_01_000003`, yang otomatis tidak mengubah apa pun di sana.

## 1. Repo (laptop)

1. Commit perubahan yang masih tertunda di laptop (OppkpkeController, OppkpkeService,
   view import/import_rat/presentasi) bersama berkas baru ini.
2. Workflow: bridge laptop tidak bisa menulis ke `.github/`, jadi berkasnya ada di
   `github-baru/`. Pindahkan, dan hapus workflow lama yang deploy lewat SSH ke server
   lama. Kalau `deploy.yml` tidak ikut dihapus di commit yang sama, push ini juga
   akan dikirim ke server lama.
   - `github-baru/workflows/ci.yml` → `.github/workflows/ci.yml`
   - `github-baru/scripts/cek-huruf-kelas.php` → `.github/scripts/cek-huruf-kelas.php`
   - hapus `.github/workflows/deploy.yml`
   - berkas setup server lama yang tidak dipakai lagi (disarankan dihapus juga):
     `Dockerfile`, `docker-compose.yml`, `docker-compose.override.yml.example`,
     `docker/nginx/default.conf`
3. Push ke `main`, lalu tunggu semua job CI hijau. Job **Rilis** memberi tag `rilis`.
   - Kalau job **Kredensial di riwayat Git** merah: `php tools/rahasia.php pindai` di
     laptop (nilai tidak dicetak). Rotasi dulu, baru `tandai <no> --sudah-dirotasi`.
     Gerbang ini memang memblokir rilis.
4. Paket `rpkd-php` di ghcr ikut privat (repo pribadi). Token yang dipakai `vps1-cd login`
   harus bisa membaca paket milik akun `harya12x` (classic token, `read:packages`).

## 2. Cloudflare (DNS dipindah dari Rumahweb)

1. Tambahkan situs `rpkdkabkotabaru.id` di Cloudflare (paket Free cukup).
2. **Sebelum** mengganti nameserver, cocokkan record hasil impor Cloudflare dengan
   record di Rumahweb, terutama **MX, TXT (SPF/DKIM/DMARC), dan subdomain lain**.
   Record yang hilang berarti email domain itu berhenti.
3. Selama masa peralihan, record `A @` dan `A www` tetap ke server lama
   (`103.247.9.252`) dengan proxy **mati** (awan abu-abu). Dengan begitu pergantian
   nameserver tidak mengubah apa pun bagi pengguna, dan sertifikat Let's Encrypt
   server lama tetap berlaku.
4. Ganti nameserver di panel Rumahweb ke dua nameserver yang diberikan Cloudflare.
   Tunggu sampai status situs di Cloudflare **Active**.
5. SSL/TLS:
   - mode **Full (strict)**;
   - **Origin Server → Create Certificate** untuk `rpkdkabkotabaru.id` dan
     `*.rpkdkabkotabaru.id` (RSA, 15 tahun). Simpan sertifikat sebagai `origin.pem`
     dan kunci sebagai `origin.key`. Kunci hanya tampil sekali dan tidak boleh lewat chat;
   - **Authenticated Origin Pulls**: aktifkan. `aop-ca.pem` = sertifikat CA
     "Cloudflare origin pull" dari dokumentasi Cloudflare (Authenticated Origin Pulls,
     zone-level). Catatan: CA zone-level dipakai bersama semua pelanggan Cloudflare.
     Yang lebih ketat adalah per-hostname dengan sertifikat sendiri (seperti SIA).
     Server ini juga sudah dibatasi `server_name` dan nftables (hanya IP Cloudflare).

## 3. vps1: pemasangan (sebelum DNS dipindah)

Unggah folder `docker/` repo ini lewat WinSCP ke `~/rpkd-docker`, lalu sebagai root:

```bash
sudo bash ~/rpkd-docker/host/vps1-rpkd-nft.sh              # aturan br-rpkd-*, konfirmasi YA
sudo bash ~/rpkd-docker/host/vps1-rpkd-siapkan.sh ~/rpkd-docker
sudo rpkd-env-siapkan                                      # rpkd.env + rpkd-migrasi.env (APP_KEY baru)
```

Sertifikat dari langkah 2.5 ke `/srv/rpkd/secrets/tls/` (lewat WinSCP ke home dulu):

```bash
sudo install -o root -g 101 -m 0640 ~/origin.pem ~/origin.key ~/aop-ca.pem /srv/rpkd/secrets/tls/
shred -u ~/origin.key ~/origin.pem ~/aop-ca.pem
```

Image dan pemasangan pertama (commit = 40 karakter dari main yang CI-nya hijau):

```bash
sudo bash /opt/rpkd/src/docker/host/vps1-rpkd-tarik-image.sh <commit>
```

Dengan data lama (dump `oppkpke_db`, diunggah ke home lewat scp/WinSCP):

```bash
sudo bash /opt/rpkd/src/docker/host/vps1-rpkd-nyalakan.sh --hanya-mysql
sudo rpkd-impor-db /home/<user>/oppkpkedb.sql     # dump dihapus aman setelah impor
```

Lalu (dengan atau tanpa data lama):

```bash
sudo bash /opt/rpkd/src/docker/host/vps1-rpkd-nyalakan.sh   # MySQL, migrate, app-green, worker, scheduler
```

**Data lama: periksa akun berpassword umum sebelum DNS dipindah.** Dump Oktober 2026
memuat 41 akun operator daerah yang masih memakai password bawaan `UserSeeder`.

```bash
sudo rpkd-akun-lemah              # jumlah per role
sudo rpkd-akun-lemah --ganti      # password acak baru -> /root/rpkd/akun-baru-*.csv (0600)
```

Edge bersama (repo PMB, `docker/edge/`): sinkron folder `docker/` PMB terbaru
(yang sudah memuat `rpkd_edge`) seperti biasa, lalu:

```bash
sudo bash /opt/pmb/src/docker/edge/vps1-edge.sh perbarui   # semua situs putus 1-2 detik
```

Admin pertama, hanya untuk database kosong (password acak ke file root-only, tidak dicetak):

```bash
sudo rpkd-admin-pertama <email-admin> "Nama" --dengan-data-master
```

`--dengan-data-master` mengisi 3 strategi, perangkat daerah, program, kegiatan, dan
sub kegiatan (tanpa laporan), dan hanya bisa dipakai di database kosong. **Jangan**
jalankan `php artisan db:seed` biasa: `UserSeeder` membuat 6 akun dengan password
umum `password123`.

Backup harian dan baseline (repo vps1-baseline): pasang `vps1-backup` dan
`vps1-baseline` versi baru ke `/usr/local/sbin` (database `rpkd` ikut didump, dan
`/usr/local/sbin/rpkd-*` ikut dipantau), lalu `sudo vps1-baseline approve`.

## 4. Uji lewat tunnel (sebelum DNS dipindah)

Port uji `9443` hanya ada di `127.0.0.1` vps1, tanpa AOP.

1. Di laptop, buka tunnel SSH: `ssh -L 9443:127.0.0.1:9443 <vps1 lewat Tailscale>`.
2. Tambahkan sementara `127.0.0.1 rpkdkabkotabaru.id` ke file hosts Windows
   (Notepad sebagai Administrator, `C:\Windows\System32\drivers\etc\hosts`).
3. Buka `https://rpkdkabkotabaru.id:9443/`. Peringatan sertifikat itu wajar karena
   origin certificate hanya dipercaya Cloudflare. Login dengan akun admin pertama,
   coba import Excel, chat, dan ekspor PDF.
4. Hapus lagi baris di file hosts.

## 5. Cutover DNS

Dikerjakan saat sudah siap, langkahnya diuraikan di sini (bukan perintah siap tempel):

- Data lama: input di server lama dihentikan dulu, ambil dump terakhir, lalu impor
  ulang (`rpkd-impor-db <dump> --timpa`, nyalakan, `rpkd-akun-lemah --ganti`) supaya
  tidak ada laporan yang tertinggal. Bagikan password baru ke operator.
- Database kosong: umumkan ke operator bahwa akun dibuat ulang oleh Admin Master.
- Di Cloudflare, ubah `A @` dan `A www` ke IP publik vps1 (sama dengan record A
  devpmb/sia) dan **nyalakan proxy** (awan oranye). TTL Auto.
- Pastikan `https://rpkdkabkotabaru.id/` membuka halaman login dan
  `https://www.rpkdkabkotabaru.id/` dialihkan ke domain utama. Log edge:
  `docker logs --since 10m edge-nginx-1 | grep rpkdkabkotabaru.id`.
- Setelah stabil, matikan aplikasi di server lama. Jangan ada dua server yang
  menerima input laporan bersamaan.

## 6. Rilis berikutnya

Push ke `main` → CI hijau → tag `rilis` → `vps1-cd` (cek tiap menit) mengirim pesan
Telegram berisi commit baru → `/setuju_<kode>` → `rpkd-deploy`:

1. aset publik ke warna cadangan;
2. **2b** `rpkd-migrasi`: dump database ke `/srv/rpkd/backup/`, lalu `php artisan
   migrate --force` dengan akun `rpkd_migrasi`;
3. warna cadangan dinyalakan; harus sehat, `/up` lewat edge OK, dan lolos
   `rpkd-uji-asap` (login, database, tidak ada migration Pending);
4. trafik pindah; worker dan scheduler dibuat ulang (import yang sedang berjalan
   diberi waktu selesai sampai 330 detik);
5. `vps1-cd` memantau 5 menit; rasio 5xx tinggi = rollback otomatis.

Catatan: pesan Telegram tidak mendaftar migration Laravel (fitur migrasi vps1-cd
khusus SQL milik SIA). Setiap deploy rpkd selalu membuat dump sebelum migrate.

Aturan migration: rollback kode tidak membatalkan migration, jadi migration baru
harus tetap cocok dengan kode versi sebelumnya (tambah kolom nullable dulu, hapus
kolom di rilis berikutnya).

Perintah berguna:

```bash
sudo rpkd-deploy status
sudo rpkd-deploy rollback             # trafik kembali ke warna sebelumnya (instan)
sudo rpkd-deploy matikan-cadangan     # bebaskan memori warna lama
sudo rpkd-migrasi <tag> --status      # php artisan migrate:status
```

## 7. Pulihkan dari dump

Kalau migration merusak data, pulihkan dump sebelum migrasi. Ini menimpa database
`rpkd`, jadi aplikasi dihentikan dulu supaya tidak ada tulisan baru. Dump-nya ada
di `/srv/rpkd/backup/pra-migrasi-<waktu>-<tag>.sql.gz`, atau di restic
(`mysql/rpkd/rpkd.sql`). Setelah dipulihkan, deploy ulang tag lama
(`rpkd-deploy <tag lama>`). Perintah persisnya disusun saat dibutuhkan, berdasarkan
dump yang dipilih.

## 8. Email notifikasi

`rpkd-env-siapkan` menulis `MAIL_MAILER=log` (email hanya masuk log). Untuk SMTP,
isi `MAIL_*` di `/srv/rpkd/secrets/rpkd.env` (`sudo nano`), lalu
`sudo rpkd-deploy <tag aktif>` supaya config cache dibuat ulang. Port 587 keluar
sudah diizinkan dari `br-rpkd-out`. `rpkd-env-siapkan --timpa` mempertahankan
APP_KEY dan MAIL_*.
