@extends('layouts.oppkpke')

@section('title', 'Panduan Penggunaan')
@section('page-title', 'Panduan Penggunaan Sistem')
@section('page-subtitle', 'OPPKPKE – Optimalisasi Pelaksanaan Program Kemiskinan')

@section('content')

<div class="max-w-4xl mx-auto space-y-4 md:space-y-6">

    {{-- Intro --}}
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl p-4 md:p-6 text-white">
        <div class="flex items-start gap-3 md:gap-4">
            <i class="fas fa-book-open text-2xl md:text-3xl text-blue-200 mt-1 flex-shrink-0"></i>
            <div>
                <h2 class="text-base md:text-xl font-bold mb-1">Panduan Lengkap Penggunaan Sistem OPPKPKE</h2>
                <p class="text-blue-100 text-xs md:text-sm">
                    Sistem ini digunakan untuk mengelola data laporan realisasi anggaran program pengentasan kemiskinan
                    di Kabupaten Kotabaru. Baca panduan berikut agar Anda dapat menggunakan sistem dengan benar.
                </p>
            </div>
        </div>
    </div>

    {{-- Role Pengguna --}}
    <div class="bg-white rounded-xl shadow-sm border p-4 md:p-6">
        <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-users text-blue-600"></i> Peran Pengguna (Role)
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="border border-yellow-200 bg-yellow-50 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fas fa-shield-alt text-yellow-600"></i>
                    <h4 class="font-semibold text-yellow-800">Admin Master</h4>
                </div>
                <ul class="text-sm text-yellow-700 space-y-1.5">
                    <li><i class="fas fa-check text-yellow-500 mr-1.5"></i> Akses ke <strong>Dashboard</strong> ringkasan semua data</li>
                    <li><i class="fas fa-check text-yellow-500 mr-1.5"></i> Melihat dan mengedit data <strong>semua</strong> perangkat daerah</li>
                    <li><i class="fas fa-check text-yellow-500 mr-1.5"></i> Akses Explorer, Statistik, Rekap Laporan</li>
                    <li><i class="fas fa-check text-yellow-500 mr-1.5"></i> Export data ke Excel &amp; PDF</li>
                    <li><i class="fas fa-check text-yellow-500 mr-1.5"></i> Melihat siapa yang mengisi/mengubah data</li>
                </ul>
            </div>
            <div class="border border-green-200 bg-green-50 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fas fa-user text-green-600"></i>
                    <h4 class="font-semibold text-green-800">Operator Daerah</h4>
                </div>
                <ul class="text-sm text-green-700 space-y-1.5">
                    <li><i class="fas fa-check text-green-500 mr-1.5"></i> Input laporan untuk <strong>perangkat daerah sendiri</strong></li>
                    <li><i class="fas fa-check text-green-500 mr-1.5"></i> Akses Explorer (data terbatas sesuai daerah)</li>
                    <li><i class="fas fa-check text-green-500 mr-1.5"></i> Melihat Statistik &amp; Rekap Laporan daerah sendiri</li>
                    <li><i class="fas fa-times text-red-400 mr-1.5"></i> Tidak bisa akses Dashboard Master</li>
                    <li><i class="fas fa-times text-red-400 mr-1.5"></i> Tidak bisa mengubah data perangkat lain</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Panduan per fitur --}}
    <div class="bg-white rounded-xl shadow-sm border divide-y">

        {{-- Dashboard --}}
        <div class="p-4 md:p-5 cursor-pointer" onclick="toggleSection('sec1')">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-chart-pie text-blue-600"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-sm md:text-base">Dashboard</h4>
                        <p class="text-xs text-gray-500">Ringkasan statistik &amp; KPI &ndash; khusus Admin Master</p>
                    </div>
                </div>
                <i class="fas fa-chevron-down text-gray-400 transition flex-shrink-0 ml-2" id="icon-sec1"></i>
            </div>
            <div id="sec1" class="hidden mt-4 text-sm text-gray-600 space-y-2 pl-3 md:pl-12">
                <p>Dashboard menampilkan ringkasan data secara keseluruhan untuk tahun yang dipilih.</p>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Pilih <strong>tahun</strong> di pojok kanan atas untuk mengganti periode tampilan.</li>
                    <li>Kartu KPI menampilkan total alokasi, total realisasi, persentase, dan jumlah sub kegiatan.</li>
                    <li>Grafik batang membandingkan alokasi vs realisasi per strategi.</li>
                    <li>Tabel rekap menampilkan top 10 perangkat daerah berdasarkan realisasi tertinggi.</li>
                </ul>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Dashboard hanya dapat diakses oleh <strong>Admin Master</strong>.
                </div>
            </div>
        </div>

        {{-- Explorer Data --}}
        <div class="p-4 md:p-5 cursor-pointer" onclick="toggleSection('sec2')">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-folder-tree text-purple-600"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-sm md:text-base">Explorer Data</h4>
                        <p class="text-xs text-gray-500">Jelajahi data secara hierarkis dengan filter lengkap</p>
                    </div>
                </div>
                <i class="fas fa-chevron-down text-gray-400 flex-shrink-0 ml-2" id="icon-sec2"></i>
            </div>
            <div id="sec2" class="hidden mt-4 text-sm text-gray-600 space-y-2 pl-3 md:pl-12">
                <p>Explorer memungkinkan Anda melihat data sub kegiatan secara hierarkis dan langsung mengedit laporan dari sini.</p>
                <h5 class="font-semibold text-gray-700 mt-3">Cara Menggunakan Filter:</h5>
                <ol class="list-decimal pl-5 space-y-1">
                    <li>Pilih <strong>Strategi</strong> terlebih dahulu (opsional).</li>
                    <li>Pilih <strong>Perangkat Daerah</strong> untuk mempersempit data.</li>
                    <li>Pilih <strong>Program</strong> berdasarkan perangkat daerah yang dipilih.</li>
                    <li>Pilih <strong>Kegiatan</strong> untuk filter lebih spesifik.</li>
                    <li>Gunakan kolom <strong>Cari</strong> untuk pencarian cepat berdasarkan nama sub kegiatan.</li>
                    <li>Klik tombol <strong>Terapkan Filter</strong>.</li>
                </ol>
                <h5 class="font-semibold text-gray-700 mt-3">Membaca Data:</h5>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Data dikelompokkan per <strong>Strategi → Perangkat Daerah → Program → Kegiatan → Sub Kegiatan</strong>.</li>
                    <li>Badge <span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-xs">Terisi</span> berarti laporan sudah diinput.</li>
                    <li>Badge <span class="bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded text-xs">Kosong</span> berarti belum ada data laporan.</li>
                    <li>Persentase progress bar menunjukkan tingkat realisasi anggaran.</li>
                </ul>
            </div>
        </div>

        {{-- Input Laporan --}}
        <div class="p-4 md:p-5 cursor-pointer" onclick="toggleSection('sec3')">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-file-pen text-green-600"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-sm md:text-base">Input Laporan</h4>
                        <p class="text-xs text-gray-500">Cara mengisi data laporan realisasi anggaran</p>
                    </div>
                </div>
                <i class="fas fa-chevron-down text-gray-400 flex-shrink-0 ml-2" id="icon-sec3"></i>
            </div>
            <div id="sec3" class="hidden mt-4 text-sm text-gray-600 space-y-2 pl-3 md:pl-12">
                <h5 class="font-semibold text-gray-700">Langkah-langkah Input Laporan:</h5>
                <ol class="list-decimal pl-5 space-y-2">
                    <li>
                        <strong>Pilih Tahun</strong> &ndash; Gunakan selector tahun di pojok kanan atas.
                    </li>
                    <li>
                        <strong>Temukan Sub Kegiatan</strong> &ndash; Gunakan filter di sidebar kiri:
                        pilih Strategi → Perangkat Daerah → Program → Kegiatan, lalu klik <em>Terapkan Filter</em>.
                    </li>
                    <li>
                        <strong>Klik Tombol Input</strong> &ndash; Klik ikon pensil (<i class="fas fa-pen text-blue-500"></i>) pada baris sub kegiatan yang ingin diisi.
                    </li>
                    <li>
                        <strong>Isi Form</strong> &ndash; Modal akan muncul. Isi data berikut:
                        <ul class="list-disc pl-5 mt-1 space-y-1 text-gray-500">
                            <li><strong>Alokasi Anggaran</strong> (wajib) &ndash; Total pagu anggaran untuk sub kegiatan ini.</li>
                            <li><strong>Realisasi Semester 1</strong> &ndash; Realisasi s.d. Juni.</li>
                            <li><strong>Realisasi Semester 2</strong> &ndash; Realisasi Juli &ndash; Desember.</li>
                            <li><strong>Jumlah Sasaran &amp; Satuan</strong> &ndash; Contoh: 265 Sekolah, 200 Orang.</li>
                            <li><strong>Penerima Manfaat</strong> &ndash; Langsung, tidak langsung, penunjang.</li>
                            <li><strong>Sumber Pembiayaan</strong> &ndash; APBD / APBN / DAK / DAU / DBH.</li>
                            <li><strong>Lokasi</strong> &ndash; Wilayah cakupan program.</li>
                        </ul>
                    </li>
                    <li>
                        <strong>Simpan</strong> &ndash; Klik tombol <em>Simpan Data</em>. Sistem akan menghitung total realisasi secara otomatis.
                    </li>
                </ol>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-yellow-700 mt-2">
                    <i class="fas fa-lightbulb mr-1"></i>
                    <strong>Tips:</strong> Jika sub kegiatan sudah memiliki data, form akan terbuka dalam mode <em>edit</em>.
                    Data lama akan ditampilkan dan Anda cukup mengubah yang perlu diperbarui.
                </div>
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-red-700 mt-2">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Perhatian:</strong> Operator Daerah hanya dapat mengisi data untuk perangkat daerah yang terdaftar di akun mereka.
                </div>
            </div>
        </div>

        {{-- Statistik --}}
        <div class="p-4 md:p-5 cursor-pointer" onclick="toggleSection('sec4')">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-orange-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-chart-bar text-orange-600"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-sm md:text-base">Statistik</h4>
                        <p class="text-xs text-gray-500">Grafik dan tabel perbandingan realisasi per strategi</p>
                    </div>
                </div>
                <i class="fas fa-chevron-down text-gray-400 flex-shrink-0 ml-2" id="icon-sec4"></i>
            </div>
            <div id="sec4" class="hidden mt-4 text-sm text-gray-600 space-y-2 pl-3 md:pl-12">
                <p>Halaman Statistik menampilkan ringkasan realisasi anggaran dikelompokkan per <strong>Strategi OPPKPKE</strong>.</p>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Pilih tahun yang ingin ditampilkan di selector atas.</li>
                    <li>Grafik batang membandingkan alokasi vs realisasi per strategi.</li>
                    <li>Progress bar di bawah tabel menunjukkan persentase realisasi secara visual.</li>
                    <li>Warna <span class="bg-green-100 text-green-700 px-1 rounded">hijau</span> = ≥80%, <span class="bg-yellow-100 text-yellow-700 px-1 rounded">kuning</span> = 50–79%, <span class="bg-red-100 text-red-700 px-1 rounded">merah</span> = &lt;50%.</li>
                </ul>
            </div>
        </div>

        {{-- Rekap Laporan --}}
        <div class="p-4 md:p-5 cursor-pointer" onclick="toggleSection('sec5')">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-teal-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-table-list text-teal-600"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-sm md:text-base">Rekap Laporan</h4>
                        <p class="text-xs text-gray-500">Tabel lengkap semua data laporan dengan filter</p>
                    </div>
                </div>
                <i class="fas fa-chevron-down text-gray-400 flex-shrink-0 ml-2" id="icon-sec5"></i>
            </div>
            <div id="sec5" class="hidden mt-4 text-sm text-gray-600 space-y-2 pl-3 md:pl-12">
                <p>Rekap Laporan menampilkan tabel detail semua laporan yang telah diinput.</p>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Gunakan filter <strong>Tahun</strong>, <strong>Strategi</strong>, dan <strong>Perangkat Daerah</strong> untuk mempersempit data.</li>
                    <li>Klik <em>Export Excel</em> atau <em>Export PDF</em> untuk mengunduh data.</li>
                    <li>Navigasi antar halaman menggunakan pagination di bawah tabel.</li>
                    <li>Operator Daerah hanya melihat data perangkat daerah mereka sendiri.</li>
                </ul>
            </div>
        </div>

    </div>

    {{-- Hierarki Data --}}
    <div class="bg-white rounded-xl shadow-sm border p-4 md:p-6">
        <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-sitemap text-blue-600"></i> Hierarki Data OPPKPKE
        </h3>
        <div class="relative pl-6">
            <div class="absolute left-3 top-0 bottom-0 w-0.5 bg-blue-200"></div>
            @php
                $hierarchy = [
                    ['icon' => 'flag', 'color' => 'text-blue-600 bg-blue-100', 'label' => 'Strategi OPPKPKE', 'desc' => '3 strategi: Pengurangan Beban Pengeluaran, Peningkatan Pendapatan, Penurunan Kantong Kemiskinan'],
                    ['icon' => 'building-columns', 'color' => 'text-purple-600 bg-purple-100', 'label' => 'Perangkat Daerah', 'desc' => 'Dinas, Badan, atau Kecamatan yang bertanggung jawab atas program'],
                    ['icon' => 'list-check', 'color' => 'text-green-600 bg-green-100', 'label' => 'Program', 'desc' => 'Program kerja sesuai kode anggaran daerah'],
                    ['icon' => 'tasks', 'color' => 'text-orange-600 bg-orange-100', 'label' => 'Kegiatan', 'desc' => 'Kegiatan yang berada di bawah program'],
                    ['icon' => 'file-circle-check', 'color' => 'text-red-600 bg-red-100', 'label' => 'Sub Kegiatan', 'desc' => 'Unit terkecil — di sini data laporan anggaran diinput'],
                ];
            @endphp
            @foreach($hierarchy as $item)
            <div class="mb-4 flex items-start gap-3 relative">
                <div class="w-7 h-7 rounded-full {{ $item['color'] }} flex items-center justify-center flex-shrink-0 relative z-10 -ml-3.5">
                    <i class="fas fa-{{ $item['icon'] }} text-xs"></i>
                </div>
                <div>
                    <p class="font-semibold text-gray-800 text-sm">{{ $item['label'] }}</p>
                    <p class="text-xs text-gray-500">{{ $item['desc'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- FAQ --}}
    <div class="bg-white rounded-xl shadow-sm border p-4 md:p-6">
        <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-circle-question text-blue-600"></i> Pertanyaan Umum (FAQ)
        </h3>
        <div class="space-y-4">
            @php
                $faqs = [
                    ['q' => 'Mengapa saya tidak bisa melihat menu Dashboard?',
                     'a' => 'Menu Dashboard hanya tersedia untuk akun dengan role Admin Master. Jika Anda adalah Operator Daerah, halaman pertama yang muncul setelah login adalah halaman Input Laporan.'],
                    ['q' => 'Data laporan saya kosong / tidak muncul, kenapa?',
                     'a' => 'Pastikan Anda memilih tahun yang benar di selector atas. Juga pastikan filter sudah sesuai. Jika menggunakan Explorer, klik tombol "Terapkan Filter" setelah memilih kriteria.'],
                    ['q' => 'Apakah saya bisa mengubah data yang sudah disimpan?',
                     'a' => 'Ya. Klik ikon edit (pensil) pada sub kegiatan yang sudah terisi. Form akan terbuka dalam mode edit dengan data sebelumnya. Ubah nilai yang diperlukan lalu klik Simpan.'],
                    ['q' => 'Total Realisasi dihitung otomatis, saya tidak perlu mengisi manual?',
                     'a' => 'Benar. Total Realisasi dihitung otomatis dari penjumlahan Realisasi Semester 1 dan Semester 2. Sistem menghitung ini saat Anda menyimpan data.'],
                    ['q' => 'Apa perbedaan Sumber Pembiayaan APBD, APBN, DAK, DAU, DBH?',
                     'a' => 'APBD = Anggaran Pendapatan Belanja Daerah (dari pemerintah daerah sendiri). APBN = dari pemerintah pusat. DAK = Dana Alokasi Khusus. DAU = Dana Alokasi Umum. DBH = Dana Bagi Hasil.'],
                    ['q' => 'Bagaimana cara menghubungi admin jika lupa password?',
                     'a' => 'Hubungi Administrator Sistem OPPKPKE di kantor/instansi terkait. Pada halaman login tersedia informasi kontak administrator.'],
                ];
            @endphp
            @foreach($faqs as $faq)
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 font-medium text-gray-800 text-sm flex items-start gap-2">
                    <i class="fas fa-question-circle text-blue-500 mt-0.5"></i>
                    {{ $faq['q'] }}
                </div>
                <div class="px-4 py-3 text-sm text-gray-600">
                    <i class="fas fa-arrow-right text-green-500 mr-1.5 text-xs"></i>
                    {{ $faq['a'] }}
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Kontak --}}
    <div class="bg-blue-50 rounded-xl border border-blue-200 p-4 md:p-6">
        <h3 class="font-bold text-blue-800 mb-2 flex items-center gap-2">
            <i class="fas fa-headset text-blue-600"></i> Butuh Bantuan?
        </h3>
        <p class="text-sm text-blue-700">
            Jika Anda mengalami masalah teknis atau membutuhkan bantuan penggunaan sistem, silakan hubungi
            tim administrator OPPKPKE di kantor Badan Perencanaan Pembangunan Daerah (Bappeda) Kabupaten Kotabaru.
        </p>
    </div>

</div>

@endsection

@push('scripts')
<script>
function toggleSection(id) {
    const el   = document.getElementById(id);
    const icon = document.getElementById('icon-' + id);
    el.classList.toggle('hidden');
    icon.classList.toggle('rotate-180');
}
</script>
@endpush
