<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const SCHEMA_VERSION = 2;

    private const REPEATER_IDENTITIES = [
        'gallery' => ['items' => 'id'],
        'accordion' => ['items' => 'id'],
        'timeline' => ['items' => 'id'],
    ];

    public function up(): void
    {
        foreach (['page_blocks', 'reusable_blocks'] as $table) {
            if (! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'type')
                || ! Schema::hasColumn($table, 'content')) {
                continue;
            }

            DB::table($table)
                ->where('type', 'layout')
                ->select(['id', 'content'])
                ->orderBy('id')
                ->chunkById(100, function ($records) use ($table): void {
                    foreach ($records as $record) {
                        $content = $this->decodeContent($record->content);
                        if ($content === null) {
                            continue;
                        }

                        $upgraded = $this->upgradeContent($content);
                        if ($upgraded === null || $upgraded === $content) {
                            continue;
                        }

                        $encoded = json_encode(
                            $upgraded,
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        );

                        // The editor remains available while deployments run. Only replace
                        // the exact payload selected above so a save that lands between the
                        // SELECT and UPDATE is never overwritten by this migration.
                        DB::table($table)
                            ->where('id', $record->id)
                            ->where('type', 'layout')
                            ->where('content', $record->content)
                            ->update(['content' => $encoded]);
                    }
                }, 'id');
        }
    }

    public function down(): void
    {
        // Deliberate no-op: v2 adds opaque identities that may already be used
        // by translations and editor state. Removing them cannot faithfully
        // recreate whether a record began as an unversioned or v1 payload, so
        // a destructive rollback would be less safe than retaining metadata.
    }

    /** @return array<string, mixed>|null */
    private function decodeContent(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Add only contract metadata. Authored fields and their order are retained.
     * A future schema is intentionally ignored so rerunning an old migration
     * can never downgrade content produced by a newer application version.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>|null
     */
    private function upgradeContent(array $content): ?array
    {
        $version = $content['schema_version'] ?? null;

        // A payload that already declares the current schema owns its identity
        // values. Leave it byte-for-byte untouched—even when corrupt—so the
        // application's fail-closed repair guard can surface the problem instead
        // of silently changing IDs that translations may already reference.
        if ($version === self::SCHEMA_VERSION) {
            return null;
        }

        if ($version !== null && $version !== 1) {
            return null;
        }
        if (! isset($content['rows']) || ! is_array($content['rows']) || ! array_is_list($content['rows'])) {
            return null;
        }

        foreach ($content['rows'] as $row) {
            if (! is_array($row)
                || ! isset($row['columns'])
                || ! is_array($row['columns'])
                || ! array_is_list($row['columns'])) {
                return null;
            }
            foreach ($row['columns'] as $column) {
                if (! is_array($column)
                    || ! isset($column['elements'])
                    || ! is_array($column['elements'])
                    || ! array_is_list($column['elements'])) {
                    return null;
                }
                foreach ($column['elements'] as $element) {
                    if (! is_array($element)) {
                        return null;
                    }
                    $type = is_string($element['type'] ?? null) ? $element['type'] : '';
                    foreach (self::REPEATER_IDENTITIES[$type] ?? [] as $field => $identityField) {
                        if (! array_key_exists($field, $element)) {
                            continue;
                        }
                        if (! is_array($element[$field]) || ! array_is_list($element[$field])) {
                            return null;
                        }
                        foreach ($element[$field] as $item) {
                            if (! is_array($item)) {
                                return null;
                            }
                        }
                    }
                }
            }
        }

        $rowIds = [];
        $columnIds = [];
        $elementIds = [];
        $subitemIds = [];

        foreach ($content['rows'] as &$row) {
            $row['id'] = $this->uniqueUuid($row['id'] ?? null, $rowIds);
            foreach ($row['columns'] as &$column) {
                $column['id'] = $this->uniqueUuid($column['id'] ?? null, $columnIds);
                foreach ($column['elements'] as &$element) {
                    $element['id'] = $this->uniqueUuid($element['id'] ?? null, $elementIds);
                    $type = is_string($element['type'] ?? null) ? $element['type'] : '';
                    foreach (self::REPEATER_IDENTITIES[$type] ?? [] as $field => $identityField) {
                        if (! is_array($element[$field] ?? null)) {
                            continue;
                        }
                        foreach ($element[$field] as &$item) {
                            $item[$identityField] = $this->uniqueUuid(
                                $item[$identityField] ?? null,
                                $subitemIds
                            );
                        }
                        unset($item);
                    }
                }
                unset($element);
            }
            unset($column);
        }
        unset($row);

        $content['schema_version'] = self::SCHEMA_VERSION;

        return $content;
    }

    /** @param array<string, true> $seen */
    private function uniqueUuid(mixed $candidate, array &$seen): string
    {
        $value = is_string($candidate) ? $candidate : '';
        $identity = strtolower($value);

        if (! $this->isEditorUuid($value) || isset($seen[$identity])) {
            do {
                $value = (string) Str::uuid();
                $identity = strtolower($value);
            } while (isset($seen[$identity]));
        }

        $seen[$identity] = true;

        return $value;
    }

    /**
     * Match the UUID contract used by both visual editors: RFC variant UUIDs
     * with a real version from 1 through 8. Broad UUID-shape checks also accept
     * nil or unsupported version values that the browser must correctly reject.
     */
    private function isEditorUuid(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        ) === 1;
    }
};
