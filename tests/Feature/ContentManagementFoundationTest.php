<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\ReusableBlock;
use App\Services\MediaUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContentManagementFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reusable_section_content_resolves_across_every_page_instance(): void
    {
        $library = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Global appeal',
            'type' => 'cta',
            'locale' => '*',
            'content' => ['heading' => 'First appeal'],
            'settings' => ['tone' => 'warm'],
            'is_enabled' => true,
        ]);
        $first = $this->makePage('first-page');
        $second = $this->makePage('second-page');

        $firstBlock = $this->makeBlock($first, $library);
        $secondBlock = $this->makeBlock($second, $library);
        $library->update(['content' => ['heading' => 'Updated everywhere']]);

        $this->assertSame('Updated everywhere', $firstBlock->fresh('reusableBlock')->resolvedContent()['heading']);
        $this->assertSame('Updated everywhere', $secondBlock->fresh('reusableBlock')->resolvedContent()['heading']);
        $this->assertSame(['tone' => 'warm'], $firstBlock->fresh('reusableBlock')->resolvedSettings());
    }

    public function test_publication_scope_enforces_draft_private_and_schedule_boundaries(): void
    {
        $published = $this->makePage('published', [
            'status' => 1,
            'publication_status' => 'published',
        ]);
        $this->makePage('draft', ['status' => 0, 'publication_status' => 'draft']);
        $this->makePage('private', [
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'private',
        ]);
        $scheduled = $this->makePage('scheduled-now', [
            'status' => 1,
            'publication_status' => 'scheduled',
            'scheduled_for' => now()->subMinute(),
        ]);
        $this->makePage('scheduled-later', [
            'status' => 1,
            'publication_status' => 'scheduled',
            'scheduled_for' => now()->addHour(),
        ]);

        $this->assertEqualsCanonicalizing(
            [$published->id, $scheduled->id],
            Page::publiclyAvailable()->pluck('id')->all()
        );
    }

    public function test_media_usage_reports_draft_and_reusable_content_references(): void
    {
        $asset = MediaAsset::create([
            'uuid' => (string) Str::uuid(),
            'path' => 'media/2026/08/appeal.webp',
            'original_name' => 'appeal.webp',
            'mime_type' => 'image/webp',
            'extension' => 'webp',
            'bytes' => 1200,
            'alt_text' => 'Community appeal',
        ]);
        $page = $this->makePage('draft-media', ['status' => 0, 'publication_status' => 'draft']);
        PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'type' => 'hero',
            'label' => 'Draft hero',
            'content' => ['image' => $asset->url],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
        ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared hero',
            'type' => 'hero',
            'content' => ['image' => $asset->url],
            'settings' => [],
            'is_enabled' => true,
        ]);

        $references = app(MediaUsageService::class)->references($asset);

        $this->assertSame(1, $references['page_blocks']);
        $this->assertSame(1, $references['reusable_blocks']);
        $this->assertTrue(app(MediaUsageService::class)->inUse($asset));
    }

    public function test_search_view_excludes_drafts_private_unlisted_and_future_pages(): void
    {
        $visible = $this->makePage('visible-search-page');
        $this->makePage('draft-search-page', ['status' => 0, 'publication_status' => 'draft']);
        $this->makePage('private-search-page', ['visibility' => 'private']);
        $this->makePage('unlisted-search-page', ['visibility' => 'unlisted']);
        $this->makePage('future-search-page', [
            'publication_status' => 'scheduled',
            'scheduled_for' => now()->addHour(),
        ]);

        $this->assertSame(
            [$visible->id],
            \DB::table('view_search_data')->orderBy('id')->pluck('id')->all()
        );
    }

    private function makePage(string $slug, array $overrides = []): Page
    {
        return Page::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'sub_title' => 'Managed content',
            'slug' => $slug,
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
        ], $overrides));
    }

    private function makeBlock(Page $page, ReusableBlock $library): PageBlock
    {
        return PageBlock::create([
            'page_id' => $page->id,
            'reusable_block_id' => $library->id,
            'uuid' => (string) Str::uuid(),
            'type' => $library->type,
            'label' => $library->name,
            'content' => ['heading' => 'Local fallback'],
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
    }
}
