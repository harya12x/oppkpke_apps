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
use Illuminate\Support\Facades\DB;

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
        $data['semester'] = $data['semester'] ?? 2;
        $data['updated_by'] = auth()->id();
        // created_by TIDAK dimasukkan ke $data di sini — updateOrCreate() mengisi
        // $values ke model baik saat create maupun update, jadi kalau created_by
        // ikut di $values ia akan tertimpa setiap kali laporan yang sama diedit,
        // menghapus jejak siapa pembuat aslinya.
        unset($data['created_by']);

        $laporan = LaporanOppkpke::firstOrNew([
            'sub_kegiatan_id' => $subKegiatanId,
            'tahun' => $tahun,
        ]);

        if (!$laporan->exists) {
            $data['created_by'] = auth()->id();
        }

        $laporan->fill($data);
        $laporan->save();

        return $laporan;
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
    // IMPORT EXECUTION (dipanggil dari queue Job — lihat app/Jobs/)
    // Dipindah dari OppkpkeController::importExecute()/importRatExecute() apa
    // adanya, hanya dibungkus DB::transaction supaya penghapusan replace_year
    // dan loop insert/update jadi satu unit atomik (dulu tidak dalam transaksi
    // sama sekali — kegagalan di tengah loop meninggalkan delete ter-commit
    // tapi insert baru sebagian, tanpa rollback).
    // ══════════════════════════════════════════════════════════════

    public function executeImport(array $cached, array $skip, bool $replaceYear, int $userId): array
    {
        return DB::transaction(function () use ($cached, $skip, $replaceYear, $userId) {
            $imported  = 0;
            $updated   = 0;
            $skipped   = 0;
            $createdSk = 0;
            $deleted   = 0;

            if ($replaceYear) {
                $importSkIds = collect($cached['rows'])
                    ->filter(fn($r) => in_array($r['status'], ['matched', 'new_sk']) && !in_array((string) $r['row_num'], $skip))
                    ->pluck('sub_kegiatan_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();

                if (!empty($importSkIds)) {
                    $deleted = LaporanOppkpke::where('tahun', $cached['tahun'])
                        ->whereNotIn('sub_kegiatan_id', $importSkIds)
                        ->delete();
                }
            }

            foreach ($cached['rows'] as $row) {
                if (in_array($row['status'], ['not_found', 'duplicate'])) { $skipped++; continue; }
                if (in_array((string) $row['row_num'], $skip)) { $skipped++; continue; }

                $skId = $row['sub_kegiatan_id'];

                if ($row['status'] === 'new_sk' && !empty($row['kegiatan_id'])) {
                    $newSk = SubKegiatan::firstOrCreate(
                        ['kegiatan_id' => $row['kegiatan_id'], 'nama_sub_kegiatan' => $row['sub_kegiatan']],
                        ['is_active' => true]
                    );
                    $skId = $newSk->id;
                    if ($newSk->wasRecentlyCreated) $createdSk++;
                }

                if (!$skId) { $skipped++; continue; }

                $exists = LaporanOppkpke::where('sub_kegiatan_id', $skId)
                    ->where('tahun', $cached['tahun'])->first();

                $sem1 = (float) ($row['realisasi_sem1'] ?? 0);
                $sem2 = (float) ($row['realisasi_sem2'] ?? 0);
                $tot  = (float) ($row['realisasi_total'] ?? 0);
                $realisasiTotal = max($tot, $sem1 + $sem2);
                if ($realisasiTotal <= 0 && ($sem1 + $sem2) > 0) $realisasiTotal = $sem1 + $sem2;

                $payload = [
                    'semester'                 => $cached['semester'],
                    'alokasi_anggaran'         => (float) ($row['alokasi_anggaran'] ?? 0),
                    'realisasi_sem1'           => $sem1,
                    'realisasi_sem2'           => $sem2,
                    'realisasi_total'          => $realisasiTotal,
                    'sumber_pembiayaan'        => $row['sumber_pembiayaan'] ?: 'APBD',
                    'sifat_bantuan'            => $row['sifat_bantuan'] ?: null,
                    'lokasi'                   => $row['lokasi'] ?: null,
                    'jumlah_sasaran'           => $row['jumlah_sasaran'] ?: null,
                    'besaran_manfaat'          => $row['besaran_manfaat'] ?: null,
                    'jenis_bantuan'            => $row['jenis_bantuan'] ?: null,
                    'durasi_pemberian'         => $row['durasi_pemberian'] ?: null,
                    'aktivitas_langsung'       => $row['aktivitas_langsung'] ?: null,
                    'aktivitas_tidak_langsung' => $row['aktivitas_tidak_langsung'] ?: null,
                    'aktivitas_penunjang'      => $row['aktivitas_penunjang'] ?: null,
                    'updated_by'               => $userId,
                ];

                if ($exists) {
                    $exists->update($payload);
                    $updated++;
                } else {
                    $payload['sub_kegiatan_id'] = $skId;
                    $payload['tahun']           = $cached['tahun'];
                    $payload['created_by']      = $userId;
                    LaporanOppkpke::create($payload);
                    $imported++;
                }
            }

            return [
                'imported'   => $imported,
                'updated'    => $updated,
                'skipped'    => $skipped,
                'created_sk' => $createdSk,
                'deleted'    => $deleted,
            ];
        });
    }

    public function executeRatImport(array $cached, array $skip, bool $replaceYear, int $userId): array
    {
        return DB::transaction(function () use ($cached, $skip, $replaceYear, $userId) {
            $imported       = 0;
            $updated        = 0;
            $skipped        = 0;
            $createdSk      = 0;
            $createdHier    = 0;
            $createdPd      = 0;
            $skippedDup     = 0;
            $skippedAmbig   = 0;
            $totalAlokasi   = 0.0;   // total alokasi yang benar-benar diproses (import + update)
            $processedSkIds = [];
            // Cache in-memory PD/Program/Kegiatan yang sudah di-resolve dalam batch ini
            // → banyak sub kegiatan dari 1 operator/program tidak query & buat berulang
            // (bukan "insert 1-1"); entitas yang sama dipakai ulang.
            $hierCache = ['pd' => [], 'prog' => [], 'keg' => []];

            foreach ($cached['rows'] as $row) {
                $rowNumStr = (string) $row['row_num'];

                if (in_array($rowNumStr, $skip)) { $skipped++; continue; }
                if ($row['status'] === 'duplicate') { $skippedDup++; continue; }
                // Baris ambigu TIDAK diproses (sistem tak menebak) — admin memperbaiki file.
                if ($row['status'] === 'ambiguous') { $skippedAmbig++; continue; }

                $skId = $row['sub_kegiatan_id'];

                if ($row['status'] === 'new_sk' && !empty($row['kegiatan_id'])) {
                    $newSk = SubKegiatan::firstOrCreate(
                        ['kegiatan_id' => $row['kegiatan_id'], 'nama_sub_kegiatan' => $row['sub_kegiatan']],
                        ['is_active' => true]
                    );
                    $skId = $newSk->id;
                    if ($newSk->wasRecentlyCreated) $createdSk++;
                }

                if ($row['status'] === 'not_found') {
                    $skId = $this->forceCreateSkFromRow($row, $userId, $createdHier, $createdPd, $hierCache);
                    if (!$skId) { $skipped++; continue; }
                }

                if (!$skId) { $skipped++; continue; }

                $processedSkIds[] = $skId;

                // Isi kode sub kegiatan dari prefix nama di file (kolom G) bila kolom
                // kode-nya masih kosong. Tidak menimpa kode yang sudah ada.
                $kodeSub = trim((string) ($row['kode_sub'] ?? ''));
                if ($kodeSub !== '') {
                    SubKegiatan::where('id', $skId)
                        ->where(fn ($q) => $q->whereNull('kode')->orWhere('kode', ''))
                        ->update(['kode' => $kodeSub]);
                }

                $exists = LaporanOppkpke::where('sub_kegiatan_id', $skId)
                    ->where('tahun', $cached['tahun'])->first();

                $payload = [
                    'alokasi_anggaran'         => $row['alokasi_anggaran'],
                    'sumber_pembiayaan'        => $row['sumber_pembiayaan'] ?: 'APBD',
                    'sifat_bantuan'            => $row['sifat_bantuan'] ?: null,
                    'lokasi'                   => $row['lokasi'] ?: null,
                    'jumlah_sasaran'           => $row['jumlah_sasaran'] ?: null,
                    'besaran_manfaat'          => $row['besaran_manfaat'] ?: null,
                    'jenis_bantuan'            => $row['jenis_bantuan'] ?: null,
                    'durasi_pemberian'         => $row['durasi_pemberian'] ?: null,
                    'aktivitas_langsung'       => $row['aktivitas_langsung'] ?: null,
                    'aktivitas_tidak_langsung' => $row['aktivitas_tidak_langsung'] ?: null,
                    'aktivitas_penunjang'      => $row['aktivitas_penunjang'] ?: null,
                    'updated_by'               => $userId,
                ];

                if ($exists) {
                    $exists->update($payload);
                    $updated++;
                } else {
                    $payload['sub_kegiatan_id'] = $skId;
                    $payload['tahun']           = $cached['tahun'];
                    $payload['semester']        = 1;
                    $payload['realisasi_sem1']  = 0;
                    $payload['realisasi_sem2']  = 0;
                    $payload['realisasi_total'] = 0;
                    $payload['created_by']      = $userId;
                    LaporanOppkpke::create($payload);
                    $imported++;
                }

                $totalAlokasi += (float) $row['alokasi_anggaran'];
            }

            $deleted = 0;
            if ($replaceYear && !empty($processedSkIds)) {
                $deleted = LaporanOppkpke::where('tahun', $cached['tahun'])
                    ->whereNotIn('sub_kegiatan_id', array_unique($processedSkIds))
                    ->delete();
            }

            return [
                'imported'      => $imported,
                'updated'       => $updated,
                'skipped'       => $skipped,
                'created_sk'    => $createdSk,
                'created_hier'  => $createdHier,
                'created_pd'    => $createdPd,
                'skipped_dup'   => $skippedDup,
                'skipped_ambig' => $skippedAmbig,
                'total_alokasi' => $totalAlokasi,
                'deleted'       => $deleted,
            ];
        });
    }

    // Membangun hierarki (perangkat daerah → program → kegiatan → sub kegiatan)
    // otomatis dari kolom file saat baris tidak cocok dengan data yang ada.
    // Memakai kode berjenjang (program/kegiatan/sub) & cache in-memory ($cache)
    // agar banyak sub dari satu operator/program tidak dibuat/di-query berulang.
    private function forceCreateSkFromRow(array $row, int $userId, int &$created, int &$createdPd, array &$cache): ?int
    {
        $pdName   = trim($row['perangkat_daerah'] ?? '');
        $progName = trim($row['program'] ?? '');
        $kegName  = trim($row['kegiatan'] ?? '');
        $skName   = trim($row['sub_kegiatan'] ?? '');

        if (!$pdName || !$skName) return null;

        $normalize = fn(string $s) => trim(preg_replace('/\s+/', ' ', strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $s))));

        $kodeProg = trim((string) ($row['kode'] ?? ''));
        $kodeKeg  = trim((string) ($row['kode_kegiatan'] ?? ''));
        $kodeSub  = trim((string) ($row['kode_sub'] ?? ''));

        // ── PERANGKAT DAERAH (cache) ───────────────────────────────────
        $pdKey = $normalize($pdName);
        if (isset($cache['pd'][$pdKey])) {
            $pd = $cache['pd'][$pdKey];
        } else {
            $pd = PerangkatDaerah::all()->first(fn($p) =>
                $normalize($p->nama) === $pdKey ||
                str_contains($normalize($p->nama), $pdKey) ||
                str_contains($pdKey, $normalize($p->nama))
            );
            if (!$pd) {
                $pd = PerangkatDaerah::create(['nama' => $pdName, 'is_active' => true]);
                $createdPd++;
            }
            $cache['pd'][$pdKey] = $pd;
        }

        // ── PROGRAM (cache by pd + kode/nama) ──────────────────────────
        $progCacheKey = $pd->id . '|' . ($kodeProg !== '' ? 'K:' . strtolower($kodeProg) : 'N:' . $normalize($progName));
        if (isset($cache['prog'][$progCacheKey])) {
            $prog = $cache['prog'][$progCacheKey];
        } else {
            $prog = null;
            if ($kodeProg !== '' || $progName !== '') {
                $prog = Program::where('perangkat_daerah_id', $pd->id)->get()->first(fn($p) =>
                    ($kodeProg !== '' && strcasecmp(trim($p->kode_program), $kodeProg) === 0) ||
                    ($progName !== '' && (
                        $normalize($p->nama_program) === $normalize($progName) ||
                        str_contains($normalize($p->nama_program), $normalize($progName)) ||
                        str_contains($normalize($progName), $normalize($p->nama_program))
                    ))
                );
            }
            if (!$prog) {
                // Program baru wajib strategi valid — tanpa fallback diam-diam.
                $strategi = StrategiOppkpke::resolveFromText($row['strategi'] ?? '');
                if (!$strategi) {
                    return null;
                }
                $prog = Program::firstOrCreate(
                    ['strategi_id' => $strategi->id, 'perangkat_daerah_id' => $pd->id, 'kode_program' => $kodeProg],
                    ['nama_program' => ($progName !== '' ? $progName : 'Program (belum diisi)'), 'is_active' => true]
                );
            }
            // Lengkapi kode program bila kosong.
            if ($kodeProg !== '' && trim((string) $prog->kode_program) === '') {
                $prog->kode_program = $kodeProg;
                $prog->save();
            }
            $cache['prog'][$progCacheKey] = $prog;
        }

        // ── KEGIATAN (cache by program + nama) ─────────────────────────
        $kegCacheKey = $prog->id . '|' . $normalize($kegName ?: ('kegiatan ' . $skName));
        if (isset($cache['keg'][$kegCacheKey])) {
            $keg = $cache['keg'][$kegCacheKey];
        } else {
            $keg = null;
            if ($kegName !== '') {
                $keg = Kegiatan::where('program_id', $prog->id)->get()->first(fn($k) =>
                    $normalize($k->nama_kegiatan) === $normalize($kegName) ||
                    str_contains($normalize($k->nama_kegiatan), $normalize($kegName)) ||
                    str_contains($normalize($kegName), $normalize($k->nama_kegiatan))
                );
            }
            if (!$keg) {
                $keg = Kegiatan::firstOrCreate(
                    ['program_id' => $prog->id, 'nama_kegiatan' => $kegName ?: 'Kegiatan ' . $skName],
                    ['is_active' => true, 'kode' => ($kodeKeg !== '' ? $kodeKeg : null)]
                );
            }
            if ($kodeKeg !== '' && trim((string) $keg->kode) === '') {
                $keg->kode = $kodeKeg;
                $keg->save();
            }
            $cache['keg'][$kegCacheKey] = $keg;
        }

        // ── SUB KEGIATAN ───────────────────────────────────────────────
        $sk = SubKegiatan::firstOrCreate(
            ['kegiatan_id' => $keg->id, 'nama_sub_kegiatan' => $skName],
            ['is_active' => true, 'kode' => ($kodeSub !== '' ? $kodeSub : null)]
        );

        if ($sk->wasRecentlyCreated) $created++;
        return $sk->id;
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