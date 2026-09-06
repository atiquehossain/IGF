<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\SiteSettingRevision;
use App\Services\SiteSettingRevisionService;
use App\Services\SiteSettingVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebsiteCustomizerRevisionSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.localization', true);
        config()->set('sslcommerz.store_id', 'customizer-revision-store');
        config()->set('sslcommerz.store_password', 'customizer-revision-password');
        config()->set('sslcommerz.payment_methods.bkash.enabled', true);
        config()->set('sslcommerz.payment_methods.bkash.gateway_filter', 'bkash');
        config()->set('sslcommerz.payment_methods.nagad.enabled', false);
        config()->set('sslcommerz.payment_methods.nagad.gateway_filter', null);
        config()->set('sslcommerz.payment_methods.card.enabled', true);
        config()->set('sslcommerz.payment_methods.card.gateway_filter', 'visacard');
    }

    public function test_each_save_captures_the_selected_locale_and_audits_changed_fields(): void
    {
        $admin = $this->editor(['site.settings.edit', 'site.settings.restore']);
        SiteSetting::query()->create([
            'group' => 'branding',
            'key' => 'site_name',
            'locale' => 'en',
            'value' => 'Earlier website name',
            'type' => 'text',
            'is_public' => true,
        ]);
        $settings = $this->defaultSettings();
        $settings['branding']['site_name'] = 'Current website name';

        $this->actingAs($admin, 'admin')
            ->put(route('site.settings.update'), $this->updatePayload($settings, 'en'))
            ->assertRedirect(route('site.settings.index', ['locale' => 'en']));

        $revision = SiteSettingRevision::query()->sole();
        $this->assertSame('en', $revision->locale);
        $this->assertTrue($revision->snapshot['settings']['branding']['site_name']['stored']);
        $this->assertSame('Earlier website name', $revision->snapshot['settings']['branding']['site_name']['value']);
        $this->assertSame('Earlier website name', $revision->snapshot['settings']['branding']['site_name']['effective']);
        $this->assertDatabaseHas('admin_audit_events', [
            'actor_admin_id' => $admin->id,
            'action' => 'site_settings.saved',
            'outcome' => 'success',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('site.settings.index', ['locale' => 'en']))
            ->assertOk()
            ->assertSee('Revision history')
            ->assertSee('Preview changes')
            ->assertSee('Earlier website name')
            ->assertSee('Current website name')
            ->assertSee('Restore this version');
    }

    public function test_a_localized_change_invalidates_a_stale_token_for_that_language(): void
    {
        $admin = $this->editor(['site.settings.edit']);
        $staleBanglaVersion = app(SiteSettingVersionService::class)->current('bn');
        SiteSetting::query()->create([
            'group' => 'branding',
            'key' => 'site_name',
            'locale' => 'bn',
            'value' => 'সমসাময়িক সম্পাদনা',
            'type' => 'text',
            'is_public' => true,
        ]);
        $settings = $this->defaultSettings();
        $settings['branding']['site_name'] = 'অচল ফর্মের লেখা';

        $this->actingAs($admin, 'admin')
            ->from(route('site.settings.index', ['locale' => 'bn']))
            ->put(route('site.settings.update'), [
                'locale' => 'bn',
                'global_settings_version' => $staleBanglaVersion,
                'settings' => $settings,
            ])
            ->assertRedirect(route('site.settings.index', ['locale' => 'bn']))
            ->assertSessionHasErrors('global_settings_version');

        $this->assertDatabaseHas('site_settings', [
            'group' => 'branding',
            'key' => 'site_name',
            'locale' => 'bn',
            'value' => 'সমসাময়িক সম্পাদনা',
        ]);
        $this->assertDatabaseMissing('site_settings', ['value' => 'অচল ফর্মের লেখা']);
        $this->assertDatabaseCount('site_setting_revisions', 0);
    }

    public function test_restore_is_undoable_and_recovers_localized_and_shared_values(): void
    {
        $admin = $this->editor(['site.settings.edit', 'site.settings.restore']);
        foreach ([
            ['key' => 'site_name', 'locale' => 'en', 'value' => 'Recoverable name'],
            ['key' => 'logo', 'locale' => '*', 'value' => '/image/recoverable-logo.png'],
        ] as $seed) {
            SiteSetting::query()->create($seed + [
                'group' => 'branding',
                'type' => 'text',
                'is_public' => true,
            ]);
        }
        $settings = $this->defaultSettings();
        $settings['branding']['site_name'] = 'Replacement name';
        $settings['branding']['logo'] = '/image/replacement-logo.png';

        $this->actingAs($admin, 'admin')
            ->put(route('site.settings.update'), $this->updatePayload($settings, 'en'))
            ->assertSessionHasNoErrors();
        $restorePoint = SiteSettingRevision::query()->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('site.settings.revisions.restore', $restorePoint), [
                'locale' => 'en',
                'global_settings_version' => app(SiteSettingVersionService::class)->current('en'),
                'restore_confirmation' => '1',
            ])
            ->assertRedirect(route('site.settings.index', ['locale' => 'en']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('site_settings', [
            'group' => 'branding',
            'key' => 'site_name',
            'locale' => 'en',
            'value' => 'Recoverable name',
        ]);
        $this->assertDatabaseHas('site_settings', [
            'group' => 'branding',
            'key' => 'logo',
            'locale' => '*',
            'value' => '/image/recoverable-logo.png',
        ]);
        $this->assertDatabaseCount('site_setting_revisions', 2);
        $undoPoint = SiteSettingRevision::query()->latest('id')->firstOrFail();
        $this->assertStringStartsWith('Automatic backup before restoring revision', $undoPoint->reason);
        $this->assertSame('Replacement name', $undoPoint->snapshot['settings']['branding']['site_name']['effective']);
        $this->assertDatabaseHas('admin_audit_events', [
            'actor_admin_id' => $admin->id,
            'action' => 'site_settings.restored',
            'outcome' => 'success',
        ]);
    }

    public function test_restore_requires_its_own_permission_confirmation_and_current_locale_scope(): void
    {
        $restoreAdmin = $this->editor(['site.settings.edit', 'site.settings.restore']);
        $revision = app(SiteSettingRevisionService::class)->capture('en', 'Permission test', $restoreAdmin);
        $editorWithoutRestore = $this->editor(['site.settings.edit']);
        $validRequest = [
            'locale' => 'en',
            'global_settings_version' => app(SiteSettingVersionService::class)->current('en'),
            'restore_confirmation' => '1',
        ];

        $this->actingAs($editorWithoutRestore, 'admin')
            ->get(route('site.settings.index', ['locale' => 'en']))
            ->assertOk()
            ->assertSee('Revision history')
            ->assertDontSee('Restore this version');

        $this->actingAs($editorWithoutRestore, 'admin')
            ->post(route('site.settings.revisions.restore', $revision), $validRequest)
            ->assertForbidden();

        $this->actingAs($restoreAdmin, 'admin')
            ->from(route('site.settings.index', ['locale' => 'en']))
            ->post(route('site.settings.revisions.restore', $revision), array_diff_key(
                $validRequest,
                ['restore_confirmation' => true]
            ))
            ->assertRedirect(route('site.settings.index'))
            ->assertSessionHasErrors('restore_confirmation');

        $this->actingAs($restoreAdmin, 'admin')
            ->from(route('site.settings.index', ['locale' => 'bn']))
            ->post(route('site.settings.revisions.restore', $revision), array_replace($validRequest, ['locale' => 'bn']))
            ->assertRedirect(route('site.settings.index', ['locale' => 'bn']))
            ->assertSessionHasErrors('locale');

        SiteSetting::query()->create([
            'group' => 'branding',
            'key' => 'tagline',
            'locale' => 'en',
            'value' => 'A concurrent change',
            'type' => 'text',
            'is_public' => true,
        ]);
        $this->actingAs($restoreAdmin, 'admin')
            ->from(route('site.settings.index', ['locale' => 'en']))
            ->post(route('site.settings.revisions.restore', $revision), $validRequest)
            ->assertRedirect(route('site.settings.index'))
            ->assertSessionHasErrors('global_settings_version');

        $this->assertDatabaseCount('site_setting_revisions', 1);
        $this->assertDatabaseMissing('admin_audit_events', ['action' => 'site_settings.restored']);
    }

    public function test_field_reset_keeps_its_existing_ux_with_a_recoverable_snapshot(): void
    {
        $admin = $this->editor(['site.settings.destroy']);
        SiteSetting::query()->create([
            'group' => 'branding',
            'key' => 'tagline',
            'locale' => 'bn',
            'value' => 'ফিরিয়ে আনার মতো লেখা',
            'type' => 'text',
            'is_public' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->delete(route('site.settings.destroy', ['branding', 'tagline']), [
                'locale' => 'bn',
                'global_settings_version' => app(SiteSettingVersionService::class)->current('bn'),
            ])
            ->assertRedirect(route('site.settings.index', ['locale' => 'bn']))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('site_settings', [
            'group' => 'branding',
            'key' => 'tagline',
            'locale' => 'bn',
        ]);
        $revision = SiteSettingRevision::query()->sole();
        $this->assertSame('ফিরিয়ে আনার মতো লেখা', $revision->snapshot['settings']['branding']['tagline']['effective']);
        $this->assertDatabaseHas('admin_audit_events', [
            'actor_admin_id' => $admin->id,
            'action' => 'site_settings.reset',
        ]);
    }

    private function updatePayload(array $settings, string $locale): array
    {
        return [
            'locale' => $locale,
            'global_settings_version' => app(SiteSettingVersionService::class)->current($locale),
            'settings' => $settings,
        ];
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

    /** @param list<string> $actionLinks */
    private function editor(array $actionLinks): Admin
    {
        $menu = AuthMenu::query()->where('link', 'site.settings.index')->firstOrFail();
        $actions = MenuAction::query()->whereIn('link', $actionLinks)->get();
        $this->assertCount(count($actionLinks), $actions);
        $role = Role::query()->create([
            'name' => 'Customizer revision editor ' . Str::random(6),
            'permission' => (string) $menu->id,
            'actionPermission' => $actions->pluck('id')->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);
        $suffix = Str::lower(Str::random(10));

        return Admin::query()->create([
            'name' => 'Revision editor',
            'username' => 'revision-' . $suffix,
            'email' => 'revision-' . $suffix . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);
    }
}
