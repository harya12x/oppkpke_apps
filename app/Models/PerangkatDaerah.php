<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerangkatDaerah extends Model
{
    protected $table = 'perangkat_daerah';
    
    protected $fillable = [
        'kode',
        'nama',
        'singkatan',
        'jenis',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class, 'perangkat_daerah_id');
    }

     public function program()
    {
        return $this->hasMany(Program::class, 'perangkat_daerah_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDinas($query)
    {
        return $query->whereIn('jenis', ['dinas', 'badan']);
    }

    public function scopeKecamatan($query)
    {
        return $query->where('jenis', 'kecamatan');
    }

    public function getJenisLabelAttribute(): string
    {
        return match($this->jenis) {
            'dinas' => 'Dinas',
            'badan' => 'Badan',
            'kecamatan' => 'Kecamatan',
            default => ucfirst($this->jenis),
        };
    }
}