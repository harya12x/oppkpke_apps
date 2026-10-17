# Panduan Setup OPPKPKE di VPS AlmaLinux (Docker) — untuk Pemula

Panduan ini khusus **AlmaLinux 8 / 9** (keluarga RHEL/CentOS). Mengantar Anda
dari **VPS kosong** sampai aplikasi **hidup di internet dengan HTTPS**. Cukup
salin-tempel; tiap bagian ada penjelasan **"📌 Apa ini?"**.

> Versi Ubuntu ada di [`SETUP-VPS.md`](SETUP-VPS.md). **Untuk AlmaLinux, pakai
> file ini.** Perintah OS-nya berbeda (dnf, firewalld, wheel, SELinux).

> Operasi harian setelah aplikasi hidup: lihat [`deploy/README.md`](../deploy/README.md).

---

## 0. Gambaran besar (baca dulu 1 menit)

Aplikasi berjalan dalam **container** yang diatur **Docker**:

```
                    Internet (pengguna)
                          │  HTTPS (443)
                          ▼
   ┌─────────────────────────────────────────────┐
   │  VPS AlmaLinux                                 │
   │   ┌── Docker ────────────────────────────┐    │
   │   │  [nginx]  → pintu depan + HTTPS       │    │
   │   │  [app]    → PHP/Laravel (mesin utama) │    │
   │   │  [worker] → tugas latar (email dll)   │    │
   │   │  [scheduler] → tugas terjadwal        │    │
   │   └──────────────────────────────────────┘    │
   │                    │                           │
   │              [MySQL]  ← database di HOST,       │
   │                          BUKAN di Docker        │
   └───────────────────────────────────────────────┘
```

**Yang penting dipahami:**

- **MySQL dipasang langsung di server (host)**, bukan di Docker. Container
  menghubunginya lewat alamat `host.docker.internal`.
- **Kode aplikasi "dipanggang" ke image** saat `docker compose build` → tiap
  ganti kode wajib build ulang.
- **AlmaLinux memakai `firewalld` + `SELinux`** — dua hal khas RHEL yang tidak
  ada di Ubuntu; sudah ditangani di panduan ini.

### Yang perlu disiapkan

| Kebutuhan | Keterangan |
|---|---|
| **VPS** | AlmaLinux 8/9, minimal **2 GB RAM**, 2 vCPU, 25 GB disk |
| **Akses root** | login `root@IP_VPS` (dari rebuild/panel) |
| **Domain** | mis. `domainanda.id` — wajib untuk HTTPS |
| **DNS** | domain diarahkan ke IP VPS (Langkah 1) |

> **Ganti placeholder.** Teks HURUF BESAR seperti `DOMAIN_ANDA`, `IP_VPS`,
> `PASSWORD_DB_ANDA` **harus Anda ganti** dengan nilai asli. Jangan disalin apa
> adanya.

> **Cek versi AlmaLinux Anda:** `cat /etc/almalinux-release`

---

## 1. Arahkan domain ke VPS (DNS)

📌 **Apa ini?** Agar domain menunjuk ke server, buat **A record** di pengaturan
domain (tempat beli domain: Cloudflare, Niagahoster, dll).

| Type | Name | Value |
|---|---|---|
| A | `@`   | `IP_VPS` |
| A | `www` | `IP_VPS` |

Tunggu 5–30 menit, cek dari komputer Anda (bukan server):

```bash
ping domainanda.id
```

Balasannya harus menampilkan `IP_VPS`. **Jangan lanjut sebelum ini benar** — SSL
akan gagal kalau domain belum mengarah ke server.

---

## 2. Login root & amankan dasar

📌 **Apa ini?** Perbarui sistem, setel zona waktu, buat pengguna biasa (bukan
root), dan nyalakan firewall.

### 2a. Login & perbarui

```bash
ssh root@IP_VPS
dnf update -y
timedatectl set-timezone Asia/Makassar     # WITA (Kalimantan Selatan)
```

### 2b. Buat pengguna baru + beri hak admin

📌 Di AlmaLinux, membuat user **tidak menanyakan password** — kita set terpisah
dengan `passwd`. Grup admin namanya **`wheel`** (bukan `sudo` seperti Ubuntu).

```bash
adduser deployer
passwd deployer                  # ketik password baru untuk deployer (2x)
usermod -aG wheel deployer       # beri hak sudo
```

### 2c. Firewall (firewalld)

📌 AlmaLinux memakai **firewalld**. Kita buka hanya SSH, HTTP, HTTPS.

```bash
systemctl enable --now firewalld
firewall-cmd --permanent --add-service=ssh
firewall-cmd --permanent --add-service=http
firewall-cmd --permanent --add-service=https
firewall-cmd --reload
firewall-cmd --list-all          # pastikan ssh, http, https ada
```

> 📌 Port database 3306 **tidak** dibuka ke internet. Nanti (Langkah 4) kita
> hanya izinkan container Docker mengaksesnya secara internal.

### 2d. Lanjut sebagai pengguna baru

```bash
exit                             # keluar dari root
ssh deployer@IP_VPS              # masuk sebagai deployer
```

Mulai sini, perintah dijalankan sebagai `deployer` (pakai `sudo` bila perlu hak
admin).

---

## 3. Pasang Docker

📌 **Apa ini?** Docker menjalankan container. Kita pasang Docker CE resmi + plugin
Compose.

```bash
# Tambahkan repo Docker resmi (AlmaLinux memakai repo CentOS milik Docker)
sudo dnf -y install dnf-plugins-core
sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo

# Pasang Docker + Compose
sudo dnf -y install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Nyalakan Docker + otomatis saat boot
sudo systemctl enable --now docker

# Izinkan 'deployer' memakai Docker tanpa sudo
sudo usermod -aG docker $USER
newgrp docker                    # terapkan grup (atau logout–login lagi)

# Uji — harus muncul "Hello from Docker!"
docker run --rm hello-world
docker compose version
```

---

## 4. Pasang & siapkan MySQL (di host)

📌 **Apa ini?** Database penyimpan data. Dipasang langsung di server; container
menghubunginya via `host.docker.internal`.

### 4a. Pasang MySQL

📌 Perhatikan: di AlmaLinux nama service-nya **`mysqld`** (bukan `mysql`).

```bash
sudo dnf -y install mysql-server
sudo systemctl enable --now mysqld
```

### 4b. Izinkan MySQL menerima koneksi dari container

Buka konfigurasi:

```bash
sudo nano /etc/my.cnf.d/mysql-server.cnf
```

Di bawah baris `[mysqld]`, tambahkan (atau pastikan ada):

```
bind-address=0.0.0.0
```

Simpan (`Ctrl+O`, `Enter`, `Ctrl+X`), lalu restart:

```bash
sudo systemctl restart mysqld
```

### 4c. Izinkan Docker menembus firewall ke MySQL

📌 **Apa ini?** Container Docker punya alamat jaringan sendiri (rentang
`172.16–172.31`). Firewalld memblokirnya secara default. Kita izinkan **hanya**
container mengakses port 3306 — dari internet tetap tertutup.

```bash
sudo firewall-cmd --permanent --add-rich-rule='rule family="ipv4" source address="172.16.0.0/12" port port="3306" protocol="tcp" accept'
sudo firewall-cmd --reload
```

### 4d. Buat database & pengguna aplikasi

Ganti `PASSWORD_DB_ANDA` dengan password kuat (catat baik-baik):

```bash
sudo mysql
```

Di prompt `mysql>`, tempelkan (ganti password dulu):

```sql
CREATE DATABASE oppkpke_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'oppkpke'@'%' IDENTIFIED BY 'PASSWORD_DB_ANDA';
GRANT ALL PRIVILEGES ON oppkpke_db.* TO 'oppkpke'@'%';
FLUSH PRIVILEGES;
EXIT;
```

---

## 5. Ambil kode aplikasi

```bash
sudo dnf -y install git
sudo mkdir -p /var/www
sudo chown -R $USER:$USER /var/www
cd /var/www
git clone https://github.com/AKUN_ANDA/oppkpke_apps.git
cd oppkpke_apps
```

> Ganti URL dengan repo Anda. Repo privat → pakai URL SSH (`git@github.com:...`)
> dan pasang SSH key di server dulu.

---

## 6. Buat file `.env`

📌 **Apa ini?** `.env` menyimpan pengaturan rahasia (kunci aplikasi, sambungan
database). Tidak ikut ke GitHub — dibuat sendiri di server.

```bash
cp .env.example .env
nano .env
```

Samakan isinya (ganti `DOMAIN_ANDA` dan `PASSWORD_DB_ANDA`):

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

# Email notifikasi. Sementara boleh 'log' (tidak benar-benar terkirim).
MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@DOMAIN_ANDA"
MAIL_FROM_NAME="${APP_NAME}"
```

Simpan (`Ctrl+O`, `Enter`, `Ctrl+X`).

> ⚠️ **`APP_DEBUG=false` wajib di produksi.** Kalau `true`, pesan error bisa
> membocorkan kredensial ke pengunjung.

### 6b. Buat APP_KEY

📌 Kunci enkripsi unik. Karena `.env` di-mount **read-only** ke container, kita
cetak kuncinya lalu tempel manual.

```bash
docker compose build app                                  # ± 5–10 menit (sekali)
docker compose run --rm app php artisan key:generate --show
```

Salin **seluruh** baris hasilnya (contoh `base64:AbCd....=`), lalu masukkan ke
`.env`:

```bash
nano .env
# ubah:  APP_KEY=base64:AbCd....=
```

---

## 7. Sesuaikan domain di nginx

📌 File `docker/nginx/default.conf` masih memakai domain lama. Ganti ke domain
Anda:

```bash
sed -i 's/rpkdkabkotabaru\.id/DOMAIN_ANDA/g' docker/nginx/default.conf
grep server_name docker/nginx/default.conf      # pastikan sudah domain Anda
```

---

## 8. Ambil sertifikat SSL (HTTPS gratis)

📌 SSL membuat gembok HTTPS via **Let's Encrypt**. Harus diambil **sebelum**
nginx menyala (config nginx butuh file sertifikatnya ada dulu).

```bash
# certbot ada di repo EPEL
sudo dnf -y install epel-release
sudo dnf -y install certbot

# Ambil sertifikat (certbot memakai port 80 sementara — pastikan belum dipakai).
sudo certbot certonly --standalone \
  -d DOMAIN_ANDA -d www.DOMAIN_ANDA \
  --agree-tos -m email@anda.com --no-eff-email
```

Berhasil → sertifikat ada di `/etc/letsencrypt/live/DOMAIN_ANDA/` (otomatis
dibaca nginx via mount di `docker-compose.yml`).

> **Gagal?** Biasanya DNS (Langkah 1) belum mengarah, atau port 80 tertutup
> (Langkah 2c). Perbaiki lalu ulangi.

---

## 9. Nyalakan aplikasi

```bash
cd /var/www/oppkpke_apps

# 1) Build image final
docker compose build

# 2) Jalankan semua container di latar belakang
docker compose up -d

# 3) Salin aset tampilan (CSS/JS) dari image ke host — WAJIB, agar nginx
#    bisa menyajikannya. Tanpa ini tampilan berantakan tanpa gaya.
rm -rf public/build
CID=$(docker create oppkpke_apps-app:latest)
docker cp "$CID":/var/www/public/build ./public/build
docker rm "$CID"

# 4) Buat semua tabel database
docker compose exec app php artisan migrate --force

# 5) Isi data awal: strategi + perangkat daerah + akun contoh
docker compose exec app php artisan db:seed --force

# 6) Optimalkan
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache

# 7) Pastikan semua "Up"
docker compose ps
```

Kalau `app`, `worker`, `scheduler`, `nginx` semua **Up**, buka di browser:

```
https://DOMAIN_ANDA
```

---

## 10. Amankan akun admin (WAJIB)

📌 Langkah 9 mengisi **akun contoh** berpassword bawaan yang diketahui umum.
Untuk produksi **wajib** diganti.

Akun master default: **`admin@oppkpke.go.id`**. Ganti passwordnya:

```bash
docker compose exec app php artisan tinker
```

Di dalam tinker (ganti password baru yang kuat):

```php
$u = App\Models\User::where('email','admin@oppkpke.go.id')->first();
$u->password = Hash::make('PASSWORD_BARU_YANG_KUAT');
$u->save();
exit
```

> Akun operator contoh (`dikbud@`, `dinkes@`, dst) sebaiknya juga diganti/
> dinonaktifkan lewat menu **Kelola Pengguna** setelah login sebagai admin.

---

## 11. Perpanjangan SSL otomatis

📌 Sertifikat berlaku 90 hari. Kita jadwalkan perpanjangan otomatis. Karena
nginx (Docker) memakai port 80, saat memperpanjang nginx **berhenti beberapa
detik** lalu menyala lagi (dijadwalkan jam 3 pagi, praktis tanpa gangguan).

```bash
sudo crontab -e
```

Tambahkan satu baris (ganti path bila repo Anda beda):

```
0 3 * * * certbot renew --quiet --standalone --pre-hook "cd /var/www/oppkpke_apps && docker compose stop nginx" --post-hook "cd /var/www/oppkpke_apps && docker compose start nginx"
```

Uji simulasinya:

```bash
cd /var/www/oppkpke_apps && docker compose stop nginx
sudo certbot renew --dry-run --standalone
docker compose start nginx
```

---

## 12. Cek akhir & pemecahan masalah

### Cek cepat

```bash
docker compose ps                    # semua Up?
docker compose logs -f app           # log aplikasi (Ctrl+C keluar)
docker compose logs -f nginx         # log web server
```

### Masalah umum (khusus AlmaLinux)

| Gejala | Sebab & solusi |
|---|---|
| Browser "tidak bisa dijangkau" | DNS belum mengarah (L.1) / port 443 belum dibuka (L.2c) |
| **502 Bad Gateway** | Container `app` belum siap → `docker compose logs app` |
| Error database "Connection refused" | `DB_HOST` bukan `host.docker.internal`; atau `bind-address` MySQL belum `0.0.0.0` (L.4b); atau **rich-rule firewalld belum dibuat** (L.4c); atau password DB salah |
| Container "permission denied" saat baca file (SELinux) | Jalankan: `sudo chcon -Rt container_file_t /var/www/oppkpke_apps /etc/letsencrypt` lalu `docker compose restart` |
| Tampilan berantakan (tanpa gaya) | Aset belum disalin → ulangi L.9 poin **3** |
| Sertifikat SSL error | Ulangi L.8; pastikan DNS benar & port 80 bebas |
| Perubahan kode tak muncul | Wajib `docker compose build && docker compose up -d` |

> 📌 **Tentang SELinux:** Docker CE (dari repo resmi) biasanya menjalankan
> container tanpa pembatasan SELinux, jadi umumnya langsung jalan **tanpa**
> mematikan SELinux. **Jangan** matikan SELinux kecuali benar-benar perlu. Kalau
> muncul "permission denied" pada file yang di-mount, cukup jalankan perintah
> `chcon` di tabel di atas (aman, tidak mematikan SELinux).

### Cadangkan database (rutin!)

```bash
mysqldump -u oppkpke -p oppkpke_db > ~/backup-oppkpke-$(date +%F).sql
```

---

## 13. (Opsional) Deploy otomatis dari GitHub

Proyek ini punya `.github/workflows/deploy.yml`: tiap `git push` ke `main`,
server otomatis tarik kode, build, migrasi, dan restart container.

Di GitHub repo → **Settings → Secrets and variables → Actions**, tambahkan:

| Secret | Isi | Wajib? |
|---|---|---|
| `SSH_HOST` | `IP_VPS` | ya |
| `SSH_USER` | `root` | ya |
| `SSH_KEY`  | private key SSH yang public-key-nya dipasang di `/root/.ssh/authorized_keys` | ya |
| `SSH_PORT` | port SSH, **hanya bila bukan 22** | tidak |

Langkah menyiapkannya **di server lebih dulu** (memeriksa `PermitRootLogin`,
membuat kunci, memasangnya, mengecek port) ada lengkap di
[`SETUP-VPS.md` bagian 13a](SETUP-VPS.md#13a-siapkan-di-server-dulu-ssh_user-ssh_port-ssh_key)
— sama persis untuk AlmaLinux, dengan tiga penyesuaian:

| Hal | Ubuntu | AlmaLinux |
|---|---|---|
| Restart SSH | `systemctl restart ssh` | `systemctl restart sshd` |
| Log autentikasi | `/var/log/auth.log` | `/var/log/secure` (atau `journalctl -u sshd -n 50`) |
| Firewall port baru | `ufw allow 2222/tcp` | `firewall-cmd --permanent --add-port=2222/tcp && firewall-cmd --reload` |

SELinux aktif di AlmaLinux: bila kunci sudah benar tapi tetap ditolak, perbaiki
label berkas SSH dengan `restorecon -Rv /root/.ssh`.

Detail perintah harian: [`deploy/README.md`](../deploy/README.md).

---

## Ringkasan perbedaan Ubuntu → AlmaLinux

| Hal | Ubuntu | AlmaLinux (panduan ini) |
|---|---|---|
| Paket | `apt install` | `dnf install` |
| Firewall | `ufw` | `firewalld` (`firewall-cmd`) |
| Grup admin | `sudo` | `wheel` |
| Set password user | otomatis saat `adduser` | `passwd deployer` |
| Service MySQL | `mysql` | `mysqld` |
| certbot | dari apt | dari EPEL (`dnf install epel-release certbot`) |
| Keamanan ekstra | — | **SELinux** (biarkan aktif) + rich-rule firewalld untuk MySQL |

---

## Checklist keamanan

- [ ] `APP_DEBUG=false` dan `APP_ENV=production`
- [ ] Password admin default **sudah diganti** (L.10)
- [ ] Firewalld aktif; hanya ssh/http/https terbuka (3306 hanya untuk Docker)
- [ ] Password database kuat & tidak dibagikan
- [ ] Bekerja sebagai `deployer` (bukan root) sehari-hari
- [ ] SELinux tetap aktif (enforcing)
- [ ] Backup database terjadwal
- [ ] Perpanjangan SSL otomatis aktif (L.11)

Selamat — aplikasi OPPKPKE Anda tayang aman di AlmaLinux. 🎉
