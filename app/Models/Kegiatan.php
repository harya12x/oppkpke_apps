<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Services\HierarkiSampahService;

class Kegiatan extends Model
{
    protected $table = 'kegiatan';

    protected static function booted(): void
    {
        // Kegiatan aktif di bawah program yang ada di Data Terhapus → program aktif kembali.
        static::saving(function (Kegiatan $kegiatan) {
            if ($kegiatan->is_active && $kegiatan->getAttribute('hapus_batch_id')) {
                $kegiatan->hapus_batch_id = null;
            }
        });
        static::saved(function (Kegiatan $kegiatan) {
            if ($kegiatan->is_active && $kegiatan->program_id) {
                HierarkiSampahService::aktifkanProgramBilaTerhapus((int) $kegiatan->program_id);
            }
        });
    }

    protected $fillable = [
        'program_id',
        'kode',
        'nama_kegiatan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function subKegiatan(): HasMany
    {
        return $this->hasMany(SubKegiatan::class, 'kegiatan_id');
    }

    public function laporan(): HasManyThrough
    {
        return $this->hasManyThrough(
            LaporanOppkpke::class,
            SubKegiatan::class,
            'kegiatan_id',
            'sub_kegiatan_id'
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}