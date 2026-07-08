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

        if ($user && $user->canSeeAnnouncements() && $this->shouldShowFor($user)) {
            $announcements = Announcement::live()
                ->orderByRaw("FIELD(type, 'critical','maintenance','warning','info')")
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();
        }

        $view->with('liveAnnouncements', $announcements);
    }

    /**
     * Operator Daerah: banner HANYA muncul di Beranda (dashboard) — tidak
     * mengganggu di setiap halaman. Role lain (mis. Master): semua halaman.
     */
    private function shouldShowFor($user): bool
    {
        if ($user->isDaerah()) {
            return request()->routeIs('oppkpke.dashboard');
        }

        return true;
    }
}
