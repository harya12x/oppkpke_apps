<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StrategiOppkpke;
use App\Models\PerangkatDaerah;
use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use App\Models\LaporanOppkpke;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class OppkpkeController extends Controller
{
    // =========================================
    // HALAMAN UTAMA
    // =========================================

    public function index()
    {
        if (auth()->user()->isMaster()) {
            return redirect()->route('oppkpke.matrix');
        }
        return redirect()->route('oppkpke.dashboard');
    }

    // =========================================
    // DASHBOARD (master only)
    // =========================================

    public function dashboard()
    {
        $tahun = request('tahun', date('Y'));
        $user  = auth()->user();

        if ($user->isDaerah()) {
            return $this->dashboardDaerah($tahun, $user);
        }

        $totalAlokasi   = (float) LaporanOppkpke::where('tahun', $tahun)->sum('alokasi_anggaran');
        $totalRealisasi = (float) LaporanOppkpke::where('tahun', $tahun)->sum('realisasi_total');

        // Rekap per strategi — keys must match what dashboard.blade.php expects
        $strategiRows = StrategiOppkpke::where('is_active', true)->orderBy('kode')->get();
        $perStrategi  = $strategiRows->map(function ($s) use ($tahun) {
            $alokasi   = (float) LaporanOppkpke::where('tahun', $tahun)
                ->whereHas('subKegiatan.kegiatan.program', fn($q) => $q->where('strategi_id', $s->id))
                ->sum('alokasi_anggaran');
            $realisasi = (float) LaporanOppkpke::where('tahun', $tahun)
                ->whereHas('subKegiatan.kegiatan.program', fn($q) => $q->where('strategi_id', $s->id))
                ->sum('realisasi_total');
            $sem1 = (float) LaporanOppkpke::where('tahun', $tahun)
                ->whereHas('subKegiatan.kegiatan.program', fn($q) => $q->where('strategi_id', $s->id))
                ->sum('realisasi_sem1');
            $sem2 = (float) LaporanOppkpke::where('tahun', $tahun)
                ->whereHas('subKegiatan.kegiatan.program', fn($q) => $q->where('strategi_id', $s->id))
                ->sum('realisasi_sem2');
            return [
                'id'            => $s->id,
                'kode'          => $s->kode,
                'nama'          => $s->nama,
                'icon'          => $s->icon ?? 'folder',
                'color'         => $s->color ?? 'blue',
                'alokasi'       => $alokasi,
                'realisasi'     => $realisasi,
                'sem1'          => $sem1,
                'sem2'          => $sem2,
                'persentase'    => $alokasi > 0 ? round(($realisasi / $alokasi) * 100, 1) : 0,
                'total_program' => $s->programs()->count(),
            ];
        })->values()->toArray();

        $stats = [
            'total_sub_kegiatan'   => SubKegiatan::count(),
            'total_laporan'        => LaporanOppkpke::where('tahun', $tahun)->count(),
            'total_anggaran'       => $totalAlokasi,
            'total_realisasi'      => $totalRealisasi,
            'persentase_realisasi' => $totalAlokasi > 0 ? round(($totalRealisasi / $totalAlokasi) * 100, 1) : 0,
            'per_strategi'         => $perStrategi,
        ];

        // Rekap per perangkat daerah — numeric-indexed array with 'nama' key
        $rekapPerangkat = LaporanOppkpke::where('tahun', $tahun)
            ->with('subKegiatan.kegiatan.program.perangkatDaerah')
            ->get()
            ->groupBy(fn($l) => optional($l->subKegiatan?->kegiatan?->program?->perangkatDaerah)->nama ?? 'Unknown')
            ->map(fn($group, $nama) => [
                'nama'      => $nama,
                'alokasi'   => (float) $group->sum(fn($l) => (float) $l->alokasi_anggaran),
                'realisasi' => (float) $group->sum(fn($l) => (float) $l->realisasi_total),
            ])
            ->sortByDesc('realisasi')
            ->take(10)
            ->values()
            ->toArray();

        return view('oppkpke.dashboard', compact('stats', 'tahun', 'rekapPerangkat'));
    }

    // =========================================
    // EXPLORER DATA
    // =========================================

    public function explorer()
    {
        $filterOptions = $this->getFilterOptions();
        return view('oppkpke.explorer', compact('filterOptions'));
    }

    public function explorerData(Request $request)
    {
        $tahun = $request->get('tahun', date('Y'));
        $user  = auth()->user();

        $query = SubKegiatan::with([
            'kegiatan.program.strategi',
            'kegiatan.program.perangkatDaerah',
            'laporan' => fn($q) => $q->where('tahun', $tahun),
        ])->where('is_active', true);

        // Daerah user: hanya lihat data milik perangkat daerahnya
        if ($user->isDaerah() && $user->perangkat_daerah_id) {
            $query->whereHas('kegiatan.program', fn($q) => $q->where('perangkat_daerah_id', $user->perangkat_daerah_id));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_sub_kegiatan', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('strategi_id')) {
            $query->whereHas('kegiatan.program', fn($q) => $q->where('strategi_id', $request->strategi_id));
        }

        if ($request->filled('perangkat_daerah_id')) {
            $query->whereHas('kegiatan.program', fn($q) => $q->where('perangkat_daerah_id', $request->perangkat_daerah_id));
        }

        if ($request->filled('program_id')) {
            $query->whereHas('kegiatan', fn($q) => $q->where('program_id', $request->program_id));
        }

        if ($request->filled('kegiatan_id')) {
            $query->where('kegiatan_id', $request->kegiatan_id);
        }

        if ($request->filled('status')) {
            if ($request->status === 'filled') {
                $query->whereHas('laporan', fn($q) => $q->where('tahun', $tahun)->where('alokasi_anggaran', '>', 0));
            } elseif ($request->status === 'empty') {
                $query->where(function ($q) use ($tahun) {
                    $q->whereDoesntHave('laporan', fn($q2) => $q2->where('tahun', $tahun))
                      ->orWhereHas('laporan', fn($q2) => $q2->where('tahun', $tahun)->where('alokasi_anggaran', 0));
                });
            }
        }

        $data = $query->orderBy('id')->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    // =========================================
    // INPUT LAPORAN
    // =========================================

    public function laporan()
    {
        $filterOptions = $this->getFilterOptions();
        return view('oppkpke.index', compact('filterOptions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id'                      => 'nullable|exists:laporan_oppkpke,id',
            'sub_kegiatan_id'         => 'required|exists:sub_kegiatan,id',
            'tahun'                   => 'required|integer|min:2020|max:2035',
            'semester'                => 'nullable|integer|in:1,2',
            'penerima_langsung'       => 'nullable|numeric|min:0',
            'penerima_tidak_langsung' => 'nullable|numeric|min:0',
            'penerima_penunjang'      => 'nullable|numeric|min:0',
            'alokasi_anggaran'        => 'required|numeric|min:0',
            'realisasi_sem1'          => 'nullable|numeric|min:0',
            'realisasi_sem2'          => 'nullable|numeric|min:0',
            'sumber_pembiayaan'       => 'nullable|string|max:50',
            'sifat_bantuan'           => 'nullable|string|max:100',
            'lokasi'                  => 'nullable|string|max:255',
            'jumlah_sasaran'          => 'nullable|string|max:100',
            'satuan_sasaran'          => 'nullable|string|max:50',
            'aktivitas_langsung'      => 'nullable|string',
            'aktivitas_tidak_langsung'=> 'nullable|string',
            'aktivitas_penunjang'     => 'nullable|string',
            'besaran_manfaat'         => 'nullable|string|max:255',
            'jenis_bantuan'           => 'nullable|string|max:100',
            'durasi_pemberian'        => 'nullable|string|max:100',
        ]);

        $validated['realisasi_total'] = ($validated['realisasi_sem1'] ?? 0) + ($validated['realisasi_sem2'] ?? 0);
        $validated['semester']        = $validated['semester'] ?? 2;

        // Daerah user: pastikan sub_kegiatan milik perangkat_daerah mereka
        $user = auth()->user();
        if ($user->isDaerah() && $user->perangkat_daerah_id) {
            $allowed = SubKegiatan::whereHas('kegiatan.program', fn($q) => $q->where('perangkat_daerah_id', $user->perangkat_daerah_id))
                ->pluck('id')
                ->contains($validated['sub_kegiatan_id']);

            if (!$allowed) {
                return response()->json(['success' => false, 'message' => 'Anda tidak berwenang mengisi data ini.'], 403);
            }
        }

        // Track who modified
        $validated['updated_by'] = $user->id;

        try {
            if ($request->filled('id')) {
                $laporan = LaporanOppkpke::findOrFail($request->id);
                $laporan->update($validated);
                $message = 'Data berhasil diperbarui';
            } else {
                $exists = LaporanOppkpke::where('sub_kegiatan_id', $validated['sub_kegiatan_id'])
                    ->where('tahun', $validated['tahun'])
                    ->first();

                if ($exists) {
                    $exists->update($validated);
                    $message = 'Data berhasil diperbarui';
                } else {
                    $validated['created_by'] = $user->id;
                    LaporanOppkpke::create($validated);
                    $message = 'Data berhasil ditambahkan';
                }
            }

            return response()->json(['success' => true, 'message' => $message]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->merge(['id' => $id]);
        return $this->store($request);
    }

    public function destroy($id)
    {
        try {
            $laporan = LaporanOppkpke::findOrFail($id);

            // Daerah user: hanya bisa hapus data miliknya
            $user = auth()->user();
            if ($user->isDaerah() && $user->perangkat_daerah_id) {
                $allowed = SubKegiatan::whereHas('kegiatan.program', fn($q) => $q->where('perangkat_daerah_id', $user->perangkat_daerah_id))
                    ->pluck('id')
                    ->contains($laporan->sub_kegiatan_id);
                if (!$allowed) {
                    return response()->json(['success' => false, 'message' => 'Anda tidak berwenang menghapus data ini.'], 403);
                }
            }

            $laporan->delete();
            return response()->json(['success' => true, 'message' => 'Data berhasil dihapus']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data'], 500);
        }
    }

    // =========================================
    // OPTIONS - CASCADING DROPDOWN
    // =========================================

    public function getStrategi()
    {
        $data = StrategiOppkpke::where('is_active', true)
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'color']);

        return response()->json($data);
    }

    public function getPerangkatDaerah(Request $request)
    {
        $user  = auth()->user();
        $query = PerangkatDaerah::where('is_active', true);

        // Daerah user: hanya tampilkan perangkat daerah mereka sendiri
        if ($user->isDaerah() && $user->perangkat_daerah_id) {
            $query->where('id', $user->perangkat_daerah_id);
        } elseif ($request->filled('strategi_id')) {
            $query->whereHas('programs', fn($q) => $q->where('strategi_id', $request->strategi_id));
        }

        $data = $query->orderBy('nama')->get(['id', 'kode', 'nama', 'singkatan', 'jenis']);

        return response()->json($data);
    }

    public function getPrograms(Request $request)
    {
        $user  = auth()->user();
        $query = Program::where('is_active', true);

        if ($user->isDaerah() && $user->perangkat_daerah_id) {
            $query->where('perangkat_daerah_id', $user->perangkat_daerah_id);
        }

        if ($request->filled('strategi_id')) {
            $query->where('strategi_id', $request->strategi_id);
        }

        if ($request->filled('perangkat_daerah_id')) {
            $query->where('perangkat_daerah_id', $request->perangkat_daerah_id);
        }

        $data = $query->orderBy('kode_program')->get(['id', 'kode_program as kode', 'nama_program']);

        return response()->json($data);
    }

    public function getKegiatan(Request $request)
    {
        $query = Kegiatan::where('is_active', true);

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->program_id);
        }

        $data = $query->orderBy('id')->get(['id', 'kode', 'nama_kegiatan']);

        return response()->json($data);
    }

    public function getSubKegiatan(Request $request)
    {
        $user  = auth()->user();
        $query = SubKegiatan::with(['kegiatan.program.perangkatDaerah:id,nama'])
            ->where('is_active', true);

        if ($user->isDaerah() && $user->perangkat_daerah_id) {
            $query->whereHas('kegiatan.program', fn($q) => $q->where('perangkat_daerah_id', $user->perangkat_daerah_id));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_sub_kegiatan', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%")
                  ->orWhereHas('kegiatan.program.perangkatDaerah', fn($qp) => $qp->where('nama', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('kegiatan_id')) {
            $query->where('kegiatan_id', $request->kegiatan_id);
        }

        $data = $query->orderBy('id')->limit(30)->get(['id', 'kode', 'nama_sub_kegiatan', 'kegiatan_id']);

        return response()->json($data);
    }

    // =========================================
    // IMPORT
    // =========================================

    public function importPage()
    {
        return view('oppkpke.import');
    }

    public function importPreview(Request $request)
    {
        $request->validate([
            'file'     => 'required|file|extensions:csv,xlsx,xls|max:20480',
            'tahun'    => 'required|integer|min:2020|max:2035',
            'semester' => 'required|integer|in:1,2',
        ], [
            'file.required'   => 'Pilih file terlebih dahulu.',
            'file.extensions' => 'Format file harus CSV atau Excel (.xlsx/.xls).',
            'file.max'        => 'Ukuran file maksimal 20MB.',
        ]);

        $file     = $request->file('file');
        $tahun    = (int) $request->tahun;
        $semester = (int) $request->semester;
        $ext      = strtolower($file->getClientOriginalExtension());

        try {
            $rawRows = $this->parseMatrixFile($file->getRealPath(), $ext);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Gagal membaca file: ' . $e->getMessage()])->withInput();
        }

        if (empty($rawRows)) {
            return back()->withErrors(['file' => 'Tidak ada data yang dapat dibaca. Pastikan format file sesuai template matriks OPPKPKE (21 kolom).'])->withInput();
        }

        $matchedRows = $this->matchRowsToSubKegiatan($rawRows, $tahun, $semester);

        // Aggregate financial values of duplicate SK rows into the first occurrence
        // so the first occurrence carries the combined totals from all duplicate rows.
        $skFirstIdx = [];
        foreach ($matchedRows as $idx => $row) {
            if (!in_array($row['status'], ['matched', 'duplicate'])) continue;
            $skId = $row['sub_kegiatan_id'] ?? null;
            if (!$skId) continue;

            if (!isset($skFirstIdx[$skId])) {
                $skFirstIdx[$skId] = $idx;
            } else {
                $firstIdx = $skFirstIdx[$skId];
                $matchedRows[$firstIdx]['alokasi_anggaran'] = ((float)($matchedRows[$firstIdx]['alokasi_anggaran'] ?? 0)) + ((float)($row['alokasi_anggaran'] ?? 0));
                $matchedRows[$firstIdx]['realisasi_sem1']   = ((float)($matchedRows[$firstIdx]['realisasi_sem1']   ?? 0)) + ((float)($row['realisasi_sem1']   ?? 0));
                $matchedRows[$firstIdx]['realisasi_sem2']   = ((float)($matchedRows[$firstIdx]['realisasi_sem2']   ?? 0)) + ((float)($row['realisasi_sem2']   ?? 0));
                $matchedRows[$firstIdx]['realisasi_total']  = ((float)($matchedRows[$firstIdx]['realisasi_total']  ?? 0)) + ((float)($row['realisasi_total']  ?? 0));
            }
        }

        $cacheKey = 'oppkpke_import_' . auth()->id() . '_' . uniqid();
        cache()->put($cacheKey, ['tahun' => $tahun, 'semester' => $semester, 'rows' => $matchedRows], now()->addHours(2));

        $coll = collect($matchedRows);
        $stats = [
            'total'      => count($matchedRows),
            'matched'    => $coll->whereIn('status', ['matched', 'new_sk'])->count(),
            'not_found'  => $coll->where('status', 'not_found')->count(),
            'duplicate'  => $coll->where('status', 'duplicate')->count(),
            'new_sk'     => $coll->where('status', 'new_sk')->count(),
            'update'     => $coll->where('status', 'matched')->where('has_existing', true)->count(),
            'new_record' => $coll->where('status', 'matched')->where('has_existing', false)->count(),
        ];

        return view('oppkpke.import', compact('matchedRows', 'stats', 'tahun', 'semester', 'cacheKey'));
    }

    public function importExecute(Request $request)
    {
        $request->validate(['cache_key' => 'required|string']);

        $cached = cache()->get($request->cache_key);
        if (!$cached) {
            return back()->withErrors(['error' => 'Sesi preview sudah kedaluwarsa (2 jam). Silakan upload ulang.']);
        }

        $user       = auth()->user();
        $skip       = $request->input('skip', []);
        $imported   = 0;
        $updated    = 0;
        $skipped    = 0;
        $createdSk  = 0;
        $deleted    = 0;

        // Sinkronisasi Penuh: hapus record lama tahun ini yang tidak ada di file
        if ($request->boolean('replace_year')) {
            $importSkIds = collect($cached['rows'])
                ->filter(fn($r) => in_array($r['status'], ['matched', 'new_sk']) && !in_array((string)$r['row_num'], $skip))
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
            if (in_array((string)$row['row_num'], $skip)) { $skipped++; continue; }

            $skId = $row['sub_kegiatan_id'];

            // Auto-create sub_kegiatan if it matched a kegiatan name
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
            // Pastikan realisasi_total konsisten: gunakan yang lebih besar antara kolom total atau sem1+sem2
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
                'updated_by'               => $user->id,
            ];

            if ($exists) {
                $exists->update($payload);
                $updated++;
            } else {
                $payload['sub_kegiatan_id'] = $skId;
                $payload['tahun']           = $cached['tahun'];
                $payload['created_by']      = $user->id;
                LaporanOppkpke::create($payload);
                $imported++;
            }
        }

        cache()->forget($request->cache_key);

        $skMsg  = $createdSk > 0 ? ", {$createdSk} sub kegiatan baru dibuat" : '';
        $delMsg = $deleted > 0 ? ", {$deleted} record lama dihapus (sinkronisasi penuh)" : '';
        $msg    = "Import selesai! {$updated} data diperbarui, {$imported} data baru ditambahkan{$skMsg}{$delMsg}, {$skipped} dilewati.";
        return redirect()->route('oppkpke.import')->with('success', $msg);
    }

    // =========================================
    // IMPORT MATRIKS RAT (18 kolom)
    // =========================================

    public function importRatPage()
    {
        return view('oppkpke.import_rat');
    }

    public function importRatPreview(Request $request)
    {
        $request->validate([
            'file'  => 'required|file|extensions:csv,xlsx,xls|max:20480',
            'tahun' => 'required|integer|min:2020|max:2035',
        ], [
            'file.required'   => 'Pilih file terlebih dahulu.',
            'file.extensions' => 'Format file harus CSV atau Excel (.xlsx/.xls).',
            'file.max'        => 'Ukuran file maksimal 20MB.',
        ]);

        $file  = $request->file('file');
        $tahun = (int) $request->tahun;
        $ext   = strtolower($file->getClientOriginalExtension());

        try {
            $rawRows = $this->parseRatFile($file->getRealPath(), $ext);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Gagal membaca file: ' . $e->getMessage()])->withInput();
        }

        if (empty($rawRows)) {
            return back()->withErrors(['file' => 'Tidak ada data yang dapat dibaca. Pastikan format file sesuai template Matriks RAT (18 kolom).'])->withInput();
        }

        $matchedRows = $this->matchRowsToSubKegiatan($rawRows, $tahun, 1);

        // Aggregate alokasi of duplicate rows into first occurrence (same as regular import)
        $ratFirstIdx = [];
        foreach ($matchedRows as $idx => $row) {
            if ($row['status'] === 'duplicate') {
                $skId = $row['sub_kegiatan_id'] ?? null;
                if ($skId && isset($ratFirstIdx[$skId])) {
                    $fi = $ratFirstIdx[$skId];
                    $matchedRows[$fi]['alokasi_anggaran'] = ((float)($matchedRows[$fi]['alokasi_anggaran'] ?? 0))
                                                          + ((float)($row['alokasi_anggaran'] ?? 0));
                }
                continue;
            }
            $skId = $row['sub_kegiatan_id'] ?? null;
            if ($skId && !isset($ratFirstIdx[$skId])) {
                $ratFirstIdx[$skId] = $idx;
            }
        }

        $cacheKey = 'oppkpke_rat_' . auth()->id() . '_' . uniqid();
        cache()->put($cacheKey, ['tahun' => $tahun, 'rows' => $matchedRows], now()->addHours(2));

        $coll = collect($matchedRows);
        $stats = [
            'total'      => count($matchedRows),
            'matched'    => $coll->whereIn('status', ['matched', 'new_sk'])->count(),
            'not_found'  => $coll->where('status', 'not_found')->count(),
            'duplicate'  => $coll->where('status', 'duplicate')->count(),
            'new_sk'     => $coll->where('status', 'new_sk')->count(),
            'update'     => $coll->where('status', 'matched')->where('has_existing', true)->count(),
            'new_record' => $coll->where('status', 'matched')->where('has_existing', false)->count(),
        ];

        return view('oppkpke.import_rat', compact('matchedRows', 'stats', 'tahun', 'cacheKey'));
    }

    public function importRatExecute(Request $request)
    {
        $request->validate(['cache_key' => 'required|string']);

        $cached = cache()->get($request->cache_key);
        if (!$cached) {
            return back()->withErrors(['error' => 'Sesi preview sudah kedaluwarsa (2 jam). Silakan upload ulang.']);
        }

        $user         = auth()->user();
        $skip         = $request->input('skip', []);
        $imported     = 0;
        $updated      = 0;
        $skipped      = 0;
        $createdSk    = 0;
        $createdHier  = 0;
        $createdPd    = 0;
        $processedSkIds = [];

        $skippedDup = 0;
        foreach ($cached['rows'] as $row) {
            $rowNumStr = (string) $row['row_num'];

            // Skip unchecked rows
            if (in_array($rowNumStr, $skip)) { $skipped++; continue; }

            // Skip duplicate rows — alokasi already aggregated into first occurrence
            if ($row['status'] === 'duplicate') { $skippedDup++; continue; }

            $skId = $row['sub_kegiatan_id'];

            // Auto-create sub_kegiatan for new_sk rows
            if ($row['status'] === 'new_sk' && !empty($row['kegiatan_id'])) {
                $newSk = SubKegiatan::firstOrCreate(
                    ['kegiatan_id' => $row['kegiatan_id'], 'nama_sub_kegiatan' => $row['sub_kegiatan']],
                    ['is_active' => true]
                );
                $skId = $newSk->id;
                if ($newSk->wasRecentlyCreated) $createdSk++;
            }

            // not_found rows: auto force-create hierarchy from file columns
            if ($row['status'] === 'not_found') {
                $skId = $this->forceCreateSkFromRow($row, $user->id, $createdHier, $createdPd);
                if (!$skId) { $skipped++; continue; }
            }

            if (!$skId) { $skipped++; continue; }

            $processedSkIds[] = $skId;

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
                'updated_by'               => $user->id,
            ];

            if ($exists) {
                $exists->update($payload);
                $updated++;
            } else {
                $payload['sub_kegiatan_id']  = $skId;
                $payload['tahun']            = $cached['tahun'];
                $payload['semester']         = 1;
                $payload['realisasi_sem1']   = 0;
                $payload['realisasi_sem2']   = 0;
                $payload['realisasi_total']  = 0;
                $payload['created_by']       = $user->id;
                LaporanOppkpke::create($payload);
                $imported++;
            }
        }

        // Sinkronisasi Penuh: hapus record lama tahun ini yang tidak ada di file
        $deleted = 0;
        if ($request->boolean('replace_year') && !empty($processedSkIds)) {
            $deleted = LaporanOppkpke::where('tahun', $cached['tahun'])
                ->whereNotIn('sub_kegiatan_id', array_unique($processedSkIds))
                ->delete();
        }

        cache()->forget($request->cache_key);

        $skMsg   = $createdSk   > 0 ? ", {$createdSk} sub kegiatan baru" : '';
        $hierMsg = $createdHier > 0 ? ", {$createdHier} entri hierarki baru" : '';
        $pdMsg   = $createdPd   > 0 ? ", {$createdPd} perangkat daerah baru" : '';
        $dupMsg  = $skippedDup  > 0 ? ", {$skippedDup} duplikat digabung" : '';
        $delMsg  = $deleted     > 0 ? ", {$deleted} record lama dihapus (sinkronisasi penuh)" : '';
        $msg     = "Import Matriks RAT selesai! {$updated} diperbarui, {$imported} baru{$skMsg}{$hierMsg}{$pdMsg}{$dupMsg}{$delMsg}, {$skipped} dilewati.";
        return redirect()->route('oppkpke.import.rat')->with('success', $msg);
    }

    private function forceCreateSkFromRow(array $row, int $userId, int &$created, int &$createdPd = 0): ?int
    {
        $pdName   = trim($row['perangkat_daerah'] ?? '');
        $progName = trim($row['program'] ?? '');
        $kegName  = trim($row['kegiatan'] ?? '');
        $skName   = trim($row['sub_kegiatan'] ?? '');

        if (!$pdName || !$skName) return null;

        // Find PerangkatDaerah by name (normalize for comparison)
        $normalize = fn(string $s) => strtolower(preg_replace('/\s+/', ' ', trim($s)));
        $pd = PerangkatDaerah::all()->first(fn($p) =>
            $normalize($p->nama) === $normalize($pdName) ||
            str_contains($normalize($p->nama), $normalize($pdName)) ||
            str_contains($normalize($pdName), $normalize($p->nama))
        );
        // Auto-create PD from file if not found — ensures no row is ever silently dropped
        if (!$pd) {
            $pd = PerangkatDaerah::create([
                'nama'      => $pdName,
                'is_active' => true,
            ]);
            $createdPd++;
        }

        // Find or create Program under PD
        $prog = null;
        if ($progName) {
            // Pass 1: fuzzy name match
            $prog = Program::where('perangkat_daerah_id', $pd->id)->get()->first(fn($p) =>
                $normalize($p->nama_program) === $normalize($progName) ||
                str_contains($normalize($p->nama_program), $normalize($progName)) ||
                str_contains($normalize($progName), $normalize($p->nama_program))
            );
            // Pass 2: kode match (catches cases where name differs but kode is unique)
            if (!$prog && !empty($row['kode'])) {
                $prog = Program::where('perangkat_daerah_id', $pd->id)
                    ->where('kode_program', $row['kode'])
                    ->first();
            }
            if (!$prog) {
                $stratNorm = $normalize($row['strategi'] ?? '');
                $strategi  = StrategiOppkpke::where('is_active', true)->get()->first(fn($s) =>
                    str_contains($stratNorm, $normalize($s->kode)) ||
                    str_contains($stratNorm, $normalize($s->nama))
                ) ?? StrategiOppkpke::where('is_active', true)->orderBy('id')->first();

                if ($strategi) {
                    // firstOrCreate on unique constraint to avoid duplicate key errors
                    $prog = Program::firstOrCreate(
                        [
                            'strategi_id'         => $strategi->id,
                            'perangkat_daerah_id' => $pd->id,
                            'kode_program'        => $row['kode'] ?? '',
                        ],
                        [
                            'nama_program' => $progName,
                            'is_active'    => true,
                        ]
                    );
                }
            }
        }

        // Fallback: use first program under this PD
        if (!$prog) {
            $prog = Program::where('perangkat_daerah_id', $pd->id)->first();
        }
        if (!$prog) return null;

        // Find or create Kegiatan under Program
        $keg = null;
        if ($kegName) {
            $keg = Kegiatan::where('program_id', $prog->id)->get()->first(fn($k) =>
                $normalize($k->nama_kegiatan) === $normalize($kegName) ||
                str_contains($normalize($k->nama_kegiatan), $normalize($kegName)) ||
                str_contains($normalize($kegName), $normalize($k->nama_kegiatan))
            );
        }
        if (!$keg) {
            $keg = Kegiatan::firstOrCreate(
                ['program_id' => $prog->id, 'nama_kegiatan' => $kegName ?: 'Kegiatan ' . $skName],
                ['is_active' => true]
            );
        }

        // Create SubKegiatan
        $sk = SubKegiatan::firstOrCreate(
            ['kegiatan_id' => $keg->id, 'nama_sub_kegiatan' => $skName],
            ['is_active' => true]
        );

        if ($sk->wasRecentlyCreated) $created++;
        return $sk->id;
    }

    private function parseRatFile(string $filePath, string $ext): array
    {
        if ($ext === 'csv' || $ext === 'txt') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
            $reader->setDelimiter(',');
            $reader->setEnclosure('"');
            $reader->setInputEncoding('UTF-8');
        } elseif ($ext === 'xls') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        } else {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        }

        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $maxRow      = $sheet->getHighestRow();

        // Normalize a cell value to a plain trimmed string
        $cell = fn(string $col, int $row) => trim((string) $sheet->getCell($col . $row)->getValue());

        // ── Robust dataStartRow detection ─────────────────────────────────
        // Strategy 1: numbering row "1  2  3 ..." — handle "1", "1.", "(1)"
        // Strategy 2: "No" header row followed by a numbering row
        // Strategy 3: fallback — scan for first row with non-empty col G AND numeric col K
        $dataStartRow = 5; // safe default

        $stripToInt = fn(string $v): string => preg_replace('/[^0-9]/', '', $v);

        for ($r = 1; $r <= min($maxRow, 30); $r++) {
            $a = $cell('A', $r);
            $b = $cell('B', $r);

            // Strategy 1: col A digit == 1 and col B digit == 2 (handles "1.", "(1)" etc.)
            if ($stripToInt($a) === '1' && ($stripToInt($b) === '2' || $b === '')) {
                $c = $cell('C', $r);
                if ($stripToInt($c) === '3' || $stripToInt($b) === '2') {
                    $dataStartRow = $r + 1;
                    break;
                }
            }

            // Strategy 2: "No" or "No." in col A, "Strategi" anywhere in col B
            if (preg_match('/^no\.?$/i', $a) && stripos($b, 'strategi') !== false) {
                for ($r2 = $r + 1; $r2 <= $r + 10; $r2++) {
                    $a2 = $cell('A', $r2);
                    if ($stripToInt($a2) === '1') {
                        $dataStartRow = $r2 + 1;
                        break 2;
                    }
                }
                // numbering row not found within next 10 rows — data starts right after header
                $dataStartRow = $r + 1;
                break;
            }

            // Strategy 2b: "Strategi" in col A (some templates put label there)
            if (stripos($a, 'strategi') !== false && stripos($b, 'perangkat') !== false) {
                for ($r2 = $r + 1; $r2 <= $r + 10; $r2++) {
                    if ($stripToInt($cell('A', $r2)) === '1') {
                        $dataStartRow = $r2 + 1;
                        break 2;
                    }
                }
                $dataStartRow = $r + 1;
                break;
            }
        }

        // Strategy 3: if default still at 5, scan forward for the first actual data row
        // (col G non-empty, not a header keyword, col K has a parseable number)
        if ($dataStartRow === 5) {
            $headerWords = ['no', 'strategi', 'perangkat', 'kegiatan', 'alokasi', 'sub kegiatan', 'program', 'kode', 'sumber'];
            for ($r = 2; $r <= min($maxRow, 40); $r++) {
                $g = $cell('G', $r);
                $k = $this->parseCellNumber($sheet->getCell('K' . $r)->getValue(), $cell('K', $r));
                if ($g !== '' && $k > 0 && !in_array(strtolower($g), $headerWords, true)) {
                    $dataStartRow = $r;
                    break;
                }
            }
        }

        // ── Parse data rows ───────────────────────────────────────────────
        // RAT format A–R (18 cols):
        // A=No, B=Strategi, C=PD, D=Kode, E=Program, F=Kegiatan, G=SubKegiatan
        // H=Langsung, I=TidakLangsung, J=Penunjang, K=Alokasi
        // L=Sumber, M=Sifat, N=Lokasi, O=JmlSasaran, P=Besaran, Q=Jenis, R=Durasi

        $currentStrategi = '';
        $currentPd       = '';
        $currentKode     = '';
        $currentProgram  = '';
        $currentKegiatan = '';
        $rows            = [];

        for ($r = $dataStartRow; $r <= $maxRow; $r++) {
            $colA = $cell('A', $r);
            $colB = $cell('B', $r);
            $colC = $cell('C', $r);
            $colD = $cell('D', $r);
            $colE = $cell('E', $r);
            $colF = $cell('F', $r);
            $colG = $cell('G', $r);
            $colH = $cell('H', $r);
            $colI = $cell('I', $r);
            $colJ = $cell('J', $r);
            $colK = $cell('K', $r);
            $colL = $cell('L', $r);
            $colM = $cell('M', $r);
            $colN = $cell('N', $r);
            $colO = $cell('O', $r);
            $colP = $cell('P', $r);
            $colQ = $cell('Q', $r);
            $colR = $cell('R', $r);

            // Skip entirely empty rows
            if ($colA === '' && $colB === '' && $colG === '' && $colK === '') continue;

            // Stop at footnotes / keterangan
            if (stripos($colA, 'Keterangan') !== false || stripos($colB, 'Keterangan') !== false) break;

            // Skip residual header rows (col G is a known header keyword)
            $gLower = strtolower($colG);
            if (in_array($gLower, ['sub kegiatan', 'sub-kegiatan', 'nama sub kegiatan', '7'], true)) continue;

            // Strategi/section header: B or A has text, G is empty, K is empty
            if ($colG === '' && $colK === '') {
                if ($colB !== '') { $currentStrategi = $colB; continue; }
                if ($colA !== '' && !is_numeric($colA)) { $currentStrategi = $colA; continue; }
                continue;
            }

            // Data row must have sub kegiatan name (col G)
            if ($colG === '') continue;

            // Strip "kode prefix" from cols that may embed it: G (SK), F (Kegiatan), E (Program)
            // Pattern: leading digits+dots (≥5 chars) followed by a space then the name
            $stripKodePrefix = function(string $v): array {
                $v = trim(str_replace(["\r\n", "\r", "\n"], ' ', $v));
                if (preg_match('/^(\d[\d.]{4,})\s+(.+)$/', $v, $m)) {
                    return [trim($m[1]), trim($m[2])]; // [kode, name]
                }
                return [null, $v];
            };

            [$skKodeFromG, $colG] = $stripKodePrefix($colG);
            [, $colF] = $stripKodePrefix($colF);
            [, $colE] = $stripKodePrefix($colE);

            // Inherit hierarchical context from filled columns
            if ($colB !== '' && stripos($colB, 'strategi') === false) $currentStrategi = $colB;
            if ($colC !== '') $currentPd       = $colC;
            if ($colD !== '') $currentKode     = $colD;
            if ($colE !== '') $currentProgram  = $colE;
            if ($colF !== '') $currentKegiatan = $colF;

            // Prefer the more specific SK kode extracted from col G over the program kode in col D
            $effectiveKode = $skKodeFromG ?? $currentKode;

            $alokasi = $this->parseCellNumber($sheet->getCell('K' . $r)->getValue(), $colK);

            $rows[] = [
                'strategi'                 => $currentStrategi,
                'perangkat_daerah'         => $currentPd,
                'kode'                     => $effectiveKode,
                'program'                  => $currentProgram,
                'kegiatan'                 => $currentKegiatan,
                'sub_kegiatan'             => $colG,
                'aktivitas_langsung'       => $colH,
                'aktivitas_tidak_langsung' => $colI,
                'aktivitas_penunjang'      => $colJ,
                'alokasi_anggaran'         => $alokasi,
                'sumber_pembiayaan'        => $colL ?: 'APBD',
                'sifat_bantuan'            => $colM,
                'lokasi'                   => $colN,
                'jumlah_sasaran'           => $colO,
                'besaran_manfaat'          => $colP,
                'jenis_bantuan'            => $colQ,
                'durasi_pemberian'         => $colR,
                'realisasi_sem1'           => 0,
                'realisasi_sem2'           => 0,
                'realisasi_total'          => 0,
            ];
        }

        return $rows;
    }

    private function parseMatrixFile(string $filePath, string $ext): array
    {
        if ($ext === 'csv' || $ext === 'txt') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
            $reader->setDelimiter(',');
            $reader->setEnclosure('"');
            $reader->setInputEncoding('UTF-8');
        } elseif ($ext === 'xls') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        } else {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        }

        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $maxRow      = $sheet->getHighestRow();
        $colLetters  = range('A', 'U'); // 21 columns A–U

        // Find data start: look for the "1  2  3 ... 21" number header row
        $dataStartRow = 7;
        for ($r = 1; $r <= min($maxRow, 25); $r++) {
            $a = trim((string) $sheet->getCell('A' . $r)->getValue());
            $b = trim((string) $sheet->getCell('B' . $r)->getValue());
            if ($a === '1' && ($b === '2' || $b === '')) {
                // Check if this is the column-number row (1,2,3...)
                $c3 = trim((string) $sheet->getCell('C' . $r)->getValue());
                if ($c3 === '3' || $b === '2') {
                    $dataStartRow = $r + 1;
                    break;
                }
            }
            // Also detect by "No" label in A
            if (strtolower($a) === 'no' && stripos($b, 'strategi') !== false) {
                for ($r2 = $r + 1; $r2 <= $r + 6; $r2++) {
                    $a2 = trim((string) $sheet->getCell('A' . $r2)->getValue());
                    $b2 = trim((string) $sheet->getCell('B' . $r2)->getValue());
                    if ($a2 === '1' && $b2 === '2') { $dataStartRow = $r2 + 1; break 2; }
                }
            }
        }

        $currentStrategi = '';
        $currentPd       = '';
        $currentKode     = '';
        $currentProgram  = '';
        $currentKegiatan = '';
        $rows            = [];

        for ($r = $dataStartRow; $r <= $maxRow; $r++) {
            $col = [];
            foreach ($colLetters as $letter) {
                $raw   = $sheet->getCell($letter . $r)->getValue();
                $col[] = is_null($raw) ? '' : trim((string) $raw);
            }

            // Skip fully empty rows
            if (count(array_filter($col, fn($v) => $v !== '')) < 2) continue;

            // Stop at "Keterangan" footnote section
            if (stripos($col[0], 'Keterangan') !== false || stripos($col[1], 'Keterangan') !== false) break;

            // Strategi header: col B has text, col G empty, col K empty
            if ($col[1] !== '' && $col[6] === '' && $col[10] === '') {
                $currentStrategi = $col[1];
                continue;
            }

            // Data row needs a sub_kegiatan name (col G, index 6)
            if ($col[6] === '') continue;

            // Parse numeric values — prefer raw getValue() for XLSX numeric cells
            $alokasi = $this->parseCellNumber($sheet->getCell('K' . $r)->getValue(), $col[10]);
            $sem1    = $this->parseCellNumber($sheet->getCell('S' . $r)->getValue(), $col[18]);
            $sem2    = $this->parseCellNumber($sheet->getCell('T' . $r)->getValue(), $col[19] ?? '');
            $total   = $this->parseCellNumber($sheet->getCell('U' . $r)->getValue(), $col[20] ?? '');

            if ($total <= 0 && ($sem1 + $sem2) > 0) $total = $sem1 + $sem2;

            // Inherit hierarchical context from non-empty cells
            if ($col[2] !== '') $currentPd       = $col[2];
            if ($col[3] !== '') $currentKode     = $col[3];
            if ($col[4] !== '') $currentProgram  = $col[4];
            if ($col[5] !== '') $currentKegiatan = $col[5];

            $rows[] = [
                'strategi'                 => $currentStrategi,
                'perangkat_daerah'         => $currentPd,
                'kode'                     => $currentKode,
                'program'                  => $currentProgram,
                'kegiatan'                 => $currentKegiatan,
                'sub_kegiatan'             => $col[6],
                'aktivitas_langsung'       => $col[7],
                'aktivitas_tidak_langsung' => $col[8],
                'aktivitas_penunjang'      => $col[9],
                'alokasi_anggaran'         => $alokasi,
                'sumber_pembiayaan'        => $col[11] ?: 'APBD',
                'sifat_bantuan'            => $col[12],
                'lokasi'                   => $col[13],
                'jumlah_sasaran'           => $col[14],
                'besaran_manfaat'          => $col[15],
                'jenis_bantuan'            => $col[16],
                'durasi_pemberian'         => $col[17],
                'realisasi_sem1'           => $sem1,
                'realisasi_sem2'           => $sem2,
                'realisasi_total'          => $total,
            ];
        }

        return $rows;
    }

    private function parseCellNumber($rawVal, string $strFallback): float
    {
        if (is_numeric($rawVal)) return (float) $rawVal;
        $s = trim((string) $strFallback);
        if ($s === '' || $s === '-') return 0.0;
        $s = preg_replace('/[Rp\s\t]/', '', $s);
        $s = str_replace(',', '', $s); // remove English comma thousands
        $parts = explode('.', $s);
        if (count($parts) > 2) return (float) implode('', $parts); // Indonesian thousands dots
        if (count($parts) === 2 && strlen($parts[1]) === 3) return (float) implode('', $parts);
        return (float) $s;
    }

    private function matchRowsToSubKegiatan(array $rawRows, int $tahun, int $semester): array
    {
        $allSk = SubKegiatan::with(['kegiatan.program.perangkatDaerah', 'kegiatan.program.strategi'])
            ->where('is_active', true)->get();

        // Build sub_kegiatan lookup maps
        $skByName     = $allSk->groupBy(fn($sk) => $this->normalizeSkName($sk->nama_sub_kegiatan));
        $skByKodeName = $allSk->groupBy(fn($sk) =>
            (optional($sk->kegiatan?->program)->kode_program ?? '') . '||' . $this->normalizeSkName($sk->nama_sub_kegiatan)
        );

        // Build kegiatan lookup map for Tier 4 (auto-create new SK)
        $allKegiatan    = Kegiatan::with(['program.perangkatDaerah', 'program.strategi'])->get();
        $kegiatanByName = $allKegiatan->groupBy(fn($k) => $this->normalizeSkName($k->nama_kegiatan));

        $existingIds = LaporanOppkpke::where('tahun', $tahun)->pluck('sub_kegiatan_id')->flip();

        $result     = [];
        $rowNum     = 0;
        $seenSkIds  = []; // track already-matched SK IDs to detect duplicate file rows

        foreach ($rawRows as $raw) {
            $rowNum++;
            $normalized = $this->normalizeSkName($raw['sub_kegiatan']);
            $kode       = trim($raw['kode']);
            $pdName     = strtolower(trim($raw['perangkat_daerah']));

            $matched = null;

            // Tier 1: Exact normalized name match
            $candidates = $skByName->get($normalized, collect());
            if ($candidates->count() === 1) {
                $matched = $candidates->first();
            } elseif ($candidates->count() > 1) {
                if ($kode) {
                    $byKode = $candidates->filter(fn($sk) => optional($sk->kegiatan?->program)->kode_program === $kode);
                    $matched = $byKode->first();
                }
                if (!$matched && $pdName) {
                    $byPd = $candidates->filter(fn($sk) =>
                        strtolower(optional($sk->kegiatan?->program?->perangkatDaerah)->nama ?? '') === $pdName
                    );
                    $matched = $byPd->first() ?? $candidates->first();
                }
                if (!$matched) $matched = $candidates->first();
            }

            // Tier 2: Kode + name composite key
            if (!$matched && $kode) {
                $matched = $skByKodeName->get("{$kode}||{$normalized}", collect())->first();
            }

            // Tier 3: Fuzzy prefix match (min 20 chars)
            if (!$matched && strlen($normalized) >= 20) {
                $prefix = substr($normalized, 0, 25);
                $fuzzy  = $allSk->filter(fn($sk) =>
                    str_contains($this->normalizeSkName($sk->nama_sub_kegiatan), $prefix) ||
                    str_contains($prefix, substr($this->normalizeSkName($sk->nama_sub_kegiatan), 0, 25))
                );
                if ($fuzzy->count() === 1) {
                    $matched = $fuzzy->first();
                } elseif ($fuzzy->count() > 1 && $pdName) {
                    $narrowed = $fuzzy->filter(fn($sk) =>
                        strtolower(optional($sk->kegiatan?->program?->perangkatDaerah)->nama ?? '') === $pdName
                    );
                    $matched = $narrowed->first() ?? $fuzzy->first();
                }
            }

            if ($matched) {
                // Duplicate: same sub_kegiatan already matched by an earlier row in this file
                if (isset($seenSkIds[$matched->id])) {
                    $result[] = array_merge($raw, [
                        'row_num'         => $rowNum,
                        'status'          => 'duplicate',
                        'sub_kegiatan_id' => $matched->id,
                        'kegiatan_id'     => null,
                        'matched_sk_nama' => $matched->nama_sub_kegiatan,
                        'matched_pd_nama' => optional($matched->kegiatan?->program?->perangkatDaerah)->nama,
                        'matched_strategi'=> optional($matched->kegiatan?->program?->strategi)->nama,
                        'has_existing'    => isset($existingIds[$matched->id]),
                    ]);
                    continue;
                }

                $seenSkIds[$matched->id] = true;
                $result[] = array_merge($raw, [
                    'row_num'         => $rowNum,
                    'status'          => 'matched',
                    'sub_kegiatan_id' => $matched->id,
                    'kegiatan_id'     => null,
                    'matched_sk_nama' => $matched->nama_sub_kegiatan,
                    'matched_pd_nama' => optional($matched->kegiatan?->program?->perangkatDaerah)->nama,
                    'matched_strategi'=> optional($matched->kegiatan?->program?->strategi)->nama,
                    'has_existing'    => isset($existingIds[$matched->id]),
                ]);
                continue;
            }

            // Tier 4: Sub kegiatan name matches a kegiatan name → will auto-create new SK
            $kegCandidates = $kegiatanByName->get($normalized, collect());
            if ($kegCandidates->isNotEmpty()) {
                // Narrow by PD if ambiguous
                $keg = $kegCandidates->count() === 1
                    ? $kegCandidates->first()
                    : ($pdName
                        ? ($kegCandidates->filter(fn($k) =>
                            strtolower(optional($k->program?->perangkatDaerah)->nama ?? '') === $pdName
                          )->first() ?? $kegCandidates->first())
                        : $kegCandidates->first());

                $result[] = array_merge($raw, [
                    'row_num'         => $rowNum,
                    'status'          => 'new_sk',
                    'sub_kegiatan_id' => null,
                    'kegiatan_id'     => $keg->id,
                    'matched_sk_nama' => '[Baru] ' . $raw['sub_kegiatan'],
                    'matched_pd_nama' => optional($keg->program?->perangkatDaerah)->nama,
                    'matched_strategi'=> optional($keg->program?->strategi)->nama,
                    'has_existing'    => false,
                ]);
                continue;
            }

            // Tier 5: Look up kegiatan by file's kegiatan column name (for project-specific SK names)
            if (!empty($raw['kegiatan'])) {
                $normalizedKeg5   = $this->normalizeSkName($raw['kegiatan']);
                $keg5Candidates   = $kegiatanByName->get($normalizedKeg5, collect());

                if ($keg5Candidates->isNotEmpty()) {
                    $keg5 = $keg5Candidates->count() === 1
                        ? $keg5Candidates->first()
                        : ($kode
                            ? ($keg5Candidates->filter(fn($k) =>
                                optional($k->program)->kode_program === $kode
                              )->first()
                              ?? ($pdName
                                    ? ($keg5Candidates->filter(fn($k) =>
                                        strtolower(optional($k->program?->perangkatDaerah)->nama ?? '') === $pdName
                                      )->first() ?? $keg5Candidates->first())
                                    : $keg5Candidates->first()))
                            : ($pdName
                                ? ($keg5Candidates->filter(fn($k) =>
                                    strtolower(optional($k->program?->perangkatDaerah)->nama ?? '') === $pdName
                                  )->first() ?? $keg5Candidates->first())
                                : $keg5Candidates->first()));

                    $result[] = array_merge($raw, [
                        'row_num'         => $rowNum,
                        'status'          => 'new_sk',
                        'sub_kegiatan_id' => null,
                        'kegiatan_id'     => $keg5->id,
                        'matched_sk_nama' => '[Baru] ' . $raw['sub_kegiatan'],
                        'matched_pd_nama' => optional($keg5->program?->perangkatDaerah)->nama,
                        'matched_strategi'=> optional($keg5->program?->strategi)->nama,
                        'has_existing'    => false,
                    ]);
                    continue;
                }
            }

            // Truly unmatched
            $result[] = array_merge($raw, [
                'row_num'         => $rowNum,
                'status'          => 'not_found',
                'sub_kegiatan_id' => null,
                'kegiatan_id'     => null,
                'matched_sk_nama' => null,
                'matched_pd_nama' => null,
                'matched_strategi'=> null,
                'has_existing'    => false,
            ]);
        }

        return $result;
    }

    private function normalizeSkName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = str_replace(['/', '\\', '(', ')', '"', "'", ','], [' ', ' ', ' ', ' ', '', '', ''], $name);
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    // =========================================
    // EXPORT
    // =========================================

    public function matrixReview(Request $request)
    {
        $tahun         = $request->get('tahun', date('Y'));
        $filterOptions = $this->getFilterOptions();
        $rows          = $this->buildMatrixRows($tahun, $request);

        // Totals direct from DB — single source of truth, matches all other pages
        $user   = auth()->user();
        $totQ   = LaporanOppkpke::where('tahun', $tahun);
        if ($user->isDaerah() && $user->perangkat_daerah_id) {
            $totQ->byPerangkatDaerah($user->perangkat_daerah_id);
        }
        if ($request->filled('strategi_id'))       $totQ->byStrategi($request->strategi_id);
        if ($request->filled('perangkat_daerah_id')) $totQ->byPerangkatDaerah($request->perangkat_daerah_id);

        $totals = [
            'alokasi'   => (float) (clone $totQ)->sum('alokasi_anggaran'),
            'sem1'      => (float) (clone $totQ)->sum('realisasi_sem1'),
            'sem2'      => (float) (clone $totQ)->sum('realisasi_sem2'),
            'realisasi' => (float) (clone $totQ)->sum('realisasi_total'),
        ];

        return view('oppkpke.matrix', compact('rows', 'tahun', 'filterOptions', 'totals'));
    }

    public function exportExcel(Request $request)
    {
        $tahun    = $request->get('tahun', date('Y'));
        $semester = $request->get('semester', 2);
        $rows     = $this->buildMatrixRows($tahun, $request);

        $semLabel  = $semester == 1 ? 'I (JANUARI - JUNI)' : 'II (JULI - DESEMBER)';
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Matriks OPPKPKE');

        // ── Title rows ──────────────────────────────────────────
        $sheet->mergeCells('A1:U1');
        $sheet->setCellValue('A1', 'LAPORAN OPTIMALISASI PELAKSANAAN PENGENTASAN KEMISKINAN DAN PENGHAPUSAN KEMISKINAN EKSTREM (OPPKPKE)');
        $sheet->mergeCells('A2:U2');
        $sheet->setCellValue('A2', "SEMESTER {$semLabel} TAHUN {$tahun}");

        foreach (['A1', 'A2'] as $cell) {
            $sheet->getStyle($cell)->applyFromArray([
                'font'      => ['bold' => true, 'size' => 12],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(18);

        // ── Header row 1 (group labels) ─────────────────────────
        $hRow = 4;
        $headers1 = [
            'A' => 'No', 'B' => 'Strategi OPPKPKE', 'C' => 'Perangkat Daerah',
            'D' => 'Kode', 'E' => 'Program', 'F' => 'Kegiatan', 'G' => 'Sub Kegiatan',
            'H' => 'Aktifitas Real', 'K' => 'Alokasi Anggaran (Rp)',
            'L' => 'Sumber Pembiayaan', 'M' => 'Sifat Bantuan', 'N' => 'Lokasi',
            'O' => 'Jumlah Sasaran Penerima Manfaat', 'P' => 'Besaran Manfaat',
            'Q' => 'Jenis Bantuan', 'R' => 'Durasi Pemberian Bantuan',
            'S' => 'Realisasi',
        ];

        // Merge cols for "Aktifitas Real" (H-J) and "Realisasi" (S-U)
        $sheet->mergeCells("H{$hRow}:J{$hRow}");
        $sheet->mergeCells("S{$hRow}:U{$hRow}");

        // Single-col headers span 2 rows
        foreach (['A','B','C','D','E','F','G','K','L','M','N','O','P','Q','R'] as $col) {
            $sheet->mergeCells("{$col}{$hRow}:{$col}" . ($hRow + 1));
        }

        foreach ($headers1 as $col => $label) {
            $sheet->setCellValue("{$col}{$hRow}", $label);
        }

        // ── Header row 2 (sub-labels) ───────────────────────────
        $hRow2 = $hRow + 1;
        $sheet->setCellValue("H{$hRow2}", 'Langsung');
        $sheet->setCellValue("I{$hRow2}", 'Tidak Langsung');
        $sheet->setCellValue("J{$hRow2}", 'Penunjang');
        $sheet->setCellValue("S{$hRow2}", 'Sem.1');
        $sheet->setCellValue("T{$hRow2}", 'Sem.2');
        $sheet->setCellValue("U{$hRow2}", 'Total');

        // Number row below headers
        $hRow3 = $hRow2 + 1;
        foreach (range('A', 'U') as $i => $col) {
            $sheet->setCellValue("{$col}{$hRow3}", $i + 1);
        }

        // Style header block
        $headerRange = "A{$hRow}:U{$hRow3}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFB0C4DE']]],
        ]);
        $sheet->getRowDimension($hRow)->setRowHeight(28);
        $sheet->getRowDimension($hRow2)->setRowHeight(22);
        $sheet->getRowDimension($hRow3)->setRowHeight(16);

        // ── Data rows ────────────────────────────────────────────
        $dataStartRow = $hRow3 + 1;
        $currentRow   = $dataStartRow;
        $no           = 1;
        $strategiIdx  = -1;

        foreach ($rows as $row) {
            if ($row['_type'] === 'strategi_header') {
                $strategiIdx++;
                $sheet->mergeCells("A{$currentRow}:U{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", strtoupper($row['label']));
                $bgColor = ['FFBDD7EE', 'FF9DC3E6', 'FF2E74B5'][$strategiIdx % 3] ?? 'FFBDD7EE';
                $sheet->getStyle("A{$currentRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getRowDimension($currentRow)->setRowHeight(18);
                $currentRow++;
                $no = 1;
                continue;
            }

            $cols = [
                'A' => $no++,
                'B' => $row['strategi'],
                'C' => $row['perangkat_daerah'],
                'D' => $row['kode'],
                'E' => $row['program'],
                'F' => $row['kegiatan'],
                'G' => $row['sub_kegiatan'],
                'H' => $row['aktivitas_langsung'],
                'I' => $row['aktivitas_tidak_langsung'],
                'J' => $row['aktivitas_penunjang'],
                'K' => (float) $row['alokasi_anggaran'],
                'L' => $row['sumber_pembiayaan'],
                'M' => $row['sifat_bantuan'],
                'N' => $row['lokasi'],
                'O' => $row['jumlah_sasaran'],
                'P' => $row['besaran_manfaat'],
                'Q' => $row['jenis_bantuan'],
                'R' => $row['durasi_pemberian'],
                'S' => (float) $row['realisasi_sem1'],
                'T' => (float) $row['realisasi_sem2'],
                'U' => (float) $row['realisasi_total'],
            ];

            foreach ($cols as $col => $value) {
                $sheet->setCellValue("{$col}{$currentRow}", $value);
            }

            $numFmt = '#,##0';
            foreach (['K', 'S', 'T', 'U'] as $col) {
                $sheet->getStyle("{$col}{$currentRow}")->getNumberFormat()->setFormatCode($numFmt);
                $sheet->getStyle("{$col}{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }

            $fillColor = ($no % 2 === 0) ? 'FFF5F8FC' : 'FFFFFFFF';
            $sheet->getStyle("A{$currentRow}:U{$currentRow}")->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $fillColor]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD0D8E0']]],
                'font'    => ['size' => 8],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(40);

            $currentRow++;
        }

        // ── Column widths ────────────────────────────────────────
        $widths = [
            'A'=>5,'B'=>22,'C'=>28,'D'=>28,'E'=>30,'F'=>35,'G'=>40,
            'H'=>25,'I'=>25,'J'=>20,'K'=>18,'L'=>12,'M'=>16,'N'=>20,
            'O'=>14,'P'=>18,'Q'=>14,'R'=>12,'S'=>16,'T'=>16,'U'=>16,
        ];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // ── Output ───────────────────────────────────────────────
        $filename = "Matriks_OPPKPKE_{$tahun}_Semester{$semester}.xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function exportPdf(Request $request)
    {
        return response()->json(['message' => 'Export PDF - Coming soon']);
    }

    private function buildMatrixRows(int|string $tahun, Request $request): array
    {
        $user = auth()->user();

        $query = SubKegiatan::with([
            'kegiatan.program.strategi',
            'kegiatan.program.perangkatDaerah',
            'kegiatan.program',
            'kegiatan',
            'laporan' => fn($q) => $q->where('tahun', $tahun),
        ])->where('is_active', true);

        if ($user->isDaerah() && $user->perangkat_daerah_id) {
            $query->whereHas('kegiatan.program', fn($q) => $q->where('perangkat_daerah_id', $user->perangkat_daerah_id));
        }
        if ($request->filled('strategi_id')) {
            $query->whereHas('kegiatan.program', fn($q) => $q->where('strategi_id', $request->strategi_id));
        }
        if ($request->filled('perangkat_daerah_id')) {
            $query->whereHas('kegiatan.program', fn($q) => $q->where('perangkat_daerah_id', $request->perangkat_daerah_id));
        }

        $subKegiatanList = $query->orderBy('id')->get();

        // Group by strategi
        $grouped = $subKegiatanList->groupBy(fn($sk) => optional($sk->kegiatan?->program?->strategi)->id);
        $strategi = StrategiOppkpke::where('is_active', true)->orderBy('kode')->get()->keyBy('id');

        $rows = [];
        foreach ($strategi as $sid => $strat) {
            if (!isset($grouped[$sid])) continue;

            $groupRows = [];
            foreach ($grouped[$sid] as $sk) {
                $lap = $sk->laporan->first();
                if (!$lap) continue;
                $program     = $sk->kegiatan?->program;
                $groupRows[] = [
                    '_type'                    => 'data',
                    'sub_kegiatan_id'          => $sk->id,
                    'strategi'                 => $strat->nama,
                    'perangkat_daerah'         => optional($program?->perangkatDaerah)->nama ?? '',
                    'kode'                     => optional($program)->kode_program ?? '',
                    'program'                  => optional($program)->nama_program ?? '',
                    'kegiatan'                 => optional($sk->kegiatan)->nama_kegiatan ?? '',
                    'sub_kegiatan'             => $sk->nama_sub_kegiatan,
                    'aktivitas_langsung'       => optional($lap)->aktivitas_langsung ?? '',
                    'aktivitas_tidak_langsung' => optional($lap)->aktivitas_tidak_langsung ?? '',
                    'aktivitas_penunjang'      => optional($lap)->aktivitas_penunjang ?? '',
                    'alokasi_anggaran'         => optional($lap)->alokasi_anggaran ?? 0,
                    'sumber_pembiayaan'        => optional($lap)->sumber_pembiayaan ?? 'APBD',
                    'sifat_bantuan'            => optional($lap)->sifat_bantuan ?? '',
                    'lokasi'                   => optional($lap)->lokasi ?? '',
                    'jumlah_sasaran'           => optional($lap)->jumlah_sasaran ?? '',
                    'besaran_manfaat'          => optional($lap)->besaran_manfaat ?? '',
                    'jenis_bantuan'            => optional($lap)->jenis_bantuan ?? '',
                    'durasi_pemberian'         => optional($lap)->durasi_pemberian ?? '',
                    'realisasi_sem1'           => optional($lap)->realisasi_sem1 ?? 0,
                    'realisasi_sem2'           => optional($lap)->realisasi_sem2 ?? 0,
                    'realisasi_total'          => optional($lap)->realisasi_total ?? 0,
                ];
            }

            if (empty($groupRows)) continue;

            $rows[] = ['_type' => 'strategi_header', 'label' => $strat->nama];
            foreach ($groupRows as $r) {
                $rows[] = $r;
            }
        }

        return $rows;
    }

    // =========================================
    // STATISTIK & REPORT
    // =========================================

    public function statistik()
    {
        $tahun         = request('tahun', date('Y'));
        $filterOptions = $this->getFilterOptions();

        $strategi = StrategiOppkpke::where('is_active', true)->orderBy('kode')->get();
        $stats    = $strategi->map(function ($s) use ($tahun) {
            $alokasi   = LaporanOppkpke::where('tahun', $tahun)->byStrategi($s->id)->sum('alokasi_anggaran');
            $realisasi = LaporanOppkpke::where('tahun', $tahun)->byStrategi($s->id)->sum('realisasi_total');
            $sem1      = LaporanOppkpke::where('tahun', $tahun)->byStrategi($s->id)->sum('realisasi_sem1');
            $sem2      = LaporanOppkpke::where('tahun', $tahun)->byStrategi($s->id)->sum('realisasi_sem2');
            $jumlah    = LaporanOppkpke::where('tahun', $tahun)->byStrategi($s->id)->count();
            return [
                'strategi'  => $s,
                'alokasi'   => $alokasi,
                'realisasi' => $realisasi,
                'sem1'      => $sem1,
                'sem2'      => $sem2,
                'persen'    => $alokasi > 0 ? round(($realisasi / $alokasi) * 100, 1) : 0,
                'jumlah'    => $jumlah,
            ];
        });

        // Grand totals direct from DB — single source of truth, not sum of per-strategi
        $grandTotals = [
            'alokasi'   => (float) LaporanOppkpke::where('tahun', $tahun)->sum('alokasi_anggaran'),
            'realisasi' => (float) LaporanOppkpke::where('tahun', $tahun)->sum('realisasi_total'),
            'sem1'      => (float) LaporanOppkpke::where('tahun', $tahun)->sum('realisasi_sem1'),
            'sem2'      => (float) LaporanOppkpke::where('tahun', $tahun)->sum('realisasi_sem2'),
            'jumlah'    => LaporanOppkpke::where('tahun', $tahun)->count(),
        ];

        return view('oppkpke.statistik', compact('filterOptions', 'stats', 'tahun', 'grandTotals'));
    }

    public function report()
    {
        $tahun         = request('tahun', date('Y'));
        $filterOptions = $this->getFilterOptions();

        $query = LaporanOppkpke::with([
            'subKegiatan.kegiatan.program.strategi',
            'subKegiatan.kegiatan.program.perangkatDaerah',
        ])->where('tahun', $tahun);

        // Daerah: filter sesuai perangkat daerah
        $user = auth()->user();
        if ($user->isDaerah() && $user->perangkat_daerah_id) {
            $query->byPerangkatDaerah($user->perangkat_daerah_id);
        }

        if (request()->filled('strategi_id')) {
            $query->byStrategi(request('strategi_id'));
        }
        if (request()->filled('perangkat_daerah_id')) {
            $query->byPerangkatDaerah(request('perangkat_daerah_id'));
        }

        // Compute grand totals BEFORE paginating (paginated collection only sums current page)
        $totals = [
            'alokasi'   => (float) $query->sum('alokasi_anggaran'),
            'sem1'      => (float) $query->sum('realisasi_sem1'),
            'sem2'      => (float) $query->sum('realisasi_sem2'),
            'realisasi' => (float) $query->sum('realisasi_total'),
        ];

        $laporan = $query->orderBy('id')->paginate(25)->withQueryString();

        return view('oppkpke.report', compact('filterOptions', 'laporan', 'tahun', 'totals'));
    }

    // =========================================
    // PANDUAN PENGGUNA
    // =========================================

    public function panduan()
    {
        return view('oppkpke.panduan');
    }

    // =========================================
    // HELPER METHODS
    // =========================================

    private function dashboardDaerah(string|int $tahun, $user)
    {
        $pdId = $user->perangkat_daerah_id;
        $pd   = $user->perangkatDaerah;

        $subKegiatan = SubKegiatan::with([
            'kegiatan.program.strategi',
            'kegiatan.program',
            'kegiatan',
            'laporan' => fn($q) => $q->where('tahun', $tahun),
        ])
        ->whereHas('kegiatan.program', fn($q) => $q->where('perangkat_daerah_id', $pdId))
        ->where('is_active', true)
        ->orderBy('id')
        ->get();

        // Direct DB queries — same source of truth as all other pages
        $totalAlokasi   = (float) LaporanOppkpke::where('tahun', $tahun)->byPerangkatDaerah($pdId)->sum('alokasi_anggaran');
        $totalRealisasi = (float) LaporanOppkpke::where('tahun', $tahun)->byPerangkatDaerah($pdId)->sum('realisasi_total');
        $totalSem1      = (float) LaporanOppkpke::where('tahun', $tahun)->byPerangkatDaerah($pdId)->sum('realisasi_sem1');
        $totalSem2      = (float) LaporanOppkpke::where('tahun', $tahun)->byPerangkatDaerah($pdId)->sum('realisasi_sem2');
        $persen         = $totalAlokasi > 0 ? round(($totalRealisasi / $totalAlokasi) * 100, 1) : 0;
        $terisi         = LaporanOppkpke::where('tahun', $tahun)->byPerangkatDaerah($pdId)->where('alokasi_anggaran', '>', 0)->count();
        $total          = $subKegiatan->count();

        // Per-strategi breakdown: direct DB sum grouped by strategi
        $strategi = StrategiOppkpke::where('is_active', true)->orderBy('kode')->get();
        $perStrategi = $strategi->map(fn($s) => [
            'nama'      => $s->nama,
            'alokasi'   => (float) LaporanOppkpke::where('tahun', $tahun)->byPerangkatDaerah($pdId)->byStrategi($s->id)->sum('alokasi_anggaran'),
            'realisasi' => (float) LaporanOppkpke::where('tahun', $tahun)->byPerangkatDaerah($pdId)->byStrategi($s->id)->sum('realisasi_total'),
        ])->filter(fn($row) => $row['alokasi'] > 0 || $row['realisasi'] > 0)->values();

        // Group sub-kegiatan by program for quick access cards
        $perProgram = $subKegiatan->groupBy(fn($sk) => optional($sk->kegiatan?->program)->nama_program ?? 'Program Lainnya');

        // Ranking semua PD — satu query agregat dengan nama tabel yang benar (programs)
        $laporanPerPd = \DB::table('laporan_oppkpke as lo')
            ->join('sub_kegiatan as sk', 'lo.sub_kegiatan_id', '=', 'sk.id')
            ->join('kegiatan as k',      'sk.kegiatan_id',     '=', 'k.id')
            ->join('programs as p',      'k.program_id',       '=', 'p.id')
            ->where('lo.tahun', $tahun)
            ->groupBy('p.perangkat_daerah_id')
            ->select(
                'p.perangkat_daerah_id as pd_id',
                \DB::raw('SUM(lo.alokasi_anggaran) as alokasi'),
                \DB::raw('SUM(lo.realisasi_total)  as realisasi')
            )
            ->get()
            ->keyBy('pd_id');

        $allPds = PerangkatDaerah::where('is_active', true)->orderBy('nama')->get(['id', 'nama', 'singkatan']);

        $ranking = $allPds->map(function ($pd) use ($laporanPerPd, $pdId, $totalAlokasi, $totalRealisasi) {
            $row     = $laporanPerPd->get($pd->id);
            $alokasi = (float) ($row->alokasi   ?? 0);
            $real    = (float) ($row->realisasi  ?? 0);
            $isSelf  = (int) $pd->id === (int) $pdId;

            // Self PD: use already-computed accurate stats as authoritative fallback
            if ($isSelf && $totalAlokasi > 0) {
                $alokasi = $totalAlokasi;
                $real    = $totalRealisasi;
            }

            return [
                'id'        => $pd->id,
                'nama'      => \Illuminate\Support\Str::limit($pd->singkatan ?? $pd->nama, 28),
                'nama_full' => $pd->nama,
                'alokasi'   => $alokasi,
                'realisasi' => $real,
                'persen'    => $alokasi > 0 ? round(($real / $alokasi) * 100, 1) : 0,
                'is_self'   => $isSelf,
                'has_data'  => $alokasi > 0,
            ];
        })
        ->filter(fn($r) => $r['has_data'] || $r['is_self'])
        ->sort(function ($a, $b) {
            // persen desc → alokasi desc → nama asc (deterministic)
            if ($b['persen'] !== $a['persen']) return $b['persen'] <=> $a['persen'];
            if ($b['alokasi'] !== $a['alokasi']) return $b['alokasi'] <=> $a['alokasi'];
            return strcmp($a['nama'], $b['nama']);
        })
        ->values();

        $stats = [
            'totalAlokasi'   => $totalAlokasi,
            'totalRealisasi' => $totalRealisasi,
            'totalSem1'      => $totalSem1,
            'totalSem2'      => $totalSem2,
            'persen'         => $persen,
            'terisi'         => $terisi,
            'total'          => $total,
        ];

        return view('oppkpke.dashboard_daerah', compact('stats', 'tahun', 'pd', 'subKegiatan', 'perStrategi', 'perProgram', 'ranking'));
    }

    private function getFilterOptions(): array
    {
        $user  = auth()->user();
        $pdQuery = PerangkatDaerah::where('is_active', true)->orderBy('nama');

        if ($user->isDaerah() && $user->perangkat_daerah_id) {
            $pdQuery->where('id', $user->perangkat_daerah_id);
        }

        return [
            'strategi'        => StrategiOppkpke::where('is_active', true)->orderBy('kode')->get(),
            'perangkat_daerah' => $pdQuery->get(),
            'programs'        => Program::where('is_active', true)->orderBy('kode_program')->get(),
            'kegiatan'        => Kegiatan::where('is_active', true)->orderBy('id')->get(),
        ];
    }
}
