{{-- resources/views/oppkpke/presentasi.blade.php --}}
@extends('layouts.oppkpke')

@section('title', 'Ikhtisar Eksekutif')
@section('page-title', 'Ikhtisar Eksekutif')
@section('page-subtitle', 'Ringkasan anggaran pengentasan kemiskinan untuk pimpinan — Tahun ' . $tahun)

@php
    $rp   = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $rpM  = fn ($n) => 'Rp ' . number_format((float) $n / 1_000_000_000, 2, ',', '.') . ' M';
    // Warna capaian: hijau ≥80, biru ≥50, kuning ≥25, merah <25
    $warna = function ($p) {
        return $p >= 80 ? ['text-green-600', 'bg-green-500', 'bg-green-100 text-green-700']
            : ($p >= 50 ? ['text-blue-600', 'bg-blue-500', 'bg-blue-100 text-blue-700']
            : ($p >= 25 ? ['text-amber-600', 'bg-amber-500', 'bg-amber-100 text-amber-700']
            : ['text-red-600', 'bg-red-500', 'bg-red-100 text-red-700']));
    };
    $levelBadge = [
        'Tinggi' => 'bg-red-100 text-red-700 border-red-200',
        'Sedang' => 'bg-amber-100 text-amber-700 border-amber-200',
        'Rendah' => 'bg-green-100 text-green-700 border-green-200',
    ];
    $k = $d['kpi'];
@endphp

@push('styles')
<style>
    @media print {
        .no-print { display: none !important; }
        .print-break { page-break-before: always; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .shadow-lg, .shadow { box-shadow: none !important; }
    }
    .pres-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 0.75rem; }
</style>
@endpush

@section('content')
<div class="space-y-5 md:space-y-6" id="presentasi-root">

    {{-- ── TOOLBAR (tak ikut cetak) ──────────────────────────────── --}}
    <div class="no-print flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex items-center gap-2">
            <label class="text-sm text-gray-600 font-medium">Tahun Anggaran</label>
            <select name="tahun" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                @forelse($tahunTersedia as $t)
                    <option value="{{ $t }}" @selected($t == $tahun)>{{ $t }}</option>
                @empty
                    <option value="{{ $tahun }}">{{ $tahun }}</option>
                @endforelse
            </select>
            <a href="{{ route('oppkpke.presentasi', ['tahun' => $tahun, 'fresh' => 1]) }}"
               class="text-xs text-gray-500 hover:text-blue-600" title="Muat ulang data terbaru">
                <i class="fas fa-rotate"></i> Segarkan
            </a>
        </form>
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-400">Dihitung {{ $d['generated_at'] }}</span>
            <button onclick="window.print()"
                    class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg shadow">
                <i class="fas fa-print"></i> Cetak / PDF
            </button>
        </div>
    </div>

    {{-- ── 1. IKHTISAR EKSEKUTIF ──────────────── --}}
    @php
        $ik = $d['ikhtisar'];
        // Panel rincian Output & Outcome disembunyikan; set true untuk menampilkannya kembali.
        $tampilOutputOutcome = false;
        // Kelas ditulis utuh (bukan dirakit) agar terdeteksi pemindai Tailwind.
        $tone = [
            'kritis'    => ['band' => 'from-red-700 to-red-500',     'chip' => 'bg-white/15 text-white border-white/30',
                            'teks' => 'text-red-600',   'bar' => 'bg-red-500',   'lembut' => 'bg-red-50 border-red-100'],
            'perhatian' => ['band' => 'from-amber-600 to-amber-400', 'chip' => 'bg-white/15 text-white border-white/30',
                            'teks' => 'text-amber-600', 'bar' => 'bg-amber-500', 'lembut' => 'bg-amber-50 border-amber-100'],
            'baik'      => ['band' => 'from-blue-700 to-blue-500',   'chip' => 'bg-white/15 text-white border-white/30',
                            'teks' => 'text-blue-600',  'bar' => 'bg-blue-500',  'lembut' => 'bg-blue-50 border-blue-100'],
            'optimal'   => ['band' => 'from-green-700 to-green-500', 'chip' => 'bg-white/15 text-white border-white/30',
                            'teks' => 'text-green-600', 'bar' => 'bg-green-500', 'lembut' => 'bg-green-50 border-green-100'],
        ][$ik['status']['level']] ?? ['band' => 'from-slate-700 to-slate-500', 'chip' => 'bg-white/15 text-white border-white/30',
                                      'teks' => 'text-slate-600', 'bar' => 'bg-slate-500', 'lembut' => 'bg-slate-50 border-slate-100'];
    @endphp

    <div class="pres-card shadow overflow-hidden">

        {{-- Kepala: status capaian dalam satu tarikan napas --}}
        <div class="bg-gradient-to-r {{ $tone['band'] }} text-white px-5 py-4 md:px-6">
            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-white/20">
                    <i class="fas {{ $ik['status']['ikon'] }}"></i>
                </span>
                <div>
                    <h2 class="text-lg font-bold leading-tight">Ringkasan Capaian {{ $tahun }}</h2>
                    <p class="text-[11px] text-white/80">Status penyerapan anggaran &amp; hal yang perlu perhatian pimpinan</p>
                </div>
                <span class="ml-auto text-xs font-bold px-3 py-1.5 rounded-full border {{ $tone['chip'] }} whitespace-nowrap">
                    {{ $ik['status']['label'] }} · {{ number_format($ik['persen'], 1, ',', '.') }}%
                </span>
            </div>

            <p class="text-sm md:text-[15px] leading-relaxed mt-3 text-white/95">{{ $ik['headline'] }}</p>
            <p class="text-[11px] text-white/75 mt-1"><i class="fas fa-circle-info mr-1"></i>{{ $ik['status']['arti'] }}</p>

            {{-- Bilah serapan: posisi capaian terhadap titik wajar 50% --}}
            <div class="mt-4">
                <div class="w-full bg-white/25 rounded-full h-2.5 relative">
                    <div class="bg-white h-2.5 rounded-full" style="width: {{ min(max($ik['persen'], 0.6), 100) }}%"></div>
                    <div class="absolute top-[-4px] h-[18px] w-0.5 bg-white/80" style="left: 50%" title="Titik wajar paruh tahun"></div>
                </div>
                <div class="flex justify-between text-[10px] text-white/80 mt-1">
                    <span>Pagu {{ $rpM($ik['alokasi']) }}</span>
                    <span>Titik wajar 50%</span>
                    <span>Tersalur {{ $rpM($ik['realisasi']) }}</span>
                </div>
            </div>
        </div>

        {{-- Isi: output vs outcome bersanding.
             Disembunyikan atas permintaan — ubah $tampilOutputOutcome di atas
             menjadi true bila panel ini ingin ditampilkan lagi. --}}
        @if($tampilOutputOutcome)
        <div class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-gray-100">

            {{-- OUTPUT --}}
            <div class="p-5 md:p-6">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center text-xs"><i class="fas fa-gears"></i></span>
                    <h3 class="text-sm font-bold text-gray-800">Output — Apa yang dikerjakan</h3>
                </div>
                <ul class="space-y-3">
                    @foreach($ik['output'] as $o)
                        <li class="flex items-start gap-3">
                            <i class="fas {{ $o['ikon'] }} text-slate-400 w-4 mt-1 text-center"></i>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline gap-2 flex-wrap">
                                    <span class="text-lg font-bold text-gray-800 leading-none">{{ $o['nilai'] }}</span>
                                    <span class="text-xs text-gray-500">{{ $o['dari'] }}</span>
                                </div>
                                <p class="text-xs font-semibold text-gray-600 mt-0.5">{{ $o['label'] }}</p>
                                <p class="text-[11px] text-gray-500 leading-snug mt-0.5">{{ $o['ket'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- OUTCOME --}}
            <div class="p-5 md:p-6">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-7 h-7 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center text-xs"><i class="fas fa-seedling"></i></span>
                    <h3 class="text-sm font-bold text-gray-800">Outcome — Apa hasilnya bagi masyarakat</h3>
                </div>
                <ul class="space-y-3">
                    @foreach($ik['outcome'] as $o)
                        <li class="flex items-start gap-3">
                            <i class="fas {{ $o['ikon'] }} text-teal-600/70 w-4 mt-1 text-center"></i>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline gap-2 flex-wrap">
                                    <span class="text-lg font-bold text-gray-800 leading-none">{{ $o['nilai'] }}</span>
                                    <span class="text-xs text-gray-500">{{ $o['dari'] }}</span>
                                </div>
                                <p class="text-xs font-semibold text-gray-600 mt-0.5">{{ $o['label'] }}</p>
                                <p class="text-[11px] text-gray-500 leading-snug mt-0.5">{{ $o['ket'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        {{-- Baca cepat: yang berjalan baik vs yang perlu perhatian --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-px bg-gray-100 border-t border-gray-100">
            <div class="bg-white p-5 md:p-6">
                <h4 class="text-xs font-bold text-green-700 mb-2"><i class="fas fa-circle-check mr-1"></i> Yang sudah berjalan baik</h4>
                <ul class="space-y-1.5">
                    @foreach($ik['positif'] as $p)
                        <li class="text-xs text-gray-700 leading-relaxed flex gap-2">
                            <i class="fas fa-angle-right text-green-500 mt-0.5"></i><span>{{ $p }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="bg-white p-5 md:p-6">
                <h4 class="text-xs font-bold {{ $tone['teks'] }} mb-2"><i class="fas fa-triangle-exclamation mr-1"></i> Yang perlu perhatian</h4>
                <ul class="space-y-1.5">
                    @foreach($ik['perhatian'] as $p)
                        <li class="text-xs text-gray-700 leading-relaxed flex gap-2">
                            <i class="fas fa-angle-right {{ $tone['teks'] }} mt-0.5"></i><span>{{ $p }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Dasar kesimpulan: seberapa lengkap data pendukungnya --}}
        @php $kl = $ik['kelengkapan']; @endphp
        <div class="border-t border-gray-100 p-5 md:p-6 bg-gray-50/70">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <h4 class="text-xs font-bold text-gray-700"><i class="fas fa-clipboard-list text-gray-400 mr-1"></i> Kekuatan data pendukung</h4>
                <span class="text-xs font-bold {{ $kl['persen'] >= 70 ? 'text-green-600' : ($kl['persen'] >= 40 ? 'text-amber-600' : 'text-red-600') }}">
                    {{ number_format($kl['persen'], 1, ',', '.') }}% terisi
                </span>
                <span class="text-[11px] text-gray-500">dari {{ number_format($kl['baris'], 0, ',', '.') }} baris laporan</span>
                <span class="ml-auto text-[11px] text-gray-400">Makin lengkap kolom ini, makin tajam ukuran outcome-nya.</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                @foreach($kl['detail'] as $f)
                    <div>
                        <div class="flex items-baseline justify-between gap-1">
                            <span class="text-[11px] text-gray-600 truncate" title="{{ $f['label'] }}">{{ $f['label'] }}</span>
                            <span class="text-[11px] font-semibold text-gray-700">{{ number_format($f['persen'], 0, ',', '.') }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                            <div class="{{ $f['persen'] >= 70 ? 'bg-green-500' : ($f['persen'] >= 40 ? 'bg-amber-500' : 'bg-red-400') }} h-1.5 rounded-full"
                                 style="width: {{ min(max($f['persen'], 1.5), 100) }}%"></div>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-0.5">{{ number_format($f['terisi'], 0, ',', '.') }} baris</p>
                    </div>
                @endforeach
            </div>

            <details class="mt-4 group">
                <summary class="text-xs text-gray-500 cursor-pointer hover:text-blue-600 select-none">
                    <i class="fas fa-chevron-right text-[10px] mr-1 group-open:rotate-90 transition-transform"></i>
                    Baca narasi ringkasan versi lengkap
                </summary>
                <p class="text-sm leading-relaxed text-gray-700 mt-2 pl-4 border-l-2 border-gray-200">{{ $d['narasi'] }}</p>
            </details>
        </div>
    </div>

    {{-- ── 2. KPI CARDS ──────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
        <div class="pres-card p-4 md:p-5">
            <div class="flex items-center gap-2 mb-1.5">
                <span class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center text-sm"><i class="fas fa-wallet"></i></span>
                <p class="text-xs text-gray-500">Total Alokasi</p>
            </div>
            <p class="text-xl md:text-2xl font-bold text-gray-800">{{ $rpM($k['total_alokasi']) }}</p>
            <p class="text-[11px] text-gray-400 mt-0.5 truncate">{{ $rp($k['total_alokasi']) }}</p>
        </div>
        <div class="pres-card p-4 md:p-5">
            <div class="flex items-center gap-2 mb-1.5">
                <span class="w-8 h-8 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center text-sm"><i class="fas fa-hand-holding-dollar"></i></span>
                <p class="text-xs text-gray-500">Total Realisasi</p>
            </div>
            <p class="text-xl md:text-2xl font-bold text-gray-800">{{ $rpM($k['total_realisasi']) }}</p>
            <p class="text-[11px] text-gray-400 mt-0.5 truncate">{{ $rp($k['total_realisasi']) }}</p>
        </div>
        <div class="pres-card p-4 md:p-5">
            <div class="flex items-center gap-2 mb-1.5">
                <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center text-sm"><i class="fas fa-piggy-bank"></i></span>
                <p class="text-xs text-gray-500">Sisa Anggaran</p>
            </div>
            <p class="text-xl md:text-2xl font-bold text-gray-800">{{ $rpM($k['sisa']) }}</p>
            <p class="text-[11px] text-gray-400 mt-0.5">belum terealisasi</p>
        </div>
        <div class="pres-card p-4 md:p-5">
            <div class="flex items-center gap-2 mb-1.5">
                <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center text-sm"><i class="fas fa-gauge"></i></span>
                <p class="text-xs text-gray-500">Penyerapan</p>
            </div>
            <p class="text-xl md:text-2xl font-bold text-gray-800">{{ number_format($k['persentase'], 1, ',', '.') }}%</p>
            <div class="w-full bg-gray-100 rounded-full h-1.5 mt-2">
                <div class="bg-slate-500 h-1.5 rounded-full" style="width: {{ min($k['persentase'], 100) }}%"></div>
            </div>
        </div>
    </div>

    {{-- Baris statistik sekunder --}}
    @php
        $statSekunder = [
            ['lbl' => 'Perangkat Daerah', 'val' => $k['jumlah_pd'],     'ic' => 'fa-building',         'col' => 'text-blue-600'],
            ['lbl' => 'PD Beranggaran',   'val' => $k['pd_terisi'],     'ic' => 'fa-circle-check',     'col' => 'text-green-600'],
            ['lbl' => 'Total Program',    'val' => $k['total_program'], 'ic' => 'fa-diagram-project',  'col' => 'text-violet-600'],
            ['lbl' => 'Baris Laporan',    'val' => $k['total_laporan'], 'ic' => 'fa-table-list',       'col' => 'text-amber-600'],
        ];
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach($statSekunder as $st)
            <div class="pres-card p-3.5 flex items-center gap-3">
                <i class="fas {{ $st['ic'] }} {{ $st['col'] }} text-lg"></i>
                <div>
                    <p class="text-lg font-bold text-gray-800 leading-none">{{ number_format($st['val'], 0, ',', '.') }}</p>
                    <p class="text-[11px] text-gray-500 mt-0.5">{{ $st['lbl'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── 3. BURN RATE + FORECAST ───────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Burn rate --}}
        @php $br = $d['burn_rate']; $devPos = $br['deviasi'] >= 0; @endphp
        <div class="pres-card p-5 shadow">
            <h3 class="text-sm font-bold text-gray-700 mb-3"><i class="fas fa-gauge-high text-blue-500 mr-1"></i> Kecepatan Serapan (Burn Rate)</h3>
            <div class="flex items-end gap-6">
                <div>
                    <p class="text-3xl font-bold {{ $warna($br['capaian'])[0] }}">{{ number_format($br['capaian'], 1, ',', '.') }}%</p>
                    <p class="text-[11px] text-gray-500">capaian aktual</p>
                </div>
                <div>
                    <p class="text-3xl font-bold text-gray-400">{{ number_format($br['ekspektasi'], 0) }}%</p>
                    <p class="text-[11px] text-gray-500">ekspektasi merata</p>
                </div>
                <div class="ml-auto text-right">
                    <p class="text-2xl font-bold {{ $devPos ? 'text-green-600' : 'text-red-600' }}">
                        {{ $devPos ? '+' : '' }}{{ number_format($br['deviasi'], 1, ',', '.') }}
                    </p>
                    <p class="text-[11px] text-gray-500">deviasi (poin)</p>
                </div>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2.5 mt-4 relative">
                <div class="{{ $warna($br['capaian'])[1] }} h-2.5 rounded-full" style="width: {{ min($br['capaian'], 100) }}%"></div>
                <div class="absolute top-[-3px] h-4 w-0.5 bg-gray-500" style="left: {{ min($br['ekspektasi'], 100) }}%" title="Ekspektasi merata"></div>
            </div>
            <p class="text-[11px] text-gray-500 mt-2">
                Garis vertikal = titik ekspektasi bila serapan merata sepanjang tahun.
                {{ $devPos ? 'Serapan di atas ekspektasi.' : 'Serapan tertinggal dari ekspektasi — perlu percepatan.' }}
            </p>
        </div>

        {{-- Forecast --}}
        <div class="pres-card p-5 shadow">
            <h3 class="text-sm font-bold text-gray-700 mb-3"><i class="fas fa-chart-line text-slate-500 mr-1"></i> Proyeksi Akhir Tahun
                <span class="text-[10px] font-semibold bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full ml-1">ESTIMASI</span>
            </h3>
            @if($d['forecast'])
                <div class="flex items-end gap-6">
                    <div>
                        <p class="text-3xl font-bold text-slate-700">{{ number_format($d['forecast']['proyeksi_persen'], 1, ',', '.') }}%</p>
                        <p class="text-[11px] text-gray-500">proyeksi penyerapan</p>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-gray-700">{{ $rpM($d['forecast']['proyeksi_realisasi']) }}</p>
                        <p class="text-[11px] text-gray-500">estimasi realisasi setahun</p>
                    </div>
                </div>
                <p class="text-[11px] text-gray-500 mt-3 leading-relaxed">
                    <i class="fas fa-circle-info mr-0.5"></i> {{ $d['forecast']['metode'] }}
                    Estimasi ini <strong>bukan angka final</strong> dan dapat berubah sesuai realisasi sebenarnya.
                </p>
            @else
                <p class="text-sm text-gray-400 italic mt-4">Belum ada realisasi — proyeksi belum dapat dihitung.</p>
            @endif
        </div>
    </div>

    {{-- ── 4. GRAFIK: Strategi + Sumber Dana ─────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="pres-card p-5 shadow">
            <h3 class="text-sm font-bold text-gray-700 mb-3"><i class="fas fa-bullseye text-slate-500 mr-1"></i> Alokasi vs Realisasi per Strategi</h3>
            <div class="h-64 relative"><div class="chart-skeleton skeleton absolute inset-0"></div><canvas id="chartStrategi"></canvas></div>
        </div>
        <div class="pres-card p-5 shadow">
            <h3 class="text-sm font-bold text-gray-700 mb-3"><i class="fas fa-hand-holding-dollar text-teal-600 mr-1"></i> Komposisi Sumber Dana</h3>
            <div class="h-64 relative"><div class="chart-skeleton skeleton absolute inset-0"></div><canvas id="chartSumber"></canvas></div>
            <p class="text-[10px] text-gray-400 mt-1 text-center">Adaptasi: aplikasi melacak sumber dana, bukan jenis belanja.</p>
        </div>
    </div>

    {{-- ── 5. GRAFIK: YoY + Semester ─────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="pres-card p-5 shadow">
            <h3 class="text-sm font-bold text-gray-700 mb-3"><i class="fas fa-arrow-trend-up text-indigo-600 mr-1"></i> Perbandingan Antar Tahun</h3>
            <div class="h-56 relative"><div class="chart-skeleton skeleton absolute inset-0"></div><canvas id="chartYoy"></canvas></div>
        </div>
        <div class="pres-card p-5 shadow">
            <h3 class="text-sm font-bold text-gray-700 mb-3"><i class="fas fa-calendar-days text-teal-600 mr-1"></i> Realisasi per Semester ({{ $tahun }})</h3>
            <div class="h-56 relative"><div class="chart-skeleton skeleton absolute inset-0"></div><canvas id="chartSemester"></canvas></div>
            <p class="text-[10px] text-gray-400 mt-1 text-center">Adaptasi: aplikasi mencatat per semester, bukan bulanan.</p>
        </div>
    </div>

    {{-- ── 6. REALISASI PER STRATEGI (tabel + progress) ──────────── --}}
    <div class="pres-card p-5 shadow print-break">
        <h3 class="text-sm font-bold text-gray-700 mb-3"><i class="fas fa-list-check text-blue-500 mr-1"></i> Realisasi per Strategi</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-200 text-xs">
                        <th class="py-2 pr-2">Strategi</th>
                        <th class="py-2 px-2 text-right">Alokasi</th>
                        <th class="py-2 px-2 text-right">Realisasi</th>
                        <th class="py-2 pl-2 w-40">Capaian</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($d['per_strategi'] as $s)
                        @php $c = $warna($s['persentase']); @endphp
                        <tr class="border-b border-gray-100">
                            <td class="py-2.5 pr-2"><span class="text-gray-400 mr-1">[{{ $s['kode'] }}]</span>{{ $s['nama'] }}</td>
                            <td class="py-2.5 px-2 text-right text-gray-600 whitespace-nowrap">{{ $rp($s['alokasi']) }}</td>
                            <td class="py-2.5 px-2 text-right text-gray-800 font-medium whitespace-nowrap">{{ $rp($s['realisasi']) }}</td>
                            <td class="py-2.5 pl-2">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-100 rounded-full h-2">
                                        <div class="{{ $c[1] }} h-2 rounded-full" style="width: {{ min($s['persentase'], 100) }}%"></div>
                                    </div>
                                    <span class="{{ $c[0] }} font-semibold text-xs w-12 text-right">{{ number_format($s['persentase'], 1, ',', '.') }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── 7. PER PERANGKAT DAERAH: tertinggi & terendah ─────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="pres-card p-5 shadow">
            <h3 class="text-sm font-bold text-gray-700 mb-3"><i class="fas fa-trophy text-green-500 mr-1"></i> 5 PD Realisasi Tertinggi</h3>
            @include('oppkpke.partials.pd_list', ['rows' => $d['pd_tertinggi'], 'rp' => $rp, 'warna' => $warna])
        </div>
        <div class="pres-card p-5 shadow">
            <h3 class="text-sm font-bold text-gray-700 mb-3"><i class="fas fa-triangle-exclamation text-red-500 mr-1"></i> 5 PD Capaian Terendah</h3>
            @include('oppkpke.partials.pd_list', ['rows' => $d['pd_terendah'], 'rp' => $rp, 'warna' => $warna])
        </div>
    </div>

    {{-- ── 8. VARIANCE: Under & Over budget ──────────────────────── --}}
    <div class="pres-card p-5 shadow print-break">
        <h3 class="text-sm font-bold text-gray-700 mb-1"><i class="fas fa-scale-balanced text-amber-500 mr-1"></i> Analisis Serapan Program (Variance)</h3>
        <p class="text-[11px] text-gray-400 mb-3">Program dengan serapan terendah — fokus utama percepatan.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-200 text-xs">
                        <th class="py-2 pr-2">Program</th>
                        <th class="py-2 px-2">Perangkat Daerah</th>
                        <th class="py-2 px-2 text-right">Alokasi</th>
                        <th class="py-2 px-2 text-right">Realisasi</th>
                        <th class="py-2 px-2 text-right">Selisih</th>
                        <th class="py-2 pl-2 text-right">%</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($d['under_budget'] as $p)
                        @php $c = $warna($p['persentase']); @endphp
                        <tr class="border-b border-gray-100">
                            <td class="py-2 pr-2 max-w-[220px] truncate" title="{{ $p['nama'] }}">{{ $p['nama'] }}</td>
                            <td class="py-2 px-2 text-gray-500 max-w-[180px] truncate" title="{{ $p['pd'] }}">{{ $p['pd'] }}</td>
                            <td class="py-2 px-2 text-right text-gray-600 whitespace-nowrap">{{ $rp($p['alokasi']) }}</td>
                            <td class="py-2 px-2 text-right text-gray-800 whitespace-nowrap">{{ $rp($p['realisasi']) }}</td>
                            <td class="py-2 px-2 text-right text-gray-500 whitespace-nowrap">{{ $rp($p['selisih']) }}</td>
                            <td class="py-2 pl-2 text-right"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $c[2] }}">{{ number_format($p['persentase'], 1, ',', '.') }}%</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-4 text-center text-gray-400 text-sm">Belum ada program beranggaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(!empty($d['over_budget']))
            <h4 class="text-xs font-bold text-red-600 mt-5 mb-2"><i class="fas fa-circle-exclamation mr-1"></i> Realisasi Melampaui Pagu (Over Budget)</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody>
                        @foreach($d['over_budget'] as $p)
                            <tr class="border-b border-red-100 bg-red-50/40">
                                <td class="py-2 pr-2 max-w-[240px] truncate" title="{{ $p['nama'] }}">{{ $p['nama'] }}</td>
                                <td class="py-2 px-2 text-gray-500 max-w-[180px] truncate">{{ $p['pd'] }}</td>
                                <td class="py-2 px-2 text-right whitespace-nowrap">{{ $rp($p['alokasi']) }}</td>
                                <td class="py-2 px-2 text-right font-medium whitespace-nowrap">{{ $rp($p['realisasi']) }}</td>
                                <td class="py-2 pl-2 text-right"><span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">+{{ number_format($p['over_persen'], 1, ',', '.') }}%</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ── 9. RISIKO ─────────────────────────────────────────────── --}}
    <div class="pres-card p-5 shadow">
        <h3 class="text-sm font-bold text-gray-700 mb-3"><i class="fas fa-shield-halved text-red-500 mr-1"></i> Identifikasi Risiko</h3>
        <div class="space-y-2">
            @foreach($d['risiko'] as $x)
                <div class="flex items-start gap-3 p-3 rounded-lg border {{ $levelBadge[$x['level']] ?? 'bg-gray-50 border-gray-200' }}">
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full border {{ $levelBadge[$x['level']] ?? '' }} whitespace-nowrap">{{ $x['level'] }}</span>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">{{ $x['risiko'] }}</p>
                        <p class="text-xs text-gray-600 mt-0.5">{{ $x['detail'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ── 10. ACTION PLAN / REKOMENDASI ─────────────────────────── --}}
    <div class="pres-card p-5 shadow bg-gradient-to-br from-blue-50 to-white">
        <h3 class="text-sm font-bold text-gray-700 mb-3"><i class="fas fa-clipboard-check text-blue-600 mr-1"></i> Rencana Aksi &amp; Rekomendasi</h3>
        <ol class="space-y-2">
            @foreach($d['rekomendasi'] as $i => $rek)
                <li class="flex items-start gap-3">
                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center">{{ $i + 1 }}</span>
                    <p class="text-sm text-gray-700 leading-relaxed">{{ $rek }}</p>
                </li>
            @endforeach
        </ol>
        @if(!empty($d['sorotan']))
            <div class="mt-4 pt-4 border-t border-blue-100">
                <p class="text-xs font-semibold text-gray-500 mb-2">Sorotan:</p>
                <ul class="space-y-1">
                    @foreach($d['sorotan'] as $sr)
                        <li class="text-xs text-gray-600"><i class="fas fa-angle-right text-blue-400 mr-1"></i>{{ $sr }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <p class="text-[11px] text-gray-400 text-center pb-2">
        Seluruh angka dihitung deterministik dari basis data OPPKPKE (konsisten dengan Dashboard, memperhitungkan data terhapus).
        Granularitas data: per semester &amp; per tahun.
    </p>
</div>
@endsection

@push('scripts')
@php
    $chartData = [
        'strategi' => $d['per_strategi'],
        'sumber'   => $d['sumber_dana'],
        'yoy'      => $d['yoy'],
        'semester' => $d['semester'],
    ];
@endphp
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const D = @json($chartData);
    const fmtM = v => 'Rp ' + (v / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' M';
    // Palet natural (redup, kontras cukup) — nyaman untuk pengguna lanjut usia.
    const PALET = ['#475569', '#0f766e', '#b45309', '#4f46e5', '#9f1239', '#0e7490', '#7c3a32', '#334155'];
    const hideSkeletons = () => document.querySelectorAll('.chart-skeleton').forEach(el => el.remove());
    if (typeof Chart === 'undefined') { return; }
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#475569';

    // 1. Strategi — bar alokasi vs realisasi
    new Chart(document.getElementById('chartStrategi'), {
        type: 'bar',
        data: {
            labels: D.strategi.map(s => '[' + s.kode + ']'),
            datasets: [
                { label: 'Alokasi', data: D.strategi.map(s => s.alokasi), backgroundColor: '#94a3b8' },
                { label: 'Realisasi', data: D.strategi.map(s => s.realisasi), backgroundColor: '#0f766e' },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { callbacks: {
                    title: (it) => D.strategi[it[0].dataIndex].nama,
                    label: (c) => c.dataset.label + ': ' + fmtM(c.raw),
                } },
            },
            scales: { y: { ticks: { callback: v => (v / 1e9) + ' M' } } },
        },
    });

    // 2. Sumber dana — doughnut
    new Chart(document.getElementById('chartSumber'), {
        type: 'doughnut',
        data: {
            labels: D.sumber.map(s => s.nama),
            datasets: [{ data: D.sumber.map(s => s.alokasi), backgroundColor: PALET, borderWidth: 1, borderColor: '#fff' }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { boxWidth: 12, font: { size: 10 } } },
                tooltip: { callbacks: { label: (c) => c.label + ': ' + fmtM(c.raw) + ' (' + D.sumber[c.dataIndex].porsi + '%)' } },
            },
        },
    });

    // 3. YoY — bar % + nilai
    new Chart(document.getElementById('chartYoy'), {
        type: 'bar',
        data: {
            labels: D.yoy.map(y => y.tahun),
            datasets: [
                { label: 'Alokasi', data: D.yoy.map(y => y.alokasi), backgroundColor: '#94a3b8', yAxisID: 'y' },
                { label: 'Realisasi', data: D.yoy.map(y => y.realisasi), backgroundColor: '#0f766e', yAxisID: 'y' },
                { label: 'Penyerapan %', type: 'line', data: D.yoy.map(y => y.persentase), borderColor: '#4f46e5', backgroundColor: '#4f46e5', yAxisID: 'y1', tension: 0.3 },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: {
                label: (c) => c.dataset.yAxisID === 'y1' ? c.dataset.label + ': ' + c.raw + '%' : c.dataset.label + ': ' + fmtM(c.raw),
            } } },
            scales: {
                y: { position: 'left', ticks: { callback: v => (v / 1e9) + ' M' } },
                y1: { position: 'right', min: 0, max: 100, grid: { drawOnChartArea: false }, ticks: { callback: v => v + '%' } },
            },
        },
    });

    // 4. Semester — bar
    new Chart(document.getElementById('chartSemester'), {
        type: 'bar',
        data: {
            labels: ['Semester 1', 'Semester 2'],
            datasets: [{ label: 'Realisasi', data: [D.semester.sem1, D.semester.sem2], backgroundColor: ['#0f766e', '#0e7490'] }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => fmtM(c.raw) } } },
            scales: { y: { ticks: { callback: v => (v / 1e9) + ' M' } } },
        },
    });

    // Grafik selesai digambar → hilangkan skeleton.
    hideSkeletons();
})();
</script>
@endpush
