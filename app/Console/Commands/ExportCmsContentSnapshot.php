<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ExportCmsContentSnapshot extends Command
{
    private const DEFAULT_OUTPUT = 'database/seeders/seed-data/cms-content.snapshot.json';

    private const TABLES = [
        'albums',
        'annual_reports',
        'banners',
        'categories',
        'chat_faqs',
        'chat_settings',
        'donation_cause_groups',
        'donation_types',
        'galleries',
        'latest_news',
        'media_assets',
        'notice_boards',
        'page_blocks',
        'page_menus',
        'page_tag_modules',
        'pages',
        'reusable_blocks',
        'seo_metadata',
        'site_settings',
        'splash_screens',
        'tags',
        'team_groups',
        'testimonials',
        'translation_locales',
        'volunteer_causes',
    ];

    private const EXCLUDED_COLUMNS = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
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
        'review_note',
        'review_content_hash',
        'review_requested_at',
        'reviewed_at',
        'ip',
        'click_count',
    ];

    protected $signature = 'cms:snapshot
        {--output= : Repository-relative JSON destination under database/seeders/seed-data}
        {--force : Replace an existing snapshot}';

    protected $description = 'Export a deterministic, Git-safe snapshot of public CMS content';

    public function handle(): int
    {
        try {
            $output = $this->resolveOutputPath((string) ($this->option('output') ?: self::DEFAULT_OUTPUT));
            if (File::exists($output) && ! $this->option('force')) {
                $this->error('Snapshot already exists. Use --force to replace it.');

                return self::FAILURE;
            }

            $raw = $this->readPublicContent();
            $maps = $this->buildIdentityMaps($raw);
            $tables = [];
            $checksums = [];

            foreach (self::TABLES as $table) {
                $records = array_map(
                    fn (array $record): array => $this->normalizeRecord($table, $record, $maps),
                    $raw[$table] ?? []
                );
                usort($records, fn (array $left, array $right): int => strcmp(
                    $this->canonicalJson($left),
                    $this->canonicalJson($right)
                ));

                $tables[$table] = $records;
                $checksums[$table] = hash('sha256', $this->canonicalJson($records));
            }

            $snapshot = [
                'version' => 1,
                'tables' => $tables,
                'checksums' => $checksums,
            ];

            File::put(
                $output,
                json_encode(
                    $snapshot,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                ).PHP_EOL
            );

            $this->info('CMS content snapshot written to '.$this->displayPath($output).'.');
            $this->table(
                ['Table', 'Records'],
                array_map(fn (string $table): array => [$table, count($tables[$table])], self::TABLES)
            );

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveOutputPath(string $requested): string
    {
        $allowed = realpath(database_path('seeders/seed-data'));
        if ($allowed === false) {
            throw new RuntimeException('The seed-data directory is missing.');
        }

        $candidate = $this->isAbsolutePath($requested) ? $requested : base_path($requested);
        $directory = realpath(dirname($candidate));
        if (
            $directory === false
            || ($directory !== $allowed && ! str_starts_with($directory, $allowed.DIRECTORY_SEPARATOR))
        ) {
            throw new RuntimeException('Snapshot output must stay within database/seeders/seed-data.');
        }

        return $directory.DIRECTORY_SEPARATOR.basename($candidate);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function readPublicContent(): array
    {
        $tables = [];

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                $tables[$table] = [];
                continue;
            }

            $query = DB::table($table);
            if (Schema::hasColumn($table, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }
            if ($table === 'site_settings') {
                $query->where('is_public', true);
            }
            if ($table === 'media_assets') {
                $query->where('disk', 'public');
            }

            $tables[$table] = $query->get()->map(fn (object $row): array => (array) $row)->all();
        }

        return $tables;
    }

    private function buildIdentityMaps(array $tables): array
    {
        $maps = [];
        foreach ([
            'albums',
            'banners',
            'categories',
            'donation_cause_groups',
            'page_menus',
            'pages',
            'reusable_blocks',
            'tags',
            'team_groups',
        ] as $table) {
            $maps[$table] = [];
            foreach ($tables[$table] ?? [] as $record) {
                if (isset($record['id'], $record['uuid']) && trim((string) $record['uuid']) !== '') {
                    $maps[$table][(string) $record['id']] = (string) $record['uuid'];
                }
            }
        }

        return $maps;
    }

    private function normalizeRecord(string $table, array $record, array $maps): array
    {
        foreach (self::EXCLUDED_COLUMNS as $column) {
            unset($record[$column]);
        }

        if ($table === 'latest_news') {
            unset($record['email']);
        }
        if ($table === 'notice_boards') {
            unset($record['file_path']);
        }

        foreach ($this->relationMap($table) as $column => [$targetTable, $snapshotColumn]) {
            $value = $record[$column] ?? null;
            unset($record[$column]);
            $record[$snapshotColumn] = $this->resolveRelation($table, $column, $value, $targetTable, $maps);
        }

        if ($table === 'seo_metadata') {
            $seoableId = $record['seoable_id'] ?? null;
            $seoableType = (string) ($record['seoable_type'] ?? '');
            unset($record['seoable_id']);

            $targetTable = match ($seoableType) {
                'App\\Models\\Page' => 'pages',
                'App\\Models\\Category' => 'categories',
                default => null,
            };
            if ($seoableId !== null && $targetTable === null) {
                throw new RuntimeException("Unsupported SEO relation type [{$seoableType}].");
            }
            $record['seoable_uuid'] = $targetTable === null
                ? null
                : $this->resolveRelation($table, 'seoable_id', $seoableId, $targetTable, $maps);
        }

        return $this->sortRecursively($record);
    }

    private function relationMap(string $table): array
    {
        return match ($table) {
            'banners', 'galleries' => [
                'album_id' => ['albums', 'album_uuid'],
            ],
            'categories', 'tags' => [
                'banner_id' => ['banners', 'banner_uuid'],
            ],
            'donation_types' => [
                'donation_cause_group_id' => ['donation_cause_groups', 'donation_cause_group_uuid'],
            ],
            'latest_news' => [
                'category_id' => ['categories', 'category_uuid'],
                'team_group_id' => ['team_groups', 'team_group_uuid'],
            ],
            'page_blocks' => [
                'page_id' => ['pages', 'page_uuid'],
                'reusable_block_id' => ['reusable_blocks', 'reusable_block_uuid'],
            ],
            'page_menus' => [
                'parent_id' => ['page_menus', 'parent_uuid'],
                'banner_id' => ['banners', 'banner_uuid'],
            ],
            'page_tag_modules' => [
                'page_id' => ['pages', 'page_uuid'],
                'tag_id' => ['tags', 'tag_uuid'],
            ],
            'pages' => [
                'category_id' => ['categories', 'category_uuid'],
                'banner_id' => ['banners', 'banner_uuid'],
            ],
            default => [],
        };
    }

    private function resolveRelation(
        string $sourceTable,
        string $sourceColumn,
        mixed $value,
        string $targetTable,
        array $maps
    ): ?string {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $key = (string) $value;
        if (! isset($maps[$targetTable][$key])) {
            throw new RuntimeException(
                "Cannot export {$sourceTable}.{$sourceColumn}: related {$targetTable} record [{$key}] is missing."
            );
        }

        return $maps[$targetTable][$key];
    }

    private function sortRecursively(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $nested) {
            if (is_array($nested)) {
                $value[$key] = $this->sortRecursively($nested);
            }
        }

        return $value;
    }

    private function canonicalJson(array $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    private function displayPath(string $path): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base)
            ? str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($base)))
            : $path;
    }
}
