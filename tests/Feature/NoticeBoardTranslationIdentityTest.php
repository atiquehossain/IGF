<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\NoticeBoard;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NoticeBoardTranslationIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_new_event_gets_a_distinct_stable_translation_identity(): void
    {
        $first = $this->notice('First event', 'first-event', 'en');
        $second = $this->notice('Second event', 'second-event', 'en');

        $this->assertTrue(Schema::hasColumn('notice_boards', 'translation_key'));
        $this->assertNotEmpty($first->translation_key);
        $this->assertNotEmpty($second->translation_key);
        $this->assertNotSame($first->translation_key, $second->translation_key);

        $stableKey = $first->translation_key;
        $first->update(['title' => 'First event updated']);
        $this->assertSame($stableKey, $first->fresh()->translation_key);
    }

    public function test_editor_can_connect_an_existing_bangla_event_to_its_english_original(): void
    {
        $english = $this->notice('Community gathering', 'community-gathering', 'en');
        $bangla = $this->notice('কমিউনিটি সমাবেশ', 'bangla-community-gathering', 'bn');
        $this->assertNotSame($english->translation_key, $bangla->translation_key);
        $admin = $this->makeAdmin('notice.board.edit');

        $this->asAdmin($admin)
            ->get(route('notice.board.edit', $bangla->id))
            ->assertOk()
            ->assertSee('Language connection')
            ->assertSee('Translation of')
            ->assertSee('Community gathering');

        $this->put(route('notice.board.update'), [
            'id' => $bangla->id,
            'title' => $bangla->title,
            'sub_title' => '',
            'description' => '',
            'inline_css' => '',
            'published_at' => now()->format('d-m-Y'),
            'language' => 'bn',
            'translation_source_id' => $english->id,
        ])->assertRedirect(route('notice.board.index'));

        $this->assertSame($english->translation_key, $bangla->fresh()->translation_key);
        $this->assertSame(2, NoticeBoard::where('translation_key', $english->translation_key)->count());
    }

    public function test_a_translation_group_cannot_contain_two_rows_in_the_same_language(): void
    {
        $english = $this->notice('Health workshop', 'health-workshop', 'en');
        $existingBangla = $this->notice('স্বাস্থ্য কর্মশালা', 'bangla-health-workshop', 'bn');
        $existingBangla->update(['translation_key' => $english->translation_key]);
        $otherBangla = $this->notice('অন্য কর্মশালা', 'other-bangla-workshop', 'bn');
        $admin = $this->makeAdmin('notice.board.edit');

        $this->asAdmin($admin)
            ->from(route('notice.board.edit', $otherBangla->id))
            ->put(route('notice.board.update'), [
                'id' => $otherBangla->id,
                'title' => $otherBangla->title,
                'published_at' => now()->format('d-m-Y'),
                'language' => 'bn',
                'translation_source_id' => $english->id,
            ])
            ->assertRedirect(route('notice.board.edit', $otherBangla->id))
            ->assertSessionHasErrors('translation_source_id');

        $this->assertNotSame($english->translation_key, $otherBangla->fresh()->translation_key);
    }

    private function notice(string $title, string $slug, string $language): NoticeBoard
    {
        return NoticeBoard::create([
            'title' => $title,
            'slug' => $slug,
            'language' => $language,
            'status' => 1,
            'published_at' => now()->subDay(),
            'notice_type' => 'notice-board',
        ]);
    }

    private function makeAdmin(string $capability): Admin
    {
        $menu = AuthMenu::where('link', 'notice.board.index')->firstOrFail();
        $action = MenuAction::where('link', $capability)->firstOrFail();
        $role = Role::create([
            'name' => 'Event translation editor',
            'permission' => (string) $menu->id,
            'actionPermission' => (string) $action->id,
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Event translation editor',
            'username' => 'event-translation-editor',
            'email' => 'event-translator@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }

    private function asAdmin(Admin $admin): static
    {
        session()->put(Admin::SESSION_AUTH_VERSION, $admin->auth_version);

        return $this->actingAs($admin, 'admin');
    }
}
