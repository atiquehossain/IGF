<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Album;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CriticalAdminWorkflowRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelled_submissions_do_not_activate_the_global_busy_overlay(): void
    {
        $scripts = file_get_contents(resource_path('views/admin/layouts/scripts.blade.php'));

        $this->assertStringContainsString("$(document).on('submit.adminBusy', 'form'", $scripts);
        $this->assertStringContainsString('if (event.isDefaultPrevented())', $scripts);
        $this->assertMatchesRegularExpression(
            '/setTimeout\(function\(\)\s*\{\s*if \(event\.isDefaultPrevented\(\)\).*?setAdminBusy\(true\);/s',
            $scripts
        );
        $this->assertStringContainsString("button.find('span').filter(function()", $scripts);
        $this->assertStringContainsString("removeClass('fa-check-square fa-square fa-eye fa-eye-slash')", $scripts);

        $link = file_get_contents(app_path('Link.php'));
        $this->assertStringContainsString("<span>' . \$verb . '</span>", $link);
        $this->assertStringContainsString('data-active-icon="fa-eye-slash"', $link);
        $this->assertStringContainsString('data-inactive-icon="fa-eye"', $link);
    }

    public function test_page_scripts_render_before_the_document_closes_and_trash_actions_recover_from_failures(): void
    {
        $master = file_get_contents(resource_path('views/admin/layouts/master.blade.php'));
        $footer = file_get_contents(resource_path('views/admin/layouts/footer.blade.php'));

        $this->assertStringNotContainsString("@yield('custom-js')", $master);
        $this->assertStringContainsString("@yield('custom-js')", $footer);
        $this->assertLessThan(strpos($footer, '</body>'), strpos($footer, "@yield('custom-js')"));

        foreach (['page/trash.blade.php', 'content-trash/index.blade.php'] as $view) {
            $source = file_get_contents(resource_path('views/admin/'.$view));
            $this->assertStringContainsString("contentType.includes('application/json')", $source);
            $this->assertStringContainsString("credentials: 'same-origin'", $source);
            $this->assertStringContainsString("'X-Requested-With'", $source);
            $this->assertStringContainsString('} catch (error) {', $source);
            $this->assertStringContainsString('} finally {', $source);
            $this->assertStringContainsString("button.removeAttribute('aria-busy')", $source);
            $this->assertStringContainsString('setAdminBusy(false);', $source);
        }
    }

    public function test_gallery_album_modal_is_ajax_driven_and_uses_unique_tab_relationships(): void
    {
        foreach (['add.blade.php', 'edit.blade.php'] as $view) {
            $source = file_get_contents(resource_path('views/admin/gallery/'.$view));

            $this->assertSame(1, substr_count($source, 'id="gallery-language-tabs"'));
            $this->assertSame(1, substr_count($source, 'id="gallery-language-panels"'));
            $this->assertSame(1, substr_count($source, 'id="album-language-tabs"'));
            $this->assertSame(1, substr_count($source, 'id="album-language-panels"'));
            $this->assertStringNotContainsString('id="pills-tab"', $source);
            $this->assertStringNotContainsString('id="pills-tabContent"', $source);
            $this->assertStringContainsString('aria-labelledby="album-modal-title"', $source);
            $this->assertStringContainsString('id="album-modal-title"', $source);
            $this->assertStringContainsString('<h1 class="card-title">{{ $title }}</h1>', $source);
            $this->assertStringContainsString('min-width: 44px;', $source);
            $this->assertStringContainsString('min-height: 44px;', $source);
            $this->assertStringContainsString("albumForm.on('submit', function(event)", $source);
            $this->assertStringContainsString('event.preventDefault();', $source);
            $this->assertStringContainsString("headers: {'Accept': 'application/json'}", $source);
            $this->assertStringContainsString('data-gallery-album-language', $source);
            $this->assertStringContainsString('The gallery information already entered on this page will be preserved.', $source);
        }
    }

    public function test_album_store_returns_created_locale_records_to_the_gallery_modal(): void
    {
        $this->seed(AdminPermissionRegistrySeeder::class);
        $admin = $this->makeAdminWithAlbumCreatePermission();
        $name = 'Field notes '.Str::lower(Str::random(8));

        $response = $this->actingAs($admin, 'admin')->postJson(route('album.store'), [
            'language' => ['en' => 'en'],
            'name' => ['en' => $name],
        ]);

        $response->assertCreated()
            ->assertJsonPath('albums.0.name', $name)
            ->assertJsonPath('albums.0.language', 'en')
            ->assertJsonStructure(['message', 'albums' => [['id', 'uuid', 'name', 'language']]]);

        $this->assertDatabaseHas('albums', [
            'name' => $name,
            'language' => 'en',
        ]);
        $this->assertSame(1, Album::query()->where('name', $name)->count());
    }

    public function test_album_store_rejects_a_language_without_its_matching_name(): void
    {
        $this->seed(AdminPermissionRegistrySeeder::class);
        $admin = $this->makeAdminWithAlbumCreatePermission();

        $this->actingAs($admin, 'admin')->postJson(route('album.store'), [
            'language' => ['en' => 'en'],
            'name' => ['bn' => 'বাংলা অ্যালবাম'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name.en']);

        $this->assertDatabaseCount('albums', 0);
    }

    private function makeAdminWithAlbumCreatePermission(): Admin
    {
        $menu = AuthMenu::query()->where('link', 'album.index')->where('status', 1)->firstOrFail();
        $action = MenuAction::query()->where('link', 'album.create')->where('status', 1)->firstOrFail();
        $suffix = Str::lower(Str::random(10));
        $role = Role::create([
            'name' => 'Gallery album creator '.$suffix,
            'security_rank' => 100,
            'is_owner' => false,
            'permission' => (string) $menu->id,
            'actionPermission' => (string) $action->id,
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Gallery album creator',
            'username' => 'gallery-album-'.$suffix,
            'email' => 'gallery-album-'.$suffix.'@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
