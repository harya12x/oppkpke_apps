@extends('layouts.oppkpke')

@section('title', 'Matriks OPPKPKE')
@section('page-title', 'Matriks OPPKPKE')
@section('page-subtitle', 'Format 21 Kolom Resmi — Tahun ' . $tahun)

@section('content')

@php
    $selS  = collect($filterOptions['strategi'] ?? [])->firstWhere('id', request('strategi_id'));
    $selPd = collect($filterOptions['perangkat_daerah'] ?? [])->firstWhere('id', request('perangkat_daerah_id'));
@endphp

{{-- ── Filter Bar ─────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl shadow-sm border p-3 md:p-4 mb-4 md:mb-5">
    <form method="GET" action="{{ route('oppkpke.matrix') }}" id="mx-form" class="flex flex-wrap gap-2 md:gap-3 items-end">

        {{-- Tahun --}}
        <div>
            <label class="text-xs font-medium text-gray-600 block mb-1">Tahun</label>
            <input type="hidden" name="tahun" id="mx-tahun" value="{{ $tahun }}">
            <button type="button" onclick="mxOpenTahun()"
                    class="flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-1.5 bg-white hover:bg-gray-50 transition text-sm min-w-[90px]">
                <i class="fas fa-calendar text-gray-400 text-xs"></i>
                <span id="mx-lbl-tahun" class="font-medium text-gray-800">{{ $tahun }}</span>
                <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
            </button>
        </div>

        {{-- Semester (hanya untuk label export Excel, bukan filter data) --}}
        <div>
            <label class="text-xs font-medium text-gray-600 block mb-1">
                Semester
                <span class="text-[10px] text-gray-400 font-normal ml-1">(untuk export)</span>
            </label>
            <select name="semester" class="border border-gray-300 rounded-lg px-3 py-1.5 bg-white text-sm focus:ring-2 focus:ring-blue-500">
                <option value="2" {{ request('semester', 2) == 2 ? 'selected' : '' }}>Semester 2</option>
                <option value="1" {{ request('semester') == 1 ? 'selected' : '' }}>Semester 1</option>
            </select>
        </div>

        @if(auth()->user()->isMaster())
        {{-- Strategi --}}
        <div>
            <label class="text-xs font-medium text-gray-600 block mb-1">Strategi</label>
            <select name="strategi_id" class="border border-gray-300 rounded-lg px-3 py-1.5 bg-white text-sm focus:ring-2 focus:ring-blue-500 max-w-[220px]">
                <option value="">Semua Strategi</option>
                @foreach($filterOptions['strategi'] ?? [] as $s)
                    <option value="{{ $s->id }}" {{ request('strategi_id') == $s->id ? 'selected' : '' }}>{{ $s->nama }}</option>
                @endforeach
            </select>
        </div>

        {{-- Perangkat Daerah --}}
        <div>
            <label class="text-xs font-medium text-gray-600 block mb-1">Perangkat Daerah</label>
            <select name="perangkat_daerah_id" class="border border-gray-300 rounded-lg px-3 py-1.5 bg-white text-sm focus:ring-2 focus:ring-blue-500 max-w-[240px]">
                <option value="">Semua OPD</option>
                @foreach($filterOptions['perangkat_daerah'] ?? [] as $pd)
                    <option value="{{ $pd->id }}" {{ request('perangkat_daerah_id') == $pd->id ? 'selected' : '' }}>{{ $pd->nama }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-1.5 rounded-lg transition flex items-center gap-1.5">
            <i class="fas fa-filter text-xs"></i> Filter
        </button>
        <a href="{{ route('oppkpke.matrix') }}" class="border border-gray-300 text-gray-600 text-sm px-3 py-1.5 rounded-lg hover:bg-gray-50 transition">Reset</a>

        {{-- Export Button --}}
        <div class="ml-auto">
            <a href="{{ route('oppkpke.export.excel', array_merge(request()->query(), ['tahun' => $tahun])) }}"
               class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-1.5 rounded-lg transition shadow-sm">
                <i class="fas fa-file-excel"></i>
                Export Excel (.xlsx)
            </a>
        </div>
    </form>

    {{-- Hidden tahun picker --}}
    <div id="mx-tahun-picker" class="hidden mt-2">
        <div class="flex flex-wrap gap-2">
            @for($y = 2023; $y <= 2030; $y++)
                <button type="button" onclick="mxSetTahun({{ $y }})"
                    class="px-3 py-1 rounded-lg text-sm border transition {{ $tahun == $y ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50' }}">
                    {{ $y }}
                </button>
            @endfor
        </div>
    </div>
</div>

{{-- ── Stats Summary ──────────────────────────────────────────────── --}}
@php
    $dataRows    = collect($rows)->where('_type', 'data');
    $totAlokasi  = $totals['alokasi'];
    $totSem1     = $totals['sem1'];
    $totSem2     = $totals['sem2'];
    $totTotal    = $totals['realisasi'];
    $persen      = $totAlokasi > 0 ? round(($totTotal / $totAlokasi) * 100, 1) : 0;
@endphp
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <div class="bg-white rounded-xl border p-3 shadow-sm">
        <p class="text-xs text-gray-500">Total Baris Data</p>
        <p class="text-xl font-bold text-gray-800">{{ number_format($dataRows->count()) }}</p>
        <p class="text-xs text-gray-400">sub kegiatan</p>
    </div>
    <div class="bg-white rounded-xl border p-3 shadow-sm">
        <p class="text-xs text-gray-500">Alokasi Anggaran</p>
        <p class="text-base font-bold text-blue-700">Rp {{ number_format($totAlokasi, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl border p-3 shadow-sm">
        <p class="text-xs text-gray-500">Realisasi Sem.1</p>
        <p class="text-base font-bold text-indigo-600">Rp {{ number_format($totSem1, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl border p-3 shadow-sm">
        <p class="text-xs text-gray-500">Realisasi Sem.2</p>
        <p class="text-base font-bold text-purple-600">Rp {{ number_format($totSem2, 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-xl border p-3 shadow-sm">
        <p class="text-xs text-gray-500">Total Realisasi</p>
        <p class="text-base font-bold {{ $persen >= 80 ? 'text-green-600' : ($persen >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
            Rp {{ number_format($totTotal, 0, ',', '.') }}
        </p>
        <p class="text-xs font-semibold {{ $persen >= 80 ? 'text-green-500' : ($persen >= 50 ? 'text-yellow-500' : 'text-red-500') }}">{{ $persen }}%</p>
    </div>
</div>

{{-- ── Matrix Table ────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="p-3 md:p-4 border-b flex items-center justify-between flex-wrap gap-2">
        <div>
            <h2 class="text-sm font-semibold text-gray-800">Matriks OPPKPKE — Format 21 Kolom Resmi</h2>
            <p class="text-xs text-gray-500 mt-0.5">LAPORAN OPTIMALISASI PELAKSANAAN PENGENTASAN KEMISKINAN DAN PENGHAPUSAN KEMISKINAN EKSTREM (OPPKPKE) — TAHUN {{ $tahun }}</p>
        </div>
        <span class="text-xs bg-blue-50 text-blue-700 border border-blue-200 rounded-full px-3 py-1 font-medium">
            {{ $dataRows->count() }} baris
        </span>
    </div>

    <div class="overflow-x-auto" style="max-height: 75vh; overflow-y: auto;">
        <table class="w-full text-xs border-collapse" style="min-width: 2400px;">
            {{-- Header group 1 --}}
            <thead class="sticky top-0 z-10">
            <tr style="background:#1e3a5f; color:#fff;">
                <th rowspan="3" class="border border-slate-600 px-1.5 py-2 text-center w-8">No</th>
                <th rowspan="3" class="border border-slate-600 px-2 py-2 text-center w-36">Strategi<br>OPPKPKE</th>
                <th rowspan="3" class="border border-slate-600 px-2 py-2 text-center w-40">Perangkat<br>Daerah</th>
                <th rowspan="3" class="border border-slate-600 px-2 py-2 text-center w-36">Kode</th>
                <th rowspan="3" class="border border-slate-600 px-2 py-2 text-center w-44">Program</th>
                <th rowspan="3" class="border border-slate-600 px-2 py-2 text-center w-44">Kegiatan</th>
                <th rowspan="3" class="border border-slate-600 px-2 py-2 text-center w-52">Sub Kegiatan</th>
                <th colspan="3" class="border border-slate-600 px-2 py-2 text-center">Aktifitas Real</th>
                <th rowspan="3" class="border border-slate-600 px-2 py-2 text-center w-32">Alokasi<br>Anggaran (Rp)</th>
                <th rowspan="3" class="border border-slate-600 px-2 py-2 text-center w-20">Sumber<br>Pembiayaan</th>
                <th rowspan="3" class="border border-slate-600 px-2 py-2 text-center w-24">Sifat<br>Bantuan</th>
                <th rowspan="3" class="border border-slate-600 px-2 py-2 text-center w-28">Lokasi</th>
                <th rowspan="3" class="border border-slate-600 px-2 py-2 text-center w-24">Jumlah Sasaran<br>Penerima Manfaat</th>
                <th rowspan="3" class="border border-slate-600 px-2 py-2 text-center w-28">Besaran<br>Manfaat</th>
                <th rowspan="3" class="border border-slate-600 px-2 py-2 text-center w-24">Jenis<br>Bantuan</th>
                <th rowspan="3" class="border border-slate-600 px-2 py-2 text-center w-24">Durasi<br>Pemberian</th>
                <th colspan="3" class="border border-slate-600 px-2 py-2 text-center">Realisasi</th>
            </tr>
            <tr style="background:#1e3a5f; color:#fff;">
                <th class="border border-slate-600 px-2 py-1 text-center w-36">Langsung</th>
                <th class="border border-slate-600 px-2 py-1 text-center w-36">Tidak Langsung</th>
                <th class="border border-slate-600 px-2 py-1 text-center w-32">Penunjang</th>
                <th class="border border-slate-600 px-2 py-1 text-center w-28">Sem.1</th>
                <th class="border border-slate-600 px-2 py-1 text-center w-28">Sem.2</th>
                <th class="border border-slate-600 px-2 py-1 text-center w-28">Total</th>
            </tr>
            <tr style="background:#2e4e7e; color:#d0dff0; font-size:10px;">
                <th class="border border-slate-600 px-1 py-1 text-center">8</th>
                <th class="border border-slate-600 px-1 py-1 text-center">9</th>
                <th class="border border-slate-600 px-1 py-1 text-center">10</th>
                <th class="border border-slate-600 px-1 py-1 text-center">19</th>
                <th class="border border-slate-600 px-1 py-1 text-center">20</th>
                <th class="border border-slate-600 px-1 py-1 text-center">21</th>
            </tr>
            </thead>

            <tbody>
            @php $no = 1; @endphp
            @foreach($rows as $row)
                @if($row['_type'] === 'strategi_header')
                    <tr>
                        <td colspan="21" class="border border-slate-300 px-3 py-2 font-bold text-sm text-white" style="background:#2563eb;">
                            {{ strtoupper($row['label']) }}
                        </td>
                    </tr>
                    @php $no = 1; @endphp
                @else
                @php
                    $alokasi = (float)$row['alokasi_anggaran'];
                    $total   = (float)$row['realisasi_total'];
                    $pct     = $alokasi > 0 ? round(($total / $alokasi) * 100, 1) : 0;
                    $rowBg   = $no % 2 === 0 ? '#f5f8fc' : '#ffffff';
                @endphp
                <tr style="background:{{ $rowBg }};" class="hover:bg-blue-50 transition-colors">
                    <td class="border border-gray-200 px-1.5 py-2 text-center text-gray-500 font-medium">{{ $no++ }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-gray-700 leading-snug">{{ $row['strategi'] }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-gray-700 font-medium leading-snug">{{ $row['perangkat_daerah'] }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-gray-500 font-mono text-[10px]">{{ $row['kode'] }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-gray-700 leading-snug">{{ $row['program'] }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-gray-700 leading-snug">{{ $row['kegiatan'] }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-gray-800 font-medium leading-snug">{{ $row['sub_kegiatan'] }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-gray-600 leading-snug">{{ $row['aktivitas_langsung'] ?: '-' }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-gray-600 leading-snug">{{ $row['aktivitas_tidak_langsung'] ?: '-' }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-gray-600 leading-snug">{{ $row['aktivitas_penunjang'] ?: '-' }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-right font-mono text-blue-800 whitespace-nowrap">
                        {{ $alokasi > 0 ? number_format($alokasi, 0, ',', '.') : '-' }}
                    </td>
                    <td class="border border-gray-200 px-2 py-2 text-center text-gray-600">{{ $row['sumber_pembiayaan'] ?: 'APBD' }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-center text-gray-600 leading-snug">{{ $row['sifat_bantuan'] ?: '-' }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-gray-600 leading-snug">{{ $row['lokasi'] ?: '-' }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-center text-gray-600">{{ $row['jumlah_sasaran'] ?: '-' }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-gray-600">{{ $row['besaran_manfaat'] ?: '-' }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-center text-gray-600">{{ $row['jenis_bantuan'] ?: '-' }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-center text-gray-600">{{ $row['durasi_pemberian'] ?: '-' }}</td>
                    <td class="border border-gray-200 px-2 py-2 text-right font-mono text-indigo-700 whitespace-nowrap">
                        {{ (float)$row['realisasi_sem1'] > 0 ? number_format($row['realisasi_sem1'], 0, ',', '.') : '-' }}
                    </td>
                    <td class="border border-gray-200 px-2 py-2 text-right font-mono text-purple-700 whitespace-nowrap">
                        {{ (float)$row['realisasi_sem2'] > 0 ? number_format($row['realisasi_sem2'], 0, ',', '.') : '-' }}
                    </td>
                    <td class="border border-gray-200 px-2 py-2 text-right font-mono whitespace-nowrap">
                        @if($total > 0)
                            <span class="{{ $pct >= 80 ? 'text-green-700' : ($pct >= 50 ? 'text-yellow-700' : 'text-red-600') }} font-semibold">
                                {{ number_format($total, 0, ',', '.') }}
                            </span>
                            <br>
                            <span class="text-[10px] {{ $pct >= 80 ? 'text-green-500' : ($pct >= 50 ? 'text-yellow-500' : 'text-red-400') }}">
                                {{ $pct }}%
                            </span>
                        @else
                            <span class="text-gray-300">-</span>
                        @endif
                    </td>
                </tr>
                @endif
            @endforeach

            {{-- Grand Total Row --}}
            <tr style="background:#1e3a5f; color:#fff; font-weight:bold;">
                <td colspan="10" class="border border-slate-600 px-3 py-2 text-right text-sm">GRAND TOTAL</td>
                <td class="border border-slate-600 px-2 py-2 text-right font-mono text-yellow-300 whitespace-nowrap">
                    {{ number_format($totAlokasi, 0, ',', '.') }}
                </td>
                <td colspan="7" class="border border-slate-600 px-2 py-2"></td>
                <td class="border border-slate-600 px-2 py-2 text-right font-mono text-cyan-300 whitespace-nowrap">
                    {{ number_format($totSem1, 0, ',', '.') }}
                </td>
                <td class="border border-slate-600 px-2 py-2 text-right font-mono text-cyan-300 whitespace-nowrap">
                    {{ number_format($totSem2, 0, ',', '.') }}
                </td>
                <td class="border border-slate-600 px-2 py-2 text-right font-mono text-green-300 whitespace-nowrap">
                    {{ number_format($totTotal, 0, ',', '.') }}
                </td>
            </tr>
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script>
function mxOpenTahun() {
    document.getElementById('mx-tahun-picker').classList.toggle('hidden');
}
function mxSetTahun(y) {
    document.getElementById('mx-tahun').value = y;
    document.getElementById('mx-lbl-tahun').textContent = y;
    document.getElementById('mx-tahun-picker').classList.add('hidden');
    document.getElementById('mx-form').submit();
}
</script>
@endpush
