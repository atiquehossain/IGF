<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ApplicationFormVersion extends Model
{
    use HasFactory, HasGeneratedUuid;

    public const STATE_DRAFT = 'draft';
    public const STATE_PUBLISHED = 'published';
    public const STATE_RETIRED = 'retired';
    public const STATES = [self::STATE_DRAFT, self::STATE_PUBLISHED, self::STATE_RETIRED];

    protected $fillable = [
        'application_form_id', 'version', 'state', 'schema_hash',
        'published_at', 'published_by_admin_id',
    ];

    protected $casts = [
        'version' => 'integer',
        'published_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (ApplicationFormVersion $version): void {
            $originalState = $version->getOriginal('state');
            if ($originalState === self::STATE_DRAFT) {
                return;
            }

            // Retirement is lifecycle metadata, not a schema mutation. Allow the
            // one-way published -> retired transition while keeping every schema
            // column and all normalized children immutable.
            $dirty = array_keys($version->getDirty());
            $onlyRetiresPublished = $originalState === self::STATE_PUBLISHED
                && $version->state === self::STATE_RETIRED
                && array_diff($dirty, ['state', 'updated_at']) === [];
            if ($onlyRetiresPublished) {
                return;
            }

            throw new LogicException('Published and retired application form versions are immutable.');
        });
        static::deleting(function (ApplicationFormVersion $version): void {
            if ($version->state !== self::STATE_DRAFT) {
                throw new LogicException('Published and retired application form versions are immutable.');
            }
        });
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(ApplicationForm::class, 'application_form_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ApplicationFormField::class)->orderBy('position');
    }

    public function currentJobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class, 'current_form_version_id');
    }

    public function currentWorkshops(): HasMany
    {
        return $this->hasMany(Workshop::class, 'current_form_version_id');
    }

    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'application_form_version_id');
    }

    public function workshopRegistrations(): HasMany
    {
        return $this->hasMany(WorkshopRegistration::class, 'application_form_version_id');
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ApplicationImportBatch::class, 'application_form_version_id');
    }

    public function publishedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'published_by_admin_id');
    }
}
