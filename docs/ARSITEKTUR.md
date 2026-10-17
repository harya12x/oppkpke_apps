# Arsitektur Sistem OPPKPKE — Desain Scalable & Analisis Dampak

Dokumen ini merangkum arsitektur sistem, keputusan desain agar **scalable**, dan
untuk tiap komponen: **dampak negatif yang mungkin muncul → solusi penanganannya**.

---

## 1. Gambaran Arsitektur (Produksi, Docker)

```
                         ┌───────────────────────────────────────────┐
                         │                 NGINX (TLS)               │
                         │        reverse proxy + static + LE         │
                         └───────────────┬───────────────────────────┘
                                         │ FastCGI
                         ┌───────────────▼───────────────┐
                         │   app  (php-fpm 8.3)           │  ← request web (stateless)
                         │   Laravel 13                   │
                         └───┬───────────────┬────────────┘
             baca/tulis      │               │  dispatch job (queue: database)
                             ▼               ▼
                     ┌──────────────┐   ┌──────────────┐
                     │   MySQL      │   │  worker      │  queue:work (notifikasi email, dsb)
                     │ (host / ext) │   │  (1..N)      │
                     └──────────────┘   └──────────────┘
                             ▲
                             │ schedule:work (10 mnt / harian)
                     ┌──────────────┐
                     │  scheduler   │  announcements:deactivate-expired, audit:prune
                     └──────────────┘
```

**Prinsip scalability:**
- **Stateless app container** → bisa di-scale horizontal (tambah replika `app` di belakang nginx/LB). Sesi & cache di **database** (bukan file lokal) supaya konsisten antar replika.
- **Pemrosesan berat di-offload ke queue** (worker terpisah) → request web tetap cepat.
- **ULID** pada tabel chat (`conversations`, `messages`) → ordered, aman untuk sharding masa depan; indeks komposit `(conversation_id, id)`.
- **Realtime hemat**: polling ber-jeda + pause saat tab tidak aktif; siap di-upgrade ke Reverb (WebSocket) tanpa ubah skema.
- **Data besar dibatasi**: paginasi pesan (50), inbox (20), audit (30); throttle di endpoint polling & aksi.

---

## 2. Komponen & Alur Fitur

| Domain | Alur singkat | Titik scale |
|---|---|---|
| Auth | Login throttle 5/mnt + honeypot + cek `is_active` per-request | Rate limiter di cache DB |
| Chat support | POST → simpan (ULID) → broadcast-ready + notifikasi ter-queue; polling untuk delivery | Tambah worker; naikkan partisi bila pindah Kafka/Reverb |
| Pengumuman | Master/IT kelola → banner via View Composer; auto-nonaktif kedaluwarsa (scheduler) | Query `live()` + cache opsional |
| Audit | `AuditLog::record()` immutable pada aksi sensitif + input laporan (terlacak PIC) | Prune harian; index pada action/created_at |
| Identitas PIC | Gate `pic.identity` sebelum input laporan | Kolom di `users`, index `no_ktp` |

---

## 3. Analisis Dampak Negatif → Solusi Penanganan

### 3.1 Autentikasi & Login
| Dampak negatif | Solusi |
|---|---|
| Brute-force / credential stuffing | Throttle 5/mnt per email+IP & 15/mnt per IP; honeypot memblokir bot; audit `auth.login_failed`/`auth.login_bot` |
| Bot spam submit | Honeypot field tersembunyi + anti double-submit di klien |
| Deaktivasi akun tak berlaku instan | Middleware `EnsureUserActive` cek `is_active` tiap request → sesi diputus segera |
| Kebocoran kredensial via cache proxy | Header `Cache-Control: no-store` pada `/login` (SecurityHeaders) |

### 3.2 Chat & Lampiran
| Dampak negatif | Solusi |
|---|---|
| Beban DB dari polling | Poll hanya tulis `read` saat ada pesan baru; pause saat tab hidden; throttle 120/mnt |
| Duplikasi pesan bila Reverb diaktifkan | Dedup by `data-mid` + catatan: matikan polling saat Reverb on |
| Lampiran berbahaya/berukuran besar | Validasi mimes + maks 5 MB; disimpan **disk privat**; unduh via route berotorisasi (Policy `view`); nama file di-randomize |
| Email notifikasi bertubi (spam) | Throttle `Cache::add` 1 email/15 mnt per (percakapan, penerima); ter-queue asynchronous |
| Kehilangan riwayat saat operator dihapus | `initiator_id` `nullOnDelete` + soft delete pesan/percakapan |

### 3.3 Queue & Worker (Docker)
| Dampak negatif | Solusi |
|---|---|
| Worker memakai kode lama setelah deploy | Kode di-bake ke image → `docker compose build && up -d` (recreate); worker `--max-time=3600` keluar bersih tiap jam |
| Memory leak proses panjang | `--max-time` + `--max-jobs`; container `restart: unless-stopped` |
| Job gagal menumpuk | `--tries=3 --backoff`; `failed_jobs` + `queue:retry`; `queue:prune-failed` mingguan |
| Job hilang saat crash | Driver `database` persisten (bukan sync); commit setelah sukses |

### 3.4 Scheduler
| Dampak negatif | Solusi |
|---|---|
| Penjadwal terduplikasi (job dobel) | `scheduler` **1 instance** (jangan di-scale) |
| Pengumuman kedaluwarsa masih tampil | `scope live()` menyembunyikan + command `announcements:deactivate-expired` tiap 10 mnt merapikan `is_active` |
| Audit membengkak tanpa batas | `audit:prune --days=180` harian |

### 3.5 Identitas PIC & Audit Laporan
| Dampak negatif | Solusi |
|---|---|
| Data pribadi (NIK) bocor | NIK **disamarkan** di deskripsi audit (`1234********5678`); tidak ditampilkan penuh di UI |
| Input laporan tak terlacak ke individu | Gate `pic.identity` mewajibkan Nama+NIK sebelum input; setiap simpan mencatat `laporan.created/updated` + PIC di audit |
| Gate memblokir role lain / redirect loop | Middleware hanya aktif untuk role `daerah`; route form PIC di luar gate |
| NIK asal-asalan | Validasi server `digits:16` + sanitasi input klien (hanya angka, 16 digit) |

### 3.6 Pengumuman (Banner)
| Dampak negatif | Solusi |
|---|---|
| Banner mengganggu di tiap halaman (operator) | View Composer membatasi banner Operator Daerah **hanya di Beranda**; role lain semua halaman |
| Query banner tiap request | `limit(5)` + `live()` ringan; dapat di-cache bila perlu |
| Dismiss hilang antar-perangkat | Dismiss di `localStorage` per-signature (reset saat pengumuman diperbarui) — ringan, tanpa tabel |

### 3.7 Keamanan Aplikasi (lintas fitur)
| Dampak negatif | Solusi |
|---|---|
| XSS | Escaping Blade `{{ }}` + `esc()` di JS; CSP di `SecurityHeaders` |
| CSRF | Token pada semua form & AJAX |
| IDOR | ULID tak tertebak + Policy (`ConversationPolicy`, `MessagePolicy`, `AnnouncementPolicy`) |
| Clickjacking/sniffing | `X-Frame-Options`, CSP `frame-ancestors`, `nosniff`, HSTS |
| Otorisasi tersebar/rawan lupa | Policy terpusat + middleware `role` per-route |

---

## 4. Roadmap Scale-Up (bila beban naik)

1. **Web**: tambah replika `app` di belakang load balancer (sudah stateless).
2. **Cache/Session**: pindah dari `database` ke **Redis** (turunkan beban MySQL, lock atomik lebih cepat).
3. **Queue**: pindah driver ke **Redis/SQS**; tambah `worker` (hapus `container_name` agar bisa `--scale`).
4. **Realtime**: aktifkan **Reverb** (service terpisah) + matikan polling → hilangkan beban polling.
5. **Chat skala jutaan**: partisi tabel `messages` per bulan atau arsip > 3 bulan ke penyimpanan dingin; pertimbangkan Kafka (sesuai blueprint CHATKAFKA.md) bila throughput sangat tinggi.
6. **DB**: read-replica untuk laporan/statistik; index review berkala.

---

## 5. Risiko Operasional & Mitigasi Deploy

| Risiko | Mitigasi |
|---|---|
| Migrasi belum jalan di server → 500 | Selalu `docker compose exec app php artisan migrate --force` saat deploy (lihat `deploy/README.md`) |
| Compiled view rusak ter-cache | Selalu `view:clear` sebelum `view:cache` |
| `.env` berubah tak terbaca worker | `docker compose restart app worker scheduler` + `config:cache` |
| Kesalahan manual saat deploy | Skrip satu-perintah `deploy/deploy.sh` (build → up → migrate → clear/cache → restart) |
