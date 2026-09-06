<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\MediaAsset;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\ReusableBlock;
use App\Models\Role;
use App\Services\LayoutBlockContentService;
use App\Services\SeoContentAnalysisService;
use App\Services\TranslationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PageBuilderLayoutContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_visual_layout_registry_default_and_editor_metadata_share_one_contract(): void
    {
        $this->assertSame('Visual layout', config('page-builder.block_types.layout'));
        $this->assertSame(12, config('page-builder.layout.limits.rows'));
        $this->assertSame([
            'full',
            'halves',
            'thirds',
            'quarter',
            'third_two_thirds',
            'two_thirds_third',
        ], array_keys(config('page-builder.layout.presets')));

        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $response = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.store', $page->uuid),
            $this->withEditorVersion($page, ['locale' => 'en', 'type' => 'layout'])
        )->assertCreated()
            ->assertJsonPath('block.type', 'layout')
            ->assertJsonPath('block.label', 'Visual layout')
            ->assertJsonPath('block.content.rows.0.layout', 'full')
            ->assertJsonPath('block.content.rows.0.columns.0.elements.0.type', 'heading')
            ->assertJsonPath('block.content.rows.0.columns.0.elements.1.type', 'rich_text');

        $this->assertSame(1, count($response->json('block.content.rows.0.columns')));
        $this->assertTrue(Str::isUuid($response->json('block.content.rows.0.id')));
        $this->assertTrue(Str::isUuid($response->json('block.content.rows.0.columns.0.elements.0.id')));
        $this->assertTrue(Str::isUuid($response->json('block.content.rows.0.columns.0.elements.1.id')));

        $editor = $this->actingAs($admin, 'admin')->get(route('page.builder.edit', [
            'uuid' => $page->uuid,
            'locale' => 'en',
        ]))->assertOk();
        $this->assertSame(
            config('page-builder.layout'),
            data_get($editor->viewData('blockContentOptions'), 'layout')
        );
    }

    public function test_every_layout_preset_requires_its_declared_column_count(): void
    {
        $service = app(LayoutBlockContentService::class);

        foreach (config('page-builder.layout.presets') as $token => $preset) {
            $content = $this->layoutContent(
                $token,
                array_fill(0, $preset['columns'], ['elements' => []])
            );

            $normalized = $service->normalizeAndValidate($content);
            $this->assertSame($token, $normalized['rows'][0]['layout']);
            $this->assertCount($preset['columns'], $normalized['rows'][0]['columns']);
            $this->assertTrue(Str::isUuid($normalized['rows'][0]['id']));
            $this->assertSame($normalized, $service->normalizeAndValidate($normalized));
        }
    }

    public function test_layout_save_normalizes_copy_sanitizes_rich_text_and_accepts_only_managed_media(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $block = $this->makeLayoutBlock($page);
        $this->makeMediaAsset('media/2026/09/community.jpg', 'image/jpeg');
        $this->makeMediaAsset('media/2026/09/story.webm', 'video/webm');

        $content = $this->layoutContent('halves', [
            ['elements' => [
                ['type' => 'heading', 'text' => '  Our mission  ', 'level' => 'h2'],
                [
                    'type' => 'rich_text',
                    'body' => '<section class="rogue"><div id="rogue"><img src="https://tracker.example/pixel.png"><h3 data-size="large">Program details</h3><p class="copy">Safe <strong id="emphasis">story</strong> <code>plain code</code><script>alert(1)</script> <a class="bad" href="http://unsafe.example">bad</a> <a target="_blank" href="/about-us">internal</a> <a href="https://example.org/help">secure</a></p></div></section>',
                ],
                [
                    'type' => 'image',
                    'path' => '/storage/media/2026/09/community.jpg',
                    'alt' => '  Students learning  ',
                    'caption' => '  Community classroom  ',
                ],
                ['type' => 'button', 'label' => '  Donate  ', 'url' => '/donate', 'style' => 'primary'],
            ]],
            ['elements' => [
                [
                    'type' => 'video',
                    'source_type' => 'upload',
                    'source' => '/storage/media/2026/09/story.webm',
                    'title' => '  Community story  ',
                ],
                [
                    'type' => 'video',
                    'source_type' => 'youtube',
                    'source' => 'https://youtu.be/abcdefghijk',
                    'title' => 'Watch our update',
                ],
                ['type' => 'divider'],
                ['type' => 'spacer', 'size' => 'large'],
                ['type' => 'button', 'label' => 'Partner site', 'url' => 'https://example.org/help', 'style' => 'text'],
            ]],
        ]);

        $response = $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block->uuid]),
            $this->withEditorVersion($page, ['locale' => 'en', 'content' => $content])
        )->assertOk();

        $saved = $response->json('block.content');
        $this->assertSame('Our mission', data_get($saved, 'rows.0.columns.0.elements.0.text'));
        $richText = data_get($saved, 'rows.0.columns.0.elements.1.body');
        $this->assertSame(
            '<h3>Program details</h3><p>Safe <strong>story</strong> plain code <a>bad</a> <a href="/about-us">internal</a> <a href="https://example.org/help">secure</a></p>',
            $richText
        );
        $this->assertStringNotContainsString('tracker.example', $richText);
        $this->assertStringNotContainsString('<img', $richText);
        $this->assertStringNotContainsString('<section', $richText);
        $this->assertStringNotContainsString('<div', $richText);
        $this->assertStringNotContainsString('class=', $richText);
        $this->assertStringNotContainsString('id=', $richText);
        $this->assertSame('Students learning', data_get($saved, 'rows.0.columns.0.elements.2.alt'));
        $this->assertSame('Community classroom', data_get($saved, 'rows.0.columns.0.elements.2.caption'));
        $this->assertSame('/storage/media/2026/09/story.webm', data_get($saved, 'rows.0.columns.1.elements.0.source'));
        $this->assertSame(
            'https://www.youtube.com/watch?v=abcdefghijk',
            data_get($saved, 'rows.0.columns.1.elements.1.source')
        );
        $this->assertSame('Donate', data_get($saved, 'rows.0.columns.0.elements.3.label'));
        $this->assertStringNotContainsString('script', json_encode($block->fresh()->content));
    }

    public function test_layout_rejects_malformed_oversized_or_unsafe_payloads_without_mutating_the_block(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $block = $this->makeLayoutBlock($page);
        $original = $block->content;

        $wrongColumns = $this->layoutContent('thirds', [
            ['elements' => []],
            ['elements' => []],
        ]);
        $tooManyRows = $this->layoutContent();
        $tooManyRows['rows'] = array_fill(0, 13, $tooManyRows['rows'][0]);
        $tooManyElements = $this->layoutContent();
        $tooManyElements['rows'][0]['columns'][0]['elements'] = array_fill(0, 13, ['type' => 'divider']);
        $unknownSetting = $this->layoutContent();
        $unknownSetting['rows'][0]['columns'][0]['elements'][0]['onclick'] = 'alert(1)';
        $duplicateElementId = (string) Str::uuid();
        $duplicateIdentities = $this->layoutContent('full', [[
            'elements' => [
                ['id' => $duplicateElementId, 'type' => 'heading', 'text' => 'First', 'level' => 'h2'],
                ['id' => $duplicateElementId, 'type' => 'heading', 'text' => 'Second', 'level' => 'h3'],
            ],
        ]]);
        $unsafeButton = $this->layoutContent('full', [[
            'elements' => [[
                'type' => 'button',
                'label' => 'Unsafe',
                'url' => 'javascript:alert(1)',
                'style' => 'primary',
            ]],
        ]]);
        $insecureHttpButton = $unsafeButton;
        $insecureHttpButton['rows'][0]['columns'][0]['elements'][0]['url'] = 'http://example.org/donate';
        $customHtml = $this->layoutContent('full', [[
            'elements' => [[
                'type' => 'custom_html',
                'html' => '<script>alert(1)</script>',
            ]],
        ]]);
        $unmanagedImage = $this->layoutContent('full', [[
            'elements' => [[
                'type' => 'image',
                'path' => '/storage/media/missing.jpg',
                'alt' => 'Missing image',
            ]],
        ]]);
        $this->makeMediaAsset('media/2026/09/dangling.jpg', 'image/jpeg', false);
        $danglingImage = $this->layoutContent('full', [[
            'elements' => [[
                'type' => 'image',
                'path' => '/storage/media/2026/09/dangling.jpg',
                'alt' => 'Dangling image record',
            ]],
        ]]);
        $spoofedYouTube = $this->layoutContent('full', [[
            'elements' => [[
                'type' => 'video',
                'source_type' => 'youtube',
                'source' => 'https://attacker.test/youtube.com/watch?v=abcdefghijk',
                'title' => 'Unsafe video',
            ]],
        ]]);
        $tooLarge = $this->layoutContent('full', [[
            'elements' => [[
                'type' => 'rich_text',
                'body' => str_repeat('x', 525000),
            ]],
        ]]);

        $cases = [
            [$wrongColumns, 'content.rows.0.columns'],
            [$tooManyRows, 'content.rows'],
            [$tooManyElements, 'content.rows.0.columns.0.elements'],
            [$unknownSetting, 'content.rows.0.columns.0.elements.0.onclick'],
            [$duplicateIdentities, 'content.rows.0.columns.0.elements.1.id'],
            [$unsafeButton, 'content.rows.0.columns.0.elements.0.url'],
            [$insecureHttpButton, 'content.rows.0.columns.0.elements.0.url'],
            [$customHtml, 'content.rows.0.columns.0.elements.0.type'],
            [$unmanagedImage, 'content.rows.0.columns.0.elements.0.path'],
            [$danglingImage, 'content.rows.0.columns.0.elements.0.path'],
            [$spoofedYouTube, 'content.rows.0.columns.0.elements.0.source'],
            [$tooLarge, 'content'],
        ];

        foreach ($cases as [$content, $error]) {
            $this->actingAs($admin, 'admin')->putJson(
                route('page.builder.block.update', [$page->uuid, $block->uuid]),
                $this->withEditorVersion($page, ['locale' => 'en', 'content' => $content])
            )->assertUnprocessable()->assertJsonValidationErrors($error);

            $this->assertSame($original, $block->fresh()->content);
            $this->assertSame(0, (int) $page->fresh()->editor_version);
        }
    }

    public function test_simple_and_reusable_editors_apply_the_same_layout_normalizer(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $block = $this->makeLayoutBlock($page);
        $invalid = $this->layoutContent('halves', [['elements' => []]]);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.simple.save', $page->uuid),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'blocks' => [[
                    'uuid' => $block->uuid,
                    'label' => $block->label,
                    'content' => $invalid,
                    'is_enabled' => true,
                ]],
            ])
        )->assertUnprocessable()->assertJsonValidationErrors(
            'blocks.' . $block->uuid . '.content.rows.0.columns'
        );

        $valid = $this->layoutContent('full', [[
            'elements' => [
                ['type' => 'heading', 'text' => '  Saved in batch  ', 'level' => 'h3'],
                ['type' => 'rich_text', 'body' => '<p>Batch copy<script>bad()</script></p>'],
            ],
        ]]);
        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.simple.save', $page->uuid),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'blocks' => [[
                    'uuid' => $block->uuid,
                    'label' => $block->label,
                    'content' => $valid,
                    'is_enabled' => true,
                ]],
            ])
        )->assertOk()
            ->assertJsonPath('blocks.0.content.rows.0.columns.0.elements.0.text', 'Saved in batch')
            ->assertJsonPath('blocks.0.content.rows.0.columns.0.elements.1.body', '<p>Batch copy</p>');

        $reusable = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared visual layout',
            'type' => 'layout',
            'locale' => 'en',
            'content' => config('page-builder.default_content.layout'),
            'settings' => [],
            'is_enabled' => true,
        ]);
        $valid['rows'][0]['columns'][0]['elements'][0]['text'] = '  Saved everywhere  ';
        $this->actingAs($admin, 'admin')->putJson(route('reusable-blocks.update', $reusable), [
            'expected_version' => (int) $reusable->editor_version,
            'name' => $reusable->name,
            'locale' => 'en',
            'content' => $valid,
            'settings' => [],
            'is_enabled' => true,
        ])->assertOk()
            ->assertJsonPath('block.content.rows.0.columns.0.elements.0.text', 'Saved everywhere');

        $this->assertSame(
            '<p>Batch copy</p>',
            data_get($reusable->fresh()->content, 'rows.0.columns.0.elements.1.body')
        );
    }

    public function test_duplicating_a_layout_starts_a_distinct_translation_family_and_regenerates_nested_ids(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage();
        $content = app(LayoutBlockContentService::class)->normalizeAndValidate(
            $this->layoutContent('full', [[
                'elements' => [
                    ['type' => 'heading', 'text' => 'Original heading', 'level' => 'h2'],
                    ['type' => 'rich_text', 'body' => '<p>Original body</p>'],
                ],
            ]])
        );
        $source = $this->makeLayoutBlock($page, [
            'translation_key' => (string) Str::uuid(),
            'content' => $content,
        ]);

        $response = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.duplicate', [$page->uuid, $source->uuid]),
            $this->withEditorVersion($page, ['locale' => 'en', 'as_draft' => true])
        )->assertCreated();

        $copy = PageBlock::query()->where('uuid', $response->json('block.uuid'))->firstOrFail();
        $this->assertSame($copy->uuid, $copy->translation_key);
        $this->assertNotSame($source->translation_key, $copy->translation_key);
        $this->assertNotSame(
            data_get($source->content, 'rows.0.id'),
            data_get($copy->content, 'rows.0.id')
        );
        $this->assertNotSame(
            data_get($source->content, 'rows.0.columns.0.elements.0.id'),
            data_get($copy->content, 'rows.0.columns.0.elements.0.id')
        );
        $this->assertNotSame(
            data_get($source->content, 'rows.0.columns.0.elements.1.id'),
            data_get($copy->content, 'rows.0.columns.0.elements.1.id')
        );
        $this->assertSame(
            data_get($source->content, 'rows.0.columns.0.elements.0.text'),
            data_get($copy->content, 'rows.0.columns.0.elements.0.text')
        );
        $this->assertFalse($copy->is_enabled);
    }

    public function test_translated_layout_rich_text_uses_the_same_narrow_html_policy(): void
    {
        $rowId = (string) Str::uuid();
        $elementId = (string) Str::uuid();
        $sourcePage = $this->makePage(['name' => 'Rich text translation']);
        $sourceContent = ['rows' => [[
            'id' => $rowId,
            'layout' => 'full',
            'width' => 'standard',
            'background' => 'default',
            'spacing' => 'standard',
            'columns' => [['elements' => [[
                'id' => $elementId,
                'type' => 'rich_text',
                'body' => '<p>Source body</p>',
            ]]]],
        ]]];
        $sourceBlock = $this->makeLayoutBlock($sourcePage, [
            'translation_key' => (string) Str::uuid(),
            'content' => $sourceContent,
        ]);
        $targetPage = $this->makePage([
            'uuid' => $sourcePage->uuid,
            'name' => 'অনুবাদ',
            'slug' => 'rich-text-translation-bn',
            'language' => 'bn',
        ]);
        $targetContent = $sourceContent;
        data_set($targetContent, 'rows.0.columns.0.elements.0.body', '');
        $targetBlock = PageBlock::create([
            'page_id' => $targetPage->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => $sourceBlock->translation_key,
            'type' => 'layout',
            'label' => 'Visual layout',
            'content' => $targetContent,
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);

        $service = app(TranslationCenterService::class);
        $row = $service->rows('en', 'bn')->first(fn (array $candidate): bool =>
            ($candidate['identity']['source_block_id'] ?? null) === $sourceBlock->id
            && ($candidate['identity']['layout_element_id'] ?? null) === $elementId
            && ($candidate['identity']['field'] ?? null) === 'body'
        );
        $this->assertNotNull($row);
        $service->save('en', 'bn', [[
            'key' => $row['key'],
            'precondition' => $row['precondition'],
            'value' => '<div class="rogue"><img src="https://tracker.example/pixel.png"><p id="copy">নিরাপদ <a href="javascript:alert(1)">লিংক</a></p></div>',
        ]], null);

        $body = data_get($targetBlock->fresh()->content, 'rows.0.columns.0.elements.0.body');
        $this->assertSame('<p>নিরাপদ <a>লিংক</a></p>', $body);
        $this->assertStringNotContainsString('tracker.example', $body);
        $this->assertStringNotContainsString('<img', $body);
        $this->assertStringNotContainsString('class=', $body);
        $this->assertStringNotContainsString('id=', $body);
    }

    public function test_translation_copy_follows_stable_element_ids_when_rows_and_columns_are_rearranged(): void
    {
        $rowA = (string) Str::uuid();
        $rowB = (string) Str::uuid();
        $elementA = (string) Str::uuid();
        $elementB = (string) Str::uuid();
        $elementC = (string) Str::uuid();
        $elementD = (string) Str::uuid();
        $sourcePage = $this->makePage(['name' => 'Translation layout']);
        $sourceContent = ['rows' => [
            [
                'id' => $rowA,
                'layout' => 'full',
                'width' => 'standard',
                'background' => 'default',
                'spacing' => 'standard',
                'columns' => [['elements' => [
                    ['id' => $elementA, 'type' => 'heading', 'text' => 'Heading A', 'level' => 'h2'],
                    ['id' => $elementB, 'type' => 'heading', 'text' => 'Heading B', 'level' => 'h3'],
                ]]],
            ],
            [
                'id' => $rowB,
                'layout' => 'full',
                'width' => 'standard',
                'background' => 'soft',
                'spacing' => 'compact',
                'columns' => [['elements' => [
                    ['id' => $elementD, 'type' => 'heading', 'text' => 'Heading D', 'level' => 'h4'],
                ]]],
            ],
        ]];
        $sourceBlock = $this->makeLayoutBlock($sourcePage, [
            'translation_key' => (string) Str::uuid(),
            'content' => $sourceContent,
        ]);
        $targetPage = $this->makePage([
            'uuid' => $sourcePage->uuid,
            'name' => 'অনুবাদ লেআউট',
            'slug' => 'translation-layout-bn',
            'language' => 'bn',
        ]);
        $targetContent = $sourceContent;
        data_set($targetContent, 'rows.0.columns.0.elements.0.text', 'শিরোনাম ক');
        data_set($targetContent, 'rows.0.columns.0.elements.1.text', 'শিরোনাম খ');
        data_set($targetContent, 'rows.1.columns.0.elements.0.text', 'শিরোনাম ঘ');
        PageBlock::create([
            'page_id' => $targetPage->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => $sourceBlock->translation_key,
            'type' => 'layout',
            'label' => 'Visual layout',
            'content' => $targetContent,
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);

        $sourceBlock->update(['content' => ['rows' => [
            [
                'id' => $rowB,
                'layout' => 'full',
                'width' => 'wide',
                'background' => 'accent',
                'spacing' => 'generous',
                'columns' => [['elements' => [
                    ['id' => $elementD, 'type' => 'heading', 'text' => 'Heading D', 'level' => 'h4'],
                ]]],
            ],
            [
                'id' => $rowA,
                'layout' => 'halves',
                'width' => 'full',
                'background' => 'dark',
                'spacing' => 'standard',
                'columns' => [
                    ['elements' => [
                        ['id' => $elementB, 'type' => 'heading', 'text' => 'Heading B', 'level' => 'h3'],
                    ]],
                    ['elements' => [
                        ['id' => $elementC, 'type' => 'heading', 'text' => 'Heading C', 'level' => 'h3'],
                        ['id' => $elementA, 'type' => 'heading', 'text' => 'Heading A', 'level' => 'h2'],
                    ]],
                ],
            ],
        ]]]);

        $service = app(TranslationCenterService::class);
        $rows = $service->rows('en', 'bn')->filter(fn (array $row): bool =>
            ($row['identity']['source_block_id'] ?? null) === $sourceBlock->id
        );
        $byElement = $rows->keyBy('identity.layout_element_id');
        $this->assertSame('শিরোনাম খ', $byElement->get($elementB)['target']);
        $this->assertSame('শিরোনাম ক', $byElement->get($elementA)['target']);
        $this->assertSame('শিরোনাম ঘ', $byElement->get($elementD)['target']);
        $this->assertSame('', $byElement->get($elementC)['target']);

        $rowToSubmit = $byElement->get($elementB);
        $this->assertSame(0, $service->save('en', 'bn', [[
            'key' => $rowToSubmit['key'],
            'precondition' => $rowToSubmit['precondition'],
            'value' => $rowToSubmit['target'],
        ]], null));

        $saved = $targetPage->blocks()->where('translation_key', $sourceBlock->translation_key)->firstOrFail()->content;
        $this->assertSame([$rowB, $rowA], array_column($saved['rows'], 'id'));
        $this->assertSame('accent', data_get($saved, 'rows.0.background'));
        $this->assertSame('wide', data_get($saved, 'rows.0.width'));
        $this->assertSame('halves', data_get($saved, 'rows.1.layout'));
        $this->assertSame('dark', data_get($saved, 'rows.1.background'));
        $this->assertSame($elementB, data_get($saved, 'rows.1.columns.0.elements.0.id'));
        $this->assertSame('শিরোনাম খ', data_get($saved, 'rows.1.columns.0.elements.0.text'));
        $this->assertSame($elementC, data_get($saved, 'rows.1.columns.1.elements.0.id'));
        $this->assertSame('', data_get($saved, 'rows.1.columns.1.elements.0.text'));
        $this->assertSame($elementA, data_get($saved, 'rows.1.columns.1.elements.1.id'));
        $this->assertSame('শিরোনাম ক', data_get($saved, 'rows.1.columns.1.elements.1.text'));

        // A later source edit can contain machine-only changes, so there is no
        // translated cell to submit. Publication sync must still mirror the
        // layout while keeping every translated value attached to its UUID.
        $machineOnlyEdit = $sourceBlock->fresh()->content;
        $rowAContent = $machineOnlyEdit['rows'][1];
        $rowAContent['background'] = 'soft';
        $rowAContent['spacing'] = 'compact';
        $rowAContent['columns'] = [
            ['elements' => [
                ['id' => $elementA, 'type' => 'heading', 'text' => 'Heading A', 'level' => 'h2'],
            ]],
            ['elements' => [
                ['id' => $elementC, 'type' => 'heading', 'text' => 'Heading C', 'level' => 'h3'],
                ['id' => $elementB, 'type' => 'heading', 'text' => 'Heading B', 'level' => 'h3'],
            ]],
        ];
        $sourceBlock->update(['content' => ['rows' => [$rowAContent, $machineOnlyEdit['rows'][0]]]]);

        $service->syncPublicationState('en', 'bn');

        $published = $targetPage->blocks()->where('translation_key', $sourceBlock->translation_key)->firstOrFail()->content;
        $this->assertSame([$rowA, $rowB], array_column($published['rows'], 'id'));
        $this->assertSame('soft', data_get($published, 'rows.0.background'));
        $this->assertSame('compact', data_get($published, 'rows.0.spacing'));
        $this->assertSame($elementA, data_get($published, 'rows.0.columns.0.elements.0.id'));
        $this->assertSame('শিরোনাম ক', data_get($published, 'rows.0.columns.0.elements.0.text'));
        $this->assertSame($elementC, data_get($published, 'rows.0.columns.1.elements.0.id'));
        $this->assertSame('', data_get($published, 'rows.0.columns.1.elements.0.text'));
        $this->assertSame($elementB, data_get($published, 'rows.0.columns.1.elements.1.id'));
        $this->assertSame('শিরোনাম খ', data_get($published, 'rows.0.columns.1.elements.1.text'));
        $this->assertSame('শিরোনাম ঘ', data_get($published, 'rows.1.columns.0.elements.0.text'));
    }

    public function test_layout_copy_is_translatable_while_machine_tokens_and_seo_signals_are_preserved(): void
    {
        $page = $this->makePage(['name' => 'Mission page']);
        $content = $this->layoutContent('full', [[
            'elements' => [
                ['type' => 'heading', 'text' => 'Education for everyone', 'level' => 'h2'],
                ['type' => 'rich_text', 'body' => '<p>Education changes communities.</p>'],
                ['type' => 'image', 'path' => '/storage/media/mission.jpg', 'alt' => 'Students'],
                ['type' => 'button', 'label' => 'Read more', 'url' => '/about-us', 'style' => 'secondary'],
            ],
        ]]);
        $this->makeLayoutBlock($page, ['content' => $content]);

        $translation = app(TranslationCenterService::class)->prepareBlockTranslationContent($content);
        $this->assertSame('full', data_get($translation, 'rows.0.layout'));
        $this->assertSame('standard', data_get($translation, 'rows.0.width'));
        $this->assertSame('h2', data_get($translation, 'rows.0.columns.0.elements.0.level'));
        $this->assertSame('/storage/media/mission.jpg', data_get($translation, 'rows.0.columns.0.elements.2.path'));
        $this->assertSame('secondary', data_get($translation, 'rows.0.columns.0.elements.3.style'));
        $this->assertSame('', data_get($translation, 'rows.0.columns.0.elements.0.text'));
        $this->assertSame('', data_get($translation, 'rows.0.columns.0.elements.1.body'));

        $analysis = app(SeoContentAnalysisService::class)->analyze($page, 'page', 'en', 'education', 'https://igf.test/page/mission');
        $this->assertSame(1, $analysis['h2_count']);
        $this->assertSame(1, $analysis['image_count']);
        $this->assertSame(1, $analysis['images_with_alt']);
        $this->assertSame(1, $analysis['internal_link_count']);
        $this->assertTrue($analysis['focus_in_headings']);
    }

    private function layoutContent(string $preset = 'full', ?array $columns = null): array
    {
        return [
            'rows' => [[
                'layout' => $preset,
                'width' => 'standard',
                'background' => 'default',
                'spacing' => 'standard',
                'columns' => $columns ?? [[
                    'elements' => [[
                        'type' => 'heading',
                        'text' => 'Section heading',
                        'level' => 'h2',
                    ]],
                ]],
            ]],
        ];
    }

    private function makePage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Layout QA page',
            'sub_title' => 'QA subtitle',
            'slug' => 'layout-qa-' . Str::lower(Str::random(8)),
            'status' => 1,
            'language' => 'en',
        ], $overrides));
    }

    private function makeLayoutBlock(Page $page, array $overrides = []): PageBlock
    {
        return PageBlock::create(array_merge([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'type' => 'layout',
            'label' => 'Visual layout',
            'content' => config('page-builder.default_content.layout'),
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ], $overrides));
    }

    private function makeMediaAsset(string $path, string $mime, bool $storeFile = true): MediaAsset
    {
        if ($storeFile) {
            Storage::disk('public')->put($path, 'layout-test-media');
        }

        return MediaAsset::create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => $path,
            'original_name' => basename($path),
            'mime_type' => $mime,
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'bytes' => 1024,
            'locale' => 'en',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function withEditorVersion(Page $page, array $payload): array
    {
        return ['expected_version' => (int) $page->fresh()->editor_version] + $payload;
    }

    private function makeAuthorizedAdmin(): Admin
    {
        $role = Role::create([
            'name' => 'Layout editor',
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
            'name' => 'Layout QA',
            'username' => 'layout-qa',
            'email' => 'layout-qa@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
