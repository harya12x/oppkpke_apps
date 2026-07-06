<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Menonaktifkan pengumuman yang sudah melewati ends_at (H3).
 * Banner sudah tersembunyi via scope live(), tapi command ini menjaga
 * kolom is_active tetap akurat di panel manajemen + mencatat audit.
 */
class DeactivateExpiredAnnouncements extends Command
{
    protected $signature = 'announcements:deactivate-expired';

    protected $description = 'Nonaktifkan pengumuman yang telah melewati waktu berakhir';

    public function handle(): int
    {
        $expired = Announcement::where('is_active', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->get();

        foreach ($expired as $ann) {
            $ann->update(['is_active' => false]);
            AuditLog::record('announcement.auto_expired', "Pengumuman \"{$ann->title}\" dinonaktifkan otomatis (kedaluwarsa)", $ann);
        }

        $this->info("Menonaktifkan {$expired->count()} pengumuman kedaluwarsa.");

        return self::SUCCESS;
    }
}
