<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'chat_conversation_id',
        'admin_id',
        'action',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
