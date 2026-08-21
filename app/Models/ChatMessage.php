<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChatMessage extends Model
{
    protected $fillable = [
        'uuid',
        'chat_conversation_id',
        'sender_type',
        'body',
        'chat_faq_id',
        'user_id',
        'admin_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            $message->uuid ??= (string) Str::uuid();
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(ChatFaq::class, 'chat_faq_id');
    }
}
