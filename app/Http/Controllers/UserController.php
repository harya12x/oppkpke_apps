<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PerangkatDaerah;
use Illuminate\Http\Request;
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

        return view('admin.users.index', compact('users', 'perangkatDaerah', 'summary', 'pdInfo'));
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
            'role'                => 'required|in:master,daerah',
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

        // Master tidak perlu perangkat daerah
        if ($validated['role'] === 'master') {
            $validated['perangkat_daerah_id'] = null;
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['password']  = Hash::make($validated['password']);

        $user = User::create($validated);

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
            'role'                => 'required|in:master,daerah',
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

        if ($validated['role'] === 'master') {
            $validated['perangkat_daerah_id'] = null;
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        $user->update($validated);

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

            // Skip jika PD sudah punya operator
            $alreadyHasOp = User::where('role', 'daerah')
                ->where('perangkat_daerah_id', $pdId)
                ->exists();

            if ($alreadyHasOp) {
                $skipped++;
                $results[] = ['pd_id' => $pdId, 'status' => 'skipped', 'reason' => 'Sudah ada operator'];
                continue;
            }

            // Skip jika email sudah digunakan
            if (User::where('email', $email)->exists()) {
                $skipped++;
                $results[] = ['pd_id' => $pdId, 'status' => 'skipped', 'reason' => "Email {$email} sudah digunakan"];
                continue;
            }

            $pd = PerangkatDaerah::find($pdId);

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

        $name = $user->name;
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => "Akun <strong>{$name}</strong> berhasil dihapus.",
        ]);
    }
}
