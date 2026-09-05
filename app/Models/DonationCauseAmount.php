<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DonationCauseAmount extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'donation_type_id',
        'amount',
        'impact',
        'display_order',
        'enabled',
    ];

    protected $casts = [
        'amount' => 'integer',
        'impact' => 'array',
        'display_order' => 'integer',
        'enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (DonationCauseAmount $amount): void {
            $amount->uuid ??= (string) Str::uuid();
        });
    }

    public function donationType(): BelongsTo
    {
        return $this->belongsTo(DonationType::class);
    }
}
