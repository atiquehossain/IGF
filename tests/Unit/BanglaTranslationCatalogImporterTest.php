<?php

namespace Tests\Unit;

use App\Services\BanglaTranslationCatalogImporter;
use App\Services\TranslationCenterService;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class BanglaTranslationCatalogImporterTest extends TestCase
{
    public function test_catalog_is_explicit_and_reviewable(): void
    {
        $rows = collect([
            $this->row('one', '<p>Hello :name</p>', true, 'html'),
        ]);
        $service = Mockery::mock(TranslationCenterService::class);
        $service->shouldReceive('rows')->once()->with('en', 'bn')->andReturn($rows);

        $catalog = (new BanglaTranslationCatalogImporter($service))->catalog();

        $this->assertSame(BanglaTranslationCatalogImporter::SCHEMA, $catalog['schema']);
        $this->assertSame('en', $catalog['source_locale']);
        $this->assertSame('bn', $catalog['target_locale']);
        $this->assertSame('<p>Hello :name</p>', $catalog['entries'][0]['source']);
        $this->assertSame(hash('sha256', '<p>Hello :name</p>'), $catalog['entries'][0]['source_hash']);
        $this->assertSame('', $catalog['entries'][0]['translation']);
        $this->assertFalse($catalog['entries'][0]['preserve_source']);
    }

    public function test_catalog_surfaces_curated_bangla_setting_defaults_as_review_suggestions(): void
    {
        config()->set('site-settings.groups.header.fields.donate_label', [
            'localized_defaults' => ['bn' => 'অনুদান দিন'],
        ]);
        $row = $this->row('donate', 'Donate');
        $row['identity'] = [
            'type' => 'setting',
            'group' => 'header',
            'field' => 'donate_label',
        ];
        $service = Mockery::mock(TranslationCenterService::class);
        $service->shouldReceive('rows')->once()->with('en', 'bn')->andReturn(collect([$row]));

        $catalog = (new BanglaTranslationCatalogImporter($service))->catalog();

        $this->assertSame('অনুদান দিন', $catalog['entries'][0]['suggested_translation']);
        $this->assertSame('', $catalog['entries'][0]['translation']);
    }

    public function test_inspection_accepts_bangla_while_preserving_html_and_placeholders(): void
    {
        $rows = collect([
            $this->row('one', '<p>Hello :name and %s</p>', true, 'html'),
        ]);
        $service = Mockery::mock(TranslationCenterService::class);
        $service->shouldReceive('rows')->once()->with('en', 'bn')->andReturn($rows);
        $catalog = $this->catalogFor($rows, [
            'one' => '<p>স্বাগতম :name এবং %s</p>',
        ]);

        $report = (new BanglaTranslationCatalogImporter($service))->inspect($catalog);

        $this->assertSame(1, $report['planned']);
        $this->assertSame(1, $report['required_rows']);
        $this->assertSame(0, $report['already_translated']);
    }

    public function test_inspection_rejects_placeholder_or_html_changes_before_any_save(): void
    {
        $rows = collect([
            $this->row('one', '<p>Hello :name</p>', true, 'html'),
        ]);
        $service = Mockery::mock(TranslationCenterService::class);
        $service->shouldReceive('rows')->once()->with('en', 'bn')->andReturn($rows);
        $service->shouldNotReceive('save');
        $catalog = $this->catalogFor($rows, [
            'one' => '<strong>স্বাগতম :person</strong>',
        ]);

        $this->expectException(InvalidArgumentException::class);
        (new BanglaTranslationCatalogImporter($service))->inspect($catalog);
    }

    public function test_stale_source_is_rejected(): void
    {
        $rows = collect([$this->row('one', 'Current English')]);
        $service = Mockery::mock(TranslationCenterService::class);
        $service->shouldReceive('rows')->once()->with('en', 'bn')->andReturn($rows);
        $catalog = $this->catalogFor($rows, ['one' => 'বর্তমান ইংরেজি']);
        $catalog['entries'][0]['source'] = 'Old English';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('English source changed');
        (new BanglaTranslationCatalogImporter($service))->inspect($catalog);
    }

    public function test_stale_source_is_rejected_even_when_the_database_row_is_already_translated(): void
    {
        $rows = collect([$this->row('one', 'Current English', target: 'বর্তমান ইংরেজি')]);
        $service = Mockery::mock(TranslationCenterService::class);
        $service->shouldReceive('rows')->once()->with('en', 'bn')->andReturn($rows);
        $catalog = $this->catalogFor($rows, ['one' => 'বর্তমান ইংরেজি']);
        $catalog['entries'][0]['source'] = 'Old English';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('English source changed');
        (new BanglaTranslationCatalogImporter($service))->inspect($catalog);
    }

    public function test_unchanged_proper_nouns_require_an_explicit_preserve_source_decision(): void
    {
        $rows = collect([$this->row('one', 'bKash')]);
        $service = Mockery::mock(TranslationCenterService::class);
        $service->shouldReceive('rows')->twice()->with('en', 'bn')->andReturn($rows);
        $catalog = $this->catalogFor($rows, ['one' => 'bKash']);
        $importer = new BanglaTranslationCatalogImporter($service);

        try {
            $importer->inspect($catalog);
            $this->fail('An unchanged source must be an explicit review decision.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('preserve_source', $exception->getMessage());
        }

        $catalog['entries'][0]['preserve_source'] = true;
        $report = $importer->inspect($catalog);
        $this->assertSame(1, $report['planned']);
        $this->assertSame(1, $report['preserved_source']);
    }

    public function test_apply_never_sends_more_than_one_hundred_rows_to_translation_center_service(): void
    {
        $rows = collect(range(1, 205))->map(fn (int $number): array => $this->row(
            'row-'.$number,
            'English copy '.$number,
            $number <= 200
        ));
        $catalog = $this->catalogFor($rows, $rows->mapWithKeys(fn (array $row): array => [
            $row['key'] => 'বাংলা অনুবাদ '.substr($row['key'], 4),
        ])->all());
        $batchSizes = [];
        $service = Mockery::mock(TranslationCenterService::class);
        $service->shouldReceive('rows')->once()->with('en', 'bn')->andReturn($rows);
        $service->shouldReceive('save')
            ->times(3)
            ->withArgs(function (string $source, string $target, array $updates, ?int $adminId) use (&$batchSizes): bool {
                $batchSizes[] = count($updates);

                return $source === 'en'
                    && $target === 'bn'
                    && $adminId === 42
                    && count($updates) <= BanglaTranslationCatalogImporter::MAX_BATCH_SIZE;
            })
            ->andReturnUsing(fn (string $source, string $target, array $updates): int => count($updates));

        $report = (new BanglaTranslationCatalogImporter($service))->apply($catalog, 42);

        $this->assertSame([100, 100, 5], $batchSizes);
        $this->assertSame(205, $report['planned']);
        $this->assertSame(205, $report['saved']);
    }

    private function row(
        string $key,
        string $source,
        bool $required = true,
        string $format = 'text',
        string $target = ''
    ): array {
        return [
            'key' => $key,
            'precondition' => hash('sha256', 'precondition-'.$key),
            'identity' => ['type' => 'interface', 'path' => 'vue.'.$key],
            'group' => 'interface',
            'context' => 'Website interface',
            'field' => $key,
            'source' => $source,
            'target' => $target,
            'format' => $format,
            'status' => trim(strip_tags($target)) === '' ? 'missing' : 'translated',
            'required' => $required,
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function catalogFor(Collection $rows, array $translations): array
    {
        return [
            'schema' => BanglaTranslationCatalogImporter::SCHEMA,
            'source_locale' => 'en',
            'target_locale' => 'bn',
            'entries' => $rows->map(fn (array $row): array => [
                'key' => $row['key'],
                'source_hash' => hash('sha256', $row['source']),
                'source' => $row['source'],
                'translation' => $translations[$row['key']] ?? '',
                'preserve_source' => false,
            ])->values()->all(),
        ];
    }
}
