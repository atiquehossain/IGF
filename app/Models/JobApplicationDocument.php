<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplicationDocument extends Model
{
    use HasGeneratedUuid;

    public const KIND_CV = 'cv';
    public const KIND_ATTACHMENT = 'attachment';
    public const DOCUMENT_KINDS = [self::KIND_CV, self::KIND_ATTACHMENT];

    protected $fillable = [
        'job_application_id', 'application_form_field_id', 'document_kind',
        'disk', 'path', 'original_name', 'mime_type', 'bytes', 'sha256',
    ];

    protected $hidden = ['disk', 'path', 'sha256'];

    protected $casts = ['bytes' => 'integer'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ApplicationFormField::class, 'application_form_field_id');
    }
}
