<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use App\Services\LayoutBlockContentService;
use App\Services\TranslationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TranslationCenterLayoutSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_source_layout_is_not_exposed_or_synchronized_and_cannot_be_prepared(): void
    {
        [$sourcePage, $targetPage] = $this->makePagePair('Future source');
        $valid = $this->layoutContent();
        $future = $valid;
        $future['schema_version'] = LayoutBlockContentService::CURRENT_SCHEMA_VERSION + 1;
        $translationKey = (string) Str::uuid();
        $sourceBlock = $this->makeLayoutBlock($sourcePage, $valid, $translationKey);
        $targetContent = app(TranslationCenterService::class)->prepareBlockTranslationContent($valid);
        data_set($targetContent, 'rows.0.columns.0.elements.0.text', 'আগের অনুবাদ');
        $targetBlock = $this->makeLayoutBlock($targetPage, $targetContent, $translationKey);
        $before = $targetBlock->content;

        $service = app(TranslationCenterService::class);
        $staleRow = $service->rows('en', 'bn')->first(
            fn (array $row): bool => ($row['identity']['source_block_id'] ?? null) === $sourceBlock->id
        );
        $this->assertNotNull($staleRow);
        $sourceBlock->update(['content' => $future]);

        $rows = $service->rows('en', 'bn')->filter(
            fn (array $row): bool => ($row['identity']['source_block_id'] ?? null) === $sourceBlock->id
        );
        $this->assertCount(0, $rows);

        try {
            $service->save('en', 'bn', [[
                'key' => $staleRow['key'],
                'precondition' => $staleRow['precondition'],
                'value' => 'এই লেখা সংরক্ষণ করা যাবে না',
            ]], null);
            $this->fail('A row from an older supported schema must not save into a future layout.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('translations', $exception->errors());
        }
        $this->assertSame($before, $targetBlock->fresh()->content);

        try {
            $service->prepareBlockTranslationContent($future);
            $this->fail('A future layout schema must not be prepared as a translation draft.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('translations', $exception->errors());
            $this->assertStringContainsString('Nothing was changed', $exception->errors()['translations'][0]);
        }

        $sourcePage->update([
            'status' => false,
            'publication_status' => 'draft',
        ]);
        $service->syncPublicationState('en', 'bn');

        $this->assertSame($before, $targetBlock->fresh()->content);
    }

    public function test_corrupt_or_future_target_layout_is_not_exposed_and_remains_unchanged_during_sync(): void
    {
        $service = app(TranslationCenterService::class);
        $sourceBlockIds = [];
        $targets = [];

        foreach (['corrupt', 'future'] as $case) {
            [$sourcePage, $targetPage] = $this->makePagePair("{$case} target");
            $sourcePage->update([
                'status' => false,
                'publication_status' => 'draft',
            ]);
            $sourceContent = $this->layoutContent();
            $translationKey = (string) Str::uuid();
            $sourceBlock = $this->makeLayoutBlock($sourcePage, $sourceContent, $translationKey);
            $targetContent = $service->prepareBlockTranslationContent($sourceContent);

            if ($case === 'corrupt') {
                unset($targetContent['rows'][0]['columns'][0]['elements'][0]['id']);
            } else {
                $targetContent['schema_version'] = LayoutBlockContentService::CURRENT_SCHEMA_VERSION + 1;
            }

            $targetBlock = $this->makeLayoutBlock($targetPage, $targetContent, $translationKey);
            $sourceBlockIds[] = $sourceBlock->id;
            $targets[] = [$targetBlock, $targetContent];
        }

        $rows = $service->rows('en', 'bn')->filter(
            fn (array $row): bool => in_array(
                $row['identity']['source_block_id'] ?? null,
                $sourceBlockIds,
                true
            )
        );
        $this->assertCount(0, $rows);

        $service->syncPublicationState('en', 'bn');

        foreach ($targets as [$targetBlock, $before]) {
            $this->assertSame($before, $targetBlock->fresh()->content);
        }
    }

    public function test_unsafe_target_layout_cannot_follow_its_source_from_draft_to_published(): void
    {
        [$sourcePage, $targetPage] = $this->makePagePair('Unsafe target publication');
        $targetPage->update([
            'status' => false,
            'publication_status' => 'draft',
            'visibility' => 'public',
        ]);
        $service = app(TranslationCenterService::class);
        $translationKey = (string) Str::uuid();
        $sourceBlock = $this->makeLayoutBlock($sourcePage, $this->layoutContent(), $translationKey);
        $targetContent = $service->prepareBlockTranslationContent($sourceBlock->content);
        unset($targetContent['rows'][0]['columns'][0]['elements'][0]['id']);
        $targetBlock = $this->makeLayoutBlock($targetPage, $targetContent, $translationKey);
        $beforeContent = $targetBlock->content;

        $layoutRows = $service->rows('en', 'bn')->filter(
            fn (array $row): bool => ($row['identity']['source_block_id'] ?? null) === $sourceBlock->id
        );
        $this->assertCount(0, $layoutRows);

        try {
            $service->syncPublicationState('en', 'bn');
            $this->fail('An unsafe target layout must block publication even when its omitted rows look complete.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('translations', $exception->errors());
            $this->assertStringContainsString('cannot be published', $exception->errors()['translations'][0]);
            $this->assertStringContainsString('Nothing was published', $exception->errors()['translations'][0]);
        }

        $targetPage->refresh();
        $this->assertFalse($targetPage->status);
        $this->assertSame('draft', $targetPage->publication_status);
        $this->assertSame($beforeContent, $targetBlock->fresh()->content);
    }

    public function test_unsafe_source_layout_cannot_publish_a_stale_target_page(): void
    {
        [$sourcePage, $targetPage] = $this->makePagePair('Unsafe source publication');
        $targetPage->update([
            'status' => false,
            'publication_status' => 'draft',
            'visibility' => 'public',
        ]);
        $service = app(TranslationCenterService::class);
        $translationKey = (string) Str::uuid();
        $valid = $this->layoutContent();
        $sourceBlock = $this->makeLayoutBlock($sourcePage, $valid, $translationKey);
        $targetBlock = $this->makeLayoutBlock(
            $targetPage,
            $service->prepareBlockTranslationContent($valid),
            $translationKey
        );
        $future = $valid;
        $future['schema_version'] = LayoutBlockContentService::CURRENT_SCHEMA_VERSION + 1;
        $sourceBlock->update(['content' => $future]);
        $beforeContent = $targetBlock->content;

        $layoutRows = $service->rows('en', 'bn')->filter(
            fn (array $row): bool => ($row['identity']['source_block_id'] ?? null) === $sourceBlock->id
        );
        $this->assertCount(0, $layoutRows);

        try {
            $service->syncPublicationState('en', 'bn');
            $this->fail('An unsupported source layout must not publish a stale target page.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('translations', $exception->errors());
            $this->assertStringContainsString('cannot be published', $exception->errors()['translations'][0]);
            $this->assertStringContainsString('Nothing was published', $exception->errors()['translations'][0]);
        }

        $targetPage->refresh();
        $this->assertFalse($targetPage->status);
        $this->assertSame('draft', $targetPage->publication_status);
        $this->assertSame($beforeContent, $targetBlock->fresh()->content);
    }

    public function test_oversized_plain_and_nested_rich_translations_are_rejected_atomically_after_sanitizing(): void
    {
        [$sourcePage, $targetPage] = $this->makePagePair('Translation bounds');
        $sourceContent = $this->layoutContent(withAccordion: true);
        $translationKey = (string) Str::uuid();
        $sourceBlock = $this->makeLayoutBlock($sourcePage, $sourceContent, $translationKey);
        $service = app(TranslationCenterService::class);
        $targetContent = $service->prepareBlockTranslationContent($sourceContent);
        $targetBlock = $this->makeLayoutBlock($targetPage, $targetContent, $translationKey);
        $before = $targetBlock->content;

        $rows = $service->rows('en', 'bn')->filter(
            fn (array $row): bool => ($row['identity']['source_block_id'] ?? null) === $sourceBlock->id
        );
        $heading = $rows->first(
            fn (array $row): bool => ($row['identity']['field'] ?? null) === 'text'
        );
        $answer = $rows->first(
            fn (array $row): bool => str_ends_with((string) ($row['identity']['field'] ?? ''), '.answer')
        );
        $this->assertNotNull($heading);
        $this->assertNotNull($answer);
        $this->assertSame('', $heading['target']);
        $this->assertSame('', $answer['target']);

        try {
            $service->save('en', 'bn', [[
                'key' => $heading['key'],
                'precondition' => $heading['precondition'],
                'value' => '<strong>' . str_repeat('ক', 501) . '</strong>',
            ]], null);
            $this->fail('An oversized plain-text translation must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('translations', $exception->errors());
            $this->assertStringContainsString('500 characters', $exception->errors()['translations'][0]);
            $this->assertStringContainsString('Nothing was saved', $exception->errors()['translations'][0]);
        }
        $this->assertSame($before, $targetBlock->fresh()->content);

        $rows = $service->rows('en', 'bn')->filter(
            fn (array $row): bool => ($row['identity']['source_block_id'] ?? null) === $sourceBlock->id
        );
        $heading = $rows->first(
            fn (array $row): bool => ($row['identity']['field'] ?? null) === 'text'
        );
        $answer = $rows->first(
            fn (array $row): bool => str_ends_with((string) ($row['identity']['field'] ?? ''), '.answer')
        );

        try {
            $service->save('en', 'bn', [
                [
                    'key' => $heading['key'],
                    'precondition' => $heading['precondition'],
                    'value' => 'এই লেখা সংরক্ষণ করা উচিত নয়',
                ],
                [
                    'key' => $answer['key'],
                    'precondition' => $answer['precondition'],
                    'value' => '<div><p>' . str_repeat('খ', 10001) . '</p></div>',
                ],
            ], null);
            $this->fail('An oversized nested rich-text translation must reject the whole batch.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('translations', $exception->errors());
            $this->assertStringContainsString('10000 characters', $exception->errors()['translations'][0]);
            $this->assertStringContainsString('Nothing was saved', $exception->errors()['translations'][0]);
        }

        $this->assertSame($before, $targetBlock->fresh()->content);
        $this->assertSame('', data_get($targetBlock->fresh()->content, 'rows.0.columns.0.elements.0.text'));
    }

    public function test_individually_bounded_translations_cannot_overflow_the_complete_element(): void
    {
        [$sourcePage, $targetPage] = $this->makePagePair('Aggregate translation bounds');
        $sourceContent = $this->layoutContent(withAccordion: true, accordionItems: 12);
        $translationKey = (string) Str::uuid();
        $sourceBlock = $this->makeLayoutBlock($sourcePage, $sourceContent, $translationKey);
        $service = app(TranslationCenterService::class);
        $targetContent = $service->prepareBlockTranslationContent($sourceContent);

        // Six valid answers fit under the accordion element's 64 KiB bound.
        // A seventh individually valid 9,500-character answer would overflow
        // that complete serialized element unless the prospective draft is
        // checked as a whole immediately before persistence.
        for ($item = 0; $item < 6; $item++) {
            data_set(
                $targetContent,
                "rows.0.columns.0.elements.1.items.{$item}.answer",
                '<p>' . str_repeat('a', 9500) . '</p>'
            );
        }
        $targetBlock = $this->makeLayoutBlock($targetPage, $targetContent, $translationKey);
        $before = $targetBlock->content;
        $seventhItemId = data_get($sourceContent, 'rows.0.columns.0.elements.1.items.6.id');

        $rows = $service->rows('en', 'bn')->filter(
            fn (array $row): bool => ($row['identity']['source_block_id'] ?? null) === $sourceBlock->id
        );
        $question = $rows->first(
            fn (array $row): bool => ($row['identity']['field'] ?? null) === "items.{$seventhItemId}.question"
        );
        $answer = $rows->first(
            fn (array $row): bool => ($row['identity']['field'] ?? null) === "items.{$seventhItemId}.answer"
        );
        $this->assertNotNull($question);
        $this->assertNotNull($answer);

        try {
            $service->save('en', 'bn', [
                [
                    'key' => $question['key'],
                    'precondition' => $question['precondition'],
                    'value' => 'This valid change must roll back too',
                ],
                [
                    'key' => $answer['key'],
                    'precondition' => $answer['precondition'],
                    'value' => '<p>' . str_repeat('b', 9500) . '</p>',
                ],
            ], null);
            $this->fail('Cumulative translations must not exceed the complete element byte bound.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('translations', $exception->errors());
            $this->assertStringContainsString('too large', $exception->errors()['translations'][0]);
            $this->assertStringContainsString('nothing was saved', $exception->errors()['translations'][0]);
        }

        $this->assertSame($before, $targetBlock->fresh()->content);
        $this->assertSame(
            '',
            data_get($targetBlock->fresh()->content, 'rows.0.columns.0.elements.1.items.6.question')
        );
    }

    private function layoutContent(bool $withAccordion = false, int $accordionItems = 1): array
    {
        $elements = [[
            'type' => 'heading',
            'text' => 'A source heading',
            'level' => 'h2',
        ]];

        if ($withAccordion) {
            $elements[] = [
                'type' => 'accordion',
                'items' => array_map(
                    fn (int $item): array => [
                        'question' => "Question {$item}",
                        'answer' => '<p>Everyone is welcome.</p>',
                    ],
                    range(1, $accordionItems)
                ),
                'allow_one_open' => true,
            ];
        }

        return app(LayoutBlockContentService::class)->normalizeAndValidate([
            'rows' => [[
                'layout' => 'full',
                'width' => 'standard',
                'background' => 'default',
                'spacing' => 'standard',
                'columns' => [['elements' => $elements]],
            ]],
        ]);
    }

    /** @return array{Page, Page} */
    private function makePagePair(string $name): array
    {
        $uuid = (string) Str::uuid();
        $slug = 'translation-layout-' . Str::lower(Str::random(10));
        $source = Page::create([
            'uuid' => $uuid,
            'name' => $name,
            'sub_title' => 'Source introduction',
            'slug' => $slug,
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
        ]);
        $target = Page::create([
            'uuid' => $uuid,
            'name' => "{$name} translation",
            'sub_title' => 'Translated introduction',
            'slug' => $slug . '-bn',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'bn',
        ]);

        return [$source, $target];
    }

    private function makeLayoutBlock(Page $page, array $content, string $translationKey): PageBlock
    {
        return PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'translation_key' => $translationKey,
            'type' => 'layout',
            'label' => 'Visual layout',
            'content' => $content,
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
    }
}
