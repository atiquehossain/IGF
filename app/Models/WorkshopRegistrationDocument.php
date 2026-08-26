<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopRegistrationDocument extends Model
{
    use HasGeneratedUuid;

    public const KIND_ATTACHMENT = 'attachment';
    public const KIND_CV = 'cv';
    public const DOCUMENT_KINDS = [self::KIND_ATTACHMENT, self::KIND_CV];

    protected $fillable = [
        'workshop_registration_id', 'application_form_field_id', 'document_kind',
        'disk', 'path', 'original_name', 'mime_type', 'bytes', 'sha256',
    ];

    protected $hidden = ['disk', 'path', 'sha256'];

    protected $casts = ['bytes' => 'integer'];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(WorkshopRegistration::class, 'workshop_registration_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ApplicationFormField::class, 'application_form_field_id');
    }
}
