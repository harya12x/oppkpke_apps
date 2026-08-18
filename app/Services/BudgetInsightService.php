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
            . ' atau ' . $this->angka($persen) . "%, tergolong {$kategori}. "
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
            'ikhtisar'        => $this->executiveOutcome($tahun),
            'narasi'          => $narrative['ringkasan'],
            'sorotan'         => $narrative['sorotan'],
            'rekomendasi'     => $narrative['rekomendasi'],
            'generated_at'    => $snap['generated_at'],
        ];
    }

    /**
     * IKHTISAR EKSEKUTIF — rangkuman OUTPUT (keluaran) dan OUTCOME (hasil) satu
     * tahun anggaran, dirakit dari data nyata agar bisa dibaca pimpinan dalam
     * hitungan detik tanpa kehilangan detail.
     *
     * Prinsipnya jujur: setiap angka outcome disertai tingkat kelengkapan data
     * pendukungnya, sehingga pembaca tahu seberapa kuat dasar kesimpulannya.
     *
     * @return array<string,mixed>
     */
    public function executiveOutcome(int $tahun): array
    {
        $snap = $this->snapshot($tahun);
        $r    = $snap['ringkasan'];

        $alokasi   = (float) $r['total_alokasi'];
        $realisasi = (float) $r['total_realisasi'];
        $sisa      = max($alokasi - $realisasi, 0.0);
        $persen    = (float) $r['persentase_realisasi'];

        // ── Cakupan struktur: apa yang benar-benar dilaporkan tahun ini ──
        $cakupan = DB::table('laporan_oppkpke as lo')
            ->join('sub_kegiatan as sk', 'lo.sub_kegiatan_id', '=', 'sk.id')
            ->join('kegiatan as k',      'sk.kegiatan_id',     '=', 'k.id')
            ->join('programs as p',      'k.program_id',       '=', 'p.id')
            ->where('lo.tahun', $tahun)
            ->whereNull('lo.deleted_at')
            ->selectRaw('COUNT(DISTINCT p.perangkat_daerah_id) as pd')
            ->selectRaw('COUNT(DISTINCT p.id) as program')
            ->selectRaw('COUNT(DISTINCT k.id) as kegiatan')
            ->selectRaw('COUNT(DISTINCT sk.id) as sub_kegiatan')
            ->first();

        // ── Agregat baris laporan ────────────────────────────────────────
        $agg = DB::table('laporan_oppkpke')
            ->where('tahun', $tahun)->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as baris')
            ->selectRaw('SUM(CASE WHEN alokasi_anggaran > 0 THEN 1 ELSE 0 END) as beranggaran')
            ->selectRaw('SUM(CASE WHEN realisasi_total > 0 THEN 1 ELSE 0 END) as bergerak')
            ->selectRaw('SUM(CASE WHEN alokasi_anggaran > 0 AND realisasi_total <= 0 THEN 1 ELSE 0 END) as diam')
            ->selectRaw('SUM(CASE WHEN alokasi_anggaran > 0 AND realisasi_total <= 0 THEN alokasi_anggaran ELSE 0 END) as anggaran_diam')
            ->first();

        $beranggaran = (int) ($agg->beranggaran ?? 0);
        $bergerak    = (int) ($agg->bergerak ?? 0);
        $diam        = (int) ($agg->diam ?? 0);

        // ── Kelengkapan pengisian kolom kunci (dasar kekuatan outcome) ───
        $kolomKunci = [
            'jumlah_sasaran'   => 'Jumlah sasaran',
            'lokasi'           => 'Lokasi',
            'besaran_manfaat'  => 'Besaran manfaat',
            'durasi_pemberian' => 'Durasi bantuan',
            'jenis_bantuan'    => 'Jenis bantuan',
            'sifat_bantuan'    => 'Sifat bantuan',
        ];
        $qIsi = DB::table('laporan_oppkpke')->where('tahun', $tahun)->whereNull('deleted_at');
        foreach (array_keys($kolomKunci) as $kolom) {
            $qIsi->selectRaw("SUM(CASE WHEN {$kolom} IS NOT NULL AND TRIM({$kolom}) <> '' THEN 1 ELSE 0 END) as isi_{$kolom}");
        }
        $isi     = $qIsi->first();
        $baris   = max((int) ($agg->baris ?? 0), 0);
        $detailK = [];
        $totalIsi = 0;
        foreach ($kolomKunci as $kolom => $label) {
            $n = (int) ($isi->{"isi_{$kolom}"} ?? 0);
            $totalIsi += $n;
            $detailK[] = [
                'label'  => $label,
                'terisi' => $n,
                'persen' => $baris > 0 ? round(($n / $baris) * 100, 1) : 0.0,
            ];
        }
        usort($detailK, fn ($a, $b) => $b['persen'] <=> $a['persen']);
        $kelengkapan = [
            'persen' => ($baris > 0 && $kolomKunci) ? round(($totalIsi / ($baris * count($kolomKunci))) * 100, 1) : 0.0,
            'baris'  => $baris,
            'detail' => $detailK,
        ];

        // ── Isi kolom teks: sasaran, wilayah, jenis & sifat bantuan ──────
        $teks = DB::table('laporan_oppkpke')
            ->where('tahun', $tahun)->whereNull('deleted_at')
            ->get(['jumlah_sasaran', 'satuan_sasaran', 'lokasi', 'jenis_bantuan', 'sifat_bantuan']);

        $wilayah = [];
        $jenis   = [];
        $sifat   = [];
        $sasaran = [];   // satuan => jumlah
        $sasaranEntri = 0;
        $sasaranTerukur = 0;

        foreach ($teks as $t) {
            // Dipisah hanya pada ";" atau baris baru — koma justru bagian dari
            // nama lokasi ("Desa X, Kecamatan Y"), memecahnya bikin salah hitung.
            foreach (preg_split('/[;\n]+/', (string) $t->lokasi) as $lok) {
                $lok = $this->cleanName($lok);
                if ($lok !== '' && mb_strlen($lok) <= 60) {
                    $wilayah[mb_strtolower($lok)] = $lok;
                }
            }

            $jb = $this->cleanName((string) $t->jenis_bantuan);
            if ($jb !== '') {
                $key = mb_strtolower($jb);
                $jenis[$key] = ['nama' => $this->titleCase($jb), 'n' => ($jenis[$key]['n'] ?? 0) + 1];
            }

            $sb = $this->normalisasiSifat((string) $t->sifat_bantuan);
            if ($sb !== '') {
                $sifat[$sb] = ($sifat[$sb] ?? 0) + 1;
            }

            $js = $this->cleanName((string) $t->jumlah_sasaran);
            if ($js === '') {
                continue;
            }
            $sasaranEntri++;
            if (preg_match('/^([0-9][0-9.,]*)\s*(.*)$/u', $js, $m)) {
                $angka  = (float) str_replace(',', '.', str_replace('.', '', $m[1]));
                $satuan = $this->cleanName($m[2]) !== ''
                    ? $this->titleCase($this->potongSatuan($m[2]))
                    : $this->titleCase($this->cleanName((string) $t->satuan_sasaran) ?: 'Sasaran');
                if ($angka > 0) {
                    $sasaranTerukur++;
                    $sasaran[$satuan] = ($sasaran[$satuan] ?? 0) + $angka;
                }
            }
        }

        // Satuan penerima manusia didahulukan — itu yang paling bermakna bagi
        // pimpinan; satuan fisik (hektar, paket, unit) menyusul di belakangnya.
        uksort($sasaran, function ($a, $b) use ($sasaran) {
            $oa = $this->satuanOrang($a) ? 1 : 0;
            $ob = $this->satuanOrang($b) ? 1 : 0;
            return $oa === $ob ? ($sasaran[$b] <=> $sasaran[$a]) : ($ob <=> $oa);
        });
        $sasaranTop = array_slice($sasaran, 0, 4, true);

        uasort($jenis, fn ($a, $b) => $b['n'] <=> $a['n']);
        $jenisTop = array_slice(array_values($jenis), 0, 4);

        arsort($sifat);

        // ── Status / verdict ─────────────────────────────────────────────
        $status = $this->statusCapaian($persen, $realisasi);

        // ── Headline: satu kalimat inti ──────────────────────────────────
        $headline = $realisasi > 0
            ? 'Dari ' . $this->miliar($alokasi) . ' anggaran pengentasan kemiskinan yang direncanakan, '
                . $this->miliar($realisasi) . ' (' . $this->angka($persen) . '%) sudah tersalur ke masyarakat, '
                . 'menyisakan ' . $this->miliar($sisa) . ' yang belum dibelanjakan hingga laporan ini disusun.'
            : 'Anggaran pengentasan kemiskinan ' . $tahun . ' sudah direncanakan sebesar ' . $this->miliar($alokasi)
                . ', namun belum ada realisasi yang tercatat sehingga hasil di lapangan belum dapat diukur.';

        // ── OUTPUT — keluaran yang dikerjakan ────────────────────────────
        $output = [
            [
                'label' => 'Perangkat daerah bergerak',
                'nilai' => $this->angka((int) ($cakupan->pd ?? 0)),
                'dari'  => 'dari ' . $r['jumlah_pd_aktif'] . ' PD aktif',
                'ket'   => $r['pd_kosong'] > 0
                    ? $r['pd_kosong'] . ' PD belum menganggarkan sama sekali'
                    : 'seluruh PD aktif sudah menganggarkan',
                'ikon'  => 'fa-building-columns',
            ],
            [
                'label' => 'Program dijalankan',
                'nilai' => $this->angka((int) ($cakupan->program ?? 0)),
                'dari'  => (int) ($cakupan->kegiatan ?? 0) . ' kegiatan turunan',
                'ket'   => 'rangkaian program lintas ' . count($snap['per_strategi']) . ' strategi OPPKPKE',
                'ikon'  => 'fa-diagram-project',
            ],
            [
                'label' => 'Sub kegiatan terlaporkan',
                'nilai' => $this->angka($baris),
                'dari'  => $beranggaran . ' beranggaran',
                'ket'   => $bergerak . ' sub kegiatan sudah membelanjakan anggaran'
                    . ($beranggaran > 0 ? ' (' . $this->angka(round(($bergerak / $beranggaran) * 100, 1)) . '% dari yang beranggaran)' : ''),
                'ikon'  => 'fa-list-check',
            ],
            [
                'label' => 'Wilayah terjangkau',
                'nilai' => $this->angka(count($wilayah)),
                'dari'  => 'lokasi berbeda tercatat',
                'ket'   => count($wilayah) > 0
                    ? 'contoh: ' . implode(', ', array_map(
                        fn ($w) => mb_strimwidth($w, 0, 26, '…'),
                        array_slice(array_values($wilayah), 0, 3)
                    ))
                    : 'kolom lokasi belum diisi',
                'ikon'  => 'fa-location-dot',
            ],
        ];

        // ── OUTCOME — hasil yang dirasakan ───────────────────────────────
        $rataPerSub = $bergerak > 0 ? $realisasi / $bergerak : 0.0;
        $sasaranTeks = [];
        foreach ($sasaranTop as $satuan => $jml) {
            $sasaranTeks[] = $this->angka($jml) . ' ' . $satuan;
        }
        $sifatTeks = [];
        foreach ($sifat as $namaSifat => $n) {
            $sifatTeks[] = "{$namaSifat} ({$n})";
        }

        $outcome = [
            [
                'label' => 'Anggaran tersalur',
                'nilai' => $this->miliar($realisasi),
                'dari'  => $this->angka($persen) . '% dari pagu',
                'ket'   => 'Semester 1 ' . $this->miliar($snap['semester']['realisasi_sem1'])
                    . ' • Semester 2 ' . $this->miliar($snap['semester']['realisasi_sem2']),
                'ikon'  => 'fa-hand-holding-heart',
            ],
            [
                'label' => 'Manfaat belum tersalur',
                'nilai' => $this->miliar($sisa),
                'dari'  => $this->angka(100 - $persen) . '% pagu tertahan',
                'ket'   => $diam > 0
                    ? $this->miliar((float) ($agg->anggaran_diam ?? 0)) . ' di antaranya ada di ' . $diam . ' sub kegiatan yang belum bergerak sama sekali'
                    : ($beranggaran > 0
                        ? 'seluruh sub kegiatan beranggaran sudah mulai membelanjakan'
                        : 'belum ada sub kegiatan beranggaran pada tahun ini'),
                'ikon'  => 'fa-hourglass-half',
            ],
            [
                'label' => 'Sasaran penerima manfaat',
                'nilai' => ! empty($sasaranTeks) ? $sasaranTeks[0] : 'Belum terukur',
                'dari'  => $sasaranTerukur . ' dari ' . $baris . ' sub kegiatan terkuantifikasi',
                'ket'   => count($sasaranTeks) > 1
                    ? 'juga: ' . implode(' • ', array_slice($sasaranTeks, 1, 3))
                    : ($sasaranEntri > $sasaranTerukur
                        ? ($sasaranEntri - $sasaranTerukur) . ' entri sasaran masih ditulis naratif (belum berupa angka)'
                        : 'lengkapi kolom jumlah sasaran agar hasil terukur'),
                'ikon'  => 'fa-people-group',
            ],
            [
                'label' => 'Rata-rata belanja per sub kegiatan aktif',
                'nilai' => $this->miliar($rataPerSub),
                'dari'  => 'dari ' . $bergerak . ' sub kegiatan aktif',
                'ket'   => ! empty($jenisTop)
                    ? 'jenis bantuan terbanyak: ' . implode(', ', array_map(fn ($j) => $j['nama'], array_slice($jenisTop, 0, 2)))
                    : ($sifatTeks ? 'sifat bantuan: ' . implode(' • ', $sifatTeks) : 'jenis bantuan belum banyak diisi'),
                'ikon'  => 'fa-coins',
            ],
        ];

        // ── Yang berjalan baik ───────────────────────────────────────────
        $positif = [];
        $stratIsi = array_values(array_filter($snap['per_strategi'], fn ($x) => $x['alokasi'] > 0));
        usort($stratIsi, fn ($a, $b) => $b['persentase'] <=> $a['persentase']);
        if (! empty($stratIsi) && $stratIsi[0]['persentase'] > 0) {
            $positif[] = 'Strategi [' . $stratIsi[0]['kode'] . '] ' . $stratIsi[0]['nama']
                . ' memimpin dengan serapan ' . $this->angka($stratIsi[0]['persentase']) . '%.';
        }
        if (! empty($snap['pd_tertinggi'])) {
            $top = $snap['pd_tertinggi'][0];
            $positif[] = $top['nama'] . ' menyalurkan paling besar, ' . $this->miliar($top['realisasi'])
                . ' (' . $this->angka($top['persentase']) . '% dari pagunya).';
        }
        if ($beranggaran > 0 && $bergerak > 0) {
            $positif[] = $bergerak . ' dari ' . $beranggaran . ' sub kegiatan beranggaran sudah berjalan '
                . '(' . $this->angka(round(($bergerak / $beranggaran) * 100, 1)) . '%).';
        }
        if ($r['pd_kosong'] === 0) {
            $positif[] = 'Seluruh ' . $r['jumlah_pd_aktif'] . ' perangkat daerah aktif sudah menganggarkan.';
        }
        if (empty($positif)) {
            $positif[] = 'Perencanaan sudah tersusun — capaian menunggu realisasi dicatat.';
        }

        // ── Yang perlu perhatian ─────────────────────────────────────────
        $perhatian = [];
        if ($baris === 0) {
            $perhatian[] = 'Belum ada satu pun laporan tercatat untuk tahun ' . $tahun . ' — ikhtisar ini belum bisa menggambarkan apa pun.';
        }
        if ($baris > 0 && $persen < 50) {
            $perhatian[] = 'Serapan keseluruhan baru ' . $this->angka($persen) . '%, di bawah titik wajar paruh tahun (50%).';
        }
        if ($diam > 0) {
            $perhatian[] = $diam . ' sub kegiatan beranggaran (' . $this->miliar((float) ($agg->anggaran_diam ?? 0))
                . ') belum mencatat realisasi sama sekali.';
        }
        $nTanpa = count($snap['pd_tanpa_realisasi']);
        if ($nTanpa > 0) {
            $perhatian[] = $nTanpa . ' perangkat daerah sudah beranggaran namun nol realisasi: '
                . implode(', ', array_slice($snap['pd_tanpa_realisasi'], 0, 3))
                . ($nTanpa > 3 ? ', dan ' . ($nTanpa - 3) . ' lainnya.' : '.');
        }
        if ($r['pd_kosong'] > 0) {
            $perhatian[] = $r['pd_kosong'] . ' perangkat daerah aktif belum menganggarkan sehingga tidak terlihat kontribusinya.';
        }
        if ($baris > 0 && $kelengkapan['persen'] < 70) {
            $lemah = end($detailK);
            $perhatian[] = 'Kelengkapan data pendukung baru ' . $this->angka($kelengkapan['persen']) . '%'
                . ($lemah ? ' — paling kosong: ' . $lemah['label'] . ' (' . $this->angka($lemah['persen']) . '%)' : '')
                . ', membuat hasil di lapangan sulit diukur.';
        }
        if (empty($perhatian)) {
            $perhatian[] = 'Tidak ada temuan menonjol — pertahankan ritme pelaporan dan penyerapan.';
        }

        return [
            'tahun'       => $tahun,
            'status'      => $status,
            'headline'    => $headline,
            'persen'      => $persen,
            'alokasi'     => $alokasi,
            'realisasi'   => $realisasi,
            'sisa'        => $sisa,
            'output'      => $output,
            'outcome'     => $outcome,
            'positif'     => $positif,
            'perhatian'   => $perhatian,
            'kelengkapan' => $kelengkapan,
        ];
    }

    /** Label status capaian + warna, dipakai badge Ikhtisar Eksekutif. */
    private function statusCapaian(float $persen, float $realisasi): array
    {
        if ($realisasi <= 0) {
            return ['label' => 'Belum Ada Realisasi', 'level' => 'kritis', 'warna' => 'red',
                    'ikon' => 'fa-circle-exclamation', 'arti' => 'Anggaran sudah direncanakan tetapi belum ada yang dibelanjakan.'];
        }
        if ($persen < 25) {
            return ['label' => 'Serapan Sangat Rendah', 'level' => 'kritis', 'warna' => 'red',
                    'ikon' => 'fa-triangle-exclamation', 'arti' => 'Manfaat ke masyarakat masih sangat terbatas — perlu intervensi segera.'];
        }
        if ($persen < 50) {
            return ['label' => 'Perlu Percepatan', 'level' => 'perhatian', 'warna' => 'amber',
                    'ikon' => 'fa-gauge-high', 'arti' => 'Penyaluran berjalan namun tertinggal dari ritme wajar tahun anggaran.'];
        }
        if ($persen < 80) {
            return ['label' => 'Berjalan Cukup Baik', 'level' => 'baik', 'warna' => 'blue',
                    'ikon' => 'fa-thumbs-up', 'arti' => 'Penyaluran sesuai jalur — jaga konsistensi hingga akhir tahun.'];
        }
        return ['label' => 'Capaian Optimal', 'level' => 'optimal', 'warna' => 'green',
                'ikon' => 'fa-circle-check', 'arti' => 'Hampir seluruh anggaran sudah tersalur menjadi manfaat.'];
    }

    /** "Rp 12,34 M" untuk nilai besar, "Rp 12.345.678" untuk nilai kecil. */
    public function miliar(float $n): string
    {
        if (abs($n) >= 1_000_000_000) {
            return 'Rp ' . number_format($n / 1_000_000_000, 2, ',', '.') . ' M';
        }
        if (abs($n) >= 1_000_000) {
            return 'Rp ' . number_format($n / 1_000_000, 1, ',', '.') . ' Jt';
        }
        return $this->rupiah($n);
    }

    /** Angka gaya Indonesia, desimal dibuang bila bulat. */
    private function angka(float|int $n): string
    {
        return floor((float) $n) == (float) $n
            ? number_format((float) $n, 0, ',', '.')
            : number_format((float) $n, 1, ',', '.');
    }

    /**
     * Satuan sasaran sering ditulis sebagai kalimat panjang ("Keluarga yang
     * Mengikuti Penumbuhan Kesadaran…") — ambil inti satuannya saja.
     */
    private function potongSatuan(string $s): string
    {
        $stop  = ['yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'dengan', 'dalam', 'pada', 'melalui', 'atau', 'serta', 'agar', 'sebagai'];
        $ambil = [];
        foreach (preg_split('/\s+/', $this->cleanName($s)) as $kata) {
            if (in_array(mb_strtolower($kata), $stop, true) || count($ambil) >= 3) {
                break;
            }
            $ambil[] = $kata;
        }
        return implode(' ', $ambil);
    }

    /** Satuan yang menghitung manusia lebih layak jadi angka utama outcome. */
    private function satuanOrang(string $satuan): bool
    {
        return (bool) preg_match(
            '/\b(jiwa|orang|keluarga|kk|rumah tangga|siswa|murid|pelajar|mahasiswa|warga|penduduk|anak|balita|lansia|ibu|petani|nelayan|peternak|pelaku|kelompok|penerima|pekerja|guru|kader|penyandang)\b/iu',
            $satuan
        );
    }

    /** Sifat bantuan diketik bebas ("insidential", "Insedintel") — samakan. */
    private function normalisasiSifat(string $s): string
    {
        $s = mb_strtolower($this->cleanName($s));
        if ($s === '') return '';
        if (str_contains($s, 'insi') || str_contains($s, 'inse')) return 'Insidentil';
        if (str_contains($s, 'rutin')) return 'Rutin';
        return $this->titleCase($s);
    }

    /** Kapital di awal kata, agar teks hasil impor enak dibaca. */
    private function titleCase(string $s): string
    {
        return mb_convert_case($this->cleanName($s), MB_CASE_TITLE, 'UTF-8');
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
