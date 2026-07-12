<?php

namespace App\Services;

use App\Models\LaporanOppkpke;
use App\Models\PerangkatDaerah;
use App\Models\StrategiOppkpke;
use App\Models\SubKegiatan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan otomatis anggaran OPPKPKE — RINGAN & DETERMINISTIK (tanpa AI/LLM).
 *
 * Semua angka dihitung langsung dari DB (memakai logika yang sama dengan
 * dashboard, termasuk filter deleted_at). narrativeSummary() merangkai fakta
 * menjadi kalimat + sorotan + rekomendasi berbasis aturan — cepat, akurat,
 * tanpa dependensi eksternal.
 */
class BudgetInsightService
{
    /**
     * Snapshot agregat satu tahun (di-cache singkat).
     *
     * @return array<string,mixed>
     */
    public function snapshot(int $tahun, bool $fresh = false): array
    {
        $key = "insight:snapshot:{$tahun}";
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addMinutes(5), fn () => $this->build($tahun));
    }

    /**
     * @return array<string,mixed>
     */
    private function build(int $tahun): array
    {
        $totalAlokasi   = (float) LaporanOppkpke::where('tahun', $tahun)->sum('alokasi_anggaran');
        $totalRealisasi = (float) LaporanOppkpke::where('tahun', $tahun)->sum('realisasi_total');
        $sem1 = (float) LaporanOppkpke::where('tahun', $tahun)->sum('realisasi_sem1');
        $sem2 = (float) LaporanOppkpke::where('tahun', $tahun)->sum('realisasi_sem2');

        $perStrategi = StrategiOppkpke::where('is_active', true)->orderBy('kode')->get()
            ->map(function ($s) use ($tahun) {
                $base = LaporanOppkpke::where('tahun', $tahun)
                    ->whereHas('subKegiatan.kegiatan.program', fn ($q) => $q->where('strategi_id', $s->id));
                $alokasi   = (float) (clone $base)->sum('alokasi_anggaran');
                $realisasi = (float) (clone $base)->sum('realisasi_total');
                return [
                    'kode'       => $s->kode,
                    'nama'       => $this->cleanName($s->nama),
                    'alokasi'    => $alokasi,
                    'realisasi'  => $realisasi,
                    'persentase' => $alokasi > 0 ? round(($realisasi / $alokasi) * 100, 1) : 0.0,
                ];
            })->values()->all();

        $sums = DB::table('laporan_oppkpke as lo')
            ->join('sub_kegiatan as sk', 'lo.sub_kegiatan_id', '=', 'sk.id')
            ->join('kegiatan as k',      'sk.kegiatan_id',     '=', 'k.id')
            ->join('programs as p',      'k.program_id',       '=', 'p.id')
            ->where('lo.tahun', $tahun)
            ->whereNull('lo.deleted_at')
            ->groupBy('p.perangkat_daerah_id')
            ->select(
                'p.perangkat_daerah_id as pd_id',
                DB::raw('SUM(lo.alokasi_anggaran) as alokasi'),
                DB::raw('SUM(lo.realisasi_total)  as realisasi')
            )
            ->get()->keyBy('pd_id');

        $perPd = PerangkatDaerah::where('is_active', true)->orderBy('nama')->get(['id', 'nama'])
            ->map(function ($pd) use ($sums) {
                $row = $sums->get($pd->id);
                $alokasi   = (float) ($row->alokasi   ?? 0);
                $realisasi = (float) ($row->realisasi ?? 0);
                return [
                    'nama'       => $this->cleanName($pd->nama),
                    'alokasi'    => $alokasi,
                    'realisasi'  => $realisasi,
                    'persentase' => $alokasi > 0 ? round(($realisasi / $alokasi) * 100, 1) : 0.0,
                ];
            })->values();

        $withBudget = $perPd->filter(fn ($p) => $p['alokasi'] > 0)->values();

        return [
            'tahun'    => $tahun,
            'ringkasan' => [
                'total_alokasi'        => $totalAlokasi,
                'total_realisasi'      => $totalRealisasi,
                'persentase_realisasi' => $totalAlokasi > 0 ? round(($totalRealisasi / $totalAlokasi) * 100, 1) : 0.0,
                'jumlah_pd_aktif'      => $perPd->count(),
                'pd_terisi'            => $withBudget->count(),
                'pd_kosong'            => $perPd->count() - $withBudget->count(),
                'total_sub_kegiatan'   => SubKegiatan::count(),
                'total_laporan'        => LaporanOppkpke::where('tahun', $tahun)->count(),
            ],
            'semester' => [
                'realisasi_sem1' => $sem1,
                'realisasi_sem2' => $sem2,
            ],
            'per_strategi'       => $perStrategi,
            'pd_tertinggi'       => $withBudget->sortByDesc('realisasi')->take(5)->values()->all(),
            'pd_terendah'        => $withBudget->sortBy('persentase')->take(5)->values()->all(),
            'pd_tanpa_realisasi' => $withBudget->filter(fn ($p) => $p['realisasi'] <= 0)->pluck('nama')->values()->all(),
            'generated_at'       => now()->toDateTimeString(),
        ];
    }

    /**
     * Ringkasan otomatis (narasi + sorotan + rekomendasi) berbasis aturan.
     * Tidak memakai AI — cepat & selalu akurat.
     *
     * @return array<string,mixed>
     */
    public function narrativeSummary(int $tahun): array
    {
        $s = $this->snapshot($tahun);
        $r = $s['ringkasan'];
        $persen = $r['persentase_realisasi'];

        // ── Narasi ───────────────────────────────────────────────────────
        $kategori = $r['total_realisasi'] <= 0 ? 'belum ada realisasi'
            : ($persen >= 80 ? 'sangat baik'
            : ($persen >= 50 ? 'cukup baik' : 'masih rendah'));

        $ringkasan = "Pada tahun {$tahun}, total alokasi anggaran pengentasan kemiskinan sebesar "
            . $this->rupiah($r['total_alokasi']) . " dengan realisasi " . $this->rupiah($r['total_realisasi'])
            . " atau {$persen}%, tergolong {$kategori}. "
            . "Dari {$r['jumlah_pd_aktif']} perangkat daerah aktif, {$r['pd_terisi']} telah menganggarkan"
            . ($r['pd_kosong'] > 0 ? " dan {$r['pd_kosong']} belum menganggarkan. " : ". ");

        $sem1 = $s['semester']['realisasi_sem1'];
        $sem2 = $s['semester']['realisasi_sem2'];
        if (($sem1 + $sem2) > 0) {
            $ringkasan .= "Realisasi terbagi atas Semester 1 " . $this->rupiah($sem1)
                . " dan Semester 2 " . $this->rupiah($sem2) . ". ";
        }
        if (! empty($s['pd_tertinggi'])) {
            $top = $s['pd_tertinggi'][0];
            $ringkasan .= "Realisasi tertinggi dicapai " . $top['nama']
                . " (" . $this->rupiah($top['realisasi']) . " / {$top['persentase']}%).";
        }

        // ── Sorotan ──────────────────────────────────────────────────────
        $sorotan = [];
        $stratBerAnggaran = array_values(array_filter($s['per_strategi'], fn ($x) => $x['alokasi'] > 0));
        if (! empty($stratBerAnggaran)) {
            usort($stratBerAnggaran, fn ($a, $b) => $b['persentase'] <=> $a['persentase']);
            $stTop = $stratBerAnggaran[0];
            $stLow = end($stratBerAnggaran);
            $sorotan[] = "Strategi capaian tertinggi: [{$stTop['kode']}] {$stTop['nama']} ({$stTop['persentase']}%).";
            if ($stLow['kode'] !== $stTop['kode']) {
                $sorotan[] = "Strategi capaian terendah: [{$stLow['kode']}] {$stLow['nama']} ({$stLow['persentase']}%).";
            }
        }
        if (! empty($s['pd_terendah'])) {
            $low = $s['pd_terendah'][0];
            $sorotan[] = "Perangkat daerah capaian terendah: {$low['nama']} ({$low['persentase']}%).";
        }
        $nTanpa = count($s['pd_tanpa_realisasi']);
        if ($nTanpa > 0) {
            $sorotan[] = "{$nTanpa} perangkat daerah sudah beranggaran namun belum mencatat realisasi.";
        }

        // ── Rekomendasi (berbasis aturan) ────────────────────────────────
        $rekomendasi = [];
        if ($persen < 50) {
            $rekomendasi[] = "Percepat penyerapan anggaran — capaian keseluruhan baru {$persen}%, evaluasi hambatan pada perangkat daerah dengan realisasi rendah.";
        }
        if ($nTanpa > 0) {
            $rekomendasi[] = "Dorong {$nTanpa} perangkat daerah beranggaran agar segera menginput realisasi tepat waktu.";
        }
        if ($r['pd_kosong'] > 0) {
            $rekomendasi[] = "Pastikan {$r['pd_kosong']} perangkat daerah yang belum menganggarkan melengkapi perencanaan (RAT).";
        }
        // Strategi beranggaran tapi 0% realisasi.
        $stratNol = array_values(array_filter($stratBerAnggaran ?? [], fn ($x) => $x['persentase'] <= 0));
        foreach (array_slice($stratNol, 0, 2) as $st) {
            $rekomendasi[] = "Fokus percepatan pada Strategi [{$st['kode']}] {$st['nama']} yang belum merealisasi anggaran.";
        }
        if ($sem2 > 0 && $sem2 < $sem1 * 0.5) {
            $rekomendasi[] = "Realisasi Semester 2 jauh di bawah Semester 1 — jaga konsistensi penyerapan hingga akhir tahun.";
        }
        if (empty($rekomendasi)) {
            $rekomendasi[] = "Pertahankan capaian dan lanjutkan pemantauan berkala hingga akhir tahun anggaran.";
        }

        return [
            'tahun'        => $tahun,
            'persentase'   => $persen,
            'kategori'     => $kategori,
            'ringkasan'    => $ringkasan,
            'sorotan'      => $sorotan,
            'rekomendasi'  => $rekomendasi,
            'generated_at' => $s['generated_at'],
        ];
    }

    /**
     * Data lengkap untuk halaman "Presentasi Top Management".
     *
     * Semua angka DETERMINISTIK dari DB (sama dengan dashboard). Seksi yang
     * tidak punya data bulanan diadaptasi ke realita aplikasi:
     *  - Tren       → Semester 1 vs 2 + perbandingan antar tahun (YoY).
     *  - Komposisi  → berdasarkan Sumber Dana (bukan jenis belanja — tak dilacak).
     *  - Forecast   → proyeksi berbasis pola realisasi tahun sebelumnya (bila ada),
     *                 dilabeli jelas sebagai ESTIMASI, bukan angka final.
     *
     * @return array<string,mixed>
     */
    public function presentationData(int $tahun, bool $fresh = false): array
    {
        $key = "insight:presentasi:{$tahun}";
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addMinutes(5), fn () => $this->buildPresentation($tahun));
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPresentation(int $tahun): array
    {
        $snap = $this->snapshot($tahun);
        $r    = $snap['ringkasan'];
        $alokasi   = (float) $r['total_alokasi'];
        $realisasi = (float) $r['total_realisasi'];
        $sisa      = $alokasi - $realisasi;

        // ── Per PROGRAM (grouped join, efisien) ──────────────────────────
        $progRows = DB::table('laporan_oppkpke as lo')
            ->join('sub_kegiatan as sk', 'lo.sub_kegiatan_id', '=', 'sk.id')
            ->join('kegiatan as k',      'sk.kegiatan_id',     '=', 'k.id')
            ->join('programs as p',      'k.program_id',       '=', 'p.id')
            ->join('perangkat_daerah as pd', 'p.perangkat_daerah_id', '=', 'pd.id')
            ->where('lo.tahun', $tahun)
            ->whereNull('lo.deleted_at')
            ->groupBy('p.id', 'p.nama_program', 'pd.nama')
            ->select(
                'p.nama_program',
                'pd.nama as pd_nama',
                DB::raw('SUM(lo.alokasi_anggaran) as alokasi'),
                DB::raw('SUM(lo.realisasi_total)  as realisasi')
            )
            ->get()
            ->map(function ($row) {
                $a = (float) $row->alokasi;
                $re = (float) $row->realisasi;
                return [
                    'nama'       => $this->cleanName((string) $row->nama_program),
                    'pd'         => $this->cleanName((string) $row->pd_nama),
                    'alokasi'    => $a,
                    'realisasi'  => $re,
                    'selisih'    => $a - $re,
                    'persentase' => $a > 0 ? round(($re / $a) * 100, 1) : 0.0,
                ];
            });

        $berAnggaran = $progRows->filter(fn ($p) => $p['alokasi'] > 0)->values();

        // Program teratas (nilai alokasi terbesar) — konteks skala.
        $programTeratas = $berAnggaran->sortByDesc('alokasi')->take(10)->values()->all();

        // Over budget: realisasi > alokasi → urut kelebihan (%) terbesar.
        $overBudget = $berAnggaran->filter(fn ($p) => $p['realisasi'] > $p['alokasi'])
            ->map(function ($p) {
                $p['over_persen'] = round((($p['realisasi'] - $p['alokasi']) / $p['alokasi']) * 100, 1);
                return $p;
            })
            ->sortByDesc('over_persen')->take(10)->values()->all();

        // Under budget: serapan terendah (alokasi ada).
        $underBudget = $berAnggaran->sortBy('persentase')->take(10)->values()->all();

        // ── Komposisi SUMBER DANA ────────────────────────────────────────
        $sumberDana = DB::table('laporan_oppkpke')
            ->where('tahun', $tahun)
            ->whereNull('deleted_at')
            ->groupBy('sumber_pembiayaan')
            ->select(
                'sumber_pembiayaan',
                DB::raw('SUM(alokasi_anggaran) as alokasi'),
                DB::raw('SUM(realisasi_total)  as realisasi')
            )
            ->get()
            ->map(function ($row) use ($alokasi) {
                $nama = trim((string) $row->sumber_pembiayaan);
                $a = (float) $row->alokasi;
                return [
                    'nama'      => $nama !== '' ? $this->cleanName($nama) : 'Tidak Ditentukan',
                    'alokasi'   => $a,
                    'realisasi' => (float) $row->realisasi,
                    'porsi'     => $alokasi > 0 ? round(($a / $alokasi) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('alokasi')->values()->all();

        // ── Perbandingan antar tahun (YoY) ───────────────────────────────
        $yoy = collect(range($tahun - 2, $tahun))
            ->filter(fn ($y) => $y >= 2000)
            ->map(function ($y) {
                $a  = (float) LaporanOppkpke::where('tahun', $y)->sum('alokasi_anggaran');
                $re = (float) LaporanOppkpke::where('tahun', $y)->sum('realisasi_total');
                return [
                    'tahun'      => $y,
                    'alokasi'    => $a,
                    'realisasi'  => $re,
                    'persentase' => $a > 0 ? round(($re / $a) * 100, 1) : 0.0,
                ];
            })
            ->filter(fn ($x) => $x['alokasi'] > 0 || $x['realisasi'] > 0)
            ->values()->all();

        // ── Forecast (ESTIMASI, berbasis pola tahun lalu) ────────────────
        $sem1 = (float) $snap['semester']['realisasi_sem1'];
        $sem2 = (float) $snap['semester']['realisasi_sem2'];
        $prev = $tahun - 1;
        $prevAlokasi   = (float) LaporanOppkpke::where('tahun', $prev)->sum('alokasi_anggaran');
        $prevRealisasi = (float) LaporanOppkpke::where('tahun', $prev)->sum('realisasi_total');
        $prevSem1      = (float) LaporanOppkpke::where('tahun', $prev)->sum('realisasi_sem1');

        $forecast = null;
        if ($realisasi > 0) {
            // Metode 1: pola sem1→setahun tahun lalu (realisasi cenderung menumpuk akhir tahun).
            if ($prevSem1 > 0 && $prevRealisasi > 0 && $sem1 > 0) {
                $mult = $prevRealisasi / $prevSem1;                 // pengali sem1 → setahun (tahun lalu)
                $proyeksi = min($sem1 * $mult, $alokasi);           // tak melampaui alokasi
                $metode = "Berdasarkan pola realisasi {$prev}: Semester 1 tumbuh ~"
                    . round($mult, 1) . '× menjadi realisasi setahun penuh.';
            } else {
                // Metode 2 (fallback): linear dari capaian saat ini.
                $proyeksi = min($realisasi * 2, $alokasi);
                $metode = 'Proyeksi linear sederhana (2× capaian saat ini) — tahun pembanding tidak tersedia.';
            }
            $forecast = [
                'proyeksi_realisasi' => $proyeksi,
                'proyeksi_persen'    => $alokasi > 0 ? round(($proyeksi / $alokasi) * 100, 1) : 0.0,
                'metode'             => $metode,
            ];
        }

        // Burn rate: capaian vs ekspektasi merata per semester (2 semester).
        // Ekspektasi merata di pertengahan tahun = 50%. Deviasi = capaian − ekspektasi.
        $ekspektasi = 50.0;
        $burnRate = [
            'capaian'    => $r['persentase_realisasi'],
            'ekspektasi' => $ekspektasi,
            'deviasi'    => round($r['persentase_realisasi'] - $ekspektasi, 1),
        ];

        // ── Risiko (berbasis aturan) ─────────────────────────────────────
        $narrative = $this->narrativeSummary($tahun);
        $risiko = [];
        $persen = $r['persentase_realisasi'];
        if ($persen < 30) {
            $risiko[] = ['risiko' => 'Serapan anggaran sangat rendah', 'level' => 'Tinggi',
                'detail' => "Capaian baru {$persen}% — berisiko anggaran tidak terserap optimal hingga akhir tahun."];
        } elseif ($persen < 50) {
            $risiko[] = ['risiko' => 'Serapan anggaran di bawah paruh', 'level' => 'Sedang',
                'detail' => "Capaian {$persen}% — perlu percepatan agar target akhir tahun tercapai."];
        }
        if (count($snap['pd_tanpa_realisasi']) > 0) {
            $n = count($snap['pd_tanpa_realisasi']);
            $risiko[] = ['risiko' => 'Perangkat daerah tanpa realisasi', 'level' => $n > 5 ? 'Tinggi' : 'Sedang',
                'detail' => "{$n} perangkat daerah sudah beranggaran namun belum mencatat realisasi."];
        }
        if ($r['pd_kosong'] > 0) {
            $risiko[] = ['risiko' => 'Perencanaan belum lengkap', 'level' => 'Sedang',
                'detail' => "{$r['pd_kosong']} perangkat daerah belum menganggarkan (RAT belum lengkap)."];
        }
        if (! empty($overBudget)) {
            $risiko[] = ['risiko' => 'Realisasi melampaui alokasi', 'level' => 'Tinggi',
                'detail' => count($overBudget) . ' program mencatat realisasi di atas pagu anggaran.'];
        }
        if (empty($risiko)) {
            $risiko[] = ['risiko' => 'Tidak ada risiko signifikan', 'level' => 'Rendah',
                'detail' => 'Capaian dalam batas wajar — lanjutkan pemantauan berkala.'];
        }

        return [
            'tahun'       => $tahun,
            'kpi'         => [
                'total_alokasi'   => $alokasi,
                'total_realisasi' => $realisasi,
                'sisa'            => $sisa,
                'persentase'      => $persen,
                'jumlah_pd'       => $r['jumlah_pd_aktif'],
                'pd_terisi'       => $r['pd_terisi'],
                'total_program'   => $progRows->count(),
                'total_laporan'   => $r['total_laporan'],
            ],
            'kategori'        => $narrative['kategori'],
            'semester'        => ['sem1' => $sem1, 'sem2' => $sem2],
            'burn_rate'       => $burnRate,
            'per_strategi'    => $snap['per_strategi'],
            'pd_tertinggi'    => $snap['pd_tertinggi'],
            'pd_terendah'     => $snap['pd_terendah'],
            'program_teratas' => $programTeratas,
            'over_budget'     => $overBudget,
            'under_budget'    => $underBudget,
            'sumber_dana'     => $sumberDana,
            'yoy'             => $yoy,
            'forecast'        => $forecast,
            'risiko'          => $risiko,
            'narasi'          => $narrative['ringkasan'],
            'sorotan'         => $narrative['sorotan'],
            'rekomendasi'     => $narrative['rekomendasi'],
            'generated_at'    => $snap['generated_at'],
        ];
    }

    public function rupiah(float $n): string
    {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }

    /** Rapikan nama (buang newline/spasi ganda dari data impor). */
    private function cleanName(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
