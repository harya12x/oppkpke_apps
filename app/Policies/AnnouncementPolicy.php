<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

/**
 * SEC5 — otorisasi pengelolaan pengumuman. Dikelola Admin Master & Tim IT.
 * (Visibilitas banner ke pengguna diatur terpisah di AnnouncementComposer.)
 */
class AnnouncementPolicy
{
    public function manage(User $user): bool
    {
        return $user->isMaster() || $user->isItTeam();
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->manage($user);
    }
}
