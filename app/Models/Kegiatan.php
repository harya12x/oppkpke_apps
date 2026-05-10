<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Kegiatan extends Model
{
    protected $table = 'kegiatan';
    
    protected $fillable = [
        'program_id',
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