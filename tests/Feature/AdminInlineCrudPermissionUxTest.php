<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\DonationType;
use App\Models\Gallery;
use App\Models\LatestNews;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\Tag;
use App\Models\Testimonial;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminInlineCrudPermissionUxTest extends TestCase
{
    use RefreshDatabase;

    private Tag $tag;
    private LatestNews $member;
    private DonationType $donationType;
    private Testimonial $testimonial;
    private Role $listedRole;
    private Admin $listedAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionRegistrySeeder::class);

        Role::create([
            'name' => 'Reserved owner role',
            'security_rank' => 0,
            'is_owner' => true,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $this->listedRole = Role::create([
            'name' => 'Community content reviewer',
            'security_rank' => 200,
            'is_owner' => false,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $this->listedAdmin = Admin::create([
            'name' => 'Listed administrator',
            'username' => 'listed-administrator',
            'email' => 'listed-administrator@example.test',
            'mobile' => '01700000000',
            'role' => (string) $this->listedRole->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);

        $this->tag = Tag::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Safe water project',
            'slug' => 'safe-water-project',
            'status' => 1,
        ]);
        $this->member = LatestNews::create([
            'name' => 'Permission Test Member',
            'description' => 'Community coordinator',
            'type' => 'our-members',
            'language' => 'en',
            'path' => 'permission-test-member.jpg',
            'status' => 1,
        ]);
        $this->donationType = DonationType::create([
            'name' => 'Education access cause',
            'description' => 'Supports learning materials.',
            'status' => 1,
        ]);
        $this->testimonial = Testimonial::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Permission Test Supporter',
            'designation' => 'Community partner',
            'testimonial' => 'The programme was useful.',
            'photo' => '/image/permission-test-supporter.jpg',
            'language' => 'en',
            'status' => 1,
        ]);
    }

    public function test_view_only_roles_keep_lists_and_search_without_inline_mutations(): void
    {
        $admin = $this->makeAdmin('List viewer', $this->listMenus(), ['role.permission']);

        $this->actingAs($admin, 'admin')->get(route('tag.index'))
            ->assertOk()
            ->assertSee('Read-only access.')
            ->assertSee($this->tag->name)
            ->assertSee('View only')
            ->assertDontSee('id="new_tag"', false)
            ->assertDontSee('id="tagModal"', false)
            ->assertDontSee('data-url="'.route('tag.status', $this->tag->id).'"', false)
            ->assertDontSee('data-url="'.route('tag.destroy', $this->tag->id).'"', false);

        $this->actingAs($admin, 'admin')->get(route('latest.news.index'))
            ->assertOk()
            ->assertSee('Read-only access.')
            ->assertSee($this->member->name)
            ->assertSee($this->member->description)
            ->assertSee('View only')
            ->assertDontSee('id="new_latestNews"', false)
            ->assertDontSee('id="latestNewsModal"', false)
            ->assertDontSee('data-url="'.route('latest.news.status', $this->member->id).'"', false)
            ->assertDontSee('data-url="'.route('latest.news.destroy', $this->member->id).'"', false);

        $this->actingAs($admin, 'admin')->get(route('donationType.index'))
            ->assertOk()
            ->assertSee('Read-only access.')
            ->assertSee($this->donationType->name)
            ->assertSee('View only')
            ->assertDontSee('id="new_donation_type"', false)
            ->assertDontSee('id="donationTypeModal"', false)
            ->assertDontSee('data-url="'.route('donationType.status', $this->donationType->id).'"', false)
            ->assertDontSee('data-url="'.route('donationType.destroy', $this->donationType->id).'"', false);

        $this->actingAs($admin, 'admin')->get(route('testimonial.index'))
            ->assertOk()
            ->assertSee('Read-only access.')
            ->assertSee($this->testimonial->name)
            ->assertSee('View only')
            ->assertDontSee('id="new_testimonial"', false)
            ->assertDontSee('id="testimonialModal"', false)
            ->assertDontSee('data-url="'.route('testimonial.status', $this->testimonial->uuid).'"', false)
            ->assertDontSee('data-url="'.route('testimonial.destroy', $this->testimonial->uuid).'"', false);

        $this->actingAs($admin, 'admin')->get(route('admin.index'))
            ->assertOk()
            ->assertSee('Read-only access.')
            ->assertSee($this->listedAdmin->name)
            ->assertSee($this->listedAdmin->mobile)
            ->assertSee('View only')
            ->assertDontSee('id="new_admin"', false)
            ->assertDontSee('id="edit_admin"', false)
            ->assertDontSee(route('admin.reset', $this->listedAdmin->id), false)
            ->assertDontSee('data-url="'.route('admin.status', $this->listedAdmin->id).'"', false)
            ->assertDontSee('data-url="'.route('admin.destroy', $this->listedAdmin->id).'"', false);

        $this->actingAs($admin, 'admin')->get(route('role.index'))
            ->assertOk()
            ->assertSee('Read-only access.')
            ->assertSee($this->listedRole->name)
            ->assertSee('View only')
            ->assertDontSee('id="new_role"', false)
            ->assertDontSee('id="roleModal"', false)
            ->assertSee(route('role.permission', $this->listedRole->id), false)
            ->assertDontSee('data-url="'.route('role.status', $this->listedRole->id).'"', false)
            ->assertDontSee('data-url="'.route('role.destroy', $this->listedRole->id).'"', false);

        $permissionPage = $this->get(route('role.permission', $this->listedRole->id))
            ->assertOk()
            ->assertSee('You can inspect this role\'s permissions, but your role cannot change them.', false)
            ->assertDontSee('type="submit" class="btn btn-info submit_', false);
        $this->assertMatchesRegularExpression('/<fieldset\s+disabled\s*>/', $permissionPage->getContent());
    }

    public function test_full_crud_roles_see_each_control_on_the_matching_screen(): void
    {
        $admin = $this->makeAdmin('Content owner', $this->listMenus(), [
            'tag.create', 'tag.edit', 'tag.status', 'tag.destroy',
            'latest.news.create', 'latest.news.edit', 'latest.news.status', 'latest.news.destroy',
            'donationType.create', 'donationType.edit', 'donationType.status', 'donationType.destroy',
            'testimonial.create', 'testimonial.edit', 'testimonial.status', 'testimonial.destroy',
            'admin.create', 'admin.edit', 'admin.status', 'admin.destroy', 'admin.reset',
            'role.create', 'role.edit', 'role.status', 'role.destroy', 'role.permission',
        ]);

        $this->actingAs($admin, 'admin')->get(route('tag.index'))
            ->assertOk()
            ->assertSee('id="new_tag"', false)
            ->assertSee('id="tagModal"', false)
            ->assertSee('data-id="'.$this->tag->id.'" aria-label="Edit project"', false)
            ->assertSee('data-url="'.route('tag.status', $this->tag->id).'"', false)
            ->assertSee('data-url="'.route('tag.destroy', $this->tag->id).'"', false);

        $this->actingAs($admin, 'admin')->get(route('latest.news.index'))
            ->assertOk()
            ->assertSee('id="new_latestNews"', false)
            ->assertSee('id="latestNewsModal"', false)
            ->assertSee('data-id="'.$this->member->id.'" aria-label="Edit team member"', false)
            ->assertSee('data-url="'.route('latest.news.status', $this->member->id).'"', false)
            ->assertSee('data-url="'.route('latest.news.destroy', $this->member->id).'"', false);

        $this->actingAs($admin, 'admin')->get(route('donationType.index'))
            ->assertOk()
            ->assertSee('id="new_donation_type"', false)
            ->assertSee('id="donationTypeModal"', false)
            ->assertSee('data-id="'.$this->donationType->id.'" aria-label="Edit donation cause"', false)
            ->assertSee('data-url="'.route('donationType.status', $this->donationType->id).'"', false)
            ->assertSee('data-url="'.route('donationType.destroy', $this->donationType->id).'"', false);

        $this->actingAs($admin, 'admin')->get(route('testimonial.index'))
            ->assertOk()
            ->assertSee('id="new_testimonial"', false)
            ->assertSee('id="testimonialModal"', false)
            ->assertSee('data-id="'.$this->testimonial->uuid.'" aria-label="Edit '.$this->testimonial->name.'"', false)
            ->assertSee('data-url="'.route('testimonial.status', $this->testimonial->uuid).'"', false)
            ->assertSee('data-url="'.route('testimonial.destroy', $this->testimonial->uuid).'"', false);

        $this->actingAs($admin, 'admin')->get(route('admin.index'))
            ->assertOk()
            ->assertSee('id="new_admin"', false)
            ->assertSee('id="edit_admin"', false)
            ->assertSee('data-id="'.$this->listedAdmin->id.'" aria-label="Edit administrator"', false)
            ->assertSee(route('admin.reset', $this->listedAdmin->id), false)
            ->assertSee('data-url="'.route('admin.status', $this->listedAdmin->id).'"', false)
            ->assertSee('data-url="'.route('admin.destroy', $this->listedAdmin->id).'"', false);

        $this->actingAs($admin, 'admin')->get(route('role.index'))
            ->assertOk()
            ->assertSee('id="new_role"', false)
            ->assertSee('id="roleModal"', false)
            ->assertSee('data-id="'.$this->listedRole->id.'" aria-label="Edit role"', false)
            ->assertSee(route('role.permission', $this->listedRole->id), false)
            ->assertSee('data-url="'.route('role.status', $this->listedRole->id).'"', false)
            ->assertSee('data-url="'.route('role.destroy', $this->listedRole->id).'"', false);
    }

    public function test_view_only_legacy_indexes_never_offer_create_forms_that_will_be_forbidden(): void
    {
        $admin = $this->makeAdmin('Legacy list viewer', [
            'division.index',
            'district.index',
            'upazila.index',
            'event_calendar.index',
            'editorDraft.index',
            'splash.screen.index',
        ]);
        $this->assertFalse(app(\App\Http\Middleware\Permission::class)->allows($admin, 'division.update'));

        foreach ([
            ['division.index', 'division.store', 'new_division'],
            ['district.index', 'district.store', 'new_district'],
            ['upazila.index', 'upazila.store', 'new_upazila'],
            ['event_calendar.index', 'event_calendar.store', 'new_event_calendar'],
            ['editorDraft.index', 'editorDraft.store', 'new_editorDraft'],
        ] as [$indexRoute, $storeRoute, $createId]) {
            $this->actingAs($admin, 'admin')->get(route($indexRoute))
                ->assertOk()
                ->assertDontSee('id="'.$createId.'"', false)
                ->assertDontSee('<form action="'.route($storeRoute).'" method="post"', false)
                ->assertSee('col-lg-12', false);
        }

        $this->actingAs($admin, 'admin')->get(route('splash.screen.index'))
            ->assertOk()
            ->assertSee('Read-only access. You can review the visitor announcement')
            ->assertSee('<fieldset disabled', false)
            ->assertDontSee('<button type="submit" class="btn btn-success btn-sm"', false);
    }

    public function test_gallery_album_shortcut_is_accessible_and_only_visible_to_album_creators(): void
    {
        $galleryOnly = $this->makeAdmin('Gallery creator only', ['gallery.index'], ['gallery.create']);

        $this->actingAs($galleryOnly, 'admin')->get(route('gallery.create'))
            ->assertOk()
            ->assertDontSee('class="input-group-text open_album"', false)
            ->assertDontSee('action="'.route('album.store').'"', false)
            ->assertDontSee('id="albamModal"', false);

        $galleryAndAlbum = $this->makeAdmin('Gallery and album creator', ['gallery.index'], [
            'gallery.create',
            'album.create',
        ]);

        $this->actingAs($galleryAndAlbum, 'admin')->get(route('gallery.create'))
            ->assertOk()
            ->assertSee('<button type="button" class="input-group-text open_album" aria-label="Create a new album"', false)
            ->assertSee('action="'.route('album.store').'"', false)
            ->assertSee('id="albamModal"', false);
    }

    public function test_gallery_list_uses_clear_stateful_actions_without_exposing_forbidden_controls(): void
    {
        $published = Gallery::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Community kitchen',
            'type' => 'gallery',
            'language' => 'en',
            'status' => 1,
        ]);
        $draft = Gallery::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'School supplies',
            'type' => 'gallery',
            'language' => 'en',
            'status' => 0,
        ]);
        $editor = $this->makeAdmin('Gallery action editor', ['gallery.index'], [
            'gallery.create',
            'gallery.edit',
            'gallery.status',
            'gallery.destroy',
        ]);

        $response = $this->actingAs($editor, 'admin')->get(route('gallery.index'))
            ->assertOk()
            ->assertSee('class="btn igf-btn igf-btn-secondary igf-btn-compact"', false)
            ->assertSee('class="btn igf-btn igf-btn-primary igf-btn-compact"', false)
            ->assertSee('Add gallery item')
            ->assertSee('role="group" aria-label="Actions for gallery item Community kitchen"', false)
            ->assertSee('aria-label="Edit gallery item Community kitchen"', false)
            ->assertSee('aria-label="Unpublish gallery item Community kitchen"', false)
            ->assertSee('aria-label="Publish gallery item School supplies"', false)
            ->assertSee('aria-label="Delete gallery item Community kitchen"', false)
            ->assertSee('<span>Edit</span>', false)
            ->assertSee('<span>Unpublish</span>', false)
            ->assertSee('<span>Publish</span>', false)
            ->assertSee('<span>Delete</span>', false)
            ->assertSee('class="igf-danger-action"', false)
            ->assertSee('class="edit btn igf-btn igf-btn-secondary igf-btn-compact"', false)
            ->assertSee('class="btn igf-btn igf-btn-secondary igf-btn-compact status"', false)
            ->assertSee('class="btn igf-btn igf-btn-danger igf-btn-compact trash"', false)
            ->assertSee('data-id="'.$published->uuid.'"', false)
            ->assertSee('data-id="'.$draft->uuid.'"', false)
            ->assertSee('data-url="'.route('gallery.status', $published->uuid).'"', false)
            ->assertSee('data-url="'.route('gallery.destroy', $published->uuid).'"', false);

        $this->assertSame(2, substr_count($response->getContent(), 'class="igf-action-group"'));

        $viewer = $this->makeAdmin('Gallery list viewer', ['gallery.index']);
        $this->actingAs($viewer, 'admin')->get(route('gallery.index'))
            ->assertOk()
            ->assertSee($published->name)
            ->assertDontSee('Add gallery item')
            ->assertDontSee('class="igf-action-group"', false)
            ->assertDontSee('data-url="'.route('gallery.status', $published->uuid).'"', false)
            ->assertDontSee('data-url="'.route('gallery.destroy', $published->uuid).'"', false);
    }

    public function test_donation_cause_screen_has_accessible_guided_controls_and_refreshes_publication_state(): void
    {
        $draft = DonationType::create([
            'name' => 'Emergency appeal draft',
            'description' => 'A draft cause ready for review.',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Emergency Fund',
            'status' => 0,
        ]);
        $admin = $this->makeAdmin('Donation cause owner', ['donationType.index'], [
            'donationType.create',
            'donationType.edit',
            'donationType.status',
            'donationType.destroy',
        ]);

        $this->actingAs($admin, 'admin')->get(route('donationType.index'))
            ->assertOk()
            ->assertSee('<h1 class="h3 mb-1">Donation causes</h1>', false)
            ->assertSee('<label for="donation-type-search"', false)
            ->assertSee('Search donation causes')
            ->assertSee('id="donation-type-search" type="search"', false)
            ->assertSee('class="donation-type-table-scroll" role="region" aria-labelledby="donation-causes-table-title" tabindex="0"', false)
            ->assertSee('Donation causes, their public role, funding destination, readiness, and available actions.')
            ->assertSee('aria-labelledby="donationTypeModalTitle"', false)
            ->assertSee('id="donationTypeModalTitle"', false)
            ->assertSee('<label for="e_name"', false)
            ->assertSee('aria-label="Unpublish donation cause '.$this->donationType->name.'"', false)
            ->assertSee('aria-pressed="true"', false);

        $this->actingAs($admin, 'admin')->get(route('donationType.index', ['search' => $draft->name]))
            ->assertOk()
            ->assertSee('aria-label="Publish donation cause '.$draft->name.'"', false)
            ->assertSee('aria-pressed="false"', false);

        $source = file_get_contents(resource_path('views/admin/donationType/index.blade.php'));
        $this->assertStringContainsString('overflow-x:auto', $source);
        $this->assertStringContainsString('min-width:920px', $source);
        $this->assertStringNotContainsString('table-stats ov-h', $source);
        $this->assertStringContainsString("target.trigger('reset');", $source);
        $this->assertStringContainsString('synchronizeDonationTypeForms();', $source);
        $this->assertStringContainsString('window.location.reload();', $source);
        $this->assertStringNotContainsString('itemStatus({tableId: "donation_type_table"', $source);
    }

    public function test_special_admin_and_role_capabilities_do_not_masquerade_as_record_edit(): void
    {
        $admin = $this->makeAdmin('Special action reviewer', ['admin.index', 'role.index'], [
            'admin.reset',
            'role.permission',
        ]);

        $this->actingAs($admin, 'admin')->get(route('admin.index'))
            ->assertOk()
            ->assertSee(route('admin.reset', $this->listedAdmin->id), false)
            ->assertSee('Reset administrator password')
            ->assertDontSee('class="edit btn', false)
            ->assertDontSee('data-url="'.route('admin.status', $this->listedAdmin->id).'"', false)
            ->assertDontSee('data-url="'.route('admin.destroy', $this->listedAdmin->id).'"', false);

        $this->actingAs($admin, 'admin')->get(route('role.index'))
            ->assertOk()
            ->assertSee('Read-only access.')
            ->assertSee(route('role.permission', $this->listedRole->id), false)
            ->assertSee('View role permissions')
            ->assertDontSee('class="edit btn', false)
            ->assertDontSee('data-url="'.route('role.status', $this->listedRole->id).'"', false)
            ->assertDontSee('data-url="'.route('role.destroy', $this->listedRole->id).'"', false);
    }

    /** @return list<string> */
    private function listMenus(): array
    {
        return [
            'tag.index',
            'latest.news.index',
            'donationType.index',
            'testimonial.index',
            'admin.index',
            'role.index',
        ];
    }

    private function makeAdmin(string $name, array $menuCapabilities, array $actionCapabilities = []): Admin
    {
        $menus = AuthMenu::query()->whereIn('link', $menuCapabilities)->where('status', 1)->get();
        $actions = MenuAction::query()->whereIn('link', $actionCapabilities)->where('status', 1)->get();
        $this->assertCount(count(array_unique($menuCapabilities)), $menus);
        $this->assertCount(count(array_unique($actionCapabilities)), $actions);

        $suffix = Str::lower(Str::random(10));
        $role = Role::create([
            'name' => $name.' role '.$suffix,
            'security_rank' => 100,
            'is_owner' => false,
            'permission' => $menus->pluck('id')->implode(','),
            'actionPermission' => $actions->pluck('id')->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => $name,
            'username' => Str::slug($name).'-'.$suffix,
            'email' => Str::slug($name).'-'.$suffix.'@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
