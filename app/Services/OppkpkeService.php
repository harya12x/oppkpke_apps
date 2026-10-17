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
            $imported      = 0;
            $updated       = 0;
            $skipped       = 0;
            $createdSk     = 0;
            $deleted       = 0;
            $skippedAmbig  = 0;
            $resolvedAmbig = 0;
            $merged        = 0;
            $outsidePlan   = 0;      // mode realisasi: SK belum punya data tahun ini (di luar RAT)
            $resolvedNf    = 0;      // baris tidak cocok yang dipetakan / dibuat baru oleh admin
            $createdHier   = 0;
            $createdPd     = 0;
            $hierCache     = ['pd' => [], 'prog' => [], 'keg' => []];
            $totalRealisasi = 0.0;
            $batch         = [];     // sub_kegiatan_id => [alokasi, sem1, sem2, total] yang sudah ditulis batch ini

            // "realisasi": hanya Realisasi Sem.1/Sem.2/Total yang ditulis; alokasi & data
            // perencanaan (hasil import RAT) tidak disentuh. "lengkap": perilaku lama.
            $realisasiOnly = ($cached['mode'] ?? 'lengkap') === 'realisasi';
            $realisasiFile = (float) collect($cached['rows'])->where('status', '!=', 'duplicate')
                ->sum(fn ($r) => max((float) ($r['realisasi_total'] ?? 0), (float) ($r['realisasi_sem1'] ?? 0) + (float) ($r['realisasi_sem2'] ?? 0)));

            if ($replaceYear && ! $realisasiOnly) {
                $importSkIds = collect($cached['rows'])
                    ->filter(fn($r) => ! in_array((string) $r['row_num'], $skip)
                        && (in_array($r['status'], ['matched', 'new_sk']) || is_int($r['resolved_to'] ?? null)))
                    ->map(fn ($r) => is_int($r['resolved_to'] ?? null) ? $r['resolved_to'] : $r['sub_kegiatan_id'])
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
                if ($row['status'] === 'duplicate') { $skipped++; continue; }
                if (in_array((string) $row['row_num'], $skip)) { $skipped++; continue; }

                $skId = $row['sub_kegiatan_id'];

                // Baris ambigu / tidak cocok hanya diproses bila admin memilih tindakannya di preview:
                // dipetakan ke sub kegiatan yang ada, atau dibuat baru (PD/program/kegiatan otomatis).
                if (in_array($row['status'], ['ambiguous', 'not_found'], true)) {
                    $target = $row['resolved_to'] ?? null;
                    if ($target === null) {
                        $row['status'] === 'ambiguous' ? $skippedAmbig++ : $skipped++;
                        continue;
                    }
                    $skId = $target === 'new'
                        ? $this->forceCreateSkFromRow($row, $userId, $createdHier, $createdPd, $hierCache)
                        : (int) $target;
                    if (! $skId) { $skipped++; continue; }
                    $row['status'] === 'ambiguous' ? $resolvedAmbig++ : $resolvedNf++;
                }

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
                $alok = (float) ($row['alokasi_anggaran'] ?? 0);
                $totalRealisasi += $realisasiTotal;

                // SK yang sama sudah diisi baris lain di batch ini (mis. baris ambigu yang
                // dipilih ke SK baris lain) → jumlahkan, jangan timpa.
                if (isset($batch[$skId]) && $exists) {
                    $b = &$batch[$skId];
                    [$b[0], $b[1], $b[2], $b[3]] = [$b[0] + $alok, $b[1] + $sem1, $b[2] + $sem2, $b[3] + $realisasiTotal];
                    $exists->update(array_merge(
                        ['realisasi_sem1' => $b[1], 'realisasi_sem2' => $b[2], 'realisasi_total' => $b[3], 'updated_by' => $userId],
                        $realisasiOnly ? [] : ['alokasi_anggaran' => $b[0]]
                    ));
                    unset($b);
                    $merged++;
                    continue;
                }
                $batch[$skId] = [$alok, $sem1, $sem2, $realisasiTotal];

                $payload = [
                    'semester'                 => $cached['semester'],
                    'realisasi_sem1'           => $sem1,
                    'realisasi_sem2'           => $sem2,
                    'realisasi_total'          => $realisasiTotal,
                    'updated_by'               => $userId,
                ];
                $planning = [
                    'alokasi_anggaran'         => $alok,
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
                ];

                if ($exists) {
                    $exists->update($realisasiOnly ? $payload : array_merge($payload, $planning));
                    $updated++;
                } else {
                    // Mode realisasi: SK belum punya data tahun ini (tidak ada di RAT) → tetap
                    // dicatat agar realisasi lengkap, dengan alokasi 0 (alokasi hanya dari RAT).
                    if ($realisasiOnly) {
                        $planning['alokasi_anggaran'] = 0;
                        $outsidePlan++;
                    }
                    LaporanOppkpke::create(array_merge($payload, $planning, [
                        'sub_kegiatan_id' => $skId,
                        'tahun'           => $cached['tahun'],
                        'created_by'      => $userId,
                    ]));
                    $imported++;
                }
            }

            return [
                'mode'            => $realisasiOnly ? 'realisasi' : 'lengkap',
                'imported'        => $imported,
                'updated'         => $updated,
                'skipped'         => $skipped,
                'created_sk'      => $createdSk,
                'deleted'         => $deleted,
                'skipped_ambig'   => $skippedAmbig,
                'resolved_ambig'  => $resolvedAmbig,
                'resolved_nf'     => $resolvedNf,
                'created_hier'    => $createdHier,
                'created_pd'      => $createdPd,
                'merged'          => $merged,
                'outside_plan'    => $outsidePlan,
                'total_realisasi' => $totalRealisasi,
                'realisasi_file'  => $realisasiFile,
                'selisih'         => round(max(0, $realisasiFile - $totalRealisasi), 2),
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
            $resolvedAmbig  = 0;
            $merged         = 0;
            $totalAlokasi   = 0.0;   // total alokasi yang benar-benar diproses (import + update)
            $processedSkIds = [];
            $batchAlok      = [];    // sub_kegiatan_id => alokasi yang sudah ditulis batch ini
            // Total file = semua baris non-duplikat (alokasi duplikat sudah digabung ke baris pertamanya).
            $totalFile = (float) collect($cached['rows'])->where('status', '!=', 'duplicate')
                ->sum(fn ($r) => (float) ($r['alokasi_anggaran'] ?? 0));
            // Cache in-memory PD/Program/Kegiatan yang sudah di-resolve dalam batch ini
            // → banyak sub kegiatan dari 1 operator/program tidak query & buat berulang
            // (bukan "insert 1-1"); entitas yang sama dipakai ulang.
            $hierCache = ['pd' => [], 'prog' => [], 'keg' => []];
            $realigned = 0;          // sub kegiatan yang dipindah ke program strategi sesuai RAT

            foreach ($cached['rows'] as $row) {
                $rowNumStr = (string) $row['row_num'];

                if (in_array($rowNumStr, $skip)) { $skipped++; continue; }
                if ($row['status'] === 'duplicate') { $skippedDup++; continue; }

                $skId = $row['sub_kegiatan_id'];

                // Baris ambigu hanya diproses bila admin memilih tujuannya di preview
                // (sistem tidak menebak). Tanpa pilihan → dilewati.
                if ($row['status'] === 'ambiguous') {
                    $target = $row['resolved_to'] ?? null;
                    if ($target === null) { $skippedAmbig++; continue; }
                    $skId = $target === 'new'
                        ? $this->forceCreateSkFromRow($row, $userId, $createdHier, $createdPd, $hierCache)
                        : (int) $target;
                    if (!$skId) { $skipped++; continue; }
                    $resolvedAmbig++;
                }

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

                // Strategi mengikuti kolom Strategi RAT tahun ini (strategi melekat pada program).
                $skId = $this->alignStrategi((int) $skId, $row, (int) $cached['tahun'], $realigned, $hierCache);

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

                $alok = (float) $row['alokasi_anggaran'];

                // Sub kegiatan ini sudah diisi baris lain di batch yang sama (mis. baris ambigu
                // yang dipilih ke SK yang juga cocok dengan baris lain, atau dua baris "Data Baru"
                // bernama sama) → jumlahkan alokasinya seperti baris duplikat, jangan ditimpa.
                if (isset($batchAlok[$skId]) && $exists) {
                    $batchAlok[$skId] += $alok;
                    $exists->update(['alokasi_anggaran' => $batchAlok[$skId], 'updated_by' => $userId]);
                    $merged++;
                    $totalAlokasi += $alok;
                    continue;
                }
                $batchAlok[$skId] = $alok;

                $payload = [
                    'alokasi_anggaran'         => $alok,
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

                $totalAlokasi += $alok;
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
                'strategi_dipindah' => $realigned,
                'skipped_ambig'  => $skippedAmbig,
                'resolved_ambig' => $resolvedAmbig,
                'merged'         => $merged,
                'total_alokasi'  => $totalAlokasi,
                'total_file'     => $totalFile,
                'selisih'        => round(max(0, $totalFile - $totalAlokasi), 2),
                'deleted'        => $deleted,
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

        // ── PROGRAM (cache by pd + strategi + kode/nama) ───────────────
        // Satu program bisa tercatat di beberapa strategi (mis. "Program Pengelolaan Pendidikan"
        // di strategi 1 DAN 3). Strategi baris menentukan program mana yang dipakai — dulu
        // diambil program pertama berkode sama, sehingga sub kegiatan baru bisa masuk strategi lain.
        $strategi     = StrategiOppkpke::resolveFromText($row['strategi'] ?? '');
        $progCacheKey = $pd->id . '|' . ($strategi?->id ?? '-') . '|' . ($kodeProg !== '' ? 'K:' . strtolower($kodeProg) : 'N:' . $normalize($progName));
        if (isset($cache['prog'][$progCacheKey])) {
            $prog = $cache['prog'][$progCacheKey];
        } else {
            $cands = collect();
            if ($kodeProg !== '' || $progName !== '') {
                $cands = Program::where('perangkat_daerah_id', $pd->id)->get()->filter(fn($p) =>
                    ($kodeProg !== '' && strcasecmp(trim($p->kode_program), $kodeProg) === 0) ||
                    ($progName !== '' && (
                        $normalize($p->nama_program) === $normalize($progName) ||
                        str_contains($normalize($p->nama_program), $normalize($progName)) ||
                        str_contains($normalize($progName), $normalize($p->nama_program))
                    ))
                )->values();
            }
            // Strategi tak dikenali → perilaku lama (program pertama yang cocok).
            $prog = $strategi ? $cands->firstWhere('strategi_id', $strategi->id) : $cands->first();
            if (!$prog) {
                // Program baru wajib strategi valid — tanpa fallback diam-diam.
                if (!$strategi) {
                    return null;
                }
                $base = $cands->first();   // nama/kode diambil dari program yang sama di strategi lain bila ada
                $prog = Program::firstOrCreate(
                    ['strategi_id' => $strategi->id, 'perangkat_daerah_id' => $pd->id,
                     'kode_program' => $kodeProg !== '' ? $kodeProg : (string) optional($base)->kode_program],
                    ['nama_program' => $progName !== '' ? $progName : (optional($base)->nama_program ?: 'Program (belum diisi)'), 'is_active' => true]
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
                $keg = Kegiatan::where('program_id', $prog->id)->get()
                    ->first(fn($k) => $this->namaKegiatanSama($k->nama_kegiatan, $kegName));
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

    /**
     * Nama kegiatan dianggap sama bila identik, salah satu memuat yang lain, atau seluruh
     * kata nama yang lebih pendek (min. 3 kata) ada di nama lain — mis.
     * "Pengelolaan Pendidikan PAUD" = "Pengelolaan Pendidikan Anak Usia Dini (PAUD)".
     */
    private function namaKegiatanSama(string $a, string $b): bool
    {
        $norm = fn (string $s) => trim(preg_replace('/\s+/', ' ', strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $s))));
        [$a, $b] = [$norm($a), $norm($b)];
        if ($a === '' || $b === '') return false;
        if ($a === $b || str_contains($a, $b) || str_contains($b, $a)) return true;
        [$ta, $tb] = [explode(' ', $a), explode(' ', $b)];
        [$ts, $tl] = count($ta) <= count($tb) ? [$ta, $tb] : [$tb, $ta];

        return count($ts) >= 3 && ! array_diff($ts, $tl);
    }

    /**
     * Strategi di aplikasi melekat pada PROGRAM, sedangkan RAT menetapkannya per baris per tahun.
     * Pastikan data tahun ini berada di program dengan strategi sesuai kolom Strategi file:
     *  - sub kegiatan tanpa data tahun lain → dipindah utuh ke kegiatan padanannya;
     *  - sub kegiatan yang punya data tahun lain → dibuatkan padanan di strategi yang benar dan
     *    hanya data TAHUN INI yang dipindah, sehingga laporan tahun lain tidak berubah.
     * Mengembalikan id sub kegiatan yang dipakai untuk tahun ini.
     */
    private function alignStrategi(int $skId, array $row, int $tahun, int &$moved, array &$cache): int
    {
        $cache['strat_all'] ??= StrategiOppkpke::where('is_active', true)->get();
        $want = StrategiOppkpke::resolveFromText($row['strategi'] ?? '', $cache['strat_all']);
        $sk   = SubKegiatan::with('kegiatan.program')->find($skId);
        $keg  = $sk?->kegiatan;
        $prog = $keg?->program;
        if (! $want || ! $prog || (int) $prog->strategi_id === (int) $want->id) {
            return $skId;
        }

        // Program padanan: OPD sama, strategi tujuan, nama program sama.
        $pk = $prog->perangkat_daerah_id . '|' . $want->id . '|' . strtolower(trim($prog->nama_program));
        if (! isset($cache['align_prog'][$pk])) {
            $inPd   = Program::where('perangkat_daerah_id', $prog->perangkat_daerah_id)->where('strategi_id', $want->id)->get();
            $target = $inPd->first(fn ($p) => strtolower(trim($p->nama_program)) === strtolower(trim($prog->nama_program)));
            if (! $target) {
                // Unik (strategi, OPD, kode): bila kode program sudah dipakai program lain di strategi
                // tujuan, pakai kode program Permendagri dari kode sub kegiatan (mis. 3.25.04).
                $kode = (string) $prog->kode_program;
                if ($inPd->contains(fn ($p) => trim($p->kode_program) === trim($kode))) {
                    $seg   = explode('.', trim((string) $sk->kode));
                    $alt   = count($seg) === 6 ? implode('.', array_slice($seg, 0, 3)) : null;
                    $taken = $inPd->first(fn ($p) => trim($p->kode_program) === trim($kode));
                    if ($alt && ! $inPd->contains(fn ($p) => trim($p->kode_program) === $alt)) {
                        $kode = $alt;
                    } else {
                        $target = $taken;   // tak ada kode lain: strategi tetap benar, memakai program yang ada
                    }
                }
                $target ??= Program::create([
                    'strategi_id' => $want->id, 'perangkat_daerah_id' => $prog->perangkat_daerah_id,
                    'kode_program' => $kode, 'nama_program' => $prog->nama_program, 'is_active' => true,
                ]);
            }
            $cache['align_prog'][$pk] = $target;
        }
        $target = $cache['align_prog'][$pk];

        // Kegiatan padanan di program tujuan. Kegiatan menurut RAT baris ini yang dipakai (kegiatan lama
        // di DB bisa keliru akibat import lama); bila kolom Kegiatan kosong, pakai nama kegiatan saat ini.
        $kegName = trim((string) ($row['kegiatan'] ?? '')) ?: $keg->nama_kegiatan;
        $kegKode = trim((string) ($row['kode_kegiatan'] ?? '')) ?: $keg->kode;
        $kk = $target->id . '|' . strtolower($kegName);
        $cache['align_keg'][$kk] ??= Kegiatan::where('program_id', $target->id)->get()
                ->first(fn ($k) => $this->namaKegiatanSama($k->nama_kegiatan, $kegName))
            ?? Kegiatan::create(['program_id' => $target->id, 'nama_kegiatan' => $kegName, 'kode' => $kegKode ?: null, 'is_active' => true]);
        $tKeg = $cache['align_keg'][$kk];

        $moved++;
        if (! LaporanOppkpke::where('sub_kegiatan_id', $sk->id)->where('tahun', '!=', $tahun)->exists()) {
            $sk->update(['kegiatan_id' => $tKeg->id]);
            return $sk->id;
        }

        $twin = SubKegiatan::firstOrCreate(
            ['kegiatan_id' => $tKeg->id, 'nama_sub_kegiatan' => $sk->nama_sub_kegiatan],
            ['kode' => $sk->kode, 'is_active' => true]
        );
        // Data tahun ini ikut ke padanan (beserta realisasinya); tahun lain tetap di sub kegiatan asal.
        $mine = LaporanOppkpke::where('sub_kegiatan_id', $sk->id)->where('tahun', $tahun)->first();
        if ($mine) {
            $theirs = LaporanOppkpke::where('sub_kegiatan_id', $twin->id)->where('tahun', $tahun)->first();
            if (! $theirs) {
                $mine->update(['sub_kegiatan_id' => $twin->id]);
            } else {
                if ((float) $theirs->realisasi_total <= 0 && (float) $mine->realisasi_total > 0) {
                    $theirs->update($mine->only(['realisasi_sem1', 'realisasi_sem2', 'realisasi_total']));
                }
                $mine->delete();
            }
        }

        return $twin->id;
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