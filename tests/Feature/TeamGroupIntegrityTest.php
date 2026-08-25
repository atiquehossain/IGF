<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\LatestNews;
use App\Models\MenuAction;
use App\Models\PageBlock;
use App\Models\Role;
use App\Models\TeamGroup;
use App\Services\TranslationCenterService;
use App\Services\PageBlockContentResolver;
use App\Support\AdminPermissionRegistry;
use Database\Seeders\IgniteParityContentSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamGroupIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_backfill_is_locale_aware_idempotent_and_preserves_explicit_groups(): void
    {
        $customGroup = TeamGroup::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Operational Leads',
            'description' => 'Program and operations leadership.',
            'slug' => 'operational-leads',
            'order_by' => 80,
            'status' => 1,
            'language' => 'en',
        ]);
        $englishMember = $this->member('English member', 'en');
        $banglaMember = $this->member('Bangla member', 'bn');
        $assignedMember = $this->member('Assigned member', 'en', $customGroup->id);

        $migration = require database_path('migrations/2026_08_25_120000_create_team_groups.php');
        $migration->up();
        $migration->up();

        $englishBoard = TeamGroup::where([
            'language' => 'en',
            'slug' => 'board-of-directors',
        ])->firstOrFail();
        $banglaBoard = TeamGroup::where([
            'language' => 'bn',
            'slug' => 'board-of-directors',
        ])->firstOrFail();

        $this->assertSame(1, TeamGroup::where('language', 'en')->where('slug', 'board-of-directors')->count());
        $this->assertSame(1, TeamGroup::where('language', 'bn')->where('slug', 'board-of-directors')->count());
        $this->assertSame($englishBoard->id, $englishMember->fresh()->team_group_id);
        $this->assertSame($banglaBoard->id, $banglaMember->fresh()->team_group_id);
        $this->assertSame($customGroup->id, $assignedMember->fresh()->team_group_id);
    }

    public function test_team_seeder_is_idempotent_and_preserves_an_explicit_group_assignment(): void
    {
        $customGroup = TeamGroup::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Founder and Executive Board',
            'slug' => 'founder-executive-board',
            'order_by' => 110,
            'status' => 1,
            'language' => 'en',
        ]);
        $founder = LatestNews::create([
            'name' => 'Muhammad Jahirul Islam',
            'type' => 'our-members',
            'description' => 'Existing role',
            'team_group_id' => $customGroup->id,
            'language' => 'en',
            'status' => 1,
        ]);

        $seeder = app(IgniteParityContentSeeder::class);
        $method = new \ReflectionMethod($seeder, 'seedTeam');
        $method->invoke($seeder);
        $method->invoke($seeder);

        $boardGroup = TeamGroup::where('language', 'en')->where('slug', 'board-of-directors')->firstOrFail();
        $seededMember = LatestNews::where('name', 'Monmoy Jahan Ali')->where('type', 'our-members')->firstOrFail();

        $this->assertSame(7, LatestNews::where('type', 'our-members')->where('language', 'en')->count());
        $this->assertSame($customGroup->id, $founder->fresh()->team_group_id);
        $this->assertSame($boardGroup->id, $seededMember->team_group_id);
    }

    public function test_authorized_admin_can_manage_groups_and_assign_members(): void
    {
        app()->setLocale('en');
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')->get(route('latest.news.index'))
            ->assertOk()
            ->assertSee('Team groups')
            ->assertSee('Higher display-order numbers appear first.');

        $this->actingAs($admin, 'admin')->post(route('latest.news.group.store'), [
            'group_name' => '<strong>Operational Leads</strong>',
            'group_description' => '<p>Program and operations leadership.</p>',
            'group_slug' => '',
            'group_order_by' => 80,
        ])->assertSessionHasNoErrors();

        $group = TeamGroup::where('language', 'en')->where('slug', 'operational-leads')->firstOrFail();
        $this->assertSame('Operational Leads', $group->name);
        $this->assertSame('Program and operations leadership.', $group->description);
        $this->assertSame(80, $group->order_by);
        $this->assertSame(1, $group->status);

        $this->actingAs($admin, 'admin')->put(route('latest.news.group.update', $group), [
            'group_name' => 'Program Leadership',
            'group_description' => 'Leads day-to-day programs.',
            'group_slug' => 'program-leadership',
            'group_order_by' => 190,
        ])->assertSessionHasNoErrors();

        $group->refresh();
        $this->assertSame('Program Leadership', $group->name);
        $this->assertSame('program-leadership', $group->slug);
        $this->assertSame(190, $group->order_by);

        $this->actingAs($admin, 'admin')
            ->put(route('latest.news.group.status', $group))
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $group->fresh()->status);

        $this->actingAs($admin, 'admin')->post(route('latest.news.store'), [
            'name' => 'Nadia Karim',
            'designation' => 'Operations Director',
            'team_group_id' => $group->id,
            'order_by' => 50,
        ])->assertSessionHasNoErrors();

        $member = LatestNews::where('name', 'Nadia Karim')->firstOrFail();

        $this->actingAs($admin, 'admin')->post(route('latest.news.store'), [
            'name' => 'Legacy payload member',
            'designation' => 'Board Member',
        ])->assertSessionHasNoErrors();

        $boardGroup = TeamGroup::where('language', 'en')->where('slug', 'board-of-directors')->firstOrFail();
        $legacyMember = LatestNews::where('name', 'Legacy payload member')->firstOrFail();
        $this->assertSame($boardGroup->id, $legacyMember->team_group_id);
        $this->assertSame($group->id, $member->team_group_id);
        $this->assertSame('Program Leadership', $member->teamGroup->name);

        $member->delete();
        $this->actingAs($admin, 'admin')
            ->get(route('latest.news.index'))
            ->assertOk()
            ->assertSee('0 live')
            ->assertSee('1 in trash');
        $this->actingAs($admin, 'admin')
            ->delete(route('latest.news.group.destroy', $group))
            ->assertSessionHasErrorsIn('teamGroup', ['team_group']);
        $this->assertDatabaseHas('team_groups', ['id' => $group->id]);

        $emptyGroup = TeamGroup::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Advisory Board',
            'slug' => 'advisory-board',
            'order_by' => 70,
            'status' => 1,
            'language' => 'en',
        ]);
        $this->actingAs($admin, 'admin')
            ->delete(route('latest.news.group.destroy', $emptyGroup))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('team_groups', ['id' => $emptyGroup->id]);
    }

    public function test_group_and_member_mutations_are_restricted_to_the_current_locale(): void
    {
        app()->setLocale('en');
        $admin = $this->makeAdmin();
        $banglaGroup = TeamGroup::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Bangla board',
            'slug' => 'bangla-board',
            'order_by' => 100,
            'status' => 1,
            'language' => 'bn',
        ]);

        $this->actingAs($admin, 'admin')->post(route('latest.news.store'), [
            'name' => 'Wrong locale member',
            'designation' => 'Member',
            'team_group_id' => $banglaGroup->id,
        ])->assertSessionHasErrors(['team_group_id']);
        $this->assertDatabaseMissing('latest_news', ['name' => 'Wrong locale member']);

        $this->actingAs($admin, 'admin')->put(route('latest.news.group.update', $banglaGroup), [
            'group_name' => 'Crafted update',
            'group_slug' => 'crafted-update',
            'group_order_by' => 10,
        ])->assertForbidden();
        $this->actingAs($admin, 'admin')->put(route('latest.news.group.status', $banglaGroup))->assertForbidden();
        $this->actingAs($admin, 'admin')->delete(route('latest.news.group.destroy', $banglaGroup))->assertForbidden();

        $this->assertDatabaseHas('team_groups', [
            'id' => $banglaGroup->id,
            'name' => 'Bangla board',
            'status' => 1,
        ]);
    }

    public function test_public_resolver_groups_only_enabled_members_and_keeps_legacy_fallbacks(): void
    {
        app()->setLocale('en');

        $board = TeamGroup::where('language', 'en')
            ->where('slug', 'board-of-directors')
            ->firstOrFail();
        $board->update([
            'description' => 'Mission stewardship and accountability.',
            'order_by' => 100,
        ]);
        $operations = TeamGroup::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Operational Leads',
            'description' => 'Program and operations leadership.',
            'slug' => 'operational-leads',
            'order_by' => 200,
            'status' => 1,
            'language' => 'en',
        ]);
        $hidden = TeamGroup::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Hidden group',
            'slug' => 'hidden-group',
            'order_by' => 300,
            'status' => 0,
            'language' => 'en',
        ]);

        $this->member('Board member', 'en', $board->id);
        $this->member('Operations member', 'en', $operations->id);
        $this->member('Hidden member', 'en', $hidden->id);
        $this->member('Ungrouped member', 'en');
        $draft = $this->member('Draft member', 'en', $operations->id);
        $draft->update(['status' => 0]);

        $content = app(PageBlockContentResolver::class)->resolve(new PageBlock([
            'type' => 'team',
            'content' => [
                'content_source' => 'team',
                'selection_mode' => 'automatic',
                'sort' => 'featured',
                'limit' => 12,
            ],
        ]));

        $this->assertEqualsCanonicalizing(
            ['Board member', 'Operations member', 'Ungrouped member'],
            collect($content['items'])->pluck('heading')->all()
        );
        $groups = collect($content['groups']);
        $this->assertSame(['Operational Leads', 'Board of directors', 'Team'], $groups->pluck('name')->all());
        $this->assertSame('Program and operations leadership.', $groups->firstWhere('name', 'Operational Leads')['description']);
        $this->assertSame('Operations member', $groups->firstWhere('name', 'Operational Leads')['items'][0]['heading']);
        $this->assertSame('Ungrouped member', $groups->firstWhere('name', 'Team')['items'][0]['heading']);
        $this->assertFalse($groups->contains('name', 'Hidden group'));
    }

    public function test_team_limit_is_global_and_translation_queries_are_batched(): void
    {
        LatestNews::withTrashed()
            ->where('type', 'our-members')
            ->forceDelete();

        $leadership = TeamGroup::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Leadership',
            'slug' => 'limit-leadership',
            'order_by' => 200,
            'status' => 1,
            'language' => 'en',
        ]);
        $advisory = TeamGroup::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Advisory',
            'slug' => 'limit-advisory',
            'order_by' => 100,
            'status' => 1,
            'language' => 'en',
        ]);

        $this->member('Leader one', 'en', $leadership->id)->update(['order_by' => 400]);
        $this->member('Leader two', 'en', $leadership->id)->update(['order_by' => 300]);
        $this->member('Advisor one', 'en', $advisory->id)->update(['order_by' => 200]);
        $this->member('Advisor two', 'en', $advisory->id)->update(['order_by' => 100]);

        app()->setLocale('bn');
        DB::flushQueryLog();
        DB::enableQueryLog();

        $content = app(PageBlockContentResolver::class)->resolve(new PageBlock([
            'type' => 'team',
            'content' => [
                'content_source' => 'team',
                'selection_mode' => 'automatic',
                'sort' => 'featured',
                'limit' => 3,
            ],
        ]));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(3, $content['items']);
        $this->assertSame(3, collect($content['groups'])->sum(
            fn (array $group): int => count($group['items'])
        ));
        $this->assertSame(['Leadership', 'Advisory'], collect($content['groups'])->pluck('name')->all());
        $this->assertLessThanOrEqual(7, $queryCount);
    }

    public function test_team_groups_are_editable_in_translation_center_and_localized_publicly(): void
    {
        LatestNews::withTrashed()
            ->where('type', 'our-members')
            ->forceDelete();
        app()->setLocale('en');

        $group = TeamGroup::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Community Leadership',
            'description' => 'Leaders accountable to communities.',
            'slug' => 'translation-community-leadership',
            'order_by' => 220,
            'status' => 1,
            'language' => 'en',
        ]);
        $this->member('Localized leader', 'en', $group->id);

        $service = app(TranslationCenterService::class);
        $rows = $service->rows('en', 'bn')->filter(
            fn (array $row): bool => ($row['identity']['type'] ?? null) === 'content_overlay'
                && ($row['identity']['model'] ?? null) === 'team_group'
                && ($row['identity']['source_id'] ?? null) === $group->id
        );
        $nameRow = $rows->firstWhere('identity.field', 'name');
        $descriptionRow = $rows->firstWhere('identity.field', 'description');

        $this->assertNotNull($nameRow);
        $this->assertNotNull($descriptionRow);
        $this->assertSame(2, $service->save('en', 'bn', [
            [
                'key' => $nameRow['key'],
                'precondition' => $nameRow['precondition'],
                'value' => 'কমিউনিটি নেতৃত্ব',
            ],
            [
                'key' => $descriptionRow['key'],
                'precondition' => $descriptionRow['precondition'],
                'value' => 'কমিউনিটির কাছে জবাবদিহিমূলক নেতৃত্ব।',
            ],
        ], null));

        app()->setLocale('bn');
        $content = app(PageBlockContentResolver::class)->resolve(new PageBlock([
            'type' => 'team',
            'content' => [
                'content_source' => 'team',
                'selection_mode' => 'automatic',
                'sort' => 'featured',
                'limit' => 12,
            ],
        ]));

        $localized = collect($content['groups'])->firstWhere('slug', $group->slug);
        $this->assertSame('কমিউনিটি নেতৃত্ব', $localized['name']);
        $this->assertSame('কমিউনিটির কাছে জবাবদিহিমূলক নেতৃত্ব।', $localized['description']);
        $this->assertSame('Localized leader', $localized['items'][0]['heading']);
    }

    public function test_group_routes_reuse_existing_team_member_capabilities(): void
    {
        $this->assertSame(['latest.news.create'], AdminPermissionRegistry::capabilitiesForRoute('latest.news.group.store'));
        $this->assertSame(['latest.news.edit'], AdminPermissionRegistry::capabilitiesForRoute('latest.news.group.update'));
        $this->assertSame(['latest.news.status'], AdminPermissionRegistry::capabilitiesForRoute('latest.news.group.status'));
        $this->assertSame(['latest.news.destroy'], AdminPermissionRegistry::capabilitiesForRoute('latest.news.group.destroy'));
    }

    private function member(string $name, string $language, ?int $groupId = null): LatestNews
    {
        return LatestNews::create([
            'name' => $name,
            'type' => 'our-members',
            'description' => 'Member',
            'team_group_id' => $groupId,
            'language' => $language,
            'status' => 1,
        ]);
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
            'name' => 'Team group editor',
            'permission' => (string) $menu->id,
            'actionPermission' => $actions->pluck('id')->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Team Group QA',
            'username' => 'team-group-qa',
            'email' => 'team-group-qa@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
