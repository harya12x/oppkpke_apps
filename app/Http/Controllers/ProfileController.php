<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Pic;
use App\Models\User;
use App\Rules\ValidNik;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function changePasswordForm()
    {
        return view('oppkpke.ganti-password');
    }

    // ── Identitas PIC (nama lengkap + no KTP) ──────────────────────

    public function picForm()
    {
        $user = auth()->user();

        // Daftar PIC tambahan pada perangkat daerah operator (bila ada).
        $pics = ($user->isDaerah() && $user->perangkat_daerah_id)
            ? Pic::where('perangkat_daerah_id', $user->perangkat_daerah_id)->orderBy('nama_lengkap')->get()
            : collect();

        return view('oppkpke.pic-identity', compact('pics'));
    }

    public function picSave(Request $request)
    {
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|min:3|max:120',
            'no_ktp'       => ['required', 'digits:16', new ValidNik],
        ], [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'nama_lengkap.min'      => 'Nama lengkap minimal 3 karakter.',
            'no_ktp.required'       => 'Nomor KTP (NIK) wajib diisi.',
            'no_ktp.digits'         => 'Nomor KTP (NIK) harus tepat 16 digit angka.',
        ]);

        $user  = auth()->user();
        $first = !$user->hasPicIdentity();

        $user->update([
            'nama_lengkap'     => $validated['nama_lengkap'],
            'no_ktp'           => $validated['no_ktp'],
            'pic_completed_at' => $user->pic_completed_at ?? now(),
        ]);

        AuditLog::record(
            $first ? 'pic.completed' : 'pic.updated',
            "Identitas PIC {$user->email}: {$validated['nama_lengkap']} (NIK " . $this->maskKtp($validated['no_ktp']) . ')',
            $user,
        );

        return redirect()
            ->route('oppkpke.laporan.index')
            ->with('success', 'Identitas PIC berhasil disimpan. Anda kini dapat menginput laporan.');
    }

    // ── PIC tambahan (undang PIC lain — hanya catatan, tanpa akun login) ──

    public function picInvite(Request $request)
    {
        $user = auth()->user();

        if (!$user->isDaerah() || !$user->perangkat_daerah_id) {
            return response()->json(['success' => false, 'message' => 'Hanya Operator Daerah yang dapat menambah PIC.'], 403);
        }

        $validated = $request->validate([
            'nama_lengkap' => 'required|string|min:3|max:120',
            'no_ktp'       => ['required', 'digits:16', new ValidNik],
        ], [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'nama_lengkap.min'      => 'Nama lengkap minimal 3 karakter.',
            'no_ktp.required'       => 'Nomor KTP (NIK) wajib diisi.',
            'no_ktp.digits'         => 'Nomor KTP (NIK) harus tepat 16 digit angka.',
        ]);

        // NIK tidak boleh sudah terdaftar (sebagai PIC lain maupun identitas operator).
        $duplicate = Pic::where('no_ktp', $validated['no_ktp'])->exists()
            || User::where('no_ktp', $validated['no_ktp'])->exists();
        if ($duplicate) {
            return response()->json(['success' => false, 'message' => 'NIK ini sudah terdaftar sebagai PIC atau pengguna.'], 422);
        }

        try {
            $pic = Pic::create([
                'perangkat_daerah_id' => $user->perangkat_daerah_id,
                'added_by'            => $user->id,
                'nama_lengkap'        => $validated['nama_lengkap'],
                'no_ktp'              => $validated['no_ktp'],
            ]);
        } catch (QueryException $e) {
            // Backstop unique constraint (race antar submit).
            if ($e->getCode() === '23000') {
                return response()->json(['success' => false, 'message' => 'NIK ini sudah terdaftar sebagai PIC.'], 422);
            }
            throw $e;
        }

        AuditLog::record(
            'pic.invited',
            "Tambah PIC {$pic->nama_lengkap} (NIK " . $this->maskKtp($pic->no_ktp) . ')',
            $pic,
        );

        return response()->json([
            'success' => true,
            'message' => 'PIC berhasil ditambahkan.',
            'pic'     => [
                'id'           => $pic->id,
                'nama_lengkap' => $pic->nama_lengkap,
                'ktp_masked'   => $pic->ktp_masked,
            ],
        ]);
    }

    public function picInviteDestroy(Pic $pic)
    {
        $user = auth()->user();

        if (!$user->isDaerah() || (int) $pic->perangkat_daerah_id !== (int) $user->perangkat_daerah_id) {
            return response()->json(['success' => false, 'message' => 'Anda tidak berwenang menghapus PIC ini.'], 403);
        }

        $nama = $pic->nama_lengkap;

        AuditLog::record(
            'pic.removed',
            "Hapus PIC {$nama} (NIK " . $this->maskKtp($pic->no_ktp) . ')',
            $pic,
        );

        $pic->delete();

        return response()->json(['success' => true, 'message' => "PIC {$nama} berhasil dihapus."]);
    }

    /** Sensor NIK di deskripsi audit (tampilkan 4 digit awal & akhir saja). */
    private function maskKtp(string $ktp): string
    {
        return substr($ktp, 0, 4) . '********' . substr($ktp, -4);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password'     => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'new_password.required'     => 'Password baru wajib diisi.',
            'new_password.confirmed'    => 'Konfirmasi password tidak cocok.',
            'new_password.min'          => 'Password minimal 8 karakter.',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini tidak sesuai.'])->withInput();
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return back()->with('success', 'Password berhasil diperbarui.');
    }
}
