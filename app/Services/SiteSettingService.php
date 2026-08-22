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
