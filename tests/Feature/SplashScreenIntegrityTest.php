<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\SplashScreen;
use App\Services\TranslationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SplashScreenIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_enabled_announcement_is_safely_shared_with_public_pages(): void
    {
        $uuid = (string) Str::uuid();
        $releaseDate = now()->subDay()->startOfDay();
        $announcement = SplashScreen::create([
            'uuid' => $uuid,
            'title' => 'Important visitor update',
            'details' => '<p>Office hours changed.</p><script>alert(1)</script>',
            'language' => 'en',
            'status' => 1,
            'published_at' => $releaseDate,
        ]);

        $this->get(route('frontend.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('splashScreen.title', 'Important visitor update')
                ->where('splashScreen.public_version', $announcement->publicVersion())
                ->where('splashScreen.published_at', $releaseDate->format('Y-m-d'))
                ->where('splashScreen.details', fn ($html) => str_contains($html, 'Office hours changed.')
                    && !str_contains(strtolower($html), '<script'))
                ->where('siteSettings.splash.dismiss_label', 'Not now')
                ->where('siteSettings.splash.continue_label', 'Continue to website')
            );
    }

    public function test_disabled_or_future_announcements_are_not_shared(): void
    {
        SplashScreen::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Disabled',
            'details' => 'Hidden',
            'language' => 'en',
            'status' => 0,
            'published_at' => now()->subDay(),
        ]);
        SplashScreen::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Future',
            'details' => 'Not released yet',
            'language' => 'en',
            'status' => 1,
            'published_at' => now()->addDay(),
        ]);

        $this->get(route('frontend.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('splashScreen', null));
    }

    public function test_public_shell_renders_versioned_dismissible_announcement(): void
    {
        $layout = file_get_contents(resource_path('js/layouts/App.vue'));
        $announcement = file_get_contents(resource_path('js/layouts/AppSplashScreen.vue'));

        $this->assertStringContainsString('<AppSplashScreen />', $layout);
        $this->assertStringContainsString("inertiaPage.props?.splashScreen", $announcement);
        $this->assertStringContainsString('public_version', $announcement);
        $this->assertStringContainsString('$cookies.set(cookieKey.value, \'1\', \'30d\', \'/\')', $announcement);
        $this->assertStringContainsString('settings.dismiss_label', $announcement);
        $this->assertStringContainsString('settings.continue_label', $announcement);
    }

    public function test_saving_an_announcement_releases_one_current_version_only(): void
    {
        $selected = SplashScreen::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Selected announcement',
            'details' => 'Old selected copy',
            'language' => 'en',
            'status' => 1,
            'published_at' => now()->subDays(2),
        ]);
        $older = SplashScreen::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Older announcement',
            'details' => 'Old copy',
            'language' => 'en',
            'status' => 1,
            'published_at' => now()->subDays(3),
        ]);

        $this->actingAs($this->makeAnnouncementEditor(), 'admin')
            ->post(route('splash.screen.store'), [
                'enabled' => '1',
                'id' => ['en' => $selected->id],
                'language' => ['en' => 'en'],
                'title' => ['en' => 'New visitor notice'],
                'details' => ['en' => '<p>New managed copy</p>'],
                'published_at' => ['en' => now()->format('d-m-Y')],
            ])
            ->assertRedirect(route('splash.screen.index'));

        $this->assertFalse((bool) $older->fresh()->status);
        $this->assertTrue((bool) $selected->fresh()->status);
        $this->assertSame('New visitor notice', $selected->fresh()->title);
        $this->assertNotSame($older->uuid, $selected->fresh()->uuid);
    }

    public function test_release_date_requires_the_admin_date_format(): void
    {
        $this->actingAs($this->makeAnnouncementEditor(), 'admin')
            ->post(route('splash.screen.store'), [
                'enabled' => '1',
                'language' => ['en' => 'en'],
                'title' => ['en' => 'Visitor notice'],
                'details' => ['en' => 'Managed details'],
                'published_at' => ['en' => 'not-a-date'],
            ])
            ->assertSessionHasErrors('published_at.en');

        $this->assertDatabaseCount('splash_screens', 0);
    }

    public function test_empty_admin_form_defaults_to_today_instead_of_the_unix_epoch(): void
    {
        $this->actingAs($this->makeAnnouncementEditor(), 'admin')
            ->get(route('splash.screen.index'))
            ->assertOk()
            ->assertSee('value="' . now()->format('d-m-Y') . '"', false)
            ->assertDontSee('01-01-1970');
    }

    public function test_translation_edit_changes_public_version_without_breaking_locale_pairing(): void
    {
        $uuid = (string) Str::uuid();
        SplashScreen::create([
            'uuid' => $uuid,
            'title' => 'English notice',
            'details' => 'English details',
            'language' => 'en',
            'status' => 1,
            'published_at' => today(),
        ]);
        $target = SplashScreen::create([
            'uuid' => $uuid,
            'title' => 'পুরোনো নোটিশ',
            'details' => 'পুরোনো বিবরণ',
            'language' => 'bn',
            'status' => 1,
            'published_at' => today(),
        ]);
        $before = $target->publicVersion();
        $translations = app(TranslationCenterService::class);
        $row = $translations->rows('en', 'bn')->first(fn (array $candidate) =>
            data_get($candidate, 'identity.model') === 'splash_screen'
            && data_get($candidate, 'identity.field') === 'title'
        );

        $this->assertNotNull($row);
        $translations->save('en', 'bn', [[
            'key' => $row['key'],
            'precondition' => $row['precondition'],
            'value' => 'সংশোধিত নোটিশ',
        ]], null);

        $fresh = $target->fresh();
        $this->assertSame($uuid, $fresh->uuid);
        $this->assertSame('সংশোধিত নোটিশ', $fresh->title);
        $this->assertNotSame($before, $fresh->publicVersion());
    }

    private function makeAnnouncementEditor(): Admin
    {
        $menu = AuthMenu::create(['name' => 'Visitor announcement', 'link' => 'splash.screen.index', 'status' => 1]);
        $action = MenuAction::create([
            'auth_menu_id' => $menu->id,
            'name' => 'Edit visitor announcement',
            'link' => 'splash.screen.create',
            'status' => 1,
        ]);
        $role = Role::create([
            'name' => 'Announcement editor',
            'permission' => (string) $menu->id,
            'actionPermission' => (string) $action->id,
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Announcement QA',
            'username' => 'announcement-qa',
            'email' => 'announcement-qa@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
