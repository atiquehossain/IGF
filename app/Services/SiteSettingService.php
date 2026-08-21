<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Collection;

class SiteSettingService
{
    public function __construct(private ContentSanitizer $sanitizer)
    {
    }

    public function values(?string $locale = null, bool $publicOnly = false): array
    {
        $locale ??= app()->getLocale();
        $schema = config('site-settings.groups', []);
        $stored = SiteSetting::query()
            ->when($publicOnly, fn ($query) => $query->where('is_public', true))
            ->whereIn('locale', [$locale, '*'])
            ->get()
            ->groupBy(fn (SiteSetting $setting) => $setting->group . '.' . $setting->key);

        $values = [];

        foreach ($schema as $groupKey => $group) {
            foreach ($group['fields'] as $key => $field) {
                if ($publicOnly && !($field['public'] ?? false)) {
                    continue;
                }

                $matches = $stored->get($groupKey . '.' . $key, collect());
                $setting = $this->preferredSetting($matches, $locale);
                $value = $setting
                    ? $setting->typed_value
                    : ($field['default'] ?? null);
                $values[$groupKey][$key] = $publicOnly && in_array($field['type'] ?? null, ['url', 'url_or_path'], true)
                    ? $this->sanitizer->sanitizeUrl($value)
                    : $value;
            }
        }

        $this->addCalculatedZakatNisab($values);

        return $values;
    }

    /**
     * Keep the former public `nisab_amount` contract available as a calculated,
     * read-only value. Old stored fixed amounts are intentionally ignored: the
     * threshold must always follow the selected metal price and weight.
     */
    private function addCalculatedZakatNisab(array &$values): void
    {
        if (!isset($values['zakat_calculator']) || !is_array($values['zakat_calculator'])) {
            return;
        }

        $settings = $values['zakat_calculator'];
        $basis = in_array($settings['nisab_default_basis'] ?? null, ['gold', 'silver'], true)
            ? $settings['nisab_default_basis']
            : 'silver';
        $priceKey = $basis === 'gold' ? 'gold_price_per_gram' : 'silver_price_per_gram';
        $priceField = config("site-settings.groups.zakat_calculator.fields.{$priceKey}", []);
        $price = (float) ($settings[$priceKey] ?? 0);
        $minimum = (float) ($priceField['min'] ?? 0.01);
        $maximum = (float) ($priceField['max'] ?? 10000000);

        if (!is_finite($price) || $price < $minimum || $price > $maximum) {
            $price = (float) ($priceField['default'] ?? $minimum);
        }

        $weight = $basis === 'gold' ? 87.48 : 612.36;

        $values['zakat_calculator']['nisab_amount'] = max(1, (int) round($price * $weight));
    }

    private function preferredSetting(Collection $settings, string $locale): ?SiteSetting
    {
        return $settings->firstWhere('locale', $locale)
            ?? $settings->firstWhere('locale', '*');
    }
}
