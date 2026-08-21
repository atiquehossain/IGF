<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Comment;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommentModerationPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_view_and_moderation_are_independent_capabilities(): void
    {
        $commentMenu = AuthMenu::where('link', 'comment.index')->firstOrFail();
        $moderate = MenuAction::where('link', 'comment.publish')->firstOrFail();
        $pagePublish = MenuAction::where('link', 'page.status')->firstOrFail();
        $comment = $this->comment();

        $viewer = $this->admin('comment-viewer', (string) $commentMenu->id, '');
        $this->asAdmin($viewer)->get(route('comment.index'))
            ->assertOk()
            ->assertDontSee('id="commentPublishModal"', false)
            ->assertDontSee('role="button"', false);
        $this->put(route('page.status.comment'), ['id' => $comment->id])->assertForbidden();
        $this->assertFalse((bool) $comment->fresh()->status);

        $pagePublisher = $this->admin('page-publisher', '', (string) $pagePublish->id);
        $this->asAdmin($pagePublisher)
            ->put(route('page.status.comment'), ['id' => $comment->id])
            ->assertForbidden();
        $this->assertFalse((bool) $comment->fresh()->status);

        $moderator = $this->admin('comment-moderator', (string) $commentMenu->id, (string) $moderate->id);
        $this->asAdmin($moderator)->get(route('comment.index'))
            ->assertOk()
            ->assertSee('id="commentPublishModal"', false)
            ->assertSee('role="button"', false);
        $this->put(route('page.status.comment'), ['id' => $comment->id])->assertRedirect();
        $this->assertTrue((bool) $comment->fresh()->status);
    }

    public function test_page_comment_search_is_session_bound_query_free_and_page_toggle_is_explicit(): void
    {
        $pageView = MenuAction::where('link', 'page.view')->firstOrFail();
        $pagePublish = MenuAction::where('link', 'page.status')->firstOrFail();
        $moderate = MenuAction::where('link', 'comment.publish')->firstOrFail();
        $comment = $this->comment('Confidential visitor detail');
        $page = $comment->page;
        $admin = $this->admin('page-comment-reviewer', '', collect([
            $pageView->id, $pagePublish->id, $moderate->id,
        ])->implode(','));

        $legacy = $this->asAdmin($admin)->get(route('page.view', [
            'id' => $page->uuid,
            'search' => 'Confidential visitor detail',
        ]))->assertRedirect(route('page.view', ['id' => $page->uuid]));
        $this->assertStringNotContainsString('Confidential', (string) $legacy->headers->get('Location'));

        $this->post(route('page.comments.search', $page->uuid), [
            'search' => 'Confidential visitor detail',
        ])->assertRedirect(route('page.view', ['id' => $page->uuid]));

        $index = $this->get(route('page.view', ['id' => $page->uuid]))
            ->assertOk()
            ->assertSee('Confidential visitor detail')
            ->assertSee(route('page.comments.search.clear', $page->uuid), false)
            ->assertDontSee('search=', false);
        $this->assertStringNotContainsString('Confidential', (string) $index->headers->get('Location'));

        $this->putJson(route('page.is-comments', $page->id), ['is_comment' => false])
            ->assertOk()
            ->assertJson(['is_comment' => false]);
        $this->assertFalse((bool) $page->fresh()->is_comment);

        $this->post(route('page.comments.search.clear', $page->uuid))
            ->assertRedirect(route('page.view', ['id' => $page->uuid]));
    }

    private function comment(string $text = 'Please review this comment.'): Comment
    {
        $page = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Moderated page',
            'sub_title' => '',
            'slug' => 'moderated-page',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'is_comment' => 1,
            'language' => 'en',
        ]);

        return Comment::create([
            'page_id' => $page->id,
            'name' => 'Visitor',
            'text' => $text,
            'status' => 0,
            'is_delete' => 0,
        ]);
    }

    private function admin(string $username, string $menus, string $actions): Admin
    {
        $role = Role::create([
            'name' => Str::headline($username),
            'permission' => $menus,
            'actionPermission' => $actions,
            'serial' => '[]',
            'status' => 1,
            'is_owner' => false,
        ]);

        return Admin::create([
            'name' => Str::headline($username),
            'username' => $username,
            'email' => $username . '@example.test',
            'role' => $role->id,
            'status' => 1,
            'password' => bcrypt('not-used-in-this-test'),
            'must_change_password' => false,
        ]);
    }

    private function asAdmin(Admin $admin): self
    {
        $this->actingAs($admin, 'admin');
        session()->put(Admin::SESSION_AUTH_VERSION, $admin->auth_version);

        return $this;
    }
}
