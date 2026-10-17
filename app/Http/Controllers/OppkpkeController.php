<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StrategiOppkpke;
use App\Models\PerangkatDaerah;
use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use App\Models\LaporanOppkpke;
use App\Models\AuditLog;
use App\Services\OppkpkeService;
use App\Http\Requests\LaporanOppkpkeRequest;
use App\Jobs\ProcessLaporanImportJob;
use App\Jobs\ProcessLaporanRatImportJob;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class OppkpkeController extends Controller
{
    public function __construct(private OppkpkeService $oppkpkeService) {}

    /** Nama sementara untuk program/kegiatan yang dibuat tanpa nama (dilengkapi operator). */
    public const PROGRAM_PLACEHOLDER  = 'Program (belum diisi)';
    public const KEGIATAN_PLACEHOLDER = 'Kegiatan (belum diisi)';

    /**
     * Ubah nama (dan opsional kode) sebuah program. Untuk operator daerah:
     * terkunci ke program milik perangkat daerahnya. Admin boleh program mana pun.
     */
    public function programUpdate(Request $request, $id)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'nama_program' => 'required|string|max:500',
            'kode_program' => 'nullable|string|max:50',
        ], [
            'nama_program.required' => 'Nama program wajib diisi.',
        ]);

        $program = Program::findOrFail($id);

        // Kepemilikan: operator daerah hanya boleh program PD-nya sendiri.
        if ($user->isDaerah()) {
            abort_unless(
                $user->perangkat_daerah_id && $program->perangkat_daerah_id === $user->perangkat_daerah_id,
                403,
                'Program ini bukan milik perangkat daerah Anda.'
            );
        }

        $nama = trim($validated['nama_program']);
        $normalize = fn (string $s) => trim(preg_replace('/\s+/', ' ', strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $s))));
        $dupe = Program::where('perangkat_daerah_id', $program->perangkat_daerah_id)
            ->where('id', '!=', $program->id)
            ->get()
            ->first(fn ($p) => $normalize($p->nama_program) === $normalize($nama));
        if ($dupe) {
            return response()->json([
                'success' => false,
                'message' => 'Sudah ada program lain dengan nama serupa: "' . $dupe->nama_program . '".',
            ], 422);
        }

        $lama = $program->nama_program;
        $program->nama_program = $nama;
        if ($request->filled('kode_program')) {
            $program->kode_program = trim($validated['kode_program']);
        }
        $program->save();

        AuditLog::record('program.updated', 'Ubah nama program menjadi "' . $nama . '"', $program, [
            'perangkat_daerah_id' => $program->perangkat_daerah_id,
            'nama_lama'           => $lama,
        ]);

        return response()->json(['success' => true, 'message' => 'Nama program diperbarui.']);
    }

    /**
     * Ubah nama (dan opsional kode) sebuah kegiatan. Operator daerah terkunci ke
     * kegiatan di bawah program milik perangkat daerahnya.
     */
    public function kegiatanUpdate(Request $request, $id)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'nama_kegiatan' => 'required|string|max:500',
            'kode_kegiatan' => 'nullable|string|max:100',
        ], [
            'nama_kegiatan.required' => 'Nama kegiatan wajib diisi.',
        ]);

        $kegiatan = Kegiatan::with('program')->findOrFail($id);
        $pdId     = optional($kegiatan->program)->perangkat_daerah_id;

        if ($user->isDaerah()) {
            abort_unless(
                $user->perangkat_daerah_id && $pdId === $user->perangkat_daerah_id,
                403,
                'Kegiatan ini bukan milik perangkat daerah Anda.'
            );
        }

        $nama = trim($validated['nama_kegiatan']);
        $normalize = fn (string $s) => trim(preg_replace('/\s+/', ' ', strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $s))));
        $dupe = Kegiatan::where('program_id', $kegiatan->program_id)
            ->where('id', '!=', $kegiatan->id)
            ->get()
            ->first(fn ($k) => $normalize($k->nama_kegiatan) === $normalize($nama));
        if ($dupe) {
            return response()->json([
                'success' => false,
                'message' => 'Sudah ada kegiatan lain dengan nama serupa: "' . $dupe->nama_kegiatan . '".',
            ], 422);
        }

        $lama = $kegiatan->nama_kegiatan;
        $kegiatan->nama_kegiatan = $nama;
        if ($request->filled('kode_kegiatan')) {
            $kegiatan->kode = trim($validated['kode_kegiatan']);
        }
        $kegiatan->save();

        AuditLog::record('kegiatan.updated', 'Ubah nama kegiatan menjadi "' . $nama . '"', $kegiatan, [
            'perangkat_daerah_id' => $pdId,
            'nama_lama'           => $lama,
        ]);

        return response()->json(['success' => true, 'message' => 'Nama kegiatan diperbarui.']);
    }

    // =========================================
    // HALAMAN UTAMA
    // =========================================

    public function index()
    {
        $user = auth()->user();

        if ($user->isMaster()) {
            return redirect()->route('oppkpke.matrix');
        }
        if ($user->isItTeam()) {
            return redirect()->route('oppkpke.chat.index');
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

        // Rekap per perangkat daerah — SEMUA PD aktif (yang tanpa data tetap muncul
        // dengan nilai 0). Satu query agregat via tabel programs (bukan relasi per-row)
        // lalu digabung dengan seluruh PD aktif. Tabel di view punya toggle 10/25/Semua.
        $sumsPerPd = \DB::table('laporan_oppkpke as lo')
            ->join('sub_kegiatan as sk', 'lo.sub_kegiatan_id', '=', 'sk.id')
            ->join('kegiatan as k',      'sk.kegiatan_id',     '=', 'k.id')
            ->join('programs as p',      'k.program_id',       '=', 'p.id')
            ->where('lo.tahun', $tahun)
            ->whereNull('lo.deleted_at')   // WAJIB: DB::table melewati scope SoftDeletes Eloquent; tanpa ini baris yang sudah dihapus (mis. hasil import replace_year) ikut dijumlah → anggaran/realisasi jadi dobel.
            ->groupBy('p.perangkat_daerah_id')
            ->select(
                'p.perangkat_daerah_id as pd_id',
                \DB::raw('SUM(lo.alokasi_anggaran) as alokasi'),
                \DB::raw('SUM(lo.realisasi_total)  as realisasi')
            )
            ->get()
            ->keyBy('pd_id');

        $rekapPerangkat = PerangkatDaerah::where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(function ($pd) use ($sumsPerPd) {
                $row = $sumsPerPd->get($pd->id);
                return [
                    'nama'      => $pd->nama,
                    'alokasi'   => (float) ($row->alokasi   ?? 0),
                    'realisasi' => (float) ($row->realisasi ?? 0),
                ];
            })
            ->sortByDesc('realisasi')
            ->values()
            ->toArray();

        return view('oppkpke.dashboard', compact('stats', 'tahun', 'rekapPerangkat'));
    }

    /**
     * Ringkasan otomatis (narasi + sorotan + rekomendasi) — dihitung server,
     * tanpa AI/LLM. Dipakai kartu "Ringkasan Otomatis" di dashboard.
     */
    public function ringkasan(Request $request)
    {
        $tahun = (int) $request->input('tahun', date('Y'));
        $data  = app(\App\Services\BudgetInsightService::class)->narrativeSummary($tahun);

        return response()->json($data);
    }

    /**
     * Halaman Menu (khusus mobile) — daftar seluruh menu sesuai role,
     * menggantikan sidebar geser di perangkat kecil.
     */
    public function menu()
    {
        return view('oppkpke.menu');
    }

    /**
     * Halaman Ikhtisar Eksekutif — dashboard untuk pimpinan berbasis data nyata.
     */
    public function presentasi(Request $request)
    {
        $tahunTersedia = LaporanOppkpke::query()
            ->select('tahun')->distinct()->orderByDesc('tahun')->pluck('tahun')->all();
        $tahun = (int) $request->input('tahun', $tahunTersedia[0] ?? date('Y'));

        $data = app(\App\Services\BudgetInsightService::class)
            ->presentationData($tahun, $request->boolean('fresh'));

        return view('oppkpke.presentasi', [
            'd'             => $data,
            'tahun'         => $tahun,
            'tahunTersedia' => $tahunTersedia,
        ]);
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

    public function store(LaporanOppkpkeRequest $request)
    {
        return $this->saveLaporan($request, $request->input('id'));
    }

    public function update(LaporanOppkpkeRequest $request, $id)
    {
        return $this->saveLaporan($request, $id);
    }

    // Catatan: update() dulu memanggil $this->store($request) secara langsung (bukan
    // lewat router) — kalau store() diberi type-hint LaporanOppkpkeRequest, panggilan
    // PHP langsung seperti itu akan MELEWATI validasi FormRequest sepenuhnya (validasi
    // form request hanya terpicu saat Laravel me-resolve action dari container). Karena
    // itu store()/update() sekarang masing-masing type-hint sendiri dan berbagi logika
    // lewat method private ini.
    private function saveLaporan(LaporanOppkpkeRequest $request, $id = null)
    {
        $validated = $request->validated();
        $user      = auth()->user();

        // Daerah user: pastikan sub_kegiatan milik perangkat_daerah mereka
        if ($user->isDaerah() && $user->perangkat_daerah_id) {
            $allowed = SubKegiatan::whereHas('kegiatan.program', fn($q) => $q->where('perangkat_daerah_id', $user->perangkat_daerah_id))
                ->pluck('id')
                ->contains($validated['sub_kegiatan_id']);

            if (!$allowed) {
                return response()->json(['success' => false, 'message' => 'Anda tidak berwenang mengisi data ini.'], 403);
            }
        }

        try {
            $this->oppkpkeService->createOrUpdateLaporan(
                (int) $validated['sub_kegiatan_id'],
                (int) $validated['tahun'],
                $validated
            );

            // Audit terlacak ke PIC (nama + NIK tersamar) — dipantau Tim IT.
            AuditLog::record(
                $id ? 'laporan.updated' : 'laporan.created',
                ($id ? 'Memperbarui' : 'Menginput') . " laporan sub-kegiatan #{$validated['sub_kegiatan_id']} TA {$validated['tahun']}",
                null,
                [
                    'pic_nama'            => $user->nama_lengkap,
                    'pic_nik'             => $user->no_ktp ? substr($user->no_ktp, 0, 4) . '********' . substr($user->no_ktp, -4) : null,
                    'perangkat_daerah_id' => $user->perangkat_daerah_id,
                    'sub_kegiatan_id'     => (int) $validated['sub_kegiatan_id'],
                    'tahun'               => (int) $validated['tahun'],
                ],
            );

            return response()->json([
                'success' => true,
                'message' => $id ? 'Data berhasil diperbarui' : 'Data berhasil ditambahkan',
            ]);
        } catch (QueryException $e) {
            // Kode 23000 = unique/FK constraint violation. FormRequest sudah mencegah
            // ini di jalur normal (Rule::unique), tapi race antar dua request nyaris
            // bersamaan tetap bisa lolos validasi lalu bentrok di DB — tangani dengan
            // pesan ramah, jangan bocorkan pesan SQL mentah ke client.
            if ($e->getCode() === '23000') {
                return response()->json(['success' => false, 'message' => 'Data untuk sub kegiatan dan tahun ini sudah ada.'], 422);
            }

            Log::channel('audit')->error('Gagal simpan laporan', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem, silakan coba lagi.'], 500);
        }
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

    public function batchDestroy(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1|max:200',
            'ids.*' => 'integer|exists:laporan_oppkpke,id',
        ], ['ids.max' => 'Maksimal 200 data per batch hapus.']);

        $user  = auth()->user();
        $query = LaporanOppkpke::whereIn('id', $request->ids);

        // Daerah user: hanya bisa hapus data miliknya — sama seperti destroy()
        if ($user->isDaerah() && $user->perangkat_daerah_id) {
            $allowedSkIds = SubKegiatan::whereHas('kegiatan.program', fn($q) => $q->where('perangkat_daerah_id', $user->perangkat_daerah_id))
                ->pluck('id');
            $query->whereIn('sub_kegiatan_id', $allowedSkIds);
        }

        $deleted = DB::transaction(fn () => $query->delete());

        Log::channel('audit')->info('Batch hapus laporan', [
            'user_id' => $user->id,
            'ids'     => $request->ids,
            'deleted' => $deleted,
        ]);

        $skippedNote = $deleted < count($request->ids) ? ' (sebagian dilewati karena bukan wewenang Anda)' : '.';

        return response()->json([
            'success' => true,
            'message' => "{$deleted} data berhasil dihapus{$skippedNote}",
        ]);
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

    /**
     * Wizard manual: buat hirarki (Program → Kegiatan → Sub Kegiatan) yang belum
     * ada, untuk diisi laporannya. Role-aware:
     *  • Operator Daerah → SELALU terkunci ke perangkat daerah miliknya; tidak
     *    bisa membuat PD baru maupun menyentuh PD lain.
     *  • Admin Master / Tim IT → memilih perangkat daerah tujuan (PD mana pun) atau
     *    membuat PD baru sekalian; bisa membangun rantai dari nol.
     * Anti-duplikat & transaksional.
     */
    public function hierarchyStore(Request $request)
    {
        $user    = auth()->user();
        $isAdmin = $user->isMaster() || $user->isItTeam();

        // Operator daerah wajib terhubung ke sebuah perangkat daerah.
        if (!$isAdmin && !$user->perangkat_daerah_id) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda tidak terhubung ke perangkat daerah, sehingga tidak dapat menambah kegiatan.',
            ], 403);
        }

        $rules = [
            'program_mode'      => 'required|in:existing,new',
            'program_id'        => 'required_if:program_mode,existing|nullable|integer',
            // Strategi: operator memilih satu (strategi_id); admin boleh memilih
            // hingga 3 (strategi_ids[]) — hierarki dibuat di bawah tiap strategi.
            'strategi_id'       => 'nullable|integer|exists:strategi_oppkpke,id',
            'strategi_ids'      => 'nullable|array|max:3',
            'strategi_ids.*'    => 'integer|exists:strategi_oppkpke,id',
            // Kode & nama program/kegiatan OPSIONAL — boleh dikosongkan, dilengkapi
            // /diubah operator daerah lewat halaman Input Data.
            'kode_program'      => 'nullable|string|max:50',
            'nama_program'      => 'nullable|string|max:500',
            'kegiatan_mode'     => 'required|in:existing,new',
            'kegiatan_id'       => 'required_if:kegiatan_mode,existing|nullable|integer',
            'kode_kegiatan'     => 'nullable|string|max:100',
            'nama_kegiatan'     => 'nullable|string|max:500',
            'kode_sub'          => 'nullable|string|max:100',
            'nama_sub_kegiatan' => 'required|string|max:500',
        ];
        $messages = [
            'strategi_ids.max'           => 'Maksimal 3 strategi.',
            'nama_sub_kegiatan.required' => 'Nama sub kegiatan wajib diisi.',
        ];

        // Admin: langkah tambahan — tentukan perangkat daerah tujuan.
        if ($isAdmin) {
            $rules['pd_mode']              = 'required|in:existing,new';
            $rules['perangkat_daerah_id']  = 'required_if:pd_mode,existing|nullable|integer|exists:perangkat_daerah,id';
            $rules['pd_nama']              = 'required_if:pd_mode,new|nullable|string|max:255';
            $rules['pd_singkatan']         = 'nullable|string|max:50';
            $rules['pd_jenis']             = 'nullable|string|max:50';
            $messages['perangkat_daerah_id.required_if'] = 'Pilih perangkat daerah tujuan.';
            $messages['pd_nama.required_if']             = 'Nama perangkat daerah wajib diisi.';
        }

        $validated = $request->validate($rules, $messages);

        // Program baru ⇒ kegiatannya pasti baru juga (tak mungkin ada kegiatan lama
        // di bawah program yang belum dibuat).
        $programMode  = $validated['program_mode'];
        $kegiatanMode = $programMode === 'new' ? 'new' : $validated['kegiatan_mode'];

        // Daftar strategi untuk program baru: admin bisa banyak (maks 3), operator satu.
        // Hierarki (program→kegiatan→sub) dibuat di bawah TIAP strategi terpilih.
        $strategiList = [];
        if ($programMode === 'new') {
            $ids = $isAdmin ? ($validated['strategi_ids'] ?? []) : array_filter([$validated['strategi_id'] ?? null]);
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if (empty($ids)) {
                return response()->json(['success' => false, 'message' => 'Pilih minimal satu strategi untuk program baru.'], 422);
            }
            $strategiList = array_slice($ids, 0, 3);
        }

        // Normalisasi nama untuk deteksi duplikat (buang tanda baca/spasi ganda).
        $normalize = fn (string $s) => trim(preg_replace('/\s+/', ' ', strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $s))));

        try {
            $r = DB::transaction(function () use ($validated, $programMode, $kegiatanMode, $strategiList, $normalize, $user, $isAdmin) {
                $createdProgram = $createdKegiatan = $createdPd = false;

                // ── PERANGKAT DAERAH ─────────────────────────────────────
                if (!$isAdmin) {
                    // Operator: terkunci ke PD sendiri.
                    $pdId = $user->perangkat_daerah_id;
                    $pdNama = optional($user->perangkatDaerah)->nama ?? ('PD #' . $pdId);
                } elseif ($validated['pd_mode'] === 'existing') {
                    $pd = PerangkatDaerah::where('id', $validated['perangkat_daerah_id'])
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();
                    abort_unless($pd, 422, 'Perangkat daerah tujuan tidak ditemukan atau non-aktif.');
                    $pdId = $pd->id;
                    $pdNama = $pd->nama;
                } else {
                    $namaPd = trim($validated['pd_nama']);
                    $dupePd = PerangkatDaerah::all()->first(fn ($p) => $normalize($p->nama) === $normalize($namaPd));
                    if ($dupePd) {
                        abort(422, 'Perangkat daerah serupa sudah ada: "' . $dupePd->nama . '". Gunakan pilihan "PD yang sudah ada".');
                    }
                    $pd = PerangkatDaerah::create([
                        'nama'      => $namaPd,
                        'singkatan' => ($validated['pd_singkatan'] ?? null) ?: null,
                        'jenis'     => ($validated['pd_jenis'] ?? null) ?: null,
                        'is_active' => true,
                    ]);
                    $pdId = $pd->id;
                    $pdNama = $pd->nama;
                    $createdPd = true;
                    AuditLog::record('perangkat_daerah.created', 'Menambah perangkat daerah "' . $namaPd . '"', $pd);
                }

                // Pembuat KEGIATAN (existing/new; nama boleh kosong → placeholder).
                $makeKegiatan = function ($program) use (&$createdKegiatan, $validated, $kegiatanMode, $normalize) {
                    if ($kegiatanMode === 'existing') {
                        $kegiatan = Kegiatan::where('id', $validated['kegiatan_id'])
                            ->where('program_id', $program->id)
                            ->first();
                        abort_unless($kegiatan, 422, 'Kegiatan yang dipilih tidak berada di bawah program tersebut.');
                        return $kegiatan;
                    }
                    $namaKeg  = trim($validated['nama_kegiatan'] ?? '');
                    $blankKeg = ($namaKeg === '');
                    if ($blankKeg) {
                        $namaKeg = self::KEGIATAN_PLACEHOLDER;
                    }
                    // Placeholder tidak dianggap duplikat (boleh lebih dari satu).
                    $dupeKeg = $blankKeg ? null : Kegiatan::where('program_id', $program->id)->get()->first(fn ($k) =>
                        $normalize($k->nama_kegiatan) === $normalize($namaKeg)
                    );
                    if ($dupeKeg) {
                        abort(422, 'Kegiatan serupa sudah ada: "' . $dupeKeg->nama_kegiatan . '". Gunakan pilihan "kegiatan yang sudah ada".');
                    }
                    $createdKegiatan = true;
                    return Kegiatan::create([
                        'program_id'    => $program->id,
                        'kode'          => ($validated['kode_kegiatan'] ?? null) ?: null,
                        'nama_kegiatan' => $namaKeg,
                        'is_active'     => true,
                    ]);
                };

                // Pembuat SUB KEGIATAN (selalu baru).
                $makeSub = function ($kegiatan) use ($validated, $normalize) {
                    $namaSub = trim($validated['nama_sub_kegiatan']);
                    $dupeSub = SubKegiatan::where('kegiatan_id', $kegiatan->id)->get()->first(fn ($s) =>
                        $normalize($s->nama_sub_kegiatan) === $normalize($namaSub)
                    );
                    if ($dupeSub) {
                        abort(422, 'Sub kegiatan "' . $dupeSub->nama_sub_kegiatan . '" sudah ada di kegiatan ini.');
                    }
                    return SubKegiatan::create([
                        'kegiatan_id'       => $kegiatan->id,
                        'kode'              => ($validated['kode_sub'] ?? null) ?: null,
                        'nama_sub_kegiatan' => $namaSub,
                        'is_active'         => true,
                    ]);
                };

                // ── PROGRAM → KEGIATAN → SUB ─────────────────────────────
                $subs = [];   // [{program, kegiatan, sub}] — bisa >1 (multi strategi).

                if ($programMode === 'existing') {
                    $program = Program::where('id', $validated['program_id'])
                        ->where('perangkat_daerah_id', $pdId)
                        ->lockForUpdate()
                        ->first();
                    abort_unless($program, 422, 'Program yang dipilih tidak ditemukan pada perangkat daerah tujuan.');
                    $kegiatan = $makeKegiatan($program);
                    $sub      = $makeSub($kegiatan);
                    $subs[]   = compact('program', 'kegiatan', 'sub');
                } else {
                    $namaProg  = trim($validated['nama_program'] ?? '');
                    $kodeProg  = trim($validated['kode_program'] ?? '');
                    $blankName = ($namaProg === '');
                    if ($blankName) {
                        $namaProg = self::PROGRAM_PLACEHOLDER;
                    }
                    // Buat rantai di bawah TIAP strategi terpilih (maks 3).
                    foreach ($strategiList as $sid) {
                        $dupe = Program::where('perangkat_daerah_id', $pdId)->where('strategi_id', $sid)->get()->first(fn ($p) =>
                            ($kodeProg !== '' && strcasecmp(trim($p->kode_program), $kodeProg) === 0) ||
                            (! $blankName && $normalize($p->nama_program) === $normalize($namaProg))
                        );
                        if ($dupe) {
                            abort(422, 'Program serupa sudah ada pada salah satu strategi: "' . $dupe->nama_program . '".');
                        }
                        // Kode kosong bisa menabrak unique (strategi,pd,kode) bila sudah
                        // ada program berkode kosong di strategi+PD ini → buat kode unik.
                        $kodeCreate = $kodeProg;
                        if ($kodeCreate === '' && Program::where('perangkat_daerah_id', $pdId)->where('strategi_id', $sid)->where('kode_program', '')->exists()) {
                            $kodeCreate = 'AUTO-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
                        }
                        $program = Program::create([
                            'strategi_id'         => $sid,
                            'perangkat_daerah_id' => $pdId,
                            'kode_program'        => $kodeCreate,
                            'nama_program'        => $namaProg,
                            'is_active'           => true,
                        ]);
                        $createdProgram = true;
                        $kegiatan = $makeKegiatan($program);
                        $sub      = $makeSub($kegiatan);
                        $subs[]   = compact('program', 'kegiatan', 'sub');
                    }
                }

                $first = $subs[0];
                AuditLog::record(
                    'sub_kegiatan.created',
                    'Menambah ' . count($subs) . ' sub kegiatan "' . $first['sub']->nama_sub_kegiatan . '"',
                    $first['sub'],
                    [
                        'perangkat_daerah_id'   => $pdId,
                        'perangkat_daerah'      => $pdNama,
                        'perangkat_daerah_baru' => $createdPd,
                        'program'               => $first['program']->nama_program,
                        'program_baru'          => $createdProgram,
                        'kegiatan'              => $first['kegiatan']->nama_kegiatan,
                        'kegiatan_baru'         => $createdKegiatan,
                        'jumlah_strategi'       => count($subs),
                    ]
                );

                return compact('subs', 'createdProgram', 'createdKegiatan', 'createdPd', 'pdNama');
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // abort(...) di dalam transaksi → rollback otomatis, pesan ramah ke klien.
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode() ?: 422);
        } catch (QueryException $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan. Kemungkinan data serupa sudah ada.'], 422);
        }

        $parts = [];
        if ($r['createdPd'])       $parts[] = 'Perangkat Daerah';
        if ($r['createdProgram'])  $parts[] = 'Program';
        if ($r['createdKegiatan']) $parts[] = 'Kegiatan';
        $parts[] = 'Sub Kegiatan';

        $n     = count($r['subs']);
        $first = $r['subs'][0];
        $extra = $n > 1 ? " di {$n} strategi" : '';

        return response()->json([
            'success'      => true,
            'message'      => implode(', ', $parts) . ' berhasil ditambahkan untuk ' . $r['pdNama'] . $extra . '.',
            'sub_kegiatan' => [
                'id'   => $first['sub']->id,
                'nama' => $first['sub']->nama_sub_kegiatan,
                'path' => $r['pdNama'] . ' → ' . $first['program']->nama_program . ' → ' . $first['kegiatan']->nama_kegiatan . ' → ' . $first['sub']->nama_sub_kegiatan,
            ],
        ]);
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
            'not_found_realisasi' => (float) $coll->where('status', 'not_found')->sum('realisasi_total'),
            'duplicate'  => $coll->where('status', 'duplicate')->count(),
            'new_sk'     => $coll->where('status', 'new_sk')->count(),
            'ambiguous'  => $coll->where('status', 'ambiguous')->count(),
            'update'     => $coll->where('status', 'matched')->where('has_existing', true)->count(),
            'new_record' => $coll->where('status', 'matched')->where('has_existing', false)->count(),
            // Realisasi seluruh file (duplikat sudah digabung ke baris pertamanya).
            'realisasi_file' => (float) $coll->where('status', '!=', 'duplicate')->sum('realisasi_total'),
        ];
        // Tahun ini sudah berisi data perencanaan (mis. hasil import RAT) → default mode
        // "Realisasi saja" agar alokasi & data perencanaan tidak tertimpa file ini.
        $hasPlanData = LaporanOppkpke::where('tahun', $tahun)->exists();

        return view('oppkpke.import', compact('matchedRows', 'stats', 'tahun', 'semester', 'cacheKey', 'hasPlanData'));
    }

    /**
     * Terapkan pilihan admin untuk baris ambigu / tidak cocok: id sub kegiatan (wajib
     * salah satu kandidat baris itu) atau 'new' bila $allowNew. Pilihan lain diabaikan → dilewati.
     */
    private function applyAmbiguityChoices(array $rows, array $resolve, bool $allowNew): array
    {
        foreach ($rows as &$row) {
            if (! in_array($row['status'], ['ambiguous', 'not_found'], true)) continue;
            $choice = (string) ($resolve[$row['row_num']] ?? '');
            $ids    = array_column($row['ambig_candidates'] ?? $row['nf_candidates'] ?? [], 'id');
            if ($allowNew && $choice === 'new') {
                $row['resolved_to'] = 'new';
            } elseif (ctype_digit($choice) && in_array((int) $choice, $ids, true)) {
                $row['resolved_to'] = (int) $choice;
            }
        }
        unset($row);

        return $rows;
    }

    public function importExecute(Request $request)
    {
        $request->validate([
            'cache_key'    => 'required|string',
            'mode'         => 'nullable|in:realisasi,lengkap',
            'resolve'      => 'nullable|array',
            'resolve.*'    => 'nullable|string|max:20',
            'import_ids'   => 'nullable|array',
            'import_ids.*' => 'nullable|string|max:20',
        ]);

        // Idempotency gate: ambil + hapus cache key SEBELUM proses apa pun dimulai
        // (bukan di akhir seperti sebelumnya) — mempersempit jendela race double-submit
        // (mis. klik ganda sebelum tombol disable) dari "sepanjang durasi import" jadi
        // hanya beberapa milidetik.
        $cached = cache()->pull($request->cache_key);
        if (!$cached) {
            return back()->withErrors(['error' => 'Sesi preview sudah kedaluwarsa atau sudah diproses. Silakan upload ulang.']);
        }

        $cached['mode'] = $request->input('mode', 'lengkap');
        $cached['rows'] = $this->applyAmbiguityChoices($cached['rows'], (array) $request->input('resolve', []), true);

        // Baris yang TIDAK dicentang di preview dilewati. (Dulu centang dikirim sebagai
        // import_ids[] tetapi server hanya membaca skip[] — hilangkan centang tak berefek.)
        $skip = (array) $request->input('skip', []);
        if ($request->has('import_ids')) {
            $checked = array_map('strval', (array) $request->input('import_ids', []));
            foreach ($cached['rows'] as $row) {
                if (in_array($row['status'], ['matched', 'new_sk'], true) && ! in_array((string) $row['row_num'], $checked, true)) {
                    $skip[] = (string) $row['row_num'];
                }
            }
        }

        $statusKey = 'oppkpke_import_status_' . auth()->id() . '_' . uniqid();
        cache()->put($statusKey, ['state' => 'processing'], now()->addHour());

        ProcessLaporanImportJob::dispatch(
            $cached,
            array_values(array_unique($skip)),
            // Mode "Realisasi saja" tidak pernah menghapus data tahun ini (data RAT aman).
            $cached['mode'] === 'lengkap' && $request->boolean('replace_year'),
            auth()->id(),
            $statusKey
        );

        return redirect()->route('oppkpke.import')
            ->with('info', 'Import sedang diproses di background.')
            ->with('import_status_key', $statusKey);
    }

    public function importStatus(Request $request)
    {
        $request->validate(['key' => 'required|string']);
        return response()->json(cache()->get($request->key) ?? ['state' => 'unknown']);
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
            'ambiguous'  => $coll->where('status', 'ambiguous')->count(),
            'ambiguous_alokasi' => (float) $coll->where('status', 'ambiguous')->sum('alokasi_anggaran'),
            'strategi_warn' => $coll->where('status', 'not_found')->filter(fn ($r) => ! empty($r['strategi_warn']))->count(),
            'update'     => $coll->where('status', 'matched')->where('has_existing', true)->count(),
            'new_record' => $coll->where('status', 'matched')->where('has_existing', false)->count(),
        ];

        return view('oppkpke.import_rat', compact('matchedRows', 'stats', 'tahun', 'cacheKey'));
    }

    public function importRatExecute(Request $request)
    {
        $request->validate([
            'cache_key' => 'required|string',
            'resolve'   => 'nullable|array',
            'resolve.*' => 'nullable|string|max:20',
        ]);

        // Idempotency gate — lihat catatan di importExecute().
        $cached = cache()->pull($request->cache_key);
        if (!$cached) {
            return back()->withErrors(['error' => 'Sesi preview sudah kedaluwarsa atau sudah diproses. Silakan upload ulang.']);
        }

        $cached['rows'] = $this->applyAmbiguityChoices($cached['rows'], (array) $request->input('resolve', []), true);

        $statusKey = 'oppkpke_rat_status_' . auth()->id() . '_' . uniqid();
        cache()->put($statusKey, ['state' => 'processing'], now()->addHour());

        ProcessLaporanRatImportJob::dispatch(
            $cached,
            $request->input('skip', []),
            $request->boolean('replace_year'),
            auth()->id(),
            $statusKey
        );

        return redirect()->route('oppkpke.import.rat')
            ->with('info', 'Import Matriks RAT sedang diproses di background.')
            ->with('import_status_key', $statusKey);
    }

    /**
     * Baca matriks RAT (18 kolom A–R) atau — bila $withRealisasi — matriks OPPKPKE
     * (21 kolom: + S=Realisasi Sem.1, T=Sem.2, U=Total). Satu parser untuk keduanya
     * agar aturan kode, pewarisan konteks, dan rumus Excel diperlakukan sama.
     */
    private function parseRatFile(string $filePath, string $ext, bool $withRealisasi = false): array
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
        $cell = fn(string $col, int $row) => trim((string) $this->cellValue($sheet, $col . $row));

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
                $k = $this->parseCellNumber($this->cellValue($sheet, 'K' . $r), $cell('K', $r));
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
        $currentProgKode = '';
        $currentKegKode  = '';
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

            [$skKodeFromG,   $colG] = $stripKodePrefix($colG);
            [$kegKodeFromF,  $colF] = $stripKodePrefix($colF);
            [$progKodeFromE, $colE] = $stripKodePrefix($colE);

            // Inherit hierarchical context from filled columns
            if ($colB !== '' && stripos($colB, 'strategi') === false) $currentStrategi = $colB;
            // Ganti perangkat daerah → kode/program/kegiatan PD sebelumnya tidak boleh terbawa
            // (dulu membuat mis. BPBD tercatat di program "Air Limbah" milik PU).
            if ($colC !== '' && $colC !== $currentPd) {
                $currentKode = $currentProgram = $currentKegiatan = $currentProgKode = $currentKegKode = '';
            }
            if ($colC !== '') $currentPd       = $colC;
            if ($colD !== '') $currentKode     = $colD;
            if ($colE !== '') { $currentProgram  = $colE; $currentProgKode = (string) $progKodeFromE; }
            if ($colF !== '') { $currentKegiatan = $colF; $currentKegKode  = (string) $kegKodeFromF; }

            // Kode sub kegiatan Permendagri (mis. "1.02.02.2.01.0014") memuat kode
            // program (3 segmen pertama) & kegiatan (5 segmen). Turunkan agar tiap
            // level punya kodenya sendiri — tidak menyamakan kode sub = kode program.
            [$derivProg, $derivKeg] = $this->deriveHierarchyCodes($skKodeFromG);

            // Program/kegiatan warisan baris sebelumnya hanya dipakai bila kodenya sejalan
            // dengan kode sub kegiatan baris ini (sub 1.03.06.* tidak mewarisi kegiatan 1.03.05.*).
            $rowProgram  = ($colE === '' && $currentProgKode !== '' && $derivProg && $currentProgKode !== $derivProg) ? '' : $currentProgram;
            $rowKegiatan = ($colF === '' && $currentKegKode !== '' && $derivKeg && $currentKegKode !== $derivKeg) ? '' : $currentKegiatan;

            $kodeProgram  = $currentKode !== '' ? $currentKode : ($progKodeFromE ?: ($derivProg ?? ''));
            $kodeKegiatan = $kegKodeFromF ?: ($derivKeg ?? '');
            $kodeSub      = $skKodeFromG ?? '';

            $alokasi = $this->parseCellNumber($this->cellValue($sheet, 'K' . $r), $colK);

            $sem1 = $sem2 = $total = 0.0;
            if ($withRealisasi) {
                $sem1  = $this->parseCellNumber($this->cellValue($sheet, 'S' . $r), $cell('S', $r));
                $sem2  = $this->parseCellNumber($this->cellValue($sheet, 'T' . $r), $cell('T', $r));
                $total = $this->parseCellNumber($this->cellValue($sheet, 'U' . $r), $cell('U', $r));
                if ($total <= 0 && ($sem1 + $sem2) > 0) $total = $sem1 + $sem2;
            }

            $rows[] = [
                'excel_row'                => $r,
                'strategi'                 => $currentStrategi,
                'perangkat_daerah'         => $currentPd,
                'kode'                     => $kodeProgram,          // kode PROGRAM (kolom D / turunan)
                'kode_kegiatan'            => $kodeKegiatan,         // kode KEGIATAN (turunan/prefix F)
                'kode_sub'                 => $kodeSub,              // kode SUB KEGIATAN (prefix kolom G)
                'program'                  => $rowProgram,
                'kegiatan'                 => $rowKegiatan,
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
                'realisasi_sem1'           => $sem1,
                'realisasi_sem2'           => $sem2,
                'realisasi_total'          => $total,
            ];
        }

        return $rows;
    }

    private function parseMatrixFile(string $filePath, string $ext): array
    {
        return $this->parseRatFile($filePath, $ext, true);
    }

    /**
     * Nilai sel siap pakai: untuk sel berumus dipakai HASIL rumusnya. Dulu teks rumus
     * ikut diurai jadi angka — "=SUM(S6:T6)" terbaca Rp 66 sebagai Total Realisasi.
     */
    private function cellValue(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $coord)
    {
        $cell = $sheet->getCell($coord);
        if (! $cell->isFormula()) {
            return $cell->getValue();
        }
        try {
            return $cell->getCalculatedValue();
        } catch (\Throwable) {
            return $cell->getOldCalculatedValue();   // nilai tersimpan dari Excel
        }
    }

    /**
     * Konversi nilai sel menjadi angka dengan aman untuk format Indonesia MAUPUN
     * Inggris. Sel Excel bertipe angka asli dipakai langsung (paling akurat).
     * Untuk teks: pemisah desimal ditentukan dari tanda paling KANAN sehingga
     * "1.500.000" = 1.500.000 dan "1.500.000,50" = 1.500.000,5 (tidak lagi 100× keliru).
     *
     * PENTING: is_numeric TIDAK dipakai untuk string — karena "1.500" (ribuan
     * Indonesia) akan salah dianggap 1,5 oleh PHP.
     */
    private function parseCellNumber($rawVal, string $strFallback): float
    {
        // Sel Excel bertipe angka asli → langsung (bukan string).
        if (is_int($rawVal) || is_float($rawVal)) {
            return (float) $rawVal;
        }

        $s = (string) $rawVal;
        if (trim($s) === '') {
            $s = $strFallback;
        }
        // Sisakan hanya digit, titik, koma, minus.
        $s = preg_replace('/[^\d.,\-]/', '', trim((string) $s));
        if ($s === '' || $s === '-') {
            return 0.0;
        }

        $neg = str_starts_with($s, '-');
        $s   = ltrim($s, '-');

        $hasDot   = str_contains($s, '.');
        $hasComma = str_contains($s, ',');

        if ($hasDot && $hasComma) {
            // Pemisah desimal = simbol paling kanan.
            if (strrpos($s, ',') > strrpos($s, '.')) {
                $s = str_replace('.', '', $s);   // ID: titik ribuan
                $s = str_replace(',', '.', $s);  // ID: koma desimal
            } else {
                $s = str_replace(',', '', $s);   // EN: koma ribuan, titik desimal
            }
        } elseif ($hasComma) {
            $after = substr($s, strrpos($s, ',') + 1);
            $s = (substr_count($s, ',') === 1 && strlen($after) >= 1 && strlen($after) <= 2)
                ? str_replace(',', '.', $s)      // desimal (mis. 1500,50)
                : str_replace(',', '', $s);      // ribuan (mis. 1,500,000)
        } elseif ($hasDot) {
            $after = substr($s, strrpos($s, '.') + 1);
            if (! (substr_count($s, '.') === 1 && strlen($after) >= 1 && strlen($after) <= 2)) {
                $s = str_replace('.', '', $s);   // ribuan (mis. 1.500 / 1.500.000)
            }
            // else: biarkan sebagai desimal (mis. 1500.50)
        }

        return ($neg ? -1 : 1) * (float) $s;
    }

    private function matchRowsToSubKegiatan(array $rawRows, int $tahun, int $semester): array
    {
        $allSk = SubKegiatan::with(['kegiatan.program.perangkatDaerah', 'kegiatan.program.strategi'])
            ->where('is_active', true)->get();

        $skByName = $allSk->groupBy(fn($sk) => $this->normalizeSkName($sk->nama_sub_kegiatan));

        // Peta kegiatan untuk auto-create sub baru (Tier 4/5).
        $allKegiatan    = Kegiatan::with(['program.perangkatDaerah', 'program.strategi'])->get();
        $kegiatanByName = $allKegiatan->groupBy(fn($k) => $this->normalizeSkName($k->nama_kegiatan));

        $existingAlok = LaporanOppkpke::where('tahun', $tahun)->pluck('alokasi_anggaran', 'sub_kegiatan_id');
        $existingIds  = $existingAlok->keys()->flip();

        // Normalisasi PD (buang tanda baca) — konsisten dgn forceCreateSkFromRow.
        $pdNorm = fn (string $s) => trim(preg_replace('/\s+/', ' ', strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $s))));

        // Kode kegiatan: kolom kode-nya, atau — bila kosong — diturunkan dari kode sub
        // kegiatan anak-anaknya (5 segmen pertama) asalkan seragam.
        $kegCodeById = [];
        foreach ($allSk->groupBy('kegiatan_id') as $kegId => $subs) {
            $own = trim((string) optional($subs->first()->kegiatan)->kode);
            if ($own !== '') { $kegCodeById[$kegId] = $own; continue; }
            $prefixes = $subs->map(fn ($sk) => $this->deriveHierarchyCodes(trim((string) $sk->kode) ?: null)[1])->filter()->unique();
            if ($prefixes->count() === 1) $kegCodeById[$kegId] = $prefixes->first();
        }

        // Skor kemiripan nama 0..1 (input sudah $pdNorm). Fuzzy hanya menerima nama yang
        // memang sama — salah ketik, singkatan, atau nama terpotong — bukan sub kegiatan
        // "saudara" yang kebetulan berawalan sama ("... Usia Lanjut" vs "... Usia Produktif").
        $nameScore = function (string $a, string $b): float {
            if ($a === '' || $b === '') return 0.0;
            if ($a === $b) return 1.0;
            [$short, $long] = strlen($a) <= strlen($b) ? [$a, $b] : [$b, $a];
            if (strlen($short) >= 25 && str_starts_with($long, $short . ' ')) return 0.95;
            return 1 - levenshtein($a, $b) / max(strlen($a), strlen($b));
        };

        // Apakah kegiatan kandidat sama dengan kolom Kegiatan di file?
        // null = tak diketahui (kegiatan placeholder "Kegiatan <nama sub>" buatan import lama).
        $kegFit = function ($sk, string $rowKegKey) use ($pdNorm, $nameScore): ?bool {
            $kn = $pdNorm((string) optional($sk->kegiatan)->nama_kegiatan);
            $sn = $pdNorm((string) $sk->nama_sub_kegiatan);
            if ($kn === '' || ($sn !== '' && str_contains($kn, $sn))) return null;
            if ($kn === $rowKegKey) return true;
            [$short, $long] = strlen($kn) <= strlen($rowKegKey) ? [$kn, $rowKegKey] : [$rowKegKey, $kn];
            if (strlen($short) >= 15 && str_contains($long, $short)) return true;
            // Seluruh kata nama yang lebih pendek (min. 3 kata) ada di nama lain — mis. singkatan:
            // "Pengelolaan Pendidikan PAUD" ⊂ "Pengelolaan Pendidikan Anak Usia Dini (PAUD)".
            $ta = explode(' ', $kn);
            $tb = explode(' ', $rowKegKey);
            [$ts, $tl] = count($ta) <= count($tb) ? [$ta, $tb] : [$tb, $ta];
            if (count($ts) >= 3 && ! array_diff($ts, $tl)) return true;
            return $nameScore($kn, $rowKegKey) >= 0.85;
        };
        // Cocok PD: baris yang menyebut PD WAJIB sama PD-nya; baris tanpa PD tak membatasi.
        $pdMatch = function ($skPdName, string $rowPd) use ($pdNorm) {
            if ($rowPd === '') return true;
            $a = $pdNorm((string) ($skPdName ?? ''));
            $b = $pdNorm($rowPd);
            if ($a === '' || $b === '') return false;
            return $a === $b || str_contains($a, $b) || str_contains($b, $a);
        };
        $skPd  = fn ($sk) => optional($sk->kegiatan?->program?->perangkatDaerah)->nama;
        $kegPd = fn ($k)  => optional($k->program?->perangkatDaerah)->nama;

        // Strategi aktif (untuk resolusi & tampilan) + program (untuk cek "program baru").
        $activeStrategi = StrategiOppkpke::where('is_active', true)->get();
        $progsByPd = Program::with('perangkatDaerah')->get(['id', 'perangkat_daerah_id', 'kode_program', 'nama_program'])
            ->groupBy(fn ($p) => $pdNorm((string) (optional($p->perangkatDaerah)->nama ?? '')));

        // Apakah program utk baris ini AKAN dibuat baru? (bila ya → butuh strategi valid)
        $needsNewProgram = function (string $rowPd, string $progName, string $kode) use ($progsByPd, $pdNorm) {
            // Cerminkan forceCreateSkFromRow(): program dibuat baru bila TIDAK ada
            // program di PD yang cocok berdasarkan kode ATAU nama. Kolom nama program
            // kosong TAPI kode ada (mis. kode turunan dari kode sub kegiatan) tetap
            // membuat program baru → jadi tetap butuh strategi valid. Karena itu jangan
            // langsung return false hanya karena nama program kosong.
            $pn = $pdNorm($progName);
            $rp = $pdNorm($rowPd);
            $cands = collect();
            foreach ($progsByPd as $key => $ps) {
                if ($rp !== '' && ($key === $rp || ($key !== '' && (str_contains($key, $rp) || str_contains($rp, $key))))) {
                    $cands = $cands->merge($ps);
                }
            }
            $exists = $cands->contains(function ($p) use ($pn, $kode, $pdNorm) {
                $npn = $pdNorm((string) $p->nama_program);
                return $npn === $pn
                    || ($pn !== '' && (str_contains($npn, $pn) || str_contains($pn, $npn)))
                    || ($kode !== '' && strcasecmp(trim((string) $p->kode_program), $kode) === 0);
            });
            return ! $exists;
        };

        $result    = [];
        $rowNum    = 0;
        $seenSkIds = []; // SK yang sudah dicocokkan → deteksi baris duplikat di file

        // Helper penambah baris hasil (mengurangi duplikasi & risiko salah tulis).
        $push = function (array $raw, int $rowNum, string $status, $sk, $keg) use (&$result, $existingIds, $skPd, $kegPd) {
            $result[] = array_merge($raw, [
                'row_num'          => $rowNum,
                'status'           => $status,
                'sub_kegiatan_id'  => $sk?->id,
                'kegiatan_id'      => $keg?->id,
                'matched_sk_nama'  => $sk ? $sk->nama_sub_kegiatan : ($keg ? '[Baru] ' . $raw['sub_kegiatan'] : null),
                'matched_pd_nama'  => $sk ? $skPd($sk) : ($keg ? $kegPd($keg) : null),
                'matched_strategi' => $sk
                    ? optional($sk->kegiatan?->program?->strategi)->nama
                    : ($keg ? optional($keg->program?->strategi)->nama : null),
                'has_existing'     => $sk ? isset($existingIds[$sk->id]) : false,
            ]);
        };

        // Pemilih kegiatan dari kandidat (PD-aware, dipertajam kode).
        $pickKegiatan = function ($cands, string $rowPd, string $kode) use ($pdMatch, $kegPd) {
            $cands = $cands->filter(fn ($k) => $pdMatch($kegPd($k), $rowPd))->values();
            if ($cands->isEmpty()) return null;
            if ($cands->count() === 1) return $cands->first();
            if ($kode) {
                $byKode = $cands->filter(fn ($k) => optional($k->program)->kode_program === $kode);
                if ($byKode->count() === 1) return $byKode->first();
            }
            return $cands->first();
        };

        // Pertajam kandidat bernama sama memakai data baris itu sendiri (bukan menebak):
        // kode sub → kode kegiatan → nama kegiatan → kode program.
        // Hasil: ['match' => sk|null, 'pool' => sisa kandidat, 'other_keg' => bool].
        // other_keg = semua kandidat ada di kegiatan LAIN dari kolom Kegiatan file → baris ini sub baru.
        $narrow = function ($pool, array $raw) use ($kegCodeById, $kegFit, $pdNorm, $existingIds) {
            $kodeSub = trim((string) ($raw['kode_sub'] ?? ''));
            $kodeKeg = trim((string) ($raw['kode_kegiatan'] ?? ''));
            $kode    = trim((string) ($raw['kode'] ?? ''));
            $rowKeg  = $pdNorm((string) ($raw['kegiatan'] ?? ''));

            $steps = [
                fn ($sk) => $kodeSub !== '' && strcasecmp(trim((string) $sk->kode), $kodeSub) === 0,
                fn ($sk) => $kodeKeg !== '' && strcasecmp((string) ($kegCodeById[$sk->kegiatan_id] ?? ''), $kodeKeg) === 0,
            ];
            $codeConfirmed = false;   // kode sub kegiatan identik → bukti lebih kuat dari nama kegiatan
            foreach ($steps as $i => $step) {
                $hit = $pool->filter($step)->values();
                if ($hit->count() === 1) return ['match' => $hit->first(), 'pool' => $hit, 'other_keg' => false];
                if ($hit->count() > 1) { $pool = $hit; $codeConfirmed = $codeConfirmed || $i === 0; }
            }

            if ($rowKeg !== '') {
                $fit     = $pool->filter(fn ($sk) => $kegFit($sk, $rowKeg) === true)->values();
                $unknown = $pool->filter(fn ($sk) => $kegFit($sk, $rowKeg) === null)->values();
                $hit     = $fit->isNotEmpty() ? $fit : $unknown;
                // Nama kegiatan beda TIDAK membatalkan kode sub kegiatan yang identik (kegiatan di DB bisa keliru).
                if ($hit->isEmpty() && $codeConfirmed) $hit = $pool;
                if ($hit->isEmpty()) return ['match' => null, 'pool' => $pool, 'other_keg' => true];
                if ($hit->count() === 1) return ['match' => $hit->first(), 'pool' => $hit, 'other_keg' => false];
                $pool = $hit;
            }

            // Strategi baris (RAT): padanan sub kegiatan per strategi dibedakan di sini.
            $strId = $raw['strategi_id_file'] ?? null;
            if ($strId) {
                $hit = $pool->filter(fn ($sk) => (int) optional($sk->kegiatan?->program)->strategi_id === (int) $strId)->values();
                if ($hit->count() === 1) return ['match' => $hit->first(), 'pool' => $hit, 'other_keg' => false];
                if ($hit->count() > 1) $pool = $hit;
            }

            if ($kode !== '') {
                $hit = $pool->filter(fn ($sk) => optional($sk->kegiatan?->program)->kode_program === $kode)->values();
                if ($hit->count() === 1) return ['match' => $hit->first(), 'pool' => $hit, 'other_keg' => false];
                if ($hit->count() > 1) $pool = $hit;
            }

            // Terakhir: satu-satunya kandidat yang sudah punya data tahun ini (mis. hasil
            // import RAT) — realisasi ikut ke sub kegiatan tempat alokasinya tercatat.
            $hit = $pool->filter(fn ($sk) => isset($existingIds[$sk->id]))->values();
            if ($hit->count() === 1) return ['match' => $hit->first(), 'pool' => $hit, 'other_keg' => false];

            return ['match' => null, 'pool' => $pool, 'other_keg' => false];
        };

        // Info satu kandidat sub kegiatan untuk dropdown pilihan admin di preview.
        $candInfo = fn ($sk) => [
            'id'            => $sk->id,
            'nama'          => $sk->nama_sub_kegiatan,
            'kode'          => trim((string) $sk->kode) ?: null,
            'kegiatan_id'   => $sk->kegiatan_id,
            'kegiatan'      => optional($sk->kegiatan)->nama_kegiatan,
            'kegiatan_kode' => $kegCodeById[$sk->kegiatan_id] ?? null,
            'program'       => trim(optional($sk->kegiatan?->program)->kode_program . ' ' . optional($sk->kegiatan?->program)->nama_program),
            'pd'            => $skPd($sk),
            'alokasi_db'    => $existingAlok->has($sk->id) ? (float) $existingAlok[$sk->id] : null,
            'claimed_by'    => null, // diisi setelah semua baris dicocokkan
        ];

        // Penjelasan baris TIDAK COCOK + kandidat terdekat di PD yang sama, agar admin
        // bisa memetakannya ke sub kegiatan yang ada atau membuatnya baru (tidak hilang diam-diam).
        $describeNotFound = function (array $raw) use ($allSk, $pdMatch, $skPd, $pdNorm, $nameScore, $candInfo, $kegiatanByName, $kegPd, $existingIds) {
            $rowPd   = trim((string) ($raw['perangkat_daerah'] ?? ''));
            $pdLabel = $rowPd !== '' ? $rowPd : 'semua perangkat daerah';
            $rowKey  = $pdNorm((string) $raw['sub_kegiatan']);
            $near = $allSk->filter(fn ($sk) => $pdMatch($skPd($sk), $rowPd))
                ->map(fn ($sk) => ['sk' => $sk, 'score' => $nameScore($rowKey, $pdNorm($sk->nama_sub_kegiatan))])
                ->filter(fn ($x) => $x['score'] >= 0.5)->sortByDesc('score')->take(4)->values();

            $reasons = [];
            if (! empty($raw['match_note'])) {
                $reasons[] = $raw['match_note'];
            } elseif ($near->isEmpty()) {
                $reasons[] = "Tidak ada sub kegiatan bernama sama atau mirip di {$pdLabel}.";
            } else {
                $reasons[] = "Tidak ada sub kegiatan bernama persis sama di {$pdLabel}; yang paling mirip \""
                    . $near[0]['sk']->nama_sub_kegiatan . '" (' . (int) round($near[0]['score'] * 100) . '%).';
            }
            $keg = trim((string) ($raw['kegiatan'] ?? ''));
            if ($keg === '') {
                $reasons[] = 'Kolom Kegiatan kosong — bila dibuat baru, kegiatannya dibuat otomatis dari nama sub kegiatan.';
            } elseif ($kegiatanByName->get($this->normalizeSkName($keg), collect())->contains(fn ($k) => $pdMatch($kegPd($k), $rowPd))) {
                $reasons[] = "Kegiatan \"{$keg}\" sudah ada — bila dibuat baru, sub kegiatan ditempatkan di bawahnya.";
            } else {
                $reasons[] = "Kegiatan \"{$keg}\" juga belum ada di database — ikut dibuat bila memilih \"Buat baru\".";
            }
            if ($existingIds->isNotEmpty()) {
                $reasons[] = 'Bila baris ini tercantum di RAT, import RAT terbaru lebih dulu — import RAT membuat sub kegiatan barunya otomatis.';
            }

            return [
                'nf_reasons'    => $reasons,
                'nf_candidates' => $near->map(fn ($x) => $candInfo($x['sk']) + ['skor' => (int) round($x['score'] * 100)])->all(),
            ];
        };

        // Penjelasan baris ambigu + daftar kandidat agar admin bisa memilih di preview.
        $describeAmbig = function ($pool, array $raw, string $kind) use ($kegCodeById, $candInfo, $existingAlok) {
            $rowPd   = trim((string) ($raw['perangkat_daerah'] ?? ''));
            $kodeSub = trim((string) ($raw['kode_sub'] ?? ''));
            $kodeKeg = trim((string) ($raw['kode_kegiatan'] ?? ''));
            $pdLabel = $rowPd !== '' ? $rowPd : 'semua perangkat daerah (baris tidak menyebut PD)';

            $reasons   = [];
            $reasons[] = $kind === 'fuzzy'
                ? 'Nama tidak ada persis di database, tetapi hampir sama dengan ' . $pool->count() . ' sub kegiatan di ' . $pdLabel . '.'
                : 'Ada ' . $pool->count() . ' sub kegiatan bernama sama di ' . $pdLabel . ' (beda kegiatan/program).';

            if ($kodeSub === '') {
                $reasons[] = 'File tidak mencantumkan kode sub kegiatan, jadi tidak bisa dibedakan lewat kode.';
            } elseif ($pool->every(fn ($sk) => trim((string) $sk->kode) === '')) {
                $reasons[] = 'Kode ' . $kodeSub . ' dari file belum tersimpan di kandidat mana pun (kode sub kegiatan di database masih kosong).';
            } else {
                $reasons[] = 'Kode ' . $kodeSub . ' dari file tidak cocok dengan kode kandidat.';
            }
            if ($kodeKeg !== '' && $pool->every(fn ($sk) => empty($kegCodeById[$sk->kegiatan_id]))) {
                $reasons[] = 'Kegiatan kandidat belum punya kode, sehingga kode kegiatan ' . $kodeKeg . ' tidak bisa dipakai pembeda.';
            }
            $reasons[] = trim((string) ($raw['kegiatan'] ?? '')) === ''
                ? 'Kolom Kegiatan di file kosong, jadi tidak bisa dibedakan dari nama kegiatan.'
                : 'Nama kegiatan di file cocok dengan lebih dari satu kandidat.';
            $withData = $pool->filter(fn ($sk) => $existingAlok->has($sk->id))->count();
            $reasons[] = $withData === 0
                ? 'Belum ada kandidat yang punya data tahun ini (mis. dari import RAT) untuk dijadikan acuan.'
                : $withData . ' kandidat sama-sama sudah punya data tahun ini — kemungkinan sub kegiatan ganda di database.';

            $candidates = $pool->map($candInfo)->sortBy('kegiatan')->values()->all();

            return ['ambig_kind' => $kind, 'ambig_reasons' => $reasons, 'ambig_candidates' => $candidates];
        };

        foreach ($rawRows as $raw) {
            $rowNum++;
            $normalized = $this->normalizeSkName($raw['sub_kegiatan']);
            $kode       = trim($raw['kode']);
            $rowPd      = trim($raw['perangkat_daerah']);

            // Resolusi strategi dari file (kode/nama) untuk tampilan preview.
            $stratObj = StrategiOppkpke::resolveFromText($raw['strategi'] ?? '', $activeStrategi);
            $raw['strategi_file']    = $stratObj?->nama;
            $raw['strategi_id_file'] = $stratObj?->id;

            $matched   = null;
            $ambigPool = null;
            $ambigKind = 'exact';
            $otherKeg  = false;

            // ── Cocokkan sub kegiatan (WAJIB cocok PD bila baris menyebut PD) ──
            $candidates = $skByName->get($normalized, collect())
                ->filter(fn ($sk) => $pdMatch($skPd($sk), $rowPd))->values();

            if ($candidates->count() === 1) {
                // Nama sama tetapi kode KEGIATAN-nya berbeda (keduanya kode baku) → sub kegiatan lain,
                // mis. "…Kemitraan Usaha" untuk nelayan (3.25.03.2.02) vs pembudi daya (3.25.04.2.02).
                $only   = $candidates->first();
                $rowSub = trim((string) ($raw['kode_sub'] ?? ''));
                $dbSub  = trim((string) $only->kode);
                $std    = '/^\d\.\d{2}\.\d{2}\.\d\.\d{2}\.\d{4}$/';
                if (preg_match($std, $rowSub) && preg_match($std, $dbSub)
                    && $this->deriveHierarchyCodes($rowSub)[1] !== $this->deriveHierarchyCodes($dbSub)[1]) {
                    $otherKeg = true;
                    $raw['match_note'] = "Nama sama dengan sub kegiatan berkode {$dbSub} (kegiatan lain), sedangkan kode di file {$rowSub} — dibuat sebagai sub kegiatan tersendiri.";
                } else {
                    $matched = $only;
                }
            } elseif ($candidates->count() > 1) {
                $n = $narrow($candidates, $raw);
                $matched  = $n['match'];
                $otherKeg = $n['other_keg'];
                if (! $matched && ! $otherKeg) $ambigPool = $n['pool'];   // jangan menebak — biar admin putuskan
                if ($otherKeg) {
                    $raw['match_note'] = 'Nama sama sudah ada di kegiatan lain ('
                        . $candidates->map(fn ($sk) => optional($sk->kegiatan)->nama_kegiatan)->filter()->unique()->implode('; ')
                        . ') — dibuat sebagai sub kegiatan tersendiri di kegiatan sesuai file.';
                }
            }

            // Fuzzy (PD-aware) — hanya bila nama tidak ada persis & tidak ambigu.
            // Kandidat berawalan sama baru diterima bila namanya memang sama (skor ≥ 0,90).
            if (! $matched && ! $ambigPool && ! $otherKeg && strlen($normalized) >= 20) {
                $prefix = substr($normalized, 0, 25);
                $fuzzy  = $allSk->filter(fn ($sk) =>
                    $pdMatch($skPd($sk), $rowPd) && (
                        str_contains($this->normalizeSkName($sk->nama_sub_kegiatan), $prefix) ||
                        str_contains($prefix, substr($this->normalizeSkName($sk->nama_sub_kegiatan), 0, 25))
                    )
                )->values();

                $rowKey = $pdNorm($raw['sub_kegiatan']);
                $scored = $fuzzy->map(fn ($sk) => ['sk' => $sk, 'score' => $nameScore($rowKey, $pdNorm($sk->nama_sub_kegiatan))])
                    ->sortByDesc('score')->values();
                $strong = $scored->filter(fn ($s) => $s['score'] >= 0.90)->values();

                if ($strong->count() === 1 || ($strong->count() > 1 && $strong[0]['score'] - $strong[1]['score'] >= 0.03)) {
                    $matched = $strong[0]['sk'];
                } elseif ($strong->count() > 1) {
                    // Beberapa kandidat nyaris identik (mis. padanan per strategi) → pertajam seperti nama persis.
                    $top = $strong->filter(fn ($s) => $strong[0]['score'] - $s['score'] < 0.03)->pluck('sk')->values();
                    $n   = $narrow($top, $raw);
                    $matched = $n['match'];
                    if (! $matched) { $ambigPool = $n['other_keg'] ? $top : $n['pool']; $ambigKind = 'fuzzy'; }
                } elseif ($scored->isNotEmpty()) {
                    // Hanya mirip awalan → dianggap sub kegiatan berbeda (Data Baru); tampilkan sebagai info.
                    $raw['match_note'] = 'Mirip awalan dengan "' . $scored[0]['sk']->nama_sub_kegiatan . '" ('
                        . (int) round($scored[0]['score'] * 100) . '%), tetapi namanya berbeda — diperlakukan sebagai sub kegiatan baru.';
                }
            }

            if ($matched) {
                $status = isset($seenSkIds[$matched->id]) ? 'duplicate' : 'matched';
                if ($status === 'matched') $seenSkIds[$matched->id] = true;
                $push($raw, $rowNum, $status, $matched, null);
                continue;
            }

            if ($ambigPool) {
                $push(array_merge($raw, $describeAmbig($ambigPool, $raw, $ambigKind)), $rowNum, 'ambiguous', null, null);
                continue;
            }

            // Tier 4: nama sub == nama kegiatan → buat sub baru di kegiatan itu (PD-aware).
            $keg = $pickKegiatan($kegiatanByName->get($normalized, collect()), $rowPd, $kode);

            // Tier 5: cari kegiatan lewat kolom "kegiatan" file (PD-aware).
            if (! $keg && ! empty($raw['kegiatan'])) {
                $keg = $pickKegiatan($kegiatanByName->get($this->normalizeSkName($raw['kegiatan']), collect()), $rowPd, $kode);
            }

            if ($keg) {
                $push($raw, $rowNum, 'new_sk', null, $keg);
                continue;
            }

            // not_found → forceCreateSkFromRow akan membangun hierarki di PD yang benar.
            // Tandai bila program HARUS dibuat baru namun strategi file tak dikenali
            // (baris tsb akan DILEWATI saat eksekusi — tidak ditebak ke strategi lain).
            $raw['strategi_warn'] = ! $stratObj && $needsNewProgram($rowPd, (string) ($raw['program'] ?? ''), $kode);
            $push(array_merge($raw, $describeNotFound($raw)), $rowNum, 'not_found', null, null);
        }

        // Tandai kandidat ambigu yang sudah dipakai baris lain (alokasinya nanti dijumlahkan).
        $claimed = [];
        foreach ($result as $r) {
            if ($r['status'] === 'matched' && $r['sub_kegiatan_id']) $claimed[$r['sub_kegiatan_id']] ??= $r['row_num'];
        }
        foreach ($result as &$r) {
            if ($r['status'] !== 'ambiguous') continue;
            foreach ($r['ambig_candidates'] as &$c) $c['claimed_by'] = $claimed[$c['id']] ?? null;
            unset($c);
        }
        unset($r);

        return $result;
    }

    /**
     * Turunkan kode program & kegiatan dari kode sub kegiatan (format Permendagri).
     * "1.02.02.2.01.0014" → program "1.02.02" (3 segmen), kegiatan "1.02.02.2.01" (5).
     *
     * @return array{0: ?string, 1: ?string}  [kodeProgram, kodeKegiatan]
     */
    private function deriveHierarchyCodes(?string $fullSubCode): array
    {
        if (! $fullSubCode) {
            return [null, null];
        }
        $p = explode('.', trim($fullSubCode));
        $prog = count($p) >= 3 ? implode('.', array_slice($p, 0, 3)) : null;
        $keg  = count($p) >= 5 ? implode('.', array_slice($p, 0, 5)) : null;

        return [$prog, $keg];
    }

    private function normalizeSkName(string $name): string
    {
        // Spasi non-standar dari Excel (NBSP dsb.) → spasi biasa; tanpa ini
        // "Nelayan Kecil\u{00A0}" dianggap berbeda dari "Nelayan Kecil".
        $name = preg_replace('/[\x{00A0}\x{1680}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}\x{FEFF}]/u', ' ', $name) ?? $name;
        // Awalan penanda daftar ("- Fasilitasi ...") bukan bagian nama.
        $name = preg_replace('/^[\s\-\x{2013}\x{2014}\x{2022}*]+/u', '', $name) ?? $name;
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
        $semester = (int) $request->get('semester', 2);
        $rows     = $this->buildMatrixRows($tahun, $request);

        // ── Susun ulang baris datar → Strategi ▸ Perangkat Daerah ▸ Program ▸ Kegiatan
        $tree = [];
        $cur  = null;
        foreach ($rows as $row) {
            if (($row['_type'] ?? '') === 'strategi_header') {
                if ($cur) $tree[] = $cur;
                $cur = ['label' => $row['label'], 'kode' => $row['kode'] ?? '', 'dinas' => []];
                continue;
            }
            if (!$cur) continue;
            $namaDinas = trim((string) $row['perangkat_daerah']) !== ''
                ? trim((string) $row['perangkat_daerah'])
                : '(Tanpa Perangkat Daerah)';
            $cur['dinas'][$namaDinas][] = $row;
        }
        if ($cur) $tree[] = $cur;

        foreach ($tree as &$strat) {
            ksort($strat['dinas'], SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($strat['dinas'] as &$list) {
                usort($list, function ($a, $b) {
                    return [$a['kode'], $a['program'], $a['kegiatan'], $a['sub_kegiatan']]
                       <=> [$b['kode'], $b['program'], $b['kegiatan'], $b['sub_kegiatan']];
                });
            }
            unset($list);
        }
        unset($strat);

        $sum = fn(array $list, string $key) => array_sum(array_map(fn($r) => (float) ($r[$key] ?? 0), $list));
        $rp  = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $pct = fn($num, $den) => $den > 0 ? round(($num / $den) * 100, 1) : 0;

        $semLabel    = $semester == 1 ? 'I (JANUARI - JUNI)' : 'II (JULI - DESEMBER)';
        $moneyFmt    = '#,##0;[Red]-#,##0;"-"';
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Matriks OPPKPKE');
        $sheet->setShowGridlines(false);
        $sheet->getSheetView()->setZoomScale(85);

        // ── Judul ───────────────────────────────────────────────
        $sheet->mergeCells('A1:U1');
        $sheet->setCellValue('A1', 'LAPORAN OPTIMALISASI PELAKSANAAN PENGENTASAN KEMISKINAN DAN PENGHAPUSAN KEMISKINAN EKSTREM (OPPKPKE)');
        $sheet->mergeCells('A2:U2');
        $sheet->setCellValue('A2', "SEMESTER {$semLabel} TAHUN {$tahun}");

        $metaParts = [];
        if ($request->filled('strategi_id')) {
            $metaParts[] = 'Strategi: ' . (optional(StrategiOppkpke::find($request->strategi_id))->nama ?? '-');
        }
        if ($request->filled('perangkat_daerah_id')) {
            $metaParts[] = 'Perangkat Daerah: ' . (optional(PerangkatDaerah::find($request->perangkat_daerah_id))->nama ?? '-');
        }
        $metaParts[] = 'Satuan nilai: Rupiah (Rp)';
        $metaParts[] = 'Dicetak: ' . now()->format('d-m-Y H:i');

        $sheet->mergeCells('A3:U3');
        $sheet->setCellValue('A3', implode('   •   ', $metaParts));

        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A3')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF64748B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getRowDimension(3)->setRowHeight(16);
        $sheet->getRowDimension(4)->setRowHeight(8); // margin atas tabel

        // ── Header tabel ────────────────────────────────────────
        $hRow     = 5;
        $hRow2    = $hRow + 1;
        $hRow3    = $hRow2 + 1;
        $headers1 = [
            'A' => 'No', 'B' => 'Strategi OPPKPKE', 'C' => 'Perangkat Daerah',
            'D' => 'Kode', 'E' => 'Program', 'F' => 'Kegiatan', 'G' => 'Sub Kegiatan',
            'H' => 'Aktifitas Real', 'K' => 'Alokasi Anggaran (Rp)',
            'L' => 'Sumber Pembiayaan', 'M' => 'Sifat Bantuan', 'N' => 'Lokasi',
            'O' => 'Jumlah Sasaran Penerima Manfaat', 'P' => 'Besaran Manfaat',
            'Q' => 'Jenis Bantuan', 'R' => 'Durasi Pemberian Bantuan',
            'S' => 'Realisasi (Rp)',
        ];

        $sheet->mergeCells("H{$hRow}:J{$hRow}");
        $sheet->mergeCells("S{$hRow}:U{$hRow}");
        foreach (['A','B','C','D','E','F','G','K','L','M','N','O','P','Q','R'] as $col) {
            $sheet->mergeCells("{$col}{$hRow}:{$col}{$hRow2}");
        }
        foreach ($headers1 as $col => $label) {
            $sheet->setCellValue("{$col}{$hRow}", $label);
        }
        $sheet->setCellValue("H{$hRow2}", 'Langsung');
        $sheet->setCellValue("I{$hRow2}", 'Tidak Langsung');
        $sheet->setCellValue("J{$hRow2}", 'Penunjang');
        $sheet->setCellValue("S{$hRow2}", 'Sem. 1');
        $sheet->setCellValue("T{$hRow2}", 'Sem. 2');
        $sheet->setCellValue("U{$hRow2}", 'Total');

        foreach (range('A', 'U') as $i => $col) {
            $sheet->setCellValue("{$col}{$hRow3}", $i + 1);
        }

        $sheet->getStyle("A{$hRow}:U{$hRow2}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFB0C4DE']]],
        ]);
        $sheet->getStyle("A{$hRow3}:U{$hRow3}")->applyFromArray([
            'font'      => ['size' => 8, 'color' => ['argb' => 'FF1E3A5F']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDCE6F1']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFB0C4DE']]],
        ]);
        $sheet->getRowDimension($hRow)->setRowHeight(30);
        $sheet->getRowDimension($hRow2)->setRowHeight(22);
        $sheet->getRowDimension($hRow3)->setRowHeight(14);

        // ── Isi tabel ───────────────────────────────────────────
        $dataStartRow = $hRow3 + 1;
        $currentRow   = $dataStartRow;

        $thinBorder   = ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => 'FFD5DDE6']];
        $mediumBorder = ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF9DB2C8']];

        $grand     = ['alokasi' => 0.0, 'sem1' => 0.0, 'sem2' => 0.0, 'total' => 0.0, 'sub' => 0, 'dinas' => 0];
        $ringkasan = [];

        foreach ($tree as $strat) {
            if (empty($strat['dinas'])) continue;
            $allStratRows = array_merge(...array_values($strat['dinas']));
            $sAlokasi = $sum($allStratRows, 'alokasi_anggaran');
            $sSem1    = $sum($allStratRows, 'realisasi_sem1');
            $sSem2    = $sum($allStratRows, 'realisasi_sem2');
            $sTotal   = $sum($allStratRows, 'realisasi_total');

            // ── Band Strategi ───────────────────────────────────
            $judulStrat = trim(($strat['kode'] ? "STRATEGI {$strat['kode']} — " : '') . strtoupper($strat['label']));
            $sheet->mergeCells("A{$currentRow}:U{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", $judulStrat
                . '     |     ' . count($strat['dinas']) . ' Perangkat Daerah'
                . '  •  ' . count($allStratRows) . ' Sub Kegiatan'
                . '  •  Alokasi ' . $rp($sAlokasi)
                . '  •  Realisasi ' . $rp($sTotal) . ' (' . $pct($sTotal, $sAlokasi) . '%)');
            $sheet->getStyle("A{$currentRow}:U{$currentRow}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2E74B5']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                'borders'   => ['allBorders' => $mediumBorder],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(22);
            $currentRow++;

            foreach ($strat['dinas'] as $namaDinas => $list) {
                // Kelompokkan per program → kegiatan
                $programs = [];
                foreach ($list as $r) {
                    $pk = trim((string) $r['kode']) . '|' . trim((string) $r['program']);
                    $programs[$pk][] = $r;
                }

                $dAlokasi = $sum($list, 'alokasi_anggaran');
                $dSem1    = $sum($list, 'realisasi_sem1');
                $dSem2    = $sum($list, 'realisasi_sem2');
                $dTotal   = $sum($list, 'realisasi_total');

                // ── Band Perangkat Daerah ───────────────────────
                $sheet->mergeCells("A{$currentRow}:U{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", '▍ ' . strtoupper($namaDinas)
                    . '     |     ' . count($programs) . ' Program'
                    . '  •  ' . count($list) . ' Sub Kegiatan'
                    . '  •  Alokasi ' . $rp($dAlokasi)
                    . '  •  Realisasi ' . $rp($dTotal) . ' (' . $pct($dTotal, $dAlokasi) . '%)');
                $sheet->getStyle("A{$currentRow}:U{$currentRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF14304F']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDCE6F1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                    'borders'   => [
                        'allBorders' => $thinBorder,
                        'top'        => $mediumBorder,
                        'bottom'     => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF9DB2C8']],
                    ],
                ]);
                $sheet->getRowDimension($currentRow)->setRowHeight(19);
                $currentRow++;

                $blockStart = $currentRow;
                $no         = 1;
                $progIdx    = 0;

                foreach ($programs as $pRows) {
                    $progStart = $currentRow;
                    $progIdx++;

                    // Kelompokkan lagi per kegiatan agar sel berulang bisa digabung vertikal
                    $kegiatanGroups = [];
                    foreach ($pRows as $r) {
                        $kegiatanGroups[trim((string) $r['kegiatan'])][] = $r;
                    }

                    foreach ($kegiatanGroups as $kRows) {
                        $kegStart = $currentRow;
                        foreach ($kRows as $r) {
                            $cols = [
                                'A' => $no++,
                                'B' => $r['strategi'],
                                'C' => $r['perangkat_daerah'],
                                'D' => $r['kode'],
                                'E' => $r['program'],
                                'F' => $r['kegiatan'],
                                'G' => $r['sub_kegiatan'],
                                'H' => $r['aktivitas_langsung'],
                                'I' => $r['aktivitas_tidak_langsung'],
                                'J' => $r['aktivitas_penunjang'],
                                'K' => (float) $r['alokasi_anggaran'],
                                'L' => $r['sumber_pembiayaan'],
                                'M' => $r['sifat_bantuan'],
                                'N' => $r['lokasi'],
                                'O' => $r['jumlah_sasaran'],
                                'P' => $r['besaran_manfaat'],
                                'Q' => $r['jenis_bantuan'],
                                'R' => $r['durasi_pemberian'],
                                'S' => (float) $r['realisasi_sem1'],
                                'T' => (float) $r['realisasi_sem2'],
                                'U' => (float) $r['realisasi_total'],
                            ];
                            foreach ($cols as $col => $value) {
                                $sheet->setCellValue("{$col}{$currentRow}", $value);
                            }

                            $sheet->getStyle("A{$currentRow}:U{$currentRow}")->applyFromArray([
                                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $progIdx % 2 === 1 ? 'FFFFFFFF' : 'FFF6F9FC']],
                                'borders'   => ['allBorders' => $thinBorder],
                                'font'      => ['size' => 8, 'color' => ['argb' => 'FF1F2937']],
                                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true, 'indent' => 1],
                            ]);
                            $sheet->getStyle("A{$currentRow}")->getAlignment()
                                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setIndent(0);
                            foreach (['K', 'S', 'T', 'U'] as $col) {
                                $sheet->getStyle("{$col}{$currentRow}")->getNumberFormat()->setFormatCode($moneyFmt);
                                $sheet->getStyle("{$col}{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                            }
                            foreach (['L', 'M', 'O', 'R'] as $col) {
                                $sheet->getStyle("{$col}{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            }
                            $sheet->getStyle("U{$currentRow}")->getFont()->setBold(true);
                            $sheet->getRowDimension($currentRow)->setRowHeight($this->estimateRowHeight($r, count($pRows) === 1));

                            $currentRow++;
                        }

                        // Gabung sel Kegiatan yang berulang
                        if ($currentRow - 1 > $kegStart) {
                            $sheet->mergeCells("F{$kegStart}:F" . ($currentRow - 1));
                        }
                    }

                    // Gabung sel Kode & Program → nama program tidak lagi mengulang ke bawah
                    if ($currentRow - 1 > $progStart) {
                        $sheet->mergeCells("D{$progStart}:D" . ($currentRow - 1));
                        $sheet->mergeCells("E{$progStart}:E" . ($currentRow - 1));
                    }
                    // Garis pemisah antar program
                    $sheet->getStyle("A{$progStart}:U{$progStart}")->applyFromArray([
                        'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF8FA9C4']]],
                    ]);
                }

                $blockEnd = $currentRow - 1;

                // Gabung kolom Strategi & Perangkat Daerah untuk satu blok dinas
                if ($blockEnd > $blockStart) {
                    $sheet->mergeCells("B{$blockStart}:B{$blockEnd}");
                    $sheet->mergeCells("C{$blockStart}:C{$blockEnd}");
                }
                $sheet->getStyle("B{$blockStart}:F{$blockEnd}")->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true, 'indent' => 1],
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FF14304F']],
                ]);
                $sheet->getStyle("G{$blockStart}:G{$blockEnd}")->getFont()->setBold(true);

                // ── Subtotal per Perangkat Daerah ───────────────
                $sheet->mergeCells("A{$currentRow}:J{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'SUBTOTAL ' . strtoupper($namaDinas)
                    . '  (' . count($programs) . ' Program / ' . count($list) . ' Sub Kegiatan)  —  Serapan ' . $pct($dTotal, $dAlokasi) . '%');
                $sheet->setCellValue("K{$currentRow}", $dAlokasi);
                $sheet->mergeCells("L{$currentRow}:R{$currentRow}");
                $sheet->setCellValue("S{$currentRow}", $dSem1);
                $sheet->setCellValue("T{$currentRow}", $dSem2);
                $sheet->setCellValue("U{$currentRow}", $dTotal);
                $sheet->getStyle("A{$currentRow}:U{$currentRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF14304F']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEDF3FA']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => $thinBorder, 'top' => $mediumBorder, 'bottom' => $mediumBorder],
                ]);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setIndent(1);
                foreach (['K', 'S', 'T', 'U'] as $col) {
                    $sheet->getStyle("{$col}{$currentRow}")->getNumberFormat()->setFormatCode($moneyFmt);
                    $sheet->getStyle("{$col}{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $sheet->getRowDimension($currentRow)->setRowHeight(20);
                $currentRow++;

                // ── Margin / jarak antar Perangkat Daerah ───────
                $sheet->getRowDimension($currentRow)->setRowHeight(10);
                $currentRow++;

                $ringkasan[] = [
                    'strategi' => $strat['label'],
                    'dinas'    => $namaDinas,
                    'program'  => count($programs),
                    'sub'      => count($list),
                    'alokasi'  => $dAlokasi,
                    'sem1'     => $dSem1,
                    'sem2'     => $dSem2,
                    'total'    => $dTotal,
                ];

                $grand['alokasi'] += $dAlokasi;
                $grand['sem1']    += $dSem1;
                $grand['sem2']    += $dSem2;
                $grand['total']   += $dTotal;
                $grand['sub']     += count($list);
                $grand['dinas']++;
            }

            // ── Total per Strategi ──────────────────────────────
            $sheet->mergeCells("A{$currentRow}:J{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'TOTAL ' . $judulStrat . '  —  Serapan ' . $pct($sTotal, $sAlokasi) . '%');
            $sheet->setCellValue("K{$currentRow}", $sAlokasi);
            $sheet->mergeCells("L{$currentRow}:R{$currentRow}");
            $sheet->setCellValue("S{$currentRow}", $sSem1);
            $sheet->setCellValue("T{$currentRow}", $sSem2);
            $sheet->setCellValue("U{$currentRow}", $sTotal);
            $sheet->getStyle("A{$currentRow}:U{$currentRow}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => $mediumBorder],
            ]);
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setIndent(1);
            foreach (['K', 'S', 'T', 'U'] as $col) {
                $sheet->getStyle("{$col}{$currentRow}")->getNumberFormat()->setFormatCode($moneyFmt);
                $sheet->getStyle("{$col}{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $sheet->getRowDimension($currentRow)->setRowHeight(21);
            $currentRow++;

            // ── Margin antar strategi ───────────────────────────
            $sheet->getRowDimension($currentRow)->setRowHeight(16);
            $currentRow++;
        }

        // ── Grand total ─────────────────────────────────────────
        if (!empty($tree)) {
            $sheet->mergeCells("A{$currentRow}:J{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'GRAND TOTAL SELURUH STRATEGI  ('
                . $grand['dinas'] . ' Perangkat Daerah / ' . $grand['sub'] . ' Sub Kegiatan)  —  Serapan '
                . $pct($grand['total'], $grand['alokasi']) . '%');
            $sheet->setCellValue("K{$currentRow}", $grand['alokasi']);
            $sheet->mergeCells("L{$currentRow}:R{$currentRow}");
            $sheet->setCellValue("S{$currentRow}", $grand['sem1']);
            $sheet->setCellValue("T{$currentRow}", $grand['sem2']);
            $sheet->setCellValue("U{$currentRow}", $grand['total']);
            $sheet->getStyle("A{$currentRow}:U{$currentRow}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0F2D4A']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => $mediumBorder],
            ]);
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setIndent(1);
            foreach (['K', 'S', 'T', 'U'] as $col) {
                $sheet->getStyle("{$col}{$currentRow}")->getNumberFormat()->setFormatCode($moneyFmt);
                $sheet->getStyle("{$col}{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $sheet->getRowDimension($currentRow)->setRowHeight(24);
        } else {
            $sheet->mergeCells("A{$currentRow}:U{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Tidak ada data laporan untuk filter yang dipilih.');
            $sheet->getStyle("A{$currentRow}")->applyFromArray([
                'font'      => ['italic' => true, 'size' => 10, 'color' => ['argb' => 'FF64748B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        // ── Lebar kolom, freeze pane & setelan cetak ────────────
        $widths = [
            'A'=>5,'B'=>22,'C'=>26,'D'=>18,'E'=>32,'F'=>34,'G'=>38,
            'H'=>24,'I'=>24,'J'=>20,'K'=>18,'L'=>12,'M'=>16,'N'=>20,
            'O'=>14,'P'=>18,'Q'=>14,'R'=>14,'S'=>16,'T'=>16,'U'=>17,
        ];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        $sheet->freezePane("D{$dataStartRow}");

        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($hRow, $hRow3);
        $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);
        $sheet->getHeaderFooter()->setOddFooter('&L&9Matriks OPPKPKE ' . $tahun . '&R&9Halaman &P dari &N');

        // ── Sheet Ringkasan per Perangkat Daerah ────────────────
        $this->buildRingkasanSheet($spreadsheet, $ringkasan, $grand, $tahun, $semLabel, $moneyFmt);
        $spreadsheet->setActiveSheetIndex(0);

        // ── Output ──────────────────────────────────────────────
        $filename = "Matriks_OPPKPKE_{$tahun}_Semester{$semester}.xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Perkiraan tinggi baris supaya teks panjang tetap terbaca tanpa membuat
     * semua baris jadi setinggi baris terpanjang.
     */
    private function estimateRowHeight(array $r, bool $includeProgram = false): float
    {
        $fields = [
            'sub_kegiatan' => 38, 'aktivitas_langsung' => 24, 'aktivitas_tidak_langsung' => 24,
            'aktivitas_penunjang' => 20, 'lokasi' => 20, 'besaran_manfaat' => 18, 'jenis_bantuan' => 14,
        ];
        if ($includeProgram) {
            $fields += ['program' => 32, 'kegiatan' => 34, 'kode' => 18];
        }

        $lines = 1;
        foreach ($fields as $key => $width) {
            $len   = mb_strlen(trim((string) ($r[$key] ?? '')));
            $lines = max($lines, (int) ceil($len / max(8, $width - 2)));
        }

        return min(96.0, max(22.0, $lines * 11.0));
    }

    /**
     * Sheet ke-2: rekap ringkas per perangkat daerah, untuk pembacaan cepat.
     */
    private function buildRingkasanSheet(Spreadsheet $spreadsheet, array $ringkasan, array $grand, $tahun, string $semLabel, string $moneyFmt): void
    {
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('Ringkasan');
        $ws->setShowGridlines(false);

        $ws->mergeCells('A1:J1');
        $ws->setCellValue('A1', "RINGKASAN OPPKPKE PER PERANGKAT DAERAH — SEMESTER {$semLabel} TAHUN {$tahun}");
        $ws->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension(1)->setRowHeight(26);
        $ws->getRowDimension(2)->setRowHeight(8);

        $head = ['No', 'Strategi OPPKPKE', 'Perangkat Daerah', 'Jml Program', 'Jml Sub Kegiatan',
                 'Alokasi (Rp)', 'Realisasi Sem. 1 (Rp)', 'Realisasi Sem. 2 (Rp)', 'Realisasi Total (Rp)', '% Serapan'];
        foreach ($head as $i => $label) {
            $ws->setCellValue(chr(65 + $i) . '3', $label);
        }
        $ws->getStyle('A3:J3')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFB0C4DE']]],
        ]);
        $ws->getRowDimension(3)->setRowHeight(30);

        $row        = 4;
        $no         = 1;
        $prevStr    = null;
        $stratStart = $row;

        foreach ($ringkasan as $r) {
            if ($prevStr !== null && $prevStr !== $r['strategi']) {
                if ($row - 1 > $stratStart) {
                    $ws->mergeCells("B{$stratStart}:B" . ($row - 1));
                }
                $ws->getRowDimension($row)->setRowHeight(8); // margin antar strategi
                $row++;
                $stratStart = $row;
            }
            $prevStr = $r['strategi'];

            $persen = $r['alokasi'] > 0 ? $r['total'] / $r['alokasi'] : 0;
            $ws->setCellValue("A{$row}", $no);
            $ws->setCellValue("B{$row}", $r['strategi']);
            $ws->setCellValue("C{$row}", $r['dinas']);
            $ws->setCellValue("D{$row}", $r['program']);
            $ws->setCellValue("E{$row}", $r['sub']);
            $ws->setCellValue("F{$row}", $r['alokasi']);
            $ws->setCellValue("G{$row}", $r['sem1']);
            $ws->setCellValue("H{$row}", $r['sem2']);
            $ws->setCellValue("I{$row}", $r['total']);
            $ws->setCellValue("J{$row}", $persen);

            $ws->getStyle("A{$row}:J{$row}")->applyFromArray([
                'font'      => ['size' => 9],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $no % 2 === 0 ? 'FFF6F9FC' : 'FFFFFFFF']],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD5DDE6']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true, 'indent' => 1],
            ]);
            foreach (['F', 'G', 'H', 'I'] as $col) {
                $ws->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode($moneyFmt);
                $ws->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            foreach (['A', 'D', 'E'] as $col) {
                $ws->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $ws->getStyle("J{$row}")->getNumberFormat()->setFormatCode('0.0%');
            $ws->getStyle("J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $ws->getStyle("J{$row}")->getFont()->setBold(true)->getColor()->setARGB(
                $persen >= 0.85 ? 'FF1B7F4B' : ($persen >= 0.5 ? 'FFB7791F' : 'FFB3261E')
            );
            $ws->getRowDimension($row)->setRowHeight(26);
            $row++;
            $no++;
        }

        if ($row - 1 > $stratStart) {
            $ws->mergeCells("B{$stratStart}:B" . ($row - 1));
        }

        // Grand total
        $row++;
        $ws->mergeCells("A{$row}:C{$row}");
        $ws->setCellValue("A{$row}", 'GRAND TOTAL');
        $ws->setCellValue("D{$row}", array_sum(array_column($ringkasan, 'program')));
        $ws->setCellValue("E{$row}", $grand['sub']);
        $ws->setCellValue("F{$row}", $grand['alokasi']);
        $ws->setCellValue("G{$row}", $grand['sem1']);
        $ws->setCellValue("H{$row}", $grand['sem2']);
        $ws->setCellValue("I{$row}", $grand['total']);
        $ws->setCellValue("J{$row}", $grand['alokasi'] > 0 ? $grand['total'] / $grand['alokasi'] : 0);
        $ws->getStyle("A{$row}:J{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0F2D4A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF9DB2C8']]],
        ]);
        foreach (['F', 'G', 'H', 'I'] as $col) {
            $ws->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode($moneyFmt);
            $ws->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getStyle("J{$row}")->getNumberFormat()->setFormatCode('0.0%');
        $ws->getRowDimension($row)->setRowHeight(24);

        foreach (['A'=>5,'B'=>26,'C'=>34,'D'=>11,'E'=>13,'F'=>18,'G'=>18,'H'=>18,'I'=>18,'J'=>11] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
        $ws->freezePane('A4');
        $ws->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
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

            $rows[] = ['_type' => 'strategi_header', 'label' => $strat->nama, 'kode' => $strat->kode ?? ''];
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
            ->whereNull('lo.deleted_at')   // WAJIB: exclude baris yang sudah di-soft-delete (lihat catatan di dashboard()).
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
