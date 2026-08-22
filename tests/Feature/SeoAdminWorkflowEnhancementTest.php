<?php

namespace Tests\Feature;

use App\Http\Middleware\Permission;
use App\Models\Admin;
use App\Models\AnnualReport;
use App\Models\AuthMenu;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\Role;
use App\Models\SeoMetadata;
use App\Services\SeoHealthService;
use App\Services\SeoMetadataEditorVersionService;
use App\Services\SeoMetadataRevisionService;
use App\Services\SeoMetadataService;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoAdminWorkflowEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionRegistrySeeder::class);
    }

    public function test_ready_means_there_are_no_required_or_recommended_actions(): void
    {
        $service = app(SeoHealthService::class);
        $recommended = $service->evaluate([
            'title' => 'Community education in Bangladesh',
            'description' => str_repeat('Clear information for families and supporters. ', 3),
            'image' => '',
            'indexable' => true,
        ]);

        $this->assertSame('Needs attention', $recommended['status']);
        $this->assertSame(0, $recommended['required_count']);
        $this->assertSame(1, $recommended['recommended_count']);
        $this->assertSame('recommended', $recommended['issues'][0]['level']);

        $ready = $service->evaluate([
            'title' => 'Community education in Bangladesh',
            'description' => str_repeat('Clear information for families and supporters. ', 3),
            'image' => 'https://example.test/share.jpg',
            'indexable' => true,
        ]);
        $this->assertSame('Ready', $ready['status']);
        $this->assertSame([], $ready['issues']);

        $titleWarning = $service->evaluate([
            'title' => str_repeat('Long search title ', 5),
            'description' => str_repeat('Clear information for families and supporters. ', 3),
            'image' => 'https://example.test/share.jpg',
            'indexable' => true,
        ]);
        $this->assertSame('Needs attention', $titleWarning['status']);
        $this->assertLessThan(100, $titleWarning['score'], 'A 100% score must not be shown beside an open SEO action.');
    }

    public function test_dashboard_defaults_to_actionable_paginated_rows_with_progressive_tools(): void
    {
        $admin = $this->adminWith(actions: ['seo.metadata.edit']);
        foreach (range(1, 14) as $number) {
            $this->page('seo-checklist-page-' . $number);
        }

        $response = $this->actingAs($admin, 'admin')->get(route('seo.index'));
        $response->assertOk()
            ->assertSee('Advanced SEO tools')
            ->assertSee('Needs attention', false)
            ->assertDontSee('>Improve<', false);

        $this->assertSame('needs_attention', data_get($response->viewData('dashboardFilters'), 'issue'));
        $visible = collect($response->viewData('dashboardVisibleTargets'));
        $pagination = $response->viewData('dashboardPagination');
        $counts = $response->viewData('dashboardCounts');
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $pagination);
        $this->assertLessThanOrEqual(12, $visible->count());
        $this->assertGreaterThan(12, $pagination->total());
        $this->assertTrue($visible->every(fn (array $target): bool => $target['status'] === 'Needs attention'));
        $this->assertSame($counts['indexable_live'], $counts['ready'] + $counts['attention']);
        $this->assertMatchesRegularExpression('/Fix \d+ SEO issues?/', $response->getContent());

        $secondPage = $this->get(route('seo.index', ['issue' => 'all', 'seo_page' => 2]))->assertOk();
        $this->assertSame('all', data_get($secondPage->viewData('dashboardFilters'), 'issue'));
        $this->assertSame(2, $secondPage->viewData('dashboardPagination')->currentPage());
    }

    public function test_auto_metadata_mode_shows_the_inherited_values_without_saving_an_override(): void
    {
        $admin = $this->adminWith(actions: ['seo.metadata.edit']);
        $page = $this->page('inherited-search-preview');

        $response = $this->actingAs($admin, 'admin')
            ->get(route('seo.content.edit', ['page', $page->id]))
            ->assertOk()
            ->assertSee('Use the current page title and summary automatically')
            ->assertSee('This inherited value is read-only and is not saved as a custom override.')
            ->assertSee('data-inherited-value="' . e($page->name) . '"', false);

        $this->assertTrue((bool) data_get($response->viewData('editor'), 'auto_content'));
        $this->assertSame('', data_get($response->viewData('editor'), 'values.title'));

        $payload = $this->editorPayload();
        $payload['seo_auto'] = 1;
        $payload['seo']['title'] = $page->name;
        $payload['seo']['description'] = $page->sub_title;
        $this->put(route('seo.content.update', ['page', $page->id]), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $metadata = SeoMetadata::query()
            ->where('seoable_type', Page::class)
            ->where('seoable_id', $page->id)
            ->where('locale', 'en')
            ->firstOrFail();
        $this->assertSame('', trim((string) $metadata->title));
        $this->assertSame('', trim((string) $metadata->description));
    }

    public function test_view_only_role_can_inspect_but_cannot_edit_restore_or_review(): void
    {
        $admin = $this->adminWith(actions: ['seo.metadata.view']);
        $page = $this->page('read-only-seo');
        $metadata = app(SeoMetadataService::class)->updateForModel($page, [
            'title' => 'Read-only search title',
            'description' => $this->description(),
        ], 'en');
        $revision = app(SeoMetadataRevisionService::class)->capture($metadata);

        $this->actingAs($admin, 'admin')->get(route('seo.index'))
            ->assertOk()->assertSee('Read-only SEO access');
        $this->get(route('seo.content.edit', ['page', $page->id]))
            ->assertOk()->assertSee('Read-only SEO access')->assertDontSee('Save search &amp; sharing');
        $this->put(route('seo.content.update', ['page', $page->id]), [])->assertForbidden();
        $this->post(route('seo.revisions.restore', $revision))->assertForbidden();
        $this->post(route('seo.review.resolve'), [])->assertForbidden();
    }

    public function test_media_library_navigation_requires_view_permission_independently_from_upload_permission(): void
    {
        $page = $this->page('permission-safe-media-navigation');
        $asset = MediaAsset::create([
            'uuid' => (string) Str::uuid(), 'disk' => 'public', 'path' => 'media/permission-safe.jpg',
            'original_name' => 'permission-safe.jpg', 'mime_type' => 'image/jpeg', 'extension' => 'jpg',
            'bytes' => 1000, 'width' => 1200, 'height' => 630, 'alt_text' => 'Permission safe image', 'locale' => '*',
        ]);
        $mediaLibraryUrl = route('media.index', ['type' => 'image']);

        // Upload permission alone must not expose navigation to media.index,
        // which this role cannot open.
        $uploader = $this->adminWith(actions: ['seo.metadata.edit', 'media.create']);
        $editor = $this->actingAs($uploader, 'admin')
            ->get(route('seo.content.edit', ['page', $page->id]))
            ->assertOk()
            ->assertViewHas('canUploadMedia', true)
            ->assertViewHas('canViewMedia', false)
            ->assertDontSee($mediaLibraryUrl, false);
        $this->assertStringContainsString(route('seo.media.index'), $editor->getContent());
        $this->get(route('seo.bulk.index'))
            ->assertOk()
            ->assertViewHas('canViewMedia', false)
            ->assertDontSee($mediaLibraryUrl, false);
        $this->getJson(route('seo.media.index', ['search' => $asset->original_name]))
            ->assertOk()
            ->assertJsonPath('data.0.edit_url', null);

        // A viewer can follow library links even without upload permission.
        $viewer = $this->adminWith(menus: ['media.index'], actions: ['seo.metadata.edit']);
        $this->actingAs($viewer, 'admin')
            ->get(route('seo.content.edit', ['page', $page->id]))
            ->assertOk()
            ->assertViewHas('canUploadMedia', false)
            ->assertViewHas('canViewMedia', true)
            ->assertSee($mediaLibraryUrl, false)
            ->assertSee('Open Media Library')
            ->assertDontSee('Upload or manage images');
        $this->get(route('seo.bulk.index'))
            ->assertOk()
            ->assertViewHas('canViewMedia', true)
            ->assertSee($mediaLibraryUrl, false);
        $this->getJson(route('seo.media.index', ['search' => $asset->original_name]))
            ->assertOk()
            ->assertJsonPath(
                'data.0.edit_url',
                route('media.index', ['type' => 'image', 'search' => $asset->original_name])
            );
    }

    public function test_bulk_editor_updates_multiple_languages_and_exports_safe_csv(): void
    {
        $admin = $this->adminWith(actions: ['seo.metadata.edit']);
        $uuid = (string) Str::uuid();
        $english = $this->page('bulk-english', 'en', $uuid);
        $bangla = $this->page('bulk-bangla', 'bn', $uuid);

        $this->actingAs($admin, 'admin')->get(route('seo.bulk.index', ['search' => 'bulk-']))
            ->assertOk()->assertSee('Bulk metadata editor')->assertSee('bulk-english')->assertSee('bulk-bangla');

        $this->put(route('seo.bulk.update'), ['items' => [
            [
                'owner_type' => 'page', 'owner_id' => $english->id, 'route_name' => null, 'locale' => 'en', 'expected_editor_version' => 0,
                'mode' => 'custom', 'title' => 'English bulk title', 'description' => $this->description(),
                'image' => 'https://example.test/en-share.jpg', 'indexable' => 1, 'schema_template' => 'webpage',
            ],
            [
                'owner_type' => 'page', 'owner_id' => $bangla->id, 'route_name' => null, 'locale' => 'bn', 'expected_editor_version' => 0,
                'mode' => 'custom', 'title' => 'বাংলা সার্চ শিরোনাম', 'description' => 'ইগনাইটের কার্যক্রম, শিক্ষা সহায়তা এবং বাংলাদেশের কমিউনিটির দীর্ঘমেয়াদি পরিবর্তনের বিস্তারিত তথ্য।',
                'image' => 'https://example.test/bn-share.jpg', 'indexable' => 0, 'schema_template' => 'webpage',
            ],
        ]])->assertRedirect()->assertSessionHas('alert-type', 'success');

        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => Page::class, 'seoable_id' => $english->id, 'locale' => 'en', 'title' => 'English bulk title', 'robots_index' => 1,
        ]);
        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => Page::class, 'seoable_id' => $bangla->id, 'locale' => 'bn', 'title' => 'বাংলা সার্চ শিরোনাম', 'robots_index' => 0, 'exclude_from_sitemap' => 1,
        ]);

        $this->get(route('seo.bulk.export', ['search' => 'bulk-english']))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_review_request_requires_required_items_and_reviewer_signoff_is_separate(): void
    {
        $editor = $this->adminWith(actions: ['seo.metadata.edit']);
        $reviewer = $this->adminWith(actions: ['seo.metadata.view', 'seo.metadata.review']);
        $page = $this->page('review-workflow');
        $page->forceFill(['sub_title' => '', 'description' => '', 'meta_description' => ''])->save();
        $metadata = app(SeoMetadataService::class)->updateForModel($page, ['title' => 'Review title'], 'en');
        $identity = ['owner_type' => 'page', 'owner_id' => $page->id, 'route_name' => null, 'locale' => 'en'];

        $this->actingAs($editor, 'admin')->from(route('seo.content.edit', ['page', $page->id]))
            ->post(route('seo.review.request'), $identity)
            ->assertSessionHasErrors('review');

        $metadata->forceFill(['description' => $this->description()])->save();
        $this->post(route('seo.review.request'), $identity)
            ->assertRedirect()->assertSessionHas('alert-type', 'success');
        $this->assertSame('pending', $metadata->fresh()->review_status);
        $requestedHash = $metadata->fresh()->review_content_hash;
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $requestedHash);

        $requested = $metadata->fresh();
        $this->actingAs($reviewer, 'admin')->post(route('seo.review.resolve'), $identity + [
            'decision' => 'approve',
            'expected_review_hash' => $requested->review_content_hash,
            'expected_review_version' => $requested->review_request_version,
        ])
            ->assertRedirect()->assertSessionHas('alert-type', 'success');
        $this->assertSame('approved', $metadata->fresh()->review_status);
        $this->assertSame($requestedHash, $metadata->fresh()->review_content_hash);
        $this->assertFalse(app(Permission::class)->allows($reviewer, 'seo.update'));

        $this->actingAs($editor, 'admin')->put(route('seo.content.update', ['page', $page->id]), $this->editorPayload())
            ->assertRedirect();
        $this->assertSame('draft', $metadata->fresh()->review_status);
    }

    public function test_reviewer_cannot_approve_metadata_that_changed_after_the_request(): void
    {
        $editor = $this->adminWith(actions: ['seo.metadata.edit']);
        $reviewer = $this->adminWith(actions: ['seo.metadata.review']);
        $page = $this->page('stale-review');
        $metadata = app(SeoMetadataService::class)->updateForModel($page, [
            'title' => 'Requested title', 'description' => $this->description(),
        ], 'en');
        $identity = ['owner_type' => 'page', 'owner_id' => $page->id, 'route_name' => null, 'locale' => 'en'];
        $this->actingAs($editor, 'admin')->post(route('seo.review.request'), $identity)->assertRedirect();

        // Simulate a legacy/background writer that does not call the normal
        // controller reset hook while the review is awaiting a decision.
        $metadata->forceFill(['title' => 'Changed after request'])->save();

        $requested = $metadata->fresh();
        $this->actingAs($reviewer, 'admin')->from(route('seo.content.edit', ['page', $page->id]))
            ->post(route('seo.review.resolve'), $identity + [
                'decision' => 'approve',
                'expected_review_hash' => $requested->review_content_hash,
                'expected_review_version' => $requested->review_request_version,
            ])
            ->assertSessionHasErrors('review');
        $metadata->refresh();
        $this->assertSame('draft', $metadata->review_status);
        $this->assertNull($metadata->reviewed_by);
        $this->assertNull($metadata->review_content_hash);
    }

    public function test_stale_reviewer_form_cannot_resolve_a_newer_review_request(): void
    {
        $editor = $this->adminWith(actions: ['seo.metadata.edit']);
        $reviewer = $this->adminWith(actions: ['seo.metadata.view', 'seo.metadata.review']);
        $page = $this->page('review-request-generation');
        $metadata = app(SeoMetadataService::class)->updateForModel($page, [
            'title' => 'First requested title',
            'description' => $this->description(),
        ], 'en');
        $identity = ['owner_type' => 'page', 'owner_id' => $page->id, 'route_name' => null, 'locale' => 'en'];

        $this->actingAs($editor, 'admin')->post(route('seo.review.request'), $identity)->assertRedirect();
        $firstRequest = $metadata->fresh();
        $firstHash = $firstRequest->review_content_hash;
        $firstVersion = $firstRequest->review_request_version;

        $this->put(route('seo.content.update', ['page', $page->id]), $this->editorPayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->post(route('seo.review.request'), $identity)->assertRedirect();
        $secondRequest = $metadata->fresh();
        $this->assertGreaterThan($firstVersion, $secondRequest->review_request_version);

        $reviewPage = $this->actingAs($reviewer, 'admin')
            ->get(route('seo.content.edit', ['page', $page->id]))
            ->assertOk()
            ->assertSee('name="expected_review_hash" value="' . $secondRequest->review_content_hash . '"', false)
            ->assertSee('name="expected_review_version" value="' . $secondRequest->review_request_version . '"', false);

        $reviewPage->assertSee('Approve');
        $this->post(route('seo.review.resolve'), $identity + [
            'decision' => 'approve',
            'expected_review_hash' => $firstHash,
            'expected_review_version' => $firstVersion,
        ])->assertStatus(409);
        $this->assertSame('pending', $metadata->fresh()->review_status);
        $this->assertSame($secondRequest->review_content_hash, $metadata->fresh()->review_content_hash);

        $this->post(route('seo.review.resolve'), $identity + [
            'decision' => 'approve',
            'expected_review_hash' => $secondRequest->review_content_hash,
            'expected_review_version' => $secondRequest->review_request_version,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $metadata->fresh()->review_status);
    }

    public function test_annual_reports_have_a_permission_safe_no_code_seo_owner_in_every_workflow(): void
    {
        $editor = $this->adminWith(actions: ['seo.metadata.edit']);
        $reviewer = $this->adminWith(actions: ['seo.metadata.view', 'seo.metadata.review']);
        $english = AnnualReport::create([
            'title' => 'Ignite Impact Report 2026', 'sub_title' => 'Transparent progress and community impact.',
            'description' => $this->description(), 'slug' => 'ignite-impact-2026', 'language' => 'en',
            'image_path' => 'impact-cover.jpg', 'published_at' => now()->subDay(), 'status' => 1,
        ]);
        $bangla = AnnualReport::create([
            'translation_key' => $english->translation_key,
            'title' => 'ইগনাইট বার্ষিক প্রতিবেদন ২০২৬', 'sub_title' => 'স্বচ্ছ অগ্রগতি ও কমিউনিটির প্রভাব।',
            'description' => 'বাংলাদেশের কমিউনিটির সঙ্গে ইগনাইটের শিক্ষা, স্বাস্থ্য ও জীবিকা সহায়তার স্বচ্ছ অগ্রগতির পূর্ণ প্রতিবেদন।',
            'slug' => 'ignite-impact-2026-bn', 'language' => 'bn', 'published_at' => now()->addWeek(), 'status' => 1,
        ]);

        $this->actingAs($editor, 'admin')->get(route('seo.index', [
            'locale' => 'en', 'type' => 'annual_report', 'search' => 'Impact Report',
        ]))->assertOk()->assertSee('Annual report')->assertSee('Ignite Impact Report 2026');
        $this->get(route('seo.content.edit', ['annual_report', $english->id]))
            ->assertOk()->assertSee('Annual report')->assertSee('/annual-report/ignite-impact-2026');

        $editorPayload = $this->editorPayload();
        $editorPayload['expected_seo_version'] = app(SeoMetadataEditorVersionService::class)
            ->currentForModel($english, 'en');
        $editorPayload['permalink_slug'] = 'ignite-impact-report-2026';
        $this->put(route('seo.content.update', ['annual_report', $english->id]), $editorPayload)->assertRedirect();
        $this->assertDatabaseHas('annual_reports', ['id' => $english->id, 'slug' => 'ignite-impact-report-2026']);
        $this->assertDatabaseHas('seo_redirects', [
            'from_path' => '/annual-report/ignite-impact-2026', 'locale' => 'en', 'status_code' => 301,
        ]);
        $this->get(route('seo.content.edit', [
            'type' => 'annual_report',
            'id' => $bangla->id,
            'locale' => 'bn',
            'copy' => 'en',
        ]))->assertOk()->assertViewHas('editor', fn (array $editor): bool =>
            $editor['copying_english'] === true
            && $editor['values']['title'] === 'Edited after approval'
        );

        $this->get(route('seo.bulk.index', ['type' => 'annual_report']))
            ->assertOk()->assertSee('Ignite Impact Report 2026')->assertSee('ইগনাইট বার্ষিক প্রতিবেদন ২০২৬')->assertSee('Scheduled report');
        $this->put(route('seo.bulk.update'), ['items' => [
            [
                'owner_type' => 'annual_report', 'owner_id' => $english->id, 'route_name' => null, 'locale' => 'en',
                'expected_seo_version' => app(SeoMetadataEditorVersionService::class)->currentForModel($english->fresh(), 'en'),
                'mode' => 'custom', 'title' => 'Impact report — English', 'description' => $this->description(),
                'image' => 'https://example.test/report-en.jpg', 'indexable' => 1, 'schema_template' => 'webpage',
            ],
            [
                'owner_type' => 'annual_report', 'owner_id' => $bangla->id, 'route_name' => null, 'locale' => 'bn',
                'expected_seo_version' => app(SeoMetadataEditorVersionService::class)->currentForModel($bangla, 'bn'),
                'mode' => 'custom', 'title' => 'প্রভাব প্রতিবেদন — বাংলা',
                'description' => 'বাংলাদেশের কমিউনিটির সঙ্গে ইগনাইটের শিক্ষা, স্বাস্থ্য ও জীবিকা সহায়তার স্বচ্ছ অগ্রগতির পূর্ণ প্রতিবেদন।',
                'image' => 'https://example.test/report-bn.jpg', 'indexable' => 1, 'schema_template' => 'webpage',
            ],
        ]])->assertRedirect()->assertSessionHas('alert-type', 'success');
        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => AnnualReport::class, 'seoable_id' => $english->id, 'locale' => 'en', 'title' => 'Impact report — English',
        ]);
        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => AnnualReport::class, 'seoable_id' => $bangla->id, 'locale' => 'bn', 'title' => 'প্রভাব প্রতিবেদন — বাংলা',
        ]);

        $identity = ['owner_type' => 'annual_report', 'owner_id' => $english->id, 'route_name' => null, 'locale' => 'en'];
        $this->post(route('seo.review.request'), $identity)->assertRedirect()->assertSessionHas('alert-type', 'success');
        $requested = SeoMetadata::query()
            ->where('seoable_type', AnnualReport::class)
            ->where('seoable_id', $english->id)
            ->where('locale', 'en')
            ->firstOrFail();
        $this->actingAs($reviewer, 'admin')->post(route('seo.review.resolve'), $identity + [
            'decision' => 'approve',
            'expected_review_hash' => $requested->review_content_hash,
            'expected_review_version' => $requested->review_request_version,
        ])
            ->assertRedirect()->assertSessionHas('alert-type', 'success');
        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => AnnualReport::class, 'seoable_id' => $english->id, 'locale' => 'en', 'review_status' => 'approved',
        ]);
    }

    public function test_live_metrics_exclude_missing_translations_without_treating_duplicate_copy_as_unpublished(): void
    {
        $admin = $this->adminWith(actions: ['seo.metadata.view']);
        $first = $this->page('duplicate-live-one');
        $second = $this->page('duplicate-live-two');
        foreach ([$first, $second] as $page) {
            app(SeoMetadataService::class)->updateForModel($page, [
                'title' => 'Shared but published search title', 'description' => $this->description(),
                'og_image' => 'https://example.test/shared.jpg', 'robots_index' => true,
            ], 'en');
        }

        $englishResponse = $this->actingAs($admin, 'admin')->get(route('seo.index', ['locale' => 'en']));
        $englishResponse->assertOk();
        $duplicateTargets = collect($englishResponse->viewData('dashboardTargets'))
            ->whereIn('key', ['page:' . $first->uuid, 'page:' . $second->uuid]);
        $this->assertCount(2, $duplicateTargets);
        $duplicateTargets->each(function (array $target): void {
            $this->assertSame('Needs attention', $target['status']);
            $this->assertTrue($target['is_live']);
            $this->assertSame('published', $target['publication']['state']);
            $this->assertContains('duplicate_title', $target['issue_keys']);
        });

        $banglaResponse = $this->get(route('seo.index', ['locale' => 'bn']));
        $banglaResponse->assertOk();
        $missing = collect($banglaResponse->viewData('dashboardTargets'))->firstWhere('key', 'page:' . $first->uuid);
        $this->assertNotNull($missing);
        $this->assertFalse($missing['is_live']);
        $this->assertFalse($missing['publication']['is_live']);
        $this->assertSame('missing_translation', $missing['publication']['state']);

        $banglaCounts = $banglaResponse->viewData('dashboardCounts');
        $banglaTargets = collect($banglaResponse->viewData('dashboardTargets'));
        $expectedMissingTranslations = $banglaTargets
            ->filter(fn (array $target): bool => data_get($target, 'publication.state') === 'missing_translation')
            ->count();
        $expectedDrafts = $banglaTargets
            ->where('is_live', false)
            ->reject(fn (array $target): bool => data_get($target, 'publication.state') === 'missing_translation')
            ->count();
        $this->assertSame($expectedMissingTranslations, $banglaCounts['missing_translation']);
        $this->assertSame($expectedDrafts, $banglaCounts['draft']);
        $banglaResponse->assertSee(
            '<strong>' . $expectedMissingTranslations . '</strong><span>Missing translations</span>',
            false
        );
        $banglaSummary = collect($banglaResponse->viewData('languageSummary'))->firstWhere('id', 'bn');
        $this->assertSame($banglaCounts['indexable_live'], $banglaSummary['total']);
        $this->assertSame($banglaCounts['ready'], $banglaSummary['ready']);
        $banglaResponse->assertSee(
            'Bangla: ' . $banglaCounts['ready'] . ' of ' . $banglaCounts['indexable_live'] . ' indexable live pages ready',
            false
        );
    }

    public function test_media_picker_is_server_paginated_and_surfaces_dimensions_alt_and_crop_warnings(): void
    {
        $admin = $this->adminWith(actions: ['seo.metadata.view']);
        foreach (range(1, 25) as $number) {
            MediaAsset::create([
                'uuid' => (string) Str::uuid(), 'disk' => 'public', 'path' => "media/image-{$number}.jpg",
                'original_name' => "image-{$number}.jpg", 'mime_type' => 'image/jpeg', 'extension' => 'jpg',
                'bytes' => 1000, 'width' => $number === 1 ? 600 : 1200, 'height' => $number === 1 ? 600 : 630,
                'alt_text' => $number === 1 ? null : "Image {$number}", 'locale' => '*',
            ]);
        }

        $response = $this->actingAs($admin, 'admin')->getJson(route('seo.media.index', ['page' => 1]));
        $response->assertOk()->assertJsonCount(24, 'data')->assertJsonPath('meta.last_page', 2);
        $warningResponse = $this->getJson(route('seo.media.index', ['search' => 'image-1.jpg']));
        $warningResponse->assertOk()->assertJsonCount(1, 'data');
        $small = collect($warningResponse->json('data'))->firstWhere('name', 'image-1.jpg');
        $this->assertNotNull($small);
        $this->assertContains('Alternative text is missing', $small['warnings']);
        $this->assertContains('Smaller than the recommended 1200 × 630', $small['warnings']);
        $this->assertContains('May crop in a 1.91:1 social card', $small['warnings']);
    }

    public function test_content_hub_displays_publication_aware_seo_chip_and_direct_action(): void
    {
        $admin = $this->adminWith(menus: ['page.index'], actions: ['seo.metadata.view']);
        $page = $this->page('content-hub-seo');
        app(SeoMetadataService::class)->updateForModel($page, [
            'title' => 'Content hub SEO title', 'description' => $this->description(),
            'og_image' => 'https://example.test/share.jpg', 'robots_index' => true,
        ], 'en');

        $this->actingAs($admin, 'admin')->get(route('page.index'))
            ->assertOk()
            ->assertSee('SEO ready')
            ->assertSee(route('seo.content.edit', ['type' => 'page', 'id' => $page->id, 'locale' => 'en']), false);
    }

    public function test_content_hub_does_not_trust_an_approval_after_page_fallback_content_changes(): void
    {
        $editorAdmin = $this->adminWith(actions: ['seo.metadata.edit']);
        $reviewerAdmin = $this->adminWith(actions: ['seo.metadata.review']);
        $viewerAdmin = $this->adminWith(menus: ['page.index'], actions: ['seo.metadata.view']);
        $page = $this->page('content-hub-stale-approval');
        $metadata = app(SeoMetadataService::class)->updateForModel($page, [
            'title' => null,
            'description' => null,
            'og_image' => 'https://example.test/share.jpg',
        ], 'en');
        $identity = ['owner_type' => 'page', 'owner_id' => $page->id, 'route_name' => null, 'locale' => 'en'];
        $this->actingAs($editorAdmin, 'admin')->post(route('seo.review.request'), $identity)
            ->assertRedirect()->assertSessionHasNoErrors();
        $requested = $metadata->fresh();
        $this->actingAs($reviewerAdmin, 'admin')->post(route('seo.review.resolve'), $identity + [
            'decision' => 'approve',
            'expected_review_hash' => $requested->review_content_hash,
            'expected_review_version' => $requested->review_request_version,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $approved = $this->actingAs($viewerAdmin, 'admin')->get(route('page.index'))->assertOk();
        $approvedPage = $approved->viewData('pages')->getCollection()->firstWhere('id', $page->id);
        $this->assertSame('approved', $approvedPage?->seo_review_status);

        $page->forceFill(['name' => 'Changed after the SEO approval'])->save();
        $stale = $this->get(route('page.index'))->assertOk();
        $stalePage = $stale->viewData('pages')->getCollection()->firstWhere('id', $page->id);
        $this->assertSame('draft', $stalePage?->seo_review_status);

        $dashboard = $this->get(route('seo.index', ['search' => 'Changed after the SEO approval']))->assertOk();
        $dashboardTarget = collect($dashboard->viewData('dashboardTargets'))
            ->firstWhere('key', 'page:' . $page->uuid);
        $this->assertSame('draft', data_get($dashboardTarget, 'stored.review_status'));
        $this->assertTrue((bool) data_get($dashboardTarget, 'stored.review_stale'));

        $bulk = $this->get(route('seo.bulk.index', ['search' => 'Changed after the SEO approval']))->assertOk();
        $bulkTarget = collect($bulk->viewData('targets')->items())
            ->firstWhere('key', 'page:' . $page->uuid);
        $this->assertSame('draft', data_get($bulkTarget, 'stored.review_status'));
        $this->assertTrue((bool) data_get($bulkTarget, 'stored.review_stale'));

        $editor = $this->get(route('seo.content.edit', ['page', $page->id]))->assertOk();
        $this->assertSame('draft', data_get($editor->viewData('editor'), 'review.status'));
        $this->assertTrue((bool) data_get($editor->viewData('editor'), 'review.stale'));
        $this->assertSame('approved', $metadata->fresh()->review_status, 'Stored review history remains immutable.');
    }

    public function test_missing_translation_handoff_is_only_linked_for_translation_center_viewers(): void
    {
        $page = $this->page('permission-gated-translation-handoff');
        $category = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Permission gated translation handoff category',
            'slug' => 'permission-gated-translation-handoff-category-bn',
            'description' => $this->description(),
            'language' => 'bn',
            'status' => 1,
        ]);
        $query = ['locale' => 'bn', 'type' => 'page', 'search' => 'permission-gated-translation-handoff'];
        $seoOnly = $this->adminWith(actions: ['seo.metadata.edit']);

        $readOnlyHandoff = $this->actingAs($seoOnly, 'admin')
            ->get(route('seo.bulk.index', $query))
            ->assertOk()
            ->assertViewHas('canViewTranslations', false)
            ->assertSee('Ask a Translation Center editor to create this translation.');
        $missing = collect($readOnlyHandoff->viewData('targets')->items())
            ->firstWhere('key', 'page:' . $page->uuid);
        $this->assertNotNull($missing);
        $this->assertFalse($missing['is_editable']);
        $readOnlyHandoff->assertDontSee($missing['edit_url'], false);

        $mixedQuery = ['locale' => 'bn', 'search' => 'permission gated translation handoff'];
        $mixed = $this->get(route('seo.bulk.index', $mixedQuery))->assertOk();
        $mixedRows = collect($mixed->viewData('targets')->items());
        $missingIndex = $mixedRows->search(fn (array $target): bool => $target['key'] === 'page:' . $page->uuid);
        $categoryIndex = $mixedRows->search(fn (array $target): bool => $target['key'] === 'category:' . $category->uuid);
        $this->assertNotFalse($missingIndex);
        $this->assertNotFalse($categoryIndex);
        $this->assertStringNotContainsString('name="items[' . $missingIndex . '][owner_type]"', $mixed->getContent());
        $this->assertStringContainsString('name="items[' . $categoryIndex . '][owner_type]"', $mixed->getContent());
        $categoryTarget = $mixedRows->get($categoryIndex);
        $this->put(route('seo.bulk.update'), ['items' => [[
            'owner_type' => 'category',
            'owner_id' => $category->id,
            'route_name' => null,
            'locale' => 'bn',
            'expected_seo_version' => $categoryTarget['expected_seo_version'],
            'mode' => 'custom',
            'title' => 'বাংলা বাস্তব সার্চ সারি',
            'description' => 'বাংলাদেশের কমিউনিটির সঙ্গে শিক্ষা, স্বাস্থ্য ও জীবিকা সহায়তার দীর্ঘমেয়াদি অগ্রগতির বিস্তারিত তথ্য।',
            'image' => '',
            'indexable' => 1,
            'schema_template' => 'webpage',
        ]]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => Category::class,
            'seoable_id' => $category->id,
            'locale' => 'bn',
            'title' => 'বাংলা বাস্তব সার্চ সারি',
        ]);
        $this->assertDatabaseMissing('seo_metadata', [
            'seoable_type' => Page::class,
            'seoable_id' => $page->id,
            'locale' => 'bn',
        ]);

        $translationViewer = $this->adminWith(
            menus: ['translations.index'],
            actions: ['seo.metadata.edit']
        );
        $this->actingAs($translationViewer, 'admin')
            ->get(route('seo.bulk.index', $query))
            ->assertOk()
            ->assertViewHas('canViewTranslations', true)
            ->assertSee($missing['edit_url'], false)
            ->assertSee('Create this translation in Translation Center');

        $readOnlySeoViewer = $this->adminWith(
            menus: ['translations.index'],
            actions: ['seo.metadata.view']
        );
        $this->actingAs($readOnlySeoViewer, 'admin')
            ->get(route('seo.bulk.index', [
                'locale' => 'en',
                'type' => 'page',
                'search' => 'permission-gated-translation-handoff',
            ]))
            ->assertOk()
            ->assertViewHas('canEditMetadata', false)
            ->assertDontSee('Create this translation in Translation Center')
            ->assertDontSee('Ask a Translation Center editor to create this translation.');
    }

    public function test_revision_history_explains_human_before_and_after_values(): void
    {
        $admin = $this->adminWith(actions: ['seo.metadata.edit']);
        $page = $this->page('revision-diff');
        $service = app(SeoMetadataService::class);
        $metadata = $service->updateForModel($page, ['title' => 'Earlier title', 'description' => $this->description()], 'en');
        app(SeoMetadataRevisionService::class)->capture($metadata, 'Before title update');
        $metadata->forceFill(['title' => 'Current title'])->save();

        $this->actingAs($admin, 'admin')->get(route('seo.content.edit', ['page', $page->id]))
            ->assertOk()->assertSee('What changed (1)')->assertSee('Search title')->assertSee('Earlier title')->assertSee('Current title');
    }

    private function page(string $slug, string $locale = 'en', ?string $uuid = null): Page
    {
        return Page::create([
            'uuid' => $uuid ?: (string) Str::uuid(), 'name' => Str::headline($slug), 'slug' => $slug,
            'sub_title' => $this->description(), 'language' => $locale, 'status' => 1,
            'publication_status' => 'published', 'visibility' => 'public', 'published_at' => now()->subDay(),
        ]);
    }

    private function description(): string
    {
        return 'Learn how Ignite works alongside communities to deliver practical education, health and livelihood support throughout Bangladesh.';
    }

    private function editorPayload(): array
    {
        return [
            'locale' => 'en', 'schema_template' => 'none',
            'expected_editor_version' => 0,
            'seo' => [
                'title' => 'Edited after approval', 'description' => $this->description(), 'focus_keyword' => '',
                'canonical_url' => '', 'robots_index' => 1, 'robots_follow' => 1, 'og_title' => '',
                'og_description' => '', 'og_image' => '', 'twitter_card' => 'summary_large_image',
                'twitter_title' => '', 'twitter_description' => '', 'twitter_image' => '', 'schema_markup' => '',
                'sitemap_priority' => 0.5, 'sitemap_change_frequency' => 'monthly', 'exclude_from_sitemap' => 0,
            ],
        ];
    }

    private function adminWith(array $menus = [], array $actions = []): Admin
    {
        $menuIds = AuthMenu::whereIn('link', $menus)->pluck('id')->implode(',');
        $actionIds = MenuAction::whereIn('link', $actions)->pluck('id')->implode(',');
        $role = Role::create([
            'name' => 'SEO workflow ' . Str::random(8), 'permission' => $menuIds,
            'actionPermission' => $actionIds, 'serial' => '[]', 'status' => 1,
        ]);

        return Admin::create([
            'name' => 'SEO workflow QA', 'username' => 'seo-' . Str::lower(Str::random(10)),
            'email' => Str::lower(Str::random(10)) . '@example.test', 'role' => (string) $role->id,
            'status' => 1, 'password' => bcrypt('test-password'), 'must_change_password' => false,
        ]);
    }
}
