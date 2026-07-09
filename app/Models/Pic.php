<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PIC tambahan (penanggung jawab data) yang diundang oleh Operator Daerah.
 * Hanya catatan identitas (nama + NIK) — TIDAK memiliki akun login.
 */
class Pic extends Model
{
    protected $table = 'pics';

    protected $fillable = [
        'perangkat_daerah_id',
        'added_by',
        'nama_lengkap',
        'no_ktp',
    ];

    public function perangkatDaerah(): BelongsTo
    {
        return $this->belongsTo(PerangkatDaerah::class, 'perangkat_daerah_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /** NIK tersamar untuk tampilan (4 depan + 4 belakang). */
    public function getKtpMaskedAttribute(): string
    {
        $k = (string) $this->no_ktp;
        return strlen($k) === 16 ? substr($k, 0, 4) . '********' . substr($k, -4) : $k;
    }
}
