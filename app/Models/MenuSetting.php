<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuSetting extends Model
{
    protected $table = 'menu_settings';

    protected $fillable = ['role', 'menu_key', 'is_enabled'];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];
}
