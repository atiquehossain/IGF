<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplicationAnswer extends Model
{
    protected $fillable = [
        'job_application_id', 'application_form_field_id', 'value_text',
        'value_number', 'value_date', 'value_boolean', 'value_json',
    ];

    protected $casts = [
        'value_number' => 'decimal:4',
        'value_date' => 'immutable_date',
        'value_boolean' => 'boolean',
        'value_json' => 'array',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ApplicationFormField::class, 'application_form_field_id');
    }
}
