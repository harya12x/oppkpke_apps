<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Services\HierarkiSampahService;

class SubKegiatan extends Model
{
    protected $table = 'sub_kegiatan';

    protected static function booted(): void
    {
        // Sub kegiatan aktif di bawah kegiatan/program yang ada di Data Terhapus
        // (mis. dibuat import) → induknya aktif kembali agar tetap terlihat.
        static::saving(function (SubKegiatan $sub) {
            if ($sub->is_active && $sub->getAttribute('hapus_batch_id')) {
                $sub->hapus_batch_id = null;   // diaktifkan lewat Eloquent → keluar dari Data Terhapus
            }
        });
        static::saved(function (SubKegiatan $sub) {
            if ($sub->is_active && $sub->kegiatan_id) {
                HierarkiSampahService::aktifkanIndukBilaTerhapus((int) $sub->kegiatan_id);
            }
        });
    }

    protected $fillable = [
        'kegiatan_id',
        'kode',
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