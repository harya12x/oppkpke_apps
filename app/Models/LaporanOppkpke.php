<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanOppkpke extends Model
{
    protected $table = 'laporan_oppkpke';

    protected $fillable = [
        'sub_kegiatan_id',
        'tahun',
        'semester',
        'durasi_pemberian',
        'besaran_manfaat',
        'jenis_bantuan',
        'jumlah_sasaran',
        'satuan_sasaran',
        'aktivitas_langsung',
        'aktivitas_tidak_langsung',
        'aktivitas_penunjang',
        'penerima_langsung',
        'penerima_tidak_langsung',
        'penerima_penunjang',
        'sumber_pembiayaan',
        'sifat_bantuan',
        'lokasi',
        'alokasi_anggaran',
        'realisasi_sem1',
        'realisasi_sem2',
        'realisasi_total',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'semester' => 'integer',
        'penerima_langsung' => 'decimal:2',
        'penerima_tidak_langsung' => 'decimal:2',
        'penerima_penunjang' => 'decimal:2',
        'alokasi_anggaran' => 'decimal:2',
        'realisasi_sem1' => 'decimal:2',
        'realisasi_sem2' => 'decimal:2',
        'realisasi_total' => 'decimal:2',
    ];

    // ══════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ══════════════════════════════════════════════════════════════

    public function subKegiatan(): BelongsTo
    {
        return $this->belongsTo(SubKegiatan::class, 'sub_kegiatan_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ══════════════════════════════════════════════════════════════
    // ACCESSORS
    // ══════════════════════════════════════════════════════════════

    public function getPersentaseRealisasiAttribute(): float
    {
        if ($this->alokasi_anggaran <= 0) {
            return 0;
        }
        return round(($this->realisasi_total / $this->alokasi_anggaran) * 100, 2);
    }

    public function getTotalPenerimaAttribute(): float
    {
        return ($this->penerima_langsung ?? 0)
            + ($this->penerima_tidak_langsung ?? 0)
            + ($this->penerima_penunjang ?? 0);
    }

    public function getStatusRealisasiAttribute(): string
    {
        $persen = $this->persentase_realisasi;

        return match(true) {
            $persen >= 90 => 'excellent',
            $persen >= 70 => 'good',
            $persen >= 50 => 'warning',
            default => 'danger',
        };
    }

    // ══════════════════════════════════════════════════════════════
    // SCOPES
    // ══════════════════════════════════════════════════════════════

    public function scopeTahun($query, $tahun)
    {
        return $query->where('tahun', $tahun);
    }

    public function scopeSemester($query, $semester)
    {
        return $query->where('semester', $semester);
    }

    public function scopeByStrategi($query, $strategiId)
    {
        return $query->whereHas('subKegiatan.kegiatan.program', function ($q) use ($strategiId) {
            $q->where('strategi_id', $strategiId);
        });
    }

    public function scopeByPerangkatDaerah($query, $perangkatDaerahId)
    {
        return $query->whereHas('subKegiatan.kegiatan.program', function ($q) use ($perangkatDaerahId) {
            $q->where('perangkat_daerah_id', $perangkatDaerahId);
        });
    }
}
