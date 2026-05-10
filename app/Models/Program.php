<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Program extends Model
{
    protected $table = 'programs';

    protected $fillable = [
        'strategi_id',
        'perangkat_daerah_id',
        'kode_program',
        'nama_program',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function strategi(): BelongsTo
    {
        return $this->belongsTo(StrategiOppkpke::class, 'strategi_id');
    }

    public function perangkatDaerah(): BelongsTo
    {
        return $this->belongsTo(PerangkatDaerah::class, 'perangkat_daerah_id');
    }

    public function kegiatan(): HasMany
    {
        return $this->hasMany(Kegiatan::class, 'program_id');
    }

    public function subKegiatan(): HasManyThrough
    {
        return $this->hasManyThrough(
            SubKegiatan::class,
            Kegiatan::class,
            'program_id',
            'kegiatan_id'
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByStrategi($query, $strategiId)
    {
        return $query->where('strategi_id', $strategiId);
    }

    public function scopeByPerangkatDaerah($query, $perangkatDaerahId)
    {
        return $query->where('perangkat_daerah_id', $perangkatDaerahId);
    }
}