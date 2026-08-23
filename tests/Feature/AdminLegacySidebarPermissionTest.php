<?php

namespace Tests\Feature;

use App\Helper\MyMenu;
use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Role;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminLegacySidebarPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionRegistrySeeder::class);
    }

    public function test_stale_serialized_leaf_cannot_expose_a_forbidden_link_or_empty_parent_groups(): void
    {
        $donations = AuthMenu::where('link', 'donations.index')->firstOrFail();
        $serial = [[
            'id' => 9001,
            'name' => 'Stale finance tools',
            'link' => '',
            'icon' => 'fa-money',
            'children' => [[
                'id' => 9002,
                'name' => 'Stale nested group',
                'link' => '',
                'icon' => 'fa-folder',
                'children' => [[
                    'id' => $donations->id,
                    'name' => 'Stale donation records',
                    'link' => 'donations.index',
                    'icon' => 'fa-money',
                    'children' => [],
                ]],
            ]],
        ]];
        $admin = $this->makeAdmin('Restricted legacy viewer', '', $serial);

        $this->actingAs($admin, 'admin')
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertDontSee('Stale finance tools')
            ->assertDontSee('Stale nested group')
            ->assertDontSee('Stale donation records')
            ->assertDontSee('Advanced & Legacy Tools', false)
            ->assertDontSee(route('donations.index'), false);

        $this->get(route('donations.index'))->assertForbidden();
    }

    public function test_current_permissions_replace_stale_serialized_labels_and_remove_forbidden_siblings(): void
    {
        $donations = AuthMenu::where('link', 'donations.index')->firstOrFail();
        $adminUsers = AuthMenu::where('link', 'admin.index')->firstOrFail();
        $serial = [[
            'id' => 9101,
            'name' => 'Legacy owner tools',
            'link' => 'admin.index',
            'icon' => 'fa-cogs',
            'children' => [
                [
                    'id' => $donations->id,
                    'name' => 'Legacy donation queue',
                    'link' => 'donations.index',
                    'icon' => 'fa-money',
                    'children' => [],
                ],
                [
                    'id' => 9102,
                    'name' => 'Forbidden user tools',
                    'link' => '',
                    'icon' => 'fa-lock',
                    'children' => [[
                        'id' => $adminUsers->id,
                        'name' => 'Forbidden legacy users',
                        'link' => 'admin.index',
                        'icon' => 'fa-user',
                        'children' => [],
                    ]],
                ],
            ],
        ]];
        $owner = $this->makeAdmin('Super Admin', (string) $donations->id, $serial);

        $response = $this->actingAs($owner, 'admin')
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertDontSee('Advanced & Legacy Tools', false)
            ->assertDontSee('Legacy owner tools')
            ->assertDontSee('Legacy donation queue')
            ->assertSee('Donation Records')
            ->assertSee(route('donations.index'), false)
            ->assertDontSee('Forbidden user tools')
            ->assertDontSee('Forbidden legacy users')
            ->assertDontSee(route('admin.index'), false);

        $this->assertSame(1, substr_count($response->getContent(), 'href="' . route('donations.index') . '"'));

        $this->get(route('donations.index'))->assertOk();
        $this->get(route('admin.index'))->assertForbidden();
    }

    public function test_forbidden_matching_leaf_never_receives_current_page_semantics(): void
    {
        $now = now();
        DB::table('auth_menus')->updateOrInsert(
            ['id' => 2],
            [
                'parent_id' => null,
                'name' => 'Settings',
                'link' => 'dashboard.index',
                'icon' => null,
                'order_by' => 10,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $district = AuthMenu::where('link', 'district.index')->firstOrFail();
        $admin = $this->makeAdmin('District-only legacy viewer', (string) $district->id, []);
        Auth::guard('admin')->login($admin);

        $currentRoute = new \ReflectionProperty(app('router'), 'current');
        $currentRoute->setValue(app('router'), app('router')->getRoutes()->getByName('division.index'));
        $forbiddenMatchHtml = MyMenu::menuUi();

        $this->assertStringNotContainsString(route('division.index'), $forbiddenMatchHtml);
        $this->assertStringContainsString(route('district.index'), $forbiddenMatchHtml);
        $this->assertSame(0, substr_count($forbiddenMatchHtml, 'aria-current="page"'));

        $currentRoute->setValue(app('router'), app('router')->getRoutes()->getByName('district.index'));
        $permittedMatchHtml = MyMenu::menuUi();
        $this->assertSame(1, substr_count($permittedMatchHtml, 'aria-current="page"'));

        $document = new \DOMDocument();
        @$document->loadHTML('<!doctype html><html><body><ul>'.$permittedMatchHtml.'</ul></body></html>');
        $currentLink = (new \DOMXPath($document))->query('//a[@aria-current="page"]')->item(0);
        $this->assertSame(route('district.index'), $currentLink->getAttribute('href'));
    }

    private function makeAdmin(string $name, string $menuPermissions, array $serial): Admin
    {
        $suffix = Str::lower(Str::random(8));
        $role = Role::create([
            'name' => $name,
            'permission' => $menuPermissions,
            'actionPermission' => '',
            'serial' => json_encode($serial, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'status' => 1,
        ]);

        return Admin::create([
            'name' => $name,
            'username' => Str::slug($name) . '-' . $suffix,
            'email' => Str::slug($name) . '-' . $suffix . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
