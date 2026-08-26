<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobScorecardCriterion extends Model
{
    use HasGeneratedUuid, SoftDeletes;

    protected $fillable = [
        'job_posting_id', 'label', 'description', 'maximum_score', 'position', 'is_enabled',
    ];

    protected $casts = [
        'maximum_score' => 'decimal:2',
        'position' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(JobApplicationScore::class);
    }
}
