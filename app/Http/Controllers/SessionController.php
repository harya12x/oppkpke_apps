<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pemantauan sesi login aktif + logout paksa.
 *
 * Dipakai Tim IT (dan Master) untuk menendang keluar akun yang sedang login —
 * mis. beberapa dinas yang memakai akun yang sama / lupa logout. Mengandalkan
 * SESSION_DRIVER=database: baris di tabel `sessions` punya kolom user_id, jadi
 * "logout paksa" = hapus baris sesi milik user tsb → request berikutnya user itu
 * tidak lagi terautentikasi dan diarahkan ke halaman login.
 */
class SessionController extends Controller
{
    public function index(Request $request)
    {
        $lifetime = (int) config('session.lifetime', 120);      // menit
        $cutoff   = now()->subMinutes($lifetime)->getTimestamp();

        // Ambil sesi yang masih dalam masa aktif & terikat ke sebuah user.
        $rows = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $cutoff)
            ->get(['user_id', 'ip_address', 'last_activity']);

        $users = User::with('perangkatDaerah')
            ->whereIn('id', $rows->pluck('user_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $currentUserId = $request->user()->id;

        // Rangkum per user: satu user bisa punya beberapa sesi (beberapa perangkat).
        $sessions = $rows
            ->groupBy('user_id')
            ->map(function ($group, $userId) use ($users, $currentUserId) {
                $u = $users->get($userId);
                if (!$u) return null;   // user terhapus tapi sesi tertinggal

                $last = $group->max('last_activity');

                return [
                    'user_id'        => (int) $userId,
                    'name'           => $u->name,
                    'email'          => $u->email,
                    'role'           => $u->role,
                    'role_label'     => $u->role_label,
                    'perangkat'      => optional($u->perangkatDaerah)->nama,
                    'session_count'  => $group->count(),
                    'ip_address'     => optional($group->sortByDesc('last_activity')->first())->ip_address,
                    'last_activity'  => \Carbon\Carbon::createFromTimestamp($last)->diffForHumans(),
                    'last_ts'        => $last,
                    'is_self'        => (int) $userId === $currentUserId,
                ];
            })
            ->filter()
            ->sortByDesc('last_ts')
            ->values();

        return view('oppkpke.sessions', compact('sessions'));
    }

    public function forceLogout(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        // Tidak boleh menendang diri sendiri — cegah IT membuat dirinya ter-logout.
        if ((int) $request->user_id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat me-logout paksa akun Anda sendiri.',
            ], 422);
        }

        $target = User::findOrFail($request->user_id);

        $deleted = DB::table('sessions')->where('user_id', $target->id)->delete();

        AuditLog::record(
            'session.force_logout',
            "Logout paksa akun {$target->email} ({$deleted} sesi dihentikan)",
            $target,
            ['sessions_deleted' => $deleted]
        );

        return response()->json([
            'success' => true,
            'message' => "Akun <strong>{$target->name}</strong> berhasil di-logout paksa ({$deleted} sesi).",
        ]);
    }
}
