<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Category;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Role;
use App\Services\PageBlockContentResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PageBuilderClientHandoffRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_simple_section_has_an_explicit_semantic_preview_contract(): void
    {
        $expected = [
            'hero', 'rich_text', 'media_text', 'stats', 'cards', 'ways_to_give',
            'causes', 'events', 'testimonials', 'team', 'partners', 'faq',
            'timeline', 'gallery', 'video', 'cta', 'newsletter', 'layout',
        ];

        $this->assertSame($expected, array_keys(config('page-builder.simple_sections')));

        $source = $this->simpleBuilderSource();
        $preview = $this->between($source, 'function previewBlock(block)', 'function setInlineValue(');

        foreach ($expected as $type) {
            $this->assertMatchesRegularExpression(
                "/if\\s*\\(\\s*block\\.type\\s*===\\s*'" . preg_quote($type, '/') . "'/",
                $preview,
                "The {$type} Simple section must have an explicit preview branch instead of falling into a generic renderer."
            );
        }

        $this->assertStringContainsString('data-preview-type="${escapeHtml(block.type)}"', $source);
        $this->assertStringContainsString(
            "simple-preview-block--\${String(block.type).replaceAll('_','-')}",
            $source,
            'Every preview root must receive its stable type-specific class through the shared class builder.'
        );
        foreach (['events', 'team', 'gallery', 'video', 'newsletter', 'faq', 'timeline', 'partners'] as $type) {
            $this->assertStringContainsString("simple-preview-{$type}", $preview);
        }

        foreach ([
            'simple-preview-block--spacing-${spacing}',
            'simple-preview-block--align-${alignment}',
            'simple-preview-block--columns-${columns}',
        ] as $designClass) {
            $this->assertStringContainsString($designClass, $source);
        }
    }

    public function test_simple_visual_layout_editor_exposes_safe_keyboard_controls_and_responsive_preview(): void
    {
        $source = $this->simpleBuilderSource();
        $editor = $this->between($source, 'const layoutOptionsMarkup', 'function renderEssentialFields(');
        $wiring = $this->between($source, 'function wireLayoutEditor(', 'function wireInspector(');
        $preview = $this->between($source, 'function previewLayoutElement(', 'function previewBlock(');

        foreach (['full', 'halves', 'thirds', 'quarter', 'third_two_thirds', 'two_thirds_third'] as $preset) {
            $this->assertStringContainsString($preset, $source);
        }
        foreach (['heading', 'rich_text', 'image', 'video', 'button', 'divider', 'spacer'] as $elementType) {
            $this->assertStringContainsString("{$elementType}:", $source);
        }
        foreach (['layout', 'width', 'background', 'spacing'] as $rowField) {
            $this->assertStringContainsString("'{$rowField}'", $editor);
        }
        foreach (['up', 'down', 'duplicate', 'remove'] as $rowAction) {
            $this->assertStringContainsString("data-layout-row-action=\"{$rowAction}\"", $editor);
        }
        foreach (['up', 'down', 'left', 'right', 'duplicate', 'remove'] as $elementAction) {
            $this->assertStringContainsString("data-layout-element-action=\"{$elementAction}\"", $editor);
        }

        $this->assertStringContainsString('rows.length >= 12', $wiring);
        $this->assertStringContainsString('column.elements.length >= 12', $wiring);
        $this->assertStringContainsString("openMedia({kind:'layout'", $wiring);
        $this->assertStringContainsString("openVideoMedia({kind:'layout'", $wiring);
        $this->assertStringContainsString("target.kind==='layout'", $source);
        $this->assertStringContainsString("path.startsWith('rows.')", $source);

        foreach (['simple-layout-preview-row__inner', 'simple-layout-preview-columns', 'simple-layout-preview-column'] as $previewClass) {
            $this->assertStringContainsString($previewClass, $preview);
        }
        $this->assertStringContainsString('aria-label="Layout row', $preview);
        $this->assertStringContainsString('aria-label="Row ${rowIndex+1}, column ${columnIndex+1}', $preview);
        $this->assertStringContainsString('.simple-preview[data-viewport=mobile] .simple-layout-preview-columns{grid-template-columns:1fr!important}', $source);
        $this->assertStringContainsString('.simple-layout-preview-row--background-accent{background:linear-gradient', $source);
        $this->assertStringContainsString('window.crypto?.randomUUID', $source);
        $this->assertStringContainsString('copy.id = newLayoutId()', $wiring);
        $this->assertStringContainsString('duplicateLayoutRow(rows[index])', $wiring);
        $this->assertStringContainsString('const explicitHttpsYoutubeEmbedUrl', $source);
        $this->assertStringContainsString('function safeLayoutRichHtml', $source);
        $this->assertStringContainsString('safeLayoutRichHtml(element.body||\'\')', $source);
        $this->assertStringContainsString("if (!/^https:\\/\\//i.test(candidate)) return '';", $source);
        $this->assertStringContainsString("sourceType === 'youtube' ? explicitHttpsYoutubeEmbedUrl(element.source)", $preview);
        $this->assertStringNotContainsString('custom_html', $editor);
    }

    public function test_safe_design_fields_are_defaulted_validated_and_persisted(): void
    {
        $this->assertSame(['compact', 'standard', 'spacious'], array_keys(config('page-builder.section_spacing_options')));
        $this->assertSame(['left', 'center'], array_keys(config('page-builder.content_alignment_options')));
        $this->assertSame(
            ['auto', '2', '3', '4'],
            array_map('strval', array_keys(config('page-builder.column_count_options')))
        );
        $this->assertSame([
            'section_spacing' => 'standard',
            'content_alignment' => 'left',
            'column_count' => 'auto',
        ], config('page-builder.design_defaults'));

        $admin = $this->makeAdmin(['page.builder.create', 'page.builder.edit']);
        $page = $this->makePage();

        $created = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.store', $page->uuid),
            $this->withVersion($page, ['locale' => 'en', 'type' => 'cards'])
        )->assertCreated()
            ->assertJsonPath('block.content.section_spacing', 'standard')
            ->assertJsonPath('block.content.content_alignment', 'left')
            ->assertJsonPath('block.content.column_count', 'auto')
            ->json('block');

        $content = $created['content'];
        $content['section_spacing'] = 'spacious';
        $content['content_alignment'] = 'center';
        $content['column_count'] = '4';

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $created['uuid']]),
            $this->withVersion($page, [
                'locale' => 'en',
                'content' => $content,
            ])
        )->assertOk()
            ->assertJsonPath('block.content.section_spacing', 'spacious')
            ->assertJsonPath('block.content.content_alignment', 'center')
            ->assertJsonPath('block.content.column_count', '4');

        $saved = PageBlock::where('uuid', $created['uuid'])->firstOrFail();
        $this->assertSame('spacious', $saved->content['section_spacing']);
        $this->assertSame('center', $saved->content['content_alignment']);
        $this->assertSame('4', $saved->content['column_count']);

        $invalid = $saved->content;
        $invalid['section_spacing'] = 'huge injected-class';
        $invalid['content_alignment'] = 'justify';
        $invalid['column_count'] = '12';

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $saved->uuid]),
            $this->withVersion($page, ['locale' => 'en', 'content' => $invalid])
        )->assertUnprocessable()->assertJsonValidationErrors([
            'content.section_spacing',
            'content.content_alignment',
            'content.column_count',
        ]);

        $this->assertSame('spacious', $saved->fresh()->content['section_spacing']);

        $nonGridBlock = $this->makeBlock($page);
        $nonGridContent = $nonGridBlock->content;
        $nonGridContent['column_count'] = '3';

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $nonGridBlock->uuid]),
            $this->withVersion($page, ['locale' => 'en', 'content' => $nonGridContent])
        )->assertUnprocessable()->assertJsonValidationErrors('content.column_count');

        $this->assertSame('auto', $nonGridBlock->fresh()->content['column_count'] ?? 'auto');
    }

    public function test_simple_save_persists_device_visibility_and_section_schedule(): void
    {
        $admin = $this->makeAdmin(['page.builder.edit']);
        $page = $this->makePage();
        $block = $this->makeBlock($page);
        $from = now()->addDay()->startOfMinute();
        $until = now()->addDays(3)->startOfMinute();

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.simple.save', $page->uuid),
            $this->withVersion($page, [
                'locale' => 'en',
                'blocks' => [[
                    'uuid' => $block->uuid,
                    'label' => $block->label,
                    'content' => $block->content,
                    'is_enabled' => true,
                    'show_on_desktop' => false,
                    'show_on_mobile' => true,
                    'available_from' => $from->toIso8601String(),
                    'available_until' => $until->toIso8601String(),
                ]],
            ])
        )->assertOk()
            ->assertJsonPath('blocks.0.show_on_desktop', false)
            ->assertJsonPath('blocks.0.show_on_mobile', true);

        $saved = $block->fresh();
        $this->assertFalse((bool) $saved->show_on_desktop);
        $this->assertTrue((bool) $saved->show_on_mobile);
        $this->assertTrue($saved->available_from?->equalTo($from) ?? false);
        $this->assertTrue($saved->available_until?->equalTo($until) ?? false);
    }

    public function test_managed_manual_selection_preserves_the_authored_order_and_has_reorder_controls(): void
    {
        $category = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Programs',
            'slug' => 'programs',
            'language' => 'en',
            'status' => 1,
        ]);
        $first = $this->makePage([
            'name' => 'First program',
            'slug' => 'first-program',
            'category_id' => $category->id,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
        ]);
        $second = $this->makePage([
            'name' => 'Second program',
            'slug' => 'second-program',
            'category_id' => $category->id,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now(),
        ]);
        $host = $this->makePage(['name' => 'Builder host']);
        $block = $this->makeBlock($host, [
            'type' => 'causes',
            'content' => array_merge(config('page-builder.default_content.causes'), [
                'category_slug' => 'programs',
                'selection_mode' => 'manual',
                'selected_items' => [$second->uuid, $first->uuid],
                'limit' => 2,
            ]),
        ]);

        $admin = $this->makeAdmin(['page.builder.edit']);
        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.simple.save', $host->uuid),
            $this->withVersion($host, [
                'locale' => 'en',
                'blocks' => [[
                    'uuid' => $block->uuid,
                    'label' => $block->label,
                    'content' => $block->content,
                    'is_enabled' => true,
                    'show_on_desktop' => true,
                    'show_on_mobile' => true,
                    'available_from' => null,
                    'available_until' => null,
                ]],
            ])
        )->assertOk();

        $block->refresh();
        $this->assertSame([$second->uuid, $first->uuid], $block->content['selected_items']);

        app()->setLocale('en');
        $resolved = app(PageBlockContentResolver::class)->resolve($block);

        $this->assertSame(['Second program', 'First program'], array_column($resolved['items'], 'heading'));

        $source = $this->simpleBuilderSource();
        foreach (['data-managed-toggle', 'data-managed-move', 'data-managed-index'] as $attribute) {
            $this->assertStringContainsString($attribute, $source);
        }
    }

    public function test_simple_editor_exposes_missing_guided_controls_without_legacy_fa4_tokens(): void
    {
        $source = $this->simpleBuilderSource();
        $hero = $this->between($source, 'function renderHeroEditor(', 'function renderStatsEditor(');
        foreach (['report_label', 'report_url', 'overlay_opacity', 'autoplay', 'interval', 'pause_on_hover'] as $key) {
            $this->assertStringContainsString($key, $hero, "Hero control {$key} must be guided in Simple editor.");
        }

        $stats = $this->between($source, 'function renderStatsEditor(', 'function renderCardsEditor(');
        $this->assertStringContainsString("cardSelectField(index,'icon'", $stats);
        $this->assertStringContainsString(".replaceAll('data-card-key','data-stat-key')", $stats);
        $this->assertStringContainsString('data-stat-move', $stats);

        $cards = $this->between($source, 'function renderCardsEditor(', 'function renderMediaTextEditor(');
        $this->assertStringContainsString("timeline:{", $cards);
        $this->assertStringContainsString('data-card-key="eyebrow"', $cards);
        $this->assertStringContainsString('data-card-move', $cards);

        $essential = $this->between($source, 'function renderEssentialFields(', 'function renderInspector(');
        foreach (['email_label', 'email_placeholder', 'button_label', 'consent_text', 'privacy_label', 'privacy_url'] as $key) {
            $this->assertStringContainsString($key, $essential, "Newsletter control {$key} must be available in Simple editor.");
        }
        $this->assertStringContainsString("videoField('video_url'", $essential);
        $this->assertStringContainsString("imageField('poster'", $essential);
        $this->assertStringContainsString("textField('caption'", $essential);

        $simpleSections = config('page-builder.simple_sections');
        $invalidFa4Tokens = [
            'fa-image', 'fa-handshake', 'fa-circle-question', 'fa-timeline',
            'fa-images', 'fa-circle-play',
        ];
        foreach ($simpleSections as $section) {
            $this->assertNotContains($section['icon'], $invalidFa4Tokens);
        }
    }

    public function test_rich_text_preview_cannot_flatten_saved_html_through_inline_editing(): void
    {
        $source = $this->simpleBuilderSource();
        $preview = $this->between($source, 'function previewBlock(block)', 'function setInlineValue(');
        $matched = preg_match(
            "/if\\s*\\(\\s*block\\.type\\s*===\\s*'rich_text'\\s*\\)/",
            $preview,
            $richMatch,
            PREG_OFFSET_CAPTURE
        );
        $this->assertSame(1, $matched);
        $richStart = $richMatch[0][1];
        $nextMatched = preg_match(
            "/\\n\\s*if\\s*\\(\\s*block\\.type\\s*===/",
            $preview,
            $nextMatch,
            PREG_OFFSET_CAPTURE,
            $richStart + strlen($richMatch[0][0])
        );
        $richEnd = $nextMatched === 1 ? $nextMatch[0][1] : strlen($preview);
        $richBranch = substr($preview, $richStart, $richEnd - $richStart);

        $this->assertStringNotContainsString("inlineElement('p'", $richBranch);
        $this->assertStringNotContainsString('data-inline-path', $richBranch);
        $this->assertStringContainsString("safeRichHtml(c.body || '')", $richBranch);
        $this->assertStringContainsString('simple-preview-edit-hint', $richBranch);
        $this->assertStringNotContainsString("{rich:block.type==='rich_text'}", $preview);
    }

    public function test_contextual_manage_links_are_filtered_independently_by_destination_permission(): void
    {
        $page = $this->makePage();
        $restricted = $this->makeAdmin(['page.builder.edit']);

        $restrictedResponse = $this->actingAs($restricted, 'admin')
            ->get(route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => 'en']))
            ->assertOk();

        $this->assertSame([], data_get($restrictedResponse->viewData('blockContentOptions'), 'manage_urls'));

        $galleryEditor = $this->makeAdmin(['page.builder.edit', 'gallery.index']);
        $manageUrls = data_get(
            $this->actingAs($galleryEditor, 'admin')
                ->get(route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => 'en']))
                ->assertOk()
                ->viewData('blockContentOptions'),
            'manage_urls'
        );

        $this->assertSame([
            'gallery' => [
                'label' => 'Manage gallery photos',
                'url' => route('gallery.index'),
            ],
        ], $manageUrls);
        foreach (['cards', 'causes', 'events', 'testimonials', 'team', 'ways_to_give', 'media'] as $unauthorizedType) {
            $this->assertArrayNotHasKey($unauthorizedType, $manageUrls);
        }

        $source = $this->simpleBuilderSource();
        $this->assertStringContainsString('const map = contentOptions.manage_urls || {};', $source);
        $this->assertStringContainsString('links.filter(link => safeManageUrl(link.url))', $source);
        $this->assertStringContainsString('simple-manage-links', $source);
        $this->assertStringContainsString('simple-manage-link', $source);
    }

    private function simpleBuilderSource(): string
    {
        return (string) file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));
    }

    private function between(string $source, string $start, string $end): string
    {
        $startAt = strpos($source, $start);
        $this->assertNotFalse($startAt, "Missing source marker: {$start}");
        $endAt = strpos($source, $end, $startAt + strlen($start));
        $this->assertNotFalse($endAt, "Missing source marker: {$end}");

        return substr($source, $startAt, $endAt - $startAt);
    }

    private function makePage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Handoff page',
            'sub_title' => 'Handoff subtitle',
            'slug' => 'handoff-' . Str::lower(Str::random(10)),
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
            'label' => 'Handoff section',
            'content' => config('page-builder.default_content.rich_text'),
            'sort_order' => 0,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ], $overrides));
    }

    /** @param list<string> $capabilities */
    private function makeAdmin(array $capabilities): Admin
    {
        $role = Role::create([
            'name' => 'Page builder handoff editor ' . Str::random(8),
            'permission' => AuthMenu::query()
                ->whereIn('link', $capabilities)
                ->pluck('id')
                ->implode(','),
            'actionPermission' => MenuAction::query()
                ->whereIn('link', $capabilities)
                ->pluck('id')
                ->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Handoff Editor',
            'username' => 'handoff-' . Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function withVersion(Page $page, array $payload): array
    {
        return ['expected_version' => (int) $page->fresh()->editor_version] + $payload;
    }
}
