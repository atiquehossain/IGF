<?php

namespace App\Data;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use JsonException;

final class SeoMetadataPayload
{
    /**
     * Ownership, locale and audit columns are deliberately absent. Callers
     * must derive those values from the route/model being edited.
     */
    public const WRITABLE_FIELDS = [
        'title',
        'description',
        'focus_keyword',
        'canonical_url',
        'robots_index',
        'robots_follow',
        'og_title',
        'og_description',
        'og_image',
        'social_image_alt',
        'twitter_card',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'schema_markup',
        'sitemap_priority',
        'sitemap_change_frequency',
        'exclude_from_sitemap',
    ];

    private function __construct(private readonly array $attributes)
    {
    }

    public static function from(array $input): self
    {
        $attributes = Arr::only($input, self::WRITABLE_FIELDS);

        if (array_key_exists('social_image_alt', $attributes)) {
            $value = is_scalar($attributes['social_image_alt'])
                ? (string) $attributes['social_image_alt']
                : '';
            $value = strip_tags($value);
            $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
            $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
            $attributes['social_image_alt'] = $value === '' ? null : mb_substr($value, 0, 420);
        }

        if (array_key_exists('schema_markup', $attributes) && is_string($attributes['schema_markup'])) {
            if (trim($attributes['schema_markup']) === '') {
                $attributes['schema_markup'] = null;
            } else {
                try {
                    $schema = json_decode($attributes['schema_markup'], true, 64, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw ValidationException::withMessages([
                        'seo.schema_markup' => 'Schema markup must be valid JSON.',
                    ]);
                }

                if (!is_array($schema)) {
                    throw ValidationException::withMessages([
                        'seo.schema_markup' => 'Schema markup must be a JSON object or array.',
                    ]);
                }

                $attributes['schema_markup'] = $schema;
            }
        }

        return new self($attributes);
    }

    public function attributes(): array
    {
        return $this->attributes;
    }
}
