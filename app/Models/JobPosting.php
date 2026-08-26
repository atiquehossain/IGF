<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobPosting extends Model
{
    use HasFactory, HasGeneratedUuid, SoftDeletes;

    public const PUBLICATION_DRAFT = 'draft';
    public const PUBLICATION_PUBLISHED = 'published';
    public const PUBLICATION_WITHDRAWN = 'withdrawn';
    public const PUBLICATION_STATUSES = [
        self::PUBLICATION_DRAFT,
        self::PUBLICATION_PUBLISHED,
        self::PUBLICATION_WITHDRAWN,
    ];

    public const EMPLOYMENT_FULL_TIME = 'full_time';
    public const EMPLOYMENT_PART_TIME = 'part_time';
    public const EMPLOYMENT_CONTRACT = 'contract';
    public const EMPLOYMENT_INTERNSHIP = 'internship';
    public const EMPLOYMENT_CONSULTANCY = 'consultancy';
    public const EMPLOYMENT_TYPES = [
        self::EMPLOYMENT_FULL_TIME,
        self::EMPLOYMENT_PART_TIME,
        self::EMPLOYMENT_CONTRACT,
        self::EMPLOYMENT_INTERNSHIP,
        self::EMPLOYMENT_CONSULTANCY,
    ];

    public const WORK_ON_SITE = 'on_site';
    public const WORK_REMOTE = 'remote';
    public const WORK_HYBRID = 'hybrid';
    public const WORK_ARRANGEMENTS = [self::WORK_ON_SITE, self::WORK_REMOTE, self::WORK_HYBRID];

    protected $fillable = [
        'application_form_id', 'current_form_version_id', 'publication_status',
        'visible_from_at', 'application_opens_at', 'application_closes_at',
        'employment_type', 'work_arrangement', 'vacancy_count', 'editor_version',
        'created_by_admin_id', 'updated_by_admin_id', 'published_by_admin_id',
    ];

    protected $casts = [
        'visible_from_at' => 'immutable_datetime',
        'application_opens_at' => 'immutable_datetime',
        'application_closes_at' => 'immutable_datetime',
        'vacancy_count' => 'integer',
        'editor_version' => 'integer',
    ];

    public function scopePublicDetail(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where($query->qualifyColumn('publication_status'), self::PUBLICATION_PUBLISHED)
            ->whereNotNull($query->qualifyColumn('visible_from_at'))
            ->where($query->qualifyColumn('visible_from_at'), '<=', $at);
    }

    public function scopeActiveList(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query->publicDetail($at)
            ->where($query->qualifyColumn('application_closes_at'), '>', $at);
    }

    public function scopeOpenForSubmission(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query->activeList($at)
            ->where($query->qualifyColumn('application_opens_at'), '<=', $at);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(ApplicationForm::class, 'application_form_id');
    }

    public function currentFormVersion(): BelongsTo
    {
        return $this->belongsTo(ApplicationFormVersion::class, 'current_form_version_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(JobPostingTranslation::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function scorecardCriteria(): HasMany
    {
        return $this->hasMany(JobScorecardCriterion::class)->orderBy('position');
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ApplicationImportBatch::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    public function publishedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'published_by_admin_id');
    }
}
