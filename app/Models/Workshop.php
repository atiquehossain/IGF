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

class Workshop extends Model
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

    public const ATTENDANCE_OFFLINE = 'offline';
    public const ATTENDANCE_ONLINE = 'online';
    public const ATTENDANCE_HYBRID = 'hybrid';
    public const ATTENDANCE_MODES = [
        self::ATTENDANCE_OFFLINE,
        self::ATTENDANCE_ONLINE,
        self::ATTENDANCE_HYBRID,
    ];

    public const REGISTRATION_AUTOMATIC = 'automatic';
    public const REGISTRATION_MANUAL = 'manual';
    public const REGISTRATION_WAITLIST = 'waitlist';
    public const REGISTRATION_MODES = [
        self::REGISTRATION_AUTOMATIC,
        self::REGISTRATION_MANUAL,
        self::REGISTRATION_WAITLIST,
    ];

    protected $fillable = [
        'application_form_id', 'current_form_version_id', 'publication_status',
        'visible_from_at', 'registration_opens_at', 'registration_closes_at',
        'starts_at', 'ends_at', 'attendance_mode', 'registration_mode',
        'capacity', 'private_meeting_url', 'editor_version',
        'created_by_admin_id', 'updated_by_admin_id', 'published_by_admin_id',
    ];

    protected $hidden = ['private_meeting_url'];

    protected $casts = [
        'visible_from_at' => 'immutable_datetime',
        'registration_opens_at' => 'immutable_datetime',
        'registration_closes_at' => 'immutable_datetime',
        'starts_at' => 'immutable_datetime',
        'ends_at' => 'immutable_datetime',
        'capacity' => 'integer',
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
            ->where($query->qualifyColumn('registration_closes_at'), '>', $at);
    }

    public function scopeOpenForSubmission(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query->activeList($at)
            ->where($query->qualifyColumn('registration_opens_at'), '<=', $at);
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
        return $this->hasMany(WorkshopTranslation::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(WorkshopRegistration::class);
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
