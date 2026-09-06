<?php

namespace App\Services;

use App\Models\SiteSetting;

class SiteSettingVersionService
{
    /**
     * Hash the exact scope edited by one customizer form: the selected
     * language plus every shared setting. A Bangla-only edit must invalidate
     * another stale Bangla form without unnecessarily blocking an English-
     * only edit, while shared changes invalidate every language form.
     */
    public function current(string|bool|null $locale = null, bool $lockForUpdate = false): string
    {
        // Keep the former current(true) lock call compatible for any command
        // or extension that used the service before locale scoping was added.
        if (is_bool($locale)) {
            $lockForUpdate = $locale;
            $locale = null;
        }

        $locale ??= app()->getLocale();
        $query = SiteSetting::withTrashed()
            ->whereIn('locale', [$locale, '*'])
            ->orderBy('locale')
            ->orderBy('group')
            ->orderBy('key');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $stored = $query->get()->keyBy(fn (SiteSetting $setting): string => implode("\0", [
            $setting->group,
            $setting->key,
            $setting->locale,
        ]));
        $state = [];

        foreach (config('site-settings.groups', []) as $groupKey => $group) {
            foreach ($group['fields'] ?? [] as $key => $field) {
                $settingLocale = ($field['localized'] ?? false) ? $locale : '*';
                $setting = $stored->get(implode("\0", [$groupKey, $key, $settingLocale]));
                $isStored = $setting && !$setting->trashed();
                $legacyFallback = ($field['localized'] ?? false)
                    ? $stored->get(implode("\0", [$groupKey, $key, '*']))
                    : null;
                $hasFallback = !$isStored && $legacyFallback && !$legacyFallback->trashed();
                $state[$groupKey][$key] = [
                    'locale' => $hasFallback ? '*' : $settingLocale,
                    'stored' => (bool) $isStored,
                    'fallback' => (bool) $hasFallback,
                    'value' => $isStored || $hasFallback
                        ? (string) ($isStored ? $setting->value : $legacyFallback->value)
                        : $this->serializeDefault($this->defaultFor($field, $locale), (string) ($field['type'] ?? 'text')),
                    'type' => $isStored || $hasFallback
                        ? (string) ($isStored ? $setting->type : $legacyFallback->type)
                        : null,
                    'public' => $isStored || $hasFallback
                        ? (bool) ($isStored ? $setting->is_public : $legacyFallback->is_public)
                        : null,
                ];
            }
        }

        $payload = json_encode(
            $state,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $secret = (string) config('app.key', '');

        return hash_hmac('sha256', $payload, $secret !== '' ? $secret : 'site-settings-version');
    }

    private function defaultFor(array $field, string $locale): mixed
    {
        $localizedDefaults = $field['localized_defaults'] ?? [];

        if (($field['localized'] ?? false)
            && is_array($localizedDefaults)
            && array_key_exists($locale, $localizedDefaults)) {
            return $localizedDefaults[$locale];
        }

        return $field['default'] ?? null;
    }

    private function serializeDefault(mixed $value, string $type): string
    {
        if ($type === 'faq_list' || is_array($value)) {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) ($value ?? '');
    }
}
