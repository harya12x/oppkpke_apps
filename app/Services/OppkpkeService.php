<?php

namespace App\Services;

use App\Models\StrategiOppkpke;
use App\Models\PerangkatDaerah;
use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use App\Models\LaporanOppkpke;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class OppkpkeService
{
    // ══════════════════════════════════════════════════════════════
    // DASHBOARD STATISTICS
    // ══════════════════════════════════════════════════════════════

    public function getDashboardStats(int $tahun): array
    {
        $laporan = LaporanOppkpke::tahun($tahun);

        return [
            'total_anggaran' => $laporan->sum('alokasi_anggaran'),
            'total_realisasi' => $laporan->sum('realisasi_total'),
            'persentase_realisasi' => $this->calculatePercentage(
                $laporan->sum('realisasi_total'),
                $laporan->sum('alokasi_anggaran')
            ),
            'total_program' => Program::active()->count(),
            'total_kegiatan' => Kegiatan::active()->count(),
            'total_sub_kegiatan' => SubKegiatan::active()->count(),
            'per_strategi' => $this->getStatsByStrategi($tahun),
        ];
    }

    public function getRekapPerPerangkatDaerah(int $tahun): array
    {
        return \App\Models\PerangkatDaerah::active()
            ->with(['programs.kegiatan.subKegiatan.laporan' => fn($q) => $q->tahun($tahun)])
            ->get()
            ->map(function ($perangkat) {
                $alokasi = 0;
                $realisasi = 0;
                $sem1 = 0;
                $sem2 = 0;

                foreach ($perangkat->programs as $program) {
                    foreach ($program->kegiatan as $kegiatan) {
                        foreach ($kegiatan->subKegiatan as $sub) {
                            foreach ($sub->laporan as $laporan) {
                                $alokasi += $laporan->alokasi_anggaran;
                                $realisasi += $laporan->realisasi_total;
                                $sem1 += $laporan->realisasi_sem1;
                                $sem2 += $laporan->realisasi_sem2;
                            }
                        }
                    }
                }

                return [
                    'id' => $perangkat->id,
                    'nama' => $perangkat->nama,
                    'jenis' => $perangkat->jenis,
                    'alokasi' => $alokasi,
                    'realisasi' => $realisasi,
                    'sem1' => $sem1,
                    'sem2' => $sem2,
                ];
            })
            ->filter(fn($item) => $item['alokasi'] > 0)
            ->sortByDesc('realisasi')
            ->values()
            ->toArray();
    }

    public function getStatsByStrategi(int $tahun): Collection
    {
        return StrategiOppkpke::active()
            ->withCount(['programs'])
            ->get()
            ->map(function ($strategi) use ($tahun) {
                $laporan = LaporanOppkpke::tahun($tahun)->byStrategi($strategi->id)->get();
                
                return [
                    'id' => $strategi->id,
                    'kode' => $strategi->kode,
                    'nama' => $strategi->nama,
                    'icon' => $strategi->icon,
                    'color' => $strategi->color,
                    'total_program' => $strategi->programs_count,
                    'alokasi' => $laporan->sum('alokasi_anggaran'),
                    'realisasi' => $laporan->sum('realisasi_total'),
                    'sem1' => $laporan->sum('realisasi_sem1'),
                    'sem2' => $laporan->sum('realisasi_sem2'),
                    'persentase' => $this->calculatePercentage(
                        $laporan->sum('realisasi_total'),
                        $laporan->sum('alokasi_anggaran')
                    ),
                ];
            });
    }

    // ══════════════════════════════════════════════════════════════
    // HIERARCHICAL DATA RETRIEVAL
    // ══════════════════════════════════════════════════════════════

    public function getHierarchyData(array $filters = []): array
    {
        $strategiId = $filters['strategi_id'] ?? null;
        $perangkatDaerahId = $filters['perangkat_daerah_id'] ?? null;
        $programId = $filters['program_id'] ?? null;
        $kegiatanId = $filters['kegiatan_id'] ?? null;
        $tahun = $filters['tahun'] ?? date('Y');
        $search = $filters['search'] ?? null;

        $query = SubKegiatan::query()
            ->with([
                'kegiatan.program.strategi',
                'kegiatan.program.perangkatDaerah',
                'laporan' => fn($q) => $q->tahun($tahun)
            ])
            ->whereHas('kegiatan.program', function ($q) use ($strategiId, $perangkatDaerahId) {
                if ($strategiId) {
                    $q->where('strategi_id', $strategiId);
                }
                if ($perangkatDaerahId) {
                    $q->where('perangkat_daerah_id', $perangkatDaerahId);
                }
            });

        if ($programId) {
            $query->whereHas('kegiatan', fn($q) => $q->where('program_id', $programId));
        }

        if ($kegiatanId) {
            $query->where('kegiatan_id', $kegiatanId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_sub_kegiatan', 'like', "%{$search}%")
                  ->orWhereHas('kegiatan', fn($kq) => $kq->where('nama_kegiatan', 'like', "%{$search}%"))
                  ->orWhereHas('kegiatan.program', fn($pq) => $pq->where('nama_program', 'like', "%{$search}%"));
            });
        }

        return $query->active()->get()->toArray();
    }

    public function getFilterOptions(array $currentFilters = []): array
    {
        $strategi = StrategiOppkpke::active()->orderBy('nama')->get();
        
        $perangkatDaerah = PerangkatDaerah::active()
            ->when($currentFilters['strategi_id'] ?? null, function ($q, $strategiId) {
                $q->whereHas('programs', fn($pq) => $pq->where('strategi_id', $strategiId));
            })
            ->orderBy('jenis')
            ->orderBy('nama')
            ->get();

        $programs = Program::active()
            ->with('perangkatDaerah')
            ->when($currentFilters['strategi_id'] ?? null, fn($q, $val) => $q->where('strategi_id', $val))
            ->when($currentFilters['perangkat_daerah_id'] ?? null, fn($q, $val) => $q->where('perangkat_daerah_id', $val))
            ->orderBy('nama_program')
            ->get();

        $kegiatan = Kegiatan::active()
            ->when($currentFilters['program_id'] ?? null, fn($q, $val) => $q->where('program_id', $val))
            ->orderBy('nama_kegiatan')
            ->get();

        return [
            'strategi' => $strategi,
            'perangkat_daerah' => $perangkatDaerah,
            'programs' => $programs,
            'kegiatan' => $kegiatan,
            'tahun' => range(date('Y') - 5, date('Y') + 1),
        ];
    }

    

    // ══════════════════════════════════════════════════════════════
    // LAPORAN CRUD
    // ══════════════════════════════════════════════════════════════

    public function getLaporanPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $tahun = $filters['tahun'] ?? date('Y');

        return LaporanOppkpke::query()
            ->with([
                'subKegiatan.kegiatan.program.strategi',
                'subKegiatan.kegiatan.program.perangkatDaerah',
            ])
            ->tahun($tahun)
            ->when($filters['strategi_id'] ?? null, fn($q, $val) => $q->byStrategi($val))
            ->when($filters['perangkat_daerah_id'] ?? null, fn($q, $val) => $q->byPerangkatDaerah($val))
            ->when($filters['program_id'] ?? null, function($q, $val) {
                $q->whereHas('subKegiatan.kegiatan', fn($kq) => $kq->where('program_id', $val));
            })
            ->when($filters['kegiatan_id'] ?? null, function($q, $val) {
                $q->whereHas('subKegiatan', fn($sq) => $sq->where('kegiatan_id', $val));
            })
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->whereHas('subKegiatan', fn($sq) => $sq->where('nama_sub_kegiatan', 'like', "%{$search}%"));
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function createOrUpdateLaporan(int $subKegiatanId, int $tahun, array $data): LaporanOppkpke
    {
        $data['realisasi_total'] = ($data['realisasi_sem1'] ?? 0) + ($data['realisasi_sem2'] ?? 0);
        $data['updated_by'] = auth()->id();

        return LaporanOppkpke::updateOrCreate(
            [
                'sub_kegiatan_id' => $subKegiatanId,
                'tahun' => $tahun,
            ],
            array_merge($data, [
                'created_by' => auth()->id(),
            ])
        );
    }

    public function bulkUpdateLaporan(array $items): int
    {
        $updated = 0;

        foreach ($items as $item) {
            if (isset($item['sub_kegiatan_id'], $item['tahun'])) {
                $this->createOrUpdateLaporan(
                    $item['sub_kegiatan_id'],
                    $item['tahun'],
                    $item
                );
                $updated++;
            }
        }

        return $updated;
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════

    private function calculatePercentage(float $value, float $total): float
    {
        if ($total <= 0) {
            return 0;
        }
        return round(($value / $total) * 100, 2);
    }
}