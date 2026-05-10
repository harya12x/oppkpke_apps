<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'perangkat_daerah_id',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ══════════════════════════════════════════════════════════════

    public function perangkatDaerah(): BelongsTo
    {
        return $this->belongsTo(PerangkatDaerah::class, 'perangkat_daerah_id');
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════

    public function isMaster(): bool
    {
        return $this->role === 'master';
    }

    public function isDaerah(): bool
    {
        return $this->role === 'daerah';
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    // ══════════════════════════════════════════════════════════════
    // ACCESSORS
    // ══════════════════════════════════════════════════════════════

    public function getRoleLabelAttribute(): string
    {
        return match($this->role) {
            'master' => 'Admin Master',
            'daerah' => 'Operator Daerah',
            default  => ucfirst($this->role),
        };
    }

    public function getInitialsAttribute(): string
    {
        $words = explode(' ', trim($this->name));
        $init  = strtoupper($words[0][0] ?? 'U');
        if (count($words) > 1) {
            $init .= strtoupper($words[1][0]);
        }
        return $init;
    }

    // ══════════════════════════════════════════════════════════════
    // SCOPES
    // ══════════════════════════════════════════════════════════════

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}
