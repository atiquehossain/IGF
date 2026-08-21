<?php

namespace Tests\Feature;

use App\Models\AnnualReport;
use App\Models\NoticeBoard;
use App\Models\PageBlock;
use App\Services\PageBlockContentResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicRegionalDateContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_and_report_pages_expose_raw_iso_dates_for_frontend_formatting(): void
    {
        $event = $this->event();
        AnnualReport::create([
            'title' => 'Impact report',
            'slug' => 'impact-report',
            'image_path' => 'impact-report.pdf',
            'language' => 'en',
            'status' => 1,
            'order_by' => 10,
            'published_at' => '2026-08-19 00:00:00',
        ]);

        $this->get(route('frontend.events'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.items.0.published_at', '2026-08-19'));

        $this->get(route('frontend.event', ['slug' => $event->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.event.published_at', '2026-08-19'));

        $this->get(route('frontend.annual_report.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.items.0.published_at', '2026-08-19'));
    }

    public function test_managed_event_blocks_expose_a_raw_iso_date_instead_of_english_date_fragments(): void
    {
        $this->event();
        $block = new PageBlock([
            'type' => 'events',
            'content' => [
                'content_source' => 'events',
                'selection_mode' => 'automatic',
                'limit' => 1,
            ],
        ]);

        $item = app(PageBlockContentResolver::class)->resolve($block)['items'][0];

        $this->assertSame('2026-08-19', $item['published_at']);
        $this->assertArrayNotHasKey('month', $item);
        $this->assertArrayNotHasKey('day', $item);
    }

    private function event(): NoticeBoard
    {
        return NoticeBoard::create([
            'title' => 'Community day',
            'slug' => 'community-day',
            'description' => 'A public event.',
            'language' => 'en',
            'status' => 1,
            'order_by' => 10,
            'published_at' => '2026-08-19 00:00:00',
        ]);
    }
}
