<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class AuditLog extends Model
{
    public const UPDATED_AT = null;   // immutable: hanya created_at

    protected $fillable = [
        'user_id', 'actor_name', 'action', 'auditable_type', 'auditable_id',
        'description', 'properties', 'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Catat satu peristiwa audit. Aman dipanggil dari mana saja; gagal
     * mencatat audit tidak boleh menggagalkan aksi utama.
     *
     * @param  Model|null  $auditable  model terkait (mis. User, Announcement, Conversation)
     */
    public static function record(string $action, ?string $description = null, ?Model $auditable = null, array $properties = []): void
    {
        try {
            $actor   = auth()->user();
            $request = app(Request::class);

            static::create([
                'user_id'        => $actor?->id,
                'actor_name'     => $actor?->name,
                'action'         => $action,
                'auditable_type' => $auditable ? $auditable::class : null,
                'auditable_id'   => $auditable?->getKey(),
                'description'    => $description,
                'properties'     => $properties ?: null,
                'ip_address'     => $request?->ip(),
                'user_agent'     => substr((string) $request?->userAgent(), 0, 255) ?: null,
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'user.created'          => 'Buat Pengguna',
            'user.updated'          => 'Ubah Pengguna',
            'user.deleted'          => 'Hapus Pengguna',
            'user.toggled'          => 'Aktif/Nonaktif Pengguna',
            'user.password_reset'   => 'Reset Password',
            'announcement.created'  => 'Buat Pengumuman',
            'announcement.updated'  => 'Ubah Pengumuman',
            'announcement.toggled'  => 'Aktif/Nonaktif Pengumuman',
            'announcement.deleted'  => 'Hapus Pengumuman',
            'announcement.auto_expired' => 'Pengumuman Kedaluwarsa (otomatis)',
            'chat.status_changed'   => 'Ubah Status Chat',
            'laporan.created'       => 'Input Laporan',
            'laporan.updated'       => 'Ubah Laporan',
            'laporan.deleted'       => 'Hapus Laporan',
            'pic.completed'         => 'Lengkapi Identitas PIC',
            'pic.updated'           => 'Ubah Identitas PIC',
            'auth.login'            => 'Login',
            'auth.login_failed'     => 'Login Gagal',
            'auth.login_bot'        => 'Login Diblokir (Bot)',
            'auth.logout'           => 'Logout',
            default                 => $this->action,
        };
    }
}
