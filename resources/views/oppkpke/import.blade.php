@extends('layouts.oppkpke')

@section('title', 'Import Data OPPKPKE')
@section('page-title', 'Import Data OPPKPKE')
@section('page-subtitle', 'Upload file CSV / Excel format matriks 21 kolom')

@section('content')

{{-- ── Success Message ──────────────────────────────────────────────── --}}
@if(session('success'))
<div class="flex items-start gap-3 bg-green-50 border border-green-300 rounded-xl p-4 mb-5 shadow-sm">
    <i class="fas fa-circle-check text-green-500 text-xl mt-0.5 flex-shrink-0"></i>
    <div>
        <p class="font-semibold text-green-800 text-sm">Import Berhasil</p>
        <p class="text-green-700 text-sm mt-0.5">{{ session('success') }}</p>
    </div>
</div>
@endif

{{-- ── Import Diproses di Background (polling status) ──────────────────── --}}
@if(session('import_status_key'))
<div id="importStatusBanner" class="flex items-start gap-3 bg-blue-50 border border-blue-300 rounded-xl p-4 mb-5 shadow-sm">
    <i class="fas fa-circle-notch fa-spin text-blue-500 text-xl mt-0.5 flex-shrink-0" id="importStatusIcon"></i>
    <div>
        <p class="font-semibold text-blue-800 text-sm" id="importStatusTitle">Sedang Diproses</p>
        <p class="text-blue-700 text-sm mt-0.5" id="importStatusMessage">{{ session('info') }}</p>
    </div>
</div>
<script>
(function () {
    var statusKey = @json(session('import_status_key'));
    var statusUrl = @json(route('oppkpke.import.status'));
    var poll = setInterval(function () {
        fetch(statusUrl + '?key=' + encodeURIComponent(statusKey))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.state === 'processing' || data.state === 'unknown') return;

                clearInterval(poll);
                var banner = document.getElementById('importStatusBanner');
                var icon   = document.getElementById('importStatusIcon');
                var title  = document.getElementById('importStatusTitle');
                var msg    = document.getElementById('importStatusMessage');

                if (data.state === 'done') {
                    var rp      = function (n) { return 'Rp ' + Math.round(n || 0).toLocaleString('id-ID'); };
                    var gap     = data.selisih || 0;
                    var perfect = gap < 1 && (data.skipped_ambig || 0) === 0;
                    // Kelas ditulis utuh agar terdeteksi Tailwind saat build.
                    var tone = perfect
                        ? { box: ['bg-green-50', 'border-green-300'], icon: ['fa-circle-check', 'text-green-500'], title: 'text-green-800', msg: 'text-green-700' }
                        : { box: ['bg-amber-50', 'border-amber-300'], icon: ['fa-circle-exclamation', 'text-amber-500'], title: 'text-amber-800', msg: 'text-amber-700' };
                    banner.classList.remove('bg-blue-50', 'border-blue-300');
                    banner.classList.add(tone.box[0], tone.box[1]);
                    icon.classList.remove('fa-circle-notch', 'fa-spin', 'text-blue-500');
                    icon.classList.add(tone.icon[0], tone.icon[1]);
                    title.className = 'font-semibold text-sm ' + tone.title;
                    title.textContent = (data.mode === 'realisasi' ? 'Import Realisasi Selesai' : 'Import Selesai')
                        + (perfect ? ' — Realisasi Tersinkron Penuh ✓' : ' — Realisasi Belum Tersinkron Penuh');
                    msg.className = 'text-sm mt-0.5 ' + tone.msg;
                    var rincian = (data.updated || 0) + ' data diperbarui, ' + (data.imported || 0) + ' data baru ditambahkan'
                        + ((data.created_sk || 0) > 0 ? ', ' + data.created_sk + ' sub kegiatan baru' : '')
                        + ((data.resolved_ambig || 0) > 0 ? ', ' + data.resolved_ambig + ' ambigu dipilih manual' : '')
                        + ((data.merged || 0) > 0 ? ', ' + data.merged + ' baris dijumlahkan ke sub kegiatan yang sama' : '')
                        + ((data.deleted || 0) > 0 ? ', ' + data.deleted + ' data lama dihapus (sinkronisasi penuh)' : '')
                        + ((data.skipped_ambig || 0) > 0 ? ', ' + data.skipped_ambig + ' ambigu dilewati' : '')
                        + ', ' + (data.skipped || 0) + ' dilewati.';
                    var div = function (cls, html) { var d = document.createElement('div'); d.className = cls; d.innerHTML = html; return d; };
                    msg.textContent = '';
                    msg.appendChild(div('', rincian));
                    if (data.realisasi_file !== undefined) {
                        msg.appendChild(div('mt-1 font-semibold', 'Realisasi tersinkron: ' + rp(data.total_realisasi) + ' dari ' + rp(data.realisasi_file) + ' (total file)'
                            + (gap >= 1 ? ' — selisih ' + rp(gap) : ' ✓')));
                    }
                    if (data.mode === 'realisasi') {
                        msg.appendChild(div('mt-0.5 text-xs', '<i class="fas fa-shield-halved mr-1"></i>Mode Realisasi saja: Alokasi Anggaran & data perencanaan (hasil import RAT) tidak diubah.'
                            + ((data.outside_plan || 0) > 0 ? ' ' + data.outside_plan + ' sub kegiatan di luar RAT dicatat dengan alokasi Rp 0 — cek & lengkapi di RAT.' : '')));
                    }
                } else {
                    banner.classList.remove('bg-blue-50', 'border-blue-300');
                    banner.classList.add('bg-red-50', 'border-red-300');
                    icon.classList.remove('fa-circle-notch', 'fa-spin', 'text-blue-500');
                    icon.classList.add('fa-triangle-exclamation', 'text-red-500');
                    title.className = 'font-semibold text-red-800 text-sm';
                    title.textContent = 'Import Gagal';
                    msg.className = 'text-red-700 text-sm mt-0.5';
                    msg.textContent = data.message || 'Terjadi kesalahan saat memproses import.';
                }
            })
            .catch(function () { /* diam — coba lagi di tick berikutnya */ });
    }, 3000);
})();
</script>
@endif

{{-- ── Error Messages ────────────────────────────────────────────────── --}}
@if($errors->any())
<div class="flex items-start gap-3 bg-red-50 border border-red-300 rounded-xl p-4 mb-5 shadow-sm">
    <i class="fas fa-triangle-exclamation text-red-500 text-xl mt-0.5 flex-shrink-0"></i>
    <div>
        <p class="font-semibold text-red-800 text-sm">Terjadi Kesalahan</p>
        @foreach($errors->all() as $e)
            <p class="text-red-700 text-sm mt-0.5">{{ $e }}</p>
        @endforeach
    </div>
</div>
@endif

@if(!isset($matchedRows))
{{-- ══════════════════════════════════════════════════════════════
     STEP 1: UPLOAD FORM
══════════════════════════════════════════════════════════════ --}}

{{-- Panduan Format --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
        <div class="flex items-center gap-2 mb-2">
            <div class="w-7 h-7 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center">1</div>
            <span class="text-sm font-semibold text-blue-800">Format File</span>
        </div>
        <p class="text-xs text-blue-700 leading-relaxed">Upload file <strong>CSV</strong> atau <strong>Excel (.xlsx)</strong> dengan format matriks OPPKPKE resmi <strong>21 kolom</strong>.</p>
    </div>
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
        <div class="flex items-center gap-2 mb-2">
            <div class="w-7 h-7 rounded-full bg-indigo-600 text-white text-xs font-bold flex items-center justify-center">2</div>
            <span class="text-sm font-semibold text-indigo-800">Preview & Verifikasi</span>
        </div>
        <p class="text-xs text-indigo-700 leading-relaxed">Sistem akan mencocokkan data file dengan sub kegiatan di database dan menampilkan preview lengkap sebelum import.</p>
    </div>
    <div class="bg-green-50 border border-green-200 rounded-xl p-4">
        <div class="flex items-center gap-2 mb-2">
            <div class="w-7 h-7 rounded-full bg-green-600 text-white text-xs font-bold flex items-center justify-center">3</div>
            <span class="text-sm font-semibold text-green-800">Konfirmasi Import</span>
        </div>
        <p class="text-xs text-green-700 leading-relaxed">Setelah preview, pilih baris yang ingin diimport lalu klik <strong>Konfirmasi Import</strong> untuk menyimpan data.</p>
    </div>
</div>

{{-- Kolom yang didukung --}}
<div class="bg-white rounded-xl border shadow-sm p-4 mb-5">
    <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
        <i class="fas fa-table-columns text-blue-500"></i>
        Kolom yang Didukung (21 Kolom Format Resmi)
    </h3>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 text-xs">
        @foreach([
            ['1','No'], ['2','Strategi OPPKPKE'], ['3','Perangkat Daerah'], ['4','Kode'],
            ['5','Program'], ['6','Kegiatan'], ['7','Sub Kegiatan ⭐'], ['8','Aktivitas Langsung'],
            ['9','Aktivitas Tidak Langsung'], ['10','Aktivitas Penunjang'], ['11','Alokasi Anggaran ⭐'],
            ['12','Sumber Pembiayaan'], ['13','Sifat Bantuan'], ['14','Lokasi'],
            ['15','Jumlah Sasaran'], ['16','Besaran Manfaat'], ['17','Jenis Bantuan'],
            ['18','Durasi Pemberian'], ['19','Realisasi Sem.1 ⭐'], ['20','Realisasi Sem.2 ⭐'], ['21','Total ⭐'],
        ] as [$num, $name])
        <div class="flex items-center gap-1.5 bg-gray-50 rounded-lg px-2 py-1.5 border border-gray-100">
            <span class="w-5 h-5 bg-blue-600 text-white rounded text-[10px] font-bold flex items-center justify-center flex-shrink-0">{{ $num }}</span>
            <span class="text-gray-700 truncate">{{ $name }}</span>
        </div>
        @endforeach
    </div>
    <p class="text-xs text-gray-400 mt-2">⭐ = Kolom utama yang diimport ke database</p>
</div>

{{-- Upload Form --}}
<div class="bg-white rounded-xl border shadow-sm p-5 md:p-6">
    <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fas fa-file-arrow-up text-blue-600"></i>
        Upload File Data
    </h2>

    <form method="POST" action="{{ route('oppkpke.import.preview') }}" enctype="multipart/form-data" id="upload-form">
        @csrf

        {{-- Drag & Drop Zone --}}
        <div id="drop-zone"
             class="border-2 border-dashed border-blue-300 rounded-xl bg-blue-50 hover:bg-blue-100 transition cursor-pointer p-8 text-center mb-5"
             onclick="document.getElementById('file-input').click()"
             ondragover="event.preventDefault(); this.classList.add('border-blue-500','bg-blue-100')"
             ondragleave="this.classList.remove('border-blue-500','bg-blue-100')"
             ondrop="handleDrop(event)">
            <div id="drop-content">
                <i class="fas fa-cloud-arrow-up text-4xl text-blue-400 mb-3"></i>
                <p class="text-sm font-semibold text-blue-700">Klik atau seret file ke sini</p>
                <p class="text-xs text-blue-500 mt-1">Format: CSV, XLSX, XLS — Maksimal 20MB</p>
            </div>
            <div id="file-info" class="hidden">
                <i class="fas fa-file-excel text-4xl text-green-500 mb-3"></i>
                <p class="text-sm font-semibold text-green-700" id="file-name"></p>
                <p class="text-xs text-green-500 mt-1" id="file-size"></p>
                <p class="text-xs text-gray-400 mt-2">Klik untuk ganti file</p>
            </div>
        </div>
        <input type="file" id="file-input" name="file" class="hidden" accept=".csv,.xlsx,.xls"
               onchange="showFileInfo(this)">

        {{-- Tahun & Semester --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Tahun Data <span class="text-red-500">*</span></label>
                <select name="tahun" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                    @for($y = 2023; $y <= 2030; $y++)
                        <option value="{{ $y }}" {{ (old('tahun', date('Y')) == $y) ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
                <p class="text-xs text-gray-400 mt-1">Tahun yang akan diupdate di database</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Semester <span class="text-red-500">*</span></label>
                <div class="flex gap-3">
                    <label class="flex-1 flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2.5 cursor-pointer hover:bg-gray-50 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                        <input type="radio" name="semester" value="1" {{ old('semester') == '1' ? 'checked' : '' }} class="text-blue-600">
                        <span class="text-sm font-medium text-gray-700">Semester 1</span>
                        <span class="text-xs text-gray-400">(Jan–Jun)</span>
                    </label>
                    <label class="flex-1 flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2.5 cursor-pointer hover:bg-gray-50 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                        <input type="radio" name="semester" value="2" {{ old('semester', '2') == '2' ? 'checked' : '' }} class="text-blue-600">
                        <span class="text-sm font-medium text-gray-700">Semester 2</span>
                        <span class="text-xs text-gray-400">(Jul–Des)</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex items-center gap-3">
            <button type="submit" id="upload-btn"
                    class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold px-6 py-2.5 rounded-lg transition shadow-sm text-sm">
                <i class="fas fa-magnifying-glass"></i>
                Preview & Analisis Data
            </button>
            <p class="text-xs text-gray-400">Sistem akan menganalisis dan mencocokkan data sebelum diimport</p>
        </div>
    </form>
</div>

@else
{{-- ══════════════════════════════════════════════════════════════
     STEP 2: PREVIEW TABLE
══════════════════════════════════════════════════════════════ --}}

{{-- Stats Cards --}}
<div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-4">
    <div class="bg-white rounded-xl border p-3 shadow-sm">
        <p class="text-xs text-gray-500 font-medium">Total Baris</p>
        <p class="text-2xl font-bold text-gray-800">{{ $stats['total'] }}</p>
        <p class="text-xs text-gray-400">dari file</p>
    </div>
    <div class="bg-white rounded-xl border border-green-200 p-3 shadow-sm">
        <p class="text-xs text-green-600 font-medium flex items-center gap-1"><i class="fas fa-circle-check text-xs"></i> Cocok</p>
        <p class="text-2xl font-bold text-green-700">{{ $stats['matched'] - ($stats['new_sk'] ?? 0) }}</p>
        <p class="text-xs text-green-500">akan diimport</p>
    </div>
    @if(($stats['new_sk'] ?? 0) > 0)
    <div class="bg-white rounded-xl border border-purple-200 p-3 shadow-sm">
        <p class="text-xs text-purple-600 font-medium flex items-center gap-1"><i class="fas fa-wand-magic-sparkles text-xs"></i> Buat SK Baru</p>
        <p class="text-2xl font-bold text-purple-700">{{ $stats['new_sk'] }}</p>
        <p class="text-xs text-purple-500">sub kegiatan otomatis</p>
    </div>
    @endif
    <div class="bg-white rounded-xl border border-red-200 p-3 shadow-sm">
        <p class="text-xs text-red-600 font-medium flex items-center gap-1"><i class="fas fa-circle-xmark text-xs"></i> Tidak Cocok</p>
        <p class="text-2xl font-bold text-red-600">{{ $stats['not_found'] }}</p>
        <p class="text-xs text-red-400">akan dilewati</p>
    </div>
    @if(($stats['ambiguous'] ?? 0) > 0)
    <div class="bg-white rounded-xl border border-yellow-300 p-3 shadow-sm">
        <p class="text-xs text-yellow-700 font-medium flex items-center gap-1"><i class="fas fa-circle-question text-xs"></i> Ambigu</p>
        <p class="text-2xl font-bold text-yellow-600">{{ $stats['ambiguous'] }}</p>
        <p class="text-xs text-yellow-600">pilih tujuan di tabel</p>
    </div>
    @endif
    @if(($stats['duplicate'] ?? 0) > 0)
    <div class="bg-white rounded-xl border border-orange-200 p-3 shadow-sm">
        <p class="text-xs text-orange-600 font-medium flex items-center gap-1"><i class="fas fa-copy text-xs"></i> Duplikat</p>
        <p class="text-2xl font-bold text-orange-600">{{ $stats['duplicate'] }}</p>
        <p class="text-xs text-orange-400">nilainya digabung</p>
    </div>
    @endif
    <div class="bg-white rounded-xl border border-amber-200 p-3 shadow-sm">
        <p class="text-xs text-amber-600 font-medium flex items-center gap-1"><i class="fas fa-pen text-xs"></i> Update</p>
        <p class="text-2xl font-bold text-amber-700">{{ $stats['update'] }}</p>
        <p class="text-xs text-amber-500">data sudah ada</p>
    </div>
    <div class="bg-white rounded-xl border border-blue-200 p-3 shadow-sm">
        <p class="text-xs text-blue-600 font-medium flex items-center gap-1"><i class="fas fa-plus text-xs"></i> Baru</p>
        <p class="text-2xl font-bold text-blue-700">{{ $stats['new_record'] }}</p>
        <p class="text-xs text-blue-400">data laporan baru</p>
    </div>
</div>

{{-- Notice for new_sk --}}
@if(($stats['new_sk'] ?? 0) > 0)
<div class="flex items-start gap-3 bg-purple-50 border border-purple-300 rounded-xl p-3 mb-3 text-sm">
    <i class="fas fa-wand-magic-sparkles text-purple-500 text-base mt-0.5 flex-shrink-0"></i>
    <div>
        <p class="font-semibold text-purple-800">{{ $stats['new_sk'] }} sub kegiatan baru akan dibuat secara otomatis</p>
        <p class="text-purple-700 text-xs mt-0.5">
            Baris bertanda <span class="bg-purple-100 text-purple-700 rounded px-1.5 py-0.5 font-semibold">Buat SK</span>
            tidak ditemukan sebagai Sub Kegiatan di database, namun namanya cocok dengan Kegiatan yang ada.
            Sub Kegiatan baru akan dibuat otomatis saat import dikonfirmasi.
        </p>
    </div>
</div>
@endif

{{-- Notice for unmatched --}}
@if($stats['not_found'] > 0)
<div class="flex items-start gap-3 bg-amber-50 border border-amber-300 rounded-xl p-3 mb-3 text-sm">
    <i class="fas fa-triangle-exclamation text-amber-500 text-base mt-0.5 flex-shrink-0"></i>
    <div>
        <p class="font-semibold text-amber-800">{{ $stats['not_found'] }} baris tidak dapat dicocokkan dan akan dilewati</p>
        <p class="text-amber-700 text-xs mt-0.5">Baris bertanda <span class="bg-red-100 text-red-700 rounded px-1">Tidak Cocok</span> tidak ditemukan di database maupun sebagai nama Kegiatan. Nama sub kegiatan di file berbeda dari yang ada di database.</p>
    </div>
</div>
@endif

{{-- Notice for duplicates --}}
@if(($stats['duplicate'] ?? 0) > 0)
<div class="flex items-start gap-3 bg-orange-50 border border-orange-300 rounded-xl p-3 mb-4 text-sm">
    <i class="fas fa-copy text-orange-500 text-base mt-0.5 flex-shrink-0"></i>
    <div>
        <p class="font-semibold text-orange-800">{{ $stats['duplicate'] }} baris duplikat ditemukan dan nilainya digabungkan</p>
        <p class="text-orange-700 text-xs mt-0.5">
            Baris bertanda <span class="bg-orange-100 text-orange-700 rounded px-1.5 py-0.5 font-semibold">Duplikat</span>
            memiliki Sub Kegiatan yang sama dengan baris sebelumnya di file ini. Nilai alokasi, Sem.1, Sem.2, dan Total dari baris duplikat secara otomatis <strong>dijumlahkan</strong> ke kemunculan pertamanya — sehingga total yang diimport tetap akurat dan mencakup semua nilai.
        </p>
    </div>
</div>
@endif

{{-- Notice for ambiguous --}}
@if(($stats['ambiguous'] ?? 0) > 0)
<div class="flex items-start gap-3 bg-yellow-50 border border-yellow-300 rounded-xl p-3 mb-3 text-sm">
    <i class="fas fa-circle-question text-yellow-500 text-base mt-0.5 flex-shrink-0"></i>
    <div>
        <p class="font-semibold text-yellow-800">{{ $stats['ambiguous'] }} baris ambigu — belum diimport sampai dipilih tujuannya</p>
        <p class="text-yellow-700 text-xs mt-0.5">
            <strong>Kenapa ambigu?</strong> Di perangkat daerah yang sama ada lebih dari satu sub kegiatan bernama sama, dan kode, kegiatan,
            maupun data tahun ini tidak cukup untuk membedakannya. Alasan rinci tampil di tiap baris. Pilih sub kegiatan tujuannya di kolom
            <em>Sub Kegiatan (Database)</em> agar realisasinya ikut tersinkron.
        </p>
    </div>
</div>
@endif

{{-- Notice: realisasi di luar data perencanaan tahun ini --}}
@php
    $outsideRows = collect($matchedRows)->whereIn('status', ['matched', 'new_sk'])->where('has_existing', false);
    $outsideReal = $outsideRows->sum('realisasi_total');
@endphp
@if($hasPlanData && $outsideRows->isNotEmpty())
<div class="flex items-start gap-3 bg-sky-50 border border-sky-300 rounded-xl p-3 mb-3 text-sm">
    <i class="fas fa-circle-info text-sky-500 text-base mt-0.5 flex-shrink-0"></i>
    <div>
        <p class="font-semibold text-sky-800">{{ $outsideRows->count() }} baris di luar RAT {{ $tahun }} — realisasi Rp {{ number_format($outsideReal, 0, ',', '.') }}</p>
        <p class="text-sky-700 text-xs mt-0.5">
            Baris bertanda <span class="bg-sky-100 text-sky-700 rounded px-1.5 py-0.5 font-semibold">di luar RAT</span> cocok dengan sub kegiatan di database,
            tetapi sub kegiatan itu belum punya data tahun {{ $tahun }} (tidak tercantum di import RAT). Pada mode <strong>Realisasi saja</strong>,
            realisasinya tetap dicatat agar total lengkap, dengan alokasi Rp 0. Lengkapi item ini di RAT bila memang termasuk rencana.
        </p>
    </div>
</div>
@endif

{{-- Info tahun & semester --}}
<div class="flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-xl p-3 mb-4 text-sm">
    <i class="fas fa-calendar-check text-blue-500 text-base flex-shrink-0"></i>
    <p class="text-blue-800">
        Import untuk <strong>Tahun {{ $tahun }} — Semester {{ $semester }}</strong>.
        <a href="{{ route('oppkpke.import') }}" class="underline text-blue-600 hover:text-blue-800 ml-2">← Ganti file / ubah parameter</a>
    </p>
</div>

{{-- Preview Table --}}
<div class="bg-white rounded-xl border shadow-sm overflow-hidden mb-4">
    <div class="p-3 md:p-4 border-b flex items-center justify-between flex-wrap gap-2">
        <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
            <i class="fas fa-table-list text-blue-500"></i>
            Preview Data — {{ $stats['matched'] }} baris siap diimport dari {{ $stats['total'] }} total
        </h2>
        <div class="flex items-center gap-2 text-xs flex-wrap">
            <span class="flex items-center gap-1 bg-green-100 text-green-700 rounded px-2 py-1">
                <i class="fas fa-circle-check text-xs"></i> Cocok
            </span>
            <span class="flex items-center gap-1 bg-purple-100 text-purple-700 rounded px-2 py-1">
                <i class="fas fa-wand-magic-sparkles text-xs"></i> Buat SK
            </span>
            <span class="flex items-center gap-1 bg-red-100 text-red-700 rounded px-2 py-1">
                <i class="fas fa-circle-xmark text-xs"></i> Tidak Cocok
            </span>
            <span class="flex items-center gap-1 bg-orange-100 text-orange-700 rounded px-2 py-1">
                <i class="fas fa-copy text-xs"></i> Duplikat
            </span>
            <span class="flex items-center gap-1 bg-amber-100 text-amber-700 rounded px-2 py-1">
                <i class="fas fa-pen text-xs"></i> Update
            </span>
            <span class="flex items-center gap-1 bg-blue-100 text-blue-700 rounded px-2 py-1">
                <i class="fas fa-plus text-xs"></i> Baru
            </span>
        </div>
    </div>

    <div class="overflow-x-auto" style="max-height: 60vh; overflow-y: auto;">
        <table class="w-full text-xs border-collapse" style="min-width: 1800px;">
            <thead class="sticky top-0 z-10">
            <tr style="background:#1e3a5f; color:#fff;">
                <th class="border border-slate-600 px-2 py-2 text-center w-8">
                    <input type="checkbox" id="check-all" class="rounded" checked onchange="toggleAll(this)" title="Pilih semua">
                </th>
                <th class="border border-slate-600 px-2 py-2 text-center w-16">No</th>
                <th class="border border-slate-600 px-2 py-2 text-center w-20">Status</th>
                <th class="border border-slate-600 px-2 py-2 w-32">Strategi</th>
                <th class="border border-slate-600 px-2 py-2 w-36">Perangkat Daerah</th>
                <th class="border border-slate-600 px-2 py-2 w-36">Kode</th>
                <th class="border border-slate-600 px-2 py-2 w-48">Sub Kegiatan (File)</th>
                <th class="border border-slate-600 px-2 py-2 w-48">Sub Kegiatan (Database)</th>
                <th class="border border-slate-600 px-2 py-2 w-32">Alokasi (Rp)</th>
                <th class="border border-slate-600 px-2 py-2 w-28">Realisasi Sem.1</th>
                <th class="border border-slate-600 px-2 py-2 w-28">Realisasi Sem.2</th>
                <th class="border border-slate-600 px-2 py-2 w-28">Total</th>
                <th class="border border-slate-600 px-2 py-2 w-20">Sumber</th>
                <th class="border border-slate-600 px-2 py-2 w-24">Sifat Bantuan</th>
                <th class="border border-slate-600 px-2 py-2 w-28">Lokasi</th>
                <th class="border border-slate-600 px-2 py-2 w-20">Jml Sasaran</th>
                <th class="border border-slate-600 px-2 py-2 w-28">Besaran Manfaat</th>
                <th class="border border-slate-600 px-2 py-2 w-24">Jenis Bantuan</th>
                <th class="border border-slate-600 px-2 py-2 w-20">Durasi</th>
            </tr>
            </thead>
            <tbody>
            @php $no = 0; @endphp
            @foreach($matchedRows as $row)
            @php
                $status      = $row['status'];
                $isMatched   = $status === 'matched';
                $isNewSk     = $status === 'new_sk';
                $isDuplicate = $status === 'duplicate';
                $isAmbiguous = $status === 'ambiguous';
                $canImport   = $isMatched || $isNewSk;
                $hasExisting = $row['has_existing'] ?? false;
                $rowBg = match($status) {
                    'matched'   => $hasExisting ? '#FFFBEB' : '#F0FDF4',
                    'new_sk'    => '#FAF5FF',
                    'duplicate' => '#FFF7ED',
                    'ambiguous' => '#FEFCE8',
                    default     => '#FEF2F2',
                };
                $no++;
                $alokasi = (float)$row['alokasi_anggaran'];
                $sem1    = (float)$row['realisasi_sem1'];
                $sem2    = (float)$row['realisasi_sem2'];
                $total   = (float)$row['realisasi_total'];
            @endphp
            <tr style="background:{{ $rowBg }};" class="imp-row hover:opacity-90 transition border-b border-gray-100"
                data-status="{{ $status }}" data-realisasi="{{ $isDuplicate ? 0 : max($total, $sem1 + $sem2) }}">
                <td class="border border-gray-200 px-2 py-1.5 text-center">
                    @if($canImport)
                    <input type="checkbox" name="import_ids[]" value="{{ $row['row_num'] }}"
                           class="rounded row-check" checked
                           form="confirm-form">
                    @else
                    <span class="text-gray-300 text-lg leading-none">—</span>
                    @endif
                </td>
                <td class="border border-gray-200 px-2 py-1.5 text-center text-gray-500 font-medium">{{ $no }}</td>
                <td class="border border-gray-200 px-2 py-1.5 text-center">
                    @if($isNewSk)
                        <span class="inline-flex items-center gap-1 bg-purple-100 text-purple-700 rounded-full px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap">
                            <i class="fas fa-wand-magic-sparkles text-[9px]"></i> Buat SK
                        </span>
                    @elseif($isMatched && $hasExisting)
                        <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 rounded-full px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap">
                            <i class="fas fa-pen text-[9px]"></i> Update
                        </span>
                    @elseif($isMatched)
                        <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 rounded-full px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap">
                            <i class="fas fa-plus text-[9px]"></i> Baru
                        </span>
                    @elseif($isDuplicate)
                        <span class="inline-flex items-center gap-1 bg-orange-100 text-orange-700 rounded-full px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap">
                            <i class="fas fa-copy text-[9px]"></i> Duplikat
                        </span>
                    @elseif($isAmbiguous)
                        <span class="ambig-badge inline-flex items-center gap-1 bg-yellow-100 text-yellow-800 rounded-full px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap">
                            <i class="fas fa-circle-question text-[9px]"></i> <span class="ambig-badge-text">Ambigu</span>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 bg-red-100 text-red-700 rounded-full px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap">
                            <i class="fas fa-xmark text-[9px]"></i> Tidak Cocok
                        </span>
                    @endif
                </td>
                <td class="border border-gray-200 px-2 py-1.5 leading-snug text-gray-700">
                    <span class="text-[10px]">{{ Str::limit($row['matched_strategi'] ?? $row['strategi'], 30) }}</span>
                </td>
                <td class="border border-gray-200 px-2 py-1.5 leading-snug">
                    <p class="text-gray-800 font-medium text-[11px]">{{ $row['matched_pd_nama'] ?? $row['perangkat_daerah'] ?: '—' }}</p>
                </td>
                <td class="border border-gray-200 px-2 py-1.5 font-mono text-[10px] text-gray-500">{{ $row['kode'] ?: '—' }}</td>
                <td class="border border-gray-200 px-2 py-1.5 leading-snug">
                    <p class="text-gray-800 text-[11px]">{{ $row['sub_kegiatan'] }}</p>
                </td>
                <td class="border border-gray-200 px-2 py-1.5 leading-snug">
                    @if($isMatched)
                        <p class="text-green-700 text-[11px] font-medium">
                            <i class="fas fa-link text-green-400 text-[9px] mr-0.5"></i>
                            {{ $row['matched_sk_nama'] }}
                        </p>
                        <p class="text-[10px] text-gray-400 mt-0.5">ID: {{ $row['sub_kegiatan_id'] }}
                            @if($hasPlanData && ! $hasExisting)
                                <span class="ml-1 bg-sky-100 text-sky-700 rounded px-1 py-0.5 font-semibold" title="Sub kegiatan ini belum punya data tahun {{ $tahun }} (tidak ada di import RAT).">di luar RAT</span>
                            @endif
                        </p>
                    @elseif($isAmbiguous)
                        <div class="min-w-[280px] max-w-[380px]">
                            <p class="text-yellow-800 font-semibold text-[11px]"><i class="fas fa-circle-question text-[9px] mr-0.5"></i> Kenapa ambigu?</p>
                            <ul class="list-disc pl-4 mt-0.5 space-y-0.5 text-[10px] text-yellow-700">
                                @foreach($row['ambig_reasons'] ?? [] as $why)<li>{{ $why }}</li>@endforeach
                            </ul>
                            <label class="block text-[10px] font-semibold text-gray-600 mt-1.5 mb-0.5">Import ke sub kegiatan:</label>
                            <select class="ambig-select w-full border border-yellow-400 rounded px-1.5 py-1 text-[11px] text-gray-800 bg-white focus:ring-2 focus:ring-yellow-400"
                                    data-row="{{ $row['row_num'] }}" onchange="impAmbigChange()">
                                <option value="">— Lewati (tidak diimport) —</option>
                                @foreach($row['ambig_candidates'] ?? [] as $cand)
                                    <option value="{{ $cand['id'] }}">
                                        {{ ($row['ambig_kind'] ?? '') === 'fuzzy' ? $cand['nama'] . ' — ' : '' }}Kegiatan: {{ $cand['kegiatan'] ?: '—' }}{{ $cand['kode'] ? ' · kode ' . $cand['kode'] : '' }}{{ $cand['alokasi_db'] !== null ? ' · data ' . $tahun . ': alokasi Rp ' . number_format($cand['alokasi_db'], 0, ',', '.') : ' · belum ada data ' . $tahun }}{{ $cand['claimed_by'] ? ' · dipakai baris #' . $cand['claimed_by'] : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($isNewSk)
                        <p class="text-purple-700 text-[11px] font-medium">
                            <i class="fas fa-wand-magic-sparkles text-purple-400 text-[9px] mr-0.5"></i>
                            Akan dibuat: <em>{{ $row['sub_kegiatan'] }}</em>
                        </p>
                        <p class="text-[10px] text-purple-400 mt-0.5">kegiatan_id: {{ $row['kegiatan_id'] }}</p>
                    @elseif($isDuplicate)
                        <p class="text-orange-600 text-[11px] font-medium">
                            <i class="fas fa-copy text-orange-400 text-[9px] mr-0.5"></i>
                            {{ $row['matched_sk_nama'] }}
                        </p>
                        <p class="text-[10px] text-orange-400 mt-0.5">ID: {{ $row['sub_kegiatan_id'] }} — nilainya digabung ke baris pertama</p>
                    @else
                        <span class="text-red-400 text-[11px] italic">Tidak ditemukan di database</span>
                    @endif
                </td>
                <td class="border border-gray-200 px-2 py-1.5 text-right font-mono text-blue-800 whitespace-nowrap text-[11px]">
                    {{ $alokasi > 0 ? number_format($alokasi, 0, ',', '.') : '-' }}
                </td>
                <td class="border border-gray-200 px-2 py-1.5 text-right font-mono text-indigo-600 whitespace-nowrap text-[11px]">
                    {{ $sem1 > 0 ? number_format($sem1, 0, ',', '.') : '-' }}
                </td>
                <td class="border border-gray-200 px-2 py-1.5 text-right font-mono text-purple-600 whitespace-nowrap text-[11px]">
                    {{ $sem2 > 0 ? number_format($sem2, 0, ',', '.') : '-' }}
                </td>
                <td class="border border-gray-200 px-2 py-1.5 text-right font-mono whitespace-nowrap text-[11px]">
                    @php $pct = $alokasi > 0 ? round(($total/$alokasi)*100,1) : 0; @endphp
                    @if($total > 0)
                        <span class="{{ $pct >= 80 ? 'text-green-700' : ($pct >= 50 ? 'text-amber-700' : 'text-red-600') }} font-semibold">
                            {{ number_format($total, 0, ',', '.') }}
                        </span>
                        <br><span class="text-[10px] text-gray-400">{{ $pct }}%</span>
                    @else
                        <span class="text-gray-300">-</span>
                    @endif
                </td>
                <td class="border border-gray-200 px-2 py-1.5 text-center text-gray-600 text-[11px]">{{ $row['sumber_pembiayaan'] ?: 'APBD' }}</td>
                <td class="border border-gray-200 px-2 py-1.5 text-gray-600 text-[11px]">{{ $row['sifat_bantuan'] ?: '—' }}</td>
                <td class="border border-gray-200 px-2 py-1.5 text-gray-600 text-[11px] leading-snug">{{ Str::limit($row['lokasi'], 30) ?: '—' }}</td>
                <td class="border border-gray-200 px-2 py-1.5 text-center text-gray-600 text-[11px]">{{ $row['jumlah_sasaran'] ?: '—' }}</td>
                <td class="border border-gray-200 px-2 py-1.5 text-gray-600 text-[11px] leading-snug">{{ Str::limit($row['besaran_manfaat'], 25) ?: '—' }}</td>
                <td class="border border-gray-200 px-2 py-1.5 text-center text-gray-600 text-[11px]">{{ $row['jenis_bantuan'] ?: '—' }}</td>
                <td class="border border-gray-200 px-2 py-1.5 text-center text-gray-600 text-[11px]">{{ $row['durasi_pemberian'] ?: '—' }}</td>
            </tr>
            @endforeach
            </tbody>
            {{-- Footer totals --}}
            @php
                $dataRows = collect($matchedRows)->whereIn('status', ['matched', 'new_sk']);
                $fAlokasi = $dataRows->sum('alokasi_anggaran');
                $fSem1    = $dataRows->sum('realisasi_sem1');
                $fSem2    = $dataRows->sum('realisasi_sem2');
                $fTotal   = $dataRows->sum('realisasi_total');
            @endphp
            <tfoot>
            <tr style="background:#1e3a5f; color:#fff; font-weight:bold; font-size:11px;">
                <td colspan="8" class="border border-slate-600 px-3 py-2 text-right">TOTAL AKAN DIIMPORT ({{ $stats['matched'] }} baris)</td>
                <td class="border border-slate-600 px-2 py-2 text-right font-mono text-yellow-300 whitespace-nowrap">{{ number_format($fAlokasi, 0, ',', '.') }}</td>
                <td class="border border-slate-600 px-2 py-2 text-right font-mono text-cyan-300 whitespace-nowrap">{{ number_format($fSem1, 0, ',', '.') }}</td>
                <td class="border border-slate-600 px-2 py-2 text-right font-mono text-cyan-300 whitespace-nowrap">{{ number_format($fSem2, 0, ',', '.') }}</td>
                <td class="border border-slate-600 px-2 py-2 text-right font-mono text-green-300 whitespace-nowrap">{{ number_format($fTotal, 0, ',', '.') }}</td>
                <td colspan="7" class="border border-slate-600 px-2 py-2"></td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Confirm Form --}}
<form id="confirm-form" method="POST" action="{{ route('oppkpke.import.execute') }}"
      onsubmit="return confirmImport(this)">
    @csrf
    <input type="hidden" name="cache_key" value="{{ $cacheKey }}">
    {{-- import_ids[] checkboxes are rendered inside the table above --}}

    @php
        $existingCount = \App\Models\LaporanOppkpke::where('tahun', $tahun)->count();
        $importSkIds   = collect($matchedRows)->whereIn('status', ['matched', 'new_sk'])->pluck('sub_kegiatan_id')->filter()->unique()->count();
        $oldCount      = max(0, $existingCount - $importSkIds);
    @endphp

    {{-- Ringkasan realisasi: berapa dari total file yang akan masuk (dihitung ulang tiap centang/pilihan) --}}
    <div class="bg-white rounded-xl border shadow-sm p-4 mb-4">
        <div class="flex flex-wrap items-end gap-x-8 gap-y-2">
            <div>
                <p class="text-xs text-gray-500">Total Realisasi File</p>
                <p class="font-bold text-gray-800 font-mono">Rp {{ number_format($stats['realisasi_file'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Akan Tersinkron</p>
                <p class="font-bold text-green-700 font-mono" id="imp-sum-selected">—</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Selisih (tidak ikut)</p>
                <p class="font-bold font-mono" id="imp-sum-gap">—</p>
            </div>
            <div class="flex-1 min-w-[220px]">
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden"><div id="imp-sum-bar" class="h-full bg-green-600 transition-all" style="width:0%"></div></div>
                <p class="text-[11px] text-gray-500 mt-1" id="imp-sum-note"></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border shadow-sm p-4 flex flex-wrap items-center justify-between gap-4">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-gray-800">Siap mengimport data?</p>
            <p class="text-xs text-gray-500 mt-0.5 mb-3">
                Hanya baris yang <strong>dicentang</strong> (dan baris ambigu yang sudah dipilih tujuannya) yang akan diimport ke tahun <strong>{{ $tahun }}</strong>.
            </p>

            {{-- Mode import --}}
            @php $defaultMode = $hasPlanData ? 'realisasi' : 'lengkap'; @endphp
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-3">
                <label class="flex items-start gap-2 border rounded-lg p-2.5 cursor-pointer has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                    <input type="radio" name="mode" value="realisasi" class="mt-0.5" onchange="onModeChange()" {{ $defaultMode === 'realisasi' ? 'checked' : '' }}>
                    <span>
                        <span class="block text-sm font-semibold text-gray-800">Realisasi saja {{ $hasPlanData ? '(disarankan)' : '' }}</span>
                        <span class="block text-xs text-gray-500">Hanya Realisasi Sem.1, Sem.2 & Total yang diisi. Alokasi Anggaran & data perencanaan dari import RAT <strong>tidak diubah</strong> (kolom Alokasi di tabel diabaikan).</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 border rounded-lg p-2.5 cursor-pointer has-[:checked]:border-red-400 has-[:checked]:bg-red-50">
                    <input type="radio" name="mode" value="lengkap" class="mt-0.5" onchange="onModeChange()" {{ $defaultMode === 'lengkap' ? 'checked' : '' }}>
                    <span>
                        <span class="block text-sm font-semibold text-gray-800">Lengkap — timpa dari file</span>
                        <span class="block text-xs text-gray-500">Alokasi, data perencanaan, dan realisasi <strong>semuanya diambil dari file ini</strong> dan menimpa data tahun {{ $tahun }} (termasuk hasil import RAT).</span>
                    </span>
                </label>
            </div>

            {{-- Replace mode toggle (hanya untuk mode Lengkap) --}}
            <label class="flex items-start gap-2.5 cursor-pointer group" id="replace-label">
                <div class="relative mt-0.5 flex-shrink-0">
                    <input type="checkbox" name="replace_year" value="1" id="replace-year-check"
                           class="sr-only peer" onchange="onReplaceToggle(this)">
                    <div class="w-9 h-5 bg-gray-200 peer-checked:bg-red-500 rounded-full transition-colors"></div>
                    <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-700 group-has-[:checked]:text-red-700">
                        Sinkronisasi Penuh — hapus data lama yang tidak ada di file ini
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5 group-has-[:checked]:text-red-600">
                        @if($oldCount > 0)
                            Saat ini ada <strong>{{ $existingCount }}</strong> record untuk tahun {{ $tahun }} di database.
                            Setelah import, <strong class="text-red-600">{{ $oldCount }} record lama</strong> yang tidak ada di file ini akan dihapus,
                            sehingga total DB = persis {{ $stats['matched'] }} record dari file.
                        @else
                            Semua record tahun {{ $tahun }} di database sudah tercakup dalam file ini.
                        @endif
                        <br><em>Matikan opsi ini jika hanya ingin update sebagian data.</em>
                    </p>
                </div>
            </label>
        </div>
        <div class="flex gap-3 flex-wrap items-center">
            <a href="{{ route('oppkpke.import') }}"
               class="flex items-center gap-2 border border-gray-300 text-gray-600 text-sm px-4 py-2 rounded-lg hover:bg-gray-50 transition">
                <i class="fas fa-arrow-left text-xs"></i> Upload Ulang
            </a>
            <button type="submit" id="confirm-btn"
                    class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded-lg transition shadow-sm text-sm">
                <i class="fas fa-file-import"></i>
                Konfirmasi Import
                <span class="bg-green-800 rounded px-1.5 py-0.5 text-xs" id="import-count">{{ $stats['matched'] }}</span>
                baris
            </button>
        </div>
    </div>
</form>

@endif

@endsection

@push('scripts')
<script>
// ── File Upload Handling ────────────────────────────────────────
function showFileInfo(input) {
    if (!input.files.length) return;
    const file = input.files[0];
    const mb   = (file.size / 1024 / 1024).toFixed(2);

    document.getElementById('drop-content').classList.add('hidden');
    document.getElementById('file-info').classList.remove('hidden');
    document.getElementById('file-name').textContent = file.name;
    document.getElementById('file-size').textContent = mb + ' MB';
}

function handleDrop(e) {
    e.preventDefault();
    document.getElementById('drop-zone').classList.remove('border-blue-500', 'bg-blue-100');

    const file = e.dataTransfer.files[0];
    if (!file) return;

    const input = document.getElementById('file-input');
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    showFileInfo(input);
}

// ── Checkbox "Select All" ───────────────────────────────────────
function toggleAll(master) {
    document.querySelectorAll('.row-check').forEach(cb => {
        cb.checked = master.checked;
    });
    updateImportCount();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.row-check').forEach(cb => {
        cb.addEventListener('change', () => {
            updateImportCount();
            const all   = document.querySelectorAll('.row-check').length;
            const chkd  = document.querySelectorAll('.row-check:checked').length;
            const master = document.getElementById('check-all');
            if (master) master.indeterminate = (chkd > 0 && chkd < all);
            if (master) master.checked = chkd === all;
        });
    });
});

function impResolved() {
    return Array.prototype.filter.call(document.querySelectorAll('.ambig-select'), function (s) { return s.value !== ''; });
}

function updateImportCount() {
    const count = document.querySelectorAll('.row-check:checked').length + impResolved().length;
    const badge = document.getElementById('import-count');
    if (badge) badge.textContent = count;
    impRecalc();
}

// ── Ringkasan realisasi: berapa dari total file yang akan masuk ─
function impRupiah(n) { return 'Rp ' + Math.round(n || 0).toLocaleString('id-ID'); }
function impRecalc() {
    var total = 0, selected = 0, ambigOpen = 0, otherOut = 0;
    document.querySelectorAll('tr.imp-row').forEach(function (tr) {
        var st = tr.getAttribute('data-status');
        if (st === 'duplicate') return;                       // sudah digabung ke baris pertamanya
        var a  = parseFloat(tr.getAttribute('data-realisasi') || '0') || 0;
        var cb = tr.querySelector('.row-check');
        var rs = tr.querySelector('.ambig-select');
        total += a;
        if ((cb && cb.checked) || (rs && rs.value !== '')) selected += a;
        else if (a > 0) { if (rs) ambigOpen++; else otherOut++; }
    });
    var el = function (id) { return document.getElementById(id); };
    if (!el('imp-sum-selected')) return;
    var gap = Math.max(0, total - selected);
    var pct = total > 0 ? Math.min(100, selected / total * 100) : 100;
    el('imp-sum-selected').textContent = impRupiah(selected);
    el('imp-sum-gap').textContent = impRupiah(gap);
    el('imp-sum-gap').className = 'font-bold font-mono ' + (gap >= 1 ? 'text-amber-600' : 'text-green-700');
    el('imp-sum-bar').style.width = pct.toFixed(2) + '%';
    el('imp-sum-bar').className = 'h-full transition-all ' + (gap >= 1 ? 'bg-amber-500' : 'bg-green-600');
    el('imp-sum-note').innerHTML = gap < 1
        ? '<i class="fas fa-circle-check text-green-600 mr-1"></i>Seluruh realisasi di file akan tersinkron (100%).'
        : pct.toFixed(2).replace('.', ',') + '% tersinkron — belum ikut: '
            + [ambigOpen ? ambigOpen + ' baris ambigu belum dipilih' : '',
               otherOut ? otherOut + ' baris tidak dicentang / tidak cocok' : ''].filter(Boolean).join(', ') + '.';
}

function impAmbigChange() {
    document.querySelectorAll('.ambig-select').forEach(function (s) {
        var tr = s.closest('tr'), chosen = s.value !== '';
        var badge = tr.querySelector('.ambig-badge'), text = tr.querySelector('.ambig-badge-text');
        if (badge) {
            badge.classList.toggle('bg-yellow-100', !chosen);
            badge.classList.toggle('text-yellow-800', !chosen);
            badge.classList.toggle('bg-green-100', chosen);
            badge.classList.toggle('text-green-700', chosen);
        }
        if (text) text.textContent = chosen ? 'Dipilih ✓' : 'Ambigu';
    });
    updateImportCount();
}

// ── Mode import: Sinkronisasi Penuh hanya untuk mode Lengkap ────
function impMode() {
    var r = document.querySelector('input[name="mode"]:checked');
    return r ? r.value : 'lengkap';
}
function onModeChange() {
    var lengkap = impMode() === 'lengkap';
    var cb = document.getElementById('replace-year-check');
    var label = document.getElementById('replace-label');
    if (cb && !lengkap) { cb.checked = false; onReplaceToggle(cb); }
    if (cb) cb.disabled = !lengkap;
    if (label) label.classList.toggle('opacity-40', !lengkap);
}
document.addEventListener('DOMContentLoaded', function () { onModeChange(); impRecalc(); });

// ── Replace-year toggle ─────────────────────────────────────────
function onReplaceToggle(cb) {
    const btn = document.getElementById('confirm-btn');
    if (cb.checked) {
        btn.classList.remove('bg-green-600', 'hover:bg-green-700');
        btn.classList.add('bg-red-600', 'hover:bg-red-700');
    } else {
        btn.classList.remove('bg-red-600', 'hover:bg-red-700');
        btn.classList.add('bg-green-600', 'hover:bg-green-700');
    }
}

// ── Confirm before import ───────────────────────────────────────
function confirmImport(form) {
    const resolved = impResolved();
    const count    = document.querySelectorAll('.row-check:checked').length + resolved.length;
    const replace  = document.getElementById('replace-year-check')?.checked;
    if (count === 0) {
        alert('Tidak ada baris yang dipilih untuk diimport. Centang minimal satu baris.');
        return false;
    }

    // Pilihan tujuan baris ambigu (divalidasi ulang di server terhadap kandidatnya).
    form.querySelectorAll('input[name^="resolve["]').forEach(el => el.remove());
    resolved.forEach(s => {
        const h = document.createElement('input');
        h.type = 'hidden'; h.name = 'resolve[' + s.dataset.row + ']'; h.value = s.value;
        form.appendChild(h);
    });

    const gapEl  = document.getElementById('imp-sum-gap');
    const gapMsg = gapEl && gapEl.classList.contains('text-amber-600')
        ? `\n\n⚠️ Realisasi belum tersinkron penuh — selisih ${gapEl.textContent} tidak ikut diimport.` : '';

    if (impMode() === 'realisasi') {
        return confirm(`Anda akan mengimport REALISASI ${count} baris.\n\nAlokasi Anggaran & data perencanaan (hasil import RAT) TIDAK diubah.` + gapMsg + `\n\nLanjutkan?`);
    }
    if (replace) {
        return confirm(`⚠️ SINKRONISASI PENUH\n\nAnda akan mengimport ${count} baris data DAN menghapus semua record lama tahun ini yang tidak ada di file.\n\nTotal data di database akan sama persis dengan file Excel ini.` + gapMsg + `\n\nLanjutkan?`);
    }
    return confirm(`Anda akan mengimport ${count} baris data.\n\nAlokasi, data perencanaan & realisasi tahun yang sama akan DITIMPA dari file ini.` + gapMsg + `\n\nLanjutkan?`);
}

// ── Upload form loading state ───────────────────────────────────
const uploadForm = document.getElementById('upload-form');
if (uploadForm) {
    uploadForm.addEventListener('submit', () => {
        const btn = document.getElementById('upload-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menganalisis...';
    });
}
</script>
@endpush
