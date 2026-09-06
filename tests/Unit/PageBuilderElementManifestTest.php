<?php

namespace Tests\Unit;

use App\Support\PageBuilderElementManifest;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PageBuilderElementManifestTest extends TestCase
{
    private const TARGET_TYPES = [
        'heading',
        'rich_text',
        'image',
        'video',
        'button',
        'divider',
        'spacer',
        'icon',
        'file',
        'card',
        'stat',
        'quote',
        'gallery',
        'accordion',
        'timeline',
        'callout',
        'content_feed',
        'team',
        'giving',
        'managed_form',
    ];

    public function test_manifest_contains_each_target_type_once_with_a_complete_contract(): void
    {
        $manifest = PageBuilderElementManifest::all();
        $types = array_keys($manifest);
        $expectedTypes = self::TARGET_TYPES;
        sort($types);
        sort($expectedTypes);

        $this->assertSame($expectedTypes, $types);
        $this->assertCount(20, $manifest);
        $this->assertSame(1, PageBuilderElementManifest::VERSION);

        foreach ($manifest as $token => $element) {
            $this->assertSame($token, $element['type']);
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $token);
            $this->assertNotSame('', $element['label']);
            $this->assertNotSame('', $element['description']);
            $this->assertNotSame('', $element['icon']);
            $this->assertArrayHasKey($element['category'], PageBuilderElementManifest::categories());
            $this->assertContains($element['mode'], ['static', 'managed'], true);

            $this->assertSame($token, $element['defaults']['type']);
            $this->assertSame(
                array_merge(['id', 'type'], array_keys($element['fields'])),
                $element['allowed_fields']
            );
            $this->assertEmpty(array_diff(array_keys($element['defaults']), $element['allowed_fields']));
            $this->assertSame(
                [],
                array_values(array_intersect($element['translatable_fields'], $element['machine_fields']))
            );

            $this->assertGreaterThan(0, $element['safe_bounds']['max_serialized_bytes']);
            $this->assertGreaterThan(0, $element['safe_bounds']['max_instances_per_column']);
            $this->assertLessThanOrEqual(12, $element['safe_bounds']['max_instances_per_column']);

            $this->assertFieldDefinitionsAreComplete($element['fields']);
        }
    }

    public function test_categories_make_the_full_library_browsable_in_four_small_groups(): void
    {
        $groups = PageBuilderElementManifest::grouped();

        $this->assertSame(
            ['text_layout', 'media_files', 'highlights', 'website_content'],
            array_keys($groups)
        );
        $this->assertSame([7, 4, 5, 4], array_map(
            static fn (array $group): int => count($group['elements']),
            array_values($groups)
        ));

        $groupedTypes = [];
        foreach ($groups as $group) {
            $this->assertNotSame('', $group['label']);
            $this->assertNotSame('', $group['description']);
            foreach ($group['elements'] as $element) {
                $groupedTypes[] = $element['type'];
            }
        }

        sort($groupedTypes);
        $targetTypes = self::TARGET_TYPES;
        sort($targetTypes);
        $this->assertSame($targetTypes, $groupedTypes);
    }

    #[DataProvider('existingElementProvider')]
    public function test_existing_element_defaults_and_allowed_fields_remain_storage_compatible(
        string $type,
        array $defaults,
        array $allowedFields
    ): void {
        $element = PageBuilderElementManifest::get($type);

        $this->assertSame($defaults, $element['defaults']);
        $this->assertSame($allowedFields, $element['allowed_fields']);
    }

    /** @return iterable<string, array{string, array<string, mixed>, list<string>}> */
    public static function existingElementProvider(): iterable
    {
        yield 'heading' => [
            'heading',
            ['type' => 'heading', 'text' => 'New heading', 'level' => 'h2'],
            ['id', 'type', 'text', 'level'],
        ];
        yield 'formatted text' => [
            'rich_text',
            ['type' => 'rich_text', 'body' => '<p>Add your text here.</p>'],
            ['id', 'type', 'body'],
        ];
        yield 'image' => [
            'image',
            ['type' => 'image', 'path' => '', 'alt' => '', 'caption' => ''],
            ['id', 'type', 'path', 'alt', 'caption'],
        ];
        yield 'video' => [
            'video',
            ['type' => 'video', 'source_type' => 'upload', 'source' => '', 'title' => ''],
            ['id', 'type', 'source_type', 'source', 'title'],
        ];
        yield 'button' => [
            'button',
            ['type' => 'button', 'label' => 'Learn more', 'url' => '', 'style' => 'primary'],
            ['id', 'type', 'label', 'url', 'style'],
        ];
        yield 'divider' => [
            'divider',
            ['type' => 'divider'],
            ['id', 'type'],
        ];
        yield 'spacer' => [
            'spacer',
            ['type' => 'spacer', 'size' => 'medium'],
            ['id', 'type', 'size'],
        ];
    }

    public function test_translation_and_machine_paths_are_explicit_including_nested_items(): void
    {
        $heading = PageBuilderElementManifest::get('heading');
        $this->assertSame(['text'], $heading['translatable_fields']);
        $this->assertSame(['id', 'type', 'level'], $heading['machine_fields']);

        $gallery = PageBuilderElementManifest::get('gallery');
        $this->assertSame(
            ['items.*.alt', 'items.*.caption'],
            $gallery['translatable_fields']
        );
        $this->assertSame(
            ['id', 'type', 'items.*.id', 'items.*.path', 'columns', 'lightbox'],
            $gallery['machine_fields']
        );

        $timeline = PageBuilderElementManifest::get('timeline');
        $this->assertContains('items.*.heading', $timeline['translatable_fields'], true);
        $this->assertContains('items.*.icon', $timeline['machine_fields'], true);
    }

    public function test_safe_bounds_export_choices_lengths_repeater_limits_and_managed_resources(): void
    {
        $heading = PageBuilderElementManifest::get('heading');
        $this->assertSame(500, $heading['safe_bounds']['fields']['text']['max_length']);
        $this->assertSame(['h2', 'h3', 'h4'], $heading['safe_bounds']['fields']['level']['choices']);

        $icon = PageBuilderElementManifest::get('icon');
        $this->assertArrayNotHasKey('', $icon['fields']['icon']['options']);
        $this->assertSame('heart', $icon['defaults']['icon']);
        foreach (['card', 'stat', 'callout'] as $optionalIconType) {
            $this->assertArrayHasKey('', PageBuilderElementManifest::get($optionalIconType)['fields']['icon']['options']);
        }
        $this->assertArrayHasKey(
            '',
            PageBuilderElementManifest::get('timeline')['fields']['items']['item_fields']['icon']['options']
        );

        $gallery = PageBuilderElementManifest::get('gallery');
        $this->assertSame(12, $gallery['safe_bounds']['fields']['items']['max_items']);
        $this->assertSame(
            255,
            $gallery['safe_bounds']['fields']['items.*.alt']['max_length']
        );

        $giving = PageBuilderElementManifest::get('giving');
        $destinations = $giving['safe_bounds']['fields']['destination_ids'];
        $this->assertSame('managed_reference_list', $destinations['kind']);
        $this->assertSame('active_donation_destinations', $destinations['resource']);
        $this->assertSame('published', $destinations['scope']);
        $this->assertSame(8, $destinations['max_items']);
    }

    public function test_managed_elements_are_bounded_references_and_presets_not_arbitrary_logic(): void
    {
        $manifest = PageBuilderElementManifest::all();
        $managed = array_filter(
            $manifest,
            static fn (array $element): bool => $element['mode'] === 'managed'
        );

        $this->assertSame(
            ['content_feed', 'team', 'giving', 'managed_form'],
            array_keys($managed)
        );

        $forbiddenNames = [
            'query',
            'model',
            'endpoint',
            'webhook',
            'recipient',
            'gateway',
            'html',
            'css',
            'javascript',
            'script',
            'code',
        ];

        foreach ($managed as $element) {
            $fieldNames = $this->recursiveFieldNames($element['fields']);
            $this->assertSame([], array_values(array_intersect($forbiddenNames, $fieldNames)));

            $referenceFields = array_filter(
                $element['fields'],
                static fn (array $field): bool => in_array(
                    $field['kind'],
                    ['managed_reference', 'managed_reference_list'],
                    true
                )
            );
            $this->assertNotEmpty($referenceFields, "{$element['type']} needs a managed reference.");

            foreach ($referenceFields as $field) {
                $this->assertNotSame('', $field['resource']);
                $this->assertSame('published', $field['scope']);
                if ($field['kind'] === 'managed_reference_list') {
                    $this->assertGreaterThan(0, $field['bounds']['max_items']);
                }
            }
        }

        $managedForm = PageBuilderElementManifest::get('managed_form');
        $this->assertSame(
            ['id', 'type', 'heading', 'intro', 'form_id', 'presentation'],
            $managedForm['allowed_fields']
        );
        $this->assertArrayNotHasKey('recipient', $managedForm['fields']);

        $giving = PageBuilderElementManifest::get('giving');
        $this->assertArrayNotHasKey('url', $giving['fields']);
        $this->assertArrayNotHasKey('gateway', $giving['fields']);
    }

    public function test_lookup_helpers_reject_unknown_types(): void
    {
        $this->assertTrue(PageBuilderElementManifest::has('card'));
        $this->assertFalse(PageBuilderElementManifest::has('custom_html'));
        $this->assertSame('card', PageBuilderElementManifest::defaults('card')['type']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown page-builder element type [custom_html].');
        PageBuilderElementManifest::get('custom_html');
    }

    /** @param array<string, array<string, mixed>> $fields */
    private function assertFieldDefinitionsAreComplete(array $fields): void
    {
        foreach ($fields as $name => $field) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $name);
            $this->assertNotSame('', $field['label']);
            $this->assertNotSame('', $field['kind']);
            $this->assertIsBool($field['translatable']);
            $this->assertIsBool($field['required']);
            $this->assertArrayHasKey('default', $field);

            if (in_array($field['kind'], ['choice', 'approved_icon'], true)) {
                $this->assertNotEmpty($field['options']);
                $this->assertArrayHasKey((string) $field['default'], $field['options']);
            }
            if ($field['kind'] === 'repeater') {
                $this->assertGreaterThan(0, $field['bounds']['max_items']);
                $this->assertNotEmpty($field['item_fields']);
                $this->assertSame('id', $field['item_identity']);
                $this->assertSame('uuid', $field['item_fields']['id']['kind']);
                $this->assertFalse($field['item_fields']['id']['translatable']);
                $this->assertFieldDefinitionsAreComplete($field['item_fields']);
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @return list<string>
     */
    private function recursiveFieldNames(array $fields): array
    {
        $names = [];

        foreach ($fields as $name => $field) {
            $names[] = $name;
            $names = array_merge($names, $this->recursiveFieldNames((array) ($field['item_fields'] ?? [])));
        }

        return $names;
    }
}
