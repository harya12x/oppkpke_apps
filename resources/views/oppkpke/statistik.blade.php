@extends('layouts.oppkpke')

@section('title', 'Statistik')
@section('page-title', 'Statistik Realisasi')
@section('page-subtitle', 'Tahun ' . $tahun)

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@section('content')

{{-- Filter Tahun --}}
<div class="mb-4 md:mb-6">
    <form method="GET" action="{{ route('oppkpke.statistik') }}" class="inline-flex items-center gap-2">
        <label class="text-sm font-medium text-gray-600">Tahun:</label>
        <select name="tahun" onchange="this.form.submit()"
                class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 focus:ring-2 focus:ring-blue-500">
            @for($y = date('Y') + 1; $y >= 2023; $y--)
                <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endfor
        </select>
    </form>
</div>

{{-- Total Summary — from grandTotals (direct DB sum, same source as dashboard/report) --}}
@php
    $totalAlokasi   = $grandTotals['alokasi'];
    $totalRealisasi = $grandTotals['realisasi'];
    $totalPersen    = $totalAlokasi > 0 ? round(($totalRealisasi / $totalAlokasi) * 100, 1) : 0;
    $persenColor    = $totalPersen >= 80 ? 'text-green-600' : ($totalPersen >= 50 ? 'text-yellow-600' : 'text-red-600');
    $persenBar      = $totalPersen >= 80 ? 'bg-green-500'   : ($totalPersen >= 50 ? 'bg-yellow-500'   : 'bg-red-500');
@endphp

<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 md:gap-4 mb-4 md:mb-6">
    <div class="bg-white rounded-xl p-4 md:p-5 shadow-sm border">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Total Alokasi {{ $tahun }}</p>
        <p class="text-xl md:text-2xl font-bold text-blue-600 mt-1 break-all">
            Rp {{ number_format($totalAlokasi, 0, ',', '.') }}
        </p>
    </div>
    <div class="bg-white rounded-xl p-4 md:p-5 shadow-sm border">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Total Realisasi {{ $tahun }}</p>
        <p class="text-xl md:text-2xl font-bold text-green-600 mt-1 break-all">
            Rp {{ number_format($totalRealisasi, 0, ',', '.') }}
        </p>
    </div>
    <div class="bg-white rounded-xl p-4 md:p-5 shadow-sm border">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Persentase Realisasi</p>
        <div class="flex items-end gap-2 mt-1">
            <p class="text-xl md:text-2xl font-bold {{ $persenColor }}">{{ $totalPersen }}%</p>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
            <div class="h-2 rounded-full {{ $persenBar }}" style="width: {{ min($totalPersen, 100) }}%"></div>
        </div>
    </div>
</div>

{{-- Tabel + Chart --}}
<div class="grid grid-cols-1 xl:grid-cols-2 gap-4 md:gap-6">

    <div class="bg-white rounded-xl shadow-sm border">
        <div class="px-4 md:px-6 py-3 md:py-4 border-b">
            <h3 class="font-semibold text-gray-800 text-sm md:text-base">Rekap Per Strategi</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[400px]">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-4 md:px-5 py-3 text-gray-600 font-medium text-xs">Strategi</th>
                        <th class="text-right px-4 md:px-5 py-3 text-gray-600 font-medium text-xs">Alokasi</th>
                        <th class="text-right px-4 md:px-5 py-3 text-gray-600 font-medium text-xs">Realisasi</th>
                        <th class="text-right px-4 md:px-5 py-3 text-gray-600 font-medium text-xs">%</th>
                        <th class="text-right px-4 md:px-5 py-3 text-gray-600 font-medium text-xs">Lap.</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($stats as $s)
                    @php
                        $dotColor = $s['strategi']->color === 'blue' ? 'bg-blue-500' : ($s['strategi']->color === 'green' ? 'bg-green-500' : 'bg-orange-500');
                        $pBadge   = $s['persen'] >= 80 ? 'bg-green-100 text-green-700' : ($s['persen'] >= 50 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 md:px-5 py-2.5">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full inline-block flex-shrink-0 {{ $dotColor }}"></span>
                                <span class="text-gray-700 font-medium text-xs md:text-sm">{{ Str::limit($s['strategi']->nama, 30) }}</span>
                            </div>
                        </td>
                        <td class="px-4 md:px-5 py-2.5 text-right text-gray-600 text-xs">
                            Rp {{ number_format($s['alokasi'], 0, ',', '.') }}
                        </td>
                        <td class="px-4 md:px-5 py-2.5 text-right text-green-700 font-medium text-xs">
                            Rp {{ number_format($s['realisasi'], 0, ',', '.') }}
                        </td>
                        <td class="px-4 md:px-5 py-2.5 text-right">
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold {{ $pBadge }}">
                                {{ $s['persen'] }}%
                            </span>
                        </td>
                        <td class="px-4 md:px-5 py-2.5 text-right text-gray-500 text-xs">{{ $s['jumlah'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 border-t-2">
                    @php $totalBadge = $totalPersen >= 80 ? 'bg-green-100 text-green-700' : ($totalPersen >= 50 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); @endphp
                    <tr>
                        <td class="px-4 md:px-5 py-3 font-bold text-gray-700 text-xs md:text-sm">Total</td>
                        <td class="px-4 md:px-5 py-3 text-right font-bold text-gray-700 text-xs">Rp {{ number_format($totalAlokasi, 0, ',', '.') }}</td>
                        <td class="px-4 md:px-5 py-3 text-right font-bold text-green-700 text-xs">Rp {{ number_format($totalRealisasi, 0, ',', '.') }}</td>
                        <td class="px-4 md:px-5 py-3 text-right">
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold {{ $totalBadge }}">{{ $totalPersen }}%</span>
                        </td>
                        <td class="px-4 md:px-5 py-3 text-right font-bold text-gray-700 text-xs">{{ collect($stats)->sum('jumlah') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-4 md:p-6">
        <h3 class="font-semibold text-gray-800 mb-3 text-sm md:text-base">Grafik Alokasi vs Realisasi</h3>
        <div class="h-64 md:h-72">
            <canvas id="chartStrategi"></canvas>
        </div>
    </div>
</div>

{{-- Progress bars per strategi --}}
<div class="mt-4 md:mt-6 bg-white rounded-xl shadow-sm border p-4 md:p-6">
    <h3 class="font-semibold text-gray-800 mb-4 text-sm md:text-base">Progress Realisasi per Strategi</h3>
    <div class="space-y-4 md:space-y-5">
        @foreach($stats as $s)
        @php
            $colorMap = ['blue' => 'bg-blue-500', 'green' => 'bg-green-500', 'orange' => 'bg-orange-500'];
            $barColor = $colorMap[$s['strategi']->color] ?? 'bg-blue-500';
            $txtColor = $s['persen'] >= 80 ? 'text-green-600' : ($s['persen'] >= 50 ? 'text-yellow-600' : 'text-red-600');
        @endphp
        <div>
            <div class="flex justify-between items-center mb-1.5 gap-2">
                <span class="text-xs md:text-sm font-medium text-gray-700 truncate">{{ $s['strategi']->nama }}</span>
                <span class="text-xs md:text-sm font-bold {{ $txtColor }} flex-shrink-0">{{ $s['persen'] }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="{{ $barColor }} h-3 rounded-full transition-all duration-700"
                     style="width: {{ min($s['persen'], 100) }}%"></div>
            </div>
            <div class="flex flex-wrap justify-between text-xs text-gray-500 mt-1 gap-2">
                <span>Realisasi: Rp {{ number_format($s['realisasi'], 0, ',', '.') }}</span>
                <span>Alokasi: Rp {{ number_format($s['alokasi'], 0, ',', '.') }}</span>
            </div>
        </div>
        @endforeach
    </div>
</div>

@endsection

@push('scripts')
<script>
var statNama    = @json(collect($stats)->pluck('strategi')->pluck('nama'));
var statAlokasi = @json(collect($stats)->pluck('alokasi'));
var statReal    = @json(collect($stats)->pluck('realisasi'));

// Short labels for axis (first 3 words max)
var shortLabels = statNama.map(function(n) {
    var words = n.split(' ');
    return words.slice(0, 3).join(' ') + (words.length > 3 ? '…' : '');
});

var fmtRp = function(v) {
    var n = parseFloat(v) || 0;
    if (n >= 1e9)  return 'Rp ' + (n / 1e9).toFixed(1) + ' M';
    if (n >= 1e6)  return 'Rp ' + (n / 1e6).toFixed(1) + ' Jt';
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(n);
};

new Chart(document.getElementById('chartStrategi'), {
    type: 'bar',
    data: {
        labels: shortLabels,
        datasets: [
            {
                label: 'Alokasi',
                data: statAlokasi,
                backgroundColor: 'rgba(59,130,246,0.75)',
                borderColor: '#3b82f6',
                borderWidth: 1,
                borderRadius: 6,
                barPercentage: 0.6
            },
            {
                label: 'Realisasi',
                data: statReal,
                backgroundColor: 'rgba(34,197,94,0.75)',
                borderColor: '#22c55e',
                borderWidth: 1,
                borderRadius: 6,
                barPercentage: 0.6
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { bottom: 8 } },
        plugins: {
            legend: {
                position: 'top',
                labels: { boxWidth: 12, boxHeight: 10, font: { size: 11 }, padding: 16 }
            },
            tooltip: {
                callbacks: {
                    title: function(items) { return statNama[items[0].dataIndex] || ''; },
                    label: function(ctx) {
                        return ctx.dataset.label + ': ' + fmtRp(ctx.parsed.y);
                    }
                }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: {
                    font: { size: 10 },
                    maxRotation: 0,
                    minRotation: 0,
                    autoSkip: false
                }
            },
            y: {
                beginAtZero: true,
                ticks: {
                    font: { size: 10 },
                    callback: function(v) { return fmtRp(v); },
                    maxTicksLimit: 6
                }
            }
        }
    }
});
</script>
@endpush
