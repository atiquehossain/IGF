<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranslationString extends Model
{
    protected $fillable = [
        'key',
        'locale',
        'value',
        'source_hash',
        'status',
        'updated_by',
    ];
}
