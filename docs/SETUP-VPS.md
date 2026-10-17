# Panduan Setup OPPKPKE di VPS Baru (Docker) — untuk Pemula

Panduan ini mengantar Anda dari **VPS yang masih kosong** sampai aplikasi
**hidup di internet dengan HTTPS**. Ditulis selangkah demi selangkah — cukup
salin-tempel perintahnya. Setiap bagian ada penjelasan **"📌 Apa ini?"** agar
Anda paham, bukan sekadar mengikuti.

> Untuk operasi harian setelah aplikasi hidup (deploy rilis baru, lihat log,
> job antrean), lihat [`deploy/README.md`](../deploy/README.md).

---

## 0. Gambaran besar (baca dulu 1 menit)

Aplikasi ini berjalan dalam **kotak-kotak terisolasi bernama _container_** yang
diatur oleh **Docker**. Susunannya:

```
                    Internet (pengguna)
                          │  HTTPS (443)
                          ▼
   ┌─────────────────────────────────────────────┐
   │  VPS Anda (mis. Ubuntu 22.04)                 │
   │                                               │
   │   ┌── Docker ────────────────────────────┐    │
   │   │  [nginx]  → penerima tamu + HTTPS     │    │
   │   │     │                                 │    │
   │   │  [app]    → PHP/Laravel (mesin utama) │    │
   │   │  [worker] → tugas latar (email dll)   │    │
   │   │  [scheduler] → tugas terjadwal        │    │
   │   └──────────────────────────────────────┘    │
   │                    │                           │
   │              [MySQL]  ← database (di HOST,     │
   │                          BUKAN di dalam Docker)│
   └───────────────────────────────────────────────┘
```

**Yang penting dipahami:**

- **nginx** = pintu depan. Menerima HTTPS, menyajikan file statis, meneruskan
  ke PHP.
- **app** = otak aplikasi (Laravel + PHP 8.3).
- **worker / scheduler** = pekerja latar (kirim notifikasi, bersih-bersih data
  terjadwal). Otomatis, tak perlu disentuh.
- **MySQL (database)** dipasang **langsung di server (host)**, bukan di dalam
  Docker. Container Docker menghubunginya lewat alamat khusus
  `host.docker.internal`.
- **Kode aplikasi "dipanggang" (baked) ke dalam image** saat `docker compose
  build`. Artinya setiap ganti kode → wajib build ulang.

### Yang perlu Anda siapkan sebelum mulai

| Kebutuhan | Keterangan |
|---|---|
| **VPS** | Ubuntu 22.04 / 24.04 LTS, minimal **2 GB RAM**, 2 vCPU, 25 GB disk |
| **Akses root/SSH** | IP server + password/SSH key dari penyedia VPS |
| **Domain** | mis. `domainanda.id` — **wajib** untuk HTTPS |
| **DNS** | Arahkan domain ke IP VPS (dijelaskan di Langkah 1) |
| **Waktu** | Sekitar 45–60 menit |

> **Ganti placeholder.** Di panduan ini, teks HURUF BESAR seperti
> `DOMAIN_ANDA`, `IP_VPS`, `PASSWORD_DB_ANDA` **harus Anda ganti** dengan nilai
> asli Anda. Jangan disalin apa adanya.

---

## 1. Arahkan domain ke VPS (DNS)

📌 **Apa ini?** Agar `domainanda.id` menunjuk ke server Anda, kita buat "papan
petunjuk" bernama **A record** di pengaturan domain (di tempat Anda beli domain:
Niagahoster, Cloudflare, dll).

Buat 2 record:

| Type | Name | Value (isi) |
|---|---|---|
| A | `@`   | `IP_VPS` |
| A | `www` | `IP_VPS` |

Tunggu 5–30 menit. Cek dari komputer Anda (bukan server):

```bash
ping domainanda.id
```

Kalau balasannya menampilkan `IP_VPS`, DNS sudah benar. **Jangan lanjut sebelum
ini benar** — SSL nanti akan gagal kalau domain belum mengarah ke server.

---

## 2. Masuk ke server & amankan dasar

📌 **Apa ini?** Login pertama ke server, perbarui sistem, buat pengguna biasa
(bukan root), aktifkan firewall, dan setel zona waktu. Ini pondasi keamanan.

### 2a. Login sebagai root

Dari komputer Anda:

```bash
ssh root@IP_VPS
```

### 2b. Perbarui sistem & setel zona waktu

```bash
apt update && apt upgrade -y
timedatectl set-timezone Asia/Makassar     # WITA (Kalimantan Selatan)
```

### 2c. Buat pengguna baru (jangan kerja sebagai root)

Ganti `deployer` bila mau nama lain:

```bash
adduser deployer                 # isi password saat diminta, sisanya Enter
usermod -aG sudo deployer        # beri hak admin (sudo)
```

### 2d. Firewall (UFW) — hanya buka pintu yang perlu

```bash
apt install -y ufw                # pasang UFW (VPS fresh biasanya belum punya)
ufw allow OpenSSH                 # port 22 (agar tidak terkunci!)
ufw allow 80/tcp                  # HTTP
ufw allow 443/tcp                 # HTTPS
ufw --force enable
ufw status                        # pastikan 22, 80, 443 = ALLOW
```

> 📌 Port database **3306 sengaja tidak dibuka** ke internet. Database hanya
> diakses dari dalam server, jadi aman.

### 2e. Lanjut sebagai pengguna baru

```bash
exit                             # keluar dari root
ssh deployer@IP_VPS              # masuk lagi sebagai deployer
```

Mulai sekarang, semua perintah dijalankan sebagai `deployer`.

---

## 3. Pasang Docker

📌 **Apa ini?** Docker adalah program yang menjalankan container. Kita pasang
Docker + plugin Compose (untuk membaca `docker-compose.yml`).

```bash
# Pasang Docker resmi (skrip otomatis)
curl -fsSL https://get.docker.com | sudo sh

# Izinkan 'deployer' memakai Docker tanpa sudo tiap kali
sudo usermod -aG docker $USER

# Terapkan perubahan grup (atau logout–login lagi)
newgrp docker

# Uji — harus muncul "Hello from Docker!"
docker run --rm hello-world
docker compose version
```

Kalau `hello-world` sukses, Docker siap.

---

## 4. Pasang & siapkan database MySQL (di host)

📌 **Apa ini?** Database menyimpan semua data aplikasi. Aplikasi ini memakai
**MySQL yang terpasang langsung di server** (bukan dalam Docker). Container
aplikasi menghubunginya lewat `host.docker.internal`.

### 4a. Pasang MySQL

```bash
sudo apt install -y mysql-server
sudo systemctl enable --now mysql
```

### 4b. Izinkan container Docker menghubungi MySQL

📌 Secara bawaan MySQL hanya mau menerima koneksi dari dirinya sendiri
(`127.0.0.1`). Container Docker datang dari alamat lain, jadi kita longgarkan —
tetap aman karena firewall (Langkah 2d) menutup port 3306 dari internet.

```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

Cari baris `bind-address` dan ubah menjadi:

```
bind-address = 0.0.0.0
```

Simpan (`Ctrl+O`, `Enter`) lalu keluar (`Ctrl+X`). Restart MySQL:

```bash
sudo systemctl restart mysql
```

### 4c. Buat database & pengguna aplikasi

Ganti `PASSWORD_DB_ANDA` dengan password kuat buatan Anda (catat baik-baik):

```bash
sudo mysql
```

Di dalam prompt `mysql>`, tempelkan (ganti password dulu):

```sql
CREATE DATABASE oppkpke_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'oppkpke'@'%' IDENTIFIED BY 'PASSWORD_DB_ANDA';
GRANT ALL PRIVILEGES ON oppkpke_db.* TO 'oppkpke'@'%';
FLUSH PRIVILEGES;
EXIT;
```

> 📌 `'oppkpke'@'%'` artinya pengguna `oppkpke` boleh terhubung dari mana saja
> **di dalam server**. Karena port 3306 ditutup firewall, dari internet tetap
> tidak bisa. Password kuat tetap wajib.

### 4d. Izinkan Docker menembus firewall ke MySQL

📌 **Apa ini?** Container Docker punya alamat jaringan sendiri (rentang
`172.16–172.31`). UFW memblokirnya secara default, sehingga aplikasi bisa gagal
konek ke database ("Connection refused"). Kita izinkan **hanya** container
mengakses port 3306 — dari internet tetap tertutup.

```bash
sudo ufw allow from 172.16.0.0/12 to any port 3306 proto tcp
sudo ufw reload
sudo ufw status                  # 3306 hanya ALLOW dari 172.16.0.0/12
```

---

## 5. Ambil kode aplikasi

📌 **Apa ini?** Menyalin kode program dari GitHub ke server, ke folder
`/var/www/oppkpke_apps` (lokasi standar proyek ini).

```bash
sudo apt install -y git
sudo mkdir -p /var/www
sudo chown -R $USER:$USER /var/www
cd /var/www
git clone https://github.com/AKUN_ANDA/oppkpke_apps.git
cd oppkpke_apps
```

> Ganti URL dengan repo Anda. Kalau repo privat, gunakan URL SSH
> (`git@github.com:...`) dan pasang SSH key di server dulu.

---

## 6. Buat file konfigurasi `.env`

📌 **Apa ini?** `.env` menyimpan pengaturan rahasia (kunci aplikasi, sambungan
database). File ini **tidak** ikut ke GitHub — Anda buat sendiri di server.

Salin contoh lalu edit:

```bash
cp .env.example .env
nano .env
```

Samakan isinya seperti di bawah (ganti `DOMAIN_ANDA` dan `PASSWORD_DB_ANDA`):

```ini
APP_NAME="OPPKPKE"
APP_ENV=production
APP_KEY=                                   # diisi di langkah 6b
APP_DEBUG=false
APP_URL=https://DOMAIN_ANDA

APP_LOCALE=id
APP_TIMEZONE=Asia/Makassar

# Database (di host) — WAJIB host.docker.internal, bukan 127.0.0.1
DB_CONNECTION=mysql
DB_HOST=host.docker.internal
DB_PORT=3306
DB_DATABASE=oppkpke_db
DB_USERNAME=oppkpke
DB_PASSWORD=PASSWORD_DB_ANDA

# Sesi, antrean, cache pakai database (tak perlu Redis)
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
BROADCAST_CONNECTION=log

# Email — untuk notifikasi chat. Isi kredensial SMTP asli Anda.
# Sementara boleh 'log' (email hanya masuk file log, tidak terkirim).
MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@DOMAIN_ANDA"
MAIL_FROM_NAME="${APP_NAME}"
```

Simpan (`Ctrl+O`, `Enter`, `Ctrl+X`).

> ⚠️ **Penting soal keamanan:** `APP_DEBUG=false` **wajib** di produksi. Kalau
> `true`, pesan error bisa membocorkan kredensial ke pengunjung.

### 6b. Buat APP_KEY

📌 **Apa ini?** Kunci enkripsi unik aplikasi (untuk mengamankan sesi & data).
Karena `.env` di-mount **read-only** ke container, kita cetak kuncinya lalu
tempel manual.

Pertama, build dulu image-nya (lihat Langkah 8 kalau ingin paham build), lalu:

```bash
docker compose build app                                  # ± 5–10 menit (sekali)
docker compose run --rm app php artisan key:generate --show
```

Perintah terakhir mencetak sesuatu seperti:

```
base64:AbCdEf0123....xyz=
```

Salin **seluruh** baris itu (termasuk `base64:`), lalu masukkan ke `.env`:

```bash
nano .env
# ubah baris:  APP_KEY=base64:AbCdEf0123....xyz=
```

---

## 7. Sesuaikan domain di konfigurasi nginx

📌 **Apa ini?** File `docker/nginx/default.conf` bawaan memakai domain
`rpkdkabkotabaru.id`. Kalau domain Anda berbeda, ganti ke domain Anda.

> ⏭️ **Kalau domain Anda memang `rpkdkabkotabaru.id`, LEWATI langkah ini** —
> file sudah benar apa adanya.

Cara aman (isi domain Anda **satu kali** di variabel, lalu jalankan):

```bash
DOMAIN=domainanda.id                              # ← GANTI dengan domain asli Anda (tanpa https://, tanpa www)

sed -i "s/rpkdkabkotabaru\.id/$DOMAIN/g" docker/nginx/default.conf
grep -E 'server_name|ssl_certificate' docker/nginx/default.conf   # cek: harus domain Anda, TANPA https://
```

> ⚠️ **Jangan** menaruh `https://` di sini — `server_name` hanya nama domain.
> Tanda `/` pada `https://` juga akan merusak perintah `sed`.
>
> **Salah menjalankan** dan `DOMAIN_ANDA` telanjur masuk ke file? Kembalikan:
> ```bash
> sed -i 's/DOMAIN_ANDA/domainanda.id/g' docker/nginx/default.conf
> ```

---

## 8. Ambil sertifikat SSL (HTTPS gratis)

📌 **Apa ini?** SSL membuat gembok HTTPS. Kita ambil sertifikat gratis dari
**Let's Encrypt** memakai **certbot**. Ini harus dilakukan **sebelum** nginx
menyala, karena konfigurasi nginx butuh file sertifikatnya ada dulu.

```bash
sudo apt install -y certbot

# Ambil sertifikat (certbot memakai port 80 sementara — pastikan belum ada
# yang memakainya). Ganti DOMAIN_ANDA & email Anda.
sudo certbot certonly --standalone \
  -d DOMAIN_ANDA -d www.DOMAIN_ANDA \
  --agree-tos -m email@anda.com --no-eff-email
```

Kalau berhasil, muncul lokasi sertifikat:
`/etc/letsencrypt/live/DOMAIN_ANDA/`. Folder ini nanti otomatis dibaca nginx
(sudah di-mount di `docker-compose.yml`).

> **Gagal?** Biasanya karena DNS (Langkah 1) belum mengarah ke server, atau port
> 80 belum dibuka (Langkah 2d). Perbaiki lalu ulangi.

---

## 9. Nyalakan aplikasi

📌 **Apa ini?** Membangun sisa image, menjalankan semua container, membuat tabel
database, mengisi data awal, dan menyalin aset tampilan.

```bash
cd /var/www/oppkpke_apps

# 1) Build image final (kalau Langkah 6b sudah build, ini cepat)
docker compose build

# 2) Jalankan semua container di latar belakang
docker compose up -d

# 3) Salin aset tampilan (CSS/JS hasil build) dari image ke host,
#    supaya nginx bisa menyajikannya. (Langkah ini WAJIB.)
rm -rf public/build
CID=$(docker create oppkpke_apps-app:latest)
docker cp "$CID":/var/www/public/build ./public/build
docker rm "$CID"

# 4) Buat semua tabel database
docker compose exec app php artisan migrate --force

# 5) Isi data awal: 3 strategi + daftar perangkat daerah + akun contoh
docker compose exec app php artisan db:seed --force

# 6) Optimalkan (cache konfigurasi, rute, tampilan)
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache

# 7) Pastikan semua container "Up"
docker compose ps
```

Kalau `docker compose ps` menunjukkan `app`, `worker`, `scheduler`, `nginx`
semua **Up**, aplikasi sudah hidup. Buka di browser:

```
https://DOMAIN_ANDA
```

---

## 10. Amankan akun admin (WAJIB, jangan dilewati)

📌 **Apa ini?** Langkah 9 mengisi **akun contoh** dengan password bawaan yang
diketahui umum. Untuk produksi Anda **harus** menggantinya, jika tidak siapa pun
bisa masuk.

Akun master default: **`admin@oppkpke.go.id`**. Ganti passwordnya:

```bash
docker compose exec app php artisan tinker
```

Di dalam tinker, tempelkan (ganti password baru yang kuat):

```php
$u = App\Models\User::where('email','admin@oppkpke.go.id')->first();
$u->password = Hash::make('PASSWORD_BARU_YANG_KUAT');
$u->save();
exit
```

> Akun operator contoh (`dikbud@`, `dinkes@`, dst) sebaiknya juga diganti
> password-nya atau dinonaktifkan lewat menu **Kelola Pengguna** setelah login
> sebagai admin.

---

## 11. Perpanjangan SSL otomatis

📌 **Apa ini?** Sertifikat Let's Encrypt berlaku 90 hari. Kita jadwalkan
perpanjangan otomatis agar tidak pernah kedaluwarsa.

📌 **Kenapa nginx dihentikan sesaat?** Kita mengambil sertifikat dengan metode
`--standalone` yang butuh port 80. Padahal nginx (Docker) sudah memakai port 80.
Jadi saat memperpanjang, nginx **berhenti beberapa detik**, certbot bekerja, lalu
nginx menyala lagi. Karena dijadwalkan jam 3 pagi, praktis tanpa gangguan.

```bash
sudo crontab -e
```

Tambahkan satu baris di bawah (ganti path bila repo Anda beda):

```
0 3 * * * certbot renew --quiet --standalone --pre-hook "cd /var/www/oppkpke_apps && docker compose stop nginx" --post-hook "cd /var/www/oppkpke_apps && docker compose start nginx"
```

Uji simulasinya (juga menghentikan nginx sesaat, lalu menyalakannya lagi):

```bash
cd /var/www/oppkpke_apps && docker compose stop nginx
sudo certbot renew --dry-run --standalone
docker compose start nginx
```

Setiap jam 3 pagi sistem mengecek; kalau kurang dari 30 hari, memperpanjang
otomatis.

---

## 12. Selesai! Cek akhir & pemecahan masalah

### Cek cepat

```bash
docker compose ps                    # semua Up?
docker compose logs -f app           # log aplikasi (Ctrl+C untuk keluar)
docker compose logs -f nginx         # log web server
```

### Masalah umum

| Gejala | Kemungkinan sebab & solusi |
|---|---|
| Browser "tidak bisa dijangkau" | DNS (Langkah 1) belum mengarah / firewall 443 belum dibuka (Langkah 2d) |
| Halaman **502 Bad Gateway** | Container `app` belum siap. `docker compose logs app` untuk lihat error |
| Error database / "Connection refused" | `DB_HOST` bukan `host.docker.internal`; atau `bind-address` MySQL belum `0.0.0.0` (Langkah 4b); atau **aturan UFW 3306 belum dibuat** (Langkah 4d); atau password DB salah |
| Tampilan berantakan (tanpa gaya) | Aset belum disalin — ulangi Langkah 9 poin **3** (`docker cp ... public/build`) |
| Sertifikat SSL error | Ulangi Langkah 8; pastikan DNS benar & port 80 bebas |
| Perubahan kode tak muncul | Wajib `docker compose build && docker compose up -d` (kode dipanggang ke image) |

### Cadangkan database (lakukan rutin!)

```bash
# Backup ke file (ganti password saat diminta)
mysqldump -u oppkpke -p oppkpke_db > ~/backup-oppkpke-$(date +%F).sql
```

---

## 13. (Opsional) Deploy otomatis dari GitHub

Proyek ini sudah punya `.github/workflows/deploy.yml`: setiap kali Anda `git
push` ke branch `main`, server **otomatis** menarik kode, build, migrasi, dan
menyalakan ulang container.

GitHub masuk ke server persis seperti Anda `ssh` dari laptop — hanya saja ia
tidak bisa mengetik password, jadi ia memakai **kunci SSH**. Karena itu urutan
yang benar adalah: **siapkan dulu di server**, baru isi secret di GitHub.

> Panduan di bawah memakai pengguna **`root`**. Bila Anda memakai pengguna lain
> (mis. `deployer`), langkahnya sama persis — ganti `root` dengan nama pengguna
> itu dan `/root` dengan `/home/<pengguna>`, lalu pastikan pengguna tersebut ada
> di grup `docker`.

### 13a. Siapkan di server dulu (SSH_USER, SSH_PORT, SSH_KEY)

Login ke VPS sebagai root, lalu kerjakan tiga hal berikut berurutan.

> ⚠️ **Jangan tutup sesi SSH ini sampai semua langkah selesai dan sudah diuji.**
> Kalau ada yang salah, sesi yang masih terbuka adalah jalan masuk cadangan Anda.

#### 1) `SSH_USER` — pengguna yang dipakai GitHub

Nilainya: **`root`**.

Karena root, tidak perlu urusan grup `docker` maupun `sudo` — root sudah bisa
menjalankan `docker compose` yang dipakai skrip deploy. Pastikan saja bisa:

```bash
whoami                  # root
docker compose version  # muncul versinya
```

Satu hal yang **wajib dicek untuk root**: server harus mengizinkan root login
memakai kunci. Ini penyebab penolakan yang paling sering saat memakai root:

```bash
sshd -T | grep -i permitrootlogin
```

| Hasil | Artinya |
|---|---|
| `permitrootlogin prohibit-password` | ✅ Aman & benar — login kunci boleh, password ditolak |
| `permitrootlogin yes` | ✅ Jalan, tapi password ikut diizinkan (kurang aman) |
| `permitrootlogin no` | ❌ **Semua** login root ditolak, termasuk lewat kunci — inilah sebabnya deploy gagal |

Kalau hasilnya `no`, ubah:

```bash
nano /etc/ssh/sshd_config          # cari baris PermitRootLogin, jadikan:
                                   # PermitRootLogin prohibit-password
sshd -t                            # uji konfigurasi (diam = OK)
systemctl restart ssh              # terapkan (AlmaLinux/CentOS: sshd)
sshd -T | grep -i permitrootlogin  # pastikan sudah berubah
```

> Nilai bisa juga datang dari berkas lain di `/etc/ssh/sshd_config.d/*.conf`
> (banyak image VPS menaruhnya di sana). Kalau setelah diubah `sshd -T` masih
> `no`, cari sumbernya: `grep -ri permitrootlogin /etc/ssh/`

#### 2) `SSH_PORT` — port SSH server

Cek port yang benar-benar dipakai server:

```bash
sshd -T | grep -i '^port'        # contoh keluaran: port 22
```

- Kalau hasilnya **22** → secret `SSH_PORT` **tidak perlu dibuat**; workflow
  memakai 22 secara bawaan.
- Kalau bukan 22 (mis. 2222) → catat angkanya, nanti dibuat secret `SSH_PORT`.

Mau menggantinya jadi port lain (opsional, sekadar mengurangi bot yang mengetuk
port 22)? Buka firewall **lebih dulu**, baru ubah, agar tidak terkunci:

```bash
ufw allow 2222/tcp                 # 1. buka port baru
nano /etc/ssh/sshd_config          # 2. ubah baris: Port 2222
sshd -t                            # 3. uji konfigurasi (diam = OK)
systemctl restart ssh              # 4. terapkan
# 5. UJI dari terminal BARU (jangan tutup yang lama):
#    ssh -p 2222 root@IP_VPS
ufw delete allow OpenSSH           # 6. baru tutup port 22
```

#### 3) `SSH_KEY` — kunci untuk GitHub

Buat sepasang kunci khusus deploy, langsung di server:

```bash
ssh-keygen -t ed25519 -C "github-actions" -f /root/.ssh/gh_deploy -N ""
```

Penjelasan singkat: `-t ed25519` = jenis kunci modern (aman & selalu didukung),
`-N ""` = tanpa passphrase (wajib, karena GitHub tidak bisa mengetikkannya),
`-f` = nama berkasnya. Hasilnya dua berkas:

| Berkas | Isi | Dipasang di |
|---|---|---|
| `gh_deploy.pub` | kunci **publik** — boleh dilihat siapa saja | **server** (`authorized_keys`) |
| `gh_deploy` | kunci **privat** — rahasia | **GitHub** (secret `SSH_KEY`) |

Pasang kunci publiknya ke daftar kunci yang diizinkan, lalu rapikan izin berkas
(SSH menolak diam-diam kalau izinnya terlalu longgar):

```bash
cat /root/.ssh/gh_deploy.pub >> /root/.ssh/authorized_keys
chown -R root:root /root/.ssh
chmod 700 /root/.ssh
chmod 600 /root/.ssh/authorized_keys
chmod 700 /root                       # rumah root tidak boleh writable grup/lainnya
```

Sekarang tampilkan kunci **privat**-nya untuk disalin ke GitHub:

```bash
cat /root/.ssh/gh_deploy
```

Salin **seluruh** keluarannya — termasuk baris
`-----BEGIN OPENSSH PRIVATE KEY-----` dan `-----END OPENSSH PRIVATE KEY-----`,
tanpa spasi tambahan di awal. Setelah tersalin dan deploy sudah berhasil, hapus
salinan privat di server (GitHub sudah menyimpannya):

```bash
rm /root/.ssh/gh_deploy
```

#### 4) Uji dari server sendiri

Pastikan kunci itu memang diterima, sebelum menyentuh GitHub:

```bash
ssh -i /root/.ssh/gh_deploy -o IdentitiesOnly=yes -p 22 root@localhost \
    'echo BERHASIL; whoami; docker compose version'
```

Muncul `BERHASIL` + `root` = beres, lanjut ke GitHub. Kalau masih diminta
password, berarti kunci belum diterima — lihat 13c.

### 13b. Baru isi secret di GitHub

Repo GitHub → **Settings → Secrets and variables → Actions** → *New repository
secret*:

| Secret | Isi | Wajib? |
|---|---|---|
| `SSH_HOST` | IP VPS, mis. `203.0.113.10` | ya |
| `SSH_USER` | `root` | ya |
| `SSH_KEY`  | seluruh isi `gh_deploy` (kunci **privat**, termasuk baris BEGIN/END) | ya |
| `SSH_PORT` | port SSH, **hanya bila bukan 22** | tidak |

Setelah itu, cukup `git push origin main` — server memperbarui diri sendiri.
Detail perintah harian ada di [`deploy/README.md`](../deploy/README.md).

### 13c. Deploy GitHub gagal: ssh handshake failed

Pesan di Actions:

```
ssh: handshake failed: ssh: unable to authenticate,
attempted methods [none publickey], no supported methods remain
```

Artinya: jaringan & port **sudah benar** (server menjawab), tetapi server
**menolak kuncinya**. Skrip deploy sama sekali belum berjalan, jadi tidak ada
hubungannya dengan kode aplikasi.

Jawaban pastinya selalu ada di log server. Jalankan ini di VPS, lalu ulangi
deploy dari GitHub sambil memperhatikan barisnya:

```bash
tail -f /var/log/auth.log        # Ubuntu/Debian
# AlmaLinux/CentOS: tail -f /var/log/secure
```

| Baris di log | Artinya & perbaikannya |
|---|---|
| `ROOT LOGIN REFUSED` / `User root not allowed because ...` | `PermitRootLogin no` — perbaiki seperti di 13a poin 1 |
| Login root langsung terputus dengan pesan `Please login as the user ...` | Image VPS memasang perintah paksa di baris pertama `/root/.ssh/authorized_keys`. Hapus baris berawalan `no-port-forwarding,...command="..."` itu, sisakan kunci Anda |
| `Invalid user xxx` | `SSH_USER` salah — samakan dengan pengguna di server (`root`) |
| `Connection closed by authenticating user ... [preauth]` tanpa keterangan lain | Kunci publik tidak ada di `authorized_keys` pengguna itu — ulangi 13a poin 3 |
| `Authentication refused: bad ownership or modes for directory /root` | Izin berkas terlalu longgar — jalankan ulang perintah `chown`/`chmod` di 13a poin 3 |
| `userauth_pubkey: key type ssh-rsa not in PubkeyAcceptedAlgorithms` | Kunci RSA lama ditolak server baru — buat ulang kunci `ed25519` seperti di 13a poin 3 |
| Tidak ada baris baru sama sekali | Koneksi tidak sampai: IP/port salah, atau firewall memblokir — cek `SSH_HOST`, `SSH_PORT`, dan `ufw status` |

Penyebab yang paling sering, dan cara memastikannya dalam hitungan detik:

1. **`PermitRootLogin no`** di server (khas image VPS yang sudah dikeraskan).
   Cek: `sshd -T | grep -i permitrootlogin`
2. **Yang tersalin ke `SSH_KEY` adalah kunci publik (`.pub`)**, bukan privat.
   Ciri: isinya satu baris diawali `ssh-ed25519`/`ssh-rsa`. Yang benar diawali
   `-----BEGIN OPENSSH PRIVATE KEY-----`.
3. **Kunci tersalin sebagian** — baris BEGIN/END ikut terpotong saat menempel.
4. **Kunci publiknya belum dipasang** di `/root/.ssh/authorized_keys`.
   Cek isinya: `cat /root/.ssh/authorized_keys`
5. **Kunci dibuat dengan passphrase** — GitHub tidak bisa mengisinya. Buat ulang
   dengan `-N ""`.

Workflow sudah memeriksa kesalahan bentuk nomor 2, 3, dan secret kosong sebelum
menyambung, jadi kalau salah satunya terjadi, pesan di Actions langsung
menyebutkannya.

---

## Ringkasan checklist keamanan

- [ ] `APP_DEBUG=false` dan `APP_ENV=production` di `.env`
- [ ] Password admin default **sudah diganti** (Langkah 10)
- [ ] Firewall aktif; hanya 22, 80, 443 terbuka (3306 tertutup)
- [ ] Password database kuat & tidak dibagikan
- [ ] Bekerja sebagai `deployer` (bukan root) sehari-hari
- [ ] Backup database terjadwal
- [ ] Perpanjangan SSL otomatis aktif (Langkah 11)

Selamat — aplikasi OPPKPKE Anda sudah tayang dengan aman. 🎉
