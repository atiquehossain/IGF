<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Banner;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BannerPresentationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_banner_editor_saves_structured_plain_text_content_and_legacy_name(): void
    {
        $admin = $this->makeAdmin('banner.create');

        $this->actingAs($admin, 'admin')->get(route('banner.create'))
            ->assertOk()
            ->assertSee('Headline')
            ->assertSee('Image alternative text')
            ->assertSee('Button destination');

        $this->actingAs($admin, 'admin')->post(route('banner.store'), [
            'language' => ['en' => 'en'],
            'type' => ['en' => 'banner-page'],
            'headline' => ['en' => '<strong>Education for every child</strong>'],
            'subheadline' => ['en' => 'Learning with dignity'],
            'eyebrow' => ['en' => 'Our work'],
            'description' => ['en' => '<script>alert(1)</script>A community-led program.'],
            'image_alt' => ['en' => 'Students reading together'],
            'cta_label' => ['en' => 'Explore education'],
            'cta_url' => ['en' => '/category/education'],
        ])->assertRedirect(route('banner.index'));

        $banner = Banner::firstOrFail();
        $this->assertSame('Education for every child', $banner->headline);
        $this->assertSame('Learning with dignity', $banner->subheadline);
        $this->assertSame('<b>Education for every child</b> Learning with dignity', $banner->name);
        $this->assertSame('alert(1)A community-led program.', $banner->description);
        $this->assertSame('Students reading together', $banner->image_alt);
        $this->assertSame('/category/education', $banner->cta_url);
    }

    public function test_banner_editor_rejects_an_unsafe_call_to_action(): void
    {
        $admin = $this->makeAdmin('banner.create');

        $this->actingAs($admin, 'admin')->post(route('banner.store'), [
            'language' => ['en' => 'en'],
            'type' => ['en' => 'banner-page'],
            'headline' => ['en' => 'Safe headline'],
            'cta_url' => ['en' => 'javascript:alert(1)'],
        ])->assertSessionHasErrors('cta_url.en');

        $this->assertDatabaseCount('banners', 0);
    }

    public function test_public_page_receives_structured_banner_content_and_normalized_media_url(): void
    {
        $banner = Banner::create([
            'uuid' => (string) Str::uuid(),
            'name' => '<b>Legacy headline</b> Legacy subheadline',
            'headline' => 'Managed headline',
            'subheadline' => 'Managed supporting headline',
            'image' => 'hero image.webp',
            'path' => 'hero image.webp',
            'image_alt' => 'A community workshop',
            'cta_label' => 'Read the story',
            'cta_url' => '/page/community-story',
            'type' => 'banner-page',
            'language' => 'en',
            'status' => 1,
        ]);
        Page::create([
            'uuid' => (string) Str::uuid(),
            'banner_id' => $banner->id,
            'name' => 'Banner test page',
            'sub_title' => '',
            'slug' => 'banner-test-page',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
        ]);

        $this->get(route('frontend.page', 'banner-test-page'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('page')
                ->where('data.banner.headline', 'Managed headline')
                ->where('data.banner.image_alt', 'A community workshop')
                ->where('data.banner.image_url', '/storage/photos/1/banner/hero%20image.webp')
                ->where('data.banner.cta_url', '/page/community-story')
            );
    }

    private function makeAdmin(string $capability): Admin
    {
        $menu = AuthMenu::create(['name' => 'Banners', 'link' => 'banner.index', 'status' => 1]);
        $action = MenuAction::create([
            'auth_menu_id' => $menu->id,
            'name' => 'Create banners',
            'link' => $capability,
            'status' => 1,
        ]);
        $role = Role::create([
            'name' => 'Banner editor',
            'permission' => (string) $menu->id,
            'actionPermission' => (string) $action->id,
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Banner QA',
            'username' => 'banner-qa',
            'email' => 'banner-qa@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
