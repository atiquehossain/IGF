<?php

namespace App\Services;

use App\Models\SiteSetting;

class SiteSettingVersionService
{
    public function current(bool $lockForUpdate = false): string
    {
        $query = SiteSetting::withTrashed()
            ->where('locale', '*')
            ->orderBy('group')
            ->orderBy('key');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $stored = $query->get()->keyBy(
            fn (SiteSetting $setting): string => $setting->group . '.' . $setting->key
        );
        $state = [];

        foreach (config('site-settings.groups', []) as $groupKey => $group) {
            foreach ($group['fields'] ?? [] as $key => $field) {
                if (($field['localized'] ?? false) === true) {
                    continue;
                }

                $setting = $stored->get($groupKey . '.' . $key);
                $state[$groupKey][$key] = $setting && !$setting->trashed()
                    ? (string) $setting->value
                    : $this->serializeDefault($field['default'] ?? null, (string) ($field['type'] ?? 'text'));
            }
        }

        $payload = json_encode(
            $state,
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
