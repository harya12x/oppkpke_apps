<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

/**
 * Otorisasi edit/hapus pesan (SEC5).
 */
class MessagePolicy
{
    /** Jendela waktu edit pesan sendiri (menit). */
    private const EDIT_WINDOW_MINUTES = 15;

    /**
     * Edit: hanya pengirim, pesan teks biasa, belum dihapus, dan masih
     * dalam jendela waktu.
     */
    public function update(User $user, Message $message): bool
    {
        return $message->type === 'text'
            && ! $message->trashed()
            && $message->sender_id === $user->id
            && $message->created_at?->gt(now()->subMinutes(self::EDIT_WINDOW_MINUTES));
    }

    /**
     * Hapus: pengirim (pesan teks miliknya) atau Tim IT (moderasi).
     * Admin Master hanya memantau, jadi tidak boleh menghapus.
     */
    public function delete(User $user, Message $message): bool
    {
        if ($message->type !== 'text' || $message->trashed()) {
            return false;
        }

        return $message->sender_id === $user->id || $user->isItTeam();
    }
}
