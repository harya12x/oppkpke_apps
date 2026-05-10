<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OppkpkeMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedStrategi();
        $this->seedPerangkatDaerah();
        $this->seedPrograms();
        $this->seedKegiatan();
        $this->seedSubKegiatan();
    }

    private function seedStrategi(): void
    {
        $data = [
            ['kode' => '1', 'nama' => 'Pengurangan Beban Pengeluaran Masyarakat', 'deskripsi' => 'Program untuk mengurangi beban pengeluaran masyarakat miskin melalui bantuan pendidikan, kesehatan, dan sosial', 'urutan' => 1, 'icon' => 'hand-holding-usd', 'color' => 'blue', 'is_active' => 1],
            ['kode' => '2', 'nama' => 'Peningkatan Pendapatan Masyarakat', 'deskripsi' => 'Program untuk meningkatkan pendapatan masyarakat melalui pemberdayaan ekonomi dan pelatihan', 'urutan' => 2, 'icon' => 'chart-line', 'color' => 'green', 'is_active' => 1],
            ['kode' => '3', 'nama' => 'Penurunan Jumlah Kantong-Kantong Kemiskinan', 'deskripsi' => 'Program untuk mengurangi wilayah kantong kemiskinan melalui pembangunan infrastruktur', 'urutan' => 3, 'icon' => 'map-marked-alt', 'color' => 'orange', 'is_active' => 1],
        ];

        foreach ($data as $row) {
            DB::table('strategi_oppkpke')->updateOrInsert(['kode' => $row['kode']], $row);
        }
    }

    private function seedPerangkatDaerah(): void
    {
        $data = [
            ['kode' => '1.01.2.22.0.00.01.0000', 'nama' => 'Dinas Pendidikan dan Kebudayaan', 'jenis' => 'dinas', 'is_active' => 1],
            ['kode' => '1.02.0.00.0.00.02.0000', 'nama' => 'Dinas Kesehatan', 'jenis' => 'dinas', 'is_active' => 1],
            ['kode' => '1.05.0.00.0.00.07.0000', 'nama' => 'Badan Penanggulangan Bencana Daerah', 'jenis' => 'badan', 'is_active' => 1],
            ['kode' => '1.06.0.00.0.00.08.0000', 'nama' => 'Dinas Sosial', 'jenis' => 'dinas', 'is_active' => 1],
            ['kode' => '2.08.2.14.0.00.10.0000', 'nama' => 'Dinas Pemberdayaan Perempuan Perlindungan Anak Pengendalian Penduduk dan Keluarga Berencana', 'jenis' => 'dinas', 'is_active' => 1],
            ['kode' => '2.09.3.27.0.00.11.0000', 'nama' => 'Dinas Ketahanan Pangan dan Pertanian', 'jenis' => 'dinas', 'is_active' => 1],
            ['kode' => '2.12.0.00.0.00.13.0000', 'nama' => 'Dinas Kependudukan dan Pencatatan Sipil', 'jenis' => 'dinas', 'is_active' => 1],
            ['kode' => '2.07.3.32.0.00.09.0000', 'nama' => 'Dinas Ketenagakerjaan dan Transmigrasi', 'jenis' => 'dinas', 'is_active' => 1],
            ['kode' => '2.13.0.00.0.00.14.0000', 'nama' => 'Dinas Pemberdayaan Masyarakat dan Desa', 'jenis' => 'dinas', 'is_active' => 1],
            ['kode' => '2.17.3.31.3.30.17.0000', 'nama' => 'Dinas Koperasi, Perindustrian dan Perdagangan', 'jenis' => 'dinas', 'is_active' => 1],
            ['kode' => '3.25.0.00.0.00.20.0000', 'nama' => 'Dinas Perikanan', 'jenis' => 'dinas', 'is_active' => 1],
            ['kode' => '1.03.0.00.0.00.03.0000', 'nama' => 'Dinas Pekerjaan Umum dan Penataan Ruang', 'jenis' => 'dinas', 'is_active' => 1],
            ['kode' => '1.04.0.00.0.00.04.0000', 'nama' => 'Dinas Perumahan Rakyat, Permukiman dan Pertanahan', 'jenis' => 'dinas', 'is_active' => 1],
            ['kode' => '2.15.0.00.0.00.15.0000', 'nama' => 'Dinas Perhubungan', 'jenis' => 'dinas', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.31.0000', 'nama' => 'Kecamatan Pulau Laut Barat', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.32.0000', 'nama' => 'Kecamatan Pulau Laut Selatan', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.33.0000', 'nama' => 'Kecamatan Pulau Laut Kepulauan', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.34.0000', 'nama' => 'Kecamatan Pulau Laut Timur', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.36.0000', 'nama' => 'Kecamatan Pulau Laut Utara', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.37.0000', 'nama' => 'Kecamatan Pulau Laut Tengah', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.38.0000', 'nama' => 'Kecamatan Kelumpang Selatan', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.39.0000', 'nama' => 'Kecamatan Kelumpang Hilir', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.40.0000', 'nama' => 'Kecamatan Kelumpang Hulu', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.41.0000', 'nama' => 'Kecamatan Kelumpang Barat', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.42.0000', 'nama' => 'Kecamatan Kelumpang Tengah', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.43.0000', 'nama' => 'Kecamatan Kelumpang Utara', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.44.0000', 'nama' => 'Kecamatan Pamukan Selatan', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.45.0000', 'nama' => 'Kecamatan Pamukan Utara', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.46.0000', 'nama' => 'Kecamatan Pamukan Barat', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.49.0000', 'nama' => 'Kecamatan Sungai Durian', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.50.0000', 'nama' => 'Kecamatan Pulau Laut Tanjung Selayar', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.51.0000', 'nama' => 'Kecamatan Pulau Laut Sigam', 'jenis' => 'kecamatan', 'is_active' => 1],
            ['kode' => '7.01.0.00.0.00.35.0000', 'nama' => 'Kecamatan Pulau Sebuku', 'jenis' => 'kecamatan', 'is_active' => 1],
        ];

        foreach ($data as $row) {
            DB::table('perangkat_daerah')->updateOrInsert(['kode' => $row['kode']], $row);
        }
    }

    private function seedPrograms(): void
    {
        // [strategi_id, perangkat_daerah_id, kode_program, nama_program]
        $data = [
            [1,  1,  '1.01.2.22.0.00.01.0000', 'Program Pengelolaan Pendidikan'],
            [1,  2,  '1.02.0.00.0.00.02.0000', 'Program Pemenuhan Upaya Kesehatan Perorangan Dan Upaya Kesehatan Masyarakat'],
            [1,  3,  '1.05.0.00.0.00.07.0000', 'Program Penanggulangan Bencana'],
            [1,  4,  '1.06.0.00.0.00.08.0000', 'Program Pemberdayaan Sosial'],
            [1,  5,  '2.08.2.14.0.00.10.0000', 'Program Pembinaan Keluarga Berencana (KB)'],
            [1,  6,  '2.09.3.27.0.00.11.0000', 'Program Peningkatan Diversifikasi dan Ketahanan Pangan Masyarakat'],
            [1,  7,  '2.12.0.00.0.00.13.0000', 'Program Pendaftaran Penduduk'],
            [1,  19, '7.01.0.00.0.00.36.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [1,  27, '7.01.0.00.0.00.44.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [1,  30, '7.01.0.00.0.00.49.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  1,  '1.01.2.22.0.00.01.0000', 'Program Pengembangan Kebudayaan'],
            [2,  4,  '1.06.0.00.0.00.08.0000', 'Program Pemberdayaan Sosial'],
            [2,  8,  '2.07.3.32.0.00.09.0000', 'Program Pelatihan Kerja dan Produktivitas Tenaga Kerja'],
            [2,  5,  '2.08.2.14.0.00.10.0000', 'Program Pengarusutamaan Gender dan Pemberdayaan Perempuan'],
            [2,  6,  '2.09.3.27.0.00.11.0000', 'Program Peningkatan Diversifikasi dan Ketahanan Pangan Masyarakat'],
            [2,  9,  '2.13.0.00.0.00.14.0000', 'Program Pemberdayaan Lembaga Kemasyarakatan, Lembaga Adat dan Masyarakat Hukum Adat'],
            [2,  10, '2.17.3.31.3.30.17.0000', 'Program Pemberdayaan Usaha Menengah, Usaha Kecil, dan Usaha Mikro (UMKM)'],
            [2,  11, '3.25.0.00.0.00.20.0000', 'Program Pengelolaan Perikanan Tangkap'],
            [2,  15, '7.01.0.00.0.00.31.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  16, '7.01.0.00.0.00.32.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  17, '7.01.0.00.0.00.33.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  18, '7.01.0.00.0.00.34.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  19, '7.01.0.00.0.00.36.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  20, '7.01.0.00.0.00.37.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  21, '7.01.0.00.0.00.38.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  22, '7.01.0.00.0.00.39.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  23, '7.01.0.00.0.00.40.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  24, '7.01.0.00.0.00.41.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  25, '7.01.0.00.0.00.42.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  26, '7.01.0.00.0.00.43.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  27, '7.01.0.00.0.00.44.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  29, '7.01.0.00.0.00.46.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  30, '7.01.0.00.0.00.49.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  31, '7.01.0.00.0.00.50.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [2,  32, '7.01.0.00.0.00.51.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [3,  1,  '1.01.2.22.0.00.01.0000', 'Program Pengelolaan Pendidikan'],
            [3,  12, '1.03.0.00.0.00.03.0000', 'Program Pengelolaan Sumber Daya Air (SDA)'],
            [3,  13, '1.04.0.00.0.00.04.0000', 'Program Pengembangan Perumahan'],
            [3,  8,  '2.07.3.32.0.00.09.0000', 'Program Pembangunan Kawasan Transmigrasi'],
            [3,  6,  '2.09.3.27.0.00.11.0000', 'Program Peningkatan Diversifikasi dan Ketahanan Pangan Masyarakat'],
            [3,  9,  '2.13.0.00.0.00.14.0000', 'Program Penataan Desa'],
            [3,  14, '2.15.0.00.0.00.15.0000', 'Program Penyelenggaraan Lalu Lintas dan Angkutan Jalan'],
            [3,  11, '3.25.0.00.0.00.20.0000', 'Program Pengelolaan Perikanan Budidaya'],
            [3,  33, '7.01.0.00.0.00.35.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [3,  28, '7.01.0.00.0.00.45.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
            [3,  30, '7.01.0.00.0.00.49.0000', 'Program Pemberdayaan Masyarakat Desa dan Kelurahan'],
        ];

        // Resolve perangkat_daerah id by order (they were inserted sequentially by kode)
        $pdMap = DB::table('perangkat_daerah')->orderBy('id')->pluck('id', 'kode');
        $strategiMap = DB::table('strategi_oppkpke')->orderBy('id')->pluck('id', 'kode');

        // perangkat_daerah_id references by position in the original insert order
        // We use the actual IDs from the DB as extracted during analysis
        foreach ($data as $row) {
            DB::table('programs')->updateOrInsert(
                [
                    'strategi_id'        => $row[0],
                    'perangkat_daerah_id' => $row[1],
                    'nama_program'       => $row[3],
                ],
                [
                    'strategi_id'        => $row[0],
                    'perangkat_daerah_id' => $row[1],
                    'kode_program'       => $row[2],
                    'nama_program'       => $row[3],
                    'is_active'          => 1,
                ]
            );
        }
    }

    private function seedKegiatan(): void
    {
        // [program_id, nama_kegiatan]
        $data = [
            [1,  'Pengelolaan Dana BOS Sekolah Dasar'],
            [1,  'Pengelolaan Dana BOS Sekolah Menengah Pertama'],
            [1,  'Pengelolaan Dana BOP PAUD'],
            [1,  'Pengelolaan Dana BOP Sekolah Nonformal/Kesetaraan'],
            [2,  'Penyediaan Layanan Kesehatan untuk UKM dan UKP Rujukan Tingkat Daerah Kabupaten/Kota'],
            [3,  'Pelayanan Pencegahan dan Kesiapsiagaan Terhadap Bencana'],
            [3,  'Pelayanan Penyelamatan dan Evakuasi Korban Bencana'],
            [4,  'Pemberdayaan Sosial Komunitas Adat Terpencil (KAT)'],
            [4,  'Rehabilitasi Sosial Dasar Penyandang Disabilitas Terlantar, Anak Terlantar, Lanjut Usia Terlantar, serta Gelandangan Pengemis di Luar Panti Sosial'],
            [4,  'Pengelolaan Data Fakir Miskin Cakupan Daerah Kabupaten/Kota'],
            [5,  'Pemberdayaan dan Peningkatan Keluarga Sejahtera'],
            [6,  'Penyediaan dan Penyaluran Pangan Pokok atau Pangan Lainnya sesuai dengan Kebutuhan Daerah Kabupaten/Kota dalam rangka Stabilisasi Pasokan dan Harga Pangan'],
            [7,  'Pelayanan Pencatatan Sipil'],
            [7,  'Pengumpulan Data Kependudukan dan Pemanfaatan dan Penyajian Database Kependudukan'],
            [7,  'Penyelenggaraan Pengelolaan Informasi Administrasi Kependudukan'],
            [7,  'Penyusunan Profil Kependudukan'],
            [8,  'Pemberdayaan Lembaga Kemasyarakatan Tingkat Kecamatan'],
            [9,  'Pemberdayaan Lembaga Kemasyarakatan Tingkat Kecamatan'],
            [10, 'Pemberdayaan Lembaga Kemasyarakatan Tingkat Kecamatan'],
            [11, 'Pelestarian Kesenian Tradisional'],
            [12, 'Pemberdayaan Sosial Komunitas Adat Terpencil (KAT)'],
            [13, 'Pelaksanaan Pelatihan berdasarkan Unit Kompetensi'],
            [14, 'Penguatan dan Pengembangan Lembaga Penyedia Layanan Pemberdayaan Perempuan'],
            [15, 'Pelaksanaan Pencapaian Target Konsumsi Pangan Perkapita/Tahun sesuai dengan Angka Kecukupan Gizi'],
            [15, 'Penyediaan dan Pengembangan Sarana Pertanian'],
            [15, 'Pengendalian dan Penanggulangan Bencana Pertanian'],
            [15, 'Penyuluhan Pertanian'],
            [16, 'Pemberdayaan Lembaga Kemasyarakatan yang Bergerak di Bidang Pemberdayaan Desa dan Lembaga Adat Tingkat Daerah Kabupaten/Kota'],
            [17, 'Pemberdayaan Usaha Mikro yang Dilakukan melalui Pendataan, Kemitraan, Kemudahan Perijinan, Penguatan Kelembagaan dan Koordinasi dengan Para Pemangku Kepentingan'],
            [18, 'Pemberdayaan Nelayan Kecil dalam Daerah Kabupaten/Kota'],
            [18, 'Pengelolaan Pembudidayaan Ikan'],
            [19, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [20, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [21, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [22, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [23, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [24, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [25, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [26, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [27, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [28, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [29, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [30, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [31, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [32, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [33, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [34, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [35, 'Pemberdayaan dan Kesejahteraan Keluarga Tingkat Kecamatan dan Kelurahan'],
            [36, 'Pengelolaan Pendidikan Sekolah Dasar'],
            [36, 'Pengelolaan Pendidikan Sekolah Menengah Pertama'],
            [36, 'Pengelolaan Pendidikan PAUD'],
            [37, 'Pengelolaan SDA dan Bangunan Pengaman Pantai pada Wilayah Sungai'],
            [37, 'Pengembangan dan Pengelolaan Sistem Irigasi'],
            [37, 'Pengelolaan dan Pengembangan Sistem Penyediaan Air Minum'],
            [37, 'Pengelolaan dan Pengembangan Sistem Air Limbah Domestik'],
            [38, 'Pembangunan dan Rehabilitasi Rumah Korban Bencana'],
            [38, 'Penataan dan Peningkatan Kualitas Kawasan Permukiman Kumuh'],
            [38, 'Penyediaan Prasarana, Sarana dan Utilitas Umum (PSU) Perumahan'],
            [39, 'Penataan Persebaran Penduduk yang Berasal dari Lintas Daerah Kabupaten/Kota'],
            [40, 'Penanganan Kerawanan Pangan'],
            [40, 'Penyuluhan Pertanian'],
            [41, 'Penyelenggaraan Penataan Desa'],
            [41, 'Fasilitasi Kerja Sama Antar Desa'],
            [42, 'Pengelolaan Angkutan Umum untuk Jasa Angkutan Orang dan/atau Barang'],
            [42, 'Pengelolaan Pelabuhan Sungai dan Danau'],
            [43, 'Pengelolaan Pembudidayaan Ikan'],
            [44, 'Kegiatan Pemberdayaan Masyarakat'],
            [45, 'Pemberdayaan Lembaga Kemasyarakatan Tingkat Kecamatan'],
            [46, 'Pemberdayaan Lembaga Kemasyarakatan Tingkat Kecamatan'],
        ];

        foreach ($data as $row) {
            DB::table('kegiatan')->updateOrInsert(
                ['program_id' => $row[0], 'nama_kegiatan' => $row[1]],
                ['program_id' => $row[0], 'nama_kegiatan' => $row[1], 'is_active' => 1]
            );
        }
    }

    private function seedSubKegiatan(): void
    {
        // [kegiatan_id, nama_sub_kegiatan]
        $data = [
            [1,  'Pengadaan Perlengkapan Peserta Didik'],
            [1,  'Penyediaan Biaya Personil Peserta Didik Sekolah Dasar'],
            [2,  'Penyediaan Biaya Personil Peserta Didik Sekolah Menengah Pertama'],
            [2,  'Pengadaan Perlengkapan Peserta Didik'],
            [3,  'Penyediaan Biaya Personil Peserta Didik PAUD'],
            [4,  'Penyediaan Biaya Personil Peserta Didik Nonformal/Kesetaraan'],
            [5,  'Pengelolaan Pelayanan Kesehatan Ibu Hamil'],
            [5,  'Pengelolaan Pelayanan Kesehatan Ibu Bersalin'],
            [5,  'Pengelolaan Pelayanan Kesehatan Bayi Baru Lahir'],
            [5,  'Pengelolaan Pelayanan Kesehatan Balita'],
            [5,  'Pengelolaan Pelayanan Kesehatan pada Usia Pendidikan Dasar'],
            [5,  'Pengelolaan Pelayanan Kesehatan pada Usia Produktif'],
            [5,  'Pengelolaan Pelayanan Kesehatan Penderita Hipertensi'],
            [5,  'Pengelolaan Pelayanan Kesehatan Penderita Diabetes Melitus'],
            [5,  'Pengelolaan Pelayanan Kesehatan Orang dengan Gangguan Jiwa Berat'],
            [5,  'Pengelolaan Pelayanan Kesehatan Orang Terduga Tuberkulosis'],
            [5,  'Pengelolaan Pelayanan Kesehatan Orang dengan Risiko Terinfeksi HIV'],
            [5,  'Pengelolaan Pelayanan Kesehatan bagi Penduduk Terdampak Krisis Kesehatan Akibat Bencana dan/atau Berpotensi Bencana'],
            [5,  'Pengelolaan Pelayanan Kesehatan Gizi Masyarakat'],
            [5,  'Pengelolaan Pelayanan Kesehatan Orang dengan Masalah Kesehatan Jiwa (ODMK)'],
            [5,  'Pengelolaan Pelayanan Kesehatan Jiwa dan NAPZA'],
            [5,  'Pelayanan Kesehatan Penyakit Menular dan Tidak Menular'],
            [5,  'Pengelolaan Jaminan Kesehatan Masyarakat'],
            [6,  'Sosialisasi Komunikasi Informasi dan Edukasi (KIE) Rawan Bencana Kabupaten/Kota (Per Jenis Ancaman Bencana)'],
            [6,  'Pelatihan Keluarga Tanggap Bencana Alam'],
            [7,  'Pencarian Pertolongan dan Evakuasi Korban Bencana Kabupaten/Kota'],
            [7,  'Penyediaan Logistik Penyelamatan dan Evakuasi Korban Bencana Kabupaten/Kota'],
            [8,  'Peningkatan Kemampuan Potensi Sumber Kesejahteraan Sosial Kelembagaan Masyarakat Kewenangan Kabupaten/Kota'],
            [9,  'Penyediaan Permakanan'],
            [9,  'Penyediaan Sandang'],
            [9,  'Penyediaan Alat Bantu'],
            [9,  'Pemberian Pelayanan Reunifikasi Keluarga'],
            [9,  'Pemberian Bimbingan Fisik Mental Spiritual dan Sosial'],
            [9,  'Pemberian Bimbingan Sosial kepada Keluarga Penyandang Disabilitas Terlantar Anak Terlantar Lanjut Usia Terlantar serta Gelandangan Pengemis dan Masyarakat'],
            [9,  'Fasilitasi Pembuatan Nomor Induk Kependudukan Akta Kelahiran Surat Nikah dan Kartu Identitas Anak'],
            [9,  'Pemberian Akses ke Layanan Pendidikan dan Kesehatan Dasar'],
            [9,  'Pemberian Layanan Data dan Pengaduan'],
            [9,  'Pemberian Layanan Kedaruratan'],
            [9,  'Pemberian Pelayanan Penelusuran Keluarga'],
            [9,  'Pemberian Layanan Rujukan'],
            [10, 'Pendataan Fakir Miskin Cakupan Daerah Kabupaten/Kota'],
            [10, 'Pengelolaan Data Fakir Miskin Cakupan Daerah Kabupaten/Kota'],
            [10, 'Fasilitasi Bantuan Sosial Kesejahteraan Keluarga'],
            [10, 'Fasilitasi Bantuan Pengembangan Ekonomi Masyarakat'],
            [10, 'Penyediaan Makanan'],
            [10, 'Penyediaan Sandang'],
            [11, 'Pendampingan Keluarga Berisiko Stunting (Termasuk remaja Calon Pengantin/Calon PUS Ibu Hamil Pasca salin/kelahiran Baduta/Balita)'],
            [12, 'Pengadaan Cadangan Pangan Pemerintah Kabupaten/Kota'],
            [13, 'Peningkatan dalam Pelayanan Pencatatan Sipil'],
            [14, 'Pengolahan dan Penyajian Data Kependudukan'],
            [14, 'Kerja Sama Pemanfaatan Data Kependudukan'],
            [15, 'Fasilitasi Terkait Pengelolaan Informasi Administrasi Kependudukan'],
            [16, 'Penyediaan Data Kependudukan Kabupaten/Kota'],
            [16, 'Penyusunan Profil Data Perkembangan dan Proyeksi Kependudukan serta Kebutuhan yang Lain'],
            [17, 'Pelatihan Keluarga Tanggap Bencana Alam'],
            [18, 'Peningkatan Ketahanan Pangan Keluarga'],
            [19, 'Peningkatan Ketahanan Pangan Keluarga'],
            [19, 'Pelatihan Keluarga Tanggap Bencana Alam'],
            [20, 'Peningkatan Pendidikan dan Pelatihan Sumber Daya Manusia Kesenian Tradisional'],
            [21, 'Fasilitasi Pemberdayaan Sosial KAT'],
            [22, 'Proses Pelaksanaan Pendidikan dan Pelatihan Keterampilan bagi Pencari Kerja berdasarkan Klaster Kompetensi'],
            [22, 'Pembinaan Lembaga Pelatihan Kerja Swasta'],
            [22, 'Penyelenggaraan Unit Layanan Disabilitas Ketenagakerjaan'],
            [22, 'Perluasan Kesempatan Kerja'],
            [23, 'Penyediaan Data Gender dan Anak di Kewenangan Kabupaten/Kota'],
            [24, 'Pemberdayaan Masyarakat dalam Penganekaragaman Konsumsi Pangan Berbasis Sumber Daya Lokal'],
            [25, 'Pendampingan Penggunaan Sarana Pendukung Pertanian'],
            [25, 'Pengadaan Hijauan Pakan Ternak yang Sumbernya dari Daerah Kabupaten/Kota Lain'],
            [26, 'Pengendalian Organisme Pengganggu Tumbuhan (OPT) Tanaman Pangan, Hortikultura, dan Perkebunan'],
            [27, 'Pengembangan Kapasitas Kelembagaan Petani di Kecamatan dan Desa'],
            [28, 'Fasilitasi Tim Penggerak PKK dalam Penyelenggaraan Gerakan Pemberdayaan Masyarakat dan Kesejahteraan Keluarga'],
            [29, 'Pemberdayaan Melalui Kemitraan Usaha Mikro'],
            [30, 'Penyediaan Prasarana Usaha Perikanan Tangkap'],
            [30, 'Pengembangan Kapasitas Nelayan Kecil'],
            [30, 'Pelaksanaan Fasilitasi Pembentukan dan Pengembangan Kelembagaan Nelayan Kecil'],
            [30, 'Pelaksanaan Fasilitasi Bantuan Pendanaan, Bantuan Pembiayaan, Kemitraan Usaha'],
            [31, 'Pengembangan Kapasitas Pembudi Daya Ikan Kecil'],
            [31, 'Pelaksanaan Fasilitasi Pembentukan dan Pengembangan Kelembagaan Pembudi Daya Ikan Kecil'],
            [31, 'Pemberian Pendampingan, Kemudahanan Akses Ilmu Pengetahuan, Teknologi dan Informasi, serta Penyelenggaraan Pendidikan dan Pelatihan'],
            [31, 'Penyediaan Prasarana Pembudidayaan Ikan dalam 1 (Satu) Daerah Kabupaten/Kota'],
            [31, 'Penjaminan Ketersediaan Sarana Pembudidayaan Ikan dalam 1 (Satu) Daerah Kabupaten/Kota'],
            [31, 'Peningkatan Ketersediaan Ikan untuk Konsumsi dan Usaha Pengolahan dalam 1 (Satu) Daerah Kabupaten/Kota'],
            [31, 'Pemberian Fasilitas bagi Pelaku Usaha Perikanan Skala Mikro dan Kecil dalam 1 (Satu) Daerah Kabupaten/Kota'],
            [32, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [33, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [34, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [35, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [36, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [37, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [38, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [39, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [40, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [41, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [42, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [43, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [44, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [45, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [46, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [47, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [48, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Taraf Hidup Keluarga Melalui Kehidupan Berkoperasi dan Pengembangan Ekonomi Lainnya'],
            [49, 'Pembangunan Sarana, Prasarana dan Utilitas Sekolah'],
            [49, 'Pembangunan Ruang Kelas Baru'],
            [49, 'Rehabilitasi Sedang/Berat Sarana, Prasarana dan Utilitas Sekolah'],
            [49, 'Rehabilitasi Sedang/Berat Ruang Kelas Sekolah'],
            [50, 'Pembangunan Sarana, Prasarana dan Utilitas Sekolah'],
            [50, 'Rehabilitasi Sedang/Berat Ruang Kelas Sekolah'],
            [50, 'Rehabilitasi Sedang/Berat Sarana, Prasarana dan Utilitas Sekolah'],
            [50, 'Pembangunan Ruang Kelas Baru'],
            [51, 'Pembangunan Ruang Kelas Baru'],
            [51, 'Rehabilitasi Sedang/Berat Ruang Kelas Sekolah'],
            [51, 'Rehabilitasi Sedang/Berat Sarana, Prasarana dan Utilitas Sekolah'],
            [51, 'Rehabilitasi Sedang/Berat Ruang Kelas Sekolah'],
            [52, 'Operasi dan Pemeliharaan Embung Air Baku'],
            [52, 'Operasi dan Pemeliharaan Tanggul dan Tebing Sungai'],
            [52, 'Operasi dan Pemeliharaan Embung dan Penampung Air Lainnya'],
            [52, 'Operasi dan Pemeliharaan Sumur Air Tanah untuk Air Baku'],
            [52, 'Rehabilitasi Unit Air Baku'],
            [52, 'Rehabilitasi Embung dan Penampungan Air Lainnya'],
            [52, 'Pembangunan Sumur Air Tanah untuk Air Baku'],
            [52, 'Pembangunan Embung dan Penampung Air Lainnya'],
            [53, 'Rehabilitasi Jaringan Irigasi Rawa'],
            [53, 'Operasi dan Pemeliharaan Jaringan Irigasi Rawa'],
            [53, 'Penyusunan Rencana Teknis dan Dokumen Lingkungan Hidup untuk Konstruksi Irigasi dan Rawa'],
            [54, 'Pembangunan Sistem Penyediaan Air Minum (SPAM) Jaringan Perpipaan'],
            [54, 'Operasi dan Pemeliharaan Sistem Penyediaan Air Minum (SPAM)'],
            [55, 'Penyediaan Sub Sistem Pengolahan Air Limbah Domestik (SPALD) Setempat'],
            [55, 'Penyusunan Rencana, Kebijakan, Strategi dan Teknis Sistem Pengelolaan Air Limbah Domestik (SPALD)'],
            [56, 'Rehabilitasi Rumah bagi Korban Bencana'],
            [56, 'Pembangunan Rumah bagi Korban Bencana'],
            [57, 'Koordinasi dan Sinkronisasi Penyelenggaraan Kawasan Permukiman'],
            [57, 'Survei dan Penetapan Lokasi Perumahan dan Permukiman Kumuh'],
            [57, 'Perbaikan Rumah Tidak Layak Huni untuk Pencegahan Terhadap Tumbuh dan Berkembangnya Permukiman Kumuh'],
            [58, 'Perencanaan Penyediaan PSU Perumahan'],
            [58, 'Penyediaan Prasarana, Sarana, dan Utilitas Umum di Perumahan untuk Menunjang Fungsi Hunian'],
            [59, 'Penyediaan Tanah untuk Pembangunan Kawasan Transmigrasi'],
            [59, 'Pelaksanaan Penataan Penduduk Setempat Sekitar Lokasi Kawasan Transmigrasi'],
            [60, 'Penyediaan Infrastruktur Pendukung Kemandirian Pangan Lainnya'],
            [60, 'Penyusunan, Pemutakhiran dan Analisis Peta Ketahanan dan Kerentanan Pangan'],
            [60, 'Pelaksanaan Pengadaan, Pengelolaan, dan Penyaluran Cadangan Pangan pada Kerawanan Pangan'],
            [61, 'Pembangunan, Rehabilitasi dan Pemeliharaan Balai Penyuluh di Kecamatan serta Sarana Pendukungnya'],
            [61, 'Pembangunan, Rehabilitasi dan Pemeliharaan Prasarana Pertanian Lainnya'],
            [62, 'Fasilitasi Sarana dan Prasarana Desa'],
            [63, 'Fasilitasi Penyelenggaraan Administrasi Pemerintahan Desa'],
            [63, 'Fasilitasi Penyusunan Perencanaan Pembangunan Desa'],
            [63, 'Fasilitasi Pengelolaan Keuangan Desa'],
            [63, 'Fasilitasi Pengelolaan Aset Desa'],
            [63, 'Fasilitasi Penataan, Pemberdayaan dan Pendayagunaan Kelembagaan Lembaga Kemasyarakatan Desa/Kelurahan'],
            [64, 'Pengendalian dan Pengawasan Ketersediaan Angkutan Umum untuk Jasa Angkutan Orang dan/atau Barang Antar Kota dalam 1 (Satu) Kabupaten/Kota'],
            [64, 'Penyediaan Angkutan Umum untuk Jasa Angkutan Orang dan/atau Barang Antar Kota dalam 1 (Satu) Daerah Kabupaten/Kota'],
            [65, 'Pembangunan Pelabuhan Pengumpan Lokal'],
            [65, 'Pengoperasian dan Pemeliharaan Pelabuhan Sungai dan Danau'],
            [66, 'Perencanaan, Pengembangan, Pemanfaatan dan Perlindungan Lahan untuk Pembudidayaan Ikan di Darat'],
            [67, 'Fasilitasi Penyusunan Program dan Pelaksanaan Pemberdayaan Masyarakat Desa'],
            [68, 'Peningkatan Kesadaran Keluarga dalam Mewujudkan Rumah Sehat dan Layak Huni serta Kesadaran Hukum tentang Kepemilikan Rumah'],
            [69, 'Penumbuhan Kesadaran Keluarga dalam Peningkatan Derajat Kesehatan Keluarga dan Lingkungan dengan Menerapkan Perilaku Hidup Bersih dan Sehat'],
            [69, 'Fasilitasi Penyusunan Program dan Pelaksanaan Pemberdayaan Masyarakat Desa'],
        ];

        foreach ($data as $row) {
            DB::table('sub_kegiatan')->updateOrInsert(
                ['kegiatan_id' => $row[0], 'nama_sub_kegiatan' => $row[1]],
                ['kegiatan_id' => $row[0], 'nama_sub_kegiatan' => $row[1], 'is_active' => 1]
            );
        }
    }
}
