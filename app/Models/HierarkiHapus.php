<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu kali "hapus" program/kegiatan/sub kegiatan dari halaman Input Data.
 * Baris yang ikut terhapus ditandai hapus_batch_id = id baris ini.
 * Lihat App\Services\HierarkiSampahService.
 */
class HierarkiHapus extends Model
{
    protected $table = 'hierarki_hapus';

    protected $fillable = [
        'perangkat_daerah_id',
        'jenis',
        'objek_id',
        'nama',
        'lokasi',
        'jumlah_kegiatan',
        'jumlah_sub',
        'jumlah_laporan',
        'ringkasan',
        'dihapus_oleh',
        'dihapus_oleh_nama',
        'dipulihkan_pada',
        'dipulihkan_oleh',
        'dipulihkan_oleh_nama',
        'cara_pulih',
    ];

    protected $casts = [
        'ringkasan'       => 'array',
        'dipulihkan_pada' => 'datetime',
        'jumlah_kegiatan' => 'integer',
        'jumlah_sub'      => 'integer',
        'jumlah_laporan'  => 'integer',
    ];

    public const LABEL = [
        'program'      => 'Program',
        'kegiatan'     => 'Kegiatan',
        'sub_kegiatan' => 'Sub Kegiatan',
    ];

    public function perangkatDaerah()
    {
        return $this->belongsTo(PerangkatDaerah::class, 'perangkat_daerah_id');
    }

    public function getLabelJenisAttribute(): string
    {
        return self::LABEL[$this->jenis] ?? $this->jenis;
    }
}
