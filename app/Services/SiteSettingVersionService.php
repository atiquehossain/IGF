<?php

namespace App\Services;

use App\Models\SiteSetting;

class SiteSettingVersionService
{
    public function current(bool $lockForUpdate = false): string
    {
        return $this->currentForLocale((string) app()->getLocale(), $lockForUpdate);
    }

    public function currentForLocale(string $locale, bool $lockForUpdate = false): string
    {
        $locale = trim($locale) !== '' ? trim($locale) : (string) app()->getLocale();
        $query = SiteSetting::withTrashed()
            ->whereIn('locale', ['*', $locale])
            ->orderBy('locale')
            ->orderBy('group')
            ->orderBy('key');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $stored = $query->get()->keyBy(
            fn (SiteSetting $setting): string => $setting->locale . '.' . $setting->group . '.' . $setting->key
        );
        $state = [];

        foreach (config('site-settings.groups', []) as $groupKey => $group) {
            foreach ($group['fields'] ?? [] as $key => $field) {
                $settingLocale = ($field['localized'] ?? false) === true ? $locale : '*';
                $setting = $stored->get($settingLocale . '.' . $groupKey . '.' . $key);
                $state[$groupKey][$key] = $setting && !$setting->trashed()
                    ? (string) $setting->value
                    : $this->serializeDefault($field['default'] ?? null, (string) ($field['type'] ?? 'text'));
            }
        }

        $payload = json_encode(
            ['locale' => $locale, 'settings' => $state],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $secret = (string) config('app.key', '');

        return hash_hmac('sha256', $payload, $secret !== '' ? $secret : 'site-settings-version');
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
