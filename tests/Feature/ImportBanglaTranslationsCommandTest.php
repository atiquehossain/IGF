<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\TranslationLocale;
use App\Models\TranslationString;
use App\Services\BanglaTranslationCatalogImporter;
use App\Services\TranslationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportBanglaTranslationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_export_is_read_only_and_contains_every_current_translation_row(): void
    {
        $relativePath = 'resources/translations/.command-test-read-only/testing-bangla-catalog.json';
        $absolutePath = base_path($relativePath);
        $testDirectory = dirname($absolutePath);
        File::deleteDirectory($testDirectory);

        try {
            $this->artisan('translations:bangla', [
                '--catalog' => $relativePath,
                '--export-template' => true,
            ])->assertSuccessful();

            $catalog = json_decode(File::get($absolutePath), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(BanglaTranslationCatalogImporter::SCHEMA, $catalog['schema']);
            $this->assertCount(
                count(app(BanglaTranslationCatalogImporter::class)->catalog()['entries']),
                $catalog['entries']
            );
            $this->assertSame(0, TranslationString::query()->where('locale', 'bn')->count());
            $this->assertSame(0, SiteSetting::query()->where('locale', 'bn')->count());
        } finally {
            File::deleteDirectory($testDirectory);
        }
    }

    public function test_apply_requires_an_explicit_active_owner_super_admin(): void
    {
        $relativePath = 'resources/translations/testing-bangla-empty-catalog.json';
        $absolutePath = base_path($relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, json_encode([
            'schema' => BanglaTranslationCatalogImporter::SCHEMA,
            'source_locale' => 'en',
            'target_locale' => 'bn',
            'entries' => [],
        ], JSON_THROW_ON_ERROR));

        try {
            $this->artisan('translations:bangla', [
                '--catalog' => $relativePath,
                '--apply' => true,
            ])
                ->expectsOutputToContain('--admin must identify the active owner/super-admin')
                ->assertFailed();
        } finally {
            File::delete($absolutePath);
        }
    }

    public function test_reviewed_catalog_can_fill_every_row_without_activating_bangla(): void
    {
        $role = Role::query()->create([
            'name' => 'Translation Deployment Owner',
            'security_rank' => 0,
            'is_owner' => true,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $admin = Admin::query()->create([
            'name' => 'Translation Owner',
            'username' => 'translation-owner',
            'email' => 'translation-owner@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
        $catalog = app(BanglaTranslationCatalogImporter::class)->catalog();
        foreach ($catalog['entries'] as &$entry) {
            $entry['translation'] = 'বাংলা '.$entry['source'];
        }
        unset($entry);

        $relativePath = 'resources/translations/testing-bangla-complete-catalog.json';
        $absolutePath = base_path($relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, json_encode(
            $catalog,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));

        try {
            $this->artisan('translations:bangla', [
                '--catalog' => $relativePath,
                '--apply' => true,
                '--admin' => (string) $admin->id,
            ])->assertSuccessful();

            $rows = app(TranslationCenterService::class)->rows('en', 'bn');
            $this->assertSame(0, app(TranslationCenterService::class)->summary($rows)['missing']);
            $this->assertFalse((bool) TranslationLocale::query()->where('locale', 'bn')->value('is_enabled'));
            $this->assertDatabaseMissing('site_settings', [
                'group' => 'header',
                'key' => 'show_language_switcher',
                'locale' => 'en',
                'value' => '1',
            ]);
        } finally {
            File::delete($absolutePath);
        }
    }

    public function test_catalog_path_cannot_escape_the_repository(): void
    {
        $this->artisan('translations:bangla', [
            '--catalog' => '../outside.json',
        ])
            ->expectsOutputToContain('cannot contain parent traversal')
            ->assertFailed();
    }

    public function test_force_export_cannot_overwrite_another_repository_file(): void
    {
        $composerPath = base_path('composer.json');
        $before = hash_file('sha256', $composerPath);

        $this->artisan('translations:bangla', [
            '--catalog' => 'composer.json',
            '--export-template' => true,
            '--force' => true,
        ])
            ->expectsOutputToContain('inside resources/translations/')
            ->assertFailed();

        $this->assertSame($before, hash_file('sha256', $composerPath));
    }
}
