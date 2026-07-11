<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\PerangkatDaerah;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    // =========================================
    // INDEX — daftar semua user
    // =========================================

    public function index(Request $request)
    {
        $query = User::with('perangkatDaerah')->orderBy('role')->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('perangkat_daerah_id')) {
            $query->where('perangkat_daerah_id', $request->perangkat_daerah_id);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $users          = $query->paginate(15)->withQueryString();
        $perangkatDaerah = PerangkatDaerah::where('is_active', true)->orderBy('nama')->get();

        $summary = [
            'total'    => User::count(),
            'master'   => User::where('role', 'master')->count(),
            'daerah'   => User::where('role', 'daerah')->count(),
            'it_team'  => User::where('role', 'it_team')->count(),
            'active'   => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
        ];

        // Map each PD with its registered operator info (for picker UI)
        $operatorByPd = User::where('role', 'daerah')
            ->whereNotNull('perangkat_daerah_id')
            ->get(['id', 'name', 'is_active', 'perangkat_daerah_id'])
            ->keyBy('perangkat_daerah_id');

        $pdInfo = $perangkatDaerah->map(function ($pd) use ($operatorByPd) {
            $op = $operatorByPd->get($pd->id);
            return [
                'id'            => $pd->id,
                'kode'          => $pd->kode,
                'nama'          => $pd->nama,
                'singkatan'     => $pd->singkatan,
                'jenis'         => $pd->jenis,
                'jenis_label'   => $pd->jenis_label,
                'has_operator'  => $op !== null,
                'operator_id'   => $op?->id,
                'operator_name' => $op?->name,
                'operator_active'=> $op?->is_active,
            ];
        })->values();

        // Deteksi perangkat daerah (aktif) yang belum punya akun operator — dipakai
        // untuk badge indikator di tombol Generate Credential agar admin langsung
        // tahu ada PD baru yang butuh akun tanpa harus membuka modal dulu.
        $pdTanpaAkun = $pdInfo->where('has_operator', false)->count();

        return view('admin.users.index', compact('users', 'perangkatDaerah', 'summary', 'pdInfo', 'pdTanpaAkun'));
    }

    // =========================================
    // EXPORT PDF — rekap seluruh pengguna (print-optimized)
    // =========================================

    public function exportPdf(Request $request)
    {
        // PDF khusus Operator Daerah — Admin Master tidak ditampilkan.
        $query = User::with('perangkatDaerah')
            ->where('role', 'daerah')
            ->orderBy('name');

        // Terapkan filter yang sama dengan halaman index agar PDF konsisten
        $filters = [];

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
            $filters['Pencarian'] = $search;
        }

        if ($request->filled('perangkat_daerah_id')) {
            $query->where('perangkat_daerah_id', $request->perangkat_daerah_id);
            $pd = PerangkatDaerah::find($request->perangkat_daerah_id);
            $filters['Perangkat Daerah'] = $pd?->nama ?? '—';
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
            $filters['Status'] = $request->status === 'active' ? 'Aktif' : 'Nonaktif';
        }

        $users = $query->get();

        // Ringkasan dihitung dari hasil terfilter agar selaras dengan isi tabel
        $summary = [
            'total'    => $users->count(),
            'active'   => $users->where('is_active', true)->count(),
            'inactive' => $users->where('is_active', false)->count(),
        ];

        $pdf = Pdf::loadView('admin.users.pdf-download', [
            'users'      => $users,
            'summary'    => $summary,
            'filters'    => $filters,
            'generatedAt'=> now(),
            'generatedBy'=> auth()->user(),
        ])->setPaper('a4', 'landscape');

        $filename = 'rekap-operator-daerah-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    // =========================================
    // STORE — buat user baru
    // =========================================

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:100',
            'email'               => 'required|email|unique:users,email',
            'password'            => ['required', Password::min(8)->letters()->numbers()],
            'role'                => 'required|in:master,daerah,it_team',
            'perangkat_daerah_id' => 'nullable|exists:perangkat_daerah,id',
            'is_active'           => 'boolean',
        ], [
            'name.required'  => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique'   => 'Email sudah digunakan oleh akun lain.',
            'password.min'   => 'Password minimal 8 karakter.',
            'role.required'  => 'Role wajib dipilih.',
        ]);

        // Operator daerah wajib punya perangkat daerah
        if ($validated['role'] === 'daerah' && empty($validated['perangkat_daerah_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Operator Daerah wajib memilih Perangkat Daerah.',
                'errors'  => ['perangkat_daerah_id' => ['Perangkat Daerah wajib dipilih untuk role Operator Daerah.']],
            ], 422);
        }

        // Master & Tim IT tidak terikat perangkat daerah
        if (in_array($validated['role'], ['master', 'it_team'], true)) {
            $validated['perangkat_daerah_id'] = null;
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['password']  = Hash::make($validated['password']);

        $user = User::create($validated);

        AuditLog::record('user.created', "Membuat akun {$user->email} (role: {$user->role})", $user);

        return response()->json([
            'success' => true,
            'message' => "Akun <strong>{$user->name}</strong> berhasil dibuat.",
        ]);
    }

    // =========================================
    // SHOW — data user untuk edit (AJAX)
    // =========================================

    public function show(User $user)
    {
        $user->load('perangkatDaerah');

        return response()->json([
            'success' => true,
            'user'    => [
                'id'                  => $user->id,
                'name'                => $user->name,
                'email'               => $user->email,
                'role'                => $user->role,
                'perangkat_daerah_id' => $user->perangkat_daerah_id,
                'is_active'           => $user->is_active,
                'last_login_at'       => $user->last_login_at?->format('d/m/Y H:i'),
            ],
        ]);
    }

    // =========================================
    // UPDATE — edit data user
    // =========================================

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:100',
            'email'               => "required|email|unique:users,email,{$user->id}",
            'role'                => 'required|in:master,daerah,it_team',
            'perangkat_daerah_id' => 'nullable|exists:perangkat_daerah,id',
            'is_active'           => 'boolean',
        ], [
            'name.required'  => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique'   => 'Email sudah digunakan oleh akun lain.',
            'role.required'  => 'Role wajib dipilih.',
        ]);

        if ($validated['role'] === 'daerah' && empty($validated['perangkat_daerah_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Operator Daerah wajib memilih Perangkat Daerah.',
                'errors'  => ['perangkat_daerah_id' => ['Perangkat Daerah wajib dipilih untuk role Operator Daerah.']],
            ], 422);
        }

        // Jangan nonaktifkan diri sendiri
        if ($user->id === auth()->id() && !$request->boolean('is_active', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ], 422);
        }

        if (in_array($validated['role'], ['master', 'it_team'], true)) {
            $validated['perangkat_daerah_id'] = null;
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        $user->update($validated);

        AuditLog::record('user.updated', "Memperbarui akun {$user->email}", $user, ['changes' => array_keys($user->getChanges())]);

        return response()->json([
            'success' => true,
            'message' => "Data akun <strong>{$user->name}</strong> berhasil diperbarui.",
        ]);
    }

    // =========================================
    // TOGGLE ACTIVE — aktifkan / nonaktifkan
    // =========================================

    public function toggleActive(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ], 422);
        }

        $user->update(['is_active' => !$user->is_active]);

        $status  = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        AuditLog::record('user.toggled', "Akun {$user->email} {$status}", $user, ['is_active' => $user->is_active]);

        $message = "Akun <strong>{$user->name}</strong> berhasil {$status}.";

        return response()->json([
            'success'   => true,
            'message'   => $message,
            'is_active' => $user->is_active,
        ]);
    }

    // =========================================
    // RESET PASSWORD — reset oleh admin
    // =========================================

    public function resetPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'new_password' => ['required', Password::min(8)->letters()->numbers()],
        ], [
            'new_password.required' => 'Password baru wajib diisi.',
            'new_password.min'      => 'Password minimal 8 karakter.',
        ]);

        $user->update(['password' => Hash::make($validated['new_password'])]);

        AuditLog::record('user.password_reset', "Reset password akun {$user->email}", $user);

        return response()->json([
            'success' => true,
            'message' => "Password akun <strong>{$user->name}</strong> berhasil direset.",
        ]);
    }

    // =========================================
    // GENERATE CREDENTIALS PREVIEW — data PD tanpa operator
    // =========================================

    public function generateCredentialsPreview()
    {
        $perangkatDaerah = PerangkatDaerah::where('is_active', true)->orderBy('nama')->get();

        $existingEmails = User::where('role', 'daerah')
            ->pluck('email')
            ->map(fn($e) => strtolower($e))
            ->toArray();

        $operatorByPd = User::where('role', 'daerah')
            ->whereNotNull('perangkat_daerah_id')
            ->pluck('perangkat_daerah_id')
            ->toArray();

        $usedPrefixes = [];

        $items = $perangkatDaerah
            ->filter(fn($pd) => !in_array($pd->id, $operatorByPd))
            ->values()
            ->map(function ($pd) use ($existingEmails, &$usedPrefixes) {
                $base    = $this->generateEmailPrefix($pd->nama);
                $prefix  = $base;
                $counter = 2;
                while (
                    in_array($prefix . '@oppkpke.go.id', $existingEmails) ||
                    in_array($prefix, $usedPrefixes)
                ) {
                    $prefix = $base . $counter++;
                }
                $usedPrefixes[] = $prefix;

                return [
                    'perangkat_daerah_id' => $pd->id,
                    'nama'                => $pd->nama,
                    'singkatan'           => $pd->singkatan,
                    'suggested_prefix'    => $prefix,
                    'suggested_email'     => $prefix . '@oppkpke.go.id',
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'items'   => $items,
        ]);
    }

    // =========================================
    // GENERATE CREDENTIALS — buat user massal
    // =========================================

    public function generateCredentials(Request $request)
    {
        $request->validate([
            'items'                       => 'required|array|min:1',
            'items.*.perangkat_daerah_id' => 'required|exists:perangkat_daerah,id',
            'items.*.email_prefix'        => 'required|string|max:50|alpha_num',
        ]);

        $results  = [];
        $created  = 0;
        $skipped  = 0;

        foreach ($request->items as $item) {
            $pdId   = $item['perangkat_daerah_id'];
            $prefix = strtolower(trim($item['email_prefix']));
            $email  = $prefix . '@oppkpke.go.id';

            try {
                DB::transaction(function () use ($pdId, $email, &$created, &$skipped, &$results) {
                    // Kunci baris perangkat_daerah supaya dua request generateCredentials
                    // yang nyaris bersamaan untuk PD yang sama saling menunggu di sini —
                    // belum ada baris users untuk dikunci (itulah yang sedang dibuat),
                    // jadi baris induk inilah yang dikunci. TIDAK menambah unique
                    // constraint pada perangkat_daerah_id: UI memang sengaja mengizinkan
                    // operator kedua untuk satu PD (cuma tampil peringatan, tidak diblokir).
                    $pd = PerangkatDaerah::where('id', $pdId)->lockForUpdate()->firstOrFail();

                    $alreadyHasOp = User::where('role', 'daerah')
                        ->where('perangkat_daerah_id', $pdId)
                        ->exists();

                    if ($alreadyHasOp) {
                        $skipped++;
                        $results[] = ['pd_id' => $pdId, 'status' => 'skipped', 'reason' => 'Sudah ada operator'];
                        return;
                    }

                    if (User::where('email', $email)->exists()) {
                        $skipped++;
                        $results[] = ['pd_id' => $pdId, 'status' => 'skipped', 'reason' => "Email {$email} sudah digunakan"];
                        return;
                    }

                    User::create([
                        'name'                => $pd->nama,
                        'email'               => $email,
                        'password'            => Hash::make('password123'),
                        'role'                => 'daerah',
                        'perangkat_daerah_id' => $pdId,
                        'is_active'           => true,
                    ]);

                    $created++;
                    $results[] = ['pd_id' => $pdId, 'status' => 'created', 'email' => $email];
                });
            } catch (QueryException $e) {
                if ($e->getCode() === '23000') {
                    $skipped++;
                    $results[] = ['pd_id' => $pdId, 'status' => 'skipped', 'reason' => 'Email sudah digunakan (bentrok saat penyimpanan)'];
                    continue;
                }
                throw $e;
            }
        }

        $message = "Berhasil membuat <strong>{$created}</strong> akun baru";
        if ($skipped > 0) {
            $message .= ", <strong>{$skipped}</strong> dilewati.";
        } else {
            $message .= '.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'created' => $created,
            'skipped' => $skipped,
            'results' => $results,
        ]);
    }

    // =========================================
    // TAMBAH OPERATOR DAERAH — alur khusus, terpandu, aman
    // =========================================

    /** Data untuk modal: semua PD aktif + status operator + email saran (unik). */
    public function operatorPrepare()
    {
        $existingEmails = User::pluck('email')->map(fn ($e) => strtolower($e))->all();

        $operatorByPd = User::where('role', 'daerah')->whereNotNull('perangkat_daerah_id')
            ->get(['name', 'perangkat_daerah_id'])
            ->keyBy('perangkat_daerah_id');

        $used  = [];
        $items = PerangkatDaerah::where('is_active', true)->orderBy('nama')->get(['id', 'nama', 'singkatan'])
            ->map(function ($pd) use ($existingEmails, &$used, $operatorByPd) {
                $base = $this->generateEmailPrefix($pd->nama);
                $prefix = $base; $n = 2;
                while (in_array($prefix . '@oppkpke.go.id', $existingEmails, true) || in_array($prefix, $used, true)) {
                    $prefix = $base . $n++;
                }
                $used[] = $prefix;
                $op = $operatorByPd->get($pd->id);

                return [
                    'id'              => $pd->id,
                    'nama'            => $pd->nama,
                    'singkatan'       => $pd->singkatan,
                    'has_operator'    => $op !== null,
                    'operator_name'   => $op?->name,
                    'suggested_email' => $prefix . '@oppkpke.go.id',
                ];
            })->values();

        return response()->json(['success' => true, 'items' => $items]);
    }

    /** Buat satu akun operator daerah. Mengembalikan kredensial (tampil sekali). */
    public function storeOperator(Request $request)
    {
        $validated = $request->validate([
            'perangkat_daerah_id' => 'required|exists:perangkat_daerah,id',
            'name'                => 'required|string|min:3|max:120',
            'email'               => 'required|email|max:150|unique:users,email',
            'password'            => ['required', Password::min(10)->letters()->numbers()->mixedCase()],
            'allow_duplicate'     => 'sometimes|boolean',
        ], [
            'perangkat_daerah_id.required' => 'Perangkat daerah wajib dipilih.',
            'perangkat_daerah_id.exists'   => 'Perangkat daerah tidak valid.',
            'name.required'  => 'Nama operator wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique'   => 'Email sudah digunakan oleh akun lain.',
            'password.min'   => 'Password minimal 10 karakter (huruf besar-kecil + angka).',
        ]);

        $allowDuplicate = $request->boolean('allow_duplicate');

        try {
            // Kunci baris PD supaya dua pembuatan operator utk PD yang sama serialize di sini
            // (baris users belum ada untuk dikunci — jadi baris induk PD yang dikunci).
            $result = DB::transaction(function () use ($validated, $allowDuplicate) {
                $pd = PerangkatDaerah::where('id', $validated['perangkat_daerah_id'])->lockForUpdate()->firstOrFail();

                $existing = User::where('role', 'daerah')->where('perangkat_daerah_id', $pd->id)->first();
                if ($existing && !$allowDuplicate) {
                    return ['conflict' => $existing->name];   // belum ada tulisan → commit aman
                }

                $user = User::create([
                    'name'                => $validated['name'],
                    'email'               => strtolower($validated['email']),
                    'password'            => Hash::make($validated['password']),
                    'role'                => 'daerah',
                    'perangkat_daerah_id' => $pd->id,
                    'is_active'           => true,
                ]);

                return ['user' => $user];
            });
        } catch (QueryException $e) {
            // Backstop unique email (race antar submit) → pesan ramah, bukan 500 SQL.
            if ($e->getCode() === '23000') {
                return response()->json(['success' => false, 'message' => 'Email sudah digunakan (bentrok saat penyimpanan).'], 422);
            }
            throw $e;
        }

        if (isset($result['conflict'])) {
            return response()->json([
                'success'       => false,
                'needs_confirm' => true,
                'message'       => "Perangkat daerah ini sudah punya operator ({$result['conflict']}). Centang \"tetap buat operator kedua\" untuk melanjutkan.",
            ], 409);
        }

        $user = $result['user'];

        // PENTING: password TIDAK pernah dicatat ke audit — hanya email & role.
        AuditLog::record('user.created', "Membuat operator daerah {$user->email}", $user, ['via' => 'tambah_operator']);

        return response()->json([
            'success'     => true,
            'message'     => "Operator <strong>{$user->name}</strong> berhasil dibuat.",
            'credentials' => [
                'name'             => $user->name,
                'email'            => $user->email,
                'password'         => $validated['password'],   // plaintext, tampil sekali untuk diserahkan
                'perangkat_daerah' => $user->perangkatDaerah?->nama,
            ],
        ]);
    }

    // =========================================
    // Helper — generate email prefix dari nama PD
    // =========================================

    private function generateEmailPrefix(string $nama): string
    {
        $clean = strtolower(preg_replace('/[^a-zA-Z\s]/', ' ', $nama));
        $words = array_values(array_filter(
            preg_split('/\s+/', trim($clean)),
            fn($w) => strlen($w) > 0
        ));

        if (empty($words)) return 'user';

        $stopWords = ['dan', 'atau', 'untuk', 'serta', 'dengan', 'di', 'ke', 'dari'];
        $first     = $words[0];
        $rest      = array_values(array_filter(
            array_slice($words, 1),
            fn($w) => !in_array($w, $stopWords)
        ));

        if ($first === 'dinas') {
            if (empty($rest)) return 'dinas';
            if (count($rest) === 1) return 'din' . substr($rest[0], 0, 3);
            return 'din' . implode('', array_map(fn($w) => $w[0], $rest));
        }

        if ($first === 'badan') {
            return implode('', array_map(fn($w) => $w[0], $words));
        }

        if ($first === 'kecamatan') {
            if (empty($rest)) return 'kec';
            return 'kec' . implode('', array_map(fn($w) => $w[0], $rest));
        }

        // Default: initials semua kata
        return implode('', array_map(fn($w) => $w[0], $words));
    }

    // =========================================
    // DESTROY — hapus user
    // =========================================

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ], 422);
        }

        $name  = $user->name;
        $email = $user->email;
        AuditLog::record('user.deleted', "Menghapus akun {$email}", $user, ['name' => $name, 'email' => $email, 'role' => $user->role]);
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => "Akun <strong>{$name}</strong> berhasil dihapus.",
        ]);
    }
}
