<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
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
        return view('oppkpke.pic-identity');
    }

    public function picSave(Request $request)
    {
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|min:3|max:120',
            'no_ktp'       => 'required|digits:16',
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
