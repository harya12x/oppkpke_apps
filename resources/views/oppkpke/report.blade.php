@extends('layouts.oppkpke')

@section('title', 'Rekap Laporan')
@section('page-title', 'Rekap Laporan OPPKPKE')
@section('page-subtitle', 'Tahun ' . $tahun)

@section('content')

{{-- Filter --}}
@php
    $rptSelS  = collect($filterOptions['strategi'] ?? [])->firstWhere('id', request('strategi_id'));
    $rptSelPd = collect($filterOptions['perangkat_daerah'] ?? [])->firstWhere('id', request('perangkat_daerah_id'));
@endphp
<div class="bg-white rounded-xl shadow-sm border p-3 md:p-4 mb-4 md:mb-6">
    <form method="GET" action="{{ route('oppkpke.report') }}" id="rpt-form" class="flex flex-wrap gap-2 md:gap-3 items-end">

        {{-- Tahun --}}
        <div>
            <label class="text-xs font-medium text-gray-600 block mb-1">Tahun</label>
            <input type="hidden" name="tahun" id="rpt-tahun" value="{{ $tahun }}">
            <button type="button" onclick="rptOpenTahun()"
                    class="flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-1.5 bg-white hover:bg-gray-50 transition text-sm min-w-[90px]">
                <i class="fas fa-calendar text-gray-400 text-xs"></i>
                <span id="rpt-lbl-tahun" class="font-medium text-gray-800">{{ $tahun }}</span>
                <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
            </button>
        </div>

        @if(auth()->user()->isMaster())
        {{-- Strategi --}}
        <div>
            <label class="text-xs font-medium text-gray-600 block mb-1">Strategi</label>
            <input type="hidden" name="strategi_id" id="rpt-strategi" value="{{ request('strategi_id', '') }}">
            <button type="button" onclick="rptOpenStrategi()"
                    class="flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-1.5 bg-white hover:bg-gray-50 transition text-sm min-w-[120px] max-w-[200px]">
                <i class="fas fa-sitemap text-gray-400 text-xs flex-shrink-0"></i>
                <span id="rpt-lbl-strategi" class="truncate" style="color:{{ request('strategi_id') ? '#374151' : '#9ca3af' }}">{{ $rptSelS ? $rptSelS->nama : 'Semua Strategi' }}</span>
                <i class="fas fa-chevron-down text-gray-400 text-xs flex-shrink-0"></i>
            </button>
        </div>

        {{-- Perangkat Daerah --}}
        <div>
            <label class="text-xs font-medium text-gray-600 block mb-1">Perangkat Daerah</label>
            <input type="hidden" name="perangkat_daerah_id" id="rpt-perangkat" value="{{ request('perangkat_daerah_id', '') }}">
            <button type="button" onclick="rptOpenPerangkat()"
                    class="flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-1.5 bg-white hover:bg-gray-50 transition text-sm min-w-[140px] max-w-[220px]">
                <i class="fas fa-building text-gray-400 text-xs flex-shrink-0"></i>
                <span id="rpt-lbl-perangkat" class="truncate" style="color:{{ request('perangkat_daerah_id') ? '#374151' : '#9ca3af' }}">{{ $rptSelPd ? $rptSelPd->nama : 'Semua Perangkat' }}</span>
                <i class="fas fa-chevron-down text-gray-400 text-xs flex-shrink-0"></i>
            </button>
        </div>
        @endif

        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white px-3 md:px-4 py-1.5 rounded-lg text-sm font-medium transition flex items-center gap-1.5">
            <i class="fas fa-filter"></i> Filter
        </button>
        <a href="{{ route('oppkpke.report', ['tahun' => $tahun]) }}"
           class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 md:px-4 py-1.5 rounded-lg text-sm font-medium transition">
            Reset
        </a>
    </form>
</div>

{{-- Summary Totals — dihitung dari DB (bukan dari halaman saat ini) --}}
@php
    $totalAlokasi   = $totals['alokasi'];
    $totalRealisasi = $totals['realisasi'];
    $totalSem1      = $totals['sem1'];
    $totalSem2      = $totals['sem2'];
    $totalPersen    = $totalAlokasi > 0 ? round(($totalRealisasi / $totalAlokasi) * 100, 1) : 0;
@endphp

<div class="grid grid-cols-2 md:grid-cols-5 gap-3 md:gap-4 mb-4 md:mb-6">
    <div class="bg-blue-50 rounded-xl p-3 md:p-4 border border-blue-100">
        <p class="text-xs text-blue-600 font-medium">Total Alokasi</p>
        <p class="text-sm md:text-base font-bold text-blue-700 mt-1 break-all">Rp {{ number_format($totalAlokasi, 0, ',', '.') }}</p>
        <p class="text-[10px] text-blue-400 mt-0.5">{{ $laporan->total() }} sub kegiatan</p>
    </div>
    <div class="bg-green-50 rounded-xl p-3 md:p-4 border border-green-100">
        <p class="text-xs text-green-600 font-medium">Total Realisasi</p>
        <p class="text-sm md:text-base font-bold text-green-700 mt-1 break-all">Rp {{ number_format($totalRealisasi, 0, ',', '.') }}</p>
    </div>
    <div class="bg-purple-50 rounded-xl p-3 md:p-4 border border-purple-100">
        <p class="text-xs text-purple-600 font-medium">Realisasi Sem. 1</p>
        <p class="text-sm md:text-base font-bold text-purple-700 mt-1 break-all">Rp {{ number_format($totalSem1, 0, ',', '.') }}</p>
    </div>
    <div class="bg-indigo-50 rounded-xl p-3 md:p-4 border border-indigo-100">
        <p class="text-xs text-indigo-600 font-medium">Realisasi Sem. 2</p>
        <p class="text-sm md:text-base font-bold text-indigo-700 mt-1 break-all">Rp {{ number_format($totalSem2, 0, ',', '.') }}</p>
    </div>
    <div class="bg-orange-50 rounded-xl p-3 md:p-4 border border-orange-100">
        <p class="text-xs text-orange-600 font-medium">% Realisasi</p>
        <p class="text-sm md:text-base font-bold text-orange-700 mt-1">{{ $totalPersen }}%</p>
        <div class="w-full bg-orange-200 rounded-full h-1.5 mt-1">
            <div class="bg-orange-500 h-1.5 rounded-full" style="width:{{ min($totalPersen,100) }}%"></div>
        </div>
    </div>
</div>

{{-- Tabel Data --}}
<div class="bg-white rounded-xl shadow-sm border">
    <div class="px-4 md:px-6 py-3 md:py-4 border-b flex flex-wrap items-center justify-between gap-2">
        <h3 class="font-semibold text-gray-800 text-sm md:text-base flex items-center gap-2">
            Daftar Laporan
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ $laporan->total() }} data</span>
        </h3>
        <div class="flex gap-2">
            <a href="{{ route('oppkpke.export.excel', request()->query()) }}"
               class="flex items-center gap-1 bg-green-600 hover:bg-green-700 text-white px-2.5 md:px-3 py-1.5 rounded-lg text-xs font-medium transition">
                <i class="fas fa-file-excel"></i> <span class="hidden sm:inline">Excel</span>
            </a>
            <a href="{{ route('oppkpke.export.pdf', request()->query()) }}"
               class="flex items-center gap-1 bg-red-600 hover:bg-red-700 text-white px-2.5 md:px-3 py-1.5 rounded-lg text-xs font-medium transition">
                <i class="fas fa-file-pdf"></i> <span class="hidden sm:inline">PDF</span>
            </a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-xs min-w-[750px]">
            <thead class="bg-gray-50 text-gray-600 uppercase tracking-wide">
                <tr>
                    <th class="px-3 md:px-4 py-3 text-left">No</th>
                    <th class="px-3 md:px-4 py-3 text-left">Sub Kegiatan</th>
                    <th class="px-3 md:px-4 py-3 text-left">Perangkat Daerah</th>
                    <th class="px-3 md:px-4 py-3 text-left">Strategi</th>
                    <th class="px-3 md:px-4 py-3 text-right">Alokasi</th>
                    <th class="px-3 md:px-4 py-3 text-right">Sem. 1</th>
                    <th class="px-3 md:px-4 py-3 text-right">Sem. 2</th>
                    <th class="px-3 md:px-4 py-3 text-right">Total Real.</th>
                    <th class="px-3 md:px-4 py-3 text-right">%</th>
                    <th class="px-3 md:px-4 py-3 text-center">Sumber</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($laporan as $i => $item)
                @php
                    $sub      = $item->subKegiatan;
                    $program  = $sub?->kegiatan?->program;
                    $pd       = $program?->perangkatDaerah;
                    $strategi = $program?->strategi;
                    $persen   = $item->persentase_realisasi;
                    $pBadge   = $persen >= 80 ? 'bg-green-100 text-green-700' : ($persen >= 50 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                    $sBadge   = $strategi?->color === 'blue' ? 'bg-blue-100 text-blue-700' : ($strategi?->color === 'green' ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700');
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-3 md:px-4 py-2.5 text-gray-500">{{ $laporan->firstItem() + $i }}</td>
                    <td class="px-3 md:px-4 py-2.5 max-w-[200px]">
                        <p class="font-medium text-gray-800 line-clamp-2">{{ $sub?->nama_sub_kegiatan ?? '-' }}</p>
                        @if($sub?->kode)
                            <p class="text-gray-400 mt-0.5">{{ $sub->kode }}</p>
                        @endif
                    </td>
                    <td class="px-3 md:px-4 py-2.5 text-gray-600 max-w-[150px]">
                        <span class="line-clamp-2">{{ $pd?->nama ?? '-' }}</span>
                    </td>
                    <td class="px-3 md:px-4 py-2.5">
                        @if($strategi)
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $sBadge }}">
                            {{ $strategi->kode }}
                        </span>
                        @endif
                    </td>
                    <td class="px-3 md:px-4 py-2.5 text-right font-medium text-gray-700 whitespace-nowrap">
                        Rp {{ number_format($item->alokasi_anggaran, 0, ',', '.') }}
                    </td>
                    <td class="px-3 md:px-4 py-2.5 text-right text-purple-600 whitespace-nowrap">
                        Rp {{ number_format($item->realisasi_sem1, 0, ',', '.') }}
                    </td>
                    <td class="px-3 md:px-4 py-2.5 text-right text-blue-600 whitespace-nowrap">
                        Rp {{ number_format($item->realisasi_sem2, 0, ',', '.') }}
                    </td>
                    <td class="px-3 md:px-4 py-2.5 text-right font-bold text-green-700 whitespace-nowrap">
                        Rp {{ number_format($item->realisasi_total, 0, ',', '.') }}
                    </td>
                    <td class="px-3 md:px-4 py-2.5 text-right">
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold {{ $pBadge }}">{{ $persen }}%</span>
                    </td>
                    <td class="px-3 md:px-4 py-2.5 text-center">
                        <span class="inline-block px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs">
                            {{ $item->sumber_pembiayaan ?? 'APBD' }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-4 py-12 text-center text-gray-400">
                        <i class="fas fa-inbox text-3xl mb-2 block"></i>
                        Tidak ada data laporan untuk tahun {{ $tahun }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($laporan->hasPages())
    <div class="px-4 md:px-6 py-4 border-t">
        {{ $laporan->links() }}
    </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
var rptTahunItems = (function() {
    var a = [], cur = new Date().getFullYear();
    for (var y = cur + 1; y >= 2023; y--) a.push({ value: String(y), label: String(y) });
    return a;
})();

var rptStrategiItems = [{ value: '', label: 'Semua Strategi' }].concat(
    @json(collect($filterOptions['strategi'] ?? [])->map(fn($s) => ['value' => $s->id, 'label' => $s->nama])->values())
);

var rptPdItems = [{ value: '', label: 'Semua Perangkat' }].concat(
    @json(collect($filterOptions['perangkat_daerah'] ?? [])->map(fn($pd) => ['value' => $pd->id, 'label' => $pd->nama])->values())
);

function rptOpenTahun() {
    gpOpen({
        title: 'Pilih Tahun',
        targetId: 'rpt-tahun',
        items: rptTahunItems,
        showSearch: false,
        onSelect: function (item) {
            document.getElementById('rpt-tahun').value = item.value;
            document.getElementById('rpt-lbl-tahun').textContent = item.label;
        }
    });
}

function rptOpenStrategi() {
    gpOpen({
        title: 'Pilih Strategi',
        targetId: 'rpt-strategi',
        items: rptStrategiItems,
        showSearch: false,
        onSelect: function (item) {
            document.getElementById('rpt-strategi').value = item.value;
            var lbl = document.getElementById('rpt-lbl-strategi');
            lbl.textContent = item.label;
            lbl.style.color = item.value ? '#374151' : '#9ca3af';
        }
    });
}

function rptOpenPerangkat() {
    gpOpen({
        title: 'Pilih Perangkat Daerah',
        targetId: 'rpt-perangkat',
        items: rptPdItems,
        showSearch: rptPdItems.length > 6,
        onSelect: function (item) {
            document.getElementById('rpt-perangkat').value = item.value;
            var lbl = document.getElementById('rpt-lbl-perangkat');
            lbl.textContent = item.label;
            lbl.style.color = item.value ? '#374151' : '#9ca3af';
        }
    });
}
</script>
@endpush
