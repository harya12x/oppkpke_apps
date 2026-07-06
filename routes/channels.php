<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Otorisasi channel privat chat. Hanya partisipan sah percakapan yang boleh
| berlangganan channel-nya (Operator Daerah pemilik atau anggota Tim IT).
| Dipakai bila server produksi mengaktifkan Reverb/Pusher; saat memakai
| polling, definisi ini tetap aman/idle.
*/

Broadcast::channel('chat.{conversationId}', function ($user, string $conversationId) {
    $conversation = Conversation::find($conversationId);

    return $conversation !== null && $conversation->isAccessibleBy($user);
});
