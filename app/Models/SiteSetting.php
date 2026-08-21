<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiteSetting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'group',
        'key',
        'locale',
        'value',
        'type',
        'is_public',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function getTypedValueAttribute()
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'json' => json_decode($this->value ?: 'null', true),
            default => $this->value,
        };
    }

    public static function valueFor(string $group, string $key, ?string $locale = null, $default = null)
    {
        $locale = $locale ?: app()->getLocale();

        $setting = static::where('group', $group)
            ->where('key', $key)
            ->whereIn('locale', [$locale, '*'])
            ->orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END', [$locale])
            ->first();

        return $setting ? $setting->typed_value : $default;
    }
}
