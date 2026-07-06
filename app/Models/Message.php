<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'type',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'edited_at'       => 'datetime',
            'attachment_size' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // ── Lampiran ────────────────────────────────────────────────
    public function hasAttachment(): bool
    {
        return !empty($this->attachment_path);
    }

    public function isImageAttachment(): bool
    {
        return $this->hasAttachment() && str_starts_with((string) $this->attachment_mime, 'image/');
    }

    /**
     * Payload ringkas untuk JSON polling & broadcast (satu sumber kebenaran).
     * Menghormati soft-delete (menampilkan placeholder, bukan isi asli).
     */
    public function toChatArray(): array
    {
        $deleted = $this->trashed();

        return [
            'id'              => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id'       => $this->sender_id,
            'sender_name'     => $this->sender?->name ?? 'Sistem',
            'sender_role'     => $this->sender?->role,
            'type'            => $this->type,
            'deleted'         => $deleted,
            'edited'          => (bool) $this->edited_at && ! $deleted,
            'body'            => $deleted ? null : $this->body,
            'attachment'      => (! $deleted && $this->hasAttachment()) ? [
                'name'     => $this->attachment_name,
                'mime'     => $this->attachment_mime,
                'size'     => $this->attachment_size,
                'is_image' => $this->isImageAttachment(),
                'url'      => route('oppkpke.chat.attachment', ['conversation' => $this->conversation_id, 'message' => $this->id]),
            ] : null,
            'created_at'      => $this->created_at?->toIso8601String(),
            'created_label'   => $this->created_at?->timezone(config('app.timezone'))->format('d M Y H:i'),
        ];
    }
}
