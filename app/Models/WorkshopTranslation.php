<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopTranslation extends Model
{
    protected $fillable = [
        'workshop_id', 'locale', 'slug', 'title', 'summary', 'description',
        'facilitator_name', 'venue_name', 'venue_address', 'registration_instructions',
    ];

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function translationLocale(): BelongsTo
    {
        return $this->belongsTo(TranslationLocale::class, 'locale', 'locale');
    }
}
