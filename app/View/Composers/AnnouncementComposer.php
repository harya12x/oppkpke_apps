<?php

namespace App\View\Composers;

use App\Models\Announcement;
use Illuminate\View\View;

/**
 * Menyuntikkan pengumuman aktif ke banner layout.
 * Aturan visibilitas: SEMUA role KECUALI Tim IT.
 */
class AnnouncementComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        $announcements = collect();

        if ($user && $user->canSeeAnnouncements()) {
            $announcements = Announcement::live()
                ->orderByRaw("FIELD(type, 'critical','maintenance','warning','info')")
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();
        }

        $view->with('liveAnnouncements', $announcements);
    }
}
