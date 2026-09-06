<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use JsonException;
use stdClass;

final class SiteSettingsPayload
{
    /**
     * Normal customizer submissions are currently under 60 KB. This limit is
     * deliberately generous enough for long translated copy and 50 FAQs, but
     * small enough to fail with a useful message before a server POST limit is
     * reached.
     */
    public const MAX_BYTES = 4 * 1024 * 1024;

    public function decode(string $payload, array $schema): array
    {
        if ($payload === '') {
            $this->fail('settings_payload', 'The website changes could not be prepared. Reload the customizer and try again.');
        }

        if (strlen($payload) > self::MAX_BYTES) {
            $this->fail('settings_payload', 'These website changes are unusually large. Shorten the longest text or FAQ answers, then try again.');
        }

        try {
            $decoded = json_decode($payload, false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->fail('settings_payload', 'The website changes arrived in an invalid format. Reload the customizer and try again.');
        }

        if (!$decoded instanceof stdClass) {
            $this->fail('settings_payload', 'The website changes arrived in an invalid format. Reload the customizer and try again.');
        }

        $settings = [];
        foreach (get_object_vars($decoded) as $groupKey => $group) {
            if (!$group instanceof stdClass) {
                $this->fail('settings_payload', 'The website changes arrived in an invalid format. Reload the customizer and try again.');
            }

            foreach (get_object_vars($group) as $key => $value) {
                $settings[$groupKey][$key] = $this->toArray($value);
            }
        }

        return $this->validatedShape($settings, $schema, 'settings_payload');
    }

    /**
     * The non-JavaScript form remains supported. Requiring its complete shape
     * turns a max_input_vars truncation into a visible validation error instead
     * of clearing every setting that PHP silently omitted.
     */
    public function validateFormInput(mixed $settings, array $schema): array
    {
        return $this->validatedShape($settings, $schema, 'settings');
    }

    private function validatedShape(mixed $settings, array $schema, string $errorKey): array
    {
        if (!is_array($settings)) {
            $this->fail($errorKey, 'The website settings were incomplete. Reload the customizer and try again.');
        }

        $expectedGroups = array_keys($schema);
        $submittedGroups = array_keys($settings);
        if (array_diff($submittedGroups, $expectedGroups) !== []) {
            $this->fail($errorKey, 'The submission contains settings that are not part of this customizer. Reload the page and try again.');
        }

        if (array_diff($expectedGroups, $submittedGroups) !== []) {
            $this->fail($errorKey, 'Some website settings were missing, so nothing was saved. Reload the customizer and try again.');
        }

        $safe = [];
        foreach ($schema as $groupKey => $group) {
            $submittedFields = $settings[$groupKey] ?? null;
            if (!is_array($submittedFields)) {
                $this->fail($errorKey, 'The website settings were incomplete. Reload the customizer and try again.');
            }

            $expectedFields = array_keys($group['fields'] ?? []);
            $submittedKeys = array_keys($submittedFields);
            if (array_diff($submittedKeys, $expectedFields) !== []) {
                $this->fail($errorKey, 'The submission contains settings that are not part of this customizer. Reload the page and try again.');
            }

            if (array_diff($expectedFields, $submittedKeys) !== []) {
                $this->fail($errorKey, 'Some website settings were missing, so nothing was saved. Reload the customizer and try again.');
            }

            foreach ($expectedFields as $key) {
                $safe[$groupKey][$key] = $submittedFields[$key];
            }
        }

        return $safe;
    }

    private function toArray(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            return array_map(fn (mixed $item): mixed => $this->toArray($item), get_object_vars($value));
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->toArray($item), $value);
        }

        return $value;
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
