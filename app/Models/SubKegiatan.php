<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SubKegiatan extends Model
{
    protected $table = 'sub_kegiatan';
    
    protected $fillable = [
        'kegiatan_id',
        'nama_sub_kegiatan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'kegiatan_id');
    }

    public function laporan(): HasMany
    {
        return $this->hasMany(LaporanOppkpke::class, 'sub_kegiatan_id');
    }

    public function laporanTahunIni(): HasOne
    {
        return $this->hasOne(LaporanOppkpke::class, 'sub_kegiatan_id')
            ->where('tahun', date('Y'));
    }

    // Get full hierarchy path
    public function getFullPathAttribute(): string
    {
        $kegiatan = $this->kegiatan;
        $program = $kegiatan->program;
        $perangkat = $program->perangkatDaerah;
        $strategi = $program->strategi;

        return "{$strategi->nama} → {$perangkat->nama} → {$program->nama_program} → {$kegiatan->nama_kegiatan}";
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}