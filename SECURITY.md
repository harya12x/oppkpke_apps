# Keamanan

## Kredensial di Git

CI (job **Kredensial di riwayat Git**) menjalankan gitleaks atas seluruh riwayat
lewat `tools/rahasia.php`. Temuan **memblokir rilis** sampai ditangani:

| Langkah | Perintah / tindakan |
|---|---|
| 1. Lihat temuan (nilai tidak dicetak) | `php tools/rahasia.php pindai` |
| 2. Masih di kode | `php tools/rahasia.php perbaiki`: pindah ke `env()`, nilai lama ke `.env.rahasia-pindah` (diabaikan Git) |
| 3. Server | isi nilainya di `/srv/rpkd/secrets/rpkd.env` di vps1 sebelum kode baru dideploy |
| 4. Rotasi | ganti nilainya di layanan asal dan di `rpkd.env`, lalu `rpkd-deploy <tag aktif>` |
| 5. Tandai | `php tools/rahasia.php tandai <no> --sudah-dirotasi` (atau `--bukan-rahasia "alasan"`), commit `.gitleaksignore` |
| 6. Cegah | `php tools/rahasia.php hook`: commit yang menambah kredensial ditolak |

Fingerprint baru boleh masuk `.gitleaksignore` hanya **setelah** rahasianya dirotasi.

## Produksi

- `.env` produksi hanya ada di vps1 (`/srv/rpkd/secrets/`, root:33 0640), tidak pernah di image atau Git.
- Akun database aplikasi hanya DML. DDL hanya lewat `rpkd-migrasi` dengan akun terpisah.
- Jangan jalankan `php artisan db:seed` di produksi: `UserSeeder` berisi password contoh.
  Admin pertama: `rpkd-admin-pertama`.
- Laporan kerentanan: hubungi admin vps1 secara langsung, jangan lewat issue publik.
