<?php

namespace Tests\Feature;

use App\Models\Volunteer;
use App\Models\VolunteerCause;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\PageTagModule;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicJourneyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_sponsor_and_volunteer_pages_have_useful_defaults_and_owner_managed_content(): void
    {
        $active = VolunteerCause::create(['name' => 'Education', 'status' => true]);
        VolunteerCause::create(['name' => 'Private draft cause', 'status' => false]);

        $this->get(route('frontend.sponsor_child'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sponsor_child')
                ->where('meta_tag.meta_title', 'Sponsor a Child | Ignite Global Foundation')
                ->where('siteSettings.sponsor_page.monthly_amount', 1500)
                ->where('siteSettings.sponsor_page.benefit_1', 'Quality education')
            );

        $this->get(route('frontend.volunteer_registration.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('volunteer-registration')
                ->where('meta_tag.meta_title', 'Volunteer with Ignite | Ignite Global Foundation')
                ->where('siteSettings.volunteer_page.title', 'Bring your time, skills, and heart.')
                ->has('data.causes', 1)
                ->where('data.causes.0.id', $active->id)
            );
    }

    public function test_volunteer_registration_accepts_only_an_active_configured_cause(): void
    {
        Mail::fake();
        $inactive = VolunteerCause::create(['name' => 'Inactive cause', 'status' => false]);

        $payload = [
            'name' => 'Volunteer Tester',
            'institution' => 'Community College',
            'email' => 'volunteer@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'cause_id' => $inactive->id,
        ];

        $this->post(route('frontend.volunteer_registration.store'), $payload)
            ->assertSessionHasErrors('cause_id');
        $this->assertSame(0, Volunteer::count());

        $active = VolunteerCause::create(['name' => 'Education', 'status' => true]);
        $payload['cause_id'] = $active->id;
        $this->post(route('frontend.volunteer_registration.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Volunteer::count());
    }

    public function test_missing_category_event_and_report_download_return_real_404_responses(): void
    {
        $this->get('/category/does-not-exist')->assertNotFound();
        $this->get('/event/does-not-exist')->assertNotFound();
        $this->get('/annual-report/download/does-not-exist')->assertNotFound();
        $this->get('/projects/does-not-exist')->assertNotFound();
        $this->get('/about-us')->assertNotFound();
        $this->get('/zakat')->assertNotFound();
    }

    public function test_gallery_and_member_login_have_safe_public_presentation_defaults(): void
    {
        $this->get('/gallery')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gallery')
                ->where('meta_tag.meta_title', 'Photo Gallery | Ignite Global Foundation')
                ->where('properties.total_count', 0)
            );

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/login')
                ->where('meta_tag.robots', 'noindex,nofollow,noarchive')
            );

        $source = file_get_contents(resource_path('js/Pages/auth/login.vue'));
        $this->assertStringContainsString("phone_no: ''", $source);
        $this->assertStringNotContainsString('mamunmo25', $source);
        $this->assertStringNotContainsString("password: '123456'", $source);
    }

    public function test_projects_index_has_a_real_empty_state_and_unknown_project_filters_still_404(): void
    {
        $this->get(route('frontend.project'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('project')
                ->where('title', 'Our projects')
                ->where('meta_tag.meta_title', 'Our Projects | Ignite Global Foundation')
                ->where('meta_tag.canonical_url', route('frontend.project'))
                ->where('properties.total_count', 0)
                ->where('data.tag', null)
                ->has('data.items', 0)
            );

        $this->get('/projects/does-not-exist')->assertNotFound();
    }

    public function test_library_media_paths_are_not_prefixed_twice_on_events_or_projects(): void
    {
        NoticeBoard::create([
            'title' => 'Media path event',
            'slug' => 'media-path-event',
            'description' => 'A published event.',
            'image_path' => '/storage/media/events/community.jpg',
            'language' => 'en',
            'status' => 1,
            'published_at' => now(),
        ]);
        $tag = Tag::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Current projects',
            'slug' => 'current-project',
            'status' => 1,
        ]);
        $page = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Media path project',
            'sub_title' => 'A published project.',
            'slug' => 'media-path-project',
            'thumbnail' => '/storage/media/projects/community.jpg',
            'language' => 'en',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now(),
        ]);
        PageTagModule::create(['uuid' => (string) Str::uuid(), 'page_id' => $page->id, 'tag_id' => $tag->id]);

        $this->get(route('frontend.events'))->assertOk()->assertInertia(fn (Assert $response) => $response
            ->where('data.items.0.image_url', '/storage/media/events/community.jpg'));
        $this->get(route('frontend.project', ['slug' => $tag->slug]))->assertOk()->assertInertia(fn (Assert $response) => $response
            ->where('data.items.0.thumbnail', '/storage/media/projects/community.jpg'));
    }

    public function test_retired_learning_resources_api_keeps_a_stable_empty_contract(): void
    {
        $this->assertFalse(\Schema::hasTable('resources'));

        $this->getJson(route('api.frontend.resources'))
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'properties' => ['page' => 1, 'total_page' => 1, 'total_count' => 0],
                'data' => ['resources' => []],
            ]);
    }
}
