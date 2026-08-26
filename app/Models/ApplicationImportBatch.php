<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationImportBatch extends Model
{
    use HasFactory, HasGeneratedUuid;

    public const TARGET_JOB = 'job';
    public const TARGET_WORKSHOP = 'workshop';
    public const TARGET_KINDS = [self::TARGET_JOB, self::TARGET_WORKSHOP];

    public const STATE_UPLOADED = 'uploaded';
    public const STATE_PREVIEWED = 'previewed';
    public const STATE_CONFIRMED = 'confirmed';
    public const STATE_PROCESSING = 'processing';
    public const STATE_COMPLETED = 'completed';
    public const STATE_FAILED = 'failed';
    public const STATE_CANCELLED = 'cancelled';
    public const STATES = [
        self::STATE_UPLOADED,
        self::STATE_PREVIEWED,
        self::STATE_CONFIRMED,
        self::STATE_PROCESSING,
        self::STATE_COMPLETED,
        self::STATE_FAILED,
        self::STATE_CANCELLED,
    ];

    protected $fillable = [
        'target_kind', 'job_posting_id', 'workshop_id',
        'application_form_version_id', 'form_schema_hash', 'state',
        'source_disk', 'source_path', 'source_name', 'source_sha256',
        'column_mapping', 'options', 'total_rows', 'valid_rows',
        'invalid_rows', 'duplicate_rows', 'imported_rows',
        'uploaded_by_admin_id', 'confirmed_by_admin_id', 'previewed_at',
        'confirmed_at',
    ];

    protected $hidden = ['source_disk', 'source_path', 'source_sha256'];

    protected $casts = [
        'column_mapping' => 'array',
        'options' => 'array',
        'total_rows' => 'integer',
        'valid_rows' => 'integer',
        'invalid_rows' => 'integer',
        'duplicate_rows' => 'integer',
        'imported_rows' => 'integer',
        'previewed_at' => 'immutable_datetime',
        'confirmed_at' => 'immutable_datetime',
    ];

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(ApplicationFormVersion::class, 'application_form_version_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ApplicationImportRow::class)->orderBy('row_number');
    }

    public function importedJobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'last_import_batch_id');
    }

    public function importedWorkshopRegistrations(): HasMany
    {
        return $this->hasMany(WorkshopRegistration::class, 'last_import_batch_id');
    }

    public function uploadedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by_admin_id');
    }

    public function confirmedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'confirmed_by_admin_id');
    }
}
