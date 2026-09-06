<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\MediaAsset;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageRevision;
use App\Models\ReusableBlock;
use App\Models\Role;
use App\Services\LayoutBlockContentService;
use App\Services\SeoContentAnalysisService;
use App\Services\TranslationCenterService;
use App\Support\PageBuilderElementManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
        $staticTypes = [
            'heading',
            'rich_text',
            'button',
            'icon',
            'divider',
            'spacer',
            'callout',
            'image',
            'video',
            'file',
            'gallery',
            'card',
            'stat',
            'quote',
            'accordion',
            'timeline',
        ];
        $managedTypes = ['content_feed', 'team', 'giving', 'managed_form'];

        $this->assertSame('Visual layout', config('page-builder.block_types.layout'));
        $this->assertSame(2, config('page-builder.layout.schema_version'));
        $this->assertArrayNotHasKey('schema_version', config('page-builder.default_content.layout'));
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
            ->assertJsonPath('block.content.schema_version', 2)
            ->assertJsonPath('block.content.rows.0.layout', 'full')
            ->assertJsonPath('block.content.rows.0.columns.0.elements.0.type', 'heading')
            ->assertJsonPath('block.content.rows.0.columns.0.elements.1.type', 'rich_text');

        $this->assertSame(1, count($response->json('block.content.rows.0.columns')));
        $this->assertTrue(Str::isUuid($response->json('block.content.rows.0.id')));
        $this->assertTrue(Str::isUuid($response->json('block.content.rows.0.columns.0.id')));
        $this->assertTrue(Str::isUuid($response->json('block.content.rows.0.columns.0.elements.0.id')));
        $this->assertTrue(Str::isUuid($response->json('block.content.rows.0.columns.0.elements.1.id')));

        $editor = $this->actingAs($admin, 'admin')->get(route('page.builder.edit', [
            'uuid' => $page->uuid,
            'locale' => 'en',
        ]))->assertOk();
        $layoutOptions = data_get($editor->viewData('blockContentOptions'), 'layout');
        $this->assertSame(
            config('page-builder.layout'),
            array_intersect_key($layoutOptions, config('page-builder.layout'))
        );
        $this->assertSame(PageBuilderElementManifest::VERSION, $layoutOptions['element_catalog_version']);
        $this->assertSame(PageBuilderElementManifest::grouped(), $layoutOptions['element_catalog']);
        $this->assertSame($staticTypes, array_keys(config('page-builder.layout.element_types')));
        $this->assertSame(
            $staticTypes,
            array_keys(array_filter(
                PageBuilderElementManifest::all(),
                fn (array $definition): bool => $definition['mode'] === 'static'
            ))
        );
        foreach ($staticTypes as $type) {
            $this->assertSame('static', PageBuilderElementManifest::get($type)['mode']);
        }
        foreach ($managedTypes as $type) {
            $this->assertSame('managed', PageBuilderElementManifest::get($type)['mode']);
            $this->assertArrayNotHasKey($type, config('page-builder.layout.element_types'));
        }
        $this->assertSame(
            $managedTypes,
            array_column(data_get($layoutOptions, 'element_catalog.website_content.elements', []), 'type')
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
            $this->assertSame(2, $normalized['schema_version']);
            $this->assertCount($preset['columns'], $normalized['rows'][0]['columns']);
            $this->assertTrue(Str::isUuid($normalized['rows'][0]['id']));
            $columnIds = array_column($normalized['rows'][0]['columns'], 'id');
            $this->assertCount($preset['columns'], array_unique($columnIds));
            foreach ($columnIds as $columnId) {
                $this->assertTrue(Str::isUuid($columnId));
            }
            $this->assertSame($normalized, $service->normalizeAndValidate($normalized));
        }
    }

    public function test_unversioned_and_v1_layouts_are_accepted_and_normalized_to_v2(): void
    {
        $service = app(LayoutBlockContentService::class);
        $rowId = (string) Str::uuid();
        $elementId = (string) Str::uuid();

        foreach ([null, 1] as $legacyVersion) {
            $legacy = $this->layoutContent('halves', [
                ['elements' => [[
                    'id' => $elementId,
                    'type' => 'heading',
                    'text' => '  Existing authored heading  ',
                    'level' => 'h3',
                ]]],
                ['elements' => [[
                    'type' => 'rich_text',
                    'body' => '<p>Existing <strong>copy</strong>.</p>',
                ], [
                    'type' => 'accordion',
                    'items' => [[
                        'question' => 'Existing question',
                        'answer' => '<p>Existing answer.</p>',
                    ]],
                    'allow_one_open' => true,
                ]]],
            ]);
            $legacy['rows'][0]['id'] = $rowId;
            if ($legacyVersion !== null) {
                $legacy['schema_version'] = $legacyVersion;
            }

            $normalized = $service->normalizeAndValidate($legacy);

            $this->assertSame(2, $normalized['schema_version']);
            $this->assertSame($rowId, data_get($normalized, 'rows.0.id'));
            $this->assertSame($elementId, data_get($normalized, 'rows.0.columns.0.elements.0.id'));
            $this->assertSame('Existing authored heading', data_get($normalized, 'rows.0.columns.0.elements.0.text'));
            $this->assertSame('<p>Existing <strong>copy</strong>.</p>', data_get($normalized, 'rows.0.columns.1.elements.0.body'));
            $this->assertTrue(Str::isUuid(data_get($normalized, 'rows.0.columns.0.id')));
            $this->assertTrue(Str::isUuid(data_get($normalized, 'rows.0.columns.1.id')));
            $this->assertTrue(Str::isUuid(data_get($normalized, 'rows.0.columns.1.elements.0.id')));
            $this->assertTrue(Str::isUuid(data_get($normalized, 'rows.0.columns.1.elements.1.id')));
            $this->assertTrue(Str::isUuid(data_get($normalized, 'rows.0.columns.1.elements.1.items.0.id')));
            $this->assertSame($normalized, $service->normalizeAndValidate($normalized));
        }
    }

    public function test_v2_layouts_require_valid_stable_ids_at_every_identity_level(): void
    {
        $service = app(LayoutBlockContentService::class);
        $valid = $service->normalizeAndValidate($this->layoutContent('full', [[
            'elements' => [[
                'type' => 'accordion',
                'items' => [[
                    'question' => 'Who can join?',
                    'answer' => '<p>Everyone is welcome.</p>',
                ]],
                'allow_one_open' => true,
            ]],
        ]]));

        $identityPaths = [
            'rows.0.id',
            'rows.0.columns.0.id',
            'rows.0.columns.0.elements.0.id',
            'rows.0.columns.0.elements.0.items.0.id',
        ];

        foreach ($identityPaths as $identityPath) {
            foreach (['missing', null, '', 'not-a-uuid', 123, '00000000-0000-0000-0000-000000000000'] as $badIdentity) {
                $content = $valid;
                if ($badIdentity === 'missing') {
                    data_forget($content, $identityPath);
                } else {
                    data_set($content, $identityPath, $badIdentity);
                }

                try {
                    $service->normalizeAndValidate($content);
                    $this->fail("Expected schema-version 2 to reject {$identityPath} ({$badIdentity}).");
                } catch (ValidationException $exception) {
                    $errorPath = 'content.' . $identityPath;
                    $this->assertArrayHasKey($errorPath, $exception->errors());
                    $this->assertStringContainsString('identity', $exception->errors()[$errorPath][0]);
                }
            }
        }

        foreach (['2', 2.0] as $equivalentV2) {
            $content = $valid;
            $content['schema_version'] = $equivalentV2;
            data_forget($content, 'rows.0.id');

            try {
                $service->normalizeAndValidate($content);
                $this->fail('Expected every accepted representation of schema version 2 to require identities.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('content.rows.0.id', $exception->errors());
            }
        }
    }

    public function test_legacy_null_and_empty_identities_upgrade_but_invalid_ids_do_not(): void
    {
        $service = app(LayoutBlockContentService::class);
        $valid = $this->versionTwoLayoutWithRepeater();
        $identityPaths = [
            'rows.0.id',
            'rows.0.columns.0.id',
            'rows.0.columns.0.elements.0.id',
            'rows.0.columns.0.elements.1.items.0.id',
        ];

        foreach ($identityPaths as $identityPath) {
            foreach ([null, ''] as $missingIdentity) {
                $legacy = $valid;
                $legacy['schema_version'] = 1;
                data_set($legacy, $identityPath, $missingIdentity);

                $normalized = $service->normalizeAndValidate($legacy);
                $this->assertSame(2, $normalized['schema_version']);
                $this->assertMatchesRegularExpression(
                    '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/iD',
                    data_get($normalized, $identityPath)
                );
            }

            $invalid = $valid;
            $invalid['schema_version'] = 1;
            data_set($invalid, $identityPath, '00000000-0000-0000-0000-000000000000');
            try {
                $service->normalizeAndValidate($invalid);
                $this->fail("Expected legacy {$identityPath} with an invalid UUID variant to be rejected.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('content.' . $identityPath, $exception->errors());
            }
        }
    }

    public function test_page_update_cannot_hide_corrupt_stored_v2_identities_with_a_repaired_payload(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $valid = $this->versionTwoLayoutWithRepeater();
        $incoming = $valid;
        data_set($incoming, 'rows.0.columns.0.elements.0.text', 'Repaired editor copy');

        $corruptions = [
            'missing row identity' => [
                function (array $content): array {
                    data_forget($content, 'rows.0.id');

                    return $content;
                },
                'content.rows.0.id',
            ],
            'invalid column identity' => [
                function (array $content): array {
                    data_set($content, 'rows.0.columns.0.id', 'not-a-uuid');

                    return $content;
                },
                'content.rows.0.columns.0.id',
            ],
            'duplicate element identity' => [
                function (array $content): array {
                    data_set(
                        $content,
                        'rows.0.columns.0.elements.1.id',
                        data_get($content, 'rows.0.columns.0.elements.0.id')
                    );

                    return $content;
                },
                'content.rows.0.columns.0.elements.1.id',
            ],
            'missing repeater identity' => [
                function (array $content): array {
                    data_forget($content, 'rows.0.columns.0.elements.1.items.0.id');

                    return $content;
                },
                'content.rows.0.columns.0.elements.1.items.0.id',
            ],
        ];

        foreach ($corruptions as $case => [$corrupt, $errorPath]) {
            $page = $this->makePage(['name' => 'Stored identity guard: '.$case]);
            $stored = $corrupt($valid);
            $block = $this->makeLayoutBlock($page, ['content' => $stored]);

            $this->actingAs($admin, 'admin')->putJson(
                route('page.builder.block.update', [$page->uuid, $block->uuid]),
                $this->withEditorVersion($page, ['locale' => 'en', 'content' => $incoming])
            )->assertUnprocessable()->assertJsonValidationErrors($errorPath);

            $this->assertSame($stored, $block->fresh()->content, $case);
            $this->assertSame(0, (int) $page->fresh()->editor_version, $case);
        }
    }

    public function test_simple_editor_batch_save_cannot_hide_a_corrupt_stored_v2_identity(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $valid = $this->versionTwoLayoutWithRepeater();
        $stored = $valid;
        data_forget($stored, 'rows.0.id');
        $incoming = $valid;
        data_set($incoming, 'rows.0.columns.0.elements.0.text', 'Apparently repaired in the browser');
        $page = $this->makePage(['name' => 'Simple editor stored identity guard']);
        $block = $this->makeLayoutBlock($page, ['content' => $stored]);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.simple.save', $page->uuid),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'blocks' => [[
                    'uuid' => $block->uuid,
                    'label' => $block->label,
                    'content' => $incoming,
                    'is_enabled' => true,
                ]],
            ])
        )->assertUnprocessable()->assertJsonValidationErrors(
            'blocks.' . $block->uuid . '.content.rows.0.id'
        );

        $this->assertSame($stored, $block->fresh()->content);
        $this->assertSame(0, (int) $page->fresh()->editor_version);
    }

    public function test_reusable_update_cannot_hide_corrupt_stored_v2_identities_with_a_repaired_payload(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $valid = $this->versionTwoLayoutWithRepeater();
        $stored = $valid;
        data_forget($stored, 'rows.0.columns.0.elements.1.items.0.id');
        $incoming = $valid;
        data_set($incoming, 'rows.0.columns.0.elements.1.items.0.question', 'Can repaired content save?');
        $reusable = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Corrupt shared visual layout',
            'type' => 'layout',
            'locale' => 'en',
            'content' => $stored,
            'settings' => [],
            'is_enabled' => true,
        ]);
        $payload = [
            'expected_version' => 0,
            'name' => $reusable->name,
            'locale' => 'en',
            'content' => $incoming,
            'settings' => [],
            'is_enabled' => true,
        ];

        $this->actingAs($admin, 'admin')->putJson(
            route('reusable-blocks.update', $reusable),
            ['expected_version' => 99] + collect($payload)->except('expected_version')->all()
        )->assertStatus(409);

        $this->actingAs($admin, 'admin')->putJson(route('reusable-blocks.update', $reusable), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.rows.0.columns.0.elements.1.items.0.id');

        $this->assertSame($stored, $reusable->fresh()->content);
        $this->assertSame(0, (int) $reusable->fresh()->editor_version);
    }

    public function test_page_update_guard_checks_the_locked_shared_layout_as_effective_content(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $valid = $this->versionTwoLayoutWithRepeater();
        $stored = $valid;
        data_set(
            $stored,
            'rows.0.columns.0.elements.1.id',
            data_get($stored, 'rows.0.columns.0.elements.0.id')
        );
        $reusable = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Corrupt effective layout',
            'type' => 'layout',
            'locale' => 'en',
            'content' => $stored,
            'settings' => [],
            'is_enabled' => true,
        ]);
        $page = $this->makePage(['name' => 'Shared stored identity guard']);
        $pageBlock = $this->makeLayoutBlock($page, [
            'reusable_block_id' => $reusable->id,
            // A reusable instance may retain an old local copy. The locked
            // library row above is the content visitors and editors resolve.
            'content' => $valid,
        ]);
        $incoming = $valid;
        data_set($incoming, 'rows.0.columns.0.elements.0.text', 'Apparently repaired shared copy');

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $pageBlock->uuid]),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'expected_reusable_version' => 0,
                'content' => $incoming,
            ])
        )->assertUnprocessable()
            ->assertJsonValidationErrors('content.rows.0.columns.0.elements.1.id');

        $this->assertSame($stored, $reusable->fresh()->content);
        $this->assertSame($valid, $pageBlock->fresh()->content);
        $this->assertSame(0, (int) $page->fresh()->editor_version);
    }

    public function test_current_layout_rejects_a_stale_legacy_replacement_in_every_editor(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $current = $this->versionTwoLayoutWithRepeater();
        $legacy = $this->layoutContent();
        data_set($legacy, 'rows.0.columns.0.elements.0.text', 'Stale browser copy');

        $singlePage = $this->makePage(['name' => 'Single stale layout guard']);
        $singleBlock = $this->makeLayoutBlock($singlePage, ['content' => $current]);
        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$singlePage->uuid, $singleBlock->uuid]),
            $this->withEditorVersion($singlePage, ['locale' => 'en', 'content' => $legacy])
        )->assertUnprocessable()->assertJsonValidationErrors('content.schema_version');
        $this->assertSame($current, $singleBlock->fresh()->content);
        $this->assertSame(0, (int) $singlePage->fresh()->editor_version);

        $batchPage = $this->makePage(['name' => 'Batch stale layout guard']);
        $batchBlock = $this->makeLayoutBlock($batchPage, ['content' => $current]);
        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.simple.save', $batchPage->uuid),
            $this->withEditorVersion($batchPage, [
                'locale' => 'en',
                'blocks' => [[
                    'uuid' => $batchBlock->uuid,
                    'label' => $batchBlock->label,
                    'content' => $legacy,
                    'is_enabled' => true,
                ]],
            ])
        )->assertUnprocessable()->assertJsonValidationErrors(
            'blocks.' . $batchBlock->uuid . '.content.schema_version'
        );
        $this->assertSame($current, $batchBlock->fresh()->content);
        $this->assertSame(0, (int) $batchPage->fresh()->editor_version);

        $reusable = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Reusable stale layout guard',
            'type' => 'layout',
            'locale' => 'en',
            'content' => $current,
            'settings' => [],
            'is_enabled' => true,
        ]);
        $this->actingAs($admin, 'admin')->putJson(route('reusable-blocks.update', $reusable), [
            'expected_version' => 0,
            'name' => $reusable->name,
            'locale' => 'en',
            'content' => $legacy,
            'settings' => [],
            'is_enabled' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('content.schema_version');
        $this->assertSame($current, $reusable->fresh()->content);
        $this->assertSame(0, (int) $reusable->fresh()->editor_version);
    }

    public function test_stored_future_layout_schema_fails_closed_without_changing_content(): void
    {
        $future = $this->versionTwoLayoutWithRepeater();
        $future['schema_version'] = LayoutBlockContentService::CURRENT_SCHEMA_VERSION + 1;

        try {
            app(LayoutBlockContentService::class)->assertStoredVersionTwoIsValid($future);
            $this->fail('A future stored layout schema must not be treated as legacy content.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('content.schema_version', $exception->errors());
        }

        $admin = $this->makeAuthorizedAdmin();
        $page = $this->makePage(['name' => 'Future layout guard']);
        $block = $this->makeLayoutBlock($page, ['content' => $future]);
        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block->uuid]),
            $this->withEditorVersion($page, [
                'locale' => 'en',
                'content' => $this->versionTwoLayoutWithRepeater(),
            ])
        )->assertUnprocessable()->assertJsonValidationErrors('content.schema_version');

        $this->assertSame($future, $block->fresh()->content);
        $this->assertSame(0, (int) $page->fresh()->editor_version);
    }

    public function test_stored_guard_rejects_malformed_legacy_nesting_and_unsupported_types(): void
    {
        $service = app(LayoutBlockContentService::class);
        $base = $this->layoutContent();
        $cases = [
            'null declared schema' => [
                function (array $content): array {
                    $content['schema_version'] = null;

                    return $content;
                },
                'content.schema_version',
            ],
            'string declared schema' => [
                function (array $content): array {
                    $content['schema_version'] = '2';

                    return $content;
                },
                'content.schema_version',
            ],
            'rows list' => [
                function (array $content): array {
                    $content['rows'] = 'damaged';

                    return $content;
                },
                'content.rows',
            ],
            'row object' => [
                function (array $content): array {
                    $content['rows'][0] = 'damaged';

                    return $content;
                },
                'content.rows.0',
            ],
            'columns list' => [
                function (array $content): array {
                    $content['rows'][0]['columns'] = 'damaged';

                    return $content;
                },
                'content.rows.0.columns',
            ],
            'column object' => [
                function (array $content): array {
                    $content['rows'][0]['columns'][0] = 'damaged';

                    return $content;
                },
                'content.rows.0.columns.0',
            ],
            'elements list' => [
                function (array $content): array {
                    $content['rows'][0]['columns'][0]['elements'] = 'damaged';

                    return $content;
                },
                'content.rows.0.columns.0.elements',
            ],
            'element object' => [
                function (array $content): array {
                    $content['rows'][0]['columns'][0]['elements'][0] = 'damaged';

                    return $content;
                },
                'content.rows.0.columns.0.elements.0',
            ],
            'unsupported element' => [
                function (array $content): array {
                    $content['rows'][0]['columns'][0]['elements'][0] = ['type' => 'future_widget'];

                    return $content;
                },
                'content.rows.0.columns.0.elements.0.type',
            ],
            'repeater list' => [
                function (array $content): array {
                    $content['rows'][0]['columns'][0]['elements'][0] = [
                        'type' => 'accordion',
                        'items' => 'damaged',
                        'allow_one_open' => true,
                    ];

                    return $content;
                },
                'content.rows.0.columns.0.elements.0.items',
            ],
        ];

        foreach ($cases as $case => [$damage, $errorPath]) {
            $stored = $damage($base);
            $before = json_encode($stored, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            try {
                $service->assertStoredVersionTwoIsValid($stored);
                $this->fail("Expected malformed stored {$case} to be rejected.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($errorPath, $exception->errors(), $case);
            }
            $this->assertSame(
                $before,
                json_encode($stored, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $case
            );
        }
    }

    public function test_stored_legacy_layouts_can_still_upgrade_through_page_and_reusable_updates(): void
    {
        $admin = $this->makeAuthorizedAdmin();

        $page = $this->makePage(['name' => 'Unversioned layout upgrade']);
        $unversioned = $this->layoutContent();
        $pageBlock = $this->makeLayoutBlock($page, ['content' => $unversioned]);
        $pageIncoming = $unversioned;
        data_set($pageIncoming, 'rows.0.columns.0.elements.0.text', 'Upgraded page layout');

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $pageBlock->uuid]),
            $this->withEditorVersion($page, ['locale' => 'en', 'content' => $pageIncoming])
        )->assertOk()
            ->assertJsonPath('block.content.schema_version', 2)
            ->assertJsonPath('block.content.rows.0.columns.0.elements.0.text', 'Upgraded page layout');

        $this->assertTrue(Str::isUuid(data_get($pageBlock->fresh()->content, 'rows.0.id')));

        $versionOne = $this->layoutContent();
        $versionOne['schema_version'] = 1;
        $reusable = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Version one shared layout',
            'type' => 'layout',
            'locale' => 'en',
            'content' => $versionOne,
            'settings' => [],
            'is_enabled' => true,
        ]);
        $reusableIncoming = $versionOne;
        data_set($reusableIncoming, 'rows.0.columns.0.elements.0.text', 'Upgraded shared layout');

        $this->actingAs($admin, 'admin')->putJson(route('reusable-blocks.update', $reusable), [
            'expected_version' => 0,
            'name' => $reusable->name,
            'locale' => 'en',
            'content' => $reusableIncoming,
            'settings' => [],
            'is_enabled' => true,
        ])->assertOk()
            ->assertJsonPath('block.content.schema_version', 2)
            ->assertJsonPath('block.content.rows.0.columns.0.elements.0.text', 'Upgraded shared layout');

        $this->assertTrue(Str::isUuid(data_get($reusable->fresh()->content, 'rows.0.id')));
    }

    public function test_target_locale_builder_can_replace_a_blank_version_two_translation_draft(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $layoutService = app(LayoutBlockContentService::class);
        $sourceContent = $layoutService->normalizeAndValidate($this->layoutContent());
        $draft = app(TranslationCenterService::class)->prepareBlockTranslationContent($sourceContent);
        $this->assertSame(2, $draft['schema_version']);
        $this->assertSame('', data_get($draft, 'rows.0.columns.0.elements.0.text'));

        $sourcePage = $this->makePage(['name' => 'Translated builder source']);
        $translationKey = (string) Str::uuid();
        $this->makeLayoutBlock($sourcePage, [
            'translation_key' => $translationKey,
            'content' => $sourceContent,
        ]);
        $targetPage = $this->makePage([
            'uuid' => $sourcePage->uuid,
            'name' => 'অনুবাদ বিল্ডার',
            'slug' => 'translated-builder-bn',
            'language' => 'bn',
        ]);
        $targetBlock = $this->makeLayoutBlock($targetPage, [
            'translation_key' => $translationKey,
            'content' => $draft,
        ]);
        $incoming = $draft;
        data_set($incoming, 'rows.0.columns.0.elements.0.text', 'বাংলা অংশের শিরোনাম');

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$targetPage->uuid, $targetBlock->uuid]),
            $this->withEditorVersion($targetPage, ['locale' => 'bn', 'content' => $incoming])
        )->assertOk()
            ->assertJsonPath(
                'block.content.rows.0.columns.0.elements.0.text',
                'বাংলা অংশের শিরোনাম'
            );

        $this->assertSame(
            'বাংলা অংশের শিরোনাম',
            data_get($targetBlock->fresh()->content, 'rows.0.columns.0.elements.0.text')
        );
        $this->assertSame(
            data_get($draft, 'rows.0.columns.0.elements.0.id'),
            data_get($targetBlock->fresh()->content, 'rows.0.columns.0.elements.0.id')
        );
    }

    public function test_layout_copy_and_library_link_actions_reject_unsafe_saved_structures(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $valid = $this->versionTwoLayoutWithRepeater();

        $duplicatePage = $this->makePage(['name' => 'Unsafe layout duplicate']);
        $missingIdentity = $valid;
        data_forget($missingIdentity, 'rows.0.columns.0.elements.0.id');
        $duplicateSource = $this->makeLayoutBlock($duplicatePage, ['content' => $missingIdentity]);
        $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.duplicate', [$duplicatePage->uuid, $duplicateSource->uuid]),
            $this->withEditorVersion($duplicatePage, ['locale' => 'en'])
        )->assertUnprocessable()
            ->assertJsonValidationErrors('content.rows.0.columns.0.elements.0.id');
        $this->assertSame(1, $duplicatePage->blocks()->count());
        $this->assertSame($missingIdentity, $duplicateSource->fresh()->content);
        $this->assertSame(0, (int) $duplicatePage->fresh()->editor_version);
        $this->assertSame(0, PageRevision::where('page_id', $duplicatePage->id)->count());

        $promotePage = $this->makePage(['name' => 'Unsafe layout promotion']);
        $future = $valid;
        $future['schema_version'] = LayoutBlockContentService::CURRENT_SCHEMA_VERSION + 1;
        $promoteSource = $this->makeLayoutBlock($promotePage, ['content' => $future]);
        $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.promote', [$promotePage->uuid, $promoteSource->uuid]),
            $this->withEditorVersion($promotePage, [
                'locale' => 'en',
                'name' => 'Unsafe promoted layout',
                'library_locale' => 'en',
            ])
        )->assertUnprocessable()->assertJsonValidationErrors('content.schema_version');
        $this->assertDatabaseMissing('reusable_blocks', ['name' => 'Unsafe promoted layout']);
        $this->assertNull($promoteSource->fresh()->reusable_block_id);
        $this->assertSame($future, $promoteSource->fresh()->content);
        $this->assertSame(0, (int) $promotePage->fresh()->editor_version);

        $attachPage = $this->makePage(['name' => 'Unsafe reusable attachment']);
        $malformedLegacy = $this->layoutContent();
        $malformedLegacy['rows'][0]['columns'][0]['elements'][0] = [
            'type' => 'accordion',
            'items' => 'damaged-list',
            'allow_one_open' => true,
        ];
        $attachReusable = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Malformed legacy reusable layout',
            'type' => 'layout',
            'locale' => 'en',
            'content' => $malformedLegacy,
            'settings' => [],
            'is_enabled' => true,
        ]);
        $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.reusable.attach', $attachPage->uuid),
            $this->withEditorVersion($attachPage, [
                'locale' => 'en',
                'reusable_uuid' => $attachReusable->uuid,
            ])
        )->assertUnprocessable()
            ->assertJsonValidationErrors('content.rows.0.columns.0.elements.0.items');
        $this->assertSame(0, $attachPage->blocks()->count());
        $this->assertSame($malformedLegacy, $attachReusable->fresh()->content);
        $this->assertSame(0, (int) $attachPage->fresh()->editor_version);

        $detachPage = $this->makePage(['name' => 'Unsafe reusable detachment']);
        $detachReusable = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Future reusable layout',
            'type' => 'layout',
            'locale' => 'en',
            'content' => $future,
            'settings' => [],
            'is_enabled' => true,
        ]);
        $placementContent = $valid;
        $placement = $this->makeLayoutBlock($detachPage, [
            'reusable_block_id' => $detachReusable->id,
            'content' => $placementContent,
        ]);
        $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.detach', [$detachPage->uuid, $placement->uuid]),
            $this->withEditorVersion($detachPage, ['locale' => 'en'])
        )->assertUnprocessable()->assertJsonValidationErrors('content.schema_version');
        $this->assertSame($detachReusable->id, $placement->fresh()->reusable_block_id);
        $this->assertSame($placementContent, $placement->fresh()->content);
        $this->assertSame($future, $detachReusable->fresh()->content);
        $this->assertSame(0, (int) $detachPage->fresh()->editor_version);
    }

    public function test_linked_layout_duplicate_validates_locked_reusable_content_instead_of_cached_placement(): void
    {
        $admin = $this->makeAuthorizedAdmin();
        $validPlacementCache = $this->versionTwoLayoutWithRepeater();
        $damaged = $validPlacementCache;
        data_forget($damaged, 'rows.0.columns.0.elements.0.id');
        $future = $validPlacementCache;
        $future['schema_version'] = LayoutBlockContentService::CURRENT_SCHEMA_VERSION + 1;

        foreach ([
            'damaged' => [$damaged, 'content.rows.0.columns.0.elements.0.id'],
            'future' => [$future, 'content.schema_version'],
        ] as $case => [$unsafeReusableContent, $errorPath]) {
            $page = $this->makePage(['name' => "Linked {$case} layout duplicate"]);
            $reusable = ReusableBlock::create([
                'uuid' => (string) Str::uuid(),
                'name' => "Linked {$case} layout",
                'type' => 'layout',
                'locale' => 'en',
                'content' => $unsafeReusableContent,
                'settings' => [],
                'is_enabled' => true,
            ]);
            $placement = $this->makeLayoutBlock($page, [
                'reusable_block_id' => $reusable->id,
                'content' => $validPlacementCache,
            ]);

            $this->actingAs($admin, 'admin')->postJson(
                route('page.builder.block.duplicate', [$page->uuid, $placement->uuid]),
                $this->withEditorVersion($page, ['locale' => 'en'])
            )->assertUnprocessable()->assertJsonValidationErrors($errorPath);

            $this->assertSame(1, $page->blocks()->count());
            $this->assertSame($validPlacementCache, $placement->fresh()->content);
            $this->assertSame($unsafeReusableContent, $reusable->fresh()->content);
            $this->assertSame(0, (int) $page->fresh()->editor_version);
            $this->assertSame(0, PageRevision::where('page_id', $page->id)->count());
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

    public function test_all_safe_static_elements_validate_normalize_and_round_trip_without_losing_content(): void
    {
        $this->makeMediaAsset('media/2026/09/card.jpg', 'image/jpeg');
        $this->makeMediaAsset('media/2026/09/gallery-a.jpg', 'image/jpeg');
        $this->makeMediaAsset('media/2026/09/gallery-b.webp', 'image/webp');
        $this->makeMediaAsset('media/2026/09/portrait.png', 'image/png');
        $this->makeMediaAsset('media/2026/09/report.pdf', 'application/pdf');

        $content = $this->layoutContent('full', [[
            'elements' => [
                [
                    'type' => 'icon',
                    'icon' => 'heart',
                    'accessible_label' => '  Community support  ',
                    'decorative' => false,
                    'size' => 'large',
                    'style' => 'circle',
                ],
                [
                    'type' => 'file',
                    'path' => '/storage/media/2026/09/report.pdf',
                    'label' => '  Download our report  ',
                    'description' => '  Audited annual report  ',
                    'open_in_new_tab' => true,
                ],
                [
                    'type' => 'card',
                    'eyebrow' => '  Featured  ',
                    'heading' => '  <b>Our work</b>  ',
                    'body' => '<strong>Community-led programs</strong>',
                    'image' => '/storage/media/2026/09/card.jpg',
                    'image_alt' => '  Volunteers working together  ',
                    'icon' => '',
                    'link_label' => '  Read more  ',
                    'url' => '/our-work',
                    'style' => 'soft',
                ],
                [
                    'type' => 'stat',
                    'value' => '<b>1,200+</b>',
                    'label' => '  People reached  ',
                    'icon' => 'people',
                    'emphasis' => 'accent',
                ],
                [
                    'type' => 'quote',
                    'quote' => '  <em>Together we can do more.</em>  ',
                    'attribution' => '  Amina Rahman  ',
                    'role' => '  Community leader  ',
                    'image' => '/storage/media/2026/09/portrait.png',
                    'image_alt' => '  Portrait of Amina  ',
                    'style' => 'featured',
                ],
                [
                    'type' => 'gallery',
                    'items' => [
                        [
                            'path' => '/storage/media/2026/09/gallery-a.jpg',
                            'alt' => '  A learning session  ',
                            'caption' => '  Learning together  ',
                        ],
                        [
                            'path' => '/storage/media/2026/09/gallery-b.webp',
                            'alt' => '  A community meeting  ',
                            'caption' => '  Planning together  ',
                        ],
                    ],
                    'columns' => '3',
                    'lightbox' => true,
                ],
                [
                    'type' => 'accordion',
                    'items' => [
                        [
                            'question' => '  Who can join?  ',
                            'answer' => '<div class="rogue"><p>Everyone <strong>is welcome</strong>.</p><script>bad()</script></div>',
                        ],
                        [
                            'question' => '  How can I help?  ',
                            'answer' => '<p>You can volunteer.</p>',
                        ],
                    ],
                    'allow_one_open' => true,
                ],
                [
                    'type' => 'timeline',
                    'items' => [
                        [
                            'date_label' => '  2025  ',
                            'heading' => '  <b>Listening</b>  ',
                            'body' => '  <em>Community consultation</em>  ',
                            'icon' => 'people',
                        ],
                        [
                            'date_label' => '  2026  ',
                            'heading' => '  Acting  ',
                            'body' => '  Programs begin  ',
                            'icon' => 'heart',
                        ],
                    ],
                    'style' => 'steps',
                ],
                [
                    'type' => 'callout',
                    'eyebrow' => '  Important  ',
                    'heading' => '  Get involved  ',
                    'body' => '<section><p>Join <strong>our community</strong>.</p><img src="https://tracker.test/pixel.png"></section>',
                    'icon' => 'health',
                    'tone' => 'warning',
                    'link_label' => '  See details  ',
                    'url' => '#details',
                ],
            ],
        ]]);

        $service = app(LayoutBlockContentService::class);
        $normalized = $service->normalizeAndValidate($content);
        $elements = data_get($normalized, 'rows.0.columns.0.elements');

        $this->assertSame(2, $normalized['schema_version']);
        $this->assertSame(
            ['icon', 'file', 'card', 'stat', 'quote', 'gallery', 'accordion', 'timeline', 'callout'],
            array_column($elements, 'type')
        );
        $this->assertSame('Community support', data_get($elements, '0.accessible_label'));
        $this->assertFalse(data_get($elements, '0.decorative'));
        $this->assertSame('/storage/media/2026/09/report.pdf', data_get($elements, '1.path'));
        $this->assertSame('Download our report', data_get($elements, '1.label'));
        $this->assertSame('Our work', data_get($elements, '2.heading'));
        $this->assertSame('Community-led programs', data_get($elements, '2.body'));
        $this->assertSame('1,200+', data_get($elements, '3.value'));
        $this->assertSame('Together we can do more.', data_get($elements, '4.quote'));
        $this->assertSame('A learning session', data_get($elements, '5.items.0.alt'));
        $this->assertSame(
            '<p>Everyone <strong>is welcome</strong>.</p>',
            data_get($elements, '6.items.0.answer')
        );
        $this->assertSame('Listening', data_get($elements, '7.items.0.heading'));
        $this->assertSame('Community consultation', data_get($elements, '7.items.0.body'));
        $this->assertSame(
            '<p>Join <strong>our community</strong>.</p>',
            data_get($elements, '8.body')
        );

        $elementIds = array_column($elements, 'id');
        $this->assertCount(count($elementIds), array_unique($elementIds));
        foreach ($elementIds as $elementId) {
            $this->assertTrue(Str::isUuid($elementId));
        }
        $itemIds = collect([$elements[5], $elements[6], $elements[7]])
            ->flatMap(fn (array $element): array => array_column($element['items'], 'id'))
            ->all();
        $this->assertCount(6, $itemIds);
        $this->assertCount(6, array_unique($itemIds));
        foreach ($itemIds as $itemId) {
            $this->assertTrue(Str::isUuid($itemId));
        }
        $this->assertSame($normalized, $service->normalizeAndValidate($normalized));
    }

    public function test_static_element_contract_rejects_managed_unsafe_spoofed_and_unbounded_values(): void
    {
        $this->makeMediaAsset('media/2026/09/security.jpg', 'image/jpeg');
        $this->makeMediaAsset('media/2026/09/not-a-document.pdf', 'image/jpeg');
        $itemId = (string) Str::uuid();
        $galleryItem = [
            'path' => '/storage/media/2026/09/security.jpg',
            'alt' => 'Safe image',
            'caption' => '',
        ];
        $wrap = fn (array $elements): array => $this->layoutContent('full', [['elements' => $elements]]);
        $unsupportedDesignToken = $wrap([['type' => 'divider']]);
        $unsupportedDesignToken['section_spacing'] = 'arbitrary-css-token';

        $cases = [
            [$unsupportedDesignToken, 'content.section_spacing'],
            [$wrap([[
                'type' => 'card',
                'heading' => 'Unsafe card',
                'body' => '',
                'image' => '',
                'image_alt' => '',
                'icon' => '',
                'link_label' => 'Open',
                'url' => 'javascript:alert(1)',
                'style' => 'standard',
            ]]), 'content.rows.0.columns.0.elements.0.url'],
            [$wrap([[
                'type' => 'icon',
                'icon' => 'heart',
                'accessible_label' => '',
                'decorative' => false,
                'size' => 'medium',
                'style' => 'plain',
            ]]), 'content.rows.0.columns.0.elements.0.accessible_label'],
            [$wrap([[
                'type' => 'icon',
                'icon' => 'unapproved-token',
                'accessible_label' => '',
                'decorative' => true,
                'size' => 'medium',
                'style' => 'plain',
            ]]), 'content.rows.0.columns.0.elements.0.icon'],
            [$wrap([[
                'type' => 'file',
                'path' => '/storage/media/2026/09/not-a-document.pdf',
                'label' => 'Spoofed report',
                'description' => '',
                'open_in_new_tab' => false,
            ]]), 'content.rows.0.columns.0.elements.0.path'],
            [$wrap([[
                'type' => 'gallery',
                'items' => [[...$galleryItem, 'custom_html' => '<script>bad()</script>']],
                'columns' => '3',
                'lightbox' => true,
            ]]), 'content.rows.0.columns.0.elements.0.items.0.custom_html'],
            [$wrap([[
                'type' => 'gallery',
                'items' => ['first' => $galleryItem],
                'columns' => '3',
                'lightbox' => true,
            ]]), 'content.rows.0.columns.0.elements.0.items'],
            [$wrap([[
                'type' => 'gallery',
                'items' => array_fill(0, 13, $galleryItem),
                'columns' => '3',
                'lightbox' => true,
            ]]), 'content.rows.0.columns.0.elements.0.items'],
            [$wrap([
                [
                    'type' => 'gallery',
                    'items' => [[...$galleryItem, 'id' => $itemId]],
                    'columns' => '3',
                    'lightbox' => true,
                ],
                [
                    'type' => 'accordion',
                    'items' => [[
                        'id' => $itemId,
                        'question' => 'Duplicate identity',
                        'answer' => '',
                    ]],
                    'allow_one_open' => false,
                ],
            ]), 'content.rows.0.columns.0.elements.1.items.0.id'],
            [$wrap(array_fill(0, 4, [
                'type' => 'gallery',
                'items' => [],
                'columns' => '3',
                'lightbox' => true,
            ])), 'content.rows.0.columns.0.elements.3.type'],
            [$wrap([[
                'type' => 'file',
                'path' => '/storage/private/report.pdf',
                'label' => 'Private report',
                'description' => '',
                'open_in_new_tab' => false,
            ]]), 'content.rows.0.columns.0.elements.0.path'],
            [$wrap([[
                'type' => 'gallery',
                'items' => [],
                'columns' => '3',
                'lightbox' => 'yes',
            ]]), 'content.rows.0.columns.0.elements.0.lightbox'],
        ];
        foreach (['content_feed', 'team', 'giving', 'managed_form'] as $managedType) {
            $cases[] = [
                $wrap([['type' => $managedType]]),
                'content.rows.0.columns.0.elements.0.type',
            ];
        }

        $service = app(LayoutBlockContentService::class);
        foreach ($cases as [$content, $errorPath]) {
            try {
                $service->normalizeAndValidate($content);
                $this->fail("Expected visual-layout validation to reject {$errorPath}.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($errorPath, $exception->errors());
            }
        }
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
        $unsupportedSchema = $this->layoutContent();
        $unsupportedSchema['schema_version'] = 3;
        $unknownRootKey = $this->layoutContent();
        $unknownRootKey['custom_css'] = '.unsafe{}';
        $duplicateRowId = (string) Str::uuid();
        $duplicateRows = $this->layoutContent();
        $duplicateRows['rows'][0]['id'] = $duplicateRowId;
        $duplicateRows['rows'][] = $duplicateRows['rows'][0];
        $duplicateColumnId = (string) Str::uuid();
        $duplicateColumns = $this->layoutContent('halves', [
            ['id' => $duplicateColumnId, 'elements' => []],
            ['id' => $duplicateColumnId, 'elements' => []],
        ]);

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
            [$tooLarge, 'content.rows.0.columns.0.elements.0'],
            [$unsupportedSchema, 'content.schema_version'],
            [$unknownRootKey, 'content.custom_css'],
            [$duplicateRows, 'content.rows.1.id'],
            [$duplicateColumns, 'content.rows.0.columns.1.id'],
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
                    [
                        'type' => 'accordion',
                        'items' => [[
                            'question' => 'Original question',
                            'answer' => '<p>Original answer</p>',
                        ]],
                        'allow_one_open' => true,
                    ],
                    [
                        'type' => 'timeline',
                        'items' => [[
                            'date_label' => '2026',
                            'heading' => 'Original milestone',
                            'body' => 'Original description',
                            'icon' => 'heart',
                        ]],
                        'style' => 'timeline',
                    ],
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
        $this->assertSame(2, data_get($copy->content, 'schema_version'));
        $this->assertNotSame(
            data_get($source->content, 'rows.0.columns.0.id'),
            data_get($copy->content, 'rows.0.columns.0.id')
        );
        $this->assertTrue(Str::isUuid(data_get($copy->content, 'rows.0.columns.0.id')));
        $this->assertNotSame(
            data_get($source->content, 'rows.0.columns.0.elements.0.id'),
            data_get($copy->content, 'rows.0.columns.0.elements.0.id')
        );
        $this->assertNotSame(
            data_get($source->content, 'rows.0.columns.0.elements.1.id'),
            data_get($copy->content, 'rows.0.columns.0.elements.1.id')
        );
        foreach ([2, 3] as $elementIndex) {
            $this->assertNotSame(
                data_get($source->content, "rows.0.columns.0.elements.{$elementIndex}.id"),
                data_get($copy->content, "rows.0.columns.0.elements.{$elementIndex}.id")
            );
            $this->assertNotSame(
                data_get($source->content, "rows.0.columns.0.elements.{$elementIndex}.items.0.id"),
                data_get($copy->content, "rows.0.columns.0.elements.{$elementIndex}.items.0.id")
            );
            $this->assertTrue(Str::isUuid(
                data_get($copy->content, "rows.0.columns.0.elements.{$elementIndex}.items.0.id")
            ));
        }
        $this->assertSame(
            data_get($source->content, 'rows.0.columns.0.elements.0.text'),
            data_get($copy->content, 'rows.0.columns.0.elements.0.text')
        );
        $this->assertSame(
            data_get($source->content, 'rows.0.columns.0.elements.2.items.0.answer'),
            data_get($copy->content, 'rows.0.columns.0.elements.2.items.0.answer')
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

    public function test_manifest_translation_blanks_only_authored_copy_and_tracks_nested_items_by_uuid(): void
    {
        $this->makeMediaAsset('media/2026/09/translation-gallery.jpg', 'image/jpeg');
        $service = app(LayoutBlockContentService::class);
        $sourceContent = $service->normalizeAndValidate($this->layoutContent('full', [[
            'elements' => [
                [
                    'type' => 'stat',
                    'value' => '2,000+',
                    'label' => 'People reached',
                    'icon' => 'people',
                    'emphasis' => 'accent',
                ],
                [
                    'type' => 'gallery',
                    'items' => [[
                        'path' => '/storage/media/2026/09/translation-gallery.jpg',
                        'alt' => 'Children learning',
                        'caption' => 'A community classroom',
                    ]],
                    'columns' => '3',
                    'lightbox' => true,
                ],
                [
                    'type' => 'accordion',
                    'items' => [
                        [
                            'question' => 'Who can join?',
                            'answer' => '<p>Everyone can join.</p>',
                        ],
                        [
                            'question' => 'Where do you work?',
                            'answer' => '<p>Across Bangladesh.</p>',
                        ],
                    ],
                    'allow_one_open' => true,
                ],
                [
                    'type' => 'timeline',
                    'items' => [[
                        'date_label' => '2026',
                        'heading' => 'Programs begin',
                        'body' => 'Communities lead the work.',
                        'icon' => 'heart',
                    ]],
                    'style' => 'timeline',
                ],
            ],
        ]]));
        $elements = data_get($sourceContent, 'rows.0.columns.0.elements');
        $galleryItemId = data_get($elements, '1.items.0.id');
        $accordionFirstId = data_get($elements, '2.items.0.id');
        $accordionSecondId = data_get($elements, '2.items.1.id');
        $timelineItemId = data_get($elements, '3.items.0.id');

        $translationService = app(TranslationCenterService::class);
        $draft = $translationService->prepareBlockTranslationContent($sourceContent);
        $draftBeforeGuard = $draft;
        $service->assertStoredVersionTwoIsValid($draft);
        $this->assertSame($draftBeforeGuard, $draft);
        $this->assertSame('', data_get($draft, 'rows.0.columns.0.elements.0.value'));
        $this->assertSame('', data_get($draft, 'rows.0.columns.0.elements.0.label'));
        $this->assertSame('people', data_get($draft, 'rows.0.columns.0.elements.0.icon'));
        $this->assertSame('accent', data_get($draft, 'rows.0.columns.0.elements.0.emphasis'));
        $this->assertSame($galleryItemId, data_get($draft, 'rows.0.columns.0.elements.1.items.0.id'));
        $this->assertSame(
            '/storage/media/2026/09/translation-gallery.jpg',
            data_get($draft, 'rows.0.columns.0.elements.1.items.0.path')
        );
        $this->assertSame('', data_get($draft, 'rows.0.columns.0.elements.1.items.0.alt'));
        $this->assertSame('', data_get($draft, 'rows.0.columns.0.elements.1.items.0.caption'));
        $this->assertSame($accordionFirstId, data_get($draft, 'rows.0.columns.0.elements.2.items.0.id'));
        $this->assertSame('', data_get($draft, 'rows.0.columns.0.elements.2.items.0.question'));
        $this->assertSame('', data_get($draft, 'rows.0.columns.0.elements.2.items.0.answer'));
        $this->assertTrue(data_get($draft, 'rows.0.columns.0.elements.2.allow_one_open'));
        $this->assertSame('heart', data_get($draft, 'rows.0.columns.0.elements.3.items.0.icon'));
        $this->assertSame('timeline', data_get($draft, 'rows.0.columns.0.elements.3.style'));

        $sourcePage = $this->makePage(['name' => 'Nested translation']);
        $sourceBlock = $this->makeLayoutBlock($sourcePage, [
            'translation_key' => (string) Str::uuid(),
            'content' => $sourceContent,
        ]);
        $targetPage = $this->makePage([
            'uuid' => $sourcePage->uuid,
            'name' => 'নেস্টেড অনুবাদ',
            'slug' => 'nested-translation-bn',
            'language' => 'bn',
        ]);
        $targetBlock = PageBlock::create([
            'page_id' => $targetPage->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => $sourceBlock->translation_key,
            'type' => 'layout',
            'label' => 'Visual layout',
            'content' => $draft,
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);

        $translationRows = $translationService->rows('en', 'bn')->filter(
            fn (array $row): bool =>
                ($row['identity']['source_block_id'] ?? null) === $sourceBlock->id
        );
        $fields = $translationRows->pluck('identity.field')->all();
        $expectedFields = [
            'value',
            'label',
            "items.{$galleryItemId}.alt",
            "items.{$galleryItemId}.caption",
            "items.{$accordionFirstId}.question",
            "items.{$accordionFirstId}.answer",
            "items.{$accordionSecondId}.question",
            "items.{$accordionSecondId}.answer",
            "items.{$timelineItemId}.date_label",
            "items.{$timelineItemId}.heading",
            "items.{$timelineItemId}.body",
        ];
        sort($fields);
        sort($expectedFields);
        $this->assertSame($expectedFields, $fields);
        $this->assertNotContains("items.{$galleryItemId}.path", $fields);
        $this->assertNotContains("items.{$timelineItemId}.icon", $fields);
        $this->assertNotContains('emphasis', $fields);
        $this->assertNotContains('style', $fields);

        $updates = collect([
            ['field' => 'value', 'value' => '<b>২,০০০+</b>'],
            [
                'field' => "items.{$accordionFirstId}.question",
                'value' => '<b>কারা যোগ দিতে পারবেন?</b>',
            ],
            [
                'field' => "items.{$accordionFirstId}.answer",
                'value' => '<div class="rogue"><p>সবাই <strong>যোগ দিতে পারবেন</strong>।</p><script>bad()</script></div>',
            ],
        ])->map(function (array $update) use ($translationRows): array {
            $row = $translationRows->first(
                fn (array $candidate): bool => ($candidate['identity']['field'] ?? null) === $update['field']
            );
            $this->assertNotNull($row);

            return [
                'key' => $row['key'],
                'precondition' => $row['precondition'],
                'value' => $update['value'],
            ];
        })->all();
        $translationService->save('en', 'bn', $updates, null);

        $savedElements = data_get($targetBlock->fresh()->content, 'rows.0.columns.0.elements');
        $this->assertSame('২,০০০+', data_get($savedElements, '0.value'));
        $this->assertSame('কারা যোগ দিতে পারবেন?', data_get($savedElements, '2.items.0.question'));
        $this->assertSame(
            '<p>সবাই <strong>যোগ দিতে পারবেন</strong>।</p>',
            data_get($savedElements, '2.items.0.answer')
        );

        $changed = $sourceBlock->fresh()->content;
        $changed['rows'][0]['columns'][0]['elements'][1]['lightbox'] = false;
        $changed['rows'][0]['columns'][0]['elements'][2]['items'] = array_reverse(
            $changed['rows'][0]['columns'][0]['elements'][2]['items']
        );
        $changed['rows'][0]['columns'][0]['elements'][2]['allow_one_open'] = false;
        $changed['rows'][0]['columns'][0]['elements'][3]['style'] = 'compact';
        $sourceBlock->update(['content' => $changed]);

        $translationService->syncPublicationState('en', 'bn');

        $syncedElements = data_get($targetBlock->fresh()->content, 'rows.0.columns.0.elements');
        $syncedAccordion = $syncedElements[2];
        $this->assertSame(
            [$accordionSecondId, $accordionFirstId],
            array_column($syncedAccordion['items'], 'id')
        );
        $translatedFirstItem = collect($syncedAccordion['items'])->first(
            fn (array $item): bool => $item['id'] === $accordionFirstId
        );
        $untranslatedSecondItem = collect($syncedAccordion['items'])->first(
            fn (array $item): bool => $item['id'] === $accordionSecondId
        );
        $this->assertSame('কারা যোগ দিতে পারবেন?', $translatedFirstItem['question']);
        $this->assertSame('', $untranslatedSecondItem['question']);
        $this->assertFalse($syncedAccordion['allow_one_open']);
        $this->assertFalse(data_get($syncedElements, '1.lightbox'));
        $this->assertSame(
            '/storage/media/2026/09/translation-gallery.jpg',
            data_get($syncedElements, '1.items.0.path')
        );
        $this->assertSame('compact', data_get($syncedElements, '3.style'));
        $this->assertSame('heart', data_get($syncedElements, '3.items.0.icon'));
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

    private function versionTwoLayoutWithRepeater(): array
    {
        $content = $this->layoutContent('full', [[
            'elements' => [[
                'type' => 'heading',
                'text' => 'Stored heading',
                'level' => 'h2',
            ], [
                'type' => 'accordion',
                'items' => [[
                    'question' => 'Who can participate?',
                    'answer' => '<p>Everyone is welcome.</p>',
                ]],
                'allow_one_open' => true,
            ]],
        ]]);

        return app(LayoutBlockContentService::class)->normalizeAndValidate($content);
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
