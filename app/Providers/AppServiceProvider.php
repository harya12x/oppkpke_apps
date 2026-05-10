<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // OWASP A07 / MITRE T1110 (Brute Force):
        // 5 attempts per minute keyed by email + IP, hard cap of 15 per minute by IP alone.
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by(
                    strtolower($request->input('email', '')) . '|' . $request->ip()
                ),
                Limit::perMinute(15)->by($request->ip()),
            ];
        });
    }
}
