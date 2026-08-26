<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApplicationForm extends Model
{
    use HasFactory, HasGeneratedUuid, SoftDeletes;

    public const PURPOSE_JOB = 'job';
    public const PURPOSE_WORKSHOP = 'workshop';
    public const PURPOSES = [self::PURPOSE_JOB, self::PURPOSE_WORKSHOP];

    protected $fillable = [
        'purpose', 'name', 'is_template', 'editor_version',
        'created_by_admin_id', 'updated_by_admin_id',
    ];

    protected $casts = [
        'is_template' => 'boolean',
        'editor_version' => 'integer',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(ApplicationFormVersion::class)->orderBy('version');
    }

    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    public function workshops(): HasMany
    {
        return $this->hasMany(Workshop::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }
}
