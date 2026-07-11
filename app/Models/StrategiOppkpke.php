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

    /**
     * Resolusi strategi dari teks bebas file (kode atau nama). Mengembalikan null
     * bila tidak dikenali — TIDAK ada fallback diam-diam. Optional $active =
     * koleksi strategi aktif yang sudah dipreload (hemat query pada loop).
     */
    public static function resolveFromText(?string $text, $active = null): ?self
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        $norm = fn ($s) => trim(preg_replace('/\s+/', ' ', strtolower(preg_replace('/[^a-z0-9]+/i', ' ', (string) $s))));
        $t = $norm($text);
        if ($t === '') {
            return null;
        }
        $active ??= static::where('is_active', true)->get();

        // 1) Kode persis.
        foreach ($active as $s) {
            if (strcasecmp(trim((string) $s->kode), $text) === 0) return $s;
        }
        // 2) Nama persis (ternormalisasi).
        foreach ($active as $s) {
            if ($norm($s->nama) === $t) return $s;
        }
        // 3) Teks memuat kode/nama strategi (atau sebaliknya).
        foreach ($active as $s) {
            $nk = $norm($s->kode);
            $nn = $norm($s->nama);
            if (($nk !== '' && str_contains($t, $nk)) || str_contains($t, $nn) || str_contains($nn, $t)) return $s;
        }

        return null;
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