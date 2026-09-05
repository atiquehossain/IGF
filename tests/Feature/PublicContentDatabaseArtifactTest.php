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
        'menu_actions',
        'migrations',
        'roles',
        'seo_redirect_locks',
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

    public function test_public_content_database_contains_every_current_migration(): void
    {
        $database = $this->readOnlyConnection(database_path(self::ARTIFACT));
        $applied = $database->query('SELECT migration FROM migrations ORDER BY migration')
            ->fetchAll(PDO::FETCH_COLUMN);
        $expected = array_map(
            static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
            glob(database_path('migrations/*.php')) ?: []
        );
        sort($expected, SORT_STRING);

        $this->assertSame(
            $expected,
            $applied,
            'The public-content SQLite artifact must be rebuilt after every migration change.'
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

    public function test_public_content_database_retains_the_required_redirect_mutex(): void
    {
        $database = $this->readOnlyConnection(database_path(self::ARTIFACT));

        $this->assertTableExists($database, 'seo_redirect_locks');
        $this->assertSame(
            [['id' => 1]],
            $database->query('SELECT id FROM seo_redirect_locks ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
            'The public-content database must retain the non-sensitive singleton used to serialize redirect edits.'
        );
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
    }

    public function test_public_content_database_contains_only_public_cms_fields(): void
    {
        $database = $this->readOnlyConnection(database_path(self::ARTIFACT));
        $this->assertPublicOnlyRows($database);
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
        $this->assertCount(29, $tables, 'The public CMS snapshot must retain its 29-table allowlist.');

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

    public function test_bangla_team_overlays_follow_their_stable_english_member_identity(): void
    {
        $database = $this->readOnlyConnection(database_path(self::ARTIFACT));
        $expected = [
            'Muhammad Jahirul Islam' => [
                'name' => 'মুহাম্মদ জাহিরুল ইসলাম',
                'description' => 'প্রতিষ্ঠাতা - সভাপতি',
            ],
            'Monmoy Jahan Ali' => [
                'name' => 'মনময় জাহান আলী',
                'description' => 'উপ-সভাপতি',
            ],
            'Md. Rafeu Riyan' => [
                'name' => 'মো. রাফিউ রিয়ান',
                'description' => 'সাধারণ সম্পাদক',
            ],
            'Israt Jahan' => [
                'name' => 'ইসরাত জাহান',
                'description' => 'কোষাধ্যক্ষ',
            ],
            'Md. Fazle Munim' => [
                'name' => 'মো. ফজলে মুনিম',
                'description' => 'নির্বাহী সদস্য',
            ],
            'Md. Tajdin Hassan' => [
                'name' => 'মো. তাজদিন হাসান',
                'description' => 'নির্বাহী সদস্য',
            ],
            'Josinta Zinia' => [
                'name' => 'জোসেন্টা জিনিয়া',
                'description' => 'নির্বাহী সদস্য',
            ],
        ];
        $members = $database->query(
            "SELECT id, name, description
             FROM latest_news
             WHERE type = 'our-members' AND status = 1 AND language = 'en'
             ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(count($expected), $members);
        $this->assertEqualsCanonicalizing(array_keys($expected), array_column($members, 'name'));
        $this->assertSame(
            count($expected) * 2,
            (int) $database->query(
                "SELECT COUNT(*) FROM translation_strings
                 WHERE locale = 'bn'
                   AND (key GLOB 'content.team_member.*.name'
                        OR key GLOB 'content.team_member.*.description')"
            )->fetchColumn(),
            'The artifact must contain exactly one Bangla name and role overlay for every approved team member.'
        );

        $translation = $database->prepare(
            'SELECT value, source_hash, status FROM translation_strings WHERE locale = :locale AND key = :key'
        );
        foreach ($members as $member) {
            $identity = (string) $member['name'];
            $this->assertArrayHasKey($identity, $expected);

            foreach (['name', 'description'] as $field) {
                $translation->execute([
                    'locale' => 'bn',
                    'key' => 'content.team_member.'.(int) $member['id'].'.'.$field,
                ]);
                $overlay = $translation->fetch(PDO::FETCH_ASSOC);

                $this->assertIsArray(
                    $overlay,
                    "The approved Bangla [{$field}] overlay is missing for [{$identity}]."
                );
                $this->assertSame($expected[$identity][$field], $overlay['value']);
                $this->assertSame(
                    hash('sha256', 'en|'.(string) $member[$field]),
                    $overlay['source_hash'],
                    "The Bangla [{$field}] overlay source hash does not belong to [{$identity}]."
                );
                $this->assertSame('translated', $overlay['status']);
            }
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
                File::get($snapshotPath),
                File::get($outputPath),
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

    public function test_private_annual_report_files_referenced_by_the_artifact_are_bundled_for_restore(): void
    {
        $database = $this->readOnlyConnection(database_path(self::ARTIFACT));
        $reports = $database->query(
            "SELECT DISTINCT image_path AS filename, file_size
             FROM annual_reports
             WHERE status = 1 AND COALESCE(TRIM(image_path), '') <> ''
             ORDER BY image_path"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($reports, 'The public-content artifact should retain its approved annual reports.');

        foreach ($reports as $report) {
            $filename = (string) $report['filename'];
            $this->assertSame(basename($filename), $filename, 'Annual-report paths must remain private-storage basenames.');

            $assetPath = database_path('seeders/assets/annual-reports/'.$filename);
            $this->assertFileExists($assetPath, "Bundled annual-report file [{$filename}] is missing.");
            $this->assertSame(
                (int) $report['file_size'],
                filesize($assetPath),
                "Bundled annual-report file [{$filename}] differs from its approved metadata."
            );
        }

        $instructions = File::get(database_path('seeders/seed-data/igf-public-content.README.md'));
        $this->assertStringContainsString('storage/app/annual-reports', $instructions);
        $this->assertStringContainsString('database/seeders/assets/annual-reports/*.pdf', $instructions);
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
