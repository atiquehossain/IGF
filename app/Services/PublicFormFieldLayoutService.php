<?php

namespace App\Services;

class PublicFormFieldLayoutService
{
    /**
     * Return a deterministic, allow-listed public form layout.
     *
     * Stored JSON is treated as editorial input rather than trusted schema:
     * unknown/duplicate keys are discarded, locked fields are restored, and
     * omitted fields are appended from the configured defaults.
     */
    public function normalize(array $field, mixed $value): array
    {
        $allowed = is_array($field['allowed_fields'] ?? null)
            ? $field['allowed_fields']
            : [];
        $defaults = collect(is_array($field['default'] ?? null) ? $field['default'] : [])
            ->filter(fn ($item): bool => is_array($item) && is_string($item['key'] ?? null))
            ->keyBy('key');
        $submitted = is_array($value) ? $value : [];
        $orderedKeys = [];

        foreach ($submitted as $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = $item['key'] ?? null;
            if (!is_string($key) || !array_key_exists($key, $allowed) || in_array($key, $orderedKeys, true)) {
                continue;
            }

            $orderedKeys[] = $key;
        }

        foreach (array_keys($allowed) as $key) {
            if (!in_array($key, $orderedKeys, true)) {
                $orderedKeys[] = $key;
            }
        }

        $submittedByKey = collect($submitted)
            ->filter(fn ($item): bool => is_array($item) && is_string($item['key'] ?? null))
            ->keyBy('key');

        return collect($orderedKeys)->map(function (string $key) use ($allowed, $defaults, $submittedByKey): array {
            $rules = is_array($allowed[$key] ?? null) ? $allowed[$key] : [];
            $default = (array) ($defaults->get($key) ?? []);
            $item = (array) ($submittedByKey->get($key) ?? []);
            $alwaysVisible = (bool) ($rules['always_visible'] ?? false);
            $alwaysRequired = (bool) ($rules['always_required'] ?? false);
            $canRequire = (bool) ($rules['can_require'] ?? false);
            $enabled = $alwaysVisible || $this->boolean($item['enabled'] ?? ($default['enabled'] ?? true));
            $required = $alwaysRequired || ($enabled
                && $canRequire
                && $this->boolean($item['required'] ?? ($default['required'] ?? false)));

            return [
                'key' => $key,
                'enabled' => $enabled,
                'required' => $required,
            ];
        })->values()->all();
    }

    public function state(array $layout, string $key): array
    {
        foreach ($layout as $item) {
            if (($item['key'] ?? null) === $key) {
                return $item;
            }
        }

        return ['key' => $key, 'enabled' => false, 'required' => false];
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
