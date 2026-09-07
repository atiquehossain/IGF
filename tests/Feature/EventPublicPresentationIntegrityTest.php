<?php

namespace Tests\Feature;

use App\Models\NoticeBoard;
use App\Models\SiteSetting;
use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use App\Services\SiteSettingService;
use App\Services\TranslationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPublicPresentationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_presentation_labels_are_public_localized_and_client_customizable(): void
    {
        $settings = app(SiteSettingService::class);
        $english = $settings->values('en', true)['content_archives'];
        $bangla = $settings->values('bn', true)['content_archives'];

        $this->assertSame('Event schedule and attendance', $english['event_facts_label']);
        $this->assertSame('Starts', $english['event_start_label']);
        $this->assertSame('Moved online', $english['event_status_moved_online_label']);
        $this->assertSame('In person and online', $english['event_attendance_mixed_label']);
        $this->assertSame('Events', $english['event_archive_title']);
        $this->assertSame('Latest news', $english['news_archive_title']);
        $this->assertSame('Go to page {0}', $english['events_pagination_page_label']);
        $this->assertSame('ইভেন্টের সময়সূচি ও অংশগ্রহণের তথ্য', $bangla['event_facts_label']);
        $this->assertSame('বাতিল', $bangla['event_status_cancelled_label']);
        $this->assertSame('সরাসরি ও অনলাইন', $bangla['event_attendance_mixed_label']);
        $this->assertSame('ইভেন্ট', $bangla['event_archive_title']);
        $this->assertSame('সর্বশেষ সংবাদ', $bangla['news_archive_title']);
        $this->assertSame('পৃষ্ঠা {0}-এ যান', $bangla['events_pagination_page_label']);

        SiteSetting::create([
            'group' => 'content_archives',
            'key' => 'event_status_cancelled_label',
            'locale' => 'en',
            'value' => 'This event will not take place',
            'type' => 'text',
            'is_public' => true,
        ]);

        $this->get('/events')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where(
                    'siteSettings.content_archives.event_status_cancelled_label',
                    'This event will not take place'
                )
            );
    }

    public function test_event_archive_and_detail_expose_managed_facts_without_inventing_them_for_news(): void
    {
        $event = NoticeBoard::create([
            'title' => 'Community day',
            'sub_title' => 'A gathering led by local volunteers.',
            'slug' => 'community-day-presentation',
            'description' => '<p>Meet the community.</p>',
            'image_alt' => 'Volunteers welcoming families at the community day',
            'content_kind' => 'event',
            'event_start_at' => '2026-10-10 10:00:00',
            'event_end_at' => '2026-10-10 14:00:00',
            'event_status' => 'postponed',
            'event_attendance_mode' => 'mixed',
            'location' => 'Dhaka Community Centre',
            'published_at' => now()->subDay(),
            'language' => 'en',
            'order_by' => 20,
            'status' => 1,
        ]);
        $article = NoticeBoard::create([
            'title' => 'Field update',
            'slug' => 'field-update-presentation',
            'description' => '<p>A published community story.</p>',
            'content_kind' => 'article',
            'published_at' => now()->subDay(),
            'language' => 'en',
            'order_by' => 10,
            'status' => 1,
        ]);

        $this->get('/events')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('archive_kind', 'event')
                ->where('data.items.0.id', $event->id)
                ->where('data.items.0.content_kind', 'event')
                ->where('data.items.0.event_status', 'postponed')
                ->where('data.items.0.event_attendance_mode', 'mixed')
                ->where('data.items.0.location', 'Dhaka Community Centre')
                ->where('data.items.0.image_alt', 'Volunteers welcoming families at the community day')
                ->missing('data.items.1')
            );

        $this->get('/news')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('archive_kind', 'article')
                ->where('data.items.0.id', $article->id)
                ->where('data.items.0.content_kind', 'article')
                ->where('data.items.0.image_alt', 'Field update')
                ->where('data.items.0.event_start_at', null)
                ->where('data.items.0.event_status', null)
                ->where('data.items.0.event_attendance_mode', null)
                ->missing('data.items.1')
            );

        $this->get('/event/' . $event->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('event')
                ->where('data.event.content_kind', 'event')
                ->where('data.event.event_status', 'postponed')
                ->where('data.event.event_attendance_mode', 'mixed')
                ->where('data.event.image_alt', 'Volunteers welcoming families at the community day')
            );

        $this->get('/event/' . $article->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('event')
                ->where('data.event.content_kind', 'article')
                ->where('data.event.event_start_at', null)
                ->where('data.event.event_status', null)
                ->where('data.event.event_attendance_mode', null)
            );
    }

    public function test_annual_report_download_requires_a_slug_and_uses_the_branded_not_found_page(): void
    {
        config()->set('app.debug', false);

        $this->get('/annual-report/download')
            ->assertNotFound()
            ->assertSee('We could not find that page.')
            ->assertDontSee('TypeError');
    }

    public function test_notice_custom_css_is_explicitly_scoped_to_the_sanitized_detail_page_path(): void
    {
        $resolver = file_get_contents(resource_path('js/Shared/pageCss.js'));
        $controller = file_get_contents(app_path('Http/Controllers/Vue/NoticeBoardController.php'));

        $this->assertStringContainsString("event: 'event'", $resolver);
        $this->assertStringContainsString('sanitizeCss($event->inline_css)', $controller);

        foreach (['add.blade.php', 'edit.blade.php'] as $view) {
            $source = file_get_contents(resource_path('views/admin/notice-board/' . $view));
            $this->assertStringContainsString('affects only this item’s public detail page', $source);
            $this->assertStringContainsString('not the Events &amp; News listing', $source);
        }
    }

    public function test_event_image_description_is_per_language_and_used_by_managed_event_cards(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_06_180000_add_image_alt_to_notice_boards_table.php'));
        $resolver = file_get_contents(app_path('Services/PageBlockContentResolver.php'));
        $translationCenter = file_get_contents(app_path('Services/TranslationCenterService.php'));

        $this->assertStringContainsString("string('image_alt', 420)", $migration);
        $this->assertStringContainsString("'image_alt' => \$event->image_alt ?: \$event->title", $resolver);
        $this->assertMatchesRegularExpression(
            "/'event'.*?'fields'\s*=>\s*\[[^\]]*'image_alt'/s",
            $translationCenter
        );

        $event = NoticeBoard::create([
            'title' => 'Accessible community event',
            'slug' => 'accessible-community-event',
            'image_alt' => 'Families joining a community workshop',
            'language' => 'en',
            'published_at' => now()->subDay(),
            'status' => 1,
        ]);
        $translationRow = app(TranslationCenterService::class)->rows('en', 'bn')->first(fn (array $row): bool =>
            ($row['identity']['model'] ?? null) === 'event'
            && ($row['identity']['source_id'] ?? null) === $event->id
            && ($row['identity']['field'] ?? null) === 'image_alt'
        );
        $this->assertNotNull($translationRow);
        $this->assertSame('Families joining a community workshop', $translationRow['source']);

        foreach (['add.blade.php', 'edit.blade.php'] as $view) {
            $source = file_get_contents(resource_path('views/admin/notice-board/' . $view));
            $this->assertStringContainsString('name="image_alt"', $source);
            $this->assertStringContainsString('Leave blank to use the title.', $source);
        }
    }

    public function test_event_editor_saves_a_plain_text_image_description_and_validates_its_length(): void
    {
        $event = NoticeBoard::create([
            'title' => 'Editor image description',
            'slug' => 'editor-image-description',
            'language' => 'en',
            'published_at' => now()->subDay(),
            'status' => 1,
        ]);
        $admin = $this->makeAdmin('notice.board.edit');

        $this->actingAs($admin, 'admin')
            ->get(route('notice.board.edit', $event->id))
            ->assertOk()
            ->assertSee('Image description (optional)')
            ->assertSee('name="image_alt"', false);

        $payload = [
            'id' => $event->id,
            'title' => $event->title,
            'published_at' => now()->format('d-m-Y'),
            'language' => 'en',
            'image_alt' => '  Children <b>planting trees</b> together  ',
        ];
        $this->actingAs($admin, 'admin')
            ->put(route('notice.board.update'), $payload)
            ->assertRedirect(route('notice.board.index'));
        $this->assertSame('Children planting trees together', $event->fresh()->image_alt);

        $payload['image_alt'] = str_repeat('x', 421);
        $this->actingAs($admin, 'admin')
            ->from(route('notice.board.edit', $event->id))
            ->put(route('notice.board.update'), $payload)
            ->assertRedirect(route('notice.board.edit', $event->id))
            ->assertSessionHasErrors('image_alt');
        $this->assertSame('Children planting trees together', $event->fresh()->image_alt);
    }

    private function makeAdmin(string $capability): Admin
    {
        $menu = AuthMenu::where('link', 'notice.board.index')->firstOrFail();
        $action = MenuAction::where('link', $capability)->firstOrFail();
        $role = Role::create([
            'name' => 'Event image editor',
            'permission' => (string) $menu->id,
            'actionPermission' => (string) $action->id,
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Event image editor',
            'username' => 'event-image-editor',
            'email' => 'event-image-editor@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
