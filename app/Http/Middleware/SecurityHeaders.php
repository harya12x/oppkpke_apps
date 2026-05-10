<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OWASP Top 10 + MITRE ATT&CK security response headers.
 *
 * A01 – Broken Access Control    : frame-ancestors 'none' (blocks clickjacking)
 * A02 – Cryptographic Failures   : HSTS, no-store cache on sensitive routes
 * A03 – Injection                : CSP restricts script/style sources
 * A05 – Security Misconfiguration: removes Server/X-Powered-By fingerprints
 * A07 – Auth Failures            : Cache-Control no-store on login page
 * MITRE T1036 (Masquerading)     : X-Content-Type-Options nosniff
 * MITRE T1040 (Network Sniffing) : HSTS forces HTTPS
 * MITRE T1185 (Browser Hijack)   : X-Frame-Options, CSP frame-ancestors
 * MITRE T1190 (Exploit Public)   : CSP, XSS-Protection, Permissions-Policy
 */
class SecurityHeaders
{
    private array $csp = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://code.jquery.com",
        "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
        "font-src 'self' data: https://cdnjs.cloudflare.com",
        "img-src 'self' data: blob:",
        "connect-src 'self'",
        "frame-src 'none'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Remove server-fingerprinting headers (A05, MITRE T1190)
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        // Clickjacking prevention (A01, MITRE T1185)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // MIME-sniffing prevention (A05, MITRE T1036)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Legacy XSS filter (defense-in-depth for older browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer leakage prevention (A02)
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict dangerous browser APIs (A05, MITRE T1190)
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');

        // Content Security Policy (A03, A05)
        $response->headers->set('Content-Security-Policy', implode('; ', $this->csp));

        // HSTS – force HTTPS on production (A02, MITRE T1040)
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        }

        // Prevent caching of auth & admin pages (A02, A07)
        if ($request->is('login') || $request->is('admin') || $request->is('admin/*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
