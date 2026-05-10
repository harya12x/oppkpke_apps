<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function changePasswordForm()
    {
        return view('oppkpke.ganti-password');
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
