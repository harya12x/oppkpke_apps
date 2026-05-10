<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class StrategiOppkpke extends Model
{
    protected $table = 'strategi_oppkpke';
    
    protected $fillable = [
        'kode',
        'nama',
        'deskripsi',
        'icon',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ══════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ══════════════════════════════════════════════════════════════
    
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class, 'strategi_id');
    }

    public function kegiatan(): HasManyThrough
    {
        return $this->hasManyThrough(
            Kegiatan::class,
            Program::class,
            'strategi_id',
            'program_id'
        );
    }

    // ══════════════════════════════════════════════════════════════
    // ACCESSORS
    // ══════════════════════════════════════════════════════════════
    
    public function getTotalAnggaranAttribute(): float
    {
        return $this->programs()
            ->with('kegiatan.subKegiatan.laporan')
            ->get()
            ->sum(function ($program) {
                return $program->kegiatan->sum(function ($kegiatan) {
                    return $kegiatan->subKegiatan->sum(function ($sub) {
                        return $sub->laporan->sum('alokasi_anggaran');
                    });
                });
            });
    }

    public function getTotalRealisasiAttribute(): float
    {
        return $this->programs()
            ->with('kegiatan.subKegiatan.laporan')
            ->get()
            ->sum(function ($program) {
                return $program->kegiatan->sum(function ($kegiatan) {
                    return $kegiatan->subKegiatan->sum(function ($sub) {
                        return $sub->laporan->sum('realisasi_total');
                    });
                });
            });
    }

    // ══════════════════════════════════════════════════════════════
    // SCOPES
    // ══════════════════════════════════════════════════════════════
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function program()
    {
        return $this->hasMany(Program::class, 'strategi_id');
    }
    
}