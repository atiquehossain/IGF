<?php

namespace Tests\Feature;

use App\Models\TranslationLocale;
use App\Services\LocalizationManager;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BanglaReleaseSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_configuration_certifies_bangla_but_database_activation_remains_the_public_gate(): void
    {
        $this->assertSame(['en', 'bn'], config('localization.public_locales'));
        $this->assertTrue(config('localization.public_switcher_enabled'));

        $localization = app(LocalizationManager::class);
        $this->assertSame(['en'], $localization->publicLocales());
        $this->assertFalse($localization->switcherEnabled());
        $this->get('/language/bn')->assertNotFound();

        TranslationLocale::query()->whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);

        $this->assertSame(['en', 'bn'], $localization->publicLocales());
        $this->assertTrue($localization->switcherEnabled());
        $this->get('/language/bn')->assertRedirect();
        $this->assertSame('bn', session('locale'));
    }

    public function test_legacy_bangla_json_has_exactly_the_same_leaf_contract_as_english(): void
    {
        $english = $this->jsonLeaves(resource_path('lang/en.json'));
        $bangla = $this->jsonLeaves(resource_path('lang/bn.json'));

        $this->assertNotEmpty($english);
        $this->assertSame(array_keys($english), array_keys($bangla));
        $this->assertMatchesRegularExpression('/\p{Bengali}/u', implode(' ', $bangla));

        foreach ($english as $key => $value) {
            $this->assertSame(
                $this->placeholders($value),
                $this->placeholders($bangla[$key]),
                "Placeholder contract differs for legacy translation [{$key}]."
            );
        }
    }

    public function test_bangla_validation_and_donation_resources_preserve_english_placeholders(): void
    {
        foreach (['validation', 'donation'] as $resource) {
            $englishPath = resource_path("lang/en/{$resource}.php");
            $banglaPath = resource_path("lang/bn/{$resource}.php");

            $this->assertFileExists($englishPath);
            $this->assertFileExists($banglaPath);

            $english = $this->arrayLeaves(require $englishPath);
            $bangla = $this->arrayLeaves(require $banglaPath);

            $this->assertEmpty(
                array_diff(array_keys($english), array_keys($bangla)),
                "Bangla {$resource} messages must cover every English message key."
            );

            foreach ($english as $key => $value) {
                $this->assertSame(
                    $this->placeholders($value),
                    $this->placeholders($bangla[$key]),
                    "Placeholder contract differs for {$resource}.{$key}."
                );
            }
        }
    }

    public function test_about_statement_accessible_label_is_admin_editable_and_has_a_curated_bangla_default(): void
    {
        $settings = app(SiteSettingService::class);

        $this->assertSame(
            'About Ignite Global Foundation',
            data_get($settings->values('en', true), 'shared_blocks.about_statement_label')
        );
        $this->assertSame(
            'ইগনাইট গ্লোবাল ফাউন্ডেশন সম্পর্কে',
            data_get($settings->values('bn', true), 'shared_blocks.about_statement_label')
        );

        $field = config('site-settings.groups.shared_blocks.fields.about_statement_label');
        $this->assertTrue($field['localized']);
        $this->assertTrue($field['public']);

        $about = file_get_contents(resource_path('js/Pages/about.vue'));
        $this->assertStringContainsString(':aria-label="copy.aboutStatementLabel"', $about);
        $this->assertStringContainsString('shared_blocks?.about_statement_label', $about);
        $this->assertStringNotContainsString('aria-label="About Ignite Global Foundation"', $about);
    }

    public function test_bangla_has_localized_number_and_date_formats_that_admins_can_change(): void
    {
        $number = config('site-settings.groups.regional.fields.number_locale');
        $date = config('site-settings.groups.regional.fields.date_locale');

        $this->assertTrue($number['localized']);
        $this->assertTrue($date['localized']);
        $this->assertSame('bn-BD', $number['localized_defaults']['bn']);
        $this->assertSame('bn-BD', $date['localized_defaults']['bn']);

        $settings = app(SiteSettingService::class)->values('bn', true);
        $this->assertSame('bn-BD', data_get($settings, 'regional.number_locale'));
        $this->assertSame('bn-BD', data_get($settings, 'regional.date_locale'));
    }

    /** @return array<string, string> */
    private function jsonLeaves(string $path): array
    {
        $this->assertFileExists($path);
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $this->arrayLeaves($decoded);
    }

    /** @return array<string, string> */
    private function arrayLeaves(array $values, string $prefix = ''): array
    {
        $leaves = [];
        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $leaves += $this->arrayLeaves($value, $path);
                continue;
            }
            $leaves[$path] = (string) $value;
        }
        ksort($leaves);

        return $leaves;
    }

    /** @return list<string> */
    private function placeholders(string $value): array
    {
        preg_match_all('/(?<!:):[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);
        $placeholders = $matches[0];
        sort($placeholders);

        return $placeholders;
    }
}
