<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPostingTranslation extends Model
{
    protected $fillable = [
        'job_posting_id', 'locale', 'slug', 'title', 'department', 'location',
        'summary', 'description', 'responsibilities', 'requirements',
    ];

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function translationLocale(): BelongsTo
    {
        return $this->belongsTo(TranslationLocale::class, 'locale', 'locale');
    }
}
