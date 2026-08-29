<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CmsContentSnapshotSeeder extends Seeder
{
    private const SNAPSHOT = 'seeders/seed-data/cms-content.snapshot.json';

    private const TABLE_ORDER = [
        'translation_locales',
        'albums',
        'banners',
        'categories',
        'team_groups',
        'tags',
        'reusable_blocks',
        'pages',
        'page_blocks',
        'page_menus',
        'page_tag_modules',
        'media_assets',
        'donation_cause_groups',
        'donation_types',
        'galleries',
        'latest_news',
        'testimonials',
        'annual_reports',
        'notice_boards',
        'splash_screens',
        'site_settings',
        'chat_settings',
        'chat_faqs',
        'seo_metadata',
        'volunteer_causes',
    ];

    private const UUID_TABLES = [
        'albums',
        'banners',
        'categories',
        'chat_faqs',
        'donation_cause_groups',
        'donation_types',
        'galleries',
        'media_assets',
        'page_blocks',
        'page_menus',
        'page_tag_modules',
        'pages',
        'reusable_blocks',
        'splash_screens',
        'tags',
        'team_groups',
        'testimonials',
    ];

    public function run(): void
    {
        $snapshot = $this->readAndValidateSnapshot();

        DB::transaction(function () use ($snapshot): void {
            $deferredParents = [];

            foreach (self::TABLE_ORDER as $table) {
                if (! Schema::hasTable($table)) {
                    throw new RuntimeException("Cannot restore CMS snapshot: table [{$table}] is missing.");
                }

                foreach ($snapshot['tables'][$table] as $sourceRecord) {
                    $record = $sourceRecord;
                    $parentUuid = null;
                    if ($table === 'page_menus') {
                        $parentUuid = $record['parent_uuid'] ?? null;
                        unset($record['parent_uuid']);
                        $record['parent_id'] = null;
                    }

                    $record = $this->restoreRelations($table, $record);
                    $identity = $this->identityFor($table, $record);
                    DB::table($table)->updateOrInsert($identity, $record);

                    if ($table === 'page_menus' && $parentUuid !== null) {
                        $deferredParents[] = [
                            'uuid' => $record['uuid'],
                            'parent_uuid' => $parentUuid,
                        ];
                    }
                }

                if ($table === 'page_menus') {
                    foreach ($deferredParents as $relation) {
                        DB::table('page_menus')
                            ->where('uuid', $relation['uuid'])
                            ->update([
                                'parent_id' => $this->idForUuid('page_menus', $relation['parent_uuid']),
                            ]);
                    }
                }
            }
        });
    }

    private function readAndValidateSnapshot(): array
    {
        $path = database_path(self::SNAPSHOT);
        if (! File::isFile($path) || ! File::isReadable($path)) {
            throw new RuntimeException('The CMS content snapshot is missing or unreadable.');
        }

        $snapshot = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        if (
            ! is_array($snapshot)
            || ($snapshot['version'] ?? null) !== 1
            || ! is_array($snapshot['tables'] ?? null)
            || ! is_array($snapshot['checksums'] ?? null)
        ) {
            throw new RuntimeException('The CMS content snapshot format is invalid.');
        }

        $unexpected = array_diff(array_keys($snapshot['tables']), self::TABLE_ORDER);
        $missing = array_diff(self::TABLE_ORDER, array_keys($snapshot['tables']));
        if ($unexpected !== [] || $missing !== []) {
            throw new RuntimeException('The CMS content snapshot table manifest is invalid.');
        }

        foreach (self::TABLE_ORDER as $table) {
            $records = $snapshot['tables'][$table];
            if (! is_array($records)) {
                throw new RuntimeException("The CMS content snapshot table [{$table}] is invalid.");
            }

            $expected = $snapshot['checksums'][$table] ?? null;
            $actual = hash('sha256', $this->canonicalJson($records));
            if (! is_string($expected) || ! hash_equals($expected, $actual)) {
                throw new RuntimeException("The CMS content snapshot checksum failed for [{$table}].");
            }

            foreach ($records as $record) {
                if (! is_array($record)) {
                    throw new RuntimeException("The CMS content snapshot contains an invalid [{$table}] record.");
                }
                $this->assertSafeRecord($table, $record);
            }
        }

        return $snapshot;
    }

    private function assertSafeRecord(string $table, array $record): void
    {
        $forbidden = [
            'id',
            'created_by',
            'updated_by',
            'deleted_by',
            'created_by_admin_id',
            'updated_by_admin_id',
            'uploaded_by',
            'publish_by',
            'published_by',
            'review_requested_by',
            'reviewed_by',
            'ip',
            'click_count',
        ];

        foreach (array_keys($record) as $key) {
            if (in_array($key, $forbidden, true) || str_ends_with($key, '_id')) {
                throw new RuntimeException("The CMS content snapshot contains forbidden field [{$table}.{$key}].");
            }
        }
    }

    private function restoreRelations(string $table, array $record): array
    {
        foreach ($this->relationMap($table) as $snapshotColumn => [$targetTable, $databaseColumn]) {
            $uuid = $record[$snapshotColumn] ?? null;
            unset($record[$snapshotColumn]);
            $record[$databaseColumn] = $uuid === null ? null : $this->idForUuid($targetTable, (string) $uuid);
        }

        if ($table === 'seo_metadata') {
            $uuid = $record['seoable_uuid'] ?? null;
            unset($record['seoable_uuid']);
            if ($uuid === null) {
                $record['seoable_id'] = null;
            } else {
                $target = match ((string) ($record['seoable_type'] ?? '')) {
                    'App\\Models\\Page' => 'pages',
                    'App\\Models\\Category' => 'categories',
                    default => throw new RuntimeException('The CMS content snapshot contains an unsupported SEO relation.'),
                };
                $record['seoable_id'] = $this->idForUuid($target, (string) $uuid);
            }
        }

        return $record;
    }

    private function relationMap(string $table): array
    {
        return match ($table) {
            'banners', 'galleries' => [
                'album_uuid' => ['albums', 'album_id'],
            ],
            'categories', 'tags' => [
                'banner_uuid' => ['banners', 'banner_id'],
            ],
            'donation_types' => [
                'donation_cause_group_uuid' => ['donation_cause_groups', 'donation_cause_group_id'],
            ],
            'latest_news' => [
                'category_uuid' => ['categories', 'category_id'],
                'team_group_uuid' => ['team_groups', 'team_group_id'],
            ],
            'page_blocks' => [
                'page_uuid' => ['pages', 'page_id'],
                'reusable_block_uuid' => ['reusable_blocks', 'reusable_block_id'],
            ],
            'page_tag_modules' => [
                'page_uuid' => ['pages', 'page_id'],
                'tag_uuid' => ['tags', 'tag_id'],
            ],
            'page_menus' => [
                'banner_uuid' => ['banners', 'banner_id'],
            ],
            'pages' => [
                'category_uuid' => ['categories', 'category_id'],
                'banner_uuid' => ['banners', 'banner_id'],
            ],
            default => [],
        };
    }

    private function identityFor(string $table, array $record): array
    {
        if (in_array($table, self::UUID_TABLES, true)) {
            return $this->onlyIdentity($table, $record, ['uuid']);
        }

        return match ($table) {
            'annual_reports', 'notice_boards' => trim((string) ($record['translation_key'] ?? '')) !== ''
                ? $this->onlyIdentity($table, $record, ['translation_key', 'language'])
                : $this->onlyIdentity($table, $record, ['slug', 'language']),
            'chat_settings' => $this->onlyIdentity($table, $record, ['locale']),
            'latest_news' => $this->onlyIdentity($table, $record, ['name', 'language']),
            'seo_metadata' => $record['seoable_id'] !== null
                ? $this->onlyIdentity($table, $record, ['seoable_type', 'seoable_id', 'locale'])
                : $this->onlyIdentity($table, $record, ['route_name', 'route_path', 'locale']),
            'site_settings' => $this->onlyIdentity($table, $record, ['group', 'key', 'locale']),
            'translation_locales' => $this->onlyIdentity($table, $record, ['locale']),
            'volunteer_causes' => $this->onlyIdentity($table, $record, ['name']),
            default => throw new RuntimeException("No CMS snapshot identity is configured for [{$table}]."),
        };
    }

    private function onlyIdentity(string $table, array $record, array $columns): array
    {
        $identity = [];
        foreach ($columns as $column) {
            if (! array_key_exists($column, $record) || $record[$column] === null || $record[$column] === '') {
                throw new RuntimeException("CMS snapshot identity [{$table}.{$column}] is missing.");
            }
            $identity[$column] = $record[$column];
        }

        return $identity;
    }

    private function idForUuid(string $table, string $uuid): int
    {
        $id = DB::table($table)->where('uuid', $uuid)->value('id');
        if ($id === null) {
            throw new RuntimeException("Cannot restore CMS snapshot: [{$table}] UUID [{$uuid}] is missing.");
        }

        return (int) $id;
    }

    private function canonicalJson(array $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
