<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\LatestNews;
use App\Models\MenuAction;
use App\Models\PageBlock;
use App\Models\Role;
use App\Services\PageBlockContentResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMemberProfileIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_block_exposes_enriched_profiles_and_a_safe_legacy_link_fallback(): void
    {
        app()->setLocale('en');

        $member = LatestNews::create([
            'name' => 'Amina Rahman',
            'type' => 'our-members',
            'description' => 'Board Chair',
            'biography' => 'Amina supports mission stewardship and accountable growth.',
            'qualification' => 'MBA, University of Dhaka',
            'path' => 'amina.webp',
            'url' => 'https://example.test/amina',
            'social_links' => [
                ['platform' => 'linkedin', 'label' => '', 'url' => 'https://www.linkedin.com/in/amina'],
                ['platform' => 'linkedin', 'label' => 'Duplicate', 'url' => 'https://www.linkedin.com/in/amina/'],
                ['platform' => 'email', 'label' => 'Unsafe', 'url' => 'javascript:alert(1)'],
            ],
            'language' => 'en',
            'order_by' => 20,
            'status' => 1,
        ]);
        $legacyMember = LatestNews::create([
            'name' => 'Legacy Member',
            'type' => 'our-members',
            'description' => 'Adviser',
            'url' => '/members/legacy-member',
            'social_links' => [],
            'language' => 'en',
            'order_by' => 10,
            'status' => 1,
        ]);
        LatestNews::create([
            'name' => 'Draft Member',
            'type' => 'our-members',
            'description' => 'Not public',
            'language' => 'en',
            'status' => 0,
        ]);

        $block = new PageBlock([
            'type' => 'team',
            'content' => ['limit' => 12],
        ]);
        $items = collect(app(PageBlockContentResolver::class)->resolve($block)['items']);

        $this->assertCount(2, $items);
        $profile = $items->firstWhere('id', $member->id);
        $this->assertSame('Board Chair', $profile['designation']);
        $this->assertSame('Board Chair', $profile['body']);
        $this->assertSame('Amina supports mission stewardship and accountable growth.', $profile['biography']);
        $this->assertSame('MBA, University of Dhaka', $profile['qualification']);
        $this->assertSame('/storage/photos/1/our_members/amina.webp', $profile['image']);
        $this->assertSame([
            ['platform' => 'linkedin', 'label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/amina'],
        ], $profile['social_links']);

        $legacyProfile = $items->firstWhere('id', $legacyMember->id);
        $this->assertSame('/members/legacy-member', $legacyProfile['url']);
        $this->assertSame([
            ['platform' => 'website', 'label' => 'View profile', 'url' => '/members/legacy-member'],
        ], $legacyProfile['social_links']);
    }

    public function test_admin_member_editor_normalizes_http_social_links_and_rejects_unsafe_schemes(): void
    {
        app()->setLocale('en');
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')->get(route('latest.news.index'))
            ->assertOk()
            ->assertSee('Social links')
            ->assertSee('Biography')
            ->assertSee('Display order');

        $this->actingAs($admin, 'admin')->post(route('latest.news.store'), [
            'name' => 'Nadia Karim',
            'designation' => 'Executive Member',
            'biography' => 'Nadia works with youth-led community programs.',
            'qualification' => 'MSS in Development Studies',
            'order_by' => 75,
            'url' => 'https://example.test/nadia',
            'social_links' => [
                ['platform' => 'LinkedIn', 'label' => '', 'url' => 'https://www.linkedin.com/in/nadia'],
                ['platform' => 'LinkedIn', 'label' => 'Duplicate', 'url' => 'https://www.linkedin.com/in/nadia/'],
                ['platform' => '', 'label' => '', 'url' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $member = LatestNews::where('name', 'Nadia Karim')->firstOrFail();
        $this->assertSame('Executive Member', $member->description);
        $this->assertSame('Nadia works with youth-led community programs.', $member->biography);
        $this->assertSame('MSS in Development Studies', $member->qualification);
        $this->assertSame(75, $member->order_by);
        $this->assertSame([
            ['platform' => 'linkedin', 'label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/nadia'],
        ], $member->social_links);

        $this->actingAs($admin, 'admin')->put(route('latest.news.update'), [
            'id' => $member->id,
            'name' => 'Nadia Karim',
            'designation' => 'Board Secretary',
            'biography' => 'Updated public biography.',
            'qualification' => 'MSS, Development Studies',
            'order_by' => 80,
            'url' => '',
            'social_links' => [
                ['platform' => 'Facebook', 'label' => 'Follow Nadia', 'url' => 'https://www.facebook.com/nadia'],
            ],
        ])->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertSame('Board Secretary', $member->description);
        $this->assertSame('Updated public biography.', $member->biography);
        $this->assertSame(80, $member->order_by);
        $this->assertNull($member->url);
        $this->assertSame([
            ['platform' => 'facebook', 'label' => 'Follow Nadia', 'url' => 'https://www.facebook.com/nadia'],
        ], $member->social_links);

        $this->actingAs($admin, 'admin')->post(route('latest.news.store'), [
            'name' => 'Unsafe Profile',
            'designation' => 'Member',
            'url' => 'javascript:alert(1)',
            'social_links' => [
                ['platform' => 'website', 'label' => 'Unsafe', 'url' => 'javascript:alert(1)'],
            ],
        ])->assertSessionHasErrors(['url', 'social_links.0.url']);

        $this->assertFalse(LatestNews::where('name', 'Unsafe Profile')->exists());
    }

    public function test_member_crud_endpoints_cannot_target_other_content_types_or_locales(): void
    {
        app()->setLocale('en');
        $admin = $this->makeAdmin();
        $member = LatestNews::create([
            'name' => 'Editable Member',
            'type' => 'our-members',
            'description' => 'Member',
            'language' => 'en',
            'status' => 0,
        ]);
        $otherType = LatestNews::create([
            'name' => 'Unrelated News Record',
            'type' => 'latest-news',
            'description' => 'Must not be changed by member routes.',
            'language' => 'en',
            'status' => 1,
        ]);
        $otherLocale = LatestNews::create([
            'name' => 'Bangla Member',
            'type' => 'our-members',
            'description' => 'Bangla record',
            'language' => 'bn',
            'status' => 1,
        ]);
        $protectedRecords = [$otherType, $otherLocale];

        $this->actingAs($admin, 'admin')->get(route('latest.news.edit', $member->id))->assertOk();

        foreach ($protectedRecords as $record) {
            $this->actingAs($admin, 'admin')->get(route('latest.news.edit', $record->id))->assertForbidden();
        }

        foreach ($protectedRecords as $record) {
            $this->actingAs($admin, 'admin')->put(route('latest.news.update'), [
                'id' => $record->id,
                'name' => 'Crafted update',
                'designation' => 'Crafted designation',
            ])->assertSessionHasErrors('id');
        }

        foreach ($protectedRecords as $record) {
            $this->actingAs($admin, 'admin')
                ->withHeader('X-Requested-With', 'XMLHttpRequest')
                ->put(route('latest.news.status', $record->id), ['id' => $record->id])
                ->assertForbidden();
        }

        foreach ($protectedRecords as $record) {
            $this->actingAs($admin, 'admin')->delete(route('latest.news.destroy', $record->id))->assertForbidden();
            $this->assertDatabaseHas('latest_news', [
                'id' => $record->id,
                'name' => $record->name,
                'status' => 1,
                'deleted_at' => null,
            ]);
        }

        $this->actingAs($admin, 'admin')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->put(route('latest.news.status', $member->id), ['id' => $otherType->id])
            ->assertOk();
        $this->assertSame(1, $member->fresh()->status);
        $this->assertSame(1, $otherType->fresh()->status);
    }

    private function makeAdmin(): Admin
    {
        $menu = AuthMenu::create([
            'name' => 'Team Members',
            'link' => 'latest.news.index',
            'status' => 1,
        ]);
        $actions = collect([
            ['name' => 'Create team members', 'link' => 'latest.news.create'],
            ['name' => 'Edit team members', 'link' => 'latest.news.edit'],
            ['name' => 'Publish team members', 'link' => 'latest.news.status'],
            ['name' => 'Delete team members', 'link' => 'latest.news.destroy'],
        ])->map(fn (array $attributes) => MenuAction::create($attributes + [
            'auth_menu_id' => $menu->id,
            'status' => 1,
        ]));
        $role = Role::create([
            'name' => 'Team editor',
            'permission' => (string) $menu->id,
            'actionPermission' => $actions->pluck('id')->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Team QA',
            'username' => 'team-qa',
            'email' => 'team-qa@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
