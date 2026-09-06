<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\ReusableBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LayoutContentSchemaMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_never_repairs_or_reencodes_content_that_already_declares_schema_v2(): void
    {
        $page = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Corrupt declared v2 migration guard',
            'sub_title' => '',
            'slug' => 'corrupt-declared-v2-migration-guard',
            'status' => 1,
            'language' => 'en',
        ]);

        $corruptV2 = [
            'schema_version' => 2,
            'rows' => [[
                'id' => 'not-a-uuid',
                'layout' => 'full',
                'columns' => [[
                    'id' => 'not-a-uuid',
                    'elements' => [[
                        'id' => 'not-a-uuid',
                        'type' => 'accordion',
                        'items' => [
                            ['id' => 'duplicate', 'question' => 'One', 'answer' => 'First'],
                            ['id' => 'duplicate', 'question' => 'Two', 'answer' => 'Second'],
                        ],
                    ]],
                ]],
            ]],
        ];
        $rawPayload = json_encode(
            $corruptV2,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        $pageBlock = PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'layout',
            'label' => 'Corrupt declared v2 page layout',
            'content' => $corruptV2,
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
        $reusable = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Corrupt declared v2 reusable layout',
            'type' => 'layout',
            'locale' => 'en',
            'content' => $corruptV2,
            'settings' => [],
            'is_enabled' => true,
        ]);

        DB::table('page_blocks')->where('id', $pageBlock->id)->update(['content' => $rawPayload]);
        DB::table('reusable_blocks')->where('id', $reusable->id)->update(['content' => $rawPayload]);

        $migration = require database_path('migrations/2026_09_07_000000_upgrade_layout_content_to_schema_v2.php');
        $migration->up();

        $this->assertSame(
            $rawPayload,
            DB::table('page_blocks')->where('id', $pageBlock->id)->value('content')
        );
        $this->assertSame(
            $rawPayload,
            DB::table('reusable_blocks')->where('id', $reusable->id)->value('content')
        );
    }

    public function test_migration_never_overwrites_layout_content_saved_after_its_select(): void
    {
        $page = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Concurrent layout migration',
            'sub_title' => '',
            'slug' => 'concurrent-layout-migration',
            'status' => 1,
            'language' => 'en',
        ]);

        $selectedPageContent = $this->legacyLayout('Selected page copy');
        $concurrentPageContent = $this->legacyLayout('Concurrent page editor copy');
        $pageBlock = PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'layout',
            'label' => 'Concurrent page layout',
            'content' => $selectedPageContent,
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);

        $selectedReusableContent = $this->legacyLayout('Selected reusable copy');
        $concurrentReusableContent = $this->legacyLayout('Concurrent reusable editor copy');
        $reusable = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Concurrent reusable layout',
            'type' => 'layout',
            'locale' => 'en',
            'content' => $selectedReusableContent,
            'settings' => [],
            'is_enabled' => true,
        ]);

        $injectedPageSave = false;
        $injectedReusableSave = false;
        DB::listen(function ($query) use (
            $pageBlock,
            $reusable,
            $concurrentPageContent,
            $concurrentReusableContent,
            &$injectedPageSave,
            &$injectedReusableSave
        ): void {
            $sql = strtolower(ltrim((string) $query->sql));
            if (! str_starts_with($sql, 'select')) {
                return;
            }

            if (! $injectedPageSave
                && preg_match('/\bfrom\s+[`"]?page_blocks[`"]?\b/', $sql) === 1) {
                $injectedPageSave = true;
                DB::table('page_blocks')->where('id', $pageBlock->id)->update([
                    'content' => json_encode($concurrentPageContent, JSON_THROW_ON_ERROR),
                    'updated_at' => '2026-09-07 03:00:00',
                ]);

                return;
            }

            if (! $injectedReusableSave
                && preg_match('/\bfrom\s+[`"]?reusable_blocks[`"]?\b/', $sql) === 1) {
                $injectedReusableSave = true;
                DB::table('reusable_blocks')->where('id', $reusable->id)->update([
                    'content' => json_encode($concurrentReusableContent, JSON_THROW_ON_ERROR),
                    'editor_version' => 9,
                    'updated_at' => '2026-09-07 03:01:00',
                ]);
            }
        });

        $migration = require database_path('migrations/2026_09_07_000000_upgrade_layout_content_to_schema_v2.php');
        $migration->up();

        $this->assertTrue($injectedPageSave);
        $this->assertTrue($injectedReusableSave);
        $this->assertSame($concurrentPageContent, $pageBlock->fresh()->content);
        $this->assertSame($concurrentReusableContent, $reusable->fresh()->content);
        $this->assertSame(9, (int) $reusable->fresh()->editor_version);
        $this->assertEquals(
            '2026-09-07 03:00:00',
            DB::table('page_blocks')->where('id', $pageBlock->id)->value('updated_at')
        );
        $this->assertEquals(
            '2026-09-07 03:01:00',
            DB::table('reusable_blocks')->where('id', $reusable->id)->value('updated_at')
        );

        // A later retry can safely upgrade the editor's latest payload. Once
        // upgraded, another run is a no-op, preserving migration idempotence.
        $migration->up();
        $afterSafeRetry = [
            'page' => DB::table('page_blocks')->where('id', $pageBlock->id)->value('content'),
            'reusable' => DB::table('reusable_blocks')->where('id', $reusable->id)->value('content'),
        ];
        $this->assertSame(2, data_get($pageBlock->fresh()->content, 'schema_version'));
        $this->assertSame('Concurrent page editor copy', data_get($pageBlock->fresh()->content, 'rows.0.columns.0.elements.0.text'));
        $this->assertSame(2, data_get($reusable->fresh()->content, 'schema_version'));
        $this->assertSame('Concurrent reusable editor copy', data_get($reusable->fresh()->content, 'rows.0.columns.0.elements.0.text'));

        $migration->up();
        $this->assertSame(
            $afterSafeRetry,
            [
                'page' => DB::table('page_blocks')->where('id', $pageBlock->id)->value('content'),
                'reusable' => DB::table('reusable_blocks')->where('id', $reusable->id)->value('content'),
            ]
        );
    }

    public function test_migration_upgrades_both_live_layout_stores_without_touching_history_or_authored_content(): void
    {
        $page = Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Legacy layout migration',
            'sub_title' => '',
            'slug' => 'legacy-layout-migration',
            'status' => 1,
            'language' => 'en',
        ]);

        $sharedRowId = (string) Str::uuid();
        $sharedColumnId = (string) Str::uuid();
        $sharedElementId = (string) Str::uuid();
        $sharedSubitemId = (string) Str::uuid();
        $unsupportedNilId = '00000000-0000-0000-0000-000000000000';
        $unsupportedVersionId = '11111111-1111-9111-8111-111111111111';
        $legacyPageContent = [
            'section_presentation' => 'soft',
            'rows' => [
                [
                    'id' => $sharedRowId,
                    'layout' => 'halves',
                    'width' => 'wide',
                    'background' => 'accent',
                    'spacing' => 'generous',
                    'columns' => [
                        [
                            'id' => $sharedColumnId,
                            'elements' => [[
                                'id' => $sharedElementId,
                                'type' => 'heading',
                                'text' => 'Keep this exact heading',
                                'level' => 'h2',
                            ]],
                        ],
                        [
                            'id' => $sharedColumnId,
                            'elements' => [[
                                'id' => $sharedElementId,
                                'type' => 'rich_text',
                                'body' => '<p>Keep this <strong>authored copy</strong>.</p>',
                            ]],
                        ],
                    ],
                ],
                [
                    'id' => $unsupportedNilId,
                    'layout' => 'full',
                    'width' => 'standard',
                    'background' => 'default',
                    'spacing' => 'compact',
                    'columns' => [[
                        'elements' => [[
                            'id' => $unsupportedVersionId,
                            'type' => 'button',
                            'label' => 'Keep destination',
                            'url' => '/donate',
                            'style' => 'primary',
                        ], [
                            'type' => 'accordion',
                            'items' => [
                                [
                                    'id' => $sharedSubitemId,
                                    'question' => 'Keep first question',
                                    'answer' => '<p>Keep first answer.</p>',
                                ],
                                [
                                    'id' => $sharedSubitemId,
                                    'question' => 'Keep second question',
                                    'answer' => '<p>Keep second answer.</p>',
                                ],
                            ],
                            'allow_one_open' => true,
                        ], [
                            'type' => 'timeline',
                            'items' => [[
                                'date_label' => '2026',
                                'heading' => 'Keep milestone',
                                'body' => 'Keep milestone copy.',
                                'icon' => 'heart',
                            ]],
                            'style' => 'timeline',
                        ]],
                    ]],
                ],
            ],
        ];
        $pageBlock = PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'layout',
            'label' => 'Legacy page layout',
            'content' => $legacyPageContent,
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);

        $legacyReusableContent = [
            'schema_version' => 1,
            'rows' => [[
                'layout' => 'full',
                'width' => 'standard',
                'background' => 'dark',
                'spacing' => 'standard',
                'columns' => [[
                    'elements' => [[
                        'type' => 'gallery',
                        'items' => [[
                            'path' => '/storage/media/migration-photo.jpg',
                            'alt' => 'Keep image text',
                            'caption' => 'Keep image caption',
                        ]],
                        'columns' => '3',
                        'lightbox' => true,
                    ]],
                ]],
            ]],
        ];
        $reusable = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Legacy reusable layout',
            'type' => 'layout',
            'locale' => 'en',
            'content' => $legacyReusableContent,
            'settings' => ['locked' => true],
            'is_enabled' => true,
        ]);

        $futureContent = ['schema_version' => 3, 'rows' => []];
        $future = PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'layout',
            'label' => 'Future layout',
            'content' => $futureContent,
            'settings' => [],
            'sort_order' => 2,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
        $nonLayoutContent = ['heading' => 'Ordinary hero content'];
        $nonLayout = PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'hero',
            'label' => 'Hero',
            'content' => $nonLayoutContent,
            'settings' => [],
            'sort_order' => 3,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
        $malformedRepeaterContent = [
            'schema_version' => 1,
            'rows' => [[
                'layout' => 'full',
                'width' => 'standard',
                'background' => 'default',
                'spacing' => 'standard',
                'columns' => [[
                    'elements' => [[
                        'type' => 'accordion',
                        'items' => ['not-an-ordered-list' => 'invalid'],
                        'allow_one_open' => false,
                    ]],
                ]],
            ]],
        ];
        $malformedRepeater = PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => (string) Str::uuid(),
            'type' => 'layout',
            'label' => 'Malformed legacy repeater',
            'content' => $malformedRepeaterContent,
            'settings' => [],
            'sort_order' => 4,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);

        $snapshot = json_encode(['blocks' => [['content' => $legacyPageContent]]], JSON_THROW_ON_ERROR);
        DB::table('page_revisions')->insert([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'revision' => 1,
            'snapshot' => $snapshot,
            'note' => 'Historical legacy snapshot',
            'created_at' => '2026-09-01 00:00:00',
            'updated_at' => '2026-09-01 00:00:00',
        ]);
        DB::table('page_blocks')->where('id', $pageBlock->id)->update(['updated_at' => '2026-09-01 01:00:00']);
        DB::table('reusable_blocks')->where('id', $reusable->id)->update([
            'editor_version' => 7,
            'updated_at' => '2026-09-01 02:00:00',
        ]);
        $pageTimestamp = DB::table('page_blocks')->where('id', $pageBlock->id)->value('updated_at');
        $reusableTimestamp = DB::table('reusable_blocks')->where('id', $reusable->id)->value('updated_at');

        $migration = require database_path('migrations/2026_09_07_000000_upgrade_layout_content_to_schema_v2.php');
        $migration->up();

        $pageContent = $pageBlock->fresh()->content;
        $reusableContent = $reusable->fresh()->content;
        $this->assertSame(2, $pageContent['schema_version']);
        $this->assertSame(2, $reusableContent['schema_version']);
        $this->assertSame('soft', $pageContent['section_presentation']);
        $this->assertSame('Keep this exact heading', data_get($pageContent, 'rows.0.columns.0.elements.0.text'));
        $this->assertSame(
            '<p>Keep this <strong>authored copy</strong>.</p>',
            data_get($pageContent, 'rows.0.columns.1.elements.0.body')
        );
        $this->assertSame('/donate', data_get($pageContent, 'rows.1.columns.0.elements.0.url'));
        $this->assertSame(
            '<p>Keep second answer.</p>',
            data_get($pageContent, 'rows.1.columns.0.elements.1.items.1.answer')
        );
        $this->assertSame(
            'Keep milestone copy.',
            data_get($pageContent, 'rows.1.columns.0.elements.2.items.0.body')
        );
        $this->assertSame('dark', data_get($reusableContent, 'rows.0.background'));
        $this->assertSame(
            'Keep image caption',
            data_get($reusableContent, 'rows.0.columns.0.elements.0.items.0.caption')
        );

        $this->assertLayoutIdentitiesAreUniqueUuids($pageContent);
        $this->assertLayoutIdentitiesAreUniqueUuids($reusableContent);
        $this->assertSame($sharedRowId, data_get($pageContent, 'rows.0.id'));
        $this->assertNotSame($sharedRowId, data_get($pageContent, 'rows.1.id'));
        $this->assertNotSame($unsupportedNilId, data_get($pageContent, 'rows.1.id'));
        $this->assertSame($sharedColumnId, data_get($pageContent, 'rows.0.columns.0.id'));
        $this->assertNotSame($sharedColumnId, data_get($pageContent, 'rows.0.columns.1.id'));
        $this->assertSame($sharedElementId, data_get($pageContent, 'rows.0.columns.0.elements.0.id'));
        $this->assertNotSame($sharedElementId, data_get($pageContent, 'rows.0.columns.1.elements.0.id'));
        $this->assertNotSame(
            $unsupportedVersionId,
            data_get($pageContent, 'rows.1.columns.0.elements.0.id')
        );
        $this->assertSame(
            $sharedSubitemId,
            data_get($pageContent, 'rows.1.columns.0.elements.1.items.0.id')
        );
        $this->assertNotSame(
            $sharedSubitemId,
            data_get($pageContent, 'rows.1.columns.0.elements.1.items.1.id')
        );

        $this->assertSame($futureContent, $future->fresh()->content);
        $this->assertSame($nonLayoutContent, $nonLayout->fresh()->content);
        $this->assertSame($malformedRepeaterContent, $malformedRepeater->fresh()->content);
        $this->assertSame($snapshot, DB::table('page_revisions')->where('page_id', $page->id)->value('snapshot'));
        $this->assertSame(1, DB::table('page_revisions')->where('page_id', $page->id)->count());
        $this->assertSame(7, (int) $reusable->fresh()->editor_version);
        $this->assertEquals($pageTimestamp, DB::table('page_blocks')->where('id', $pageBlock->id)->value('updated_at'));
        $this->assertEquals($reusableTimestamp, DB::table('reusable_blocks')->where('id', $reusable->id)->value('updated_at'));

        $afterFirstRun = [
            'page' => DB::table('page_blocks')->where('id', $pageBlock->id)->value('content'),
            'reusable' => DB::table('reusable_blocks')->where('id', $reusable->id)->value('content'),
        ];
        $migration->up();
        $this->assertSame(
            $afterFirstRun,
            [
                'page' => DB::table('page_blocks')->where('id', $pageBlock->id)->value('content'),
                'reusable' => DB::table('reusable_blocks')->where('id', $reusable->id)->value('content'),
            ]
        );

        $migration->down();
        $this->assertSame(2, data_get($pageBlock->fresh()->content, 'schema_version'));
        $this->assertSame(2, data_get($reusable->fresh()->content, 'schema_version'));
    }

    /** @param array<string, mixed> $content */
    private function assertLayoutIdentitiesAreUniqueUuids(array $content): void
    {
        $rowIds = [];
        $columnIds = [];
        $elementIds = [];
        $subitemIds = [];

        foreach ($content['rows'] as $row) {
            $rowIds[] = $row['id'];
            foreach ($row['columns'] as $column) {
                $columnIds[] = $column['id'];
                foreach ($column['elements'] as $element) {
                    $elementIds[] = $element['id'];
                    if (!in_array($element['type'] ?? null, ['gallery', 'accordion', 'timeline'], true)) {
                        continue;
                    }
                    foreach ($element['items'] ?? [] as $item) {
                        $subitemIds[] = $item['id'];
                    }
                }
            }
        }

        foreach ([$rowIds, $columnIds, $elementIds, $subitemIds] as $identities) {
            foreach ($identities as $identity) {
                $this->assertTrue(Str::isUuid($identity));
            }
            $this->assertCount(count($identities), array_unique(array_map('strtolower', $identities)));
        }
    }

    /** @return array<string, mixed> */
    private function legacyLayout(string $heading): array
    {
        return [
            'schema_version' => 1,
            'rows' => [[
                'layout' => 'full',
                'width' => 'standard',
                'background' => 'default',
                'spacing' => 'standard',
                'columns' => [[
                    'elements' => [[
                        'type' => 'heading',
                        'text' => $heading,
                        'level' => 'h2',
                    ]],
                ]],
            ]],
        ];
    }
}
