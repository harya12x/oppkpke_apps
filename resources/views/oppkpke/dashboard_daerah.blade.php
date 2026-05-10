@extends('layouts.oppkpke')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', ($pd->singkatan ?? $pd->nama ?? 'Perangkat Daerah') . ' · Tahun ' . $tahun)

@section('content')
<div class="space-y-4 md:space-y-5">

    {{-- ── Filter Tahun ────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-building text-blue-600"></i>
            </div>
            <div class="min-w-0">
                <p class="font-semibold text-gray-800 text-sm md:text-base truncate">{{ $pd->nama ?? 'Perangkat Daerah' }}</p>
                <p class="text-xs text-gray-500">Operator Daerah &middot; Data Realisasi {{ $tahun }}</p>
            </div>
        </div>
        <form method="GET" action="{{ route('oppkpke.dashboard') }}">
            <div class="flex items-center gap-2">
                <label class="text-xs text-gray-600 font-medium">Tahun:</label>
                <select name="tahun" onchange="this.form.submit()"
                        class="rounded-lg border border-gray-300 text-sm px-3 py-1.5 focus:ring-2 focus:ring-blue-500">
                    @for($y = date('Y') + 1; $y >= 2023; $y--)
                        <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
        </form>
    </div>

    {{-- ── Summary Cards ───────────────────────────────────────────── --}}
    @php
        $persenColor = $stats['persen'] >= 80 ? 'from-green-500 to-green-600' : ($stats['persen'] >= 50 ? 'from-yellow-500 to-yellow-600' : 'from-red-500 to-red-600');
        $progressPct = min($stats['persen'], 100);
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">

        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 md:p-5 text-white shadow-sm">
            <p class="text-blue-100 text-xs">Total Alokasi</p>
            <p class="text-lg md:text-2xl font-bold mt-1">
                Rp {{ number_format($stats['totalAlokasi'] / 1000000, 1, ',', '.') }} Jt
            </p>
            <p class="text-blue-200 text-xs mt-1">Tahun {{ $tahun }}</p>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-4 md:p-5 text-white shadow-sm">
            <p class="text-green-100 text-xs">Total Realisasi</p>
            <p class="text-lg md:text-2xl font-bold mt-1">
                Rp {{ number_format($stats['totalRealisasi'] / 1000000, 1, ',', '.') }} Jt
            </p>
            <p class="text-green-200 text-xs mt-1">Sem1 + Sem2</p>
        </div>

        <div class="bg-gradient-to-br {{ $persenColor }} rounded-xl p-4 md:p-5 text-white shadow-sm">
            <p class="text-white/80 text-xs">% Realisasi</p>
            <p class="text-lg md:text-2xl font-bold mt-1">{{ $stats['persen'] }}%</p>
            <div class="w-full bg-white/30 rounded-full h-1.5 mt-2">
                <div class="bg-white h-1.5 rounded-full" style="width: {{ $progressPct }}%"></div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-4 md:p-5 text-white shadow-sm">
            <p class="text-orange-100 text-xs">Sub Kegiatan Terisi</p>
            <p class="text-lg md:text-2xl font-bold mt-1">{{ $stats['terisi'] }} / {{ $stats['total'] }}</p>
            <p class="text-orange-200 text-xs mt-1">
                {{ $stats['total'] - $stats['terisi'] }} belum diisi
            </p>
        </div>
    </div>

    {{-- ── Pie Chart + Per Strategi ────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Pie Chart --}}
        <div class="bg-white rounded-xl shadow-sm border p-4 md:p-5">
            <h4 class="font-semibold text-gray-800 mb-3 text-sm md:text-base flex items-center gap-2">
                <i class="fas fa-chart-pie text-blue-500"></i> Distribusi Alokasi per Strategi
            </h4>
            <div class="h-52 md:h-60">
                <canvas id="chartPie"></canvas>
            </div>
        </div>

        {{-- Per Strategi Rekap --}}
        <div class="bg-white rounded-xl shadow-sm border p-4 md:p-5">
            <h4 class="font-semibold text-gray-800 mb-3 text-sm md:text-base flex items-center gap-2">
                <i class="fas fa-layer-group text-green-500"></i> Realisasi per Strategi
            </h4>
            <div class="space-y-3 mt-2">
                @foreach($perStrategi as $s)
                @php
                    $sPersen = $s['alokasi'] > 0 ? round(($s['realisasi'] / $s['alokasi']) * 100, 1) : 0;
                    $sColor  = $sPersen >= 80 ? 'bg-green-500' : ($sPersen >= 50 ? 'bg-yellow-500' : 'bg-red-400');
                    $sTxt    = $sPersen >= 80 ? 'text-green-600' : ($sPersen >= 50 ? 'text-yellow-600' : 'text-red-500');
                @endphp
                <div>
                    <div class="flex justify-between items-center mb-1 gap-2">
                        <span class="text-xs font-medium text-gray-700 truncate">{{ $s['nama'] }}</span>
                        <span class="text-xs font-bold {{ $sTxt }} flex-shrink-0">{{ $sPersen }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="{{ $sColor }} h-2 rounded-full transition-all" style="width: {{ min($sPersen, 100) }}%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-400 mt-0.5">
                        <span>Rp {{ number_format($s['alokasi'] / 1000000, 1) }} Jt</span>
                        <span class="text-green-600">Rp {{ number_format($s['realisasi'] / 1000000, 1) }} Jt</span>
                    </div>
                </div>
                @endforeach

                @if($perStrategi->isEmpty())
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2 block"></i>
                    <p class="text-sm">Belum ada data realisasi</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Ranking per Perangkat Daerah ─────────────────────────────── --}}
    @php
        $myRankIdx  = $ranking->search(fn($r) => $r['is_self']);
        $myRank     = $myRankIdx !== false ? $myRankIdx + 1 : null;
        $totalRanked = $ranking->count();

        // Show top 10; if self is outside top 10, append separator + self row
        $showTop   = $ranking->take(10);
        $selfOutside = $myRank && $myRank > 10;
        $selfRow   = $selfOutside ? $ranking->firstWhere('is_self', true) : null;
    @endphp
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">

        {{-- Header --}}
        <div class="px-4 md:px-5 py-3 md:py-4 border-b bg-gradient-to-r from-amber-500 to-orange-500">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="font-semibold text-white text-sm md:text-base flex items-center gap-2">
                        <i class="fas fa-trophy"></i> Ranking Realisasi Anggaran {{ $tahun }}
                    </h3>
                    <p class="text-amber-100 text-xs mt-0.5">
                        {{ $totalRanked }} perangkat daerah memiliki data &middot; urut berdasarkan % realisasi
                    </p>
                </div>
                @if($myRank)
                <div class="flex-shrink-0 text-right bg-white/15 rounded-xl px-3 py-2">
                    <p class="text-[10px] text-amber-100 leading-none mb-0.5">Posisi Anda</p>
                    <p class="text-xl font-extrabold text-white leading-none">#{{ $myRank }}</p>
                    <p class="text-[10px] text-amber-200 leading-none mt-0.5">dari {{ $totalRanked }}</p>
                </div>
                @elseif(!$ranking->isEmpty())
                <div class="flex-shrink-0 text-right bg-white/10 rounded-xl px-3 py-2">
                    <p class="text-[10px] text-amber-200 leading-none mb-0.5">Posisi Anda</p>
                    <p class="text-sm font-semibold text-white/60 leading-none">Belum Ada Data</p>
                </div>
                @endif
            </div>
        </div>

        @if($ranking->isEmpty())
        {{-- Empty state --}}
        <div class="py-12 text-center text-gray-400">
            <i class="fas fa-chart-bar text-4xl mb-3 block text-gray-200"></i>
            <p class="font-medium">Belum ada perangkat daerah dengan data anggaran</p>
            <p class="text-sm mt-1 text-gray-400">Data akan muncul setelah laporan diinput</p>
        </div>
        @else

        {{-- Legenda warna --}}
        <div class="flex items-center gap-4 px-4 md:px-5 py-2 bg-gray-50 border-b text-xs text-gray-500">
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-green-500 inline-block"></span> ≥ 80%</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-yellow-500 inline-block"></span> 50–79%</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-red-400 inline-block"></span> &lt; 50%</span>
        </div>

        <div class="divide-y divide-gray-100">

            {{-- Top 10 rows --}}
            @foreach($showTop as $rank => $r)
            @php
                $isSelf   = $r['is_self'];
                $persen   = $r['persen'];
                $barColor = $persen >= 80 ? 'bg-green-500' : ($persen >= 50 ? 'bg-yellow-500' : 'bg-red-400');
                $txtColor = $persen >= 80 ? 'text-green-600 bg-green-50' : ($persen >= 50 ? 'text-yellow-700 bg-yellow-50' : 'text-red-600 bg-red-50');
                $pos      = $rank + 1;
            @endphp
            <div class="flex items-center gap-3 px-4 md:px-5 py-2.5
                        {{ $isSelf ? 'bg-blue-50 border-l-4 border-blue-500' : 'hover:bg-gray-50' }} transition">

                {{-- Rank --}}
                <div class="w-7 flex-shrink-0 text-center">
                    @if($pos === 1)
                        <span class="text-base">🥇</span>
                    @elseif($pos === 2)
                        <span class="text-base">🥈</span>
                    @elseif($pos === 3)
                        <span class="text-base">🥉</span>
                    @else
                        <span class="text-xs font-bold {{ $isSelf ? 'text-blue-600' : 'text-gray-400' }}">#{{ $pos }}</span>
                    @endif
                </div>

                {{-- Name + bar --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 mb-1">
                        <p class="text-xs md:text-sm font-medium truncate {{ $isSelf ? 'text-blue-800 font-bold' : 'text-gray-700' }}"
                           title="{{ $r['nama_full'] }}">
                            {{ $r['nama'] }}
                        </p>
                        @if($isSelf)
                        <span class="flex-shrink-0 text-[9px] bg-blue-600 text-white px-1.5 py-0.5 rounded-full font-bold tracking-wide">ANDA</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-gray-200 rounded-full h-1.5">
                            <div class="{{ $barColor }} h-1.5 rounded-full transition-all duration-700"
                                 style="width: {{ min($persen, 100) }}%"></div>
                        </div>
                        <span class="text-[10px] text-gray-400 flex-shrink-0 font-mono">
                            {{ number_format($r['realisasi'] / 1_000_000, 1) }} / {{ number_format($r['alokasi'] / 1_000_000, 1) }} Jt
                        </span>
                    </div>
                </div>

                {{-- Persen badge --}}
                <div class="flex-shrink-0">
                    <span class="inline-block text-xs font-bold px-2 py-0.5 rounded-full {{ $txtColor }}">
                        {{ $persen }}%
                    </span>
                </div>
            </div>
            @endforeach

            {{-- Self outside top 10: show separator + self row --}}
            @if($selfOutside && $selfRow)
            @php
                $persen   = $selfRow['persen'];
                $barColor = $persen >= 80 ? 'bg-green-500' : ($persen >= 50 ? 'bg-yellow-500' : 'bg-red-400');
                $txtColor = $persen >= 80 ? 'text-green-600 bg-green-50' : ($persen >= 50 ? 'text-yellow-700 bg-yellow-50' : 'text-red-600 bg-red-50');
            @endphp
            <div class="flex items-center gap-2 px-4 md:px-5 py-1.5 bg-gray-50">
                <div class="flex-1 border-t border-dashed border-gray-300"></div>
                <span class="text-[10px] text-gray-400 flex-shrink-0">posisi Anda</span>
                <div class="flex-1 border-t border-dashed border-gray-300"></div>
            </div>
            <div class="flex items-center gap-3 px-4 md:px-5 py-2.5 bg-blue-50 border-l-4 border-blue-500">
                <div class="w-7 flex-shrink-0 text-center">
                    <span class="text-xs font-bold text-blue-600">#{{ $myRank }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 mb-1">
                        <p class="text-xs md:text-sm font-bold text-blue-800 truncate" title="{{ $selfRow['nama_full'] }}">
                            {{ $selfRow['nama'] }}
                        </p>
                        <span class="flex-shrink-0 text-[9px] bg-blue-600 text-white px-1.5 py-0.5 rounded-full font-bold tracking-wide">ANDA</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-gray-200 rounded-full h-1.5">
                            <div class="{{ $barColor }} h-1.5 rounded-full" style="width: {{ min($persen, 100) }}%"></div>
                        </div>
                        <span class="text-[10px] text-gray-400 flex-shrink-0 font-mono">
                            {{ number_format($selfRow['realisasi'] / 1_000_000, 1) }} / {{ number_format($selfRow['alokasi'] / 1_000_000, 1) }} Jt
                        </span>
                    </div>
                </div>
                <div class="flex-shrink-0">
                    <span class="inline-block text-xs font-bold px-2 py-0.5 rounded-full {{ $txtColor }}">{{ $persen }}%</span>
                </div>
            </div>
            @endif

            {{-- Show more indicator if there are more than 10 --}}
            @if($totalRanked > 10)
            <div class="px-4 md:px-5 py-2 text-center">
                <span class="text-xs text-gray-400">
                    Menampilkan 10 dari {{ $totalRanked }} perangkat daerah
                    @if($selfOutside) · posisi Anda #{{ $myRank }} @endif
                </span>
            </div>
            @endif

        </div>
        @endif
    </div>

    {{-- ── Matriks RAT ─────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-4 md:px-5 py-3 md:py-4 border-b flex flex-wrap items-center justify-between gap-2 bg-gradient-to-r from-blue-700 to-blue-600">
            <div>
                <h3 class="font-semibold text-white text-sm md:text-base flex items-center gap-2">
                    <i class="fas fa-table-list"></i>
                    Matriks Rencana Aksi Tahunan (RAT)
                </h3>
                <p class="text-blue-200 text-xs mt-0.5">Klik baris untuk langsung input / edit realisasi</p>
            </div>
            <div class="flex items-center gap-3 text-xs text-white/80">
                <span><span class="inline-block w-2 h-2 rounded-full bg-green-400 mr-1"></span>≥ 80%</span>
                <span><span class="inline-block w-2 h-2 rounded-full bg-yellow-400 mr-1"></span>≥ 50%</span>
                <span><span class="inline-block w-2 h-2 rounded-full bg-red-400 mr-1"></span>&lt; 50%</span>
                <span><span class="inline-block w-2 h-2 rounded-full bg-gray-400 mr-1"></span>Kosong</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs min-w-[780px]">
                <thead class="bg-gray-50 text-gray-600 uppercase tracking-wide border-b">
                    <tr>
                        <th class="px-3 py-2.5 text-left w-8">No</th>
                        <th class="px-3 py-2.5 text-left">Sub Kegiatan</th>
                        <th class="px-3 py-2.5 text-left">Program / Kegiatan</th>
                        <th class="px-3 py-2.5 text-right">Alokasi</th>
                        <th class="px-3 py-2.5 text-right">Sem. 1</th>
                        <th class="px-3 py-2.5 text-right">Sem. 2</th>
                        <th class="px-3 py-2.5 text-right">Total Real.</th>
                        <th class="px-3 py-2.5 text-center w-16">%</th>
                        <th class="px-3 py-2.5 text-center">Status</th>
                        <th class="px-3 py-2.5 text-center w-12">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($subKegiatan as $i => $sk)
                    @php
                        $lap      = $sk->laporan->first();
                        $alokasi  = (float) optional($lap)->alokasi_anggaran;
                        $sem1     = (float) optional($lap)->realisasi_sem1;
                        $sem2     = (float) optional($lap)->realisasi_sem2;
                        $totReal  = (float) optional($lap)->realisasi_total;
                        $prsn     = $alokasi > 0 ? round(($totReal / $alokasi) * 100, 1) : 0;
                        $kosong   = !$lap || $alokasi == 0;

                        if ($kosong) {
                            $rowBorder = 'border-l-4 border-l-gray-300';
                            $badgeCls  = 'bg-gray-100 text-gray-500';
                            $statusCls = 'bg-orange-50 text-orange-600';
                            $statusTxt = 'Belum Diisi';
                        } elseif ($prsn >= 80) {
                            $rowBorder = 'border-l-4 border-l-green-400';
                            $badgeCls  = 'bg-green-100 text-green-700';
                            $statusCls = 'bg-green-50 text-green-700';
                            $statusTxt = 'Sesuai Target';
                        } elseif ($prsn >= 50) {
                            $rowBorder = 'border-l-4 border-l-yellow-400';
                            $badgeCls  = 'bg-yellow-100 text-yellow-700';
                            $statusCls = 'bg-yellow-50 text-yellow-700';
                            $statusTxt = 'On Progress';
                        } else {
                            $rowBorder = 'border-l-4 border-l-red-400';
                            $badgeCls  = 'bg-red-100 text-red-700';
                            $statusCls = 'bg-red-50 text-red-600';
                            $statusTxt = 'Perlu Perhatian';
                        }
                    @endphp
                    <tr class="hover:bg-blue-50 cursor-pointer transition {{ $rowBorder }}"
                        onclick="goToInput({{ $sk->id }})">
                        <td class="px-3 py-2.5 text-gray-400">{{ $i + 1 }}</td>
                        <td class="px-3 py-2.5 max-w-[200px]">
                            <p class="font-medium text-gray-800 line-clamp-2">{{ $sk->nama_sub_kegiatan }}</p>
                            @if($sk->kode)
                                <p class="text-gray-400 text-[10px] mt-0.5">{{ $sk->kode }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 max-w-[180px]">
                            <p class="text-gray-600 truncate">{{ Str::limit($sk->kegiatan?->program?->nama_program, 30) }}</p>
                            <p class="text-gray-400 text-[10px] truncate mt-0.5">{{ Str::limit($sk->kegiatan?->nama_kegiatan, 30) }}</p>
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono text-gray-700">
                            {{ $kosong ? '—' : 'Rp ' . number_format($alokasi, 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono text-purple-600">
                            {{ $sem1 > 0 ? 'Rp ' . number_format($sem1, 0, ',', '.') : '—' }}
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono text-blue-600">
                            {{ $sem2 > 0 ? 'Rp ' . number_format($sem2, 0, ',', '.') : '—' }}
                        </td>
                        <td class="px-3 py-2.5 text-right font-bold font-mono text-green-700">
                            {{ $totReal > 0 ? 'Rp ' . number_format($totReal, 0, ',', '.') : '—' }}
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="inline-block px-2 py-0.5 rounded-full font-semibold {{ $badgeCls }}">
                                {{ $kosong ? 'N/A' : $prsn . '%' }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-medium {{ $statusCls }}">
                                {{ $statusTxt }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-center" onclick="event.stopPropagation()">
                            <button onclick="goToInput({{ $sk->id }})"
                                    class="w-7 h-7 flex items-center justify-center bg-blue-100 hover:bg-blue-200 text-blue-600 rounded-lg transition mx-auto">
                                <i class="fas fa-pen text-[10px]"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center text-gray-400">
                            <i class="fas fa-inbox text-4xl mb-3 block text-gray-300"></i>
                            <p>Tidak ada sub kegiatan terdaftar untuk dinas ini</p>
                            <p class="text-xs mt-1">Hubungi admin untuk menambahkan data</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($subKegiatan->isNotEmpty())
                <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                    <tr>
                        <td colspan="3" class="px-3 py-2.5 font-bold text-gray-700">Total</td>
                        <td class="px-3 py-2.5 text-right font-bold font-mono text-gray-700">
                            Rp {{ number_format($stats['totalAlokasi'], 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-2.5 text-right font-bold font-mono text-purple-700">
                            Rp {{ number_format($stats['totalSem1'], 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-2.5 text-right font-bold font-mono text-blue-700">
                            Rp {{ number_format($stats['totalSem2'], 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-2.5 text-right font-bold font-mono text-green-700">
                            Rp {{ number_format($stats['totalRealisasi'], 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            @php
                                $totBadge = $stats['persen'] >= 80 ? 'bg-green-100 text-green-700' : ($stats['persen'] >= 50 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                            @endphp
                            <span class="inline-block px-2 py-0.5 rounded-full font-bold text-xs {{ $totBadge }}">{{ $stats['persen'] }}%</span>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ── Akses Cepat Input Data ───────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border p-4 md:p-5">
        <h3 class="font-semibold text-gray-800 mb-1 text-sm md:text-base flex items-center gap-2">
            <i class="fas fa-bolt text-yellow-500"></i> Akses Cepat Input Data
        </h3>
        <p class="text-xs text-gray-500 mb-4">Klik program di bawah untuk langsung membuka halaman input laporan</p>

        @if($perProgram->isEmpty())
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-folder-open text-3xl mb-2 block text-gray-300"></i>
            <p class="text-sm">Tidak ada program tersedia</p>
        </div>
        @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($perProgram as $programNama => $items)
            @php
                $filled   = $items->filter(fn($sk) => $sk->laporan->isNotEmpty() && (float) optional($sk->laporan->first())->alokasi_anggaran > 0)->count();
                $tot      = $items->count();
                $pct      = $tot > 0 ? round($filled / $tot * 100) : 0;
                $allDone  = $filled === $tot && $tot > 0;
                $partial  = $filled > 0 && !$allDone;
                $firstId  = $items->first()->id;

                $cardBorder = $allDone ? 'border-green-300 bg-green-50' : ($partial ? 'border-yellow-300 bg-yellow-50' : 'border-orange-200 bg-orange-50');
                $iconBg     = $allDone ? 'bg-green-500' : ($partial ? 'bg-yellow-500' : 'bg-orange-400');
                $icon       = $allDone ? 'fa-check' : ($partial ? 'fa-pen' : 'fa-plus');
                $barColor   = $allDone ? 'bg-green-500' : ($partial ? 'bg-yellow-500' : 'bg-orange-400');
                $badgeCls   = $allDone ? 'bg-green-100 text-green-700' : ($partial ? 'bg-yellow-100 text-yellow-700' : 'bg-orange-100 text-orange-700');
                $statusText = $allDone ? 'Selesai' : ($partial ? 'Sedang Berjalan' : 'Belum Diisi');
            @endphp
            <a href="{{ route('oppkpke.laporan.index', ['tahun' => $tahun, 'open' => $firstId]) }}"
               class="block p-4 rounded-xl border-2 {{ $cardBorder }} hover:shadow-md transition group">

                <div class="flex items-start justify-between gap-2 mb-3">
                    <div class="w-9 h-9 rounded-xl {{ $iconBg }} flex items-center justify-center flex-shrink-0 shadow-sm group-hover:scale-105 transition-transform">
                        <i class="fas {{ $icon }} text-white text-sm"></i>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $badgeCls }}">
                        {{ $filled }}/{{ $tot }} Terisi
                    </span>
                </div>

                <p class="text-sm font-semibold text-gray-800 line-clamp-2 mb-3 leading-snug">
                    {{ Str::limit($programNama, 55) }}
                </p>

                <div class="w-full bg-gray-200 rounded-full h-1.5 mb-2">
                    <div class="{{ $barColor }} h-1.5 rounded-full transition-all duration-700" style="width: {{ $pct }}%"></div>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">{{ $statusText }}</span>
                    <span class="text-xs text-blue-600 font-medium group-hover:underline">
                        Input <i class="fas fa-arrow-right text-[10px] ml-0.5"></i>
                    </span>
                </div>
            </a>
            @endforeach
        </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
var _perStrategi = @json($perStrategi);
var _labels   = _perStrategi.map(function(s) { return s.nama; });
var _alokasi  = _perStrategi.map(function(s) { return parseFloat(s.alokasi)   || 0; });
var _realisasi= _perStrategi.map(function(s) { return parseFloat(s.realisasi) || 0; });

var COLORS = ['rgba(59,130,246,0.85)', 'rgba(34,197,94,0.85)', 'rgba(249,115,22,0.85)', 'rgba(168,85,247,0.85)'];

if (document.getElementById('chartPie') && _alokasi.some(function(v){ return v > 0; })) {
    new Chart(document.getElementById('chartPie'), {
        type: 'doughnut',
        data: {
            labels: _labels,
            datasets: [{
                data: _alokasi,
                backgroundColor: COLORS,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12, font: { size: 10 } } },
                tooltip: { callbacks: { label: function(ctx) {
                    var v   = parseFloat(ctx.parsed) || 0;
                    var tot = ctx.dataset.data.reduce(function(a,b){ return (parseFloat(a)||0)+(parseFloat(b)||0); }, 0);
                    var pct = tot > 0 ? ((v/tot)*100).toFixed(1) : '0.0';
                    var jt  = (v/1000000).toFixed(1);
                    return ctx.label + ': Rp ' + jt + ' Jt (' + pct + '%)';
                }}}
            }
        }
    });
} else if (document.getElementById('chartPie')) {
    document.getElementById('chartPie').closest('.h-52').innerHTML =
        '<div class="h-full flex flex-col items-center justify-center text-gray-400">' +
        '<i class="fas fa-chart-pie text-4xl mb-2 text-gray-300"></i>' +
        '<p class="text-sm">Belum ada data alokasi</p></div>';
}

function goToInput(id) {
    window.location.href = '{{ route("oppkpke.laporan.index") }}?tahun={{ $tahun }}&open=' + id;
}
</script>
@endpush
