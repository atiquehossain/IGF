<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Services\SiteSettingVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebsiteCustomizerPayloadSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_customizer_markup_exposes_the_bounded_payload_contract(): void
    {
        $source = file_get_contents(resource_path('views/admin/site-settings/index.blade.php'));

        $this->assertStringContainsString("@vite('resources/js/admin/siteSettingsPayload.js')", $source);
        $this->assertStringContainsString('id="customizer-settings-payload"', $source);
        $this->assertStringContainsString('name="settings_payload" disabled', $source);
        $this->assertStringContainsString('enctype="multipart/form-data"', $source);
        $this->assertStringContainsString('data-setting-field', $source);
        $this->assertStringContainsString('data-setting-control', $source);
        $this->assertStringContainsString('data-settings-payload-max', $source);
        $this->assertGreaterThan(800, collect(config('site-settings.groups'))->sum(
            fn (array $group): int => count($group['fields'] ?? [])
        ));
    }

    public function test_bounded_json_submission_saves_checkbox_radio_and_faq_values_and_ignores_native_shadow_fields(): void
    {
        $admin = $this->editor();
        $settings = $this->defaultSettings();
        $settings['branding']['site_name'] = 'Payload website name';
        $settings['design']['card_columns'] = '4';
        $settings['header']['announcement_enabled'] = false;
        $settings['contact_page']['faqs'] = [
            ['question' => 'Visible question', 'answer' => 'Visible answer', 'is_active' => '1'],
            ['question' => 'Hidden question', 'answer' => 'Hidden answer', 'is_active' => '0'],
        ];
        $nativeShadow = $this->defaultSettings();
        $nativeShadow['branding']['site_name'] = 'Injected shadow value';

        $this->actingAs($admin, 'admin')
            ->put(route('site.settings.update'), [
                'locale' => 'en',
                'global_settings_version' => app(SiteSettingVersionService::class)->current('en'),
                'settings_payload' => json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'settings' => $nativeShadow,
            ])
            ->assertRedirect(route('site.settings.index', ['locale' => 'en']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('site_settings', [
            'group' => 'branding',
            'key' => 'site_name',
            'locale' => 'en',
            'value' => 'Payload website name',
        ]);
        $this->assertDatabaseHas('site_settings', [
            'group' => 'header',
            'key' => 'announcement_enabled',
            'locale' => '*',
            'value' => '0',
        ]);
        $this->assertDatabaseHas('site_settings', [
            'group' => 'design',
            'key' => 'card_columns',
            'locale' => '*',
            'value' => '4',
        ]);
        $faqValue = SiteSetting::query()
            ->where('group', 'contact_page')
            ->where('key', 'faqs')
            ->where('locale', 'en')
            ->value('value');
        $this->assertSame([
            ['question' => 'Visible question', 'answer' => 'Visible answer', 'is_active' => true],
            ['question' => 'Hidden question', 'answer' => 'Hidden answer', 'is_active' => false],
        ], json_decode((string) $faqValue, true, 512, JSON_THROW_ON_ERROR));
        $this->assertDatabaseMissing('site_settings', ['value' => 'Injected shadow value']);
        $this->assertDatabaseCount('site_setting_revisions', 1);
    }

    public function test_json_payload_rejects_unknown_or_missing_schema_fields_without_writing(): void
    {
        $admin = $this->editor();
        $unknown = $this->defaultSettings();
        $unknown['branding']['unconfigured_secret'] = 'must not persist';

        $this->actingAs($admin, 'admin')
            ->from(route('site.settings.index', ['locale' => 'en']))
            ->put(route('site.settings.update'), [
                'locale' => 'en',
                'global_settings_version' => app(SiteSettingVersionService::class)->current('en'),
                'settings_payload' => json_encode($unknown, JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('site.settings.index'))
            ->assertSessionHasErrors('settings_payload');

        $missing = $this->defaultSettings();
        unset($missing['branding']['site_name']);

        $this->actingAs($admin, 'admin')
            ->from(route('site.settings.index', ['locale' => 'en']))
            ->put(route('site.settings.update'), [
                'locale' => 'en',
                'global_settings_version' => app(SiteSettingVersionService::class)->current('en'),
                'settings_payload' => json_encode($missing, JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('site.settings.index'))
            ->assertSessionHasErrors('settings_payload');

        $this->assertDatabaseCount('site_settings', 0);
        $this->assertDatabaseCount('site_setting_revisions', 0);
    }

    public function test_non_javascript_submission_detects_truncation_and_decoded_validation_errors_keep_old_input(): void
    {
        $admin = $this->editor();
        $truncated = $this->defaultSettings();
        unset($truncated['branding']['site_name']);

        $this->actingAs($admin, 'admin')
            ->from(route('site.settings.index'))
            ->put(route('site.settings.update'), [
                'locale' => 'en',
                'global_settings_version' => app(SiteSettingVersionService::class)->current('en'),
                'settings' => $truncated,
            ])
            ->assertRedirect(route('site.settings.index'))
            ->assertSessionHasErrors('settings');

        $invalid = $this->defaultSettings();
        $invalid['branding']['site_name'] = str_repeat('x', 256);

        $this->actingAs($admin, 'admin')
            ->from(route('site.settings.index', ['locale' => 'en']))
            ->put(route('site.settings.update'), [
                'locale' => 'en',
                'global_settings_version' => app(SiteSettingVersionService::class)->current('en'),
                'settings_payload' => json_encode($invalid, JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('site.settings.index'))
            ->assertSessionHasErrors('settings.branding.site_name')
            ->assertSessionHasInput('settings.branding.site_name', $invalid['branding']['site_name']);

        $this->assertDatabaseCount('site_settings', 0);
        $this->assertDatabaseCount('site_setting_revisions', 0);
    }

    private function defaultSettings(): array
    {
        $settings = [];
        foreach (config('site-settings.groups', []) as $groupKey => $group) {
            foreach ($group['fields'] ?? [] as $key => $field) {
                $settings[$groupKey][$key] = $field['default'] ?? null;
            }
        }

        return $settings;
    }

    private function editor(): Admin
    {
        $menu = AuthMenu::query()->where('link', 'site.settings.index')->firstOrFail();
        $action = MenuAction::query()->where('link', 'site.settings.edit')->firstOrFail();
        $suffix = Str::lower(Str::random(10));
        $role = Role::query()->create([
            'name' => 'Customizer payload editor ' . $suffix,
            'permission' => (string) $menu->id,
            'actionPermission' => (string) $action->id,
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::query()->create([
            'name' => 'Customizer payload editor',
            'username' => 'payload-' . $suffix,
            'email' => 'payload-' . $suffix . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);
    }
}
