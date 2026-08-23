<?php

namespace Tests\Feature;

use App\Helper\MyMenu;
use App\Models\Admin;
use App\Models\Role;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSidebarCurrentRouteRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionRegistrySeeder::class);

        $now = now();
        foreach ([
            ['id' => 2, 'name' => 'Settings', 'order_by' => 10],
            ['id' => 3, 'name' => 'User Management', 'order_by' => 30],
            ['id' => 8, 'name' => 'Content', 'order_by' => 40],
            ['id' => 16, 'name' => 'Report', 'order_by' => 50],
        ] as $rootMenu) {
            DB::table('auth_menus')->updateOrInsert(
                ['id' => $rootMenu['id']],
                [
                    'parent_id' => null,
                    'name' => $rootMenu['name'],
                    'link' => 'dashboard.index',
                    'icon' => null,
                    'order_by' => $rootMenu['order_by'],
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function test_curated_and_legacy_routes_mark_exactly_one_sidebar_destination_current(): void
    {
        $role = Role::create([
            'name' => 'Responsive shell owner',
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
            'is_owner' => true,
        ]);
        $admin = Admin::create([
            'name' => 'Responsive shell owner',
            'username' => 'responsive-shell-owner',
            'email' => 'responsive-shell-owner@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
        Auth::guard('admin')->login($admin);

        $cases = [
            'page.menu.index' => ['Header & Footer', false],
            'page.menu.trash' => ['Header & Footer', false],
            'page.builder.edit' => ['Content Hub', false],
            'seo.redirects.index' => ['Redirects', false],
            'seo.technical.index' => ['Technical SEO & 404s', false],
            'seo.technical.scan' => ['Technical SEO & 404s', false],
            'seo.content.edit' => ['Search & Sharing', false],
            'chat.faq.index' => ['Chat Answers', false],
            'chat.index' => ['Chat Inbox', false],
            'division.index' => ['Divisions', true],
            'division.edit' => ['Divisions', true],
            'district.index' => ['Districts', true],
            'district.edit' => ['Districts', true],
            'report.youtubeMeta.search' => ['YouTube Report', true],
        ];

        foreach ($cases as $routeName => [$expectedLabel, $isLegacy]) {
            $route = app('router')->getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Expected {$routeName} to be registered.");
            $currentRoute = new \ReflectionProperty(app('router'), 'current');
            $currentRoute->setValue(app('router'), $route);

            $html = view('admin.layouts.sidebar')->render();
            $document = new \DOMDocument();
            @$document->loadHTML('<!doctype html><html><body>'.$html.'</body></html>');
            $xpath = new \DOMXPath($document);
            $currentLinks = $xpath->query('//*[@id="left-panel"]//a[@aria-current="page"]');

            $this->assertSame(1, $currentLinks->length, "{$routeName} must mark exactly one sidebar link current.");
            $currentLink = $currentLinks->item(0);
            $this->assertStringContainsString($expectedLabel, trim($currentLink->textContent));

            $legacyCurrent = $xpath->query(
                'ancestor::details[contains(concat(" ", normalize-space(@class), " "), " igf-all-tools ")]',
                $currentLink
            );
            $this->assertSame($isLegacy ? 1 : 0, $legacyCurrent->length, "{$routeName} must use the expected sidebar section.");

            if (!$isLegacy) {
                $this->assertSame(
                    0,
                    $xpath->query('//details[contains(concat(" ", normalize-space(@class), " "), " igf-all-tools ")]//a[@aria-current="page"]')->length,
                    "{$routeName} must not activate an excluded duplicate legacy link."
                );
                continue;
            }

            $legacyDetails = $legacyCurrent->item(0);
            $this->assertTrue($legacyDetails->hasAttribute('open'), "{$routeName} must open Advanced & Legacy Tools.");
            $currentLeaf = $xpath->query('ancestor::li[1]', $currentLink)->item(0);
            $this->assertStringContainsString('active', $currentLeaf->getAttribute('class'));

            $parentBranches = $xpath->query(
                'ancestor::li[contains(concat(" ", normalize-space(@class), " "), " menu-item-has-children ")]',
                $currentLink
            );
            $this->assertGreaterThan(0, $parentBranches->length, "{$routeName} must retain its legacy parent branch.");
            foreach ($parentBranches as $parentBranch) {
                $classes = ' '.$parentBranch->getAttribute('class').' ';
                $this->assertStringContainsString(' active ', $classes);
                $this->assertStringContainsString(' show ', $classes);
                $this->assertSame(1, $xpath->query('./a[@aria-expanded="true"]', $parentBranch)->length);
            }
        }
    }

    public function test_legacy_menu_uses_the_longest_permitted_route_prefix(): void
    {
        $role = Role::create([
            'name' => 'Legacy prefix owner',
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
            'is_owner' => true,
        ]);
        $admin = Admin::create([
            'name' => 'Legacy prefix owner',
            'username' => 'legacy-prefix-owner',
            'email' => 'legacy-prefix-owner@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
        Auth::guard('admin')->login($admin);

        $route = app('router')->getRoutes()->getByName('seo.technical.scan');
        $this->assertNotNull($route);
        $currentRoute = new \ReflectionProperty(app('router'), 'current');
        $currentRoute->setValue(app('router'), $route);

        $document = new \DOMDocument();
        @$document->loadHTML('<!doctype html><html><body><ul>'.MyMenu::menuUi().'</ul></body></html>');
        $currentLinks = (new \DOMXPath($document))->query('//a[@aria-current="page"]');

        $this->assertSame(1, $currentLinks->length);
        $this->assertStringContainsString('Technical SEO & 404s', trim($currentLinks->item(0)->textContent));
        $this->assertSame(route('seo.technical.index'), $currentLinks->item(0)->getAttribute('href'));
    }
}
