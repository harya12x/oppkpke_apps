<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->intended(route('oppkpke.index'));
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email|max:150',
            'password' => 'required|max:200',
        ], [
            'email.required'    => 'Email wajib diisi.',
            'email.email'       => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        // OWASP A07 / MITRE T1110: brute-force throttle (5/min per email+IP)
        $throttleKey = Str::lower($request->input('email', '')) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
                ]);
        }

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            // OWASP A07 / MITRE T1078: block deactivated accounts
            if (!$user->is_active) {
                Auth::logout();
                RateLimiter::hit($throttleKey, 300);
                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => 'Akun Anda telah dinonaktifkan. Hubungi administrator.']);
            }

            RateLimiter::clear($throttleKey);

            $user->update(['last_login_at' => now()]);
            \App\Models\AuditLog::record('auth.login', "Login berhasil ({$user->email})", $user);
            $request->session()->regenerate();   // OWASP A07: session fixation prevention

            $redirect = match (true) {
                $user->isMaster()  => route('oppkpke.matrix'),
                $user->isItTeam()  => route('oppkpke.chat.index'),
                default            => route('oppkpke.dashboard'),
            };

            return redirect()->intended($redirect);
        }

        // Increment throttle counter on failed attempt
        RateLimiter::hit($throttleKey, 60);

        // SEC4: catat percobaan login gagal (deteksi brute-force).
        \App\Models\AuditLog::record(
            'auth.login_failed',
            'Percobaan login gagal untuk ' . $request->input('email'),
            null,
            ['email' => $request->input('email')],
        );

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Email atau password salah. Silakan coba lagi.']);
    }

    public function logout(Request $request)
    {
        \App\Models\AuditLog::record('auth.logout', 'Logout', Auth::user());
        Auth::logout();
        $request->session()->invalidate();        // OWASP A07: regenerate CSRF token after logout
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda berhasil logout.');
    }
}
