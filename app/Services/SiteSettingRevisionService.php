<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\SiteSetting;
use App\Models\SiteSettingRevision;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SiteSettingRevisionService
{
    private const SNAPSHOT_FORMAT = 1;

    public function __construct(private SiteSettingService $settings)
    {
    }

    public function recentFor(string $locale, int $limit = 12): Collection
    {
        return SiteSettingRevision::query()
            ->with('changedBy:id,username')
            ->where('locale', $locale)
            ->latest('id')
            ->limit(max(1, min($limit, 50)))
            ->get();
    }

    public function capture(string $locale, string $reason, ?Admin $actor): SiteSettingRevision
    {
        $schema = config('site-settings.groups', []);
        $stored = SiteSetting::withTrashed()
            ->whereIn('locale', [$locale, '*'])
            ->orderBy('locale')
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->keyBy(fn (SiteSetting $setting): string => $this->rowKey(
                (string) $setting->group,
                (string) $setting->key,
                (string) $setting->locale
            ));
        $effective = $this->settings->values($locale);
        $snapshotSettings = [];
        $capturedFields = [];

        foreach ($schema as $groupKey => $group) {
            foreach ($group['fields'] ?? [] as $key => $field) {
                $path = $groupKey . '.' . $key;
                $settingLocale = ($field['localized'] ?? false) ? $locale : '*';
                /** @var SiteSetting|null $setting */
                $setting = $stored->get($this->rowKey($groupKey, $key, $settingLocale));
                $isStored = $setting !== null && !$setting->trashed();

                $snapshotSettings[$groupKey][$key] = [
                    'stored' => $isStored,
                    'value' => $isStored ? $setting->value : null,
                    'effective' => data_get($effective, $path),
                ];
                $capturedFields[] = $path;
            }
        }

        return SiteSettingRevision::query()->create([
            'uuid' => (string) Str::uuid(),
            'locale' => $locale,
            'snapshot' => [
                'format' => self::SNAPSHOT_FORMAT,
                'locale' => $locale,
                'captured_fields' => $capturedFields,
                'settings' => $snapshotSettings,
            ],
            'reason' => Str::limit(trim($reason) ?: 'Before website settings changed', 255, ''),
            'changed_by' => $actor?->getKey(),
            'created_at' => now(),
        ]);
    }

    /**
     * Restore only current, schema-owned setting values. Snapshot data can
     * never select a different group, key, language, type, or public status.
     */
    public function restore(SiteSettingRevision $revision, ?Admin $actor): SiteSettingRevision
    {
        $revision = SiteSettingRevision::query()
            ->whereKey($revision->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        $snapshot = $revision->snapshot;

        abort_unless(is_array($snapshot), 409, 'This website restore point is unreadable.');
        abort_unless((int) ($snapshot['format'] ?? 0) === self::SNAPSHOT_FORMAT, 409, 'This website restore point uses an unsupported format.');
        abort_unless(hash_equals((string) $revision->locale, (string) ($snapshot['locale'] ?? '')), 409, 'This website restore point has an invalid language scope.');
        abort_unless(is_array($snapshot['settings'] ?? null), 409, 'This website restore point has no settings snapshot.');

        $capturedFields = collect($snapshot['captured_fields'] ?? [])
            ->filter(fn ($path): bool => is_string($path))
            ->flip();
        $actorId = $actor?->getKey();

        foreach (config('site-settings.groups', []) as $groupKey => $group) {
            foreach ($group['fields'] ?? [] as $key => $field) {
                $path = $groupKey . '.' . $key;
                if (!$capturedFields->has($path)) {
                    // A setting introduced after this revision was captured is
                    // outside the historical snapshot and remains untouched.
                    continue;
                }

                $entry = $snapshot['settings'][$groupKey][$key] ?? null;
                abort_unless(is_array($entry) && is_bool($entry['stored'] ?? null), 409, 'This website restore point is incomplete.');

                $settingLocale = ($field['localized'] ?? false) ? (string) $revision->locale : '*';
                $setting = SiteSetting::withTrashed()
                    ->where('group', $groupKey)
                    ->where('key', $key)
                    ->where('locale', $settingLocale)
                    ->lockForUpdate()
                    ->first();

                if (!$entry['stored']) {
                    if ($setting && !$setting->trashed()) {
                        $setting->delete();
                    }

                    continue;
                }

                abort_unless(is_scalar($entry['value'] ?? null) || ($entry['value'] ?? null) === null, 409, 'This website restore point contains an invalid setting value.');
                $setting ??= new SiteSetting([
                    'group' => $groupKey,
                    'key' => $key,
                    'locale' => $settingLocale,
                ]);
                if ($setting->exists && $setting->trashed()) {
                    $setting->restore();
                }

                $setting->fill([
                    'value' => (string) ($entry['value'] ?? ''),
                    'type' => ($field['type'] ?? 'text') === 'faq_list'
                        ? 'json'
                        : (in_array($field['type'] ?? null, ['boolean', 'integer', 'float'], true) ? $field['type'] : 'text'),
                    'is_public' => (bool) ($field['public'] ?? false),
                    'created_by' => $setting->exists ? $setting->created_by : $actorId,
                    'updated_by' => $actorId,
                ])->save();
            }
        }

        return $revision;
    }

    public function diffsFor(Collection $revisions, array $currentValues): Collection
    {
        return $revisions->mapWithKeys(fn (SiteSettingRevision $revision): array => [
            (string) $revision->uuid => $this->diffAgainstValues($revision, $currentValues),
        ]);
    }

    /** @return list<array{path: string, label: string, before: string, after: string}> */
    public function diffAgainstValues(SiteSettingRevision $revision, array $currentValues): array
    {
        $snapshot = (array) $revision->snapshot;
        $snapshotSettings = (array) ($snapshot['settings'] ?? []);
        $changes = [];

        foreach (config('site-settings.groups', []) as $groupKey => $group) {
            foreach ($group['fields'] ?? [] as $key => $field) {
                $entry = $snapshotSettings[$groupKey][$key] ?? null;
                if (!is_array($entry) || !array_key_exists('effective', $entry)) {
                    continue;
                }

                $before = $entry['effective'];
                $after = data_get($currentValues, $groupKey . '.' . $key);
                if ($this->canonical($before) === $this->canonical($after)) {
                    continue;
                }

                $changes[] = [
                    'path' => $groupKey . '.' . $key,
                    'label' => (string) ($group['label'] ?? $groupKey) . ' — ' . (string) ($field['label'] ?? $key),
                    'before' => $this->displayValue($before),
                    'after' => $this->displayValue($after),
                ];
            }
        }

        return $changes;
    }

    private function rowKey(string $group, string $key, string $locale): string
    {
        return $group . "\0" . $key . "\0" . $locale;
    }

    private function canonical(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function displayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Enabled' : 'Disabled';
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return count($value) . ' ' . Str::plural('item', count($value));
            }

            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $display = trim((string) ($value ?? ''));

        return $display === '' ? '(empty)' : Str::limit($display, 180);
    }
}
