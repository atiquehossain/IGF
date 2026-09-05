<?php

namespace Tests\Feature;

use App\Models\Page;
use Database\Seeders\CmsContentSnapshotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class CmsContentSnapshotCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $temporarySnapshots = [];

    protected function tearDown(): void
    {
        foreach ($this->temporarySnapshots as $path) {
            File::delete($path);
        }

        parent::tearDown();
    }

    public function test_command_writes_a_deterministic_git_safe_content_snapshot(): void
    {
        $ids = $this->seedContentAndSensitiveCanaries();
        [$firstOption, $firstPath] = $this->temporaryOutput();
        [$secondOption, $secondPath] = $this->temporaryOutput();

        $exitCode = Artisan::call('cms:snapshot', ['--output' => $firstOption]);
        $commandOutput = Artisan::output();
        $this->assertSame(0, $exitCode, $commandOutput);
        $this->assertStringContainsString(
            "CMS content snapshot written to {$firstOption}.",
            $commandOutput
        );
        $this->artisan('cms:snapshot', ['--output' => $secondOption])
            ->expectsOutput("CMS content snapshot written to {$secondOption}.")
            ->assertSuccessful();

        $this->assertFileExists($firstPath);
        $this->assertSame(
            File::get($firstPath),
            File::get($secondPath),
            'An unchanged database must produce byte-for-byte identical snapshot JSON.'
        );

        $json = File::get($firstPath);
        $snapshot = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['version', 'tables', 'checksums'], array_keys($snapshot));
        $this->assertSame(1, $snapshot['version']);
        $this->assertIsArray($snapshot['tables']);
        $this->assertNotEmpty($snapshot['tables']);
        $this->assertSame(array_keys($snapshot['tables']), array_keys($snapshot['checksums']));
        $this->assertArrayNotHasKey('generated_at', $snapshot);

        foreach ($snapshot['tables'] as $table => $records) {
            $encodedRecords = json_encode(
                $records,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            $this->assertSame(hash('sha256', $encodedRecords), $snapshot['checksums'][$table]);
        }

        foreach ([
            'admins',
            'users',
            'donations',
            'donation_allocations',
            'volunteers',
            'ssl_commerz_transactions',
            'admin_audit_events',
        ] as $privateTable) {
            $this->assertArrayNotHasKey($privateTable, $snapshot['tables']);
        }

        foreach ([
            'snapshot-admin@example.test',
            'snapshot-member@example.test',
            'SNAPSHOT-DONOR-EMAIL-CANARY@example.test',
            'SNAPSHOT-VOLUNTEER-EMAIL-CANARY@example.test',
            'SNAPSHOT-TRANSACTION-CUSTOMER-CANARY@example.test',
            'PRIVATE-SITE-SETTING-CANARY',
            'PRIVATE-NOTICE-FILE-CANARY.pdf',
            '198.51.100.77',
            'LATEST-NEWS-PRIVATE-EMAIL-CANARY@example.test',
            'SNAPSHOT-AUDIT-IP-HASH-CANARY',
        ] as $sensitiveCanary) {
            $this->assertStringNotContainsString($sensitiveCanary, $json);
        }

        $this->assertSame(
            'PUBLIC-SITE-SETTING-CANARY',
            $this->row($snapshot, 'site_settings', 'key', 'snapshot_public_setting')['value']
        );

        $page = $this->row($snapshot, 'pages', 'uuid', $ids['page_uuid']);
        $this->assertSame($ids['category_uuid'], $page['category_uuid']);
        $this->assertArrayNotHasKey('category_id', $page);
        $this->assertArrayHasKey('published_at', $page);
        $this->assertArrayHasKey('last_published_at', $page);

        $childMenu = $this->row($snapshot, 'page_menus', 'uuid', $ids['child_menu_uuid']);
        $this->assertSame($ids['parent_menu_uuid'], $childMenu['parent_uuid']);
        $this->assertArrayNotHasKey('parent_id', $childMenu);

        $block = $this->row($snapshot, 'page_blocks', 'uuid', $ids['block_uuid']);
        $this->assertSame($ids['page_uuid'], $block['page_uuid']);
        $this->assertArrayNotHasKey('page_id', $block);

        $pageTag = $this->row($snapshot, 'page_tag_modules', 'uuid', $ids['page_tag_uuid']);
        $this->assertSame($ids['page_uuid'], $pageTag['page_uuid']);
        $this->assertSame($ids['tag_uuid'], $pageTag['tag_uuid']);
        $this->assertArrayNotHasKey('page_id', $pageTag);
        $this->assertArrayNotHasKey('tag_id', $pageTag);

        $teamMember = $this->row($snapshot, 'latest_news', 'name', 'Snapshot team member');
        $this->assertSame($ids['category_uuid'], $teamMember['category_uuid']);
        $this->assertSame($ids['team_group_uuid'], $teamMember['team_group_uuid']);
        $this->assertArrayNotHasKey('category_id', $teamMember);
        $this->assertArrayNotHasKey('team_group_id', $teamMember);
        $this->assertArrayNotHasKey('email', $teamMember);

        $gallery = $this->row($snapshot, 'galleries', 'uuid', $ids['gallery_uuid']);
        $this->assertSame($ids['album_uuid'], $gallery['album_uuid']);
        $this->assertArrayNotHasKey('album_id', $gallery);

        $cause = $this->row($snapshot, 'donation_types', 'uuid', $ids['donation_type_uuid']);
        $this->assertSame($ids['donation_cause_group_uuid'], $cause['donation_cause_group_uuid']);
        $this->assertArrayNotHasKey('donation_cause_group_id', $cause);

        $seo = $this->row($snapshot, 'seo_metadata', 'title', 'Snapshot SEO title');
        $this->assertSame($ids['page_uuid'], $seo['seoable_uuid']);
        $this->assertArrayNotHasKey('seoable_id', $seo);

        $notice = $this->row($snapshot, 'notice_boards', 'title', 'Snapshot public notice');
        $this->assertArrayNotHasKey('file_path', $notice);
        $this->assertArrayNotHasKey('ip', $notice);

        $banglaLocale = $this->row($snapshot, 'translation_locales', 'locale', 'bn');
        $this->assertSame(1, $banglaLocale['is_enabled']);
        $this->assertSame('2026-08-29 10:00:00', $banglaLocale['enabled_at']);
        $this->assertArrayNotHasKey('updated_by', $banglaLocale);

        $translation = $this->row(
            $snapshot,
            'translation_strings',
            'key',
            $ids['translation_string_key']
        );
        $this->assertSame('বাংলায় স্বাগতম', $translation['value']);
        $this->assertSame(hash('sha256', 'Welcome in Bangla'), $translation['source_hash']);
        $this->assertSame('translated', $translation['status']);
        $this->assertArrayNotHasKey('updated_by', $translation);

        $this->assertGitSafeRecordFields($snapshot['tables']);
    }

    public function test_command_refuses_to_overwrite_without_force_and_force_replaces_the_file(): void
    {
        [$outputOption, $outputPath] = $this->temporaryOutput();
        File::put($outputPath, 'existing-snapshot-must-survive');

        $this->artisan('cms:snapshot', ['--output' => $outputOption])
            ->expectsOutput('Snapshot already exists. Use --force to replace it.')
            ->assertFailed();

        $this->assertSame('existing-snapshot-must-survive', File::get($outputPath));

        $this->artisan('cms:snapshot', ['--output' => $outputOption, '--force' => true])
            ->expectsOutput("CMS content snapshot written to {$outputOption}.")
            ->assertSuccessful();

        $snapshot = json_decode(File::get($outputPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $snapshot['version']);
    }

    public function test_seeder_restores_uuid_relations_after_numeric_ids_change_and_is_idempotent(): void
    {
        $ids = $this->seedContentAndSensitiveCanaries();
        [$outputOption, $outputPath] = $this->temporaryOutput();
        $this->exportSnapshot($outputOption);
        $json = File::get($outputPath);

        $oldCategoryId = (int) DB::table('categories')->where('uuid', $ids['category_uuid'])->value('id');
        $oldPageId = (int) DB::table('pages')->where('uuid', $ids['page_uuid'])->value('id');
        $oldParentMenuId = (int) DB::table('page_menus')->where('uuid', $ids['parent_menu_uuid'])->value('id');

        DB::table('seo_metadata')->where('title', 'Snapshot SEO title')->delete();
        DB::table('page_tag_modules')->where('uuid', $ids['page_tag_uuid'])->delete();
        DB::table('page_blocks')->where('uuid', $ids['block_uuid'])->delete();
        DB::table('page_menus')->where('uuid', $ids['child_menu_uuid'])->delete();
        DB::table('page_menus')->where('uuid', $ids['parent_menu_uuid'])->delete();
        DB::table('pages')->where('uuid', $ids['page_uuid'])->delete();
        DB::table('categories')->where('uuid', $ids['category_uuid'])->delete();
        DB::table('translation_strings')
            ->where('key', $ids['translation_string_key'])
            ->where('locale', 'bn')
            ->delete();
        DB::table('translation_locales')->where('locale', 'bn')->update([
            'is_enabled' => false,
            'enabled_at' => null,
        ]);

        $decoyCategoryId = DB::table('categories')->insertGetId([
            'uuid' => '92000000-0000-4000-8000-000000000001',
            'name' => 'Numeric ID decoy category',
            'slug' => 'numeric-id-decoy-category',
            'language' => 'en',
            'status' => 1,
        ]);
        DB::table('pages')->insert([
            'uuid' => '92000000-0000-4000-8000-000000000002',
            'category_id' => $decoyCategoryId,
            'name' => 'Numeric ID decoy page',
            'sub_title' => 'Decoy',
            'slug' => 'numeric-id-decoy-page',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
        ]);
        DB::table('page_menus')->insert([
            'uuid' => '92000000-0000-4000-8000-000000000003',
            'name' => 'Numeric ID decoy menu',
            'type' => 'header',
            'language' => 'en',
            'status' => 1,
        ]);

        $this->mockSeederSnapshot($json, reads: 2);
        $seeder = $this->app->make(CmsContentSnapshotSeeder::class);
        $seeder->run();

        $newCategoryId = (int) DB::table('categories')->where('uuid', $ids['category_uuid'])->value('id');
        $newPageId = (int) DB::table('pages')->where('uuid', $ids['page_uuid'])->value('id');
        $newParentMenuId = (int) DB::table('page_menus')->where('uuid', $ids['parent_menu_uuid'])->value('id');

        $this->assertNotSame($oldCategoryId, $newCategoryId);
        $this->assertNotSame($oldPageId, $newPageId);
        $this->assertNotSame($oldParentMenuId, $newParentMenuId);
        $this->assertSame(
            $newCategoryId,
            (int) DB::table('pages')->where('uuid', $ids['page_uuid'])->value('category_id')
        );
        $this->assertSame(
            $newPageId,
            (int) DB::table('page_blocks')->where('uuid', $ids['block_uuid'])->value('page_id')
        );
        $this->assertSame(
            $newPageId,
            (int) DB::table('page_tag_modules')->where('uuid', $ids['page_tag_uuid'])->value('page_id')
        );
        $this->assertSame(
            $newPageId,
            (int) DB::table('seo_metadata')->where('title', 'Snapshot SEO title')->value('seoable_id')
        );
        $this->assertSame(
            $newParentMenuId,
            (int) DB::table('page_menus')->where('uuid', $ids['child_menu_uuid'])->value('parent_id')
        );
        $this->assertSame(
            $newCategoryId,
            (int) DB::table('latest_news')->where('name', 'Snapshot team member')->value('category_id')
        );
        $this->assertDatabaseHas('translation_locales', [
            'locale' => 'bn',
            'is_enabled' => true,
            'enabled_at' => '2026-08-29 10:00:00',
        ]);
        $this->assertDatabaseHas('translation_strings', [
            'key' => $ids['translation_string_key'],
            'locale' => 'bn',
            'value' => 'বাংলায় স্বাগতম',
            'status' => 'translated',
        ]);

        $seeder->run();

        foreach ([
            ['pages', 'uuid', $ids['page_uuid']],
            ['categories', 'uuid', $ids['category_uuid']],
            ['page_blocks', 'uuid', $ids['block_uuid']],
            ['page_tag_modules', 'uuid', $ids['page_tag_uuid']],
            ['page_menus', 'uuid', $ids['parent_menu_uuid']],
            ['page_menus', 'uuid', $ids['child_menu_uuid']],
            ['seo_metadata', 'title', 'Snapshot SEO title'],
            ['translation_strings', 'key', $ids['translation_string_key']],
        ] as [$table, $field, $value]) {
            $this->assertSame(
                1,
                DB::table($table)->where($field, $value)->count(),
                "A second seed duplicated {$table}.{$field}={$value}."
            );
        }
    }

    public function test_seeder_adopts_the_fresh_migration_team_group_without_duplicate_slug_conflicts(): void
    {
        $snapshot = json_decode(
            File::get(database_path('seeders/seed-data/cms-content.snapshot.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $sourceGroup = collect($snapshot['tables']['team_groups'] ?? [])->first(
            fn (array $group): bool => ($group['language'] ?? null) === 'en'
                && ($group['slug'] ?? null) === 'board-of-directors'
        );

        $this->assertIsArray($sourceGroup);
        $bootstrap = DB::table('team_groups')
            ->where('language', 'en')
            ->where('slug', 'board-of-directors')
            ->first();
        $this->assertNotNull($bootstrap);
        $this->assertNotSame($sourceGroup['uuid'], $bootstrap->uuid);

        $seeder = $this->app->make(CmsContentSnapshotSeeder::class);
        $seeder->run();
        $seeder->run();

        $restored = DB::table('team_groups')
            ->where('language', 'en')
            ->where('slug', 'board-of-directors')
            ->first();

        $this->assertNotNull($restored);
        $this->assertSame((int) $bootstrap->id, (int) $restored->id);
        $this->assertSame($sourceGroup['uuid'], $restored->uuid);
        $this->assertSame(
            1,
            DB::table('team_groups')
                ->where('language', 'en')
                ->where('slug', 'board-of-directors')
                ->count()
        );
    }

    public function test_seeder_preserves_localized_pages_that_share_a_uuid(): void
    {
        $uuid = '93000000-0000-4000-8000-000000000001';
        $categoryUuid = '93000000-0000-4000-8000-000000000002';
        $englishBlockUuid = '93000000-0000-4000-8000-000000000003';
        $banglaBlockUuid = '93000000-0000-4000-8000-000000000004';

        $englishCategoryId = DB::table('categories')->insertGetId([
            'uuid' => $categoryUuid,
            'name' => 'Localized category',
            'slug' => 'localized-category',
            'language' => 'en',
            'status' => 1,
        ]);
        $banglaCategoryId = DB::table('categories')->insertGetId([
            'uuid' => $categoryUuid,
            'name' => 'স্থানীয় বিভাগ',
            'slug' => 'localized-category',
            'language' => 'bn',
            'status' => 1,
        ]);

        $englishPageId = DB::table('pages')->insertGetId(
            [
                'uuid' => $uuid,
                'category_id' => $englishCategoryId,
                'name' => 'Localized snapshot page',
                'sub_title' => 'Localized snapshot page subtitle',
                'slug' => 'localized-snapshot-page',
                'language' => 'en',
                'status' => 1,
                'publication_status' => 'published',
                'visibility' => 'public',
            ]
        );
        $banglaPageId = DB::table('pages')->insertGetId(
            [
                'uuid' => $uuid,
                'category_id' => $banglaCategoryId,
                'name' => 'স্থানীয় স্ন্যাপশট পৃষ্ঠা',
                'sub_title' => 'স্থানীয় স্ন্যাপশট পৃষ্ঠার উপশিরোনাম',
                'slug' => 'localized-snapshot-page-bn',
                'language' => 'bn',
                'status' => 1,
                'publication_status' => 'published',
                'visibility' => 'public',
            ]
        );
        DB::table('page_blocks')->insert([
            [
                'uuid' => $englishBlockUuid,
                'page_id' => $englishPageId,
                'type' => 'rich-text',
                'label' => 'English localized block',
                'content' => json_encode(['html' => '<p>English block</p>'], JSON_THROW_ON_ERROR),
                'sort_order' => 1,
                'is_enabled' => 1,
            ],
            [
                'uuid' => $banglaBlockUuid,
                'page_id' => $banglaPageId,
                'type' => 'rich-text',
                'label' => 'Bangla localized block',
                'content' => json_encode(['html' => '<p>বাংলা ব্লক</p>'], JSON_THROW_ON_ERROR),
                'sort_order' => 1,
                'is_enabled' => 1,
            ],
        ]);

        [$outputOption, $outputPath] = $this->temporaryOutput();
        $this->exportSnapshot($outputOption);
        $json = File::get($outputPath);

        DB::table('page_blocks')->whereIn('uuid', [$englishBlockUuid, $banglaBlockUuid])->delete();
        DB::table('pages')->where('uuid', $uuid)->delete();
        DB::table('categories')->where('uuid', $categoryUuid)->delete();

        $this->mockSeederSnapshot($json, reads: 2);
        $seeder = $this->app->make(CmsContentSnapshotSeeder::class);
        $seeder->run();
        $seeder->run();

        $this->assertSame(2, DB::table('pages')->where('uuid', $uuid)->count());
        $this->assertSame(
            ['bn', 'en'],
            DB::table('pages')->where('uuid', $uuid)->orderBy('language')->pluck('language')->all()
        );
        $this->assertSame(
            ['bn' => 'bn', 'en' => 'en'],
            DB::table('pages as pages')
                ->join('categories as categories', 'categories.id', '=', 'pages.category_id')
                ->where('pages.uuid', $uuid)
                ->orderBy('pages.language')
                ->pluck('categories.language', 'pages.language')
                ->all()
        );
        $this->assertSame(
            ['Bangla localized block' => 'bn', 'English localized block' => 'en'],
            DB::table('page_blocks as blocks')
                ->join('pages as pages', 'pages.id', '=', 'blocks.page_id')
                ->whereIn('blocks.uuid', [$englishBlockUuid, $banglaBlockUuid])
                ->orderBy('blocks.label')
                ->pluck('pages.language', 'blocks.label')
                ->all()
        );
    }

    public function test_seeder_preserves_other_localized_records_that_share_a_uuid(): void
    {
        $albumUuid = '93500000-0000-4000-8000-000000000001';
        $bannerUuid = '93500000-0000-4000-8000-000000000002';
        $galleryUuid = '93500000-0000-4000-8000-000000000003';
        $splashUuid = '93500000-0000-4000-8000-000000000004';

        $englishAlbumId = DB::table('albums')->insertGetId([
            'uuid' => $albumUuid,
            'name' => 'Localized album',
            'language' => 'en',
            'status' => 1,
        ]);
        $banglaAlbumId = DB::table('albums')->insertGetId([
            'uuid' => $albumUuid,
            'name' => 'স্থানীয় অ্যালবাম',
            'language' => 'bn',
            'status' => 1,
        ]);

        foreach ([
            ['en', 'Localized banner', $englishAlbumId],
            ['bn', 'স্থানীয় ব্যানার', $banglaAlbumId],
        ] as [$language, $name, $albumId]) {
            DB::table('banners')->insert([
                'uuid' => $bannerUuid,
                'album_id' => $albumId,
                'name' => $name,
                'language' => $language,
                'status' => 1,
            ]);
        }

        foreach ([
            ['en', 'Localized gallery', $englishAlbumId],
            ['bn', 'স্থানীয় গ্যালারি', $banglaAlbumId],
        ] as [$language, $name, $albumId]) {
            DB::table('galleries')->insert([
                'uuid' => $galleryUuid,
                'album_id' => $albumId,
                'name' => $name,
                'language' => $language,
                'status' => 1,
            ]);
        }

        DB::table('splash_screens')->insert([
            [
                'uuid' => $splashUuid,
                'title' => 'Localized splash screen',
                'language' => 'en',
                'status' => 1,
            ],
            [
                'uuid' => $splashUuid,
                'title' => 'স্থানীয় স্প্ল্যাশ স্ক্রিন',
                'language' => 'bn',
                'status' => 1,
            ],
        ]);

        [$outputOption, $outputPath] = $this->temporaryOutput();
        $this->exportSnapshot($outputOption);
        $json = File::get($outputPath);

        DB::table('banners')->where('uuid', $bannerUuid)->delete();
        DB::table('galleries')->where('uuid', $galleryUuid)->delete();
        DB::table('splash_screens')->where('uuid', $splashUuid)->delete();
        DB::table('albums')->where('uuid', $albumUuid)->delete();

        $this->mockSeederSnapshot($json, reads: 2);
        $seeder = $this->app->make(CmsContentSnapshotSeeder::class);
        $seeder->run();
        $seeder->run();

        foreach ([
            ['albums', $albumUuid],
            ['banners', $bannerUuid],
            ['galleries', $galleryUuid],
            ['splash_screens', $splashUuid],
        ] as [$table, $uuid]) {
            $this->assertSame(2, DB::table($table)->where('uuid', $uuid)->count());
            $this->assertSame(
                ['bn', 'en'],
                DB::table($table)->where('uuid', $uuid)->orderBy('language')->pluck('language')->all()
            );
        }

        foreach (['banners', 'galleries'] as $table) {
            $this->assertSame(
                ['bn' => 'bn', 'en' => 'en'],
                DB::table($table.' as localized')
                    ->join('albums as albums', 'albums.id', '=', 'localized.album_id')
                    ->where('localized.uuid', $table === 'banners' ? $bannerUuid : $galleryUuid)
                    ->orderBy('localized.language')
                    ->pluck('albums.language', 'localized.language')
                    ->all()
            );
        }
    }

    public function test_seeder_preserves_localized_navigation_parent_relationships(): void
    {
        $parentUuid = '94000000-0000-4000-8000-000000000001';
        $childUuid = '94000000-0000-4000-8000-000000000002';

        $englishParentId = DB::table('page_menus')->insertGetId([
            'uuid' => $parentUuid,
            'name' => 'Localized parent',
            'slug' => 'localized-parent',
            'type' => 'header',
            'language' => 'en',
            'status' => 1,
        ]);
        $banglaParentId = DB::table('page_menus')->insertGetId([
            'uuid' => $parentUuid,
            'name' => 'স্থানীয় অভিভাবক',
            'slug' => 'localized-parent',
            'type' => 'header',
            'language' => 'bn',
            'status' => 1,
        ]);
        DB::table('page_menus')->insert([
            [
                'uuid' => $childUuid,
                'parent_id' => $englishParentId,
                'name' => 'Localized child',
                'slug' => 'localized-child',
                'type' => 'header',
                'language' => 'en',
                'status' => 1,
            ],
            [
                'uuid' => $childUuid,
                'parent_id' => $banglaParentId,
                'name' => 'স্থানীয় শিশু',
                'slug' => 'localized-child',
                'type' => 'header',
                'language' => 'bn',
                'status' => 1,
            ],
        ]);

        [$outputOption, $outputPath] = $this->temporaryOutput();
        $this->exportSnapshot($outputOption);
        $json = File::get($outputPath);

        DB::table('page_menus')->where('uuid', $childUuid)->delete();
        DB::table('page_menus')->where('uuid', $parentUuid)->delete();

        $this->mockSeederSnapshot($json, reads: 2);
        $seeder = $this->app->make(CmsContentSnapshotSeeder::class);
        $seeder->run();
        $seeder->run();

        $this->assertSame(2, DB::table('page_menus')->where('uuid', $parentUuid)->count());
        $this->assertSame(2, DB::table('page_menus')->where('uuid', $childUuid)->count());
        $this->assertSame(
            ['bn' => 'bn', 'en' => 'en'],
            DB::table('page_menus as children')
                ->join('page_menus as parents', 'parents.id', '=', 'children.parent_id')
                ->where('children.uuid', $childUuid)
                ->orderBy('children.language')
                ->pluck('parents.language', 'children.language')
                ->all()
        );
    }

    public function test_seeder_rejects_tampered_records_when_the_checksum_does_not_match(): void
    {
        $ids = $this->seedContentAndSensitiveCanaries();
        [$outputOption, $outputPath] = $this->temporaryOutput();
        $this->exportSnapshot($outputOption);

        $snapshot = json_decode(File::get($outputPath), true, 512, JSON_THROW_ON_ERROR);
        foreach ($snapshot['tables']['pages'] as &$page) {
            if (($page['uuid'] ?? null) === $ids['page_uuid']) {
                $page['name'] = 'Tampered after checksum generation';
            }
        }
        unset($page);

        $tampered = json_encode(
            $snapshot,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        $this->mockSeederSnapshot($tampered);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The CMS content snapshot checksum failed for [pages].');

        $this->app->make(CmsContentSnapshotSeeder::class)->run();
    }

    /**
     * @return array<string, string>
     */
    private function seedContentAndSensitiveCanaries(): array
    {
        $now = '2026-08-29 10:00:00';
        $ids = [
            'category_uuid' => '91000000-0000-4000-8000-000000000001',
            'page_uuid' => '91000000-0000-4000-8000-000000000002',
            'parent_menu_uuid' => '91000000-0000-4000-8000-000000000003',
            'child_menu_uuid' => '91000000-0000-4000-8000-000000000004',
            'tag_uuid' => '91000000-0000-4000-8000-000000000005',
            'page_tag_uuid' => '91000000-0000-4000-8000-000000000006',
            'block_uuid' => '91000000-0000-4000-8000-000000000007',
            'team_group_uuid' => '91000000-0000-4000-8000-000000000008',
            'album_uuid' => '91000000-0000-4000-8000-000000000009',
            'gallery_uuid' => '91000000-0000-4000-8000-000000000010',
            'donation_cause_group_uuid' => '91000000-0000-4000-8000-000000000011',
            'donation_type_uuid' => '91000000-0000-4000-8000-000000000012',
            'translation_string_key' => 'snapshot.interface.greeting',
        ];

        DB::table('translation_locales')->where('locale', 'bn')->update([
            'is_enabled' => true,
            'enabled_at' => $now,
            'updated_by' => 710004,
            'updated_at' => $now,
        ]);
        DB::table('translation_strings')->insert([
            'key' => $ids['translation_string_key'],
            'locale' => 'bn',
            'value' => 'বাংলায় স্বাগতম',
            'source_hash' => hash('sha256', 'Welcome in Bangla'),
            'status' => 'translated',
            'updated_by' => 710004,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $categoryId = DB::table('categories')->insertGetId([
            'uuid' => $ids['category_uuid'],
            'name' => 'Snapshot category',
            'slug' => 'snapshot-category',
            'status' => 1,
            'language' => 'en',
            'created_by' => 710001,
            'updated_by' => 710002,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $pageId = DB::table('pages')->insertGetId([
            'uuid' => $ids['page_uuid'],
            'category_id' => $categoryId,
            'name' => 'Snapshot page',
            'sub_title' => 'Snapshot page subtitle',
            'slug' => 'snapshot-page',
            'description' => 'Git-safe CMS page content.',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => '2026-08-20 00:00:00',
            'last_published_at' => '2026-08-20 12:00:00',
            'publish_by' => 'SNAPSHOT-LEGACY-ACTOR-CANARY',
            'published_by' => 710003,
            'created_by' => 710001,
            'updated_by' => 710002,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $parentMenuId = DB::table('page_menus')->insertGetId([
            'uuid' => $ids['parent_menu_uuid'],
            'name' => 'Snapshot parent menu',
            'type' => 'header',
            'language' => 'en',
            'status' => 1,
            'created_by' => 710001,
            'updated_by' => 710002,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('page_menus')->insert([
            'uuid' => $ids['child_menu_uuid'],
            'parent_id' => $parentMenuId,
            'name' => 'Snapshot child menu',
            'type' => 'header',
            'language' => 'en',
            'status' => 1,
            'created_by' => 710001,
            'updated_by' => 710002,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $tagId = DB::table('tags')->insertGetId([
            'uuid' => $ids['tag_uuid'],
            'name' => 'Snapshot tag',
            'slug' => 'snapshot-tag',
            'status' => 1,
            'created_by' => 710001,
            'updated_by' => 710002,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('page_tag_modules')->insert([
            'uuid' => $ids['page_tag_uuid'],
            'page_id' => $pageId,
            'tag_id' => $tagId,
            'created_by' => 710001,
            'updated_by' => 710002,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('page_blocks')->insert([
            'uuid' => $ids['block_uuid'],
            'page_id' => $pageId,
            'type' => 'rich-text',
            'label' => 'Snapshot content block',
            'content' => json_encode(['html' => '<p>Snapshot block</p>'], JSON_THROW_ON_ERROR),
            'sort_order' => 1,
            'is_enabled' => 1,
            'created_by' => 710001,
            'updated_by' => 710002,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('seo_metadata')->insert([
            'seoable_type' => Page::class,
            'seoable_id' => $pageId,
            'locale' => 'en',
            'title' => 'Snapshot SEO title',
            'description' => 'Snapshot SEO description',
            'created_by' => 710001,
            'updated_by' => 710002,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('site_settings')->insert([
            [
                'group' => 'snapshot',
                'key' => 'snapshot_public_setting',
                'locale' => '*',
                'value' => 'PUBLIC-SITE-SETTING-CANARY',
                'type' => 'text',
                'is_public' => 1,
                'created_by' => 710001,
                'updated_by' => 710002,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'snapshot',
                'key' => 'snapshot_private_setting',
                'locale' => '*',
                'value' => 'PRIVATE-SITE-SETTING-CANARY',
                'type' => 'text',
                'is_public' => 0,
                'created_by' => 710001,
                'updated_by' => 710002,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $teamGroupId = DB::table('team_groups')->insertGetId([
            'uuid' => $ids['team_group_uuid'],
            'name' => 'Snapshot team group',
            'slug' => 'snapshot-team-group',
            'language' => 'en',
            'status' => 1,
            'created_by' => 710001,
            'updated_by' => 710002,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('latest_news')->insert([
            'name' => 'Snapshot team member',
            'type' => 'our-members',
            'category_id' => $categoryId,
            'team_group_id' => $teamGroupId,
            'email' => 'LATEST-NEWS-PRIVATE-EMAIL-CANARY@example.test',
            'language' => 'en',
            'status' => 1,
            'created_by' => 710001,
            'updated_by' => 710002,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $albumId = DB::table('albums')->insertGetId([
            'uuid' => $ids['album_uuid'],
            'name' => 'Snapshot album',
            'language' => 'en',
            'status' => 1,
            'created_by' => 710001,
            'updated_by' => 710002,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('galleries')->insert([
            'uuid' => $ids['gallery_uuid'],
            'album_id' => $albumId,
            'name' => 'Snapshot gallery image',
            'language' => 'en',
            'status' => 1,
            'created_by' => 710001,
            'updated_by' => 710002,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $causeGroupId = DB::table('donation_cause_groups')->insertGetId([
            'uuid' => $ids['donation_cause_group_uuid'],
            'name' => 'Snapshot cause group',
            'slug' => 'snapshot-cause-group',
            'status' => 1,
            'created_by' => 710001,
            'updated_by' => 710002,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('donation_types')->insert([
            'uuid' => $ids['donation_type_uuid'],
            'donation_cause_group_id' => $causeGroupId,
            'name' => 'Snapshot donation cause',
            'slug' => 'snapshot-donation-cause',
            'destination_type' => 'restricted_fund',
            'status' => 1,
            'created_by' => 710001,
            'updated_by' => 710002,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('notice_boards')->insert([
            'translation_key' => '91000000-0000-4000-8000-000000000013',
            'title' => 'Snapshot public notice',
            'slug' => 'snapshot-public-notice',
            'language' => 'en',
            'content_kind' => 'article',
            'file_path' => 'PRIVATE-NOTICE-FILE-CANARY.pdf',
            'ip' => '198.51.100.77',
            'status' => 1,
            'published_at' => '2026-08-21 00:00:00',
            'created_by' => 710001,
            'updated_by' => 710002,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->seedExcludedPrivateRecords($now);

        return $ids;
    }

    private function seedExcludedPrivateRecords(string $now): void
    {
        DB::table('admins')->insert([
            'name' => 'Snapshot private admin',
            'username' => 'snapshot-private-admin',
            'email' => 'snapshot-admin@example.test',
            'password' => 'SNAPSHOT-ADMIN-PASSWORD-CANARY',
            'role' => (string) DB::table('roles')->orderBy('id')->value('id'),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('users')->insert([
            'name' => 'Snapshot private member',
            'email' => 'snapshot-member@example.test',
            'password' => 'SNAPSHOT-MEMBER-PASSWORD-CANARY',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('donations')->insert([
            'uuid' => '91000000-0000-4000-8000-000000000014',
            'donor_name' => 'Snapshot private donor',
            'email' => 'SNAPSHOT-DONOR-EMAIL-CANARY@example.test',
            'amount' => 25,
            'transaction_id' => 'SNAPSHOT-DONATION-TRANSACTION-CANARY',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('volunteers')->insert([
            'name' => 'Snapshot private volunteer',
            'email' => 'SNAPSHOT-VOLUNTEER-EMAIL-CANARY@example.test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('ssl_commerz_transactions')->insert([
            'tran_id' => 'SNAPSHOT-TRANSACTION-CANARY',
            'cus_email' => 'SNAPSHOT-TRANSACTION-CUSTOMER-CANARY@example.test',
            'status' => 'PENDING',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('admin_audit_events')->insert([
            'event_uuid' => '91000000-0000-4000-8000-000000000015',
            'actor_name_snapshot' => 'SNAPSHOT-AUDIT-ACTOR-CANARY',
            'action' => 'snapshot.security.canary',
            'ip_hash' => 'SNAPSHOT-AUDIT-IP-HASH-CANARY',
            'created_at' => $now,
        ]);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $tables
     */
    private function assertGitSafeRecordFields(array $tables): void
    {
        $forbiddenFields = [
            'created_at',
            'updated_at',
            'deleted_at',
            'created_by',
            'updated_by',
            'deleted_by',
            'uploaded_by',
            'publish_by',
            'published_by',
            'review_requested_by',
            'reviewed_by',
            'actor_admin_id',
            'actor_name_snapshot',
            'author_admin_id',
            'ip',
            'ip_address',
            'ip_hash',
            'user_agent',
            'user_agent_hash',
            'hits',
            'last_hit_at',
        ];

        foreach ($tables as $table => $records) {
            $this->assertIsArray($records, "Snapshot table {$table} must contain a record list.");

            foreach ($records as $record) {
                $this->assertArrayNotHasKey('id', $record, "{$table} leaked a primary key.");

                foreach ($record as $field => $value) {
                    $this->assertNotContains($field, $forbiddenFields, "{$table}.{$field} is not Git-safe.");
                    $this->assertStringNotContainsString('click', strtolower($field), "{$table}.{$field} leaked click analytics.");

                    if (str_ends_with($field, '_id') && $value !== null) {
                        $isNumericIdentifier = is_int($value)
                            || (is_string($value) && ctype_digit($value));
                        $this->assertFalse(
                            $isNumericIdentifier,
                            "{$table}.{$field} retained a database-local numeric relation."
                        );
                    }
                }
            }
        }
    }

    private function exportSnapshot(string $output): void
    {
        $exitCode = Artisan::call('cms:snapshot', ['--output' => $output]);
        $commandOutput = Artisan::output();

        $this->assertSame(0, $exitCode, $commandOutput);
        $this->assertStringContainsString("CMS content snapshot written to {$output}.", $commandOutput);
    }

    private function mockSeederSnapshot(string $json, int $reads = 1): void
    {
        $path = database_path('seeders/seed-data/cms-content.snapshot.json');
        $filesystem = File::partialMock();
        $filesystem->shouldReceive('isFile')->times($reads)->with($path)->andReturnTrue();
        $filesystem->shouldReceive('isReadable')->times($reads)->with($path)->andReturnTrue();
        $filesystem->shouldReceive('get')->times($reads)->with($path)->andReturn($json);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    private function row(array $snapshot, string $table, string $field, string $value): array
    {
        $this->assertArrayHasKey($table, $snapshot['tables']);

        foreach ($snapshot['tables'][$table] as $record) {
            if (($record[$field] ?? null) === $value) {
                return $record;
            }
        }

        $this->fail("Snapshot table {$table} did not contain {$field}={$value}.");
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function temporaryOutput(): array
    {
        $relative = 'database/seeders/seed-data/.cms-content-snapshot-test-' . Str::uuid() . '.json';
        $absolute = base_path($relative);
        File::ensureDirectoryExists(dirname($absolute));
        $this->temporarySnapshots[] = $absolute;

        return [$relative, $absolute];
    }
}
