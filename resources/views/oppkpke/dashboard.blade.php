{{-- resources/views/oppkpke/dashboard.blade.php --}}
@extends('layouts.oppkpke')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard OPPKPKE')
@section('page-subtitle', 'Laporan Optimalisasi Pelaksanaan Pengentasan Kemiskinan')

@section('content')
<div class="space-y-4 md:space-y-6">

    {{-- ── SUMMARY CARDS ──────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-5">

        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 md:p-6 text-white shadow-lg">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-blue-100 text-xs md:text-sm">Total Alokasi</p>
                    <p class="text-xl md:text-3xl font-bold mt-1">
                        Rp {{ number_format(($stats['total_anggaran'] ?? 0) / 1000000000, 2, ',', '.') }} M
                    </p>
                </div>
                <div class="w-10 h-10 md:w-14 md:h-14 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-wallet text-lg md:text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-4 md:p-6 text-white shadow-lg">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-green-100 text-xs md:text-sm">Total Realisasi</p>
                    <p class="text-xl md:text-3xl font-bold mt-1">
                        Rp {{ number_format(($stats['total_realisasi'] ?? 0) / 1000000000, 2, ',', '.') }} M
                    </p>
                </div>
                <div class="w-10 h-10 md:w-14 md:h-14 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-chart-line text-lg md:text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-4 md:p-6 text-white shadow-lg">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-purple-100 text-xs md:text-sm">% Realisasi</p>
                    <p class="text-xl md:text-3xl font-bold mt-1">{{ $stats['persentase_realisasi'] ?? 0 }}%</p>
                    <div class="w-full bg-white/30 rounded-full h-1.5 mt-2">
                        <div class="bg-white h-1.5 rounded-full" style="width: {{ min($stats['persentase_realisasi'] ?? 0, 100) }}%"></div>
                    </div>
                </div>
                <div class="w-10 h-10 md:w-14 md:h-14 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-percent text-lg md:text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-4 md:p-6 text-white shadow-lg">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-orange-100 text-xs md:text-sm">Sub Kegiatan</p>
                    <p class="text-xl md:text-3xl font-bold mt-1">{{ number_format($stats['total_sub_kegiatan'] ?? 0) }}</p>
                </div>
                <div class="w-10 h-10 md:w-14 md:h-14 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-list-check text-lg md:text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CHARTS ──────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
        <div class="bg-white rounded-xl shadow-sm border p-4 md:p-6">
            <h4 class="font-semibold text-gray-800 mb-3 text-sm md:text-base">
                <i class="fas fa-chart-bar mr-2 text-blue-500"></i>Alokasi vs Realisasi per Strategi
            </h4>
            <div class="h-56 md:h-72">
                <canvas id="chartStrategiComparison"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-4 md:p-6">
            <h4 class="font-semibold text-gray-800 mb-3 text-sm md:text-base">
                <i class="fas fa-chart-pie mr-2 text-green-500"></i>Distribusi Alokasi Anggaran
            </h4>
            <div class="h-56 md:h-72">
                <canvas id="chartAlokasiPie"></canvas>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-4 md:p-6">
        <h4 class="font-semibold text-gray-800 mb-3 text-sm md:text-base">
            <i class="fas fa-building mr-2 text-purple-500"></i>Top 10 Realisasi per Perangkat Daerah
        </h4>
        <div class="h-64 md:h-80">
            <canvas id="chartPerangkatDaerah"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
        <div class="bg-white rounded-xl shadow-sm border p-4 md:p-6">
            <h4 class="font-semibold text-gray-800 mb-3 text-sm md:text-base">
                <i class="fas fa-calendar mr-2 text-orange-500"></i>Realisasi Semester 1 vs Semester 2
            </h4>
            <div class="h-56 md:h-72">
                <canvas id="chartSemester"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-4 md:p-6">
            <h4 class="font-semibold text-gray-800 mb-3 text-sm md:text-base">
                <i class="fas fa-tasks mr-2 text-teal-500"></i>Progress Realisasi per Strategi
            </h4>
            <div class="space-y-4 mt-4">
                @foreach($stats['per_strategi'] ?? [] as $strategi)
                @php
                    $persen     = $strategi['persentase'] ?? 0;
                    $colorClass = $persen >= 80 ? 'bg-green-500' : ($persen >= 50 ? 'bg-yellow-500' : 'bg-red-500');
                    $textClass  = $persen >= 80 ? 'text-green-600' : ($persen >= 50 ? 'text-yellow-600' : 'text-red-600');
                @endphp
                <div>
                    <div class="flex justify-between mb-1 gap-2">
                        <span class="text-xs md:text-sm font-medium text-gray-700 truncate">{{ Str::limit($strategi['nama'], 35) }}</span>
                        <span class="text-xs md:text-sm font-bold {{ $textClass }} flex-shrink-0">{{ $persen }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="{{ $colorClass }} h-2.5 rounded-full transition-all" style="width: {{ min($persen, 100) }}%"></div>
                    </div>
                    <div class="flex justify-between mt-0.5 text-xs text-gray-500 gap-2">
                        <span class="truncate">Alokasi: Rp {{ number_format($strategi['alokasi'] / 1000000000, 2) }} M</span>
                        <span class="flex-shrink-0">{{ number_format($strategi['realisasi'] / 1000000000, 2) }} M</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── STRATEGI CARDS ──────────────────────────────────── --}}
    <h3 class="text-base md:text-lg font-semibold text-gray-800 flex items-center gap-2">
        <i class="fas fa-layer-group text-blue-500"></i> Program per Strategi
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
        @foreach($stats['per_strategi'] ?? [] as $strategi)
        @php $bgColor = ['1' => 'blue', '2' => 'green', '3' => 'orange'][$strategi['kode']] ?? 'blue'; @endphp
        <a href="{{ route('oppkpke.explorer', ['strategi_id' => $strategi['id'], 'tahun' => $tahun]) }}"
           class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-lg transition group block">
            <div class="flex items-start justify-between mb-3">
                <div class="w-12 h-12 bg-{{ $bgColor }}-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-{{ $strategi['icon'] ?? 'folder' }} text-{{ $bgColor }}-600 text-xl"></i>
                </div>
                <span class="text-xs bg-{{ $bgColor }}-100 text-{{ $bgColor }}-700 px-2.5 py-1 rounded-full">
                    {{ $strategi['total_program'] }} Program
                </span>
            </div>
            <h3 class="font-semibold text-gray-800 group-hover:text-blue-600 transition text-sm md:text-base">
                {{ $strategi['nama'] }}
            </h3>
            <div class="mt-3 space-y-1.5">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Alokasi</span>
                    <span class="font-medium">Rp {{ number_format($strategi['alokasi'] / 1000000000, 2) }} M</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Realisasi</span>
                    <span class="font-medium text-green-600">Rp {{ number_format($strategi['realisasi'] / 1000000000, 2) }} M</span>
                </div>
                <div class="mt-2">
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-gray-500">Progress</span>
                        <span>{{ $strategi['persentase'] }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-{{ $bgColor }}-500 h-2 rounded-full" style="width: {{ min($strategi['persentase'], 100) }}%"></div>
                    </div>
                </div>
            </div>
        </a>
        @endforeach
    </div>

    {{-- ── TABLE REKAP ─────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-4 md:px-6 py-4 border-b bg-gray-50">
            <h4 class="font-semibold text-gray-800 text-sm md:text-base">
                <i class="fas fa-table mr-2 text-blue-500"></i>Rekap Anggaran per Perangkat Daerah
            </h4>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[500px]">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 md:px-4 py-3 text-left font-medium text-gray-700 text-xs">No</th>
                        <th class="px-3 md:px-4 py-3 text-left font-medium text-gray-700 text-xs">Perangkat Daerah</th>
                        <th class="px-3 md:px-4 py-3 text-right font-medium text-gray-700 text-xs">Alokasi</th>
                        <th class="px-3 md:px-4 py-3 text-right font-medium text-gray-700 text-xs">Realisasi</th>
                        <th class="px-3 md:px-4 py-3 text-center font-medium text-gray-700 text-xs">Progress</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($rekapPerangkat ?? [] as $index => $item)
                    @php
                        $persen     = ($item['alokasi'] ?? 0) > 0 ? round((($item['realisasi'] ?? 0) / $item['alokasi']) * 100, 1) : 0;
                        $colorClass = $persen >= 80 ? 'bg-green-500' : ($persen >= 50 ? 'bg-yellow-500' : 'bg-red-500');
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 md:px-4 py-2.5 text-gray-500 text-xs">{{ $index + 1 }}</td>
                        <td class="px-3 md:px-4 py-2.5 font-medium text-xs md:text-sm">{{ $item['nama'] }}</td>
                        <td class="px-3 md:px-4 py-2.5 text-right font-mono text-xs">Rp {{ number_format($item['alokasi'], 0, ',', '.') }}</td>
                        <td class="px-3 md:px-4 py-2.5 text-right font-mono text-green-600 text-xs">Rp {{ number_format($item['realisasi'], 0, ',', '.') }}</td>
                        <td class="px-3 md:px-4 py-2.5">
                            <div class="flex items-center justify-center gap-1.5">
                                <div class="w-16 bg-gray-200 rounded-full h-1.5">
                                    <div class="{{ $colorClass }} h-1.5 rounded-full" style="width: {{ min($persen, 100) }}%"></div>
                                </div>
                                <span class="text-xs font-medium w-10">{{ $persen }}%</span>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── QUICK ACCESS ────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border p-4 md:p-6">
        <h3 class="font-semibold text-gray-800 mb-3 text-sm md:text-base">
            <i class="fas fa-rocket mr-2 text-blue-500"></i>Akses Cepat
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <a href="{{ route('oppkpke.explorer', ['tahun' => $tahun]) }}"
               class="flex flex-col items-center p-3 md:p-4 bg-gray-50 rounded-lg hover:bg-blue-50 transition">
                <i class="fas fa-search text-xl md:text-2xl text-blue-500 mb-1.5"></i>
                <span class="text-xs md:text-sm text-gray-700">Cari Data</span>
            </a>
            <a href="{{ route('oppkpke.laporan.index') }}"
               class="flex flex-col items-center p-3 md:p-4 bg-gray-50 rounded-lg hover:bg-green-50 transition">
                <i class="fas fa-edit text-xl md:text-2xl text-green-500 mb-1.5"></i>
                <span class="text-xs md:text-sm text-gray-700">Input Realisasi</span>
            </a>
            <a href="{{ route('oppkpke.export.excel', ['tahun' => $tahun]) }}"
               class="flex flex-col items-center p-3 md:p-4 bg-gray-50 rounded-lg hover:bg-emerald-50 transition">
                <i class="fas fa-file-excel text-xl md:text-2xl text-emerald-500 mb-1.5"></i>
                <span class="text-xs md:text-sm text-gray-700">Export Excel</span>
            </a>
            <a href="{{ route('oppkpke.export.pdf', ['tahun' => $tahun]) }}"
               class="flex flex-col items-center p-3 md:p-4 bg-gray-50 rounded-lg hover:bg-red-50 transition">
                <i class="fas fa-file-pdf text-xl md:text-2xl text-red-500 mb-1.5"></i>
                <span class="text-xs md:text-sm text-gray-700">Export PDF</span>
            </a>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
var statsPerStrategi = @json($stats['per_strategi'] ?? []);
var rekapPerangkat   = @json($rekapPerangkat ?? []);

var strategiLabels    = statsPerStrategi.map(function(s) { return s.nama.length > 22 ? s.nama.substring(0, 22) + '…' : s.nama; });
var strategiAlokasi   = statsPerStrategi.map(function(s) { return +(s.alokasi)   || 0; });
var strategiRealisasi = statsPerStrategi.map(function(s) { return +(s.realisasi) || 0; });
var strategiSem1      = statsPerStrategi.map(function(s) { return +(s.sem1)      || 0; });
var strategiSem2      = statsPerStrategi.map(function(s) { return +(s.sem2)      || 0; });

var c = {
    blue:   'rgba(59,130,246,0.8)',
    green:  'rgba(34,197,94,0.8)',
    orange: 'rgba(249,115,22,0.8)',
    purple: 'rgba(168,85,247,0.8)',
};

function fmtM(v) {
    var n = parseFloat(v);
    if (isNaN(n)) return '0.0 M';
    return (n / 1000000000).toFixed(1) + ' M';
}

function fmtRpM(v) {
    return 'Rp ' + fmtM(v);
}

var chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { labels: { boxWidth: 10, font: { size: 11 } } } },
};

// ── Alokasi vs Realisasi per Strategi ──────────────────────────────────
new Chart(document.getElementById('chartStrategiComparison'), {
    type: 'bar',
    data: {
        labels: strategiLabels,
        datasets: [
            { label: 'Alokasi',   data: strategiAlokasi,   backgroundColor: c.blue,  borderRadius: 4 },
            { label: 'Realisasi', data: strategiRealisasi, backgroundColor: c.green, borderRadius: 4 },
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { boxWidth: 10, font: { size: 11 } } },
            tooltip: { callbacks: { label: function(ctx) {
                return ctx.dataset.label + ': ' + fmtRpM(ctx.parsed.y);
            }}}
        },
        scales: { y: { beginAtZero: true, ticks: { callback: function(v) { return fmtM(v); }, font: { size: 10 } } } }
    }
});

// ── Distribusi Alokasi Anggaran (Doughnut) ─────────────────────────────
new Chart(document.getElementById('chartAlokasiPie'), {
    type: 'doughnut',
    data: {
        labels: strategiLabels,
        datasets: [{
            data: strategiAlokasi,
            backgroundColor: [c.blue, c.green, c.orange],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 10, padding: 10, font: { size: 10 } } },
            tooltip: { callbacks: { label: function(ctx) {
                var val   = parseFloat(ctx.parsed) || 0;
                var total = ctx.dataset.data.reduce(function(a, b) { return (parseFloat(a) || 0) + (parseFloat(b) || 0); }, 0);
                var pct   = total > 0 ? ((val / total) * 100).toFixed(1) : '0.0';
                return ctx.label + ': ' + fmtRpM(val) + ' (' + pct + '%)';
            }}}
        }
    }
});

// ── Top 10 Realisasi per Perangkat Daerah ─────────────────────────────
var pdSlice     = rekapPerangkat.slice(0, 10);
var pdLabels    = pdSlice.map(function(p) { return p.nama.length > 25 ? p.nama.substring(0, 25) + '…' : p.nama; });
var pdRealisasi = pdSlice.map(function(p) { return +(p.realisasi) || 0; });

new Chart(document.getElementById('chartPerangkatDaerah'), {
    type: 'bar',
    data: {
        labels: pdLabels,
        datasets: [{ label: 'Realisasi', data: pdRealisasi, backgroundColor: c.green, borderRadius: 4 }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: function(ctx) {
                return 'Realisasi: ' + fmtRpM(ctx.parsed.x);
            }}}
        },
        scales: { x: { beginAtZero: true, ticks: { callback: function(v) { return fmtM(v); }, font: { size: 10 } } } }
    }
});

// ── Realisasi Semester 1 vs Semester 2 ────────────────────────────────
new Chart(document.getElementById('chartSemester'), {
    type: 'bar',
    data: {
        labels: strategiLabels,
        datasets: [
            { label: 'Semester 1', data: strategiSem1, backgroundColor: c.blue,   borderRadius: 4 },
            { label: 'Semester 2', data: strategiSem2, backgroundColor: c.orange, borderRadius: 4 },
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { boxWidth: 10, font: { size: 11 } } },
            tooltip: { callbacks: { label: function(ctx) {
                return ctx.dataset.label + ': ' + fmtRpM(ctx.parsed.y);
            }}}
        },
        scales: { y: { beginAtZero: true, ticks: { callback: function(v) { return fmtM(v); }, font: { size: 10 } } } }
    }
});
</script>
@endpush
