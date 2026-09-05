<?php

namespace App\Services;

use App\Models\SiteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SiteSettingService
{
    private const ZAKAT_PRICE_MAX_AGE_DAYS = 7;
    private const DEFAULT_ZAKAT_NISAB_STANDARD = 'standard_87_48_612_36';

    private const ZAKAT_NISAB_WEIGHTS = [
        'standard_87_48_612_36' => ['gold' => 87.48, 'silver' => 612.36],
        'standard_85_595' => ['gold' => 85.0, 'silver' => 595.0],
    ];

    public function __construct(
        private ContentSanitizer $sanitizer,
        private PublicFormFieldLayoutService $formLayouts,
    )
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
                    : $this->localizedDefault($field, $locale);
                if (($field['type'] ?? null) === 'form_field_layout') {
                    $value = $this->formLayouts->normalize($field, $value);
                } elseif ($publicOnly && in_array($field['type'] ?? null, ['url', 'url_or_path'], true)) {
                    $value = $this->sanitizer->sanitizeUrl($value);
                }

                $values[$groupKey][$key] = $value;
            }
        }

        $this->addContactFaqCompatibility($values, $stored, $locale);
        $this->addCalculatedZakatNisab($values);

        return $values;
    }

    /**
     * Prefer the dynamic FAQ list while preserving installations that still
     * have customized values under the former five fixed question fields.
     * The aliases keep older public clients working during deployment.
     */
    private function addContactFaqCompatibility(array &$values, Collection $stored, string $locale): void
    {
        if (!isset($values['contact_page']) || !is_array($values['contact_page'])) {
            return;
        }

        $defaultItems = config('site-settings.groups.contact_page.fields.faqs.default', []);
        $items = $values['contact_page']['faqs'] ?? $defaultItems;
        $storedList = $this->preferredSetting($stored->get('contact_page.faqs', collect()), $locale);

        if (!is_array($items)) {
            $items = $defaultItems;
        }

        if (!$storedList) {
            foreach ($items as $index => &$item) {
                if (!is_array($item)) {
                    $item = [];
                }

                $position = $index + 1;
                foreach (['question', 'answer'] as $part) {
                    $legacy = $this->preferredSetting(
                        $stored->get("contact_page.faq_{$position}_{$part}", collect()),
                        $locale
                    );

                    if ($legacy) {
                        $item[$part] = $legacy->typed_value;
                    }
                }
            }
            unset($item);
        }

        $items = collect($items)
            ->filter(fn ($item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'question' => trim(strip_tags((string) ($item['question'] ?? ''))),
                'answer' => trim(strip_tags((string) ($item['answer'] ?? ''))),
                'is_active' => filter_var($item['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ])
            ->filter(fn (array $item): bool => $item['question'] !== '')
            ->take(50)
            ->values()
            ->all();

        $values['contact_page']['faqs'] = $items;

        for ($position = 1; $position <= 5; $position++) {
            $item = $items[$position - 1] ?? null;
            $visible = is_array($item) && ($item['is_active'] ?? true);
            $values['contact_page']["faq_{$position}_question"] = $visible ? $item['question'] : '';
            $values['contact_page']["faq_{$position}_answer"] = $visible ? $item['answer'] : '';
        }
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
        $pricesValid = collect(['gold_price_per_gram', 'silver_price_per_gram'])
            ->every(fn (string $key): bool => $this->zakatPriceIsValid($key, $settings[$key] ?? null));

        if (!$this->zakatPriceIsValid($priceKey, $settings[$priceKey] ?? null)) {
            $price = (float) ($priceField['default'] ?? $minimum);
        }

        $standard = array_key_exists($settings['nisab_weight_standard'] ?? '', self::ZAKAT_NISAB_WEIGHTS)
            ? $settings['nisab_weight_standard']
            : self::DEFAULT_ZAKAT_NISAB_STANDARD;
        $weight = self::ZAKAT_NISAB_WEIGHTS[$standard][$basis];

        $values['zakat_calculator']['nisab_prices_current'] = $this->zakatPricesCurrent(
            $settings['nisab_price_updated_at'] ?? null
        ) && $pricesValid;
        $values['zakat_calculator']['nisab_amount'] = max(1, (int) round($price * $weight));
    }

    private function zakatPriceIsValid(string $key, mixed $value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $field = config("site-settings.groups.zakat_calculator.fields.{$key}", []);
        $price = (float) $value;
        $minimum = (float) ($field['min'] ?? 0.01);
        $maximum = (float) ($field['max'] ?? 10000000);

        return is_finite($price) && $price >= $minimum && $price <= $maximum;
    }

    private function zakatPricesCurrent(mixed $value): bool
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        try {
            $timezone = (string) config('app.timezone', 'UTC');
            $checkedAt = CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone);

            if (!$checkedAt || $checkedAt->format('Y-m-d') !== $value) {
                return false;
            }

            $today = CarbonImmutable::now($timezone)->startOfDay();

            return $checkedAt->lte($today)
                && $checkedAt->gte($today->subDays(self::ZAKAT_PRICE_MAX_AGE_DAYS));
        } catch (\Throwable) {
            return false;
        }
    }

    private function preferredSetting(Collection $settings, string $locale): ?SiteSetting
    {
        return $settings->firstWhere('locale', $locale)
            ?? $settings->firstWhere('locale', '*');
    }

    private function localizedDefault(array $field, string $locale): mixed
    {
        $localizedDefaults = $field['localized_defaults'] ?? [];

        if (($field['localized'] ?? false)
            && is_array($localizedDefaults)
            && array_key_exists($locale, $localizedDefaults)) {
            return $localizedDefaults[$locale];
        }

        return $field['default'] ?? null;
    }
}
