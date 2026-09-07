<?php

namespace Tests\Feature;

use App\Models\NoticeBoard;
use App\Models\TranslationLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FixedEventNewsArchiveRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_and_news_are_independent_fixed_archives(): void
    {
        $event = $this->publication([
            'title' => 'Community gathering',
            'slug' => 'fixed-community-gathering',
            'content_kind' => 'event',
            'order_by' => 30,
        ]);
        $article = $this->publication([
            'title' => 'Education field update',
            'slug' => 'fixed-education-field-update',
            'content_kind' => 'article',
            'order_by' => 20,
        ]);

        $this->get('/events')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('events')
                ->where('archive_kind', 'event')
                ->where('archive_route', 'frontend.events')
                ->where('title', 'Events')
                ->where('properties.total_count', 1)
                ->where('data.items.0.id', $event->id)
                ->missing('data.items.1')
                ->where('contentSeo.canonical_url', url('/events'))
            );

        $this->get('/news')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('events')
                ->where('archive_kind', 'article')
                ->where('archive_route', 'frontend.news')
                ->where('title', 'Latest news')
                ->where('properties.total_count', 1)
                ->where('data.items.0.id', $article->id)
                ->missing('data.items.1')
                ->where('contentSeo.canonical_url', url('/news'))
            );
    }

    public function test_legacy_kind_urls_redirect_permanently_and_keep_only_valid_locale_and_page(): void
    {
        $this->enableEnglishAndBangla();

        $this->get('/events?kind=event&lang=bn&page=3&preview=1')
            ->assertStatus(301)
            ->assertRedirect('/events?lang=bn&page=3');

        $this->get('/events?kind=article&lang=bn&page=2&preview=1')
            ->assertStatus(301)
            ->assertRedirect('/news?lang=bn&page=2');

        $this->get('/events?kind=article&lang=zz&page=0')
            ->assertStatus(301)
            ->assertRedirect('/news');
    }

    public function test_detail_pages_point_back_to_the_archive_matching_the_content_kind(): void
    {
        $event = $this->publication([
            'title' => 'Community day',
            'slug' => 'fixed-community-day-detail',
            'content_kind' => 'event',
        ]);
        $article = $this->publication([
            'title' => 'Field report',
            'slug' => 'fixed-field-report-detail',
            'content_kind' => 'article',
        ]);

        $this->get('/event/' . $event->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('archive_route', 'frontend.events')
                ->where('archive_url', url('/events'))
                ->where('archive_presentation.title', 'Events')
            );

        $this->get('/event/' . $article->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('archive_route', 'frontend.news')
                ->where('archive_url', url('/news'))
                ->where('archive_presentation.title', 'Latest news')
            );
    }

    private function enableEnglishAndBangla(): void
    {
        foreach ([
            ['locale' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true],
            ['locale' => 'bn', 'name' => 'Bangla', 'native_name' => 'বাংলা', 'is_default' => false],
        ] as $locale) {
            TranslationLocale::query()->updateOrCreate(['locale' => $locale['locale']], $locale + [
                'is_enabled' => true,
                'enabled_at' => now(),
            ]);
        }
    }

    private function publication(array $overrides): NoticeBoard
    {
        return NoticeBoard::query()->create(array_merge([
            'translation_key' => (string) Str::uuid(),
            'title' => 'Public update',
            'sub_title' => 'A public update.',
            'slug' => 'fixed-public-update-' . Str::lower(Str::random(8)),
            'description' => '<p>Public body.</p>',
            'notice_type' => 'notice-board',
            'content_kind' => 'article',
            'language' => 'en',
            'published_at' => now()->subDay(),
            'order_by' => 0,
            'status' => 1,
        ], $overrides));
    }
}
