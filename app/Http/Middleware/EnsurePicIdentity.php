<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memastikan Operator Daerah (PIC penginput) sudah melengkapi identitas
 * (nama lengkap + no KTP) sebelum mengakses fitur input laporan.
 * Role selain daerah dilewati.
 */
class EnsurePicIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isDaerah() && !$user->hasPicIdentity()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success'  => false,
                    'message'  => 'Lengkapi identitas PIC (nama lengkap & no KTP) terlebih dahulu.',
                    'redirect' => route('oppkpke.pic.form'),
                ], 422);
            }

            return redirect()->route('oppkpke.pic.form')
                ->with('warning', 'Lengkapi identitas PIC (nama lengkap & no KTP) sebelum menginput laporan.');
        }

        return $next($request);
    }
}
