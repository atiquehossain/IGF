<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;
use Mattiverse\Userstamps\Traits\Userstamps;

class DonationCauseGroup extends Model
{
    use HasFactory, Userstamps;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'slug',
        'display_order',
        'status',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'status' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (DonationCauseGroup $group): void {
            if (blank($group->uuid)) {
                $group->uuid = (string) Str::uuid();
            }
            if (blank($group->slug)) {
                $group->slug = static::availableSlug((string) $group->name);
            }
        });

        static::updating(function (DonationCauseGroup $group): void {
            if ($group->isDirty('slug')) {
                throw new LogicException('A donation cause group slug is a stable identifier and cannot be changed.');
            }
        });
    }

    public function causes(): HasMany
    {
        return $this->hasMany(DonationType::class, 'donation_cause_group_id');
    }

    private static function availableSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'donation-group';
        $candidate = $base;
        $suffix = 2;

        while (static::query()->where('slug', $candidate)->exists()) {
            $candidate = $base . '-' . $suffix++;
        }

        return $candidate;
    }
}
