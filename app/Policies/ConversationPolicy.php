<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/**
 * SEC5 — otorisasi terpusat percakapan (auto-discovered oleh Laravel).
 * - Tim IT: akses semua (agent).
 * - Operator Daerah: hanya miliknya, boleh membalas & memulai.
 * - Admin Master: pantau (hanya-baca) — TIDAK boleh membalas / ubah status (H6).
 */
class ConversationPolicy
{
    /** Melihat daftar inbox. */
    public function viewAny(User $user): bool
    {
        return $user->isItTeam() || $user->isDaerah() || $user->isMaster();
    }

    /** Membuka satu percakapan. */
    public function view(User $user, Conversation $conversation): bool
    {
        // Master boleh memantau semua percakapan (read-only).
        return $user->isMaster() || $conversation->isAccessibleBy($user);
    }

    /** Memulai percakapan baru — hanya Operator Daerah. */
    public function create(User $user): bool
    {
        return $user->isDaerah();
    }

    /** Membalas pesan — partisipan sah (Tim IT atau pemilik). Master tidak. */
    public function reply(User $user, Conversation $conversation): bool
    {
        return $conversation->isAccessibleBy($user);
    }

    /** Mengubah status — hanya Tim IT. */
    public function updateStatus(User $user, Conversation $conversation): bool
    {
        return $user->isItTeam();
    }
}
