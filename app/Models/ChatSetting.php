<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSetting extends Model
{
    protected $fillable = [
        'locale',
        'enabled',
        'title',
        'welcome_message',
        'privacy_message',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
