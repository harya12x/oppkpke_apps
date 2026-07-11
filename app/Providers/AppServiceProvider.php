<?php

namespace App\Providers;

use App\View\Composers\AnnouncementComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Aplikasi berjalan di belakang nginx yang terminasi SSL, tapi nginx tidak
        // meneruskan info skema ke php-fpm — akibatnya Laravel mengira request HTTP
        // dan route()/asset() menghasilkan URL http://. Form action http:// dari
        // halaman https:// → browser POST ke http → nginx 301 ke https → 301 mengubah
        // POST jadi GET → 405 di route POST-only. Paksa https di production agar semua
        // URL yang di-generate konsisten https dan POST langsung sampai tanpa redirect.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Banner pengumuman/maintenance pada layout utama (semua role kecuali Tim IT).
        View::composer('layouts.oppkpke', AnnouncementComposer::class);

        // @menuon('role','key') ... @endmenuon — tampilkan blok menu hanya bila
        // menu tsb aktif (dikelola Tim IT via Kelola Menu). Default: aktif.
        Blade::if('menuon', function (string $role, string $key) {
            return app(\App\Services\MenuManager::class)->isEnabled($role, $key);
        });

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
