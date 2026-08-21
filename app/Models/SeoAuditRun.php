<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoAuditRun extends Model
{
    protected $fillable = [
        'status',
        'trigger',
        'triggered_by',
        'started_at',
        'completed_at',
        'urls_checked',
        'issues_found',
        'summary',
        'failure_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'urls_checked' => 'integer',
        'issues_found' => 'integer',
        'summary' => 'array',
    ];

    public function issues(): HasMany
    {
        return $this->hasMany(SeoAuditIssue::class, 'run_id');
    }
}
