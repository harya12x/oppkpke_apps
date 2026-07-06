<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\ConversationRead;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewChatMessageNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Lapisan layanan chat — memisahkan logika bisnis dari controller (decoupled),
 * mengikuti semangat producer/consumer di CHATKAFKA.md namun berjalan di atas
 * stack yang tersedia (MySQL + broadcast opsional).
 *
 * Alur "kirim pesan": tulis ke DB (transaksional) → perbarui metadata
 * percakapan → siarkan event. Bila DB gagal, transaksi di-rollback dan
 * pemanggil menerima exception (analog dengan tidak melakukan commit di doc).
 */
class ChatService
{
    /**
     * Buat percakapan baru dari Operator Daerah beserta pesan pertamanya.
     */
    public function startConversation(User $initiator, string $body, ?string $subject = null, string $priority = 'normal', array $attachment = []): Conversation
    {
        return DB::transaction(function () use ($initiator, $body, $subject, $priority, $attachment) {
            $conversation = Conversation::create([
                'initiator_id'        => $initiator->id,
                'perangkat_daerah_id' => $initiator->perangkat_daerah_id,
                'subject'             => $subject ?: 'Percakapan Support',
                'status'              => 'open',
                'priority'            => in_array($priority, ['low', 'normal', 'high', 'urgent'], true) ? $priority : 'normal',
            ]);

            $this->postMessage($conversation, $initiator, $body, 'text', $attachment);

            return $conversation->refresh();
        });
    }

    /**
     * Simpan satu pesan, perbarui last_message_at, tandai terbaca untuk
     * pengirim, lalu siarkan + notifikasi. Mengembalikan Message tersimpan.
     *
     * @param array{path?:string,name?:string,mime?:string,size?:int} $attachment
     */
    public function postMessage(Conversation $conversation, ?User $sender, string $body, string $type = 'text', array $attachment = []): Message
    {
        $message = DB::transaction(function () use ($conversation, $sender, $body, $type, $attachment) {
            $message = $conversation->messages()->create([
                'sender_id'       => $sender?->id,
                'type'            => $type,
                'body'            => $body,
                'attachment_path' => $attachment['path'] ?? null,
                'attachment_name' => $attachment['name'] ?? null,
                'attachment_mime' => $attachment['mime'] ?? null,
                'attachment_size' => $attachment['size'] ?? null,
            ]);

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();

            // Pengirim otomatis dianggap sudah membaca pesannya sendiri.
            if ($sender) {
                $this->markRead($conversation, $sender);
            }

            return $message;
        });

        // Broadcast di luar transaksi agar tidak menahan lock DB.
        // Gagal broadcast tidak boleh membatalkan pesan yang sudah tersimpan.
        try {
            broadcast(new MessageSent($message->load('sender')))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }

        // Notifikasi email (ter-queue) — hanya untuk pesan manusia, bukan sistem.
        if ($type !== 'system' && $sender) {
            $this->notifyCounterpart($conversation, $sender, $message);
        }

        return $message;
    }

    /**
     * Kirim notifikasi ke lawan bicara. Throttle per (percakapan, penerima)
     * maksimal 1 email / 15 menit agar tidak membanjiri saat pesan beruntun.
     */
    protected function notifyCounterpart(Conversation $conversation, User $sender, Message $message): void
    {
        try {
            $recipients = $this->recipientsFor($conversation, $sender);
            if ($recipients->isEmpty()) {
                return;
            }

            $preview = Str::limit($message->body ?: ($message->hasAttachment() ? '📎 ' . $message->attachment_name : 'Pesan baru'), 120);

            $recipients = $recipients->filter(function (User $u) use ($conversation) {
                $key = "chatnotif:{$conversation->id}:{$u->id}";
                // add() bersifat atomik: true hanya bila key belum ada (belum dinotifikasi).
                return Cache::add($key, 1, now()->addMinutes(15));
            });

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new NewChatMessageNotification($conversation, $sender->name, $preview));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Penerima notifikasi: bila pengirim Operator Daerah → Tim IT (penangan
     * bila sudah di-assign, jika belum semua Tim IT aktif). Bila pengirim
     * Tim IT → Operator Daerah pemilik (bila masih aktif).
     *
     * @return \Illuminate\Support\Collection<int,User>
     */
    protected function recipientsFor(Conversation $conversation, User $sender): \Illuminate\Support\Collection
    {
        if ($sender->isDaerah()) {
            if ($conversation->assigned_to) {
                return User::where('id', $conversation->assigned_to)->where('is_active', true)->get();
            }
            return User::where('role', 'it_team')->where('is_active', true)->get();
        }

        if ($sender->isItTeam()) {
            return User::where('id', $conversation->initiator_id)
                ->where('is_active', true)
                ->whereNotNull('email')
                ->get();
        }

        return collect();
    }

    /**
     * Catat waktu baca user untuk percakapan (upsert).
     */
    public function markRead(Conversation $conversation, User $user): void
    {
        ConversationRead::updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            ['last_read_at' => now()],
        );
    }

    /**
     * Ubah status percakapan + catat pesan sistem sebagai jejak audit.
     */
    public function changeStatus(Conversation $conversation, User $actor, string $status): void
    {
        if (!in_array($status, ['open', 'pending', 'resolved', 'closed'], true)) {
            return;
        }

        $from = $conversation->status;
        $conversation->update(['status' => $status]);

        $label = $conversation->status_label;
        $this->postMessage($conversation, $actor, "Status percakapan diubah menjadi \"{$label}\" oleh {$actor->name}.", 'system');

        // SEC4: audit perubahan status.
        \App\Models\AuditLog::record(
            'chat.status_changed',
            "Status percakapan #{$conversation->id} diubah {$from} → {$status}",
            $conversation,
            ['from' => $from, 'to' => $status],
        );
    }

    /**
     * Jumlah percakapan yang punya pesan belum terbaca oleh $user.
     * - Tim IT: seluruh percakapan.
     * - Operator Daerah: percakapan miliknya.
     * Unread = last_message_at > last_read_at (atau belum pernah dibaca).
     */
    public function unreadConversationCount(User $user): int
    {
        $query = Conversation::query()->whereNotNull('last_message_at');

        if ($user->isDaerah()) {
            $query->where('initiator_id', $user->id);
        } elseif (!$user->isItTeam()) {
            return 0;
        }

        $query->leftJoin('conversation_reads', function ($join) use ($user) {
            $join->on('conversation_reads.conversation_id', '=', 'conversations.id')
                ->where('conversation_reads.user_id', '=', $user->id);
        })->where(function ($q) {
            $q->whereNull('conversation_reads.last_read_at')
                ->orWhereColumn('conversations.last_message_at', '>', 'conversation_reads.last_read_at');
        });

        return $query->distinct('conversations.id')->count('conversations.id');
    }

    /**
     * Set id percakapan yang punya unread bagi $user (untuk badge per-baris di inbox).
     *
     * @return array<int,string> daftar conversation_id
     */
    public function unreadConversationIds(User $user): array
    {
        $query = Conversation::query()
            ->select('conversations.id')
            ->whereNotNull('last_message_at');

        if ($user->isDaerah()) {
            $query->where('initiator_id', $user->id);
        } elseif (!$user->isItTeam()) {
            return [];
        }

        return $query->leftJoin('conversation_reads', function ($join) use ($user) {
            $join->on('conversation_reads.conversation_id', '=', 'conversations.id')
                ->where('conversation_reads.user_id', '=', $user->id);
        })->where(function ($q) {
            $q->whereNull('conversation_reads.last_read_at')
                ->orWhereColumn('conversations.last_message_at', '>', 'conversation_reads.last_read_at');
        })->pluck('conversations.id')->all();
    }
}
