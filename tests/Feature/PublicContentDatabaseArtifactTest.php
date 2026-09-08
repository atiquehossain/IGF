<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

final class PublicContentDatabaseArtifactTest extends TestCase
{
    private const ARTIFACT = 'seeders/seed-data/igf-public-content.sqlite';

    private const SNAPSHOT = 'seeders/seed-data/cms-content.snapshot.json';

    private const CHECKSUM = 'seeders/seed-data/igf-public-content.sqlite.sha256';

    /** @var list<string> */
    private const STRUCTURAL_TABLES = [
        'auth_menus',
        'districts',
        'divisions',
        'igf_migration_20260908_010_ownership',
        'igf_migration_20260908_020_ownership',
        'igf_migration_20260908_030_ownership',
        'menu_actions',
        'migrations',
        'roles',
        'seo_redirect_locks',
        'upazilas',
    ];

    /** @var list<string> */
    private const PRIVATE_CMS_COLUMNS = [
        'actor_admin_id',
        'actor_name_snapshot',
        'author_admin_id',
        'created_by',
        'created_by_admin_id',
        'deleted_at',
        'deleted_by',
        'ip',
        'ip_address',
        'ip_hash',
        'publish_by',
        'published_by',
        'review_content_hash',
        'review_note',
        'review_requested_at',
        'review_requested_by',
        'reviewed_at',
        'reviewed_by',
        'updated_by',
        'updated_by_admin_id',
        'uploaded_by',
    ];

    /** @var list<string> */
    private const SENSITIVE_TABLES = [
        'admins',
        'users',
        'password_resets',
        'oauth_access_tokens',
        'oauth_auth_codes',
        'oauth_clients',
        'oauth_device_codes',
        'oauth_refresh_tokens',
        'donations',
        'donation_allocations',
        'ssl_commerz_transactions',
        'sponsorships',
        'contact_messages',
        'comments',
        'chat_conversations',
        'chat_messages',
        'chat_audits',
        'subscribers',
        'likes',
        'you_tube_watches',
        'job_applications',
        'job_application_answers',
        'job_application_documents',
        'job_application_notes',
        'job_application_scores',
        'job_application_status_events',
        'workshop_registrations',
        'workshop_registration_answers',
        'workshop_registration_documents',
        'workshop_registration_notes',
        'workshop_registration_status_events',
        'application_import_batches',
        'application_import_rows',
        'volunteers',
        'admin_audit_events',
        'admin_listing_preferences',
        'page_revisions',
        'site_setting_revisions',
        'seo_metadata_revisions',
        'seo_audit_runs',
        'seo_audit_issues',
        'seo_audit_alerts',
        'seo_audit_ignore_rules',
        'seo_not_found_hits',
        'editor_drafts',
        'failed_jobs',
        'private_file_cleanup_jobs',
    ];

    public function test_public_content_database_exists_and_passes_sqlite_integrity_checks(): void
    {
        $artifactPath = database_path(self::ARTIFACT);

        $this->assertFileExists($artifactPath, 'The Git-safe public-content SQLite artifact is missing.');

        $database = $this->readOnlyConnection($artifactPath);

        $this->assertSame(
            ['ok'],
            $database->query('PRAGMA integrity_check')->fetchAll(PDO::FETCH_COLUMN),
            'The public-content SQLite artifact failed its integrity check.'
        );
        $this->assertSame(
            [],
            $database->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC),
            'The public-content SQLite artifact contains foreign-key violations.'
        );
        $this->assertSame(
            0,
            (int) $database->query('PRAGMA freelist_count')->fetchColumn(),
            'The public-content SQLite artifact contains free pages that may retain deleted data.'
        );
    }

    public function test_public_content_database_contains_no_sensitive_or_operational_rows(): void
    {
        $database = $this->readOnlyConnection(database_path(self::ARTIFACT));

        foreach (self::SENSITIVE_TABLES as $table) {
            $this->assertTableExists($database, $table);
            $this->assertSame(
                0,
                $this->rowCount($database, $table),
                "Sensitive or operational table [{$table}] must be empty in the public artifact."
            );
        }
    }

    public function test_public_content_database_has_no_unclassified_nonempty_tables(): void
    {
        $database = $this->readOnlyConnection(database_path(self::ARTIFACT));
        $snapshot = $this->snapshot();
        $allowed = array_fill_keys([
            ...array_keys($snapshot['tables']),
            ...self::STRUCTURAL_TABLES,
        ], true);

        $tables = $database->query(
            "SELECT name FROM sqlite_master
             WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
             ORDER BY name"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $this->assertIsString($table);
            if (isset($allowed[$table])) {
                continue;
            }

            $this->assertSame(
                0,
                $this->rowCount($database, $table),
                "Unclassified table [{$table}] must stay empty until it is explicitly reviewed."
            );
        }

        $this->assertSame(
            [['id' => 1]],
            $database->query('SELECT id FROM seo_redirect_locks ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
            'The artifact must contain only the required SEO redirect mutex row.'
        );
        $this->assertSame(8, $this->rowCount($database, 'divisions'));
        $this->assertSame(64, $this->rowCount($database, 'districts'));
        $this->assertSame(495, $this->rowCount($database, 'upazilas'));
        $this->assertSame(
            ['latest_news.division_id'],
            $database->query('SELECT asset FROM igf_migration_20260908_010_ownership ORDER BY asset')
                ->fetchAll(PDO::FETCH_COLUMN)
        );
        $this->assertSame(
            [
                'districts.description',
                'districts.description_bn',
                'districts.hero_image',
                'districts.hero_image_alt',
                'districts.index.slug_unique',
                'districts.slug',
                'divisions.description',
                'divisions.description_bn',
                'divisions.index.slug_unique',
                'divisions.slug',
                'latest_news.district_id',
                'notice_boards.district_id',
                'notice_boards.division_id',
                'notice_boards.index.district_public',
                'notice_boards.index.division_public',
            ],
            $database->query('SELECT asset FROM igf_migration_20260908_020_ownership ORDER BY asset')
                ->fetchAll(PDO::FETCH_COLUMN)
        );
        $this->assertSame(
            ['districts.hero_image_alt_bn'],
            $database->query('SELECT asset FROM igf_migration_20260908_030_ownership ORDER BY asset')
                ->fetchAll(PDO::FETCH_COLUMN)
        );
        $this->assertSame(
            0,
            (int) $database->query(
                'SELECT COUNT(*) FROM districts d LEFT JOIN divisions v ON v.id = d.division_id WHERE v.id IS NULL'
            )->fetchColumn(),
            'The artifact contains a district without its division.'
        );
        $this->assertSame(
            0,
            (int) $database->query(
                'SELECT COUNT(*) FROM upazilas u LEFT JOIN districts d ON d.id = u.district_id WHERE d.id IS NULL'
            )->fetchColumn(),
            'The artifact contains an upazila without its district.'
        );
    }

    public function test_public_content_database_contains_only_public_cms_fields(): void
    {
        $database = $this->readOnlyConnection(database_path(self::ARTIFACT));
        $this->assertPublicOnlyRows($database);
    }

    public function test_public_content_database_has_nullable_member_and_activity_geography_relationships(): void
    {
        $database = $this->readOnlyConnection(database_path(self::ARTIFACT));
        $districtColumns = collect(
            $database->query('PRAGMA table_info("districts")')->fetchAll(PDO::FETCH_ASSOC)
        );
        $banglaAltColumn = $districtColumns->firstWhere('name', 'hero_image_alt_bn');
        $this->assertIsArray($banglaAltColumn, 'districts.hero_image_alt_bn is missing from the public artifact.');
        $this->assertSame(0, (int) $banglaAltColumn['notnull']);

        $columns = $database->query('PRAGMA table_info("latest_news")')->fetchAll(PDO::FETCH_ASSOC);
        $divisionColumn = collect($columns)->firstWhere('name', 'division_id');
        $districtColumn = collect($columns)->firstWhere('name', 'district_id');

        $this->assertIsArray($divisionColumn, 'latest_news.division_id is missing from the public artifact.');
        $this->assertSame(0, (int) $divisionColumn['notnull'], 'latest_news.division_id must remain nullable.');
        $this->assertIsArray($districtColumn, 'latest_news.district_id is missing from the public artifact.');
        $this->assertSame(0, (int) $districtColumn['notnull'], 'latest_news.district_id must remain nullable.');

        $foreignKeys = $database->query('PRAGMA foreign_key_list("latest_news")')->fetchAll(PDO::FETCH_ASSOC);
        $divisionForeignKey = collect($foreignKeys)->firstWhere('from', 'division_id');
        $districtForeignKey = collect($foreignKeys)->firstWhere('from', 'district_id');

        $this->assertIsArray($divisionForeignKey, 'latest_news.division_id has no foreign key.');
        $this->assertSame('divisions', $divisionForeignKey['table']);
        $this->assertSame('id', $divisionForeignKey['to']);
        $this->assertSame('SET NULL', strtoupper((string) $divisionForeignKey['on_delete']));
        $this->assertIsArray($districtForeignKey, 'latest_news.district_id has no foreign key.');
        $this->assertSame('districts', $districtForeignKey['table']);
        $this->assertSame('SET NULL', strtoupper((string) $districtForeignKey['on_delete']));
        $this->assertSame(
            0,
            (int) $database->query(
                'SELECT COUNT(*) FROM latest_news n'
                .' LEFT JOIN divisions d ON d.id = n.division_id'
                .' WHERE n.division_id IS NOT NULL AND d.id IS NULL'
            )->fetchColumn(),
            'The artifact contains a team-member division reference that cannot be resolved.'
        );
        $this->assertSame(
            0,
            (int) $database->query(
                'SELECT COUNT(*) FROM latest_news n'
                .' LEFT JOIN districts d ON d.id = n.district_id'
                .' WHERE n.district_id IS NOT NULL AND d.id IS NULL'
            )->fetchColumn(),
            'The artifact contains a team-member district reference that cannot be resolved.'
        );

        $noticeColumns = collect($database->query('PRAGMA table_info("notice_boards")')->fetchAll(PDO::FETCH_ASSOC));
        $noticeDivisionColumn = $noticeColumns->firstWhere('name', 'division_id');
        $noticeDistrictColumn = $noticeColumns->firstWhere('name', 'district_id');
        $this->assertIsArray($noticeDivisionColumn);
        $this->assertIsArray($noticeDistrictColumn);
        $this->assertSame(0, (int) $noticeDivisionColumn['notnull']);
        $this->assertSame(0, (int) $noticeDistrictColumn['notnull']);
        $noticeForeignKeys = collect($database->query('PRAGMA foreign_key_list("notice_boards")')->fetchAll(PDO::FETCH_ASSOC));
        $noticeDivisionForeignKey = $noticeForeignKeys->firstWhere('from', 'division_id');
        $noticeDistrictForeignKey = $noticeForeignKeys->firstWhere('from', 'district_id');
        $this->assertIsArray($noticeDivisionForeignKey);
        $this->assertIsArray($noticeDistrictForeignKey);
        $this->assertSame('divisions', $noticeDivisionForeignKey['table']);
        $this->assertSame('districts', $noticeDistrictForeignKey['table']);
    }

    public function test_public_content_database_restores_the_regional_heroes_showcase_semantically(): void
    {
        $database = $this->readOnlyConnection(database_path(self::ARTIFACT));
        $block = $database->query(
            "SELECT b.content, p.uuid AS page_uuid, p.language AS page_language
             FROM page_blocks b
             INNER JOIN pages p ON p.id = b.page_id
             WHERE b.uuid = '69000000-0000-4000-8000-00000000d001'"
        )->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($block, 'The regional heroes demo block is missing from the artifact.');
        $this->assertSame('22222222-2222-4222-8222-000000000010', $block['page_uuid']);
        $this->assertSame('en', $block['page_language']);

        $content = json_decode((string) $block['content'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('heroes_showcase', $content['team_presentation'] ?? null);
        $this->assertSame('manual', $content['selection_mode'] ?? null);
        $this->assertIsArray($content['selected_items'] ?? null);
        $this->assertCount(10, $content['selected_items']);

        $selectedIds = array_values(array_map('intval', $content['selected_items']));
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $statement = $database->prepare(
            "SELECT n.id, n.name, n.qualification, n.biography, n.image, n.path, n.social_links,
                    g.slug AS group_slug, d.name AS division_name, x.name AS district_name
             FROM latest_news n
             LEFT JOIN team_groups g ON g.id = n.team_group_id
             LEFT JOIN divisions d ON d.id = n.division_id
             LEFT JOIN districts x ON x.id = n.district_id
             WHERE n.id IN ({$placeholders})"
        );
        $statement->execute($selectedIds);
        $members = collect($statement->fetchAll(PDO::FETCH_ASSOC))->keyBy('id');

        $this->assertCount(10, $members);
        $this->assertSame(
            ['Barishal', 'Chattogram', 'Dhaka', 'Khulna', 'Mymensingh', 'Rajshahi', 'Rangpur', 'Rangpur', 'Rangpur', 'Sylhet'],
            $members->pluck('division_name')->sort()->values()->all()
        );
        $this->assertSame(10, $members->pluck('district_name')->unique()->count());
        foreach ($selectedIds as $selectedId) {
            $member = $members->get($selectedId);
            $this->assertIsArray($member);
            $this->assertSame('regional-heroes-demo', $member['group_slug']);
            $this->assertStringEndsWith('(Demo)', $member['name']);
            $this->assertSame('', trim((string) $member['qualification']));
            $this->assertSame('', trim((string) $member['biography']));
            $this->assertNotSame('', trim((string) $member['image']));
            $this->assertNotSame('', trim((string) $member['path']));
            $this->assertCount(2, json_decode((string) $member['social_links'], true, 512, JSON_THROW_ON_ERROR));
        }

        $this->assertSame(
            11,
            (int) $database->query(
                "SELECT COUNT(*) FROM latest_news WHERE name LIKE '%(Demo)' AND TRIM(COALESCE(image, '')) <> ''"
            )->fetchColumn()
        );
        $national = $database->query(
            "SELECT biography, social_links, image FROM latest_news
             WHERE name = 'Samira Chowdhury (Demo)'"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($national);
        $this->assertStringContainsString("\n\n", (string) $national['biography']);
        $this->assertCount(2, json_decode((string) $national['social_links'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertNotSame('', trim((string) $national['image']));

        $this->assertSame(
            10,
            (int) $database->query(
                "SELECT COUNT(*) FROM districts WHERE hero_image IS NOT NULL AND TRIM(hero_image) <> ''"
            )->fetchColumn()
        );
        $this->assertSame(
            8,
            (int) $database->query("SELECT COUNT(*) FROM notice_boards WHERE title LIKE '%(Demo)'")->fetchColumn()
        );
        $this->assertSame(
            4,
            (int) $database->query(
                "SELECT COUNT(*) FROM notice_boards n
                 INNER JOIN divisions d ON d.id = n.division_id
                 WHERE d.slug = 'rangpur' AND n.title LIKE '%(Demo)'"
            )->fetchColumn()
        );
        $this->assertSame(
            2,
            (int) $database->query(
                "SELECT COUNT(*) FROM page_menus WHERE uuid = '68000000-0002-4000-8000-000000000007'"
            )->fetchColumn(),
            'The English and Bangla About-menu links to Meet the Heroes must both be present.'
        );
    }

    public function test_public_content_database_strips_private_metadata_from_every_cms_table(): void
    {
        $database = $this->readOnlyConnection(database_path(self::ARTIFACT));
        $snapshot = $this->snapshot();

        foreach (array_keys($snapshot['tables']) as $table) {
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $table);
            $columns = $this->columnNames($database, $table);

            foreach (self::PRIVATE_CMS_COLUMNS as $column) {
                if (! in_array($column, $columns, true)) {
                    continue;
                }

                $this->assertSame(
                    0,
                    (int) $database->query(
                        'SELECT COUNT(*) FROM "'.$table.'"'
                        .' WHERE COALESCE(TRIM(CAST("'.$column.'" AS TEXT)), \'\') <> \'\''
                    )->fetchColumn(),
                    "Private CMS metadata [{$table}.{$column}] must be empty in the public artifact."
                );
            }

            if (in_array('click_count', $columns, true)) {
                $this->assertSame(
                    0,
                    (int) $database->query(
                        'SELECT COUNT(*) FROM "'.$table.'" WHERE COALESCE("click_count", 0) <> 0'
                    )->fetchColumn(),
                    "CMS analytics [{$table}.click_count] must be zero in the public artifact."
                );
            }
        }
    }

    public function test_public_content_database_table_counts_match_the_snapshot_manifest(): void
    {
        $database = $this->readOnlyConnection(database_path(self::ARTIFACT));
        $snapshot = $this->snapshot();
        $tables = $snapshot['tables'] ?? null;

        $this->assertIsArray($tables, 'The CMS snapshot table manifest is invalid.');
        $this->assertCount(28, $tables, 'The public CMS snapshot must retain its 28-table allowlist.');

        foreach ($tables as $table => $records) {
            $this->assertIsString($table);
            $this->assertIsArray($records, "Snapshot table [{$table}] must contain a record list.");
            $this->assertTableExists($database, $table);
            $this->assertSame(
                count($records),
                $this->rowCount($database, $table),
                "Public table [{$table}] does not match the CMS snapshot row count."
            );
        }
    }

    public function test_public_content_database_exactly_matches_the_normalized_snapshot(): void
    {
        $artifactPath = database_path(self::ARTIFACT);
        $snapshotPath = database_path(self::SNAPSHOT);
        $filename = '.igf-public-content-artifact-test-'.Str::uuid().'.json';
        $outputPath = database_path('seeders/seed-data/'.$filename);
        $outputOption = 'database/seeders/seed-data/'.$filename;
        $connection = config('database.connections.sqlite');
        $connectionName = 'public_content_artifact_verification';
        $previousDefault = config('database.default');
        $previousConnection = config('database.connections.'.$connectionName);

        $this->assertIsArray($connection);
        $connection['url'] = null;
        $connection['database'] = $artifactPath;
        config([
            'database.default' => $connectionName,
            'database.connections.'.$connectionName => $connection,
        ]);
        DB::purge($connectionName);

        try {
            $exitCode = Artisan::call('cms:snapshot', [
                '--output' => $outputOption,
                '--force' => true,
            ]);

            $this->assertSame(0, $exitCode, Artisan::output());
            $this->assertFileExists($outputPath);
            $this->assertSame(
                str_replace(["\r\n", "\r"], "\n", File::get($snapshotPath)),
                str_replace(["\r\n", "\r"], "\n", File::get($outputPath)),
                'The artifact content differs from the normalized, reviewed CMS snapshot.'
            );
        } finally {
            DB::disconnect($connectionName);
            DB::purge($connectionName);
            config([
                'database.default' => $previousDefault,
                'database.connections.'.$connectionName => $previousConnection,
            ]);
            File::delete($outputPath);
        }
    }

    public function test_public_content_database_matches_its_published_checksum(): void
    {
        $artifactPath = database_path(self::ARTIFACT);
        $checksumPath = database_path(self::CHECKSUM);

        $this->assertFileExists($artifactPath);
        $this->assertFileExists($checksumPath);
        $manifest = trim((string) file_get_contents($checksumPath));
        $this->assertMatchesRegularExpression(
            '/\A[0-9a-f]{64}  igf-public-content\.sqlite\z/i',
            $manifest
        );
        [$expected] = explode('  ', $manifest, 2);

        $this->assertSame(strtolower($expected), hash_file('sha256', $artifactPath));
    }

    private function readOnlyConnection(string $path): PDO
    {
        $this->assertFileExists($path, 'The Git-safe public-content SQLite artifact is missing.');

        $resolvedPath = realpath($path);
        $this->assertNotFalse($resolvedPath, 'The public-content SQLite artifact path cannot be resolved.');

        $uriPath = str_replace('\\', '/', $resolvedPath);

        return new PDO('sqlite:file:'.$uriPath.'?mode=ro', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function snapshot(): array
    {
        $path = database_path(self::SNAPSHOT);
        $this->assertFileExists($path, 'The public CMS content snapshot is missing.');

        return json_decode(
            (string) file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function assertTableExists(PDO $database, string $table): void
    {
        $statement = $database->prepare(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :table"
        );
        $statement->execute(['table' => $table]);

        $this->assertSame(
            1,
            (int) $statement->fetchColumn(),
            "Expected table [{$table}] is missing from the public-content SQLite artifact."
        );
    }

    private function rowCount(PDO $database, string $table): int
    {
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $table);

        return (int) $database->query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn();
    }

    /** @return list<string> */
    private function columnNames(PDO $database, string $table): array
    {
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $table);

        return array_values(array_map(
            static fn (array $column): string => (string) $column['name'],
            $database->query('PRAGMA table_info("'.$table.'")')->fetchAll(PDO::FETCH_ASSOC)
        ));
    }

    private function assertPublicOnlyRows(PDO $database): void
    {
        $queries = [
            'site_settings contains a non-public setting' =>
                "SELECT COUNT(*) FROM site_settings WHERE COALESCE(is_public, 0) <> 1",
            'media_assets contains a non-public disk record' =>
                "SELECT COUNT(*) FROM media_assets WHERE COALESCE(disk, '') <> 'public'",
            'latest_news contains a private team-member email address' =>
                "SELECT COUNT(*) FROM latest_news WHERE COALESCE(TRIM(email), '') <> ''",
            'notice_boards contains a private file path or source IP address' =>
                "SELECT COUNT(*) FROM notice_boards
                    WHERE COALESCE(TRIM(file_path), '') <> ''
                       OR COALESCE(TRIM(ip), '') <> ''",
            'chat_faqs contains retained click analytics' =>
                'SELECT COUNT(*) FROM chat_faqs WHERE COALESCE(click_count, 0) <> 0',
        ];

        foreach ($queries as $failure => $query) {
            $this->assertSame(
                0,
                (int) $database->query($query)->fetchColumn(),
                'The public-content SQLite artifact '.$failure.'.'
            );
        }
    }
}
