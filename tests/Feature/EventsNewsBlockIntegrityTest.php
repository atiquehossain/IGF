<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PageBuilderController;
use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\NoticeBoard;
use App\Models\PageBlock;
use App\Models\Role;
use App\Services\PageBlockContentResolver;
use App\Services\TranslationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EventsNewsBlockIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-07 12:00:00', config('app.timezone')));
        app()->setLocale('en');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dedicated_section_contract_is_configured_validated_and_translation_safe(): void
    {
        $this->assertSame(
            'Upcoming events + featured news',
            config('page-builder.block_types.events_news')
        );
        $this->assertSame(
            'Scheduled events and published news',
            config('page-builder.automatic_sources.events_news.events_news')
        );

        $firstEventKey = (string) Str::uuid();
        $secondEventKey = (string) Str::uuid();
        $newsKey = (string) Str::uuid();
        $content = array_replace(config('page-builder.default_content.events_news'), [
            'eyebrow' => 'Latest from Ignite',
            'events_heading' => 'What is coming up',
            'news_heading' => 'Read our latest story',
            'events_selection_mode' => 'manual',
            'selected_event_ids' => [$firstEventKey, $secondEventKey],
            'event_limit' => 2,
            'featured_news_id' => $newsKey,
        ]);
        $validated = app(PageBuilderController::class)
            ->validateReusableBlockPayload('events_news', $content, 'en');

        $this->assertSame($content, $validated);

        $translated = app(TranslationCenterService::class)->prepareBlockTranslationContent($content);
        $this->assertSame('', $translated['eyebrow']);
        $this->assertSame('', $translated['events_heading']);
        $this->assertSame('', $translated['news_heading']);
        $this->assertSame('events_news', $translated['content_source']);
        $this->assertSame('manual', $translated['events_selection_mode']);
        $this->assertSame([$firstEventKey, $secondEventKey], $translated['selected_event_ids']);
        $this->assertSame(2, $translated['event_limit']);
        $this->assertSame($newsKey, $translated['featured_news_id']);

        $legacy = array_replace($content, [
            'selected_event_ids' => [9, '4'],
            'featured_news_id' => '18',
        ]);
        $this->assertSame(
            $legacy,
            app(PageBuilderController::class)->validateReusableBlockPayload('events_news', $legacy, 'en')
        );
    }

    public function test_events_news_fields_are_rejected_on_other_section_types(): void
    {
        try {
            app(PageBuilderController::class)->validateReusableBlockPayload('rich_text', [
                'heading' => 'Ordinary text',
                'events_heading' => 'Not valid here',
                'selected_event_ids' => [1],
            ], 'en');
            $this->fail('Events and news settings must be limited to the dedicated section type.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('content.events_heading', $exception->errors());
            $this->assertArrayHasKey('content.selected_event_ids', $exception->errors());
        }
    }

    public function test_upcoming_events_and_featured_news_are_resolved_from_truthful_managed_records(): void
    {
        $later = $this->publication([
            'title' => 'Later community workshop',
            'slug' => 'later-community-workshop',
            'content_kind' => 'event',
            'event_start_at' => now()->addDays(6),
            'event_end_at' => now()->addDays(6)->addHours(2),
            'event_status' => 'scheduled',
            'event_attendance_mode' => 'offline',
            'location' => 'Dhaka',
            'order_by' => 90,
        ]);
        $earlier = $this->publication([
            'title' => 'First community workshop',
            'slug' => 'first-community-workshop',
            'content_kind' => 'event',
            'event_start_at' => now()->addDays(2),
            'event_status' => 'moved-online',
            'event_attendance_mode' => 'online',
            'location' => null,
            'image_alt' => 'Young volunteers attending an online workshop',
            'order_by' => 1,
        ]);
        $this->publication([
            'title' => 'Past event',
            'slug' => 'past-event',
            'content_kind' => 'event',
            'event_start_at' => now()->subDay(),
            'event_status' => 'scheduled',
        ]);
        $this->publication([
            'title' => 'Cancelled event',
            'slug' => 'cancelled-event',
            'content_kind' => 'event',
            'event_start_at' => now()->addDay(),
            'event_status' => 'cancelled',
        ]);
        $this->publication([
            'title' => 'Unpublished future event',
            'slug' => 'unpublished-future-event',
            'content_kind' => 'event',
            'event_start_at' => now()->addDay(),
            'event_status' => 'scheduled',
            'status' => 0,
        ]);
        $this->publication([
            'title' => 'Bangla future event',
            'slug' => 'bangla-future-event',
            'language' => 'bn',
            'content_kind' => 'event',
            'event_start_at' => now()->addDay(),
            'event_status' => 'scheduled',
        ]);
        $featured = $this->publication([
            'title' => 'Featured field news',
            'slug' => 'featured-field-news',
            'content_kind' => 'article',
            'image_path' => 'featured.jpg',
            'image_alt' => 'Children and volunteers at an Ignite learning centre',
            'order_by' => 50,
            'published_at' => now()->subDays(8),
        ]);
        $this->publication([
            'title' => 'Newer but lower-priority news',
            'slug' => 'newer-lower-priority-news',
            'content_kind' => 'article',
            'order_by' => 5,
            'published_at' => now()->subDay(),
        ]);

        $resolved = app(PageBlockContentResolver::class)->resolve(new PageBlock([
            'type' => 'events_news',
            'content' => config('page-builder.default_content.events_news'),
        ]));

        $this->assertSame(
            [$earlier->id, $later->id],
            collect($resolved['upcoming_events'])->pluck('id')->all()
        );
        $this->assertSame(
            $earlier->event_start_at?->toIso8601String(),
            $resolved['upcoming_events'][0]['event_start_at']
        );
        $this->assertSame('moved-online', $resolved['upcoming_events'][0]['event_status']);
        $this->assertSame('online', $resolved['upcoming_events'][0]['event_attendance_mode']);
        $this->assertSame(
            'Young volunteers attending an online workshop',
            $resolved['upcoming_events'][0]['image_alt']
        );
        $this->assertSame($featured->id, $resolved['featured_news']['id']);
        $this->assertSame('article', $resolved['featured_news']['content_kind']);
        $this->assertSame('/storage/photos/1/notice_board/featured.jpg', $resolved['featured_news']['image']);
        $this->assertNull($resolved['featured_news']['event_start_at']);
    }

    public function test_manual_event_order_and_explicit_featured_news_never_fall_back_to_unrelated_content(): void
    {
        $first = $this->publication([
            'title' => 'First eligible event',
            'slug' => 'first-eligible-event',
            'content_kind' => 'event',
            'event_start_at' => now()->addDay(),
            'event_status' => 'scheduled',
        ]);
        $second = $this->publication([
            'title' => 'Second eligible event',
            'slug' => 'second-eligible-event',
            'content_kind' => 'event',
            'event_start_at' => now()->addDays(2),
            'event_status' => 'scheduled',
        ]);
        $cancelled = $this->publication([
            'title' => 'Ineligible cancelled event',
            'slug' => 'ineligible-cancelled-event',
            'content_kind' => 'event',
            'event_start_at' => now()->addDays(3),
            'event_status' => 'cancelled',
        ]);
        $chosenNews = $this->publication([
            'title' => 'Chosen news',
            'slug' => 'chosen-news',
            'content_kind' => 'article',
            'order_by' => 1,
        ]);
        $automaticNews = $this->publication([
            'title' => 'Automatic high-priority news',
            'slug' => 'automatic-high-priority-news',
            'content_kind' => 'article',
            'order_by' => 999,
        ]);

        $content = array_replace(config('page-builder.default_content.events_news'), [
            'events_selection_mode' => 'manual',
            'selected_event_ids' => [$second->id, $cancelled->id, $first->id],
            'event_limit' => 2,
            'featured_news_id' => $chosenNews->id,
        ]);
        $resolved = app(PageBlockContentResolver::class)->resolve(new PageBlock([
            'type' => 'events_news',
            'content' => $content,
        ]));

        $this->assertSame(
            [$second->id, $first->id],
            collect($resolved['upcoming_events'])->pluck('id')->all()
        );
        $this->assertSame($chosenNews->id, $resolved['featured_news']['id']);
        $this->assertNotSame($automaticNews->id, $resolved['featured_news']['id']);

        $content['featured_news_id'] = $first->id;
        $missing = app(PageBlockContentResolver::class)->resolve(new PageBlock([
            'type' => 'events_news',
            'content' => $content,
        ]));
        $this->assertNull($missing['featured_news']);
    }

    public function test_manual_references_follow_notice_translations_in_the_active_locale(): void
    {
        $firstEventKey = (string) Str::uuid();
        $secondEventKey = (string) Str::uuid();
        $newsKey = (string) Str::uuid();
        $firstEnglish = $this->publication([
            'translation_key' => $firstEventKey,
            'title' => 'First English event',
            'slug' => 'first-english-event',
            'content_kind' => 'event',
            'event_start_at' => now()->addDays(2),
            'event_status' => 'scheduled',
        ]);
        $firstBangla = $this->publication([
            'translation_key' => $firstEventKey,
            'title' => 'প্রথম বাংলা আয়োজন',
            'slug' => 'first-bangla-event',
            'language' => 'bn',
            'content_kind' => 'event',
            'event_start_at' => now()->addDays(2),
            'event_status' => 'scheduled',
        ]);
        $secondEnglish = $this->publication([
            'translation_key' => $secondEventKey,
            'title' => 'Second English event',
            'slug' => 'second-english-event',
            'content_kind' => 'event',
            'event_start_at' => now()->addDays(4),
            'event_status' => 'scheduled',
        ]);
        $secondBangla = $this->publication([
            'translation_key' => $secondEventKey,
            'title' => 'দ্বিতীয় বাংলা আয়োজন',
            'slug' => 'second-bangla-event',
            'language' => 'bn',
            'content_kind' => 'event',
            'event_start_at' => now()->addDays(4),
            'event_status' => 'scheduled',
        ]);
        $englishNews = $this->publication([
            'translation_key' => $newsKey,
            'title' => 'English featured news',
            'slug' => 'english-featured-news',
            'content_kind' => 'article',
        ]);
        $banglaNews = $this->publication([
            'translation_key' => $newsKey,
            'title' => 'বাংলা নির্বাচিত সংবাদ',
            'slug' => 'bangla-featured-news',
            'language' => 'bn',
            'content_kind' => 'article',
        ]);
        app()->setLocale('bn');

        $content = array_replace(config('page-builder.default_content.events_news'), [
            'events_selection_mode' => 'manual',
            'selected_event_ids' => [$secondEventKey, $firstEventKey],
            'event_limit' => 2,
            'featured_news_id' => $newsKey,
        ]);
        $resolved = app(PageBlockContentResolver::class)->resolve(new PageBlock([
            'type' => 'events_news',
            'content' => $content,
        ]));

        $this->assertSame(
            [$secondBangla->id, $firstBangla->id],
            collect($resolved['upcoming_events'])->pluck('id')->all()
        );
        $this->assertSame($banglaNews->id, $resolved['featured_news']['id']);

        // Existing blocks may still contain source-locale numeric IDs. Resolve
        // those through the same translation identity instead of dropping them.
        $content['selected_event_ids'] = [$secondEnglish->id, $firstEnglish->id];
        $content['featured_news_id'] = $englishNews->id;
        $legacyResolved = app(PageBlockContentResolver::class)->resolve(new PageBlock([
            'type' => 'events_news',
            'content' => $content,
        ]));

        $this->assertSame(
            [$secondBangla->id, $firstBangla->id],
            collect($legacyResolved['upcoming_events'])->pluck('id')->all()
        );
        $this->assertSame($banglaNews->id, $legacyResolved['featured_news']['id']);
    }

    public function test_builder_options_separate_eligible_events_from_published_news_with_metadata(): void
    {
        $event = $this->publication([
            'title' => 'Selectable event',
            'slug' => 'selectable-event',
            'content_kind' => 'event',
            'event_start_at' => now()->addDays(4),
            'event_status' => 'rescheduled',
            'event_attendance_mode' => 'mixed',
            'location' => 'Community Hall',
        ]);
        $news = $this->publication([
            'title' => 'Selectable news',
            'slug' => 'selectable-news',
            'content_kind' => 'article',
        ]);
        $expired = $this->publication([
            'title' => 'Expired event option',
            'slug' => 'expired-event-option',
            'content_kind' => 'event',
            'event_start_at' => now()->subHour(),
            'event_status' => 'scheduled',
        ]);
        $owner = $this->owner();
        $this->actingAs($owner, 'admin');

        $options = app(PageBuilderController::class)->reusableBlockEditorOptions('en');

        $this->assertSame([(string) $event->translation_key], collect($options['event_options'])->pluck('value')->all());
        $this->assertSame([(string) $news->translation_key], collect($options['news_options'])->pluck('value')->all());
        $this->assertSame($event->id, $options['event_options'][0]['id']);
        $this->assertSame($news->id, $options['news_options'][0]['id']);
        $this->assertSame($event->translation_key, $options['event_options'][0]['translation_key']);
        $this->assertSame($news->translation_key, $options['news_options'][0]['translation_key']);
        $this->assertSame('event', $options['event_options'][0]['kind']);
        $this->assertSame('rescheduled', $options['event_options'][0]['event_status']);
        $this->assertSame('mixed', $options['event_options'][0]['event_attendance_mode']);
        $this->assertSame('Community Hall', $options['event_options'][0]['location']);
        $this->assertSame('article', $options['news_options'][0]['kind']);
        $this->assertSame('Manage events and news', $options['manage_urls']['events_news']['label']);
        $this->assertSame('Add event or news', $options['manage_urls']['events_news']['add_label']);
        $this->assertSame(route('notice.board.create'), $options['manage_urls']['events_news']['add_url']);
        $this->assertSame(route('notice.board.create'), $options['manage_urls']['events']['add_url']);
        $this->assertSame(
            [(string) $event->translation_key, (string) $news->translation_key],
            collect($options['items']['events_news'])->pluck('value')->all()
        );
        $this->assertSame(
            [(string) $expired->id, (string) $event->id, (string) $news->id],
            collect($options['items']['events'])->pluck('value')->all()
        );

        $publicationMenu = AuthMenu::query()->where('link', 'notice.board.index')->firstOrFail();
        $createPublication = MenuAction::query()->where('link', 'notice.board.create')->firstOrFail();
        $role = Role::create([
            'name' => 'Events and news viewer',
            'security_rank' => 10,
            'permission' => (string) $publicationMenu->id,
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $viewer = Admin::create([
            'name' => 'Events and news viewer',
            'username' => 'events-news-viewer',
            'email' => 'events-news-viewer@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
        $this->actingAs($viewer, 'admin');

        $viewOnlyOptions = app(PageBuilderController::class)->reusableBlockEditorOptions('en');
        $this->assertArrayHasKey('events_news', $viewOnlyOptions['manage_urls']);
        $this->assertArrayNotHasKey('add_url', $viewOnlyOptions['manage_urls']['events_news']);
        $this->assertArrayNotHasKey('add_url', $viewOnlyOptions['manage_urls']['events']);

        $role->update(['actionPermission' => (string) $createPublication->id]);
        $createOptions = app(PageBuilderController::class)->reusableBlockEditorOptions('en');
        $this->assertSame(route('notice.board.create'), $createOptions['manage_urls']['events_news']['add_url']);
        $this->assertSame(route('notice.board.create'), $createOptions['manage_urls']['events']['add_url']);
    }

    private function publication(array $overrides): NoticeBoard
    {
        return NoticeBoard::create(array_replace([
            'title' => 'Managed publication',
            'slug' => 'managed-publication-' . uniqid(),
            'sub_title' => 'A concise managed summary.',
            'description' => '<p>Managed visitor-facing content.</p>',
            'content_kind' => 'article',
            'language' => 'en',
            'published_at' => now()->subDay(),
            'order_by' => 0,
            'status' => 1,
        ], $overrides));
    }

    private function owner(): Admin
    {
        $role = Role::create([
            'name' => 'Events and news owner',
            'security_rank' => 100,
            'is_owner' => true,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Events and news owner',
            'username' => 'events-news-owner',
            'email' => 'events-news-owner@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
