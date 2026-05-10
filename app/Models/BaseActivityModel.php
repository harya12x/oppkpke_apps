<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class BaseActivityModel extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'user_id' => 'string',
        'pernyataan_keaslian' => 'boolean',
        'persetujuan_penggunaan' => 'boolean',
        'tanggal_pengisian' => 'date',
    ];

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeValidated($query)
    {
        return $query->where('pernyataan_keaslian', true);
    }
}