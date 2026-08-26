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

class WorkshopRegistration extends Model
{
    use HasFactory, HasGeneratedUuid, NormalizesApplicantEmail, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_WAITLISTED = 'waitlisted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_WAITLISTED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    public const SOURCE_PUBLIC = 'public';
    public const SOURCE_IMPORT = 'import';
    public const SOURCE_ADMIN = 'admin';
    public const SOURCES = [self::SOURCE_PUBLIC, self::SOURCE_IMPORT, self::SOURCE_ADMIN];

    protected $fillable = [
        'workshop_id', 'application_form_version_id', 'name', 'email', 'phone',
        'workflow_status', 'assigned_to_admin_id', 'submission_count',
        'first_submitted_at', 'last_submitted_at', 'waitlisted_at', 'confirmed_at',
        'cancelled_at', 'source', 'last_import_batch_id', 'status_changed_at',
        'status_changed_by_admin_id', 'anonymized_at', 'anonymized_by_admin_id',
    ];

    protected $hidden = ['email_hash'];

    protected $casts = [
        'assigned_to_admin_id' => 'integer',
        'submission_count' => 'integer',
        'first_submitted_at' => 'immutable_datetime',
        'last_submitted_at' => 'immutable_datetime',
        'waitlisted_at' => 'immutable_datetime',
        'confirmed_at' => 'immutable_datetime',
        'cancelled_at' => 'immutable_datetime',
        'status_changed_at' => 'immutable_datetime',
        'anonymized_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (WorkshopRegistration $registration): void {
            if (blank($registration->reference_number)) {
                $registration->reference_number = ApplicationIdentity::reference('workshop');
            }
            $registration->first_submitted_at ??= now();
            $registration->last_submitted_at ??= now();
        });
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
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
        return $this->hasMany(WorkshopRegistrationAnswer::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(WorkshopRegistrationDocument::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(WorkshopRegistrationNote::class)->latest();
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(WorkshopRegistrationStatusEvent::class)->orderBy('id');
    }

    public function lastImportBatch(): BelongsTo
    {
        return $this->belongsTo(ApplicationImportBatch::class, 'last_import_batch_id');
    }
}
