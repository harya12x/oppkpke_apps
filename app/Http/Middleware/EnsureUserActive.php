<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC2 — OWASP A07 / MITRE T1078:
 * Memaksa pengecekan status aktif pada SETIAP request. Jika admin
 * menonaktifkan akun saat user sedang login, sesi langsung diputus pada
 * request berikutnya (tanpa menunggu user logout).
 */
class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && !$user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401, 'Akun Anda telah dinonaktifkan.');
            }

            return redirect()->route('login')
                ->withErrors(['email' => 'Akun Anda telah dinonaktifkan. Hubungi administrator.']);
        }

        return $next($request);
    }
}
