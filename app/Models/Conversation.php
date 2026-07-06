<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'initiator_id',
        'perangkat_daerah_id',
        'assigned_to',
        'subject',
        'status',
        'priority',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ══════════════════════════════════════════════════════════════

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function perangkatDaerah(): BelongsTo
    {
        return $this->belongsTo(PerangkatDaerah::class, 'perangkat_daerah_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ConversationRead::class);
    }

    // ══════════════════════════════════════════════════════════════
    // AUTHORIZATION
    // ══════════════════════════════════════════════════════════════

    /**
     * Apakah $user boleh mengakses percakapan ini?
     * - Tim IT: boleh semua percakapan (peran support/agent).
     * - Operator Daerah: hanya percakapan miliknya.
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($user->isItTeam()) {
            return true;
        }

        // Cast eksplisit: kolom FK bisa terbaca sebagai string tergantung driver PDO,
        // jadi hindari perbandingan tipe-ketat yang rapuh.
        return $user->isDaerah() && (int) $this->initiator_id === (int) $user->id;
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS / ACCESSORS
    // ══════════════════════════════════════════════════════════════

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open'     => 'Terbuka',
            'pending'  => 'Menunggu',
            'resolved' => 'Selesai',
            'closed'   => 'Ditutup',
            default    => ucfirst($this->status),
        };
    }

    public function getIsClosedAttribute(): bool
    {
        return in_array($this->status, ['resolved', 'closed'], true);
    }
}
