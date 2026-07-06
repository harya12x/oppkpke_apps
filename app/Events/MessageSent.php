<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disiarkan tepat setelah pesan tersimpan (dipanggil dari ChatService — analog
 * dengan consumer yang menembakkan broadcast di CHATKAFKA.md).
 *
 * ShouldBroadcastNow: dikirim seketika tanpa antre di queue Laravel. Aman di
 * lingkungan tanpa WebSocket — bila BROADCAST_CONNECTION=null, ini menjadi
 * no-op dan pengiriman real-time ditangani oleh polling. Bila server produksi
 * menjalankan Reverb/Pusher, pesan otomatis ter-push instan tanpa ubah kode.
 */
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): PrivateChannel
    {
        // Kunci channel = conversation_id (sejalan dgn partition key di doc).
        return new PrivateChannel('chat.' . $this->message->conversation_id);
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return $this->message->toChatArray();
    }
}
