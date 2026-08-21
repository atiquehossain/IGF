<?php

namespace Tests\Feature;

use App\Http\Middleware\Permission;
use App\Http\Middleware\ProtectFileManagerMutations;
use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\ContactMessage;
use App\Models\LatestNews;
use App\Models\MenuAction;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_message_search_treats_sql_metacharacters_as_text(): void
    {
        ContactMessage::create([
            'first_name' => 'Expected',
            'email' => 'expected@example.test',
            'phone' => '01700000000',
            'message' => 'A legitimate contact message.',
        ]);
        ContactMessage::create([
            'first_name' => 'Other',
            'email' => 'other@example.test',
            'phone' => '01800000000',
            'message' => 'Another contact message.',
        ]);

        $menu = AuthMenu::where('link', 'contact-message.index')->firstOrFail();
        $role = Role::create([
            'name' => 'Contact search tester',
            'permission' => (string) $menu->id,
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $admin = Admin::create([
            'name' => 'Contact Search Tester',
            'username' => 'contact-search-tester',
            'email' => 'contact-search-tester@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('contact-message.search'), ['search' => "' OR 1=1 --"])
            ->assertRedirect(route('contact-message.index'));
        $this->get(route('contact-message.index'))
            ->assertOk()
            ->assertDontSee('expected@example.test')
            ->assertDontSee('other@example.test');

        $this->post(route('contact-message.search'), ['search' => 'expected@example.test'])
            ->assertRedirect(route('contact-message.index'));
        $this->get(route('contact-message.index'))
            ->assertOk()
            ->assertSee('expected@example.test')
            ->assertDontSee('other@example.test');
    }

    public function test_public_members_api_only_returns_published_public_fields(): void
    {
        $published = LatestNews::create([
            'name' => 'Published member',
            'type' => 'our-members',
            'description' => 'Public description',
            'path' => 'published.png',
            'url' => 'https://member.example.test',
            'email' => 'private-published@example.test',
            'language' => 'en',
            'status' => 1,
        ]);
        $unsafe = LatestNews::create([
            'name' => 'Legacy unsafe member',
            'type' => 'our-members',
            'description' => 'Legacy public record',
            'path' => 'legacy.png',
            'url' => 'javascript:alert(1)',
            'email' => 'private-legacy@example.test',
            'language' => 'en',
            'status' => 1,
        ]);
        LatestNews::create([
            'name' => 'Draft member',
            'type' => 'our-members',
            'description' => 'Not ready for publication',
            'path' => 'draft.png',
            'url' => 'https://draft.example.test',
            'email' => 'private-draft@example.test',
            'language' => 'en',
            'status' => 0,
        ]);

        $response = $this->getJson('/api/v1/members')->assertOk()->assertJsonPath('status', true);
        $members = collect($response->json('data.members'))->flatten(1);

        $this->assertSame([$unsafe->id, $published->id], $members->pluck('id')->all());
        $publishedPayload = $members->firstWhere('id', $published->id);
        $unsafePayload = $members->firstWhere('id', $unsafe->id);
        $this->assertSame('/storage/photos/1/our_members/published.png', $publishedPayload['path']);
        $this->assertSame('https://member.example.test', $publishedPayload['url']);
        $this->assertSame('', $unsafePayload['url']);
        $this->assertArrayNotHasKey('email', $publishedPayload);
        $this->assertArrayNotHasKey('status', $publishedPayload);
    }

    public function test_gallery_view_permission_does_not_grant_file_mutations(): void
    {
        $menu = AuthMenu::where('link', 'media.index')->firstOrFail();
        $create = MenuAction::where('link', 'media.create')->firstOrFail();
        $edit = MenuAction::where('link', 'media.edit')->firstOrFail();
        $destroy = MenuAction::where('link', 'media.destroy')->firstOrFail();
        $role = Role::create([
            'name' => 'Gallery viewer',
            'permission' => (string) $menu->id,
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $admin = Admin::create([
            'name' => 'Gallery Viewer',
            'username' => 'gallery-viewer',
            'email' => 'gallery-viewer@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
        ]);
        $permissions = app(Permission::class);

        $this->assertTrue($permissions->allows($admin, 'unisharp.lfm.show'));
        $this->assertTrue($permissions->allows($admin, 'media.index'));
        $this->assertFalse($permissions->allows($admin, 'unisharp.lfm.upload'));
        $this->assertFalse($permissions->allows($admin, 'unisharp.lfm.getRename'));
        $this->assertFalse($permissions->allows($admin, 'unisharp.lfm.getDelete'));
        $this->assertFalse($permissions->allows($admin, 'media.store'));
        $this->assertFalse($permissions->allows($admin, 'media.update'));
        $this->assertFalse($permissions->allows($admin, 'media.destroy'));

        $role->update(['actionPermission' => implode(',', [$create->id, $edit->id, $destroy->id])]);

        $this->assertTrue($permissions->allows($admin, 'unisharp.lfm.upload'));
        $this->assertTrue($permissions->allows($admin, 'unisharp.lfm.getRename'));
        $this->assertTrue($permissions->allows($admin, 'unisharp.lfm.getDelete'));
        $this->assertTrue($permissions->allows($admin, 'media.store'));
        $this->assertTrue($permissions->allows($admin, 'media.update'));
        $this->assertTrue($permissions->allows($admin, 'media.destroy'));
    }

    public function test_file_manager_mutations_reject_cross_site_navigation_but_keep_ajax_ui_compatible(): void
    {
        $middleware = app(ProtectFileManagerMutations::class);

        $crossSite = $this->fileManagerRequest('unisharp.lfm.getDelete', [
            'HTTP_REFERER' => 'https://attacker.example/',
        ]);
        try {
            $middleware->handle($crossSite, fn () => response('unsafe'));
            $this->fail('A navigational file-manager mutation should be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(419, $exception->getStatusCode());
        }

        $sameOriginAjax = $this->fileManagerRequest('unisharp.lfm.getDelete', [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_REFERER' => 'http://localhost/admin/filemanager',
            'HTTP_SEC_FETCH_SITE' => 'same-origin',
        ]);
        $response = $middleware->handle($sameOriginAjax, fn () => response('safe'));

        $this->assertSame('safe', $response->getContent());
    }

    private function fileManagerRequest(string $routeName, array $server = []): Request
    {
        $request = Request::create('http://localhost/admin/filemanager/delete', 'GET', [], [], [], $server);
        $route = new Route(['GET'], 'admin/filemanager/delete', fn () => null);
        $route->name($routeName);
        $request->setRouteResolver(fn () => $route);

        $session = new Store('security-test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        return $request;
    }
}
