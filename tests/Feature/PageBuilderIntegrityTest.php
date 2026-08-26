<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\LatestNews;
use App\Models\MediaAsset;
use App\Models\MenuAction;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageTagModule;
use App\Models\ReusableBlock;
use App\Models\Role;
use App\Models\SeoMetadata;
use App\Models\Tag;
use App\Models\Testimonial;
use App\Services\PageRevisionService;
use App\Services\SeoMetadataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PageBuilderIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_blocks_respect_enabled_and_scheduling_rules(): void
    {
        $page = $this->makePage();

        $this->makeBlock($page, ['label' => 'Visible', 'sort_order' => 2]);
        $this->makeBlock($page, ['label' => 'First', 'sort_order' => 1]);
        $this->makeBlock($page, ['label' => 'Disabled', 'is_enabled' => false]);
        $this->makeBlock($page, ['label' => 'Future', 'available_from' => now()->addDay()]);
        $this->makeBlock($page, ['label' => 'Expired', 'available_until' => now()->subDay()]);

        $this->assertSame(
            ['First', 'Visible'],
            $page->visibleBlocks()->pluck('label')->all()
        );
    }

    public function test_revision_restore_recovers_page_content_and_deleted_blocks(): void
    {
        $page = $this->makePage(['name' => 'Original page']);
        $block = $this->makeBlock($page, [
            'label' => 'Original hero',
            'content' => ['heading' => 'Restore this'],
        ]);
        $service = app(PageRevisionService::class);
        $revision = $service->capture($page, 'Before edits');

        $page->update(['name' => 'Changed page']);
        $block->delete();

        $restored = $service->restore($page, $revision);

        $this->assertSame('Original page', $restored->name);
        $this->assertDatabaseHas('page_blocks', [
            'page_id' => $page->id,
            'label' => 'Original hero',
            'deleted_at' => null,
        ]);
        $this->assertSame(2, $page->revisions()->count());
    }

    public function test_revision_restore_removes_seo_created_after_the_restore_point(): void
    {
        $page = $this->makePage(['name' => 'Page without curated SEO']);
        $revisions = app(PageRevisionService::class);
        $revision = $revisions->capture($page, 'Before SEO was added');

        $metadata = app(SeoMetadataService::class)->updateForPage($page, [
            'title' => 'SEO added after the revision',
            'description' => 'This metadata should not survive the older restore point.',
        ]);
        $this->assertNotNull($page->fresh()->seo);

        $restored = $revisions->restore($page, $revision);

        $this->assertNull($restored->seo);
        $this->assertSoftDeleted('seo_metadata', ['id' => $metadata->id]);
    }

    public function test_page_revision_seo_restore_whitelists_copy_preserves_identity_and_resets_review(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $this->actingAs($admin, 'admin');
        $page = $this->makePage(['name' => 'SEO restore boundary']);
        $metadata = SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $page->id,
            'route_name' => 'original.route',
            'route_path' => '/original-path',
            'locale' => 'en',
            'title' => 'Approved restore title',
            'description' => 'Approved restore description',
            'review_status' => 'approved',
            'review_note' => 'Approved by reviewer',
            'review_content_hash' => str_repeat('a', 64),
            'review_requested_by' => 701,
            'review_requested_at' => now()->subHour(),
            'reviewed_by' => 702,
            'reviewed_at' => now()->subMinutes(30),
            'created_by' => 700,
            'updated_by' => 702,
        ]);
        $service = app(PageRevisionService::class);
        $revision = $service->capture($page, 'Safe SEO restore point');
        $savedSeo = data_get($revision->snapshot, 'seo');

        foreach (['seoable_type', 'seoable_id', 'route_name', 'route_path', 'locale', 'review_status', 'reviewed_by', 'created_by', 'updated_by'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $savedSeo);
        }

        $snapshot = $revision->snapshot;
        data_set($snapshot, 'seo.seoable_type', ReusableBlock::class);
        data_set($snapshot, 'seo.seoable_id', 999999);
        data_set($snapshot, 'seo.route_name', 'attacker.route');
        data_set($snapshot, 'seo.route_path', '/attacker-path');
        data_set($snapshot, 'seo.locale', 'bn');
        data_set($snapshot, 'seo.review_status', 'approved');
        data_set($snapshot, 'seo.review_note', 'Forged approval');
        data_set($snapshot, 'seo.review_content_hash', str_repeat('f', 64));
        data_set($snapshot, 'seo.review_requested_by', 999999);
        data_set($snapshot, 'seo.reviewed_by', 999999);
        data_set($snapshot, 'seo.created_by', 999999);
        data_set($snapshot, 'seo.updated_by', 999999);
        $revision->update(['snapshot' => $snapshot]);
        $metadata->forceFill(['title' => 'Current title before restore'])->save();

        $service->restore($page, $revision->fresh());
        $restored = $metadata->fresh();

        $this->assertSame('Approved restore title', $restored->title);
        $this->assertSame(Page::class, $restored->seoable_type);
        $this->assertSame($page->id, $restored->seoable_id);
        $this->assertSame('original.route', $restored->route_name);
        $this->assertSame('/original-path', $restored->route_path);
        $this->assertSame('en', $restored->locale);
        $this->assertSame(700, $restored->created_by);
        $this->assertSame($admin->id, $restored->updated_by);
        $this->assertSame('draft', $restored->review_status);
        $this->assertNull($restored->review_note);
        $this->assertNull($restored->review_content_hash);
        $this->assertNull($restored->review_requested_by);
        $this->assertNull($restored->review_requested_at);
        $this->assertNull($restored->reviewed_by);
        $this->assertNull($restored->reviewed_at);
    }

    public function test_editor_save_preserves_omitted_schedule_and_can_clear_it_explicitly(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $from = now()->addDay()->startOfMinute();
        $until = now()->addDays(2)->startOfMinute();
        $block = $this->makeBlock($page, [
            'available_from' => $from,
            'available_until' => $until,
        ]);
        $payload = [
            'locale' => 'en',
            'label' => 'Scheduled section',
            'content' => ['body' => 'Updated'],
            'settings' => [],
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ];

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block->uuid]),
            $this->withEditorVersion($page, $payload)
        )->assertOk();
        $this->assertTrue($block->fresh()->available_from?->equalTo($from) ?? false);
        $this->assertTrue($block->fresh()->available_until?->equalTo($until) ?? false);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block->uuid]),
            $this->withEditorVersion($page, $payload + ['available_from' => null, 'available_until' => null])
        )->assertOk();
        $this->assertNull($block->fresh()->available_from);
        $this->assertNull($block->fresh()->available_until);
    }

    public function test_revision_restore_reverts_shared_reusable_section_content(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $library = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared appeal',
            'type' => 'rich_text',
            'locale' => 'en',
            'content' => ['heading' => 'Original global copy'],
            'settings' => [],
            'is_enabled' => true,
        ]);
        $block = $this->makeBlock($page, [
            'reusable_block_id' => $library->id,
            'content' => ['heading' => 'Original global copy'],
        ]);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block->uuid]),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'label' => 'Shared appeal',
                'content' => ['heading' => 'Changed everywhere'],
                'settings' => [],
                'is_enabled' => true,
                'show_on_desktop' => true,
                'show_on_mobile' => true,
                'expected_reusable_version' => 0,
            ])
        )->assertOk();

        $revision = $page->revisions()->latest('revision')->firstOrFail();
        app(PageRevisionService::class)->restore($page, $revision, [
            $library->uuid => (int) $library->fresh()->editor_version,
        ]);

        $restored = $page->fresh()->blocks()->with('reusableBlock')->firstOrFail();
        $this->assertSame('Original global copy', $restored->resolvedContent()['heading']);
    }

    public function test_authorized_editor_gets_the_simple_editor_by_default_and_can_open_advanced_mode(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();

        $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => 'en']))
            ->assertOk()
            ->assertSee('Simple Editor')
            ->assertSee('<main class="simple-editor"', false)
            ->assertSee('Add section')
            ->assertSee('Save changes')
            ->assertSee('Advanced mode')
            ->assertSee('Your SEO editor manages Search &amp; Sharing.', false)
            ->assertDontSee(route('seo.content.edit', ['type' => 'page', 'id' => $page->id, 'locale' => 'en']), false)
            ->assertSee('Click any outlined text to edit it directly')
            ->assertSee('id="simple-undo"', false)
            ->assertSee('Duplicate section')
            ->assertSee('data-delete-section', false)
            ->assertSee('title="Move to trash"', false)
            ->assertSee('A page revision will be kept so an administrator can restore it.', false)
            ->assertSee('data-link-picker', false)
            ->assertSee('options.slide ? `data-slide-key="${key}"`', false)
            ->assertSee("textField('heading','Main heading',slide.heading,{max:180,slide:true})", false)
            ->assertSee("state.heroSlide += button.dataset.heroNav === 'next' ? 1 : -1; renderInspector(); renderPreview();", false)
            ->assertSee('dirtyBlocks: new Set()', false)
            ->assertSee('sessionStorage.setItem(draftKey', false)
            ->assertDontSee('localStorage.', false)
            ->assertSee('data-add-section="hero"', false)
            ->assertSee('data-add-section="media_text"', false)
            ->assertSee('data-add-section="team"', false)
            ->assertSee('data-add-section="partners"', false)
            ->assertSee('data-add-section="faq"', false)
            ->assertSee('data-add-section="timeline"', false)
            ->assertSee('data-add-section="gallery"', false)
            ->assertSee('data-add-section="video"', false)
            ->assertDontSee('data-add-section="custom_html"', false)
            ->assertDontSee('Save block')
            ->assertSee('window.addEventListener(\'beforeunload\'', false)
            ->assertSee('role="dialog" aria-modal="true"', false);
        $this->assertStringNotContainsString('.simple-topbar__title p,.simple-viewport{display:none}', file_get_contents(resource_path('views/admin/page/builder-simple.blade.php')));

        $role = Role::findOrFail($admin->role);
        $seoPermission = MenuAction::where('link', 'seo.metadata.edit')->firstOrFail();
        $role->update(['actionPermission' => collect(explode(',', (string) $role->actionPermission))
            ->push((string) $seoPermission->id)->filter()->unique()->implode(',')]);

        $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => 'en']))
            ->assertOk()
            ->assertSee('Edit Search &amp; Sharing', false)
            ->assertSee(route('seo.content.edit', ['type' => 'page', 'id' => $page->id, 'locale' => 'en']), false);

        $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => 'en', 'mode' => 'advanced']))
            ->assertOk()
            ->assertSee('Page builder')
            ->assertSee('Search &amp; Sharing', false)
            ->assertSee(route('seo.content.edit', ['type' => 'page', 'id' => $page->id, 'locale' => 'en']), false)
            ->assertDontSee('<summary>SEO pack</summary>', false)
            ->assertDontSee('id="seo-title"', false)
            ->assertSee('role="tablist"', false)
            ->assertSee('aria-controls="builder-panel-page"', false)
            ->assertSee('window.addEventListener(\'beforeunload\'', false)
            ->assertSee('dirtyScopes: new Set()', false)
            ->assertSee('notice.setAttribute(\'role\', \'status\')', false)
            ->assertSee('data-media-dropzone', false)
            ->assertSee('id="publish-menu" role="menu"', false)
            ->assertDontSee('.igf-viewport-button:not(.is-active)', false);
    }

    public function test_simple_editor_receives_safe_published_category_cards_for_live_preview(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $editorPage = $this->makePage(['name' => 'Live preview editor']);
        $activeCategory = $this->makeCategory('Active programs', ['slug' => 'active-programs']);
        $inactiveCategory = $this->makeCategory('Hidden programs', ['slug' => 'hidden-programs', 'status' => 0]);
        $publishedAt = now()->subDays(2)->startOfDay();
        $published = $this->makePage([
            'category_id' => $activeCategory->id,
            'name' => 'Published education program',
            'slug' => 'published-education-program',
            'sub_title' => '',
            'description' => '<p>Published <strong>preview</strong> body.</p>',
            'thumbnail' => 'education-program.jpg',
            'order_by' => 17,
            'published_at' => $publishedAt,
            'publication_status' => 'published',
            'visibility' => 'public',
            'status' => 1,
        ]);
        $draft = $this->makePage([
            'category_id' => $activeCategory->id,
            'name' => 'Draft program',
            'publication_status' => 'draft',
            'visibility' => 'public',
            'status' => 1,
        ]);
        $private = $this->makePage([
            'category_id' => $activeCategory->id,
            'name' => 'Private program',
            'publication_status' => 'published',
            'visibility' => 'private',
            'status' => 1,
        ]);
        $wrongLocale = $this->makePage([
            'category_id' => $activeCategory->id,
            'name' => 'Bangla program',
            'language' => 'bn',
            'publication_status' => 'published',
            'visibility' => 'public',
            'status' => 1,
        ]);
        $inactiveCategoryPage = $this->makePage([
            'category_id' => $inactiveCategory->id,
            'name' => 'Inactive category program',
            'publication_status' => 'published',
            'visibility' => 'public',
            'status' => 1,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', ['uuid' => $editorPage->uuid, 'locale' => 'en']))
            ->assertOk();

        $items = collect(data_get($response->viewData('blockContentOptions'), 'items.category'));
        $preview = $items->firstWhere('value', (string) $published->uuid);

        $this->assertSame([
            'value' => (string) $published->uuid,
            'label' => 'Published education program',
            'body' => 'Published preview body.',
            'image' => '/storage/photos/1/page/education-program.jpg',
            'image_alt' => 'Published education program',
            'url' => '/page/published-education-program',
            'featured_order' => 17,
            'published_at' => $publishedAt->getTimestamp(),
            'sort_id' => $published->id,
            'category' => 'active-programs',
        ], $preview);
        foreach ([$draft, $private, $wrongLocale, $inactiveCategoryPage] as $excluded) {
            $this->assertFalse($items->contains('value', (string) $excluded->uuid));
        }

        $simpleSource = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));
        $this->assertStringContainsString('function managedPagePreviewItems(block)', $simpleSource);
        $this->assertStringContainsString("block.type==='causes'", $simpleSource);
        $this->assertStringContainsString('const candidates = availableManagedItems(block, contentSource(block));', $simpleSource);
        $this->assertStringContainsString("markDirty('block'); renderInspector(); renderPreview();", $simpleSource);
    }

    public function test_simple_editor_receives_safe_approved_testimonials_for_live_preview(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $editorPage = $this->makePage(['name' => 'Testimonial preview editor']);
        $createdAt = now()->subDays(3)->startOfDay();
        $approved = Testimonial::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Approved community voice',
            'designation' => 'Parent and volunteer',
            'testimonial' => '<p>A story of learning &amp; hope.</p>',
            'photo' => 'community-voice.jpg',
            'order_by' => 19,
            'language' => 'en',
            'status' => 1,
        ]);
        $approved->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        $inactive = Testimonial::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Inactive community voice',
            'language' => 'en',
            'status' => 0,
        ]);
        $wrongLocale = Testimonial::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Bangla community voice',
            'language' => 'bn',
            'status' => 1,
        ]);
        $deleted = Testimonial::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Deleted community voice',
            'language' => 'en',
            'status' => 1,
        ]);
        $deleted->delete();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', ['uuid' => $editorPage->uuid, 'locale' => 'en']))
            ->assertOk()
            ->assertDontSee('<p>A story of learning', false);

        $items = collect(data_get($response->viewData('blockContentOptions'), 'items.testimonials'));
        $preview = $items->firstWhere('value', (string) $approved->uuid);

        $this->assertSame([
            'value' => (string) $approved->uuid,
            'label' => 'Approved community voice',
            'designation' => 'Parent and volunteer',
            'quote' => 'A story of learning & hope.',
            'photo' => '/storage/photos/1/testimonial/community-voice.jpg',
            'featured_order' => 19,
            'published_at' => $createdAt->getTimestamp(),
            'sort_id' => $approved->id,
        ], $preview);
        foreach ([$inactive, $wrongLocale, $deleted] as $excluded) {
            $this->assertFalse($items->contains('value', (string) $excluded->uuid));
        }

        $simpleSource = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));
        $this->assertStringContainsString("if(block.type==='testimonials')", $simpleSource);
        $this->assertStringContainsString('const items = managedPagePreviewItems(block);', $simpleSource);
        $this->assertStringContainsString('state.testimonialIndexes[block.uuid] = index;', $simpleSource);
        $this->assertStringContainsString('data-testimonial-step="-1"', $simpleSource);
        $this->assertStringContainsString('data-testimonial-index="${index}"', $simpleSource);
        $this->assertStringContainsString('wireTestimonialPreview();', $simpleSource);
        $this->assertStringContainsString('${escapeHtml(story.quote||\'\')}', $simpleSource);
        $this->assertStringContainsString('${escapeHtml(story.designation)}', $simpleSource);
    }

    public function test_simple_editor_section_sidebar_keeps_titles_and_move_controls_clickable(): void
    {
        $simpleSource = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));

        $this->assertStringContainsString('Use the arrow buttons or drag handle to change the visitor order.', $simpleSource);
        $this->assertStringContainsString('max-width:100vw!important', $simpleSource);
        $this->assertStringContainsString("grid-template-areas:'drag select' 'actions actions'", $simpleSource);
        $this->assertStringContainsString('.simple-select{grid-area:select;width:100%;padding:0 4px}', $simpleSource);
        $this->assertStringContainsString('.simple-order{grid-area:actions;grid-template-columns:repeat(3,44px);justify-content:end}', $simpleSource);
        $this->assertStringContainsString('class="simple-drag-placeholder"', $simpleSource);
        $this->assertStringContainsString('class="simple-order" aria-hidden="true"', $simpleSource);
        $this->assertStringContainsString('title="Select ${escapeHtml(block.label || typeLabels[block.type])}"', $simpleSource);
        $this->assertStringContainsString('.simple-drag{touch-action:none;user-select:none}', $simpleSource);
        $this->assertStringContainsString("handle?.addEventListener('pointerdown', event => {", $simpleSource);
        $this->assertStringContainsString('handle.setPointerCapture?.(event.pointerId);', $simpleSource);
        $this->assertStringContainsString('const target = document.elementFromPoint(event.clientX, event.clientY)?.closest(\'[data-section]\');', $simpleSource);
        $this->assertStringContainsString('state.blocks = reordered;', $simpleSource);
        $this->assertStringNotContainsString('data-section="${block.uuid}" draggable=', $simpleSource);
    }

    public function test_simple_editor_uses_an_accessible_guarded_delete_confirmation(): void
    {
        $simpleSource = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));

        $this->assertStringContainsString(
            'id="simple-delete-modal" role="dialog" aria-modal="true" aria-labelledby="simple-delete-title" aria-describedby="simple-delete-description" hidden',
            $simpleSource
        );
        $this->assertStringContainsString(
            'id="simple-delete-status" role="status" aria-live="polite"',
            $simpleSource
        );
        $this->assertStringContainsString('id="simple-confirm-delete"', $simpleSource);
        $this->assertStringContainsString('[data-cancel-section-delete]', $simpleSource);
        $this->assertStringContainsString("document.getElementById('simple-confirm-delete')?.addEventListener('click',confirmDeleteSection);", $simpleSource);

        $matched = preg_match(
            '/function openDeleteConfirmation\(.*?(?=\n\s*function wireOrdering\()/s',
            $simpleSource,
            $deleteFlow
        );
        $this->assertSame(1, $matched);
        $this->assertStringNotContainsString('confirm(', $deleteFlow[0]);
        $this->assertStringContainsString('state.pendingDeleteUuid = block.uuid;', $deleteFlow[0]);
        $this->assertStringContainsString('if (!permissions.delete || state.busy || !state.pendingDeleteUuid) return;', $deleteFlow[0]);
        $this->assertStringContainsString("modal.setAttribute('aria-busy', 'true');", $deleteFlow[0]);
        $this->assertStringContainsString('button.disabled = true;', $deleteFlow[0]);
        $this->assertStringContainsString("confirmButton.querySelector('span').textContent = 'Moving to trash…';", $deleteFlow[0]);
        $this->assertStringContainsString(
            "request(endpoint(routes.destroy, block.uuid), 'DELETE', {locale})",
            $deleteFlow[0]
        );
        $this->assertStringContainsString('? {...body, expected_version: editorVersion}', $simpleSource);
    }

    public function test_content_editor_cannot_publish_without_the_publish_permission(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $role = Role::findOrFail($admin->role);
        $publishAction = MenuAction::where('link', 'page.status')->firstOrFail();
        $role->update([
            'actionPermission' => collect(explode(',', (string) $role->actionPermission))
                ->reject(fn ($id) => (string) $id === (string) $publishAction->id)
                ->filter()
                ->implode(','),
        ]);
        $page = $this->makePage(['publication_status' => 'draft', 'status' => 0]);

        foreach ([null, 'advanced'] as $mode) {
            $parameters = ['uuid' => $page->uuid, 'locale' => 'en'];
            if ($mode) {
                $parameters['mode'] = $mode;
            }

            $response = $this->actingAs($admin, 'admin')
                ->get(route('page.builder.edit', $parameters))
                ->assertOk()
                ->assertSee('Only a publisher can change');

            $response->assertSee($mode
                ? 'id="publication-status" disabled'
                : 'id="simple-page-status" disabled', false);

            if ($mode) {
                $response
                    ->assertSee('id="save-page"', false)
                    ->assertDontSee('id="publish-menu-toggle"', false)
                    ->assertDontSee('id="publish-menu" role="menu"', false);
            }
        }

        $this->actingAs($admin, 'admin')->putJson(route('page.builder.simple.save', $page->uuid), $this->withEditorVersion($page, [
            'locale' => 'en',
            'page' => ['name' => 'Attempted publish', 'publication_status' => 'published'],
        ]))->assertForbidden();

        $this->actingAs($admin, 'admin')->putJson(route('page.builder.update', $page->uuid), $this->withEditorVersion($page, [
            'locale' => 'en',
            'name' => 'Attempted advanced publish',
            'sub_title' => null,
            'status' => true,
            'publication_status' => 'published',
            'visibility' => 'public',
            'scheduled_for' => null,
        ]))->assertForbidden();

        $this->assertSame('draft', $page->fresh()->publication_status);
    }

    public function test_content_editor_can_edit_a_scheduled_page_without_changing_its_schedule(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $role = Role::findOrFail($admin->role);
        $publishAction = MenuAction::where('link', 'page.status')->firstOrFail();
        $role->update([
            'actionPermission' => collect(explode(',', (string) $role->actionPermission))
                ->reject(fn ($id) => (string) $id === (string) $publishAction->id)
                ->filter()
                ->implode(','),
        ]);
        $scheduledFor = now()->addDay()->startOfMinute();
        $page = $this->makePage([
            'publication_status' => 'scheduled',
            'status' => 1,
            'scheduled_for' => $scheduledFor,
        ]);
        $block = $this->makeBlock($page, ['content' => ['heading' => 'Original copy']]);

        $this->actingAs($admin, 'admin')->putJson(route('page.builder.simple.save', $page->uuid), $this->withEditorVersion($page, [
            'locale' => 'en',
            'page' => [
                'name' => $page->name,
                'publication_status' => 'scheduled',
            ],
            'block' => [
                'uuid' => $block->uuid,
                'label' => $block->label,
                'content' => ['heading' => 'Updated copy'],
                'is_enabled' => true,
            ],
        ]))->assertOk();

        $this->assertSame('Updated copy', $block->fresh()->content['heading']);
        $this->assertTrue($page->fresh()->scheduled_for?->equalTo($scheduledFor) ?? false);
    }

    public function test_page_editor_cannot_change_shared_copy_without_library_permission_but_can_save_page_only_visibility(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $role = Role::findOrFail($admin->role);
        $reusableEdit = MenuAction::where('link', 'reusable-blocks.edit')->firstOrFail();
        $role->update([
            'actionPermission' => collect(explode(',', (string) $role->actionPermission))
                ->reject(fn ($id) => (string) $id === (string) $reusableEdit->id)
                ->filter()
                ->implode(','),
        ]);
        $this->assertFalse(app(\App\Http\Middleware\Permission::class)->allows($admin, 'reusable-blocks.edit'));

        $page = $this->makePage();
        $library = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared appeal',
            'type' => 'rich_text',
            'locale' => 'en',
            'content' => ['heading' => 'Approved global copy'],
            'settings' => [],
            'is_enabled' => true,
        ]);
        $block = $this->makeBlock($page, [
            'label' => 'Appeal on this page',
            'reusable_block_id' => $library->id,
            'content' => ['heading' => 'Old local shadow'],
        ]);
        $forbiddenMessage = 'This is a shared section. Your role can change its page placement, but not its shared label or content. Detach it for a page-only copy, or ask a Reusable Sections editor.';

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block->uuid]),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'label' => $block->label,
                'content' => ['heading' => 'Unauthorized global rewrite'],
                'settings' => [],
                'is_enabled' => true,
                'show_on_desktop' => true,
                'show_on_mobile' => true,
            ])
        )->assertForbidden()->assertJsonFragment(['message' => $forbiddenMessage]);

        $this->assertSame('Approved global copy', $library->fresh()->content['heading']);
        $this->assertSame('Old local shadow', $block->fresh()->content['heading']);
        $this->assertSame(0, $page->revisions()->count());

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block->uuid]),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'label' => $block->label,
                'content' => ['heading' => 'Approved global copy'],
                'settings' => [],
                'is_enabled' => false,
                'show_on_desktop' => true,
                'show_on_mobile' => true,
            ])
        )->assertOk()->assertJsonFragment([
            'message' => 'Page placement saved. Shared content was unchanged.',
        ]);

        $this->assertFalse($block->fresh()->is_enabled);
        $this->assertSame('Approved global copy', $library->fresh()->content['heading']);

        $this->actingAs($admin, 'admin')->putJson(route('page.builder.simple.save', $page->uuid), $this->withEditorVersion($page, [
            'locale' => 'en',
            'block' => [
                'uuid' => $block->uuid,
                'label' => $block->label,
                'content' => ['heading' => 'Second unauthorized rewrite'],
                'is_enabled' => false,
            ],
        ]))->assertForbidden()->assertJsonFragment(['message' => $forbiddenMessage]);

        $this->actingAs($admin, 'admin')->putJson(route('page.builder.simple.save', $page->uuid), $this->withEditorVersion($page, [
            'locale' => 'en',
            'block' => [
                'uuid' => $block->uuid,
                'label' => $block->label,
                'content' => ['heading' => 'Approved global copy'],
                'is_enabled' => true,
            ],
        ]))->assertOk();

        $this->assertTrue($block->fresh()->is_enabled);
        $this->assertSame('Approved global copy', $library->fresh()->content['heading']);

        foreach ([null, 'advanced'] as $mode) {
            $parameters = ['uuid' => $page->uuid, 'locale' => 'en'];
            if ($mode) {
                $parameters['mode'] = $mode;
            }

            $response = $this->actingAs($admin, 'admin')
                ->get(route('page.builder.edit', $parameters))
                ->assertOk()
                ->assertSee('"editReusable":false', false)
                ->assertSee('Shared content is read only for your role');

            $response->assertSee($mode
                ? 'Detach for this page'
                : 'Detach for local editing');
        }
    }

    public function test_simple_editor_guides_the_full_reusable_section_lifecycle(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $localBlock = $this->makeBlock($page, [
            'type' => 'ways_to_give',
            'label' => 'Ways to give on this page',
            'content' => [
                'heading' => 'Choose how to help',
                'selection_mode' => 'automatic',
                'layout' => 'card_grid',
            ],
        ]);
        $library = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Approved ways to give',
            'type' => 'ways_to_give',
            'locale' => 'en',
            'content' => [
                'heading' => 'Support our work',
                'selection_mode' => 'automatic',
                'layout' => 'card_grid',
            ],
            'settings' => [],
            'is_enabled' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => 'en']))
            ->assertOk()
            ->assertSee('Use a saved section')
            ->assertSee('Approved ways to give')
            ->assertSee('Add an approved shared section without rebuilding it.')
            ->assertSee('Save as a reusable section')
            ->assertSee('This affects pages:')
            ->assertSee('Detach for this page');

        $attached = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.reusable.attach', $page->uuid),
            $this->withEditorVersion($page, ['locale' => 'en', 'reusable_uuid' => $library->uuid])
        )->assertCreated()
            ->assertJsonPath('block.is_reusable', true)
            ->assertJsonPath('block.reusable_name', 'Approved ways to give');

        $attachedUuid = $attached->json('block.uuid');
        $this->assertDatabaseHas('page_blocks', [
            'uuid' => $attachedUuid,
            'reusable_block_id' => $library->id,
        ]);

        $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.detach', [$page->uuid, $attachedUuid]),
            $this->withEditorVersion($page, ['locale' => 'en'])
        )->assertOk()
            ->assertJsonPath('block.is_reusable', false)
            ->assertJsonPath('block.content.heading', 'Support our work');

        $this->assertDatabaseHas('page_blocks', [
            'uuid' => $attachedUuid,
            'reusable_block_id' => null,
        ]);

        $promoted = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.promote', [$page->uuid, $localBlock->uuid]),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'name' => 'Ways to Give — standard cards',
                'library_locale' => 'en',
            ])
        )->assertCreated()
            ->assertJsonPath('block.is_reusable', true)
            ->assertJsonPath('block.reusable_name', 'Ways to Give — standard cards');

        $this->assertDatabaseHas('reusable_blocks', [
            'uuid' => $promoted->json('reusable.uuid'),
            'name' => 'Ways to Give — standard cards',
            'type' => 'ways_to_give',
        ]);
    }

    public function test_builder_funding_controls_are_permission_aware_and_zakat_depends_on_fundable_status(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage([
            'is_funding_project' => false,
            'is_zakat_eligible' => false,
        ]);

        foreach ([null, 'advanced'] as $mode) {
            $parameters = ['uuid' => $page->uuid, 'locale' => 'en'];
            if ($mode) {
                $parameters['mode'] = $mode;
            }

            $html = $this->actingAs($admin, 'admin')
                ->get(route('page.builder.edit', $parameters))
                ->assertOk()
                ->assertSee('This is a fundable program or project')
                ->assertSee('First mark', false)
                ->getContent();
            $fundingId = $mode ? 'page-funding-project' : 'simple-page-funding-project';
            $zakatId = $mode ? 'page-zakat-eligible' : 'simple-page-zakat-eligible';

            $this->assertMatchesRegularExpression('/id="' . preg_quote($fundingId, '/') . '"[^>]*disabled/', $html);
            $this->assertMatchesRegularExpression('/id="' . preg_quote($zakatId, '/') . '"[^>]*disabled/', $html);
        }

        $role = Role::findOrFail($admin->role);
        $financialPermission = MenuAction::where('link', 'donationType.edit')->firstOrFail();
        $role->update([
            'actionPermission' => collect(explode(',', (string) $role->actionPermission))
                ->push((string) $financialPermission->id)
                ->filter()
                ->unique()
                ->implode(','),
        ]);
        $page->update(['is_funding_project' => true]);

        foreach ([null, 'advanced'] as $mode) {
            $parameters = ['uuid' => $page->uuid, 'locale' => 'en'];
            if ($mode) {
                $parameters['mode'] = $mode;
            }

            $html = $this->actingAs($admin, 'admin')
                ->get(route('page.builder.edit', $parameters))
                ->assertOk()
                ->getContent();
            $fundingId = $mode ? 'page-funding-project' : 'simple-page-funding-project';
            $zakatId = $mode ? 'page-zakat-eligible' : 'simple-page-zakat-eligible';

            preg_match('/<input[^>]*id="' . preg_quote($fundingId, '/') . '"[^>]*>/', $html, $fundingTag);
            preg_match('/<input[^>]*id="' . preg_quote($zakatId, '/') . '"[^>]*>/', $html, $zakatTag);
            $this->assertNotEmpty($fundingTag);
            $this->assertNotEmpty($zakatTag);
            $this->assertStringNotContainsString('disabled', $fundingTag[0]);
            $this->assertStringNotContainsString('disabled', $zakatTag[0]);
        }

        $simpleSource = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));
        $advancedSource = file_get_contents(resource_path('views/admin/page/builder.blade.php'));
        $this->assertStringContainsString('is_funding_project:document.getElementById(\'simple-page-funding-project\').checked', $simpleSource);
        $this->assertStringContainsString("is_funding_project: document.getElementById('page-funding-project').checked", $advancedSource);
        $this->assertStringContainsString('!funding.checked', $simpleSource);
        $this->assertStringContainsString('!funding.checked', $advancedSource);
    }

    public function test_global_admin_quick_links_only_show_authorized_destinations_with_dashboard_fallback(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();

        $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => 'en']))
            ->assertOk()
            ->assertSee('href="' . route('dashboard.index') . '"', false)
            ->assertSee('<span class="igf-nav-label">Dashboard</span>', false)
            ->assertDontSee('<form class="igf-admin-search"', false)
            ->assertDontSee('href="' . route('media.index') . '"', false)
            ->assertDontSee('href="' . route('admin.index') . '"', false)
            ->assertDontSee('<a class="igf-quick-create"', false);

        $role = Role::findOrFail($admin->role);
        $menus = AuthMenu::whereIn('link', ['page.index', 'media.index', 'admin.index'])->get();
        $createPage = MenuAction::where('link', 'page.create')->firstOrFail();
        $role->update([
            'permission' => $menus->pluck('id')->implode(','),
            'actionPermission' => collect(explode(',', (string) $role->actionPermission))
                ->push((string) $createPage->id)
                ->filter()
                ->unique()
                ->implode(','),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => 'en']))
            ->assertOk()
            ->assertSee('<form class="igf-admin-search"', false)
            ->assertSee('href="' . route('media.index') . '"', false)
            ->assertSee('<span class="igf-nav-label">Media Library</span>', false)
            ->assertSee('href="' . route('admin.index') . '"', false)
            ->assertSee('<span class="igf-nav-label">Administrators</span>', false)
            ->assertSee('<a class="igf-quick-create igf-btn igf-btn-primary" href="' . route('page.create') . '"', false);
    }

    public function test_advanced_builder_media_links_require_library_view_permission_without_disabling_uploads(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $parameters = ['uuid' => $page->uuid, 'locale' => 'en', 'mode' => 'advanced'];

        $restrictedHtml = $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', $parameters))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('mediaLibrary: null', $restrictedHtml);
        $this->assertStringContainsString('"create":true', $restrictedHtml);
        $this->assertStringContainsString(json_encode(route('page.builder.media.store', $page->uuid)), $restrictedHtml);
        $this->assertSame(3, substr_count($restrictedHtml, '${mediaLibraryLink}'));

        $role = Role::findOrFail($admin->role);
        $role->update([
            'permission' => (string) AuthMenu::where('link', 'media.index')->firstOrFail()->id,
        ]);

        $permittedHtml = $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', $parameters))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('mediaLibrary: ' . json_encode(route('media.index')), $permittedHtml);
        $this->assertStringContainsString(json_encode(route('page.builder.media.store', $page->uuid)), $permittedHtml);
        $this->assertSame(3, substr_count($permittedHtml, '${mediaLibraryLink}'));
    }

    public function test_admin_preview_renders_a_draft_with_the_real_public_component(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage([
            'name' => 'Unpublished community plan',
            'publication_status' => 'draft',
            'status' => 0,
        ]);
        $this->makeBlock($page, [
            'label' => 'Draft introduction',
            'content' => ['heading' => 'Visible only in preview'],
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('page.builder.preview', ['uuid' => $page->uuid, 'locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $view) => $view
                ->component('page')
                ->where('title', 'Preview: Unpublished community plan')
                ->where('meta_tag.robots', 'noindex,nofollow')
                ->where('data.page.uuid', $page->uuid)
                ->has('data.page.visible_blocks', 1)
            );

        $this->assertSame(
            route('page.builder.preview', ['uuid' => $page->uuid, 'locale' => 'en']),
            route('page.builder.preview', ['uuid' => $page->uuid, 'locale' => $page->language])
        );
    }

    public function test_admin_preview_uses_each_specialized_public_component(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $components = [
            'home' => 'Home/home',
            'about-us' => 'about',
            'zakat' => 'zakat',
        ];

        foreach ($components as $slug => $component) {
            $page = $this->makePage([
                'name' => ucfirst(str_replace('-', ' ', $slug)) . ' draft',
                'slug' => $slug,
                'publication_status' => 'draft',
                'status' => 0,
            ]);

            $this->actingAs($admin, 'admin')
                ->get(route('page.builder.preview', ['uuid' => $page->uuid, 'locale' => 'en']))
                ->assertOk()
                ->assertInertia(fn (Assert $view) => $view
                    ->component($component)
                    ->where('meta_tag.robots', 'noindex,nofollow')
                );
        }
    }

    public function test_sponsor_page_routes_editors_and_previews_to_the_dedicated_customizer(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage([
            'name' => 'Sponsor a child',
            'slug' => 'sponsor-a-child',
            'publication_status' => 'published',
            'visibility' => 'public',
        ]);
        $customizerUrl = route('site.settings.index', ['locale' => 'en']) . '#settings-sponsor_page';

        $this->get('/page/sponsor-a-child')
            ->assertMovedPermanently()
            ->assertRedirect(route('frontend.sponsor_child'));

        $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => 'en']))
            ->assertRedirect($customizerUrl)
            ->assertSessionHas('message', 'Sponsor-a-child uses the dedicated Sponsor customizer. Edit its wording, images and contribution amount there.');

        $pageMenu = AuthMenu::where('link', 'page.index')->firstOrFail();
        $siteSettingsEdit = MenuAction::where('link', 'site.settings.edit')->firstOrFail();
        $role = Role::findOrFail($admin->role);
        $role->update([
            'permission' => (string) $pageMenu->id,
            'actionPermission' => collect(explode(',', (string) $role->actionPermission))
                ->push((string) $siteSettingsEdit->id)->filter()->unique()->implode(','),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('page.index', ['search' => 'Sponsor a child']))
            ->assertOk()
            ->assertSee($customizerUrl, false)
            ->assertSee(route('frontend.sponsor_child'), false)
            ->assertSee('/sponsor-child')
            ->assertSee('Sponsor customizer')
            ->assertDontSee(route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => 'en']), false)
            ->assertDontSee(route('frontend.page', ['slug' => 'sponsor-a-child']), false);
    }

    public function test_builder_pickers_only_offer_content_that_can_render_publicly(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $editorPage = $this->makePage(['name' => 'Picker test editor']);
        $this->makePage([
            'name' => 'Visible picker page',
            'slug' => 'visible-picker-page',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
        ]);
        $this->makePage([
            'name' => 'Draft picker page',
            'slug' => 'draft-picker-page',
            'status' => 1,
            'publication_status' => 'draft',
            'visibility' => 'public',
        ]);
        $this->makePage([
            'name' => 'Inactive picker page',
            'slug' => 'inactive-picker-page',
            'status' => 0,
            'publication_status' => 'published',
            'visibility' => 'public',
        ]);

        foreach ([1 => 'Visible picker event', 0 => 'Inactive picker event'] as $status => $title) {
            NoticeBoard::create([
                'title' => $title,
                'slug' => Str::slug($title),
                'notice_type' => 'notice-board',
                'language' => 'en',
                'order_by' => 1,
                'status' => $status,
            ]);
        }
        foreach ([1 => 'Visible picker testimonial', 0 => 'Inactive picker testimonial'] as $status => $name) {
            Testimonial::create([
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'language' => 'en',
                'status' => $status,
            ]);
        }
        foreach ([1 => 'Visible picker team member', 0 => 'Inactive picker team member'] as $status => $name) {
            LatestNews::create([
                'name' => $name,
                'type' => 'our-members',
                'language' => 'en',
                'status' => $status,
            ]);
        }
        foreach ([1 => 'Visible picker gallery item', 0 => 'Inactive picker gallery item'] as $status => $name) {
            Gallery::create([
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'type' => 'gallery',
                'language' => 'en',
                'status' => $status,
            ]);
        }

        $response = $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', ['uuid' => $editorPage->uuid, 'locale' => 'en']))
            ->assertOk();

        foreach (['Visible picker page', 'Visible picker event', 'Visible picker testimonial', 'Visible picker team member', 'Visible picker gallery item'] as $label) {
            $response->assertSee($label);
        }
        foreach (['Draft picker page', 'Inactive picker page', 'Inactive picker event', 'Inactive picker testimonial', 'Inactive picker team member', 'Inactive picker gallery item'] as $label) {
            $response->assertDontSee($label);
        }
    }

    public function test_saving_page_settings_without_seo_does_not_overwrite_guided_seo(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage(['name' => 'Before settings save']);
        $seo = $page->seo()->create([
            'locale' => 'en',
            'title' => 'Keep the guided SEO title',
            'description' => 'Keep the guided SEO description.',
            'robots_index' => true,
            'robots_follow' => true,
            'twitter_card' => 'summary_large_image',
            'sitemap_priority' => 0.5,
            'sitemap_change_frequency' => 'monthly',
            'exclude_from_sitemap' => false,
        ]);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.update', $page->uuid),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'name' => 'After settings save',
                'sub_title' => 'Page copy changed independently.',
                'status' => true,
                'publication_status' => 'published',
                'visibility' => 'public',
                'scheduled_for' => null,
            ])
        )->assertOk()
            ->assertJsonPath('message', 'Page settings and publishing saved.');

        $this->assertSame('After settings save', $page->fresh()->name);
        $this->assertSame('Keep the guided SEO title', $seo->fresh()->title);
        $this->assertSame('Keep the guided SEO description.', $seo->fresh()->description);
    }

    public function test_advanced_page_settings_normalize_a_blank_optional_subtitle(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage(['sub_title' => 'Remove this optional subtitle.']);

        $this->actingAs($admin, 'admin')->putJson(route('page.builder.update', $page->uuid), $this->withEditorVersion($page, [
            'locale' => 'en',
            'name' => $page->name,
            'sub_title' => '',
            'status' => true,
            'publication_status' => 'published',
            'visibility' => 'public',
            'scheduled_for' => null,
        ]))->assertOk()
            ->assertJsonPath('page.sub_title', '');

        $this->assertSame('', $page->fresh()->sub_title);
    }

    public function test_page_builder_rejects_the_removed_legacy_seo_payload(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage(['name' => 'Protected page']);
        $seo = $page->seo()->create([
            'locale' => 'en',
            'title' => 'Managed only in Search & Sharing',
            'robots_index' => true,
            'robots_follow' => true,
            'twitter_card' => 'summary_large_image',
            'sitemap_priority' => 0.5,
            'sitemap_change_frequency' => 'monthly',
            'exclude_from_sitemap' => false,
        ]);

        $this->actingAs($admin, 'admin')->putJson(route('page.builder.update', $page->uuid), $this->withEditorVersion($page, [
            'locale' => 'en',
            'name' => 'Attempted overwrite',
            'sub_title' => null,
            'status' => true,
            'publication_status' => 'published',
            'visibility' => 'public',
            'scheduled_for' => null,
            'seo' => ['title' => 'Injected title'],
        ]))->assertForbidden();

        $this->assertSame('Protected page', $page->fresh()->name);
        $this->assertSame('Managed only in Search & Sharing', $seo->fresh()->title);
    }

    public function test_advanced_page_settings_save_guided_metadata_and_tag_sync_in_one_revision(): void
    {
        Storage::fake('public');
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage(['thumbnail' => '/legacy/listing.jpg']);
        $category = $this->makeCategory('Program stories');
        $banner = $this->makeBanner('Program page banner');
        $oldTag = $this->makeTag('Old assignment');
        $selectedTag = $this->makeTag('Education');
        $asset = $this->makeMediaAsset('page/listing-card.jpg');
        PageTagModule::create([
            'uuid' => (string) Str::uuid(),
            'page_id' => $page->id,
            'tag_id' => $oldTag->id,
        ]);
        PageTagModule::create([
            'uuid' => (string) Str::uuid(),
            'page_id' => $page->id,
            'tag_id' => $selectedTag->id,
        ]);
        PageTagModule::create([
            'uuid' => (string) Str::uuid(),
            'page_id' => $page->id,
            'tag_id' => $selectedTag->id,
        ]);

        $this->actingAs($admin, 'admin')->putJson(route('page.builder.update', $page->uuid), $this->withEditorVersion($page, [
            'locale' => 'en',
            'name' => 'Guided metadata page',
            'sub_title' => 'A visitor-friendly subtitle.',
            'status' => true,
            'publication_status' => 'published',
            'visibility' => 'public',
            'scheduled_for' => null,
            'category_id' => $category->id,
            'banner_id' => $banner->id,
            'thumbnail_asset_uuid' => $asset->uuid,
            'tag_ids' => [$selectedTag->id],
        ]))->assertOk()
            ->assertJsonPath('page.category_id', $category->id)
            ->assertJsonPath('page.banner_id', $banner->id)
            ->assertJsonPath('page.thumbnail_asset_uuid', $asset->uuid)
            ->assertJsonPath('page.tag_ids.0', $selectedTag->id);

        $fresh = $page->fresh('pageTags');
        $this->assertSame((string) $category->id, (string) $fresh->category_id);
        $this->assertSame((string) $banner->id, (string) $fresh->banner_id);
        $this->assertSame($asset->url, $fresh->getRawOriginal('thumbnail'));
        $this->assertSame([$selectedTag->id], $fresh->pageTags->pluck('tag_id')->map(fn ($id) => (int) $id)->all());

        $revision = $page->revisions()->firstOrFail();
        $this->assertSame('/legacy/listing.jpg', $revision->snapshot['page']['thumbnail']);
        $this->assertSame([$oldTag->id, $selectedTag->id, $selectedTag->id], collect($revision->snapshot['tags'])->pluck('tag_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_simple_page_settings_save_and_clear_guided_metadata(): void
    {
        Storage::fake('public');
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage(['publication_status' => 'draft', 'status' => 0]);
        $category = $this->makeCategory('Community work');
        $banner = $this->makeBanner('Community banner');
        $tag = $this->makeTag('Community');
        $asset = $this->makeMediaAsset('page/community.jpg');

        $this->actingAs($admin, 'admin')->putJson(route('page.builder.simple.save', $page->uuid), $this->withEditorVersion($page, [
            'locale' => 'en',
            'page' => [
                'name' => 'Simple metadata page',
                'publication_status' => 'published',
                'category_id' => $category->id,
                'banner_id' => $banner->id,
                'thumbnail_asset_uuid' => $asset->uuid,
                'tag_ids' => [$tag->id],
            ],
        ]))->assertOk()
            ->assertJsonPath('page.name', 'Simple metadata page')
            ->assertJsonPath('page.category_id', $category->id)
            ->assertJsonPath('page.banner_id', $banner->id)
            ->assertJsonPath('page.thumbnail_asset_uuid', $asset->uuid)
            ->assertJsonPath('page.tag_ids.0', $tag->id);

        $this->actingAs($admin, 'admin')->putJson(route('page.builder.simple.save', $page->uuid), $this->withEditorVersion($page, [
            'locale' => 'en',
            'page' => [
                'name' => 'Simple metadata page',
                'publication_status' => 'published',
                'category_id' => null,
                'banner_id' => null,
                'thumbnail_asset_uuid' => null,
                'tag_ids' => [],
            ],
        ]))->assertOk()
            ->assertJsonPath('page.category_id', null)
            ->assertJsonPath('page.banner_id', null)
            ->assertJsonPath('page.thumbnail_asset_uuid', null)
            ->assertJsonCount(0, 'page.tag_ids');

        $fresh = $page->fresh('pageTags');
        $this->assertNull($fresh->category_id);
        $this->assertNull($fresh->banner_id);
        $this->assertNull($fresh->thumbnail);
        $this->assertCount(0, $fresh->pageTags);
        $this->assertSame(2, $page->revisions()->count());
    }

    public function test_page_metadata_choices_are_active_locale_scoped_and_visible_in_both_modes(): void
    {
        Storage::fake('public');
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $category = $this->makeCategory('Visible English category');
        $this->makeCategory('Hidden Bangla category', ['language' => 'bn']);
        $banner = $this->makeBanner('Visible English banner');
        $this->makeBanner('Home slider is managed elsewhere', ['type' => 'banner-home']);
        $this->makeBanner('Hidden inactive banner', ['status' => 0]);
        $tag = $this->makeTag('Visible active tag');
        $this->makeTag('Hidden inactive tag', ['status' => 0]);
        $asset = $this->makeMediaAsset('page/visible-picker.jpg');
        $page->update([
            'category_id' => $category->id,
            'banner_id' => $banner->id,
            'thumbnail' => $asset->url,
        ]);
        PageTagModule::create([
            'uuid' => (string) Str::uuid(),
            'page_id' => $page->id,
            'tag_id' => $tag->id,
        ]);

        foreach ([null, 'advanced'] as $mode) {
            $parameters = ['uuid' => $page->uuid, 'locale' => 'en'];
            if ($mode) {
                $parameters['mode'] = $mode;
            }

            $response = $this->actingAs($admin, 'admin')
                ->get(route('page.builder.edit', $parameters))
                ->assertOk()
                ->assertSee('Listing image')
                ->assertSee('Visible English category')
                ->assertSee('Visible English banner')
                ->assertSee('Visible active tag')
                ->assertSee('Hero takes precedence:', false)
                ->assertSee($asset->uuid)
                ->assertDontSee('Hidden Bangla category')
                ->assertDontSee('Home slider is managed elsewhere')
                ->assertDontSee('Hidden inactive banner')
                ->assertDontSee('Hidden inactive tag');

            $response->assertSee($mode ? 'id="page-thumbnail-asset"' : 'id="simple-page-thumbnail"', false);
        }

        $invalidCategory = $this->makeCategory('Other locale only', ['language' => 'bn']);
        $invalidBanner = $this->makeBanner('Inactive selection', ['status' => 0]);
        $invalidTag = $this->makeTag('Inactive selection tag', ['status' => 0]);
        $invalidAsset = $this->makeMediaAsset('documents/not-an-image.pdf', ['mime_type' => 'application/pdf']);

        $this->actingAs($admin, 'admin')->putJson(route('page.builder.update', $page->uuid), $this->withEditorVersion($page, [
            'locale' => 'en',
            'name' => $page->name,
            'sub_title' => $page->sub_title,
            'status' => true,
            'publication_status' => 'published',
            'visibility' => 'public',
            'scheduled_for' => null,
            'category_id' => $invalidCategory->id,
            'banner_id' => $invalidBanner->id,
            'thumbnail_asset_uuid' => $invalidAsset->uuid,
            'tag_ids' => [$invalidTag->id],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'banner_id', 'thumbnail_asset_uuid', 'tag_ids.0']);
    }

    public function test_home_page_uses_permission_aware_home_banner_handoff_instead_of_page_banner_picker(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage(['name' => 'Home', 'slug' => 'home']);
        $pageBanner = $this->makeBanner('Disconnected page banner');
        $this->makeBanner('Managed home slider', ['type' => 'banner-home']);

        foreach ([null, 'advanced'] as $mode) {
            $parameters = ['uuid' => $page->uuid, 'locale' => 'en'];
            if ($mode) {
                $parameters['mode'] = $mode;
            }

            $response = $this->actingAs($admin, 'admin')
                ->get(route('page.builder.edit', $parameters))
                ->assertOk()
                ->assertSee('Home banners are managed separately.')
                ->assertSee('Ask a banner editor to update them.')
                ->assertDontSee('Disconnected page banner')
                ->assertDontSee('Managed home slider');

            $response->assertDontSee($mode ? 'id="page-banner"' : 'id="simple-page-banner"', false);
        }

        $bannerMenu = AuthMenu::where('link', 'banner.index')->firstOrFail();
        Role::findOrFail($admin->role)->update(['permission' => (string) $bannerMenu->id]);
        $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => 'en']))
            ->assertOk()
            ->assertSee('Open Home Banners')
            ->assertSee(route('banner.index'), false);

        $this->actingAs($admin, 'admin')->putJson(route('page.builder.update', $page->uuid), $this->withEditorVersion($page, [
            'locale' => 'en',
            'name' => 'Home',
            'sub_title' => $page->sub_title,
            'status' => true,
            'publication_status' => 'published',
            'visibility' => 'public',
            'scheduled_for' => null,
            'banner_id' => $pageBanner->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('banner_id');
    }

    public function test_simple_save_updates_plain_content_and_preserves_advanced_settings(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage([
            'name' => 'Original page name',
            'publication_status' => 'draft',
            'status' => 0,
            'meta_title' => 'Keep this SEO title',
        ]);
        $from = now()->addDay()->startOfMinute();
        $until = now()->addDays(2)->startOfMinute();
        $block = $this->makeBlock($page, [
            'label' => 'Original story',
            'content' => ['heading' => 'Old heading', 'body' => '<p>Old body</p>'],
            'settings' => ['variant' => 'approved-layout'],
            'show_on_desktop' => false,
            'show_on_mobile' => true,
            'available_from' => $from,
            'available_until' => $until,
        ]);
        $secondBlock = $this->makeBlock($page, ['label' => 'Move this first', 'sort_order' => 20]);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.simple.save', $page->uuid),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'page' => ['name' => 'Friendly page name', 'publication_status' => 'published'],
                'block' => [
                    'uuid' => $block->uuid,
                    'label' => 'Community story',
                    'content' => ['heading' => 'New heading', 'body' => '<p>Safe copy</p><script>alert(1)</script>'],
                    'is_enabled' => true,
                ],
                'order' => [$secondBlock->uuid, $block->uuid],
            ])
        )->assertOk()
            ->assertJsonPath('page.name', 'Friendly page name')
            ->assertJsonPath('page.publication_status', 'published')
            ->assertJsonPath('block.label', 'Community story')
            ->assertJsonMissing(['body' => '<p>Safe copy</p><script>alert(1)</script>']);

        $freshPage = $page->fresh();
        $freshBlock = $block->fresh();
        $this->assertSame('Friendly page name', $freshPage->name);
        $this->assertSame('published', $freshPage->publication_status);
        $this->assertSame('Keep this SEO title', $freshPage->meta_title);
        $this->assertSame(['variant' => 'approved-layout'], $freshBlock->settings);
        $this->assertFalse((bool) $freshBlock->show_on_desktop);
        $this->assertTrue($freshBlock->available_from?->equalTo($from) ?? false);
        $this->assertTrue($freshBlock->available_until?->equalTo($until) ?? false);
        $this->assertStringNotContainsString('<script', (string) $freshBlock->content['body']);
        $this->assertSame(0, $secondBlock->fresh()->sort_order);
        $this->assertSame(1, $freshBlock->sort_order);
        $this->assertGreaterThanOrEqual(1, $page->revisions()->count());
    }

    public function test_simple_save_persists_multiple_edited_sections_in_one_revision(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $first = $this->makeBlock($page, ['label' => 'First', 'content' => ['heading' => 'Old first']]);
        $second = $this->makeBlock($page, ['label' => 'Second', 'content' => ['heading' => 'Old second']]);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.simple.save', $page->uuid),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'blocks' => [
                    ['uuid' => $first->uuid, 'label' => 'First updated', 'content' => ['heading' => 'New first'], 'is_enabled' => true],
                    ['uuid' => $second->uuid, 'label' => 'Second updated', 'content' => ['heading' => 'New second'], 'is_enabled' => false],
                ],
            ])
        )->assertOk()
            ->assertJsonCount(2, 'blocks')
            ->assertJsonPath('blocks.0.label', 'First updated')
            ->assertJsonPath('blocks.1.label', 'Second updated');

        $this->assertSame('New first', $first->fresh()->content['heading']);
        $this->assertSame('New second', $second->fresh()->content['heading']);
        $this->assertFalse((bool) $second->fresh()->is_enabled);
        $this->assertSame(1, $page->revisions()->count());
    }

    public function test_simple_duplicate_stays_hidden_until_the_editor_saves_it(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $source = $this->makeBlock($page, ['label' => 'Feature section', 'is_enabled' => true]);

        $copy = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.duplicate', [$page->uuid, $source->uuid]),
            $this->withEditorVersion($page, ['locale' => 'en', 'as_draft' => true])
        )->assertCreated()
            ->assertJsonPath('block.is_enabled', false)
            ->json('block');

        $this->assertDatabaseHas('page_blocks', ['uuid' => $copy['uuid'], 'is_enabled' => false]);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.simple.save', $page->uuid),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'blocks' => [[
                    'uuid' => $copy['uuid'],
                    'label' => $copy['label'],
                    'content' => $copy['content'],
                    'is_enabled' => true,
                ]],
            ])
        )->assertOk();

        $this->assertDatabaseHas('page_blocks', ['uuid' => $copy['uuid'], 'is_enabled' => true]);
    }

    public function test_page_editor_can_upload_media_without_a_separate_gallery_permission(): void
    {
        Storage::fake('public');
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();

        $response = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.media.store', $page->uuid),
            [
                'locale' => 'en',
                'file' => UploadedFile::fake()->createWithContent(
                    'hero.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2WQAAAABJRU5ErkJggg==')
                ),
                'alt_text' => 'Community volunteers',
            ]
        )->assertCreated()
            ->assertJsonPath('message', 'Media uploaded and selected.')
            ->assertJsonPath('asset.original_name', 'hero.png');

        $path = $response->json('asset.path');
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('media_assets', [
            'path' => $path,
            'locale' => 'en',
            'alt_text' => 'Community volunteers',
            'uploaded_by' => $admin->id,
        ]);
    }

    public function test_page_editor_can_upload_an_explicit_supported_video_without_weakening_image_uploads(): void
    {
        Storage::fake('public');
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();

        $response = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.media.store', $page->uuid),
            [
                'locale' => 'en',
                'media_kind' => 'video',
                'file' => UploadedFile::fake()->create('community-story.mp4', 200, 'video/mp4'),
            ]
        )->assertCreated()
            ->assertJsonPath('asset.mime_type', 'video/mp4')
            ->assertJsonPath('asset.original_name', 'community-story.mp4');

        Storage::disk('public')->assertExists($response->json('asset.path'));
        $this->assertDatabaseHas('media_assets', [
            'path' => $response->json('asset.path'),
            'mime_type' => 'video/mp4',
            'locale' => 'en',
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.media.store', $page->uuid),
            [
                'locale' => 'en',
                'media_kind' => 'video',
                'file' => UploadedFile::fake()->createWithContent(
                    'not-a-video.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2WQAAAABJRU5ErkJggg==')
                ),
            ]
        )->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_page_builder_supplies_separate_image_and_video_asset_collections_to_both_editors(): void
    {
        Storage::fake('public');
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $image = $this->makeMediaAsset('media/story.jpg', ['mime_type' => 'image/jpeg']);
        $video = $this->makeMediaAsset('media/story.mp4', ['mime_type' => 'video/mp4']);
        $this->makeMediaAsset('media/brief.pdf', ['mime_type' => 'application/pdf']);

        foreach ([null, 'advanced'] as $mode) {
            $parameters = ['uuid' => $page->uuid, 'locale' => 'en'];
            if ($mode) {
                $parameters['mode'] = $mode;
            }

            $this->actingAs($admin, 'admin')
                ->get(route('page.builder.edit', $parameters))
                ->assertOk()
                ->assertViewHas('mediaAssets', fn ($assets): bool =>
                    $assets->pluck('uuid')->all() === [$image->uuid]
                )
                ->assertViewHas('videoAssets', fn ($assets): bool =>
                    $assets->pluck('uuid')->all() === [$video->uuid]
                );
        }
    }

    public function test_new_hero_exposes_configurable_overlay_control(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();

        $hero = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.store', $page->uuid),
            $this->withEditorVersion($page, ['locale' => 'en', 'type' => 'hero'])
        )->assertCreated()
            ->assertJsonPath('block.content.overlay_opacity', 64)
            ->assertJsonPath('block.content.autoplay', true)
            ->assertJsonPath('block.content.interval', 6000)
            ->assertJsonCount(1, 'block.content.slides')
            ->json('block');

        $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => 'en', 'mode' => 'advanced']))
            ->assertOk()
            ->assertSee('overlay_opacity')
            ->assertSee("key === 'overlay_opacity'", false);

        $this->assertDatabaseHas('page_blocks', [
            'uuid' => $hero['uuid'],
            'type' => 'hero',
        ]);
    }

    public function test_statistics_animation_has_safe_defaults_and_validated_editor_controls(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();

        $stats = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.store', $page->uuid),
            $this->withEditorVersion($page, ['locale' => 'en', 'type' => 'stats'])
        )->assertCreated()
            ->assertJsonPath('block.content.animation_enabled', true)
            ->assertJsonPath('block.content.animation_type', 'count_up')
            ->assertJsonPath('block.content.animation_duration', 1600)
            ->assertJsonPath('block.content.animation_delay', 120)
            ->json('block');

        $content = $stats['content'];
        $content['animation_type'] = 'pop';
        $content['animation_duration'] = 2600;
        $content['animation_delay'] = 250;

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $stats['uuid']]),
            $this->withEditorVersion($page, ['locale' => 'en', 'content' => $content])
        )->assertOk()
            ->assertJsonPath('block.content.animation_type', 'pop')
            ->assertJsonPath('block.content.animation_duration', 2600)
            ->assertJsonPath('block.content.animation_delay', 250);

        $content['animation_type'] = 'spin_forever';
        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $stats['uuid']]),
            $this->withEditorVersion($page, ['locale' => 'en', 'content' => $content])
        )->assertUnprocessable()->assertJsonValidationErrors('content.animation_type');

        $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => 'en']))
            ->assertOk()
            ->assertSee('Animate statistics')
            ->assertSee('Count up from zero')
            ->assertSee('Visitors who prefer reduced motion');
    }

    public function test_causes_focus_area_presentation_is_selectable_previewed_and_validated(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();

        $causes = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.store', $page->uuid),
            $this->withEditorVersion($page, ['locale' => 'en', 'type' => 'causes'])
        )->assertCreated()
            ->assertJsonPath('block.content.presentation', 'card_grid')
            ->json('block');

        $content = $causes['content'];
        $content['presentation'] = 'focus_areas';

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $causes['uuid']]),
            $this->withEditorVersion($page, ['locale' => 'en', 'content' => $content])
        )->assertOk()
            ->assertJsonPath('block.content.presentation', 'focus_areas');

        $content['presentation'] = 'untrusted-layout';
        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $causes['uuid']]),
            $this->withEditorVersion($page, ['locale' => 'en', 'content' => $content])
        )->assertUnprocessable()
            ->assertJsonValidationErrors('content.presentation');

        foreach ([null, 'advanced'] as $mode) {
            $parameters = ['uuid' => $page->uuid, 'locale' => 'en'];
            if ($mode) {
                $parameters['mode'] = $mode;
            }

            $response = $this->actingAs($admin, 'admin')
                ->get(route('page.builder.edit', $parameters))
                ->assertOk()
                ->assertSee('Animated focus areas')
                ->assertSee('Standard image cards');

            $this->assertSame(
                ['card_grid' => 'Standard image cards', 'focus_areas' => 'Animated focus areas'],
                data_get($response->viewData('blockContentOptions'), 'presentations.causes')
            );
        }

        $simpleSource = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));
        $advancedSource = file_get_contents(resource_path('views/admin/page/builder.blade.php'));
        $this->assertStringContainsString("block.type==='causes'&&c.presentation==='focus_areas'", $simpleSource);
        $this->assertStringContainsString('class="simple-focus-grid"', $simpleSource);
        $this->assertStringContainsString("block.type === 'causes' && content.presentation === 'focus_areas'", $advancedSource);
        $this->assertStringContainsString('renderPreview(); renderInspector();', $advancedSource);
    }

    public function test_every_section_has_a_validated_persistent_presentation_surface(): void
    {
        $presentations = [
            'standard' => 'Standard',
            'soft' => 'Soft background',
            'framed' => 'Framed panel',
            'contrast' => 'Dark contrast',
        ];
        $this->assertSame($presentations, config('page-builder.section_presentations'));
        $this->assertSame('standard', config('page-builder.section_presentation_default'));

        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $block = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.store', $page->uuid),
            $this->withEditorVersion($page, ['locale' => 'en', 'type' => 'rich_text'])
        )->assertCreated()
            ->assertJsonPath('block.content.section_presentation', 'standard')
            ->json('block');

        $content = $block['content'];
        $content['section_presentation'] = 'soft';
        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block['uuid']]),
            $this->withEditorVersion($page, ['locale' => 'en', 'content' => $content])
        )->assertOk()
            ->assertJsonPath('block.content.section_presentation', 'soft');

        $content['section_presentation'] = 'framed';
        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.simple.save', $page->uuid),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'blocks' => [[
                    'uuid' => $block['uuid'],
                    'label' => $block['label'],
                    'content' => $content,
                    'is_enabled' => true,
                ]],
            ])
        )->assertOk()
            ->assertJsonPath('blocks.0.content.section_presentation', 'framed');

        $savedBlock = PageBlock::where('uuid', $block['uuid'])->firstOrFail();
        $this->assertSame('framed', $savedBlock->content['section_presentation']);

        $content['section_presentation'] = 'untrusted-style';
        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block['uuid']]),
            $this->withEditorVersion($page, ['locale' => 'en', 'content' => $content])
        )->assertUnprocessable()
            ->assertJsonValidationErrors('content.section_presentation');
        $this->assertSame('framed', $savedBlock->fresh()->content['section_presentation']);

        foreach ([null, 'advanced'] as $mode) {
            $parameters = ['uuid' => $page->uuid, 'locale' => 'en'];
            if ($mode) {
                $parameters['mode'] = $mode;
            }

            $response = $this->actingAs($admin, 'admin')
                ->get(route('page.builder.edit', $parameters))
                ->assertOk()
                ->assertSee('Section presentation')
                ->assertSee('Soft background')
                ->assertSee('Framed panel')
                ->assertSee('Dark contrast')
                ->assertSee('Content layout')
                ->assertSee('Giving layout');

            $this->assertSame(
                $presentations,
                data_get($response->viewData('blockContentOptions'), 'presentations.sections')
            );
        }

        $reusable = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Reusable presentation section',
            'type' => 'rich_text',
            'locale' => 'en',
            'content' => ['heading' => 'Shared heading'],
            'settings' => [],
            'is_enabled' => true,
        ]);
        $reusablePayload = [
            'expected_version' => (int) $reusable->editor_version,
            'name' => $reusable->name,
            'locale' => 'en',
            'content' => ['heading' => 'Shared heading', 'section_presentation' => 'contrast'],
            'settings' => [],
            'is_enabled' => true,
        ];
        $this->actingAs($admin, 'admin')->putJson(route('reusable-blocks.update', $reusable), $reusablePayload)
            ->assertOk()
            ->assertJsonPath('block.content.section_presentation', 'contrast');

        $reusablePayload['expected_version'] = (int) $reusable->fresh()->editor_version;
        $reusablePayload['content']['section_presentation'] = 'unsafe-class';
        $this->actingAs($admin, 'admin')->putJson(route('reusable-blocks.update', $reusable), $reusablePayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.section_presentation');
        $this->assertSame('contrast', $reusable->fresh()->content['section_presentation']);
    }

    public function test_hero_carousel_slides_are_validated_sanitized_and_saved(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $hero = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.store', $page->uuid),
            $this->withEditorVersion($page, ['locale' => 'en', 'type' => 'hero'])
        )->assertCreated()->json('block');

        $slides = [
            [
                'eyebrow' => 'First story',
                'heading' => 'First carousel heading',
                'body' => 'First description',
                'primary_label' => 'Donate',
                'primary_url' => 'javascript:alert(1)',
                'secondary_label' => '',
                'secondary_url' => '',
                'report_label' => '',
                'report_url' => '',
                'image' => 'data:image/svg+xml,<svg onload=alert(1)>',
                'overlay_opacity' => 64,
            ],
            [
                'eyebrow' => 'Second story',
                'heading' => 'Second carousel heading',
                'body' => 'Second description',
                'primary_label' => 'Learn more',
                'primary_url' => '/page/about-us',
                'secondary_label' => '',
                'secondary_url' => '',
                'report_label' => '',
                'report_url' => '',
                'image' => '/image/banner/slider-2.png',
                'overlay_opacity' => 58,
            ],
        ];

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $hero['uuid']]),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'label' => 'Homepage carousel',
                'content' => [
                    'autoplay' => true,
                    'interval' => 5500,
                    'pause_on_hover' => true,
                    'slides' => $slides,
                ],
                'settings' => [],
                'is_enabled' => true,
                'show_on_desktop' => true,
                'show_on_mobile' => true,
            ])
        )->assertOk()
            ->assertJsonCount(2, 'block.content.slides')
            ->assertJsonPath('block.content.slides.0.heading', 'First carousel heading')
            ->assertJsonPath('block.content.slides.0.primary_url', '')
            ->assertJsonPath('block.content.slides.0.image', '')
            ->assertJsonPath('block.content.slides.1.heading', 'Second carousel heading')
            ->assertJsonPath('block.content.slides.1.image', '/image/banner/slider-2.png')
            ->assertJsonPath('block.content.interval', 5500);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $hero['uuid']]),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'content' => ['autoplay' => true, 'interval' => 1000, 'pause_on_hover' => true, 'slides' => $slides],
            ])
        )->assertUnprocessable()->assertJsonValidationErrors('content.interval');
    }

    public function test_partner_logo_items_are_guided_bounded_and_sanitized(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $partnerBlock = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.store', $page->uuid),
            $this->withEditorVersion($page, ['locale' => 'en', 'type' => 'partners'])
        )->assertCreated()->json('block');

        $tooMany = array_fill(0, 61, [
            'heading' => 'Partner',
            'body' => '',
            'image' => '/storage/partner.png',
            'image_alt' => 'Partner',
            'url' => '',
        ]);
        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $partnerBlock['uuid']]),
            $this->withEditorVersion($page, ['locale' => 'en', 'content' => ['heading' => 'Partner Organizations', 'items' => $tooMany]])
        )->assertUnprocessable()->assertJsonValidationErrors('content.items');

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $partnerBlock['uuid']]),
            $this->withEditorVersion($page, ['locale' => 'en', 'content' => ['heading' => 'Partner Organizations', 'items' => [[
                'heading' => 'Safe partner',
                'body' => '',
                'image' => '/storage/partner.png',
                'image_alt' => str_repeat('x', 256),
                'url' => '',
            ]]]])
        )->assertUnprocessable()->assertJsonValidationErrors('content.items.0.image_alt');

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $partnerBlock['uuid']]),
            $this->withEditorVersion($page, ['locale' => 'en', 'content' => ['heading' => 'Partner Organizations', 'items' => [[
                'heading' => 'Safe partner',
                'body' => '',
                'image' => '/storage/partner.png',
                'image_alt' => 'Safe partner',
                'url' => 'javascript:alert(1)',
            ]]]])
        )->assertOk()
            ->assertJsonPath('block.content.items.0.image_alt', 'Safe partner')
            ->assertJsonPath('block.content.items.0.url', '');

        $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => 'en']))
            ->assertOk()
            ->assertSee('Logo description for screen readers');
    }

    public function test_simple_card_editor_guides_images_icons_links_and_checklists_without_json(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $block = $this->makeBlock($page, [
            'type' => 'cards',
            'content' => [
                'variant' => 'contributions',
                'heading' => 'Ways to help',
                'items' => [[
                    'heading' => 'Sponsor a child',
                    'body' => "Education\nMeals",
                    'image' => '',
                    'image_alt' => '',
                    'icon' => 'child',
                    'link_label' => 'View sponsorship',
                    'url' => '/sponsor-child',
                ]],
            ],
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => 'en']))
            ->assertOk()
            ->assertSee('Checklist (one item per line)')
            ->assertSee('No JSON or special formatting is needed.')
            ->assertSee('Describe the image for screen readers')
            ->assertSee('Icon shown when there is no image')
            ->assertSee('data-card-key="link_label"', false);

        $content = $block->content;
        $content['items'][0]['link_label'] = str_repeat('x', 121);
        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block->uuid]),
            $this->withEditorVersion($page, ['locale' => 'en', 'content' => $content])
        )->assertUnprocessable()->assertJsonValidationErrors('content.items.0.link_label');
    }

    public function test_media_text_supports_legacy_images_uploaded_video_and_exact_youtube_links(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $block = $this->makeBlock($page, [
            'type' => 'media_text',
            'content' => [
                'heading' => 'A legacy image story',
                'image' => '/storage/media/story.jpg',
                'image_alt' => 'Community learners',
                'image_position' => 'left',
            ],
        ]);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block->uuid]),
            $this->withEditorVersion($page, ['locale' => 'en', 'content' => $block->content])
        )->assertOk()
            ->assertJsonPath('block.content.image', '/storage/media/story.jpg');

        $youtubeContent = array_merge($block->fresh()->content, [
            'media_type' => 'youtube',
            'youtube_url' => 'youtube.com/shorts/abcdefghijk',
            'video_url' => '',
            'poster' => '/storage/media/story-poster.jpg',
            'caption' => 'A caption that gives the video visible context.',
        ]);
        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block->uuid]),
            $this->withEditorVersion($page, ['locale' => 'en', 'content' => $youtubeContent])
        )->assertOk()
            ->assertJsonPath('block.content.media_type', 'youtube')
            ->assertJsonPath('block.content.youtube_url', 'youtube.com/shorts/abcdefghijk')
            ->assertJsonPath('block.content.caption', 'A caption that gives the video visible context.');

        foreach ([
            'https://example.test/youtube.com/watch?v=abcdefghijk',
            'https://youtube.com/watch?v=too-short',
            'https://youtube.com:8443/watch?v=abcdefghijk',
            'http://youtube.com/watch?v=abcdefghijk',
        ] as $invalidUrl) {
            $invalidContent = array_merge($youtubeContent, ['youtube_url' => $invalidUrl]);
            $this->actingAs($admin, 'admin')->putJson(
                route('page.builder.block.update', [$page->uuid, $block->uuid]),
                $this->withEditorVersion($page, ['locale' => 'en', 'content' => $invalidContent])
            )->assertUnprocessable()->assertJsonValidationErrors('content.youtube_url');
        }

        $videoContent = array_merge($youtubeContent, [
            'media_type' => 'video',
            'video_url' => '',
        ]);
        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block->uuid]),
            $this->withEditorVersion($page, ['locale' => 'en', 'content' => $videoContent])
        )->assertUnprocessable()->assertJsonValidationErrors('content.video_url');

        $videoContent['video_url'] = '/storage/media/community-story.mp4';
        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block->uuid]),
            $this->withEditorVersion($page, ['locale' => 'en', 'content' => $videoContent])
        )->assertOk()
            ->assertJsonPath('block.content.media_type', 'video')
            ->assertJsonPath('block.content.video_url', '/storage/media/community-story.mp4');
    }

    public function test_media_text_rejects_unbounded_caption_and_invalid_media_choice(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $block = $this->makeBlock($page, ['type' => 'media_text']);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block->uuid]),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'content' => [
                    'media_type' => 'remote_iframe',
                    'caption' => str_repeat('x', 2001),
                ],
            ])
        )->assertUnprocessable()->assertJsonValidationErrors([
            'content.media_type',
            'content.caption',
        ]);
    }

    public function test_media_text_source_rules_apply_to_create_and_simple_batch_saves(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();

        $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.store', $page->uuid),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'type' => 'media_text',
                'content' => [
                    'media_type' => 'youtube',
                    'youtube_url' => 'https://attacker.test/youtube.com/watch?v=abcdefghijk',
                ],
            ])
        )->assertUnprocessable()->assertJsonValidationErrors('content.youtube_url');

        $block = $this->makeBlock($page, [
            'type' => 'media_text',
            'content' => config('page-builder.default_content.media_text'),
        ]);
        $content = array_merge($block->content, [
            'media_type' => 'youtube',
            'youtube_url' => 'youtube.com/watch?v=invalid',
        ]);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.simple.save', $page->uuid),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'blocks' => [[
                    'uuid' => $block->uuid,
                    'label' => $block->label,
                    'content' => $content,
                    'is_enabled' => true,
                ]],
            ])
        )->assertUnprocessable()->assertJsonValidationErrors('content.youtube_url');
    }

    public function test_page_builder_media_upload_rejects_video_for_image_fields(): void
    {
        Storage::fake('public');
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();

        $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.media.store', $page->uuid),
            [
                'locale' => 'en',
                'file' => UploadedFile::fake()->create('background.mp4', 200, 'video/mp4'),
            ]
        )->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_editor_can_create_update_duplicate_reorder_and_soft_delete_blocks(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();

        $created = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.store', $page->uuid),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'type' => 'rich_text',
                'label' => 'Story section',
                'content' => ['heading' => 'A safe story', 'body' => '<p>Visible</p><script>alert(1)</script>'],
            ])
        )->assertCreated()->json('block');

        $this->assertStringNotContainsString('<script', (string) $created['content']['body']);

        $updated = $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $created['uuid']]),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'label' => 'Updated story section',
                'content' => ['heading' => 'Updated', 'body' => '<p>Updated copy</p>'],
                'settings' => [],
                'is_enabled' => true,
                'show_on_desktop' => true,
                'show_on_mobile' => true,
            ])
        )->assertOk()->json('block');
        $this->assertSame('Updated story section', $updated['label']);

        $duplicate = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.duplicate', [$page->uuid, $created['uuid']]),
            $this->withEditorVersion($page, ['locale' => 'en'])
        )->assertCreated()->json('block');

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.reorder', $page->uuid),
            $this->withEditorVersion($page, ['locale' => 'en', 'blocks' => [$duplicate['uuid'], $created['uuid']]])
        )->assertOk();
        $this->assertDatabaseHas('page_blocks', ['uuid' => $duplicate['uuid'], 'sort_order' => 0]);
        $this->assertDatabaseHas('page_blocks', ['uuid' => $created['uuid'], 'sort_order' => 1]);

        $this->actingAs($admin, 'admin')->deleteJson(
            route('page.builder.block.destroy', [$page->uuid, $created['uuid']]),
            $this->withEditorVersion($page, ['locale' => 'en'])
        )->assertOk();
        $this->assertSoftDeleted('page_blocks', ['uuid' => $created['uuid']]);
        $this->assertGreaterThanOrEqual(4, $page->revisions()->count());
    }

    public function test_reorder_requires_every_page_block_exactly_once(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $first = $this->makeBlock($page, ['sort_order' => 1]);
        $this->makeBlock($page, ['sort_order' => 2]);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.reorder', $page->uuid),
            $this->withEditorVersion($page, ['locale' => 'en', 'blocks' => [$first->uuid]])
        )->assertUnprocessable();
    }

    public function test_revision_from_another_page_cannot_be_restored(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $role = Role::findOrFail($admin->role);
        $seoRestore = MenuAction::where('link', 'seo.metadata.edit')->firstOrFail();
        $role->update([
            'actionPermission' => collect(explode(',', (string) $role->actionPermission))
                ->push((string) $seoRestore->id)
                ->filter()
                ->unique()
                ->implode(','),
        ]);
        $source = $this->makePage();
        $target = $this->makePage();
        $revision = app(PageRevisionService::class)->capture($source, 'Source page revision');

        $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.revision.restore', [$target->uuid, $revision->uuid]),
            $this->withEditorVersion($target, ['locale' => 'en'])
        )->assertNotFound();
    }

    public function test_full_revision_restore_requires_publishing_seo_and_reusable_permissions(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $role = Role::findOrFail($admin->role);
        $requiredLinks = ['page.status', 'seo.metadata.edit', 'reusable-blocks.edit'];
        $requiredActions = MenuAction::whereIn('link', $requiredLinks)->get()->keyBy('link');
        $this->assertSame($requiredLinks, $requiredActions->keys()->sortBy(fn ($link) => array_search($link, $requiredLinks, true))->values()->all());
        $page = $this->makePage();
        $revision = app(PageRevisionService::class)->capture($page, 'Full restore permission test');
        $baseIds = collect(explode(',', (string) $role->actionPermission))->filter();
        $allIds = $baseIds->merge($requiredActions->pluck('id')->map(fn ($id) => (string) $id))->unique()->values();
        $message = 'A full revision can change publishing, Search & Sharing, and shared sections. Ask an administrator with all three permissions to restore it.';

        foreach ($requiredLinks as $missingLink) {
            $missingId = (string) $requiredActions[$missingLink]->id;
            $role->update(['actionPermission' => $allIds->reject(fn ($id) => (string) $id === $missingId)->implode(',')]);

            $this->actingAs($admin, 'admin')->postJson(
                route('page.builder.revision.restore', [$page->uuid, $revision->uuid]),
                $this->withEditorVersion($page, ['locale' => 'en'])
            )->assertForbidden()->assertJsonFragment(['message' => $message]);
        }

        $role->update(['actionPermission' => $allIds->implode(',')]);
        $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.revision.restore', [$page->uuid, $revision->uuid]),
            $this->withEditorVersion($page, ['locale' => 'en'])
        )->assertOk()->assertJsonFragment(['message' => 'Revision restored.']);
    }

    public function test_seo_view_only_editor_sees_the_handoff_but_not_revision_restore_controls(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $role = Role::findOrFail($admin->role);
        $seoView = MenuAction::where('link', 'seo.metadata.view')->firstOrFail();
        $role->update([
            'actionPermission' => collect(explode(',', (string) $role->actionPermission))
                ->push((string) $seoView->id)
                ->filter()
                ->unique()
                ->implode(','),
        ]);
        $page = $this->makePage();
        app(PageRevisionService::class)->capture($page, 'View-only SEO permission check');

        $this->actingAs($admin, 'admin')
            ->get(route('page.builder.edit', [
                'uuid' => $page->uuid,
                'locale' => 'en',
                'mode' => 'advanced',
            ]))
            ->assertOk()
            ->assertSee('Open Search &amp; Sharing', false)
            ->assertSee('Only an administrator with publishing, Search &amp; Sharing, and Reusable Sections permissions can restore a full revision.', false)
            ->assertDontSee('class="igf-btn igf-btn--small restore-revision"', false);
    }

    private function makeCategory(string $name, array $overrides = []): Category
    {
        return Category::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(5)),
            'language' => 'en',
            'status' => 1,
        ], $overrides));
    }

    private function makeBanner(string $name, array $overrides = []): Banner
    {
        return Banner::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'type' => 'banner-page',
            'language' => 'en',
            'status' => 1,
        ], $overrides));
    }

    private function makeTag(string $name, array $overrides = []): Tag
    {
        return Tag::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(5)),
            'status' => 1,
        ], $overrides));
    }

    private function makeMediaAsset(string $path, array $overrides = []): MediaAsset
    {
        return MediaAsset::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => $path,
            'original_name' => basename($path),
            'mime_type' => 'image/jpeg',
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'bytes' => 1024,
            'locale' => 'en',
        ], $overrides));
    }

    private function makePage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'QA page',
            'sub_title' => 'QA subtitle',
            'slug' => 'qa-' . Str::lower(Str::random(8)),
            'status' => 1,
            'language' => 'en',
        ], $overrides));
    }

    private function makeBlock(Page $page, array $overrides = []): PageBlock
    {
        return PageBlock::create(array_merge([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'type' => 'rich_text',
            'label' => 'QA block',
            'content' => ['body' => 'QA content'],
            'sort_order' => 10,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ], $overrides));
    }

    /**
     * Attach the optimistic-lock token a freshly opened editor would send.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withEditorVersion(Page $page, array $payload): array
    {
        return ['expected_version' => (int) $page->fresh()->editor_version] + $payload;
    }

    private function makeAuthorizedAdmin(): Admin
    {
        $role = Role::create([
            'name' => 'Page editor',
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $actions = MenuAction::whereIn('link', [
            'page.builder.create',
            'page.builder.edit',
            'page.builder.destroy',
            'page.status',
            'reusable-blocks.edit',
        ])->get();
        $role->update(['actionPermission' => $actions->pluck('id')->implode(',')]);

        return Admin::create([
            'name' => 'Builder QA',
            'username' => 'builder-qa',
            'email' => 'builder-qa@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
