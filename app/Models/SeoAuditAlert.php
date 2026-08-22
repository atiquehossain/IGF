<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoAuditAlert extends Model
{
    protected $fillable = [
        'run_id',
        'alert_type',
        'severity',
        'title',
        'message',
        'context',
        'email_status',
        'email_attempted_at',
        'email_failure',
    ];

    protected $casts = [
        'context' => 'array',
        'email_attempted_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SeoAuditRun::class, 'run_id');
    }
}
