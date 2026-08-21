<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\ReusableBlock;
use App\Models\Role;
use App\Support\AdminPermissionRegistry;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminLibraryPermissionUxTest extends TestCase
{
    use RefreshDatabase;

    private MediaAsset $media;
    private MediaAsset $trashedMedia;
    private ReusableBlock $reusableBlock;
    private ReusableBlock $trashedReusableBlock;
    private Page $trashedPage;
    private Category $trashedCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionRegistrySeeder::class);

        $this->media = $this->makeMedia('public-guide.png');
        $this->trashedMedia = $this->makeMedia('archived-guide.png');
        $this->trashedMedia->delete();

        $this->reusableBlock = $this->makeReusableBlock('Active impact summary');
        $this->trashedReusableBlock = $this->makeReusableBlock('Archived impact summary');
        $this->trashedReusableBlock->delete();

        $this->trashedPage = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Archived community page',
            'sub_title' => 'A recoverable page',
            'slug' => 'archived-community-page',
            'status' => 0,
            'language' => 'en',
        ]);
        $this->trashedPage->delete();

        $this->trashedCategory = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Archived education category',
            'slug' => 'archived-education-category',
            'status' => 0,
            'language' => 'en',
        ]);
        $this->trashedCategory->delete();
    }

    public function test_view_only_staff_keep_useful_library_details_without_mutating_controls(): void
    {
        $admin = $this->makeAdmin([
            'media.index',
            'reusable-blocks.index',
            'content.trash.index',
        ], ['page.trash.view']);

        $this->actingAs($admin, 'admin')->get(route('media.index'))
            ->assertOk()
            ->assertSee('Read-only access')
            ->assertSee($this->media->original_name)
            ->assertSee('Copy URL')
            ->assertSee('View only')
            ->assertDontSee('Upload media')
            ->assertDontSee('action="'.route('media.destroy', $this->media).'"', false);

        $this->actingAs($admin, 'admin')->get(route('media.index', ['trash' => 1]))
            ->assertOk()
            ->assertSee($this->trashedMedia->original_name)
            ->assertSee('Copy URL')
            ->assertSee('View only')
            ->assertDontSee('action="'.route('media.restore', $this->trashedMedia->uuid).'"', false)
            ->assertDontSee('action="'.route('media.force-destroy', $this->trashedMedia->uuid).'"', false);

        $this->actingAs($admin, 'admin')->get(route('reusable-blocks.index'))
            ->assertOk()
            ->assertSee('Read-only access')
            ->assertSee($this->reusableBlock->name)
            ->assertSee('Instances')
            ->assertSee('View only')
            ->assertDontSee('action="'.route('reusable-blocks.destroy', $this->reusableBlock).'"', false);

        $this->actingAs($admin, 'admin')->get(route('reusable-blocks.index', ['trash' => 1]))
            ->assertOk()
            ->assertSee($this->trashedReusableBlock->name)
            ->assertSee('View only')
            ->assertDontSee('action="'.route('reusable-blocks.restore', $this->trashedReusableBlock->uuid).'"', false)
            ->assertDontSee('action="'.route('reusable-blocks.force-destroy', $this->trashedReusableBlock->uuid).'"', false);

        $this->actingAs($admin, 'admin')->get(route('page.trash.index'))
            ->assertOk()
            ->assertSee('Read-only access')
            ->assertSee($this->trashedPage->name)
            ->assertSee('Back to dashboard')
            ->assertSee('View only')
            ->assertDontSee('data-url="'.route('page.trash.restore', $this->trashedPage->uuid).'"', false)
            ->assertDontSee('data-url="'.route('page.trash.force-destroy', $this->trashedPage->uuid).'"', false);

        $this->actingAs($admin, 'admin')->get(route('content.trash.index'))
            ->assertOk()
            ->assertSee('Read-only access')
            ->assertSee($this->trashedCategory->name)
            ->assertSee('Back to dashboard')
            ->assertSee('View only')
            ->assertDontSee('data-url="'.route('content.trash.restore', ['category', $this->trashedCategory->id]).'"', false)
            ->assertDontSee('data-url="'.route('content.trash.force-destroy', ['category', $this->trashedCategory->id]).'"', false);
    }

    public function test_restore_permissions_show_only_restore_controls(): void
    {
        $admin = $this->makeAdmin([
            'media.index',
            'reusable-blocks.index',
            'content.trash.index',
        ], [
            'media.edit',
            'reusable-blocks.edit',
            'page.trash.view',
            'page.trash.edit',
            'content.trash.edit',
        ]);

        $this->actingAs($admin, 'admin')->get(route('media.index', ['trash' => 1]))
            ->assertOk()
            ->assertSee('action="'.route('media.restore', $this->trashedMedia->uuid).'"', false)
            ->assertDontSee('action="'.route('media.force-destroy', $this->trashedMedia->uuid).'"', false);

        $this->actingAs($admin, 'admin')->get(route('reusable-blocks.index', ['trash' => 1]))
            ->assertOk()
            ->assertSee('action="'.route('reusable-blocks.restore', $this->trashedReusableBlock->uuid).'"', false)
            ->assertDontSee('action="'.route('reusable-blocks.force-destroy', $this->trashedReusableBlock->uuid).'"', false);

        $this->actingAs($admin, 'admin')->get(route('page.trash.index'))
            ->assertOk()
            ->assertSee('data-url="'.route('page.trash.restore', $this->trashedPage->uuid).'"', false)
            ->assertDontSee('data-url="'.route('page.trash.force-destroy', $this->trashedPage->uuid).'"', false);

        $this->actingAs($admin, 'admin')->get(route('content.trash.index'))
            ->assertOk()
            ->assertSee('data-url="'.route('content.trash.restore', ['category', $this->trashedCategory->id]).'"', false)
            ->assertDontSee('data-url="'.route('content.trash.force-destroy', ['category', $this->trashedCategory->id]).'"', false);
    }

    public function test_create_and_delete_permissions_show_only_their_allowed_controls(): void
    {
        $admin = $this->makeAdmin([
            'media.index',
            'reusable-blocks.index',
            'content.trash.index',
        ], [
            'media.create',
            'media.destroy',
            'reusable-blocks.destroy',
            'page.trash.view',
            'page.trash.destroy',
            'content.trash.destroy',
        ]);

        $this->actingAs($admin, 'admin')->get(route('media.index'))
            ->assertOk()
            ->assertSee('Upload media')
            ->assertSee('action="'.route('media.destroy', $this->media).'"', false);
        $this->actingAs($admin, 'admin')->get(route('media.index', ['trash' => 1]))
            ->assertOk()
            ->assertSee('action="'.route('media.force-destroy', $this->trashedMedia->uuid).'"', false)
            ->assertDontSee('action="'.route('media.restore', $this->trashedMedia->uuid).'"', false);

        $this->actingAs($admin, 'admin')->get(route('reusable-blocks.index'))
            ->assertOk()
            ->assertSee('action="'.route('reusable-blocks.destroy', $this->reusableBlock).'"', false);
        $this->actingAs($admin, 'admin')->get(route('reusable-blocks.index', ['trash' => 1]))
            ->assertOk()
            ->assertSee('action="'.route('reusable-blocks.force-destroy', $this->trashedReusableBlock->uuid).'"', false)
            ->assertDontSee('action="'.route('reusable-blocks.restore', $this->trashedReusableBlock->uuid).'"', false);

        $this->actingAs($admin, 'admin')->get(route('page.trash.index'))
            ->assertOk()
            ->assertSee('data-url="'.route('page.trash.force-destroy', $this->trashedPage->uuid).'"', false)
            ->assertDontSee('data-url="'.route('page.trash.restore', $this->trashedPage->uuid).'"', false);

        $this->actingAs($admin, 'admin')->get(route('content.trash.index'))
            ->assertOk()
            ->assertSee('data-url="'.route('content.trash.force-destroy', ['category', $this->trashedCategory->id]).'"', false)
            ->assertDontSee('data-url="'.route('content.trash.restore', ['category', $this->trashedCategory->id]).'"', false);
    }

    public function test_registered_canonical_capabilities_are_queryable_without_weakening_route_overrides(): void
    {
        foreach ([
            'media.create',
            'media.edit',
            'media.destroy',
            'reusable-blocks.edit',
            'reusable-blocks.destroy',
            'page.trash.edit',
            'page.trash.destroy',
            'content.trash.edit',
            'content.trash.destroy',
        ] as $capability) {
            $this->assertSame([$capability], AdminPermissionRegistry::capabilitiesForRoute($capability), $capability);
        }

        $this->assertSame(['seo.metadata.view', 'seo.metadata.edit'], AdminPermissionRegistry::capabilitiesForRoute('seo.index'));
        $this->assertSame([], AdminPermissionRegistry::capabilitiesForRoute('admin.not-registered'));
    }

    private function makeAdmin(array $menuCapabilities, array $actionCapabilities): Admin
    {
        $menus = AuthMenu::query()->whereIn('link', $menuCapabilities)->get();
        $actions = MenuAction::query()->whereIn('link', $actionCapabilities)->get();
        $this->assertCount(count(array_unique($menuCapabilities)), $menus);
        $this->assertCount(count(array_unique($actionCapabilities)), $actions);

        $suffix = Str::lower(Str::random(10));
        $role = Role::create([
            'name' => 'Library permission QA '.$suffix,
            'permission' => $menus->pluck('id')->implode(','),
            'actionPermission' => $actions->pluck('id')->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Library Permission QA',
            'username' => 'library-permission-'.$suffix,
            'email' => 'library-permission-'.$suffix.'@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }

    private function makeMedia(string $name): MediaAsset
    {
        return MediaAsset::create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => 'media/qa/'.$name,
            'original_name' => $name,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'bytes' => 2048,
            'width' => 640,
            'height' => 360,
            'alt_text' => 'Permission test image',
        ]);
    }

    private function makeReusableBlock(string $name): ReusableBlock
    {
        return ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'type' => 'text',
            'locale' => 'en',
            'content' => ['body' => 'Useful reusable content'],
            'settings' => [],
            'is_enabled' => true,
        ]);
    }
}
