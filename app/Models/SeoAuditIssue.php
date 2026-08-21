<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoAuditIssue extends Model
{
    protected $fillable = [
        'run_id',
        'fingerprint',
        'issue_type',
        'severity',
        'source_path',
        'target_path',
        'http_status',
        'message',
        'evidence',
    ];

    protected $casts = [
        'http_status' => 'integer',
        'evidence' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SeoAuditRun::class, 'run_id');
    }
}
