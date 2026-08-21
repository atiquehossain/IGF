<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranslationLocale extends Model
{
    protected $primaryKey = 'locale';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'locale',
        'name',
        'native_name',
        'is_default',
        'is_enabled',
        'enabled_at',
        'updated_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_enabled' => 'boolean',
        'enabled_at' => 'datetime',
    ];
}
