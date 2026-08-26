<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedUuid;
use App\Models\Concerns\NormalizesApplicantEmail;
use App\Support\ApplicationIdentity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobApplication extends Model
{
    use HasFactory, HasGeneratedUuid, NormalizesApplicantEmail, SoftDeletes;

    public const STATUS_NEW = 'new';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_SHORTLISTED = 'shortlisted';
    public const STATUS_INTERVIEW = 'interview';
    public const STATUS_OFFERED = 'offered';
    public const STATUS_HIRED = 'hired';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUSES = [
        self::STATUS_NEW, self::STATUS_UNDER_REVIEW, self::STATUS_SHORTLISTED,
        self::STATUS_INTERVIEW, self::STATUS_OFFERED, self::STATUS_HIRED,
        self::STATUS_REJECTED, self::STATUS_WITHDRAWN,
    ];

    public const SOURCE_PUBLIC = 'public';
    public const SOURCE_IMPORT = 'import';
    public const SOURCE_ADMIN = 'admin';
    public const SOURCES = [self::SOURCE_PUBLIC, self::SOURCE_IMPORT, self::SOURCE_ADMIN];

    protected $fillable = [
        'job_posting_id', 'application_form_version_id', 'name', 'email', 'phone',
        'workflow_status', 'assigned_to_admin_id', 'submission_count',
        'first_submitted_at', 'last_submitted_at', 'source', 'last_import_batch_id',
        'status_changed_at', 'status_changed_by_admin_id', 'anonymized_at',
        'anonymized_by_admin_id',
    ];

    protected $hidden = ['email_hash'];

    protected $casts = [
        'assigned_to_admin_id' => 'integer',
        'submission_count' => 'integer',
        'first_submitted_at' => 'immutable_datetime',
        'last_submitted_at' => 'immutable_datetime',
        'status_changed_at' => 'immutable_datetime',
        'anonymized_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (JobApplication $application): void {
            if (blank($application->reference_number)) {
                $application->reference_number = ApplicationIdentity::reference('job');
            }
            $application->first_submitted_at ??= now();
            $application->last_submitted_at ??= now();
        });
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(ApplicationFormVersion::class, 'application_form_version_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to_admin_id');
    }

    public function statusChangedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'status_changed_by_admin_id');
    }

    public function anonymizedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'anonymized_by_admin_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(JobApplicationAnswer::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(JobApplicationDocument::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(JobApplicationNote::class)->latest();
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(JobApplicationStatusEvent::class)->orderBy('id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(JobApplicationScore::class);
    }

    public function lastImportBatch(): BelongsTo
    {
        return $this->belongsTo(ApplicationImportBatch::class, 'last_import_batch_id');
    }
}
