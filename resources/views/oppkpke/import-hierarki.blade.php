@extends('layouts.oppkpke')

@section('title', 'Import Hierarki')
@section('page-title', 'Import Hierarki (Excel)')
@section('page-subtitle', 'Perangkat Daerah → Strategi → Program → Kegiatan → Sub Kegiatan')

@section('content')

{{-- ── Hasil eksekusi ───────────────────────────────────────────── --}}
@if(session('import_result'))
@php $res = session('import_result'); @endphp
<div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-5">
    <p class="text-sm font-semibold text-green-800 mb-2"><i class="fas fa-circle-check mr-1"></i> Import selesai.</p>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs text-green-800">
        <div class="bg-white rounded-lg border border-green-200 p-2 text-center"><div class="text-lg font-bold">{{ $res['processed'] }}</div>baris diproses</div>
        <div class="bg-white rounded-lg border border-green-200 p-2 text-center"><div class="text-lg font-bold">{{ $res['pd_created'] }}</div>PD baru</div>
        <div class="bg-white rounded-lg border border-green-200 p-2 text-center"><div class="text-lg font-bold">{{ $res['program_created'] }}</div>program baru</div>
        <div class="bg-white rounded-lg border border-green-200 p-2 text-center"><div class="text-lg font-bold">{{ $res['kegiatan_created'] }}</div>kegiatan baru</div>
        <div class="bg-white rounded-lg border border-green-200 p-2 text-center"><div class="text-lg font-bold">{{ $res['sub_created'] }}</div>sub baru</div>
        <div class="bg-white rounded-lg border border-green-200 p-2 text-center"><div class="text-lg font-bold">{{ $res['sub_existing'] }}</div>sub sudah ada</div>
        <div class="bg-white rounded-lg border border-green-200 p-2 text-center"><div class="text-lg font-bold">{{ $res['skipped'] }}</div>baris dilewati</div>
    </div>
</div>
@endif

@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5 text-sm text-red-700">
    <i class="fas fa-triangle-exclamation mr-1"></i> {{ $errors->first() }}
</div>
@endif

{{-- ── Langkah 1: Template & Upload ──────────────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
    <div class="bg-white rounded-xl border shadow-sm p-5">
        <h3 class="font-semibold text-gray-800 text-sm mb-2"><i class="fas fa-file-arrow-down text-indigo-500 mr-1"></i> 1. Unduh Template</h3>
        <p class="text-xs text-gray-500 mb-3">Isi mulai baris ke-2. Kolom wajib: Perangkat Daerah, Strategi, Nama Sub Kegiatan. Anggaran/realisasi tidak termasuk (diisi via "Isi Laporan").</p>
        <a href="{{ route('oppkpke.import.hierarki.template') }}"
           class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
            <i class="fas fa-file-excel"></i> Unduh Template Excel
        </a>
    </div>

    <div class="bg-white rounded-xl border shadow-sm p-5 lg:col-span-2">
        <h3 class="font-semibold text-gray-800 text-sm mb-2"><i class="fas fa-file-arrow-up text-indigo-500 mr-1"></i> 2. Upload &amp; Preview</h3>
        <form method="POST" action="{{ route('oppkpke.import.hierarki.preview') }}" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-3">
            @csrf
            <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                   class="flex-1 text-sm border rounded-lg p-2 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition whitespace-nowrap">
                <i class="fas fa-magnifying-glass mr-1"></i> Analisis &amp; Preview
            </button>
        </form>
        <p class="text-[11px] text-gray-400 mt-2">Format .xlsx / .xls / .csv, maksimal 20MB. Data belum disimpan sampai Anda menekan "Jalankan Import".</p>
    </div>
</div>

{{-- ── Langkah 3: Preview ────────────────────────────────────────── --}}
@isset($rows)
<div class="bg-white rounded-xl border shadow-sm overflow-hidden">
    <div class="px-4 md:px-5 py-3 border-b bg-gray-50 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2 text-xs">
            <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded">Total: <strong>{{ $stats['total'] }}</strong></span>
            <span class="bg-green-100 text-green-700 px-2 py-1 rounded">Siap: <strong>{{ $stats['valid'] }}</strong></span>
            <span class="bg-red-100 text-red-700 px-2 py-1 rounded">Bermasalah: <strong>{{ $stats['error'] }}</strong></span>
            <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded">PD baru: <strong>{{ $stats['pd_baru'] }}</strong></span>
        </div>
        <form method="POST" action="{{ route('oppkpke.import.hierarki.execute') }}" onsubmit="return confirm('Jalankan import {{ $stats['valid'] }} baris valid? Baris bermasalah akan dilewati.');">
            @csrf
            <input type="hidden" name="cache_key" value="{{ $cacheKey }}">
            <button type="submit" {{ $stats['valid'] === 0 ? 'disabled' : '' }}
                    class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                <i class="fas fa-play mr-1"></i> Jalankan Import ({{ $stats['valid'] }})
            </button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-gray-50 text-gray-600 uppercase tracking-wide">
                <tr>
                    <th class="px-3 py-2 text-left">#</th>
                    <th class="px-3 py-2 text-left">Perangkat Daerah</th>
                    <th class="px-3 py-2 text-left">Strategi</th>
                    <th class="px-3 py-2 text-left">Program</th>
                    <th class="px-3 py-2 text-left">Kegiatan</th>
                    <th class="px-3 py-2 text-left">Sub Kegiatan</th>
                    <th class="px-3 py-2 text-left">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($rows as $row)
                <tr class="{{ $row['valid'] ? 'hover:bg-gray-50' : 'bg-red-50' }}">
                    <td class="px-3 py-2 text-gray-400">{{ $row['baris'] }}</td>
                    <td class="px-3 py-2">
                        {{ $row['pd'] ?: '—' }}
                        @if($row['pd'])
                            <span class="ml-1 text-[10px] {{ $row['pd_status'] === 'baru' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }} px-1.5 py-0.5 rounded-full">{{ $row['pd_status'] }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2">{{ $row['strategi_nama'] ?? ('⚠ ' . $row['strategi']) }}</td>
                    <td class="px-3 py-2">{{ $row['nama_program'] ?: '(belum diisi)' }}</td>
                    <td class="px-3 py-2">{{ $row['nama_kegiatan'] ?: '(belum diisi)' }}</td>
                    <td class="px-3 py-2">{{ $row['nama_sub'] ?: '—' }}</td>
                    <td class="px-3 py-2">
                        @if($row['valid'])
                            <span class="text-green-600"><i class="fas fa-check"></i> Siap</span>
                        @else
                            <span class="text-red-600" title="{{ $row['error'] }}"><i class="fas fa-xmark"></i> {{ $row['error'] }}</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endisset

@endsection
