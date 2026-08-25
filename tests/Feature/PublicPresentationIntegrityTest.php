<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPresentationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_has_owner_managed_content_and_useful_seo(): void
    {
        $this->get(route('frontend.contactUs'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('contactUs')
                ->where('meta_tag.meta_title', 'Contact Us | Ignite Global Foundation')
                ->where('siteSettings.contact_page.title', "Let's start a conversation.")
                ->where('siteSettings.contact_page.faq_1_question', 'What is Ignite Global Foundation?')
                ->where('siteSettings.contact.email', 'info@ignite.org.bd')
                ->where('siteSettings.contact.phone_primary', '+8801972016221')
                ->where('siteSettings.contact.phone_secondary', '')
                ->where('siteSettings.contact.address', 'Madrasah Road, House-847, Level (A-1), East Kazi Para, Mirpur, Dhaka-1216')
                ->missing('siteSettings.contact.footer_heading')
                ->where('siteSettings.contact.footer_address_label', 'Address')
                ->where('siteSettings.contact.footer_phone_label', 'Cell')
                ->where('siteSettings.contact.footer_secondary_phone_label', 'Alternate cell')
                ->where('siteSettings.contact.footer_email_label', 'Email')
            );
    }

    public function test_public_templates_include_responsive_stitch_aligned_primitives(): void
    {
        $this->assertStringContainsString('igf-page-hero__overlay', file_get_contents(resource_path('js/component/banner.vue')));
        $this->assertStringContainsString('width:100%', file_get_contents(resource_path('js/Shared/category-item-card.vue')));
        $this->assertStringContainsString('role="search"', file_get_contents(resource_path('js/Pages/annual-report.vue')));
        $this->assertStringContainsString(':aria-expanded=', file_get_contents(resource_path('js/Pages/contactUs.vue')));
        $this->assertStringNotContainsString('linear-gradient', file_get_contents(resource_path('js/Pages/contactUs.vue')));
    }

    public function test_public_submission_routes_are_throttled_and_contact_message_is_bounded(): void
    {
        foreach ([
            'frontend.send.sms' => 'throttle:10,1',
            'frontend.subscribe' => 'throttle:newsletter-subscribe',
            'frontend.sponsorship.store' => 'throttle:10,1',
            'frontend.volunteer_registration.store' => 'throttle:10,1',
            'frontend.donate.store' => 'throttle:10,1',
        ] as $name => $throttle) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertContains($throttle, $route->gatherMiddleware());
        }

        $this->post(route('frontend.send.sms'), [
            'first_name' => 'QA visitor',
            'email' => 'qa@example.test',
            'phone' => '0123456789',
            'message' => str_repeat('A', 5001),
        ])->assertSessionHasErrors('message');
    }

    public function test_public_shell_allows_zoom_has_one_main_contract_and_a_skip_link(): void
    {
        $blade = file_get_contents(resource_path('views/app.blade.php'));
        $layout = file_get_contents(resource_path('js/layouts/App.vue'));
        $cssResolver = file_get_contents(resource_path('js/Shared/pageCss.js'));
        $guestLayout = file_get_contents(resource_path('js/layouts/GuestLayout.vue'));

        $this->assertStringNotContainsString('maximum-scale', $blade);
        $this->assertStringContainsString('href="#main-content"', $layout);
        $this->assertSame(1, substr_count($layout, '<main'));
        $this->assertSame(1, substr_count($layout, '</main>'));
        $this->assertSame(1, substr_count($guestLayout, '<main'));
        $this->assertSame(1, substr_count($guestLayout, '</main>'));
        $this->assertStringContainsString('id="main-content"', $guestLayout);

        foreach (glob(resource_path('js/Pages/*.vue')) as $component) {
            $this->assertStringNotContainsString('<main', file_get_contents($component), basename($component));
        }
        foreach (glob(resource_path('js/Pages/auth/*.vue')) as $component) {
            $this->assertStringNotContainsString('<main', file_get_contents($component), basename($component));
        }

        $this->assertStringContainsString('resolvePageCss(inertiaPage.component', $layout);
        foreach (['homePage', 'about_us', 'zakat', 'category', 'event', 'page'] as $pageSource) {
            $this->assertStringContainsString("'{$pageSource}'", $cssResolver);
        }
        $this->assertStringNotContainsString('sponsor_child', $cssResolver);
    }

    public function test_ignored_optional_paths_do_not_create_duplicate_public_pages(): void
    {
        $this->get('/about-us/duplicate')->assertNotFound();
        $this->get('/gallery/duplicate')->assertNotFound();
        $this->get('/events/duplicate')->assertNotFound();
        $this->get('/donate/unknown')->assertNotFound();
    }

    public function test_primary_public_selects_have_explicit_accessible_names(): void
    {
        $donateTemplate = file_get_contents(resource_path('js/Pages/donate.vue'));
        $this->assertStringContainsString('data-test="locked-donation-cause"', $donateTemplate);
        $this->assertStringContainsString('role="status"', $donateTemplate);
        $this->assertStringNotContainsString('id="donation-cause"', $donateTemplate);
        $this->assertStringContainsString(':aria-label="settings.interval_field_label"', file_get_contents(resource_path('js/Pages/sponsor_child.vue')));
        $this->assertStringContainsString(':aria-label="settings.cause_field_label"', file_get_contents(resource_path('js/Pages/volunteer-registration.vue')));
        $this->assertStringContainsString('for="sponsorship-interval"', file_get_contents(resource_path('js/Pages/sponsor_child.vue')));
        $this->assertStringContainsString('for="volunteer-cause"', file_get_contents(resource_path('js/Pages/volunteer-registration.vue')));
    }

    public function test_local_launch_content_contains_no_published_placeholder_or_dead_feature_link(): void
    {
        $seeder = file_get_contents(database_path('seeders/LocalDevelopmentSeeder.php'));

        $this->assertStringNotContainsString('Replace this local demonstration text before production release.', $seeder);
        $this->assertStringNotContainsString("'url' => '/page/community-health-outreach'", $seeder);
        $this->assertStringNotContainsString('ready for a client-approved', $seeder);
    }

    public function test_upgrade_migration_repairs_stale_links_in_any_page_block(): void
    {
        $pageId = DB::table('pages')->insertGetId([
            'name' => 'Legacy homepage',
            'sub_title' => 'Migration fixture',
            'slug' => 'legacy-home',
            'uuid' => '99999999-9999-4999-8999-000000000001',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $blockId = DB::table('page_blocks')->insertGetId([
            'page_id' => $pageId,
            'uuid' => '99999999-9999-4999-8999-000000000002',
            'type' => 'cards',
            'content' => json_encode([
                'items' => [['url' => '/page/community-health-outreach']],
            ], JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_08_14_000016_repair_stale_page_block_links.php');
        $migration->up();

        $content = json_decode((string) DB::table('page_blocks')->where('id', $blockId)->value('content'), true);
        $this->assertSame('/category/our-causes', $content['items'][0]['url']);
    }
}
