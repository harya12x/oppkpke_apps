<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi email pesan chat baru. ShouldQueue → dikirim asynchronous oleh
 * queue worker (Supervisor), tidak memblokir request pengiriman pesan.
 */
class NewChatMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Conversation $conversation,
        public string $senderName,
        public string $preview,
    ) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->conversation->subject ?: 'Percakapan Support';
        $url     = route('oppkpke.chat.show', $this->conversation);

        return (new MailMessage)
            ->subject("[OPPKPKE] Pesan baru: {$subject}")
            ->greeting("Halo {$notifiable->name},")
            ->line("Ada pesan baru dari {$this->senderName} pada percakapan \"{$subject}\".")
            ->line('"' . $this->preview . '"')
            ->action('Buka Percakapan', $url)
            ->line('Anda menerima email ini karena terlibat dalam percakapan support OPPKPKE.');
    }

    /**
     * Antre notifikasi dengan sedikit jeda agar beberapa pesan beruntun
     * tidak menghasilkan email bertubi (dikombinasikan dengan throttle cache
     * di ChatService).
     */
    public function withDelay(object $notifiable): array
    {
        return ['mail' => now()->addSeconds(10)];
    }
}
