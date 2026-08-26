<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplicationScore extends Model
{
    protected $fillable = [
        'job_application_id', 'job_scorecard_criterion_id', 'reviewer_admin_id',
        'score', 'criterion_label_snapshot', 'maximum_score_snapshot', 'comment',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'maximum_score_snapshot' => 'decimal:2',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(JobScorecardCriterion::class, 'job_scorecard_criterion_id');
    }

    public function reviewerAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewer_admin_id');
    }
}
