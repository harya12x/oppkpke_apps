<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'body',
        'type',
        'is_active',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ══════════════════════════════════════════════════════════════
    // SCOPES
    // ══════════════════════════════════════════════════════════════

    /**
     * Pengumuman yang sedang tayang: aktif dan berada di dalam jendela waktu
     * (starts_at / ends_at boleh null = tak dibatasi).
     */
    public function scopeLive(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    // ══════════════════════════════════════════════════════════════
    // ACCESSORS
    // ══════════════════════════════════════════════════════════════

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'info'        => 'Informasi',
            'warning'     => 'Peringatan',
            'maintenance' => 'Pemeliharaan',
            'critical'    => 'Kritis',
            default       => ucfirst($this->type),
        };
    }
}
