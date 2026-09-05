<?php

namespace Tests\Feature;

use App\Helper\MyMenu;
use App\Models\Admin;
use App\Models\PageMenu;
use App\Models\Role;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UtilityNavigationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_is_backfilled_for_both_locales_and_shared_with_public_pages(): void
    {
        $field = config('site-settings.groups.header.fields.utility_navigation_label');
        $this->assertTrue($field['localized']);
        $this->assertTrue($field['public']);
        $this->assertSame('Utility navigation', app(SiteSettingService::class)->values('en', true)['header']['utility_navigation_label']);
        $this->assertSame('উপরের নেভিগেশন', app(SiteSettingService::class)->values('bn', true)['header']['utility_navigation_label']);

        $this->assertDatabaseHas('page_menus', [
            'uuid' => '79000000-0000-4000-8000-000000000001',
            'type' => 'utility',
            'language' => 'en',
            'name' => 'Contact',
            'slug' => '/contact-us',
            'status' => 1,
        ]);
        $this->assertDatabaseHas('page_menus', [
            'uuid' => '79000000-0000-4000-8000-000000000001',
            'type' => 'utility',
            'language' => 'bn',
            'name' => 'যোগাযোগ',
            'slug' => '/contact-us',
            'status' => 1,
        ]);

        $this->get(route('frontend.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('appUtilityMenus', 1)
                ->where('appUtilityMenus.0.name', 'Contact')
                ->where('appUtilityMenus.0.slug', '/contact-us')
                ->where('appUtilityMenus.0.children', [])
            );
    }

    public function test_backfill_never_overwrites_an_existing_utility_editorial_choice(): void
    {
        $contact = PageMenu::query()
            ->where('type', 'utility')
            ->where('language', 'en')
            ->firstOrFail();
        $contact->update(['name' => 'Talk to our team', 'slug' => '/custom-contact']);

        $migration = require database_path('migrations/2026_09_05_130000_seed_editable_utility_navigation.php');
        $migration->up();

        $this->assertSame(1, PageMenu::query()->where('type', 'utility')->where('language', 'en')->count());
        $this->assertDatabaseHas('page_menus', [
            'id' => $contact->id,
            'name' => 'Talk to our team',
            'slug' => '/custom-contact',
        ]);
    }

    public function test_utility_location_supports_localized_three_level_crud_order_publication_and_trash(): void
    {
        $admin = $this->owner();

        $this->actingAs($admin, 'admin')->get(route('page.menu.index', ['location' => 'utility', 'locale' => 'en']))
            ->assertOk()
            ->assertSee('<option value="utility" selected>', false)
            ->assertSee('Utility bar');

        $root = $this->createUtility($admin, 'Resources', 'en', null, 'label');
        $child = $this->createUtility($admin, 'Reports', 'en', $root['uuid'], 'custom', '/annual-report');
        $grandchild = $this->createUtility($admin, 'Latest report', 'en', $child['uuid'], 'custom', 'https://example.test/latest');
        $bangla = $this->createUtility($admin, 'সহায়তা', 'bn', null, 'custom', '/contact-us');

        $this->actingAs($admin, 'admin')->putJson(route('page.menu.item.update', $grandchild['uuid']), [
            'locale' => 'en',
            'label' => 'Latest annual report',
            'description' => 'Read the newest audited report.',
            'enabled' => true,
            'custom_url' => '/annual-report/latest',
        ])->assertOk()->assertJsonPath('item.name', 'Latest annual report');

        $englishItems = PageMenu::query()
            ->where('type', 'utility')
            ->where('language', 'en')
            ->get()
            ->keyBy('uuid');
        $contact = $englishItems['79000000-0000-4000-8000-000000000001'];

        $this->actingAs($admin, 'admin')->putJson(route('page.menu.reorder'), [
            'locale' => 'en',
            'location' => 'utility',
            'items' => [
                ['uuid' => $root['uuid'], 'parent_uuid' => null, 'order' => 0],
                ['uuid' => $child['uuid'], 'parent_uuid' => $root['uuid'], 'order' => 0],
                ['uuid' => $grandchild['uuid'], 'parent_uuid' => $child['uuid'], 'order' => 0],
                ['uuid' => $contact->uuid, 'parent_uuid' => null, 'order' => 1],
            ],
        ])->assertOk();

        $this->actingAs($admin, 'admin')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->putJson(route('page.menu.status', $root['uuid']))
            ->assertOk();
        $this->assertDatabaseHas('page_menus', ['uuid' => $root['uuid'], 'status' => 0]);

        $this->actingAs($admin, 'admin')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->putJson(route('page.menu.status', $root['uuid']))
            ->assertOk();
        $this->assertDatabaseHas('page_menus', ['uuid' => $root['uuid'], 'status' => 1]);

        $this->actingAs($admin, 'admin')->deleteJson(route('page.menu.destroy', $grandchild['uuid']))->assertOk();
        $this->assertSoftDeleted('page_menus', ['uuid' => $grandchild['uuid']]);
        $this->actingAs($admin, 'admin')->get(route('page.menu.trash'))->assertOk()->assertSee('Latest annual report');
        $this->actingAs($admin, 'admin')->post(route('page.menu.restore', $grandchild['uuid']))->assertRedirect();
        $this->assertNotSoftDeleted('page_menus', ['uuid' => $grandchild['uuid']]);

        $public = MyMenu::frontMenus('en', 'utility')->values();
        $this->assertSame(['Resources', 'Contact'], $public->pluck('name')->all());
        $this->assertSame('Reports', $public[0]->children[0]->name);
        $this->assertSame('Latest annual report', $public[0]->children[0]->children[0]->name);
        $this->assertSame('/annual-report/latest', $public[0]->children[0]->children[0]->slug);

        $this->actingAs($admin, 'admin')->get(route('page.menu.index', ['location' => 'utility', 'locale' => 'bn']))
            ->assertOk()
            ->assertSee('সহায়তা');
        $this->assertTrue(MyMenu::frontMenus('bn', 'utility')->contains('uuid', $bangla['uuid']));

        $this->actingAs($admin, 'admin')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->putJson(route('page.menu.status', $bangla['uuid']))
            ->assertOk();
        $this->assertDatabaseHas('page_menus', ['uuid' => $bangla['uuid'], 'language' => 'bn', 'status' => 0]);
    }

    private function createUtility(
        Admin $admin,
        string $label,
        string $locale,
        ?string $parentUuid,
        string $destinationType,
        string $destination = ''
    ): array {
        return $this->actingAs($admin, 'admin')->postJson(route('page.menu.store'), [
            'simple' => true,
            'locale' => $locale,
            'location' => 'utility',
            'label' => $label,
            'description' => '',
            'destination_type' => $destinationType,
            'destination' => $destination,
            'parent_uuid' => $parentUuid,
            'enabled' => true,
        ])->assertCreated()->json('item');
    }

    private function owner(): Admin
    {
        $role = Role::create([
            'name' => 'Navigation owner',
            'is_owner' => true,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Navigation Owner',
            'username' => 'navigation-owner-'.Str::lower(Str::random(8)),
            'email' => 'navigation-owner-'.Str::lower(Str::random(8)).'@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
