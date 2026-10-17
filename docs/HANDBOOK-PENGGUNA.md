# Handbook Pengguna — Aplikasi OPPKPKE

**Untuk Admin Master & Operator Perangkat Daerah (Dinas/Badan/Kecamatan)**

Aplikasi OPPKPKE adalah sistem pelaporan **Optimalisasi Pelaksanaan Pengentasan
Kemiskinan dan Penghapusan Kemiskinan Ekstrem** Kabupaten Kotabaru. Setiap
perangkat daerah mengisi rencana dan realisasi anggaran program kemiskinannya di
sini, lalu pimpinan membacanya sebagai satu matriks dan ikhtisar tunggal.

Handbook ini menjelaskan **semua fitur yang tersedia**, per peran, disertai
langkah pengerjaannya.

---

## Daftar Isi

1. [Konsep dasar yang perlu dipahami lebih dulu](#1-konsep-dasar-yang-perlu-dipahami-lebih-dulu)
2. [Peran pengguna & hak aksesnya](#2-peran-pengguna--hak-aksesnya)
3. [Mulai cepat](#3-mulai-cepat)
4. [Panduan fitur — Operator Perangkat Daerah](#4-panduan-fitur--operator-perangkat-daerah)
5. [Panduan fitur — Admin Master](#5-panduan-fitur--admin-master)
6. [Kamus 21 kolom laporan](#6-kamus-21-kolom-laporan)
7. [Alur kerja satu tahun anggaran](#7-alur-kerja-satu-tahun-anggaran)
8. [Aturan & batasan sistem](#8-aturan--batasan-sistem)
9. [Masalah umum & solusinya](#9-masalah-umum--solusinya)
10. [Istilah singkat](#10-istilah-singkat)

---

## 1. Konsep dasar yang perlu dipahami lebih dulu

### 1.1 Hierarki data — dari atas ke bawah

Semua angka di aplikasi ini menempel pada **Sub Kegiatan**. Memahami susunan
berikut membuat seluruh menu jadi masuk akal:

```
Strategi OPPKPKE          (3 strategi, ditetapkan pusat — tidak dibuat sendiri)
   └── Perangkat Daerah    (Dinas / Badan / Kecamatan pemilik program)
         └── Program        (punya kode anggaran, mis. 1.06.05...)
               └── Kegiatan
                     └── Sub Kegiatan   ← DI SINI data anggaran diisi
```

Tiga strategi OPPKPKE:

| Kode | Nama strategi | Inti |
|---|---|---|
| 1 | Pengurangan Beban Pengeluaran Masyarakat | bantuan yang menekan pengeluaran warga miskin |
| 2 | Peningkatan Pendapatan Masyarakat | usaha, pelatihan kerja, produktivitas |
| 3 | Meminimalkan Jumlah Kantong-Kantong Kemiskinan | infrastruktur & penanganan wilayah miskin |

> Nama strategi bisa diperbarui Admin Master lewat **Kelola Strategi**, tetapi
> **kodenya tetap** karena dipakai untuk mencocokkan data.

### 1.2 Satu laporan = satu sub kegiatan × satu tahun

Kunci datanya adalah pasangan **sub kegiatan + tahun anggaran**. Mengisi ulang
sub kegiatan yang sama pada tahun yang sama berarti **memperbarui** baris itu,
bukan membuat baris baru. Jadi tidak ada risiko data kembar.

### 1.3 Pagu, realisasi, dan serapan

| Istilah | Arti | Di aplikasi |
|---|---|---|
| **Pagu / Alokasi Anggaran** | uang yang dianggarkan untuk sub kegiatan itu | kolom `Alokasi Anggaran` — **wajib diisi** |
| **Realisasi Semester 1** | yang benar-benar dibelanjakan Januari–Juni | kolom `Realisasi Sem 1` |
| **Realisasi Semester 2** | yang benar-benar dibelanjakan Juli–Desember | kolom `Realisasi Sem 2` |
| **Realisasi Total** | Sem 1 + Sem 2 | **dihitung otomatis**, tidak perlu diisi |
| **Serapan (%)** | Realisasi Total ÷ Pagu × 100 | dihitung otomatis di semua halaman |

Aplikasi mencatat per **semester**, bukan per bulan. Ini penting: semua grafik
tren dan proyeksi memakai granularitas semester.

---

## 2. Peran pengguna & hak aksesnya

| Peran | Label di aplikasi | Lingkup data | Untuk siapa |
|---|---|---|---|
| `master` | **Admin Master** | seluruh perangkat daerah | Bappeda / admin pusat program |
| `daerah` | **Operator Daerah** | **hanya perangkat daerahnya sendiri** | petugas di dinas/badan/kecamatan |
| `it_team` | **Tim IT** | teknis sistem (tanpa input laporan) | pengelola aplikasi |

Ringkasan menu per peran:

| Fitur | Operator Daerah | Admin Master |
|---|---|---|
| Beranda / Dashboard | ✅ (data PD sendiri) | ✅ (semua PD) |
| Ikhtisar Eksekutif | — | ✅ |
| Input Data Laporan | ✅ (PD sendiri) | ✅ (semua PD) |
| Kelola Pagu Anggaran | ✅ (PD sendiri) | ✅ (pilih PD) |
| Rekap Laporan | ✅ | ✅ |
| Statistik | — | ✅ |
| Matriks 21 kolom + Export Excel | — | ✅ |
| Explorer Data | ✅ | ✅ |
| Import OPPKPKE / RAT / Hierarki | — | ✅ |
| Kelola Pengguna | — | ✅ |
| Pengumuman & Maintenance | — | ✅ |
| Chat Support IT | ✅ | ✅ (pantau) |
| Sesi Login, Audit Log, Kelola Menu, Kelola Strategi, Kelola Perangkat Daerah | — | ✅ |
| Identitas PIC, Ganti Password | ✅ | ✅ (password) |

> **Menu bisa hilang bukan karena rusak.** Tim IT dapat mematikan menu tertentu
> per peran lewat **Kelola Menu**. Kalau sebuah menu tidak muncul padahal
> seharusnya ada, hubungi Tim IT lewat Chat Support.

---

## 3. Mulai cepat

### 3.1 Operator Perangkat Daerah — 6 langkah pertama

| # | Langkah | Menu |
|---|---|---|
| 1 | Login dengan email & password dari Admin Master | halaman Login |
| 2 | **Ganti password** bawaan menjadi password Anda sendiri | Ganti Password |
| 3 | **Lengkapi Identitas PIC** (nama + NIK) — tanpa ini input laporan terkunci | Identitas PIC |
| 4 | Periksa daftar Program → Kegiatan → Sub Kegiatan milik dinas Anda; tambahkan bila ada yang belum terdaftar | Input Data |
| 5 | Isi **pagu anggaran** seluruh sub kegiatan | Kelola Pagu Anggaran |
| 6 | Lengkapi detail laporan tiap sub kegiatan (sasaran, lokasi, realisasi, dll.) | Input Data |

### 3.2 Admin Master — 6 langkah pertama

| # | Langkah | Menu |
|---|---|---|
| 1 | Login, lalu ganti password bawaan | Ganti Password |
| 2 | Pastikan seluruh perangkat daerah sudah punya akun operator | Kelola Pengguna → **Generate Credential** |
| 3 | Siapkan struktur data: impor hierarki, atau impor Matriks RAT tahun berjalan | Import Hierarki / Import RAT |
| 4 | Pantau kelengkapan pengisian per perangkat daerah | Dashboard & Statistik |
| 5 | Terbitkan pengumuman batas waktu pelaporan | Pengumuman |
| 6 | Unduh matriks resmi untuk laporan pimpinan | Matriks → Export Excel |

---

## 4. Panduan fitur — Operator Perangkat Daerah

### 4.1 Beranda

Halaman pertama setelah login. Isinya khusus perangkat daerah Anda:

- **Kartu ringkasan**: total pagu, total realisasi, realisasi Semester 1 & 2,
  persentase serapan, serta jumlah sub kegiatan yang sudah/belum berpagu.
- **Rincian per strategi**: pagu & realisasi dinas Anda pada tiap strategi.
- **Kartu per program**: pintasan cepat ke sub kegiatan di bawahnya.
- **Peringkat perangkat daerah**: posisi dinas Anda dibanding PD lain.
- **Pemilih tahun** di bagian atas — semua angka mengikuti tahun yang dipilih.

### 4.2 Identitas PIC — **wajib, dikerjakan sekali**

Sebelum boleh menginput laporan, operator wajib mengisi identitas penanggung
jawab data. Ini yang tercatat di jejak audit setiap kali data berubah.

**Langkah:**
1. Buka menu **Identitas PIC** (bertanda “Wajib” bila belum diisi).
2. Isi **Nama lengkap sesuai KTP**.
3. Isi **NIK 16 digit**.
4. Simpan.

**PIC tambahan.** Bila pekerjaan dibagi ke beberapa orang di dinas yang sama,
tambahkan mereka di halaman yang sama. Catatan penting:

- PIC tambahan hanya **catatan identitas** — mereka **tidak** mendapat akun login.
- Bisa dihapus kapan saja bila petugasnya berganti.

> NIK disimpan tersamar di jejak audit (hanya 4 digit awal & 4 digit akhir yang
> tampak), jadi tidak terekspos di log.

### 4.3 Input Data — halaman kerja utama

Struktur halaman: **panel filter di kiri**, **pohon data di kanan**.

#### a. Menemukan sub kegiatan

1. Pilih **Tahun** di panel filter.
2. Persempit dengan **Strategi**, **Program**, atau **Kegiatan**.
3. Atau langsung ketik di kotak **Cari…** (mencari nama & kode).
4. Tombol **Expand / Collapse** membuka atau menutup seluruh cabang pohon.

Setiap sub kegiatan menampilkan penanda status: **hijau = belum ada data**
(tombol *Tambah*), **biru = sudah ada data** (tombol *Edit*).

#### b. Mengisi data laporan

1. Klik tombol pada baris sub kegiatan → muncul jendela isian.
2. Isi kolom-kolomnya (penjelasan tiap kolom ada di [bagian 6](#6-kamus-21-kolom-laporan)).
   Hanya **Alokasi Anggaran** yang wajib; sisanya boleh dilengkapi bertahap.
3. Klik **Simpan**.

Yang perlu diingat:

- **Realisasi Total tidak perlu diisi** — dihitung dari Sem 1 + Sem 2.
- **Sumber Pembiayaan** dipilih dari daftar: APBD, APBN, DAK, Dana Desa, Lainnya.
- Mengisi ulang sub kegiatan yang sama = memperbarui data, bukan menduplikasi.
- Perbaikan data dilakukan dengan **membuka kembali sub kegiatan itu dan
  menyimpan nilai baru** — tidak ada tombol hapus laporan, sehingga data tidak
  bisa hilang karena salah klik.

#### c. Menambah Program / Kegiatan / Sub Kegiatan baru

Bila ada sub kegiatan yang belum terdaftar, gunakan tombol **Tambah** (wizard
berpemandu). Wizard menuntun langkah demi langkah:

1. **Program** — pilih program yang sudah ada, atau buat baru.
2. Bila program baru: pilih **strategi** tempat program itu bernaung, lalu isi
   kode & nama program (kode/nama boleh dikosongkan dan dilengkapi nanti).
3. **Kegiatan** — pilih yang ada, atau buat baru.
4. **Sub Kegiatan** — isi namanya (**wajib**), kode opsional.
5. Simpan.

Operator daerah otomatis terkunci pada perangkat daerahnya sendiri — tidak
mungkin salah menaruh program di dinas lain.

#### d. Memperbaiki nama Program / Kegiatan

Ikon **pensil** di samping nama program atau kegiatan di pohon data. Berguna
untuk merapikan nama hasil impor yang typo atau tidak lengkap. Nama program dan
kegiatan milik dinas lain tidak dapat diubah.

### 4.4 Kelola Pagu Anggaran — mengubah banyak pagu sekaligus

Dipakai saat hanya angka pagu yang perlu diperbarui, misalnya setelah pergeseran
anggaran atau APBD Perubahan — tanpa membuka jendela isian 21 kolom satu per satu.

**Langkah — 3 saja:**
1. Pilih **tahun anggaran**, lalu cari sub kegiatannya bila perlu.
2. **Ketik angka pagu langsung pada daftar.** Titik pemisah ribuan muncul otomatis.
3. Klik **Simpan Perubahan** di bilah bawah, atau tekan `Ctrl` + `S`.

Fitur pembantu di halaman ini:

| Hal | Kegunaan |
|---|---|
| Dikelompokkan per **Program**, dengan subtotal | subtotal ikut berubah saat Anda mengetik |
| **Realisasi, Sisa, dan % serapan** per baris | tahu konteksnya sebelum menetapkan angka |
| Garis kuning + catatan “Semula Rp …” | menandai baris yang Anda ubah tapi belum disimpan |
| Ikon **undo** per baris | membatalkan satu baris ke nilai semula |
| Tombol **Batalkan** | mengembalikan semua perubahan di halaman |
| Tekan **Enter** | melompat ke baris berikutnya (untuk pengisian cepat) |
| Peringatan merah | muncul bila pagu lebih kecil dari realisasi — dikonfirmasi dulu, tidak diblokir |
| Filter **Status Pagu** | tampilkan hanya yang “Belum ada pagu” untuk menyisir sisa pekerjaan |

Yang diubah di halaman ini **hanya pagu**. Realisasi, sasaran, lokasi, dan kolom
lain yang Anda isi di Input Data tidak tersentuh.

> Daftar dibatasi 50 baris per halaman. **Simpan dulu sebelum pindah halaman** —
> aplikasi akan memperingatkan bila Anda mencoba meninggalkan halaman dengan
> perubahan yang belum tersimpan.

### 4.5 Rekap Laporan

Tabel seluruh laporan yang sudah masuk, untuk memeriksa hasil pekerjaan.

- Filter: **Tahun**, **Strategi**, **Perangkat Daerah**.
- Baris total di bawah: pagu, Sem 1, Sem 2, realisasi total — dihitung dari
  **seluruh** hasil filter, bukan hanya halaman yang terlihat.
- 25 baris per halaman.
- Tombol **Excel** mengunduh matriks resmi sesuai filter yang aktif.
- Operator daerah hanya melihat data dinasnya sendiri.

> Tombol **PDF** masih dalam pengembangan dan belum menghasilkan berkas. Untuk
> sekarang gunakan Excel.

### 4.6 Explorer Data

Penelusuran cepat lintas hierarki dengan filter berjenjang (strategi → perangkat
daerah → program → kegiatan) plus kotak pencarian. Ada filter **status**: sudah
terisi atau masih kosong — praktis untuk menyisir sub kegiatan yang terlewat.

### 4.7 Chat Support IT

Jalur resmi bertanya ke Tim IT bila menemui kendala aplikasi.

**Membuat tiket:**
1. Buka **Chat Support IT** → buat percakapan baru.
2. Isi **Judul** (opsional) dan **Prioritas**: Rendah / Normal / Tinggi / Mendesak.
3. Tulis pesan (maksimal 5.000 karakter).
4. Lampiran boleh: gambar, PDF, Word, Excel, CSV, TXT — **maksimal 5 MB**.

Selama percakapan berjalan: Anda dapat **mengedit** atau **menghapus** pesan
Anda sendiri, dan lampiran dapat dibuka langsung. Status tiket berubah mengikuti
penanganan Tim IT: **Open → Pending → Resolved → Closed**. Lencana merah pada
menu menandakan ada balasan yang belum dibaca.

### 4.8 Panduan (di dalam aplikasi)

Ringkasan cara pakai yang bisa dibuka tanpa keluar dari aplikasi. Handbook yang
Anda baca sekarang adalah versi lengkapnya.

### 4.9 Ganti Password

Ganti password bawaan segera setelah menerima akun. Gunakan kata sandi yang
tidak dipakai di layanan lain, dan jangan dibagikan — semua perubahan data
tercatat atas nama akun Anda.

---

## 5. Panduan fitur — Admin Master

### 5.1 Dashboard

Pandangan menyeluruh seluruh kabupaten pada tahun terpilih:

- Total pagu, total realisasi, dan persentase serapan.
- Jumlah sub kegiatan dan jumlah baris laporan.
- **Rekap per strategi**: pagu, realisasi, Sem 1, Sem 2, capaian, jumlah program.
- **Rekap per perangkat daerah**: seluruh PD aktif diurutkan menurut realisasi —
  **PD yang belum mengisi tetap muncul dengan nilai 0**, sehingga yang tertinggal
  langsung kelihatan. Tabelnya punya pengatur tampilan 10 / 25 / semua baris.

### 5.2 Ikhtisar Eksekutif

Halaman untuk pimpinan, siap dicetak (tombol **Cetak / PDF**). Semua angkanya
dihitung langsung dari basis data — tanpa AI, jadi selalu konsisten dengan
dashboard.

Isinya:

| Bagian | Kegunaan |
|---|---|
| **Ringkasan capaian** | status capaian berwarna (mis. *Perlu Percepatan*), satu kalimat inti berisi angka pokok, dan bilah serapan dengan penanda titik wajar 50% |
| **Yang sudah berjalan baik / Yang perlu perhatian** | temuan konkret bernama: strategi terdepan, PD penyalur terbesar, PD yang nol realisasi, dan seterusnya |
| **Kekuatan data pendukung** | persentase kelengkapan kolom kunci (lokasi, sasaran, besaran manfaat, dst.) — penanda seberapa kuat dasar kesimpulannya |
| **Kecepatan serapan (burn rate)** | capaian aktual vs ekspektasi merata, beserta deviasinya |
| **Proyeksi akhir tahun** | estimasi berbasis pola tahun sebelumnya — **berlabel ESTIMASI**, bukan angka final |
| **Grafik** | alokasi vs realisasi per strategi, komposisi sumber dana, perbandingan antar tahun, realisasi per semester |
| **Realisasi per strategi** | tabel dengan bilah capaian |
| **5 PD tertinggi & 5 PD terendah** | untuk apresiasi dan tindak lanjut |
| **Analisis serapan program (variance)** | program dengan serapan terendah, dan program yang realisasinya melampaui pagu |
| **Identifikasi risiko** | daftar risiko berperingkat Tinggi/Sedang/Rendah |
| **Rencana aksi & rekomendasi** | langkah tindak lanjut yang disusun otomatis dari temuan |

Tombol **Segarkan** memaksa perhitungan ulang (data disimpan sementara 5 menit
agar halaman ringan).

### 5.3 Statistik

Rekap pagu dan realisasi per strategi beserta jumlah laporan dan persentase
capaian, ditambah total keseluruhan. Cocok untuk mengutip angka cepat.

### 5.4 Matriks & Export Excel

Matriks resmi **21 kolom** sesuai format pelaporan OPPKPKE, dengan filter tahun,
strategi, dan perangkat daerah.

Tombol **Export Excel** menghasilkan satu berkas berisi **dua lembar**:

**Lembar “Matriks OPPKPKE”** — matriks resmi yang sudah dirapikan:
- Disusun **Strategi → Perangkat Daerah → Program → Kegiatan → Sub Kegiatan**.
- Setiap strategi dan setiap perangkat daerah punya baris judul berisi
  ringkasannya (jumlah program, jumlah sub kegiatan, pagu, realisasi, % serapan).
- Nama **Perangkat Daerah, Kode, Program, dan Kegiatan digabung vertikal** —
  muncul sekali saja, tidak berulang ke bawah.
- Warna baris berselang **per program**, dengan garis pemisah antar program.
- **Subtotal per perangkat daerah**, **total per strategi**, dan **grand total**,
  dipisahkan baris kosong sebagai margin agar tidak menyatu.
- Siap cetak: A3 lanskap, muat selebar halaman, header berulang tiap halaman,
  nomor halaman di footer, panel dibekukan pada kolom D.

**Lembar “Ringkasan”** — rekap per perangkat daerah: jumlah program, jumlah sub
kegiatan, pagu, realisasi Sem 1/Sem 2/total, dan % serapan berwarna (hijau ≥85%,
kuning ≥50%, merah <50%), dikelompokkan per strategi dengan grand total.

### 5.5 Input Laporan & Kelola Pagu Anggaran (lintas perangkat daerah)

Sama seperti milik operator ([4.3](#43-input-data--halaman-kerja-utama) dan
[4.4](#44-kelola-pagu-anggaran--mengubah-banyak-pagu-sekaligus)), dengan tambahan
kewenangan:

- Dapat memilih **perangkat daerah mana pun**.
- Pada wizard tambah hierarki, Admin memilih **perangkat daerah tujuan** (boleh
  membuat PD baru) dan dapat menaruh satu program di **hingga 3 strategi**
  sekaligus.
- Di Kelola Pagu Anggaran, perangkat daerah **wajib dipilih lebih dulu** agar
  daftar tetap terfokus dan tidak menampilkan ribuan baris sekaligus.

### 5.6 Import Data OPPKPKE (21 kolom)

Untuk memasukkan data massal dari berkas matriks.

**Langkah:**
1. Pilih **tahun anggaran** tujuan.
2. Unggah berkas **CSV / XLSX / XLS**, maksimal **20 MB**, format matriks resmi
   21 kolom.
3. Sistem menampilkan **Preview**: baris yang cocok dengan sub kegiatan di basis
   data, baris yang tidak dikenali, dan ringkasan nilainya. **Periksa di tahap ini.**
4. Pilihan **Ganti data tahun tersebut** (*replace year*): bila dicentang, data
   tahun itu dihapus lebih dulu lalu diganti isi berkas. Gunakan hanya bila Anda
   memang ingin menimpa keseluruhan.
5. Klik eksekusi. Proses berjalan di latar belakang; status kemajuannya tampil di
   halaman sampai selesai.

> Tahun yang diterima 2020–2035. Baris yang sub kegiatannya tidak ditemukan akan
> dilaporkan di preview — perbaiki nama/kodenya di berkas, atau tambahkan
> hierarkinya lebih dulu.

### 5.7 Import Matriks RAT (18 kolom)

Alurnya identik dengan 5.6, tetapi untuk format **Matriks RAT 18 kolom**.
Gunakan menu yang sesuai dengan bentuk berkas Anda — kolomnya berbeda, jadi
jangan ditukar.

### 5.8 Import Hierarki

Membangun struktur **Perangkat Daerah → Strategi → Program → Kegiatan → Sub
Kegiatan** sekaligus, tanpa angka anggaran. Dipakai di awal tahun atau saat ada
nomenklatur baru.

**Langkah:** unduh **template** → isi → unggah → **preview** → eksekusi.
Selalu lewat preview, sehingga tidak ada perubahan struktur yang terjadi
tanpa Anda lihat lebih dulu.

### 5.9 Kelola Pengguna

Manajemen akun seluruh operator. Empat tombol utama di kanan atas:

| Tombol | Fungsi |
|---|---|
| **Generate Credential** | membuatkan akun operator untuk perangkat daerah yang **belum punya akun** sekaligus. Lencana angka pada tombol = jumlah PD yang belum punya akun. Ada layar pratinjau sebelum dieksekusi |
| **Tambah Operator** | alur terpandu membuat satu akun operator; kredensialnya **tampil sekali** — catat/serahkan saat itu |
| **Tambah Pengguna** | membuat akun peran lain (mis. Tim IT) secara manual |
| **Export PDF** | mengunduh daftar pengguna untuk arsip/berita acara |

Pada setiap baris pengguna tersedia:

- **Lihat / Ubah** data pengguna (nama, email, perangkat daerah, peran).
- **Reset Password** — password baru ditampilkan sekali untuk diserahkan.
- **Aktif / Nonaktif** — cara aman menutup akses tanpa menghapus jejak datanya.
  Gunakan ini bila petugas mutasi.
- **Hapus** — hanya untuk akun yang salah dibuat.

> Praktik yang disarankan: petugas pindah tugas → **nonaktifkan**, jangan hapus.
> Data yang pernah ia input tetap utuh beserta jejak auditnya.

### 5.10 Pengumuman & Maintenance

Pesan yang tampil sebagai banner untuk **semua pengguna kecuali Tim IT**.
Berguna untuk mengumumkan batas waktu pelaporan atau jadwal pemeliharaan.

**Isian:**

| Kolom | Ketentuan |
|---|---|
| Judul | wajib, maksimal 160 karakter |
| Isi | wajib, maksimal 5.000 karakter |
| Jenis | **Info**, **Peringatan**, **Maintenance**, atau **Kritis** (menentukan warna & penekanan) |
| Mulai / Berakhir | opsional — bila diisi, banner tampil hanya dalam rentang itu; waktu berakhir tidak boleh sebelum waktu mulai |
| Aktif | saklar tampil/tidak, tanpa perlu menghapus pengumumannya |

Pengumuman dapat diedit, dinonaktifkan sementara, atau dihapus.

### 5.11 Pantau Chat

Seluruh tiket dari operator daerah dalam satu kotak masuk: membalas, melampirkan
berkas, dan mengubah status tiket (**Open / Pending / Resolved / Closed**). Tiket
yang dibalas otomatis tercatat sebagai ditangani oleh penjawabnya.

### 5.12 Sesi Login

Daftar akun yang sedang login beserta perangkat/alamatnya, dengan tombol
**logout paksa**. Gunakan bila ada sesi mencurigakan atau petugas lupa logout di
komputer bersama.

### 5.13 Kelola Strategi

Memperbarui **nama (label)** dan **deskripsi** tiap strategi — misalnya
menyesuaikan redaksi dengan dokumen terbaru. **Kode strategi tidak dapat diubah**
karena dipakai untuk relasi data dan pencocokan Import RAT. Nama yang kembar
antar strategi ditolak agar pencocokan impor tidak keliru.

### 5.14 Kelola Perangkat Daerah

Mendeteksi perangkat daerah **duplikat** (misalnya “Dinas Sosial” dan “DINAS
SOSIAL” yang masuk dari dua berkas impor) dan **menggabungkannya** menjadi satu.
Seluruh program milik PD duplikat dipindahkan ke PD tujuan, sehingga angka tidak
lagi terpecah dua.

### 5.15 Kelola Menu

Menyalakan/mematikan menu per peran (**Admin Master** dan **Operator Daerah**).
Semua menu aktif secara bawaan. Gunakan untuk menyederhanakan tampilan operator
pada masa tertentu — misalnya menutup menu impor saat periode input berjalan.

### 5.16 Audit Log

Jejak aktivitas sensitif: siapa mengubah apa dan kapan, lengkap dengan alamat IP
dan identitas PIC yang mengerjakannya. Tercatat antara lain: pembuatan &
perubahan laporan, **perubahan pagu** (jumlah baris + selisih totalnya),
perubahan strategi, dan pengelolaan pengumuman.

---

## 6. Kamus 21 kolom laporan

Urutan mengikuti matriks resmi. Kolom bertanda ⭐ wajib.

| # | Kolom | Arti | Contoh |
|---|---|---|---|
| 1 | No | nomor urut | otomatis |
| 2 | Strategi OPPKPKE | strategi tempat program bernaung | Pengurangan Beban Pengeluaran |
| 3 | Perangkat Daerah | dinas/badan/kecamatan pemilik program | Dinas Sosial |
| 4 | Kode | kode program sesuai dokumen anggaran | 1.06.05.2.02.0001 |
| 5 | Program | nama program | Program Perlindungan & Jaminan Sosial |
| 6 | Kegiatan | kegiatan di bawah program | Pengelolaan Data Terpadu |
| 7 | Sub Kegiatan | unit terkecil — tempat angka menempel | Fasilitasi Bantuan Sosial |
| 8 | Aktifitas Real — **Langsung** | kegiatan yang manfaatnya langsung diterima warga | Pemberian bantuan tunai kepada KPM |
| 9 | Aktifitas Real — **Tidak Langsung** | penunjang yang manfaatnya tak langsung | Bimbingan teknis SDM |
| 10 | Aktifitas Real — **Penunjang** | belanja pendukung | Pengadaan sarana prasarana |
| 11 | Alokasi Anggaran (Rp) ⭐ | **pagu** sub kegiatan | 250000000 |
| 12 | Sumber Pembiayaan | APBD / APBN / DAK / Dana Desa / Lainnya | APBD |
| 13 | Sifat Bantuan | Rutin atau Insidentil | Rutin |
| 14 | Lokasi | wilayah sasaran | Kec. Pulau Laut Utara |
| 15 | Jumlah Sasaran Penerima Manfaat | berapa banyak penerimanya | 250 |
| — | Satuan Sasaran | satuan dari angka di atas | KK / Jiwa / Sekolah |
| 16 | Besaran Manfaat | nilai manfaat per penerima | Rp 500.000 |
| 17 | Jenis Bantuan | bentuk bantuannya | Tunai / Barang / Pelatihan |
| 18 | Durasi Pemberian | lama pemberian bantuan | 12 bulan |
| 19 | Realisasi Sem. 1 | belanja Januari–Juni | 120000000 |
| 20 | Realisasi Sem. 2 | belanja Juli–Desember | 95000000 |
| 21 | Realisasi Total | **otomatis** = Sem 1 + Sem 2 | 215000000 |

Kolom tambahan pada jendela isian: **Penerima Langsung / Tidak Langsung /
Penunjang** — rincian jumlah penerima menurut jenis aktivitasnya.

> **Kenapa kolom teks penting.** Ikhtisar Eksekutif mengukur hasil di lapangan
> dari kolom **Jumlah Sasaran, Lokasi, Besaran Manfaat, Durasi, Jenis, dan Sifat
> Bantuan**. Bila kolom ini kosong, angka rupiah tetap terbaca tetapi
> **manfaatnya bagi masyarakat tidak dapat diukur** — dan halaman itu akan
> menampilkan persentase kelengkapan datanya secara terbuka.
>
> Tips: tulis Jumlah Sasaran sebagai **angka dulu, satuan kemudian**
> (“250 KK”, bukan “dua ratus lima puluh keluarga”), agar terhitung otomatis.

---

## 7. Alur kerja satu tahun anggaran

| Periode | Operator Perangkat Daerah | Admin Master |
|---|---|---|
| **Awal tahun** | periksa & lengkapi hierarki program/kegiatan/sub kegiatan; isi **pagu** seluruh sub kegiatan | impor hierarki / Matriks RAT; pastikan semua PD punya akun; terbitkan pengumuman batas waktu |
| **Berjalan (Jan–Jun)** | lengkapi detail laporan; perbarui pagu bila ada pergeseran | pantau kelengkapan lewat Dashboard & Statistik |
| **Akhir Semester 1 (Juli)** | isi **Realisasi Sem 1** | tagih PD yang nol realisasi (daftarnya ada di Ikhtisar Eksekutif) |
| **Berjalan (Jul–Des)** | perbarui data bila ada perubahan | pantau burn rate & proyeksi |
| **Akhir tahun (Des–Jan)** | isi **Realisasi Sem 2**; periksa ulang di Rekap Laporan | unduh **Matriks Excel**; cetak **Ikhtisar Eksekutif** untuk pimpinan |

---

## 8. Aturan & batasan sistem

| Hal | Ketentuan |
|---|---|
| Lingkup data operator | terkunci pada perangkat daerahnya — dijaga di sisi server, bukan hanya disembunyikan di tampilan |
| Kunci data laporan | satu baris per **sub kegiatan + tahun**; pengisian ulang = memperbarui |
| Realisasi Total | selalu dihitung sistem, tidak dapat diisi manual |
| Identitas PIC | wajib bagi operator sebelum menginput laporan |
| Unggah berkas impor | CSV / XLSX / XLS, maksimal **20 MB**, tahun 2020–2035 |
| Lampiran chat | maksimal **5 MB**; gambar, PDF, Word, Excel, CSV, TXT |
| Panjang pesan chat | maksimal 5.000 karakter |
| Kelola Pagu | maksimal 50 baris per halaman per simpan |
| Penghapusan data | memakai *soft delete* — baris tetap tersimpan di basis data dan tidak lagi dihitung |
| Jejak audit | perubahan sensitif tercatat beserta pelaku, IP, dan identitas PIC |
| Pembatasan laju | beberapa aksi dibatasi frekuensinya untuk mencegah penyalahgunaan; bila muncul pesan “terlalu banyak permintaan”, tunggu semenit lalu ulangi |
| Export PDF | belum tersedia (masih dikembangkan) — gunakan Export Excel |

---

## 9. Masalah umum & solusinya

| Gejala | Sebab & solusi |
|---|---|
| Menu **Input Data** tidak bisa dibuka, dialihkan ke Identitas PIC | Identitas PIC belum lengkap. Isi nama & NIK 16 digit lebih dulu |
| Sub kegiatan yang dicari tidak ada di pohon data | Belum terdaftar → tambahkan lewat tombol **Tambah** di Input Data. Atau filter tahun/strategi sedang mempersempit tampilan — coba kosongkan filternya |
| Angka di Dashboard berbeda dengan yang saya input | Cek **tahun** yang dipilih di kedua halaman. Semua halaman memakai sumber angka yang sama, jadi selisih hampir selalu karena beda tahun atau beda filter |
| Sudah menyimpan pagu tapi tabel masih kosong | Filter **Status Pagu** mungkin masih di posisi “Belum ada pagu”. Ubah ke “Semua” |
| Perubahan pagu hilang | Belum ditekan **Simpan Perubahan**. Angka yang diubah bertanda garis kuning selama belum disimpan |
| Impor selesai tapi data tidak muncul | Lihat hasil **preview**: baris yang sub kegiatannya tidak dikenali tidak diimpor. Perbaiki nama/kode di berkas, atau tambahkan hierarkinya lebih dulu |
| Total anggaran tampak dobel setelah impor | Berkas terimpor dua kali. Impor ulang dengan mencentang **Ganti data tahun tersebut** |
| Satu dinas muncul dua kali dengan nama mirip | Duplikat perangkat daerah → Admin Master menggabungkannya lewat **Kelola Perangkat Daerah** |
| Menu yang biasa ada tiba-tiba hilang | Kemungkinan dimatikan lewat **Kelola Menu**. Hubungi Tim IT |
| Lupa password | Minta Admin Master melakukan **Reset Password** di Kelola Pengguna |
| Akun tidak bisa login padahal password benar | Akun mungkin berstatus **Nonaktif**. Minta Admin Master mengaktifkannya |
| Persentase serapan lebih dari 100% | Realisasi melampaui pagu. Periksa pagunya di **Kelola Pagu Anggaran** — biasanya pagu belum diperbarui setelah pergeseran anggaran |

Masih belum selesai? Kirim tiket lewat **Chat Support IT**, sertakan tangkapan
layar dan sebutkan menu serta tahun anggarannya.

---

## 10. Istilah singkat

| Istilah | Arti |
|---|---|
| **OPPKPKE** | Optimalisasi Pelaksanaan Pengentasan Kemiskinan dan Penghapusan Kemiskinan Ekstrem |
| **Pagu / Alokasi** | anggaran yang ditetapkan untuk suatu sub kegiatan |
| **Realisasi** | anggaran yang benar-benar dibelanjakan |
| **Serapan** | realisasi dibagi pagu, dalam persen |
| **PD** | Perangkat Daerah — dinas, badan, atau kecamatan |
| **RAT** | Rencana Aksi Tahunan |
| **PIC** | *Person in Charge* — penanggung jawab data di perangkat daerah |
| **KPM** | Keluarga Penerima Manfaat |
| **Burn rate** | kecepatan penyerapan anggaran dibanding waktu yang berjalan |
| **Output** | keluaran yang dikerjakan (jumlah program, sub kegiatan, wilayah) |
| **Outcome** | hasil yang dirasakan masyarakat (anggaran tersalur, penerima manfaat) |

---

## Dokumen terkait

| Berkas | Isi |
|---|---|
| [`ARSITEKTUR.md`](ARSITEKTUR.md) | rancangan teknis aplikasi |
| [`SETUP-VPS.md`](SETUP-VPS.md) | pemasangan di server (Ubuntu) |
| [`SETUP-VPS-ALMALINUX.md`](SETUP-VPS-ALMALINUX.md) | pemasangan di server (AlmaLinux) |
| [`../deploy/README.md`](../deploy/README.md) | perintah operasional harian di server |
