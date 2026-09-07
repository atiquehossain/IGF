<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Banner;
use App\Models\Category;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\Role;
use App\Models\SiteSetting;
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

    public function test_banner_update_refreshes_an_unchanged_generated_legacy_heading(): void
    {
        $admin = $this->makeAdmin('banner.update');
        Role::whereKey($admin->role)->update(['is_owner' => true]);
        $banner = Banner::create([
            'uuid' => (string) Str::uuid(),
            'name' => '<b>Original headline</b> Original support',
            'headline' => 'Original headline',
            'subheadline' => 'Original support',
            'type' => 'banner-home',
            'language' => 'en',
            'status' => 0,
        ]);

        $this->actingAs($admin, 'admin')->put(route('banner.update'), [
            'uuid' => $banner->uuid,
            'language' => ['en' => 'en'],
            'name' => ['en' => $banner->name],
            'headline' => ['en' => 'Updated headline'],
            'subheadline' => ['en' => 'Updated support'],
            'type' => ['en' => 'banner-home'],
        ])->assertRedirect(route('banner.index'));

        $this->assertSame('<b>Updated headline</b> Updated support', $banner->fresh()->name);
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

    public function test_category_archive_receives_banner_and_normalized_no_code_design_controls(): void
    {
        $banner = Banner::create([
            'uuid' => (string) Str::uuid(),
            'name' => '<b>Our programs</b> Community-led change',
            'headline' => 'Programs built with communities',
            'subheadline' => 'Education, health, livelihoods, and resilience',
            'description' => 'See how local leaders turn support into lasting progress.',
            'image' => 'program archive.webp',
            'path' => 'program archive.webp',
            'image_alt' => 'Young people planting trees together',
            'cta_label' => 'Support a program',
            'cta_url' => '/donate',
            'type' => 'banner-page',
            'language' => 'en',
            'status' => 1,
        ]);
        Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Our programs',
            'slug' => 'our-causes',
            'description' => 'Programs designed with communities.',
            'banner_id' => $banner->id,
            'display_mode' => 'archive',
            'language' => 'en',
            'status' => 1,
        ]);

        $this->putSetting('category_hero_layout', 'split');
        $this->putSetting('category_show_banner', '1', 'boolean', '*');
        $this->putSetting('category_card_columns', '4');
        $this->putSetting('category_card_show_eyebrow', '1', 'boolean', '*');
        $this->putSetting('category_card_eyebrow', 'Program');
        $this->putSetting('category_card_show_link', '1', 'boolean', '*');
        $this->putSetting('category_card_link_label', 'Explore program');
        $this->putSetting('category_bottom_cta_enabled', '1', 'boolean', '*');
        $this->putSetting('category_bottom_cta_title', 'Help create lasting change');
        $this->putSetting('category_bottom_cta_body', 'Choose a program to support.');
        $this->putSetting('category_bottom_cta_label', 'Donate now');
        $this->putSetting('category_bottom_cta_url', '/donate', 'text', '*');

        $this->get(route('frontend.category', ['slug' => 'our-causes']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('category')
                ->where('data.banner.headline', 'Programs built with communities')
                ->where('data.banner.image_url', '/storage/photos/1/banner/program%20archive.webp')
                ->where('data.banner.image_alt', 'Young people planting trees together')
                ->where('data.banner.cta_url', '/donate')
                ->where('data.archive_design.hero_layout', 'split')
                ->where('data.archive_design.show_banner', true)
                ->where('data.archive_design.card_columns', '4')
                ->where('data.archive_design.card_eyebrow', 'Program')
                ->where('data.archive_design.show_card_eyebrow', true)
                ->where('data.archive_design.card_link_label', 'Explore program')
                ->where('data.archive_design.show_card_link', true)
                ->where('data.archive_design.bottom_cta', [
                    'enabled' => true,
                    'title' => 'Help create lasting change',
                    'body' => 'Choose a program to support.',
                    'label' => 'Donate now',
                    'url' => '/donate',
                ])
            );
    }

    public function test_category_archive_design_preserves_split_fallback_and_rejects_stale_columns_and_incomplete_ctas(): void
    {
        Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Programs without a banner',
            'slug' => 'programs-without-banner',
            'display_mode' => 'archive',
            'language' => 'en',
            'status' => 1,
        ]);
        $this->putSetting('category_hero_layout', 'split');
        $this->putSetting('category_card_columns', '12');
        $this->putSetting('category_bottom_cta_enabled', '1', 'boolean', '*');
        $this->putSetting('category_bottom_cta_title', 'Unsafe action');
        $this->putSetting('category_bottom_cta_label', 'Continue');
        $this->putSetting('category_bottom_cta_url', 'javascript:alert(1)', 'text', '*');

        $this->get(route('frontend.category', ['slug' => 'programs-without-banner']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.banner', null)
                ->where('data.archive_design.hero_layout', 'split')
                ->where('data.archive_design.show_banner', true)
                ->where('data.archive_design.card_columns', 'auto')
                ->where('data.archive_design.bottom_cta.enabled', false)
                ->where('data.archive_design.bottom_cta.url', '')
            );
    }

    public function test_category_archive_controls_are_safe_presets_and_discoverable_in_admin(): void
    {
        $fields = config('site-settings.groups.content_archives.fields');

        $this->assertSame(
            ['compact' => 'Compact introduction', 'split' => 'Text beside selected banner'],
            $fields['category_hero_layout']['options']
        );
        $this->assertSame(
            ['auto' => 'Use global design setting', '2' => 'Two', '3' => 'Three', '4' => 'Four'],
            $fields['category_card_columns']['options']
        );
        $this->assertSame('Browse programs', $fields['category_browse_label']['default']);
        $this->assertSame('কর্মসূচিগুলো দেখুন', $fields['category_browse_label']['localized_defaults']['bn']);
        $this->assertSame('Programs', $fields['category_listing_label']['default']);
        $this->assertSame('কর্মসূচিসমূহ', $fields['category_listing_label']['localized_defaults']['bn']);
        $this->assertSame('', $fields['category_card_eyebrow']['default']);
        $this->assertSame('Explore program', $fields['category_card_link_label']['default']);
        $this->assertSame('কর্মসূচি দেখুন', $fields['category_card_link_label']['localized_defaults']['bn']);
        $this->assertTrue($fields['category_bottom_cta_enabled']['default']);
        $this->assertStringContainsString('Our Programs archive', $fields['category_bottom_cta_enabled']['help']);
        $this->assertSame('দীর্ঘস্থায়ী পরিবর্তনে সহায়তা করুন', $fields['category_bottom_cta_title']['localized_defaults']['bn']);
        $this->assertSame('আজই কমিউনিটি-নেতৃত্বাধীন কর্মসূচিকে সহায়তা করার একটি উপায় বেছে নিন।', $fields['category_bottom_cta_body']['localized_defaults']['bn']);
        $this->assertSame('এখনই অনুদান দিন', $fields['category_bottom_cta_label']['localized_defaults']['bn']);
        $this->assertSame('url_or_path', $fields['category_bottom_cta_url']['type']);

        foreach (['add.blade.php', 'edit.blade.php'] as $view) {
            $source = file_get_contents(resource_path('views/admin/category/' . $view));
            $this->assertStringContainsString('Open archive design controls', $source);
            $this->assertStringContainsString('#settings-content_archives', $source);
            $this->assertStringContainsString('split hero', $source);
        }
        $customizer = file_get_contents(resource_path('views/admin/site-settings/index.blade.php'));
        $this->assertStringContainsString("'Programs' => \$localizedRoute('frontend.category'", $customizer);
        $this->assertStringContainsString("\$settingsAction('content_archives', 'category archive design')", $customizer);
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

    private function putSetting(string $key, string $value, string $type = 'text', string $locale = 'en'): void
    {
        SiteSetting::create([
            'group' => 'content_archives',
            'key' => $key,
            'locale' => $locale,
            'value' => $value,
            'type' => $type,
            'is_public' => true,
        ]);
    }
}
