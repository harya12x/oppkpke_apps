<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// H3: nonaktifkan pengumuman kedaluwarsa tiap 10 menit.
// (Butuh cron `php artisan schedule:run` tiap menit di server — lihat deploy notes.)
Schedule::command('announcements:deactivate-expired')->everyTenMinutes();

// SEC4: pangkas audit log lama tiap hari (default simpan 180 hari).
Schedule::command('audit:prune')->dailyAt('02:00');

// Bersihkan lampiran chat yatim (pesan sudah terhapus permanen) — opsional hygiene.
Schedule::command('queue:prune-failed')->weekly();
