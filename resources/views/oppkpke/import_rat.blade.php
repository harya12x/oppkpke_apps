@extends('layouts.oppkpke')

@section('title', 'Import Matriks RAT')
@section('page-title', 'Import Matriks RAT')
@section('page-subtitle', 'Upload file CSV / Excel format Matriks RAT 18 kolom')

@section('content')

{{-- ── Success / Error ─────────────────────────────────────────────── --}}
@if(session('success'))
<div class="flex items-start gap-3 bg-green-50 border border-green-300 rounded-xl p-4 mb-5 shadow-sm">
    <i class="fas fa-circle-check text-green-500 text-xl mt-0.5 flex-shrink-0"></i>
    <div>
        <p class="font-semibold text-green-800 text-sm">Import Berhasil</p>
        <p class="text-green-700 text-sm mt-0.5">{{ session('success') }}</p>
    </div>
</div>
@endif

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
{{-- ══════════════════════════════════════════════════════════
     STEP 1 — UPLOAD
══════════════════════════════════════════════════════════ --}}

{{-- Format badge --}}
<div class="flex items-center gap-3 mb-5">
    <span class="inline-flex items-center gap-2 bg-green-700 text-white text-sm font-semibold px-4 py-2 rounded-full shadow">
        <i class="fas fa-table-columns"></i> Matriks RAT — 18 Kolom
    </span>
    <a href="{{ route('oppkpke.import') }}" class="text-sm text-blue-600 hover:underline flex items-center gap-1">
        <i class="fas fa-arrow-right-arrow-left text-xs"></i>
        Beralih ke Import OPPKPKE (21 Kol)
    </a>
</div>

{{-- Step cards --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
    <div class="bg-green-50 border border-green-200 rounded-xl p-4">
        <div class="flex items-center gap-2 mb-2">
            <div class="w-7 h-7 rounded-full bg-green-700 text-white text-xs font-bold flex items-center justify-center">1</div>
            <span class="text-sm font-semibold text-green-800">Format File RAT</span>
        </div>
        <p class="text-xs text-green-700 leading-relaxed">Upload file <strong>CSV</strong> atau <strong>Excel (.xlsx)</strong> dengan format Matriks RAT resmi <strong>18 kolom</strong> — tanpa kolom Realisasi.</p>
    </div>
    <div class="bg-teal-50 border border-teal-200 rounded-xl p-4">
        <div class="flex items-center gap-2 mb-2">
            <div class="w-7 h-7 rounded-full bg-teal-700 text-white text-xs font-bold flex items-center justify-center">2</div>
            <span class="text-sm font-semibold text-teal-800">Preview & Verifikasi</span>
        </div>
        <p class="text-xs text-teal-700 leading-relaxed">Sistem mencocokkan nama Sub Kegiatan dengan database dan menampilkan preview baris per baris sebelum import.</p>
    </div>
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
        <div class="flex items-center gap-2 mb-2">
            <div class="w-7 h-7 rounded-full bg-emerald-700 text-white text-xs font-bold flex items-center justify-center">3</div>
            <span class="text-sm font-semibold text-emerald-800">Konfirmasi Import</span>
        </div>
        <p class="text-xs text-emerald-700 leading-relaxed">Data Alokasi Anggaran dan rincian aktivitas akan diisi ke tabel laporan. Data Realisasi tidak diubah.</p>
    </div>
</div>

{{-- 18-column reference --}}
<div class="bg-white rounded-xl border shadow-sm p-4 mb-5">
    <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
        <i class="fas fa-table-columns text-green-600"></i>
        Kolom Matriks RAT (18 Kolom)
    </h3>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2 text-xs">
        @foreach([
            ['1','No'], ['2','Strategi OPPKPKE'], ['3','Perangkat Daerah'], ['4','Kode'], ['5','Program'],
            ['6','Kegiatan'], ['7','SUB KEGIATAN ⭐'], ['8','Aktivitas Langsung'],
            ['9','Aktivitas Tidak Langsung'], ['10','Aktivitas Penunjang'],
            ['11','Alokasi Anggaran ⭐'], ['12','Sumber Pembiayaan'], ['13','Sifat Bantuan'],
            ['14','Lokasi'], ['15','Jumlah Sasaran'], ['16','Besaran Manfaat'],
            ['17','Jenis Bantuan'], ['18','Durasi Pemberian Bantuan'],
        ] as [$num, $name])
        <div class="flex items-center gap-1.5 bg-gray-50 rounded-lg px-2 py-1.5 border border-gray-100">
            <span class="w-5 h-5 bg-green-700 text-white rounded text-[10px] font-bold flex items-center justify-center flex-shrink-0">{{ $num }}</span>
            <span class="text-gray-700 truncate">{{ $name }}</span>
        </div>
        @endforeach
    </div>
    <p class="text-xs text-gray-400 mt-2">
        <i class="fas fa-info-circle text-green-500 mr-1"></i>
        Kolom ⭐ wajib ada. Format ini tidak memiliki kolom Realisasi (Sem.1, Sem.2, Total).
    </p>
</div>

{{-- Upload form --}}
<div class="bg-white rounded-xl border shadow-sm p-5">
    <form id="upload-form" method="POST" action="{{ route('oppkpke.import.rat.preview') }}" enctype="multipart/form-data">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            {{-- Drop zone --}}
            <div class="md:col-span-2">
                <label class="text-sm font-semibold text-gray-700 block mb-2">File Matriks RAT</label>
                <div id="drop-zone"
                     class="border-2 border-dashed border-green-300 rounded-xl p-6 text-center bg-green-50/40 hover:bg-green-50 transition cursor-pointer"
                     ondragover="event.preventDefault(); this.classList.add('border-green-600','bg-green-100');"
                     ondragleave="this.classList.remove('border-green-600','bg-green-100');"
                     ondrop="handleDrop(event)"
                     onclick="document.getElementById('file-input').click()">
                    <div id="drop-content">
                        <i class="fas fa-file-arrow-up text-4xl text-green-400 mb-3 block"></i>
                        <p class="text-sm font-medium text-gray-700">Drag & drop file di sini atau klik untuk memilih</p>
                        <p class="text-xs text-gray-400 mt-1">CSV, XLSX, XLS — Maks. 20MB</p>
                    </div>
                    <div id="file-info" class="hidden">
                        <i class="fas fa-file-excel text-4xl text-green-600 mb-2 block"></i>
                        <p class="text-sm font-semibold text-gray-800" id="file-name">—</p>
                        <p class="text-xs text-gray-500 mt-0.5" id="file-size">—</p>
                        <p class="text-xs text-green-600 mt-1"><i class="fas fa-check-circle"></i> File siap diupload</p>
                    </div>
                </div>
                <input type="file" id="file-input" name="file" class="hidden"
                       accept=".csv,.xlsx,.xls"
                       onchange="showFileInfo(this)">
                @error('file')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Options --}}
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-semibold text-gray-700 block mb-1">Tahun Anggaran</label>
                    <select name="tahun" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500">
                        @for($y = date('Y') + 1; $y >= 2023; $y--)
                            <option value="{{ $y }}" {{ old('tahun', date('Y')) == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Data akan diimport untuk tahun ini.</p>
                </div>

                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                    <p class="text-xs font-semibold text-amber-800 flex items-center gap-1 mb-1">
                        <i class="fas fa-triangle-exclamation"></i> Perhatian
                    </p>
                    <p class="text-xs text-amber-700 leading-relaxed">
                        Import RAT hanya mengisi <strong>Alokasi Anggaran</strong> dan data perencanaan.
                        Data Realisasi yang sudah ada <strong>tidak akan diubah</strong>.
                    </p>
                </div>

                <button id="upload-btn" type="submit"
                        class="w-full bg-green-700 hover:bg-green-800 text-white text-sm font-semibold py-2.5 px-4 rounded-lg transition flex items-center justify-center gap-2 shadow">
                    <i class="fas fa-magnifying-glass"></i>
                    Analisis & Preview
                </button>
            </div>
        </div>
    </form>
</div>

@else
{{-- ══════════════════════════════════════════════════════════
     STEP 2 — PREVIEW
══════════════════════════════════════════════════════════ --}}

{{-- Stats --}}
@php
    // Exclude duplicate rows — their alokasi is already aggregated into the first occurrence
    $ratTotalAlokasi = collect($matchedRows)->whereNotIn('status', ['duplicate'])->sum('alokasi_anggaran');
@endphp
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
    <div class="bg-white rounded-xl border p-3 shadow-sm">
        <p class="text-xs text-gray-500">Total Baris</p>
        <p class="text-2xl font-bold text-gray-800">{{ $stats['total'] }}</p>
    </div>
    <div class="bg-white rounded-xl border border-green-200 p-3 shadow-sm">
        <p class="text-xs text-green-600 font-medium">Cocok</p>
        <p class="text-2xl font-bold text-green-700">{{ $stats['matched'] - ($stats['new_sk'] ?? 0) }}</p>
        <p class="text-[10px] text-green-400">akan diimport</p>
    </div>
    @if(($stats['new_sk'] ?? 0) > 0)
    <div class="bg-white rounded-xl border border-purple-200 p-3 shadow-sm">
        <p class="text-xs text-purple-600 font-medium">Buat SK Baru</p>
        <p class="text-2xl font-bold text-purple-700">{{ $stats['new_sk'] }}</p>
        <p class="text-[10px] text-purple-400">sub kegiatan otomatis</p>
    </div>
    @endif
    <div class="bg-white rounded-xl border border-red-200 p-3 shadow-sm">
        <p class="text-xs text-red-600 font-medium">Tidak Cocok</p>
        <p class="text-2xl font-bold text-red-500">{{ $stats['not_found'] }}</p>
        <p class="text-[10px] text-red-400">dibuat otomatis</p>
    </div>
    @if(($stats['duplicate'] ?? 0) > 0)
    <div class="bg-white rounded-xl border border-orange-200 p-3 shadow-sm">
        <p class="text-xs text-orange-600 font-medium">Duplikat</p>
        <p class="text-2xl font-bold text-orange-500">{{ $stats['duplicate'] }}</p>
        <p class="text-[10px] text-orange-400">digabung ke baris pertama</p>
    </div>
    @endif
    <div class="col-span-2 md:col-span-1 bg-green-50 rounded-xl border border-green-200 p-3 shadow-sm">
        <p class="text-xs text-green-700 font-medium">Total Alokasi File</p>
        <p class="text-base font-bold text-green-800 break-all">Rp {{ number_format($ratTotalAlokasi, 0, ',', '.') }}</p>
        <p class="text-[10px] text-green-500">seluruh {{ $stats['total'] }} baris</p>
    </div>
</div>

@if(($stats['new_sk'] ?? 0) > 0)
<div class="flex items-start gap-3 bg-purple-50 border border-purple-300 rounded-xl p-3 mb-3 text-sm">
    <i class="fas fa-wand-magic-sparkles text-purple-500 mt-0.5 flex-shrink-0"></i>
    <div>
        <p class="font-semibold text-purple-800">{{ $stats['new_sk'] }} sub kegiatan baru akan dibuat otomatis</p>
        <p class="text-purple-700 text-xs mt-0.5">Baris bertanda <span class="bg-purple-100 text-purple-700 rounded px-1.5 py-0.5 font-semibold">Buat SK</span> — nama sub kegiatan cocok dengan kegiatan yang ada, SK baru akan dibuat di bawahnya saat import dikonfirmasi.</p>
    </div>
</div>
@endif

@if($stats['not_found'] > 0)
<div class="flex items-start gap-3 bg-blue-50 border border-blue-300 rounded-xl p-3 mb-4 text-sm">
    <i class="fas fa-circle-info text-blue-500 mt-0.5 flex-shrink-0"></i>
    <div>
        <p class="font-semibold text-blue-800">{{ $stats['not_found'] }} baris tidak cocok — akan dibuat otomatis sebagai Sub Kegiatan baru</p>
        <p class="text-blue-700 text-xs mt-0.5">
            Semua baris <span class="bg-red-100 text-red-700 rounded px-1.5 py-0.5 font-semibold">Tidak Cocok</span> sudah dicentang dan akan dibuatkan Sub Kegiatan, Kegiatan, dan Program baru secara otomatis saat import dikonfirmasi.
            Anda tetap bisa uncheck baris tertentu jika tidak ingin diimport.
        </p>
    </div>
</div>
@endif

{{-- Info bar --}}
<div class="flex items-center justify-between flex-wrap gap-2 mb-3">
    <div class="flex items-center gap-3">
        <span class="text-sm font-medium text-gray-700">
            <i class="fas fa-calendar text-green-600 mr-1"></i> Tahun <strong>{{ $tahun }}</strong>
        </span>
        <span class="text-xs bg-green-100 text-green-800 border border-green-200 rounded-full px-2.5 py-0.5 font-semibold">Matriks RAT 18 Kolom</span>
        <a href="{{ route('oppkpke.import.rat') }}" class="text-xs text-gray-500 hover:text-gray-800 flex items-center gap-1">
            <i class="fas fa-arrow-left text-[10px]"></i> Upload ulang
        </a>
    </div>
    <div class="flex items-center gap-2">
        <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
            <input type="checkbox" id="check-all" onchange="toggleAll(this)" checked class="rounded">
            Pilih Semua Baris
        </label>
    </div>
</div>

{{-- Preview table --}}
<div class="bg-white rounded-xl border shadow-sm overflow-hidden mb-4">
    <div class="overflow-x-auto" style="max-height: 68vh; overflow-y:auto;">
        <table class="w-full text-xs border-collapse" style="min-width: 1600px;">
            <thead class="sticky top-0 z-10">
            <tr style="background:#1a5c2a; color:#fff;">
                <th class="border border-green-800 px-2 py-2 text-center w-8">
                    <input type="checkbox" id="check-all-top" onchange="toggleAll(this)" checked class="rounded">
                </th>
                <th class="border border-green-800 px-2 py-2 text-center w-10">#</th>
                <th class="border border-green-800 px-2 py-2 text-center w-24">Status</th>
                <th class="border border-green-800 px-2 py-2 text-left w-40">Perangkat Daerah</th>
                <th class="border border-green-800 px-2 py-2 text-left">Sub Kegiatan (File)</th>
                <th class="border border-green-800 px-2 py-2 text-left">Sub Kegiatan (DB)</th>
                <th class="border border-green-800 px-2 py-2 text-left w-36">Aktivitas Langsung</th>
                <th class="border border-green-800 px-2 py-2 text-left w-36">Tdk Langsung</th>
                <th class="border border-green-800 px-2 py-2 text-left w-28">Penunjang</th>
                <th class="border border-green-800 px-2 py-2 text-right w-36">Alokasi Anggaran</th>
                <th class="border border-green-800 px-2 py-2 text-center w-24">Sumber</th>
                <th class="border border-green-800 px-2 py-2 text-left w-28">Sifat Bantuan</th>
                <th class="border border-green-800 px-2 py-2 text-left w-28">Lokasi</th>
                <th class="border border-green-800 px-2 py-2 text-center w-24">Jml Sasaran</th>
                <th class="border border-green-800 px-2 py-2 text-left w-28">Besaran Manfaat</th>
                <th class="border border-green-800 px-2 py-2 text-center w-24">Jenis Bantuan</th>
                <th class="border border-green-800 px-2 py-2 text-center w-24">Durasi</th>
            </tr>
            </thead>
            <tbody>
            @php
                $totAlokasi = 0;
                $checkedCount = 0;
            @endphp
            @foreach($matchedRows as $row)
            @php
                $status      = $row['status'];
                $isMatched   = $status === 'matched';
                $isNewSk     = $status === 'new_sk';
                $isDuplicate = $status === 'duplicate';
                $isNotFound  = $status === 'not_found';
                $canImport   = $isMatched || $isNewSk || $isNotFound;
                $isUpdate    = $isMatched && $row['has_existing'];
                if ($canImport) { $totAlokasi += $row['alokasi_anggaran']; $checkedCount++; }
                $bg = match($status) {
                    'matched'   => $isUpdate ? '#FFFBEB' : '#F0FDF4',
                    'new_sk'    => '#FAF5FF',
                    'not_found' => '#FFF1F2',
                    'duplicate' => '#FFF7ED',
                    default     => '#fff',
                };
            @endphp
            <tr style="background:{{ $bg }};" class="hover:opacity-90 transition-colors">
                {{-- Checkbox --}}
                <td class="border border-gray-200 px-2 py-2 text-center">
                    @if($canImport)
                        <input type="checkbox" name="row_check[]" value="{{ $row['row_num'] }}"
                               class="row-check rounded {{ $isNotFound ? 'accent-red-500' : '' }}" checked>
                    @else
                        <span class="text-gray-300">—</span>
                    @endif
                </td>
                {{-- Row # --}}
                <td class="border border-gray-200 px-2 py-2 text-center text-gray-400">{{ $row['row_num'] }}</td>
                {{-- Status badge --}}
                <td class="border border-gray-200 px-2 py-2 text-center">
                    @if($isNewSk)
                        <span class="inline-flex items-center gap-1 bg-purple-100 text-purple-700 text-[10px] font-semibold px-2 py-0.5 rounded-full whitespace-nowrap">
                            <i class="fas fa-wand-magic-sparkles text-[9px]"></i> Buat SK
                        </span>
                    @elseif($isUpdate)
                        <span class="inline-block bg-amber-100 text-amber-700 text-[10px] font-semibold px-2 py-0.5 rounded-full">Update</span>
                    @elseif($isMatched)
                        <span class="inline-block bg-green-100 text-green-700 text-[10px] font-semibold px-2 py-0.5 rounded-full">Baru</span>
                    @elseif($isDuplicate)
                        <span class="inline-flex items-center gap-1 bg-orange-100 text-orange-700 text-[10px] font-semibold px-2 py-0.5 rounded-full whitespace-nowrap">
                            <i class="fas fa-copy text-[9px]"></i> Duplikat
                        </span>
                    @else
                        <span class="inline-flex items-center gap-0.5 bg-red-100 text-red-700 text-[10px] font-semibold px-1.5 py-0.5 rounded-full whitespace-nowrap">
                            <i class="fas fa-xmark text-[9px]"></i> Tidak Cocok
                        </span>
                    @endif
                </td>
                {{-- PD --}}
                <td class="border border-gray-200 px-2 py-2 text-gray-700 leading-snug">
                    {{ $row['matched_pd_nama'] ?? $row['perangkat_daerah'] ?: '—' }}
                </td>
                {{-- Sub Kegiatan file vs DB --}}
                <td class="border border-gray-200 px-2 py-2 text-gray-700 leading-snug">
                    @if(!empty($row['kode']) && preg_match('/^\d[\d.]{4,}$/', $row['kode']))
                        <p class="text-[10px] text-gray-400 font-mono mb-0.5">{{ $row['kode'] }}</p>
                    @endif
                    {{ $row['sub_kegiatan'] }}
                    @if($isNotFound && !empty($row['kegiatan']))
                        <p class="text-[10px] text-gray-400 mt-0.5">↳ {{ $row['kegiatan'] }}</p>
                    @endif
                </td>
                <td class="border border-gray-200 px-2 py-2 leading-snug
                    @if($isMatched || $isNewSk) text-green-800 font-medium
                    @elseif($isNotFound) text-red-400 italic
                    @elseif($isDuplicate) text-orange-600
                    @endif">
                    @if($isNewSk)
                        <span class="text-purple-700"><i class="fas fa-wand-magic-sparkles text-[9px] mr-0.5"></i> Akan dibuat: {{ $row['sub_kegiatan'] }}</span>
                    @elseif($isDuplicate)
                        {{ $row['matched_sk_nama'] ?? '—' }}
                    @else
                        {{ $row['matched_sk_nama'] ?? '— tidak ditemukan —' }}
                    @endif
                </td>
                {{-- Aktivitas --}}
                <td class="border border-gray-200 px-2 py-2 text-gray-600 leading-snug">{{ $row['aktivitas_langsung'] ?: '—' }}</td>
                <td class="border border-gray-200 px-2 py-2 text-gray-600 leading-snug">{{ $row['aktivitas_tidak_langsung'] ?: '—' }}</td>
                <td class="border border-gray-200 px-2 py-2 text-gray-600 leading-snug">{{ $row['aktivitas_penunjang'] ?: '—' }}</td>
                {{-- Alokasi --}}
                <td class="border border-gray-200 px-2 py-2 text-right font-mono {{ $isMatched ? 'text-green-800 font-semibold' : 'text-gray-400' }} whitespace-nowrap">
                    {{ $row['alokasi_anggaran'] > 0 ? number_format($row['alokasi_anggaran'], 0, ',', '.') : '—' }}
                </td>
                {{-- Detail cols --}}
                <td class="border border-gray-200 px-2 py-2 text-center text-gray-600">{{ $row['sumber_pembiayaan'] ?: 'APBD' }}</td>
                <td class="border border-gray-200 px-2 py-2 text-gray-600 leading-snug">{{ $row['sifat_bantuan'] ?: '—' }}</td>
                <td class="border border-gray-200 px-2 py-2 text-gray-600 leading-snug">{{ $row['lokasi'] ?: '—' }}</td>
                <td class="border border-gray-200 px-2 py-2 text-center text-gray-600">{{ $row['jumlah_sasaran'] ?: '—' }}</td>
                <td class="border border-gray-200 px-2 py-2 text-gray-600 leading-snug">{{ $row['besaran_manfaat'] ?: '—' }}</td>
                <td class="border border-gray-200 px-2 py-2 text-center text-gray-600">{{ $row['jenis_bantuan'] ?: '—' }}</td>
                <td class="border border-gray-200 px-2 py-2 text-center text-gray-600">{{ $row['durasi_pemberian'] ?: '—' }}</td>
            </tr>
            @endforeach

            {{-- Grand total --}}
            <tr style="background:#1a5c2a; color:#fff; font-weight:bold;">
                <td colspan="9" class="border border-green-800 px-3 py-2 text-right text-sm">TOTAL ALOKASI (seluruh file)</td>
                <td class="border border-green-800 px-2 py-2 text-right font-mono text-yellow-300 whitespace-nowrap">
                    {{ number_format($ratTotalAlokasi, 0, ',', '.') }}
                </td>
                <td colspan="7" class="border border-green-800 px-2 py-2"></td>
            </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- Confirm form --}}
@php
    $existingRatCount = \App\Models\LaporanOppkpke::where('tahun', $tahun)->count();
    $importSkCount    = collect($matchedRows)->whereIn('status', ['matched', 'new_sk'])->pluck('sub_kegiatan_id')->filter()->unique()->count();
    $notFoundCount    = collect($matchedRows)->where('status', 'not_found')->count();
    $ratOldCount      = max(0, $existingRatCount - ($importSkCount + $notFoundCount));
@endphp
<form method="POST" action="{{ route('oppkpke.import.rat.execute') }}" onsubmit="return confirmImport(this)">
    @csrf
    <input type="hidden" name="cache_key" value="{{ $cacheKey }}">
    <input type="hidden" name="replace_year" id="rat-replace-year" value="0">

    {{-- Sinkronisasi Penuh toggle --}}
    <div class="bg-white rounded-xl border shadow-sm p-4 mb-4">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <p class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-rotate text-gray-500"></i>
                    Sinkronisasi Penuh (Tahun {{ $tahun }})
                </p>
                <p class="text-xs text-gray-500 mt-0.5">
                    Hapus data RAT tahun {{ $tahun }} yang tidak ada di file ini.
                    @if($ratOldCount > 0)
                        <span class="text-orange-600 font-medium">±{{ $ratOldCount }} record lama akan dihapus.</span>
                    @else
                        <span class="text-gray-400">Tidak ada record lama yang perlu dihapus.</span>
                    @endif
                    Data tahun lainnya <strong>tidak</strong> terpengaruh.
                </p>
            </div>
            {{-- Toggle pill --}}
            <div class="flex items-center gap-2 flex-shrink-0">
                <span class="text-xs text-gray-500" id="rat-sync-label">Nonaktif</span>
                <button type="button" id="rat-sync-toggle" onclick="ratToggleSync()"
                        class="relative w-11 h-6 rounded-full transition-colors duration-200 focus:outline-none bg-gray-300"
                        aria-pressed="false">
                    <span id="rat-sync-knob"
                          class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200"></span>
                </button>
            </div>
        </div>
        {{-- Warning (hidden by default) --}}
        <div id="rat-sync-warn" class="hidden mt-3 flex items-start gap-2 bg-red-50 border border-red-200 rounded-lg p-2.5 text-xs text-red-700">
            <i class="fas fa-triangle-exclamation text-red-500 mt-0.5 flex-shrink-0"></i>
            <span>Sinkronisasi Penuh aktif — record tahun {{ $tahun }} yang <strong>tidak ada di file ini</strong> akan dihapus permanen setelah import.</span>
        </div>
    </div>

    <div class="flex items-center justify-between flex-wrap gap-3">
        <a href="{{ route('oppkpke.import.rat') }}"
           class="flex items-center gap-2 text-sm text-gray-600 border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50 transition">
            <i class="fas fa-arrow-left text-xs"></i> Upload Ulang
        </a>

        <button type="submit" id="rat-submit-btn"
                class="flex items-center gap-2 bg-green-700 hover:bg-green-800 text-white text-sm font-semibold px-6 py-2.5 rounded-lg shadow transition">
            <i class="fas fa-file-import"></i>
            Konfirmasi Import RAT
            <span class="bg-green-900 rounded px-1.5 py-0.5 text-xs" id="import-count">{{ $checkedCount }}</span>
            baris
        </button>
    </div>
</form>

@endif

@endsection

@push('scripts')
<script>
// ── File upload ───────────────────────────────────────────────────
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
    document.getElementById('drop-zone').classList.remove('border-green-600', 'bg-green-100');
    const file = e.dataTransfer.files[0];
    if (!file) return;
    const input = document.getElementById('file-input');
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    showFileInfo(input);
}

// ── Checkboxes ────────────────────────────────────────────────────
function toggleAll(master) {
    const state = master.checked;
    document.querySelectorAll('.row-check').forEach(cb => { cb.checked = state; });
    // Sync both master checkboxes
    ['check-all', 'check-all-top'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = state;
    });
    updateImportCount();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.row-check').forEach(cb => {
        cb.addEventListener('change', () => {
            updateImportCount();
            const all  = document.querySelectorAll('.row-check').length;
            const chkd = document.querySelectorAll('.row-check:checked').length;
            ['check-all', 'check-all-top'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.indeterminate = (chkd > 0 && chkd < all);
                    el.checked = (chkd === all);
                }
            });
        });
    });

});

function updateImportCount() {
    const count = document.querySelectorAll('.row-check:checked').length;
    const badge = document.getElementById('import-count');
    if (badge) badge.textContent = count;
}

// ── Sinkronisasi Penuh toggle ─────────────────────────────────────
var ratSyncActive = false;
function ratToggleSync() {
    ratSyncActive = !ratSyncActive;
    const toggle = document.getElementById('rat-sync-toggle');
    const knob   = document.getElementById('rat-sync-knob');
    const label  = document.getElementById('rat-sync-label');
    const warn   = document.getElementById('rat-sync-warn');
    const field  = document.getElementById('rat-replace-year');
    const btn    = document.getElementById('rat-submit-btn');
    if (ratSyncActive) {
        toggle.classList.replace('bg-gray-300', 'bg-red-500');
        knob.style.transform = 'translateX(20px)';
        toggle.setAttribute('aria-pressed', 'true');
        label.textContent = 'Aktif';
        warn.classList.remove('hidden');
        field.value = '1';
        btn.classList.replace('bg-green-700', 'bg-red-600');
        btn.classList.replace('hover:bg-green-800', 'hover:bg-red-700');
    } else {
        toggle.classList.replace('bg-red-500', 'bg-gray-300');
        knob.style.transform = 'translateX(0)';
        toggle.setAttribute('aria-pressed', 'false');
        label.textContent = 'Nonaktif';
        warn.classList.add('hidden');
        field.value = '0';
        btn.classList.replace('bg-red-600', 'bg-green-700');
        btn.classList.replace('hover:bg-red-700', 'hover:bg-green-800');
    }
}

// ── Confirm import ────────────────────────────────────────────────
function confirmImport(form) {
    const count = document.querySelectorAll('.row-check:checked').length;
    if (count === 0) {
        alert('Tidak ada baris yang dipilih. Centang minimal satu baris.');
        return false;
    }

    // Build skip list: rows that are NOT checked
    document.querySelectorAll('.row-check').forEach(cb => {
        if (!cb.checked) {
            const hidden = document.createElement('input');
            hidden.type  = 'hidden';
            hidden.name  = 'skip[]';
            hidden.value = cb.value;
            form.appendChild(hidden);
        }
    });

    const syncMsg = ratSyncActive
        ? '\n\n⚠️ SINKRONISASI PENUH aktif — record lama tahun {{ $tahun ?? "" }} yang tidak ada di file ini akan DIHAPUS permanen.'
        : '';

    return confirm(
        `Anda akan mengimport ${count} baris data Matriks RAT untuk Tahun {{ $tahun ?? '' }}.\n\n` +
        `Data Alokasi Anggaran & perencanaan akan diperbarui.\n` +
        `Data Realisasi yang sudah ada TIDAK akan diubah.` +
        syncMsg + `\n\nLanjutkan?`
    );
}

// ── Upload loading state ──────────────────────────────────────────
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
