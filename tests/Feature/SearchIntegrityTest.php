<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\AnnualReport;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\NoticeBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SearchIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_migration_search_view_returns_published_page_contract(): void
    {
        Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Clean Water Initiative',
            'sub_title' => 'Safe water for rural families',
            'description' => '<p>A community water program.</p>',
            'slug' => 'clean-water-initiative',
            'status' => 1,
            'language' => 'en',
        ]);

        $this->withHeaders($this->inertiaHeaders())->get('/search?search=water')
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'search')
            ->assertJsonPath('props.properties.total_count', 1)
            ->assertJsonPath('props.data.pages.0.name', 'Clean Water Initiative')
            ->assertJsonPath('props.data.pages.0.view_type', 'page')
            ->assertJsonPath('props.data.pages.0.slug', 'clean-water-initiative');
    }

    public function test_trashed_page_is_removed_from_search_results(): void
    {
        $page = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Temporary Campaign',
            'sub_title' => 'Remove this campaign',
            'slug' => 'temporary-campaign',
            'status' => 1,
            'language' => 'en',
        ]);
        $page->delete();

        $this->withHeaders($this->inertiaHeaders())->get('/search?search=Temporary')
            ->assertOk()
            ->assertJsonPath('props.properties.total_count', 0);
    }

    public function test_search_returns_programs_events_reports_and_gallery_content_with_public_urls(): void
    {
        Category::create(['uuid' => (string) Str::uuid(), 'name' => 'Vision Program', 'slug' => 'vision-program', 'description' => 'Vision work', 'language' => 'en', 'status' => 1]);
        NoticeBoard::create(['title' => 'Vision Gathering', 'slug' => 'vision-gathering', 'description' => 'Vision story', 'notice_type' => 'notice-board', 'language' => 'en', 'order_by' => 40, 'status' => 1]);
        AnnualReport::create(['title' => 'Vision Annual Report', 'slug' => 'vision-report', 'description' => 'Vision reporting', 'notice_type' => 'annual-report', 'language' => 'en', 'order_by' => 30, 'status' => 1]);
        Gallery::create(['uuid' => (string) Str::uuid(), 'name' => 'Vision in Pictures', 'type' => 'gallery', 'description' => 'Vision photo', 'language' => 'en', 'order_by' => 20, 'status' => 1]);

        $this->withHeaders($this->inertiaHeaders())->get('/search?search=Vision')
            ->assertOk()
            ->assertJsonPath('props.properties.total_count', 4)
            ->assertJsonPath('props.data.pages.0.view_type', 'event')
            ->assertJsonPath('props.data.pages.0.result_url', '/event/vision-gathering')
            ->assertJsonPath('props.data.pages.1.view_type', 'report')
            ->assertJsonPath('props.data.pages.1.result_url', '/annual-report/vision-report')
            ->assertJsonPath('props.data.pages.2.view_type', 'gallery')
            ->assertJsonPath('props.data.pages.2.result_url', '/gallery')
            ->assertJsonPath('props.data.pages.3.view_type', 'program')
            ->assertJsonPath('props.data.pages.3.result_url', '/category/vision-program');
    }

    private function inertiaHeaders(): array
    {
        $manifest = public_path('build/manifest.json');

        return array_filter([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : null,
        ]);
    }
}
