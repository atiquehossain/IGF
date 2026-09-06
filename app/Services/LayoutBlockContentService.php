<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Support\PageBuilderElementManifest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class LayoutBlockContentService
{
    public const CURRENT_SCHEMA_VERSION = 2;

    private const STABLE_ID_PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/iD';

    /**
     * Managed elements stay outside free-form columns until their published
     * record resolvers, permissions, and empty states can be enforced here.
     */
    private const STATIC_ELEMENT_TYPES = [
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

    private const IMAGE_MIME_TYPES = [
        'image/avif',
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    private const VIDEO_MIME_TYPES = ['video/mp4', 'video/webm'];

    private const GLOBAL_DESIGN_KEYS = [
        'section_presentation',
        'section_spacing',
        'content_alignment',
        'column_count',
    ];

    public function __construct(private ContentSanitizer $sanitizer)
    {
    }

    /**
     * Validate the closed visual-layout schema and return its canonical form.
     * Unknown nested keys never reach storage, and invalid values produce an
     * editor-friendly error at the exact row, column, or element path.
     */
    public function normalizeAndValidate(array $content, string $errorPrefix = 'content'): array
    {
        $this->assertCollectionBounds($content, $errorPrefix);
        $this->assertPayloadBound($content, $errorPrefix);

        // Version 2 introduced stable identities. Older stored layouts did not
        // have them, so those payloads are still allowed through the upgrader.
        // Once a layout declares version 2, however, losing an identity must be
        // treated as data corruption rather than silently changing identity.
        $declaredSchemaVersion = $content['schema_version'] ?? null;
        $requiresStableIds = is_numeric($declaredSchemaVersion)
            && (float) $declaredSchemaVersion === (float) self::CURRENT_SCHEMA_VERSION;
        $identityRules = $this->identityRules($requiresStableIds);

        $presets = (array) config('page-builder.layout.presets', []);
        $widths = (array) config('page-builder.layout.widths', []);
        $backgrounds = (array) config('page-builder.layout.backgrounds', []);
        $spacings = (array) config('page-builder.layout.spacings', []);

        $validator = Validator::make($content, [
            'schema_version' => ['sometimes', 'integer', Rule::in([1, self::CURRENT_SCHEMA_VERSION])],
            'section_presentation' => [
                'sometimes',
                'string',
                Rule::in(array_keys((array) config('page-builder.section_presentations', []))),
            ],
            'section_spacing' => [
                'sometimes',
                'string',
                Rule::in(array_keys((array) config('page-builder.section_spacing_options', []))),
            ],
            'content_alignment' => [
                'sometimes',
                'string',
                Rule::in(array_keys((array) config('page-builder.content_alignment_options', []))),
            ],
            'column_count' => [
                'sometimes',
                'string',
                Rule::in(array_keys((array) config('page-builder.column_count_options', []))),
            ],
            'rows' => ['present', 'array', 'max:' . $this->limit('rows', 12)],
            'rows.*' => ['required', 'array'],
            'rows.*.id' => $identityRules,
            'rows.*.layout' => ['required', 'string', Rule::in(array_keys($presets))],
            'rows.*.width' => ['required', 'string', Rule::in(array_keys($widths))],
            'rows.*.background' => ['required', 'string', Rule::in(array_keys($backgrounds))],
            'rows.*.spacing' => ['required', 'string', Rule::in(array_keys($spacings))],
            'rows.*.columns' => ['present', 'array', 'max:' . $this->limit('columns', 4)],
            'rows.*.columns.*' => ['required', 'array'],
            'rows.*.columns.*.id' => $identityRules,
            'rows.*.columns.*.elements' => [
                'present',
                'array',
                'max:' . $this->limit('elements_per_column', 12),
            ],
            'rows.*.columns.*.elements.*' => ['required', 'array'],
            'rows.*.columns.*.elements.*.id' => $identityRules,
            'rows.*.columns.*.elements.*.type' => [
                'required',
                'string',
                Rule::in(self::STATIC_ELEMENT_TYPES),
            ],
        ], [
            'rows.present' => 'Add a rows list to the visual layout.',
            'rows.array' => 'The visual layout rows must be a list.',
            'rows.max' => 'A visual layout can contain at most :max rows.',
            'rows.*.layout.required' => 'Choose a column preset for every row.',
            'rows.*.layout.in' => 'Choose one of the available column presets.',
            'rows.*.width.required' => 'Choose a content width for every row.',
            'rows.*.width.in' => 'Choose a supported row width.',
            'rows.*.background.required' => 'Choose a background for every row.',
            'rows.*.background.in' => 'Choose a background from the approved palette.',
            'rows.*.spacing.required' => 'Choose spacing for every row.',
            'rows.*.spacing.in' => 'Choose one of the available row spacing options.',
            'rows.*.columns.present' => 'Add the columns required by this row preset.',
            'rows.*.columns.array' => 'Each row must contain a columns list.',
            'rows.*.columns.max' => 'A row can contain at most :max columns.',
            'schema_version.in' => 'This visual layout was created by an unsupported editor version.',
            'section_presentation.in' => 'Choose one of the available section appearances.',
            'section_spacing.in' => 'Choose one of the available section spacing options.',
            'content_alignment.in' => 'Choose left or centered content alignment.',
            'column_count.in' => 'Choose an available responsive column count.',
            'rows.*.columns.*.elements.present' => 'Each column must contain an elements list.',
            'rows.*.columns.*.elements.array' => 'Column elements must be a list.',
            'rows.*.columns.*.elements.max' => 'A column can contain at most :max elements.',
            'rows.*.columns.*.elements.*.type.required' => 'Choose an element type.',
            'rows.*.columns.*.elements.*.type.in' => 'Choose one of the supported element types.',
            'rows.*.id.required' => 'This row lost its stable identity. Reload the editor and try again.',
            'rows.*.id.string' => 'This row has an invalid stable identity. Reload the editor and try again.',
            'rows.*.id.uuid' => 'This row has an invalid stable identity. Reload the editor and try again.',
            'rows.*.id.regex' => 'This row has an invalid stable identity. Reload the editor and try again.',
            'rows.*.columns.*.id.required' => 'This column lost its stable identity. Reload the editor and try again.',
            'rows.*.columns.*.id.string' => 'This column has an invalid stable identity. Reload the editor and try again.',
            'rows.*.columns.*.id.uuid' => 'This column has an invalid stable identity. Reload the editor and try again.',
            'rows.*.columns.*.id.regex' => 'This column has an invalid stable identity. Reload the editor and try again.',
            'rows.*.columns.*.elements.*.id.required' => 'This element lost its stable identity. Reload the editor and try again.',
            'rows.*.columns.*.elements.*.id.string' => 'This element has an invalid stable identity. Reload the editor and try again.',
            'rows.*.columns.*.elements.*.id.uuid' => 'This element has an invalid stable identity. Reload the editor and try again.',
            'rows.*.columns.*.elements.*.id.regex' => 'This element has an invalid stable identity. Reload the editor and try again.',
        ])->stopOnFirstFailure(false);

        if ($validator->fails()) {
            throw ValidationException::withMessages(
                $this->prefixErrors($validator->errors()->messages(), $errorPrefix)
            );
        }

        $errors = $this->unknownKeyErrors($content, $errorPrefix);
        $mediaReferences = [];

        if (!array_is_list($content['rows'])) {
            $errors["{$errorPrefix}.rows"] = 'Visual layout rows must be an ordered list.';
        }
        $rowIds = [];
        $columnIds = [];
        $elementIds = [];
        $subitemIds = [];

        foreach ($content['rows'] as $rowIndex => $row) {
            if (isset($row['id'])) {
                $rowIdentity = strtolower((string) $row['id']);
                if (isset($rowIds[$rowIdentity])) {
                    $errors["{$errorPrefix}.rows.{$rowIndex}.id"] = 'Every layout row must have a unique identity.';
                }
                $rowIds[$rowIdentity] = true;
            }
            $preset = $presets[$row['layout']];
            $expectedColumns = (int) ($preset['columns'] ?? 0);
            if (count($row['columns']) !== $expectedColumns) {
                $label = (string) ($preset['label'] ?? $row['layout']);
                $errors["{$errorPrefix}.rows.{$rowIndex}.columns"] =
                    "The {$label} preset requires exactly {$expectedColumns} " .
                    ($expectedColumns === 1 ? 'column.' : 'columns.');
            }
            if (!array_is_list($row['columns'])) {
                $errors["{$errorPrefix}.rows.{$rowIndex}.columns"] = 'Row columns must be an ordered list.';
            }

            foreach ($row['columns'] as $columnIndex => $column) {
                $columnPath = "{$errorPrefix}.rows.{$rowIndex}.columns.{$columnIndex}";
                $elementTypeCounts = [];
                if (isset($column['id'])) {
                    $columnIdentity = strtolower((string) $column['id']);
                    if (isset($columnIds[$columnIdentity])) {
                        $errors["{$columnPath}.id"] = 'Every layout column must have a unique identity.';
                    }
                    $columnIds[$columnIdentity] = true;
                }
                if (!array_is_list($column['elements'])) {
                    $errors["{$columnPath}.elements"] =
                        'Column elements must be an ordered list.';
                }
                foreach ($column['elements'] as $elementIndex => $element) {
                    $path = "rows.{$rowIndex}.columns.{$columnIndex}.elements.{$elementIndex}";
                    $fullPath = "{$errorPrefix}.{$path}";
                    $type = (string) $element['type'];
                    $elementTypeCounts[$type] = ($elementTypeCounts[$type] ?? 0) + 1;
                    $definition = $this->staticElementDefinition($type);
                    $instanceLimit = (int) data_get($definition, 'safe_bounds.max_instances_per_column', 12);
                    if ($elementTypeCounts[$type] > $instanceLimit) {
                        $errors["{$fullPath}.type"] = "A column can contain at most {$instanceLimit} {$definition['label']} elements.";
                    }
                    if (isset($element['id'])) {
                        $elementIdentity = strtolower((string) $element['id']);
                        if (isset($elementIds[$elementIdentity])) {
                            $errors["{$fullPath}.id"] = 'Every layout element must have a unique identity.';
                        }
                        $elementIds[$elementIdentity] = true;
                    }
                    $elementValidator = Validator::make(
                        $element,
                        $this->elementRules($type, $requiresStableIds),
                        $this->elementMessages($type)
                    )->stopOnFirstFailure(false);

                    if ($elementValidator->fails()) {
                        $errors += $this->prefixErrors(
                            $elementValidator->errors()->messages(),
                            $fullPath
                        );
                        continue;
                    }

                    $this->validateElementSemantics(
                        $element,
                        $definition,
                        $fullPath,
                        $errors,
                        $mediaReferences,
                        $subitemIds
                    );
                }
            }
        }

        $errors += $this->missingManagedMediaErrors($mediaReferences);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $this->normalize($content);
    }

    /**
     * Reject damaged or unsupported persisted layouts before another action
     * can repair, replace, duplicate, or connect them implicitly. Structurally
     * valid legacy layouts remain eligible for the explicit version-2 upgrade.
     */
    public function assertStoredVersionTwoIsValid(
        array $content,
        string $errorPrefix = 'content',
        ?array $incomingContent = null
    ): void {
        $hasDeclaredSchemaVersion = array_key_exists('schema_version', $content);
        $declaredSchemaVersion = $content['schema_version'] ?? null;
        $declaresVersionOne = (is_int($declaredSchemaVersion) || is_float($declaredSchemaVersion))
            && (float) $declaredSchemaVersion === 1.0;
        $declaresCurrentVersion = (is_int($declaredSchemaVersion) || is_float($declaredSchemaVersion))
            && (float) $declaredSchemaVersion === (float) self::CURRENT_SCHEMA_VERSION;
        if ($hasDeclaredSchemaVersion
            && ! $declaresVersionOne
            && ! $declaresCurrentVersion) {
            throw ValidationException::withMessages([
                "{$errorPrefix}.schema_version" => 'This saved visual layout was created by an unsupported editor version. Reload after the website software is updated; no content was changed.',
            ]);
        }

        $requiresStableIds = $declaresCurrentVersion;
        $this->assertStoredLayoutStructureIsValid($content, $errorPrefix, $requiresStableIds);

        $incomingSchemaVersion = $incomingContent['schema_version'] ?? null;
        $incomingDeclaresCurrentVersion = (is_int($incomingSchemaVersion) || is_float($incomingSchemaVersion))
            && (float) $incomingSchemaVersion === (float) self::CURRENT_SCHEMA_VERSION;
        if ($requiresStableIds
            && $incomingContent !== null
            && ! $incomingDeclaresCurrentVersion) {
            throw ValidationException::withMessages([
                "{$errorPrefix}.schema_version" => 'This visual layout changed after this editor draft was opened. Reload the editor before saving so translated content stays connected to the correct rows and elements.',
            ]);
        }
    }

    /**
     * Persisted translation drafts deliberately keep their version-2 machine
     * structure and identities while authored copy is blank. The corruption
     * guard therefore validates only the closed shape, supported types and
     * stable identities here; normal request validation still enforces all
     * authoring, URL and managed-media rules on the content being saved.
     */
    private function assertStoredLayoutStructureIsValid(
        array $content,
        string $errorPrefix,
        bool $requiresStableIds
    ): void
    {
        $this->assertCollectionBounds($content, $errorPrefix);
        $this->assertPayloadBound($content, $errorPrefix);

        $errors = [];
        $allowedContentKeys = array_merge(['schema_version', 'rows'], self::GLOBAL_DESIGN_KEYS);
        foreach (array_diff(array_keys($content), $allowedContentKeys) as $key) {
            $errors["{$errorPrefix}.{$key}"] = 'This saved visual layout contains an unsupported setting.';
        }

        $globalChoices = [
            'section_presentation' => 'page-builder.section_presentations',
            'section_spacing' => 'page-builder.section_spacing_options',
            'content_alignment' => 'page-builder.content_alignment_options',
            'column_count' => 'page-builder.column_count_options',
        ];
        foreach ($globalChoices as $key => $configKey) {
            if (! array_key_exists($key, $content)) {
                continue;
            }
            if (! is_string($content[$key])
                || ! array_key_exists($content[$key], (array) config($configKey, []))) {
                $errors["{$errorPrefix}.{$key}"] = 'This saved visual layout uses an unsupported design choice.';
            }
        }

        if (! array_key_exists('rows', $content) || ! is_array($content['rows'])) {
            $errors["{$errorPrefix}.rows"] = 'The saved visual layout rows list is damaged or missing.';
            throw ValidationException::withMessages($errors);
        }
        if (! array_is_list($content['rows'])) {
            $errors["{$errorPrefix}.rows"] = 'The saved visual layout rows must stay in order.';
        }

        $presets = (array) config('page-builder.layout.presets', []);
        $rowChoices = [
            'layout' => $presets,
            'width' => (array) config('page-builder.layout.widths', []),
            'background' => (array) config('page-builder.layout.backgrounds', []),
            'spacing' => (array) config('page-builder.layout.spacings', []),
        ];
        $usedIds = [
            'row' => [],
            'column' => [],
            'element' => [],
            'repeater' => [],
        ];
        $recordIdentity = function (mixed $value, string $path, string $kind) use (
            &$errors,
            &$usedIds,
            $requiresStableIds
        ): void {
            if ($value === null || $value === '') {
                if ($requiresStableIds) {
                    $errors[$path] = "This saved {$kind} lost its stable identity.";
                }

                return;
            }
            if (! $this->isStableLayoutId($value)) {
                $errors[$path] = "This saved {$kind} has a damaged stable identity.";

                return;
            }
            $identity = strtolower($value);
            if (isset($usedIds[$kind][$identity])) {
                $errors[$path] = "This saved {$kind} repeats an identity already in use.";

                return;
            }
            $usedIds[$kind][$identity] = true;
        };

        foreach ($content['rows'] as $rowIndex => $row) {
            $rowPath = "{$errorPrefix}.rows.{$rowIndex}";
            if (! is_array($row)) {
                $errors[$rowPath] = 'This saved row is not in a usable format.';

                continue;
            }
            foreach (array_diff(array_keys($row), ['id', 'layout', 'width', 'background', 'spacing', 'columns']) as $key) {
                $errors["{$rowPath}.{$key}"] = 'This saved row contains an unsupported setting.';
            }
            $recordIdentity($row['id'] ?? null, "{$rowPath}.id", 'row');
            foreach ($rowChoices as $key => $choices) {
                if (! isset($row[$key]) || ! is_string($row[$key]) || ! array_key_exists($row[$key], $choices)) {
                    $errors["{$rowPath}.{$key}"] = 'This saved row uses an unsupported design choice.';
                }
            }

            $columns = $row['columns'] ?? null;
            if (! is_array($columns)) {
                $errors["{$rowPath}.columns"] = 'This saved row has a damaged or missing columns list.';

                continue;
            }
            if (! array_is_list($columns)) {
                $errors["{$rowPath}.columns"] = 'The saved row columns must stay in order.';
            }
            $preset = is_string($row['layout'] ?? null) ? ($presets[$row['layout']] ?? null) : null;
            if (is_array($preset) && count($columns) !== (int) ($preset['columns'] ?? 0)) {
                $errors["{$rowPath}.columns"] = 'This saved row does not contain the number of columns required by its design.';
            }

            foreach ($columns as $columnIndex => $column) {
                $columnPath = "{$rowPath}.columns.{$columnIndex}";
                if (! is_array($column)) {
                    $errors[$columnPath] = 'This saved column is not in a usable format.';

                    continue;
                }
                foreach (array_diff(array_keys($column), ['id', 'elements']) as $key) {
                    $errors["{$columnPath}.{$key}"] = 'This saved column contains an unsupported setting.';
                }
                $recordIdentity($column['id'] ?? null, "{$columnPath}.id", 'column');
                $elements = $column['elements'] ?? null;
                if (! is_array($elements)) {
                    $errors["{$columnPath}.elements"] = 'This saved column has a damaged or missing content list.';

                    continue;
                }
                if (! array_is_list($elements)) {
                    $errors["{$columnPath}.elements"] = 'The saved column content must stay in order.';
                }

                $typeCounts = [];
                foreach ($elements as $elementIndex => $element) {
                    $elementPath = "{$columnPath}.elements.{$elementIndex}";
                    if (! is_array($element)) {
                        $errors[$elementPath] = 'This saved content item is not in a usable format.';

                        continue;
                    }
                    $type = is_string($element['type'] ?? null) ? $element['type'] : '';
                    if (! in_array($type, self::STATIC_ELEMENT_TYPES, true)) {
                        $errors["{$elementPath}.type"] = 'This saved content item uses a type this editor does not support.';

                        continue;
                    }
                    $definition = $this->staticElementDefinition($type);
                    foreach (array_diff(array_keys($element), $definition['allowed_fields']) as $key) {
                        $errors["{$elementPath}.{$key}"] = 'This saved content item contains an unsupported setting.';
                    }
                    $recordIdentity($element['id'] ?? null, "{$elementPath}.id", 'element');
                    $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
                    $instanceLimit = (int) data_get($definition, 'safe_bounds.max_instances_per_column', 12);
                    if ($typeCounts[$type] > $instanceLimit) {
                        $errors["{$elementPath}.type"] = "This saved column contains too many {$definition['label']} items.";
                    }

                    foreach ($definition['fields'] as $field => $fieldDefinition) {
                        $fieldPath = "{$elementPath}.{$field}";
                        if (! array_key_exists($field, $element)) {
                            if ((bool) ($fieldDefinition['required'] ?? false)) {
                                $errors[$fieldPath] = 'This saved content item is missing part of its structure.';
                            }

                            continue;
                        }
                        $shapeIssue = $this->storedFieldShapeIssue($element[$field], $fieldDefinition);
                        if ($shapeIssue !== null) {
                            $errors[$fieldPath] = $shapeIssue;

                            continue;
                        }
                        if (($fieldDefinition['kind'] ?? null) !== 'repeater') {
                            continue;
                        }

                        $items = $element[$field];
                        if (! array_is_list($items)) {
                            $errors[$fieldPath] = 'This saved list must stay in order.';
                        }
                        $maxItems = (int) data_get($fieldDefinition, 'bounds.max_items', 12);
                        if (count($items) > $maxItems) {
                            $errors[$fieldPath] = "This saved list can contain at most {$maxItems} items.";
                        }
                        $itemFields = (array) ($fieldDefinition['item_fields'] ?? []);
                        $itemIdentity = (string) ($fieldDefinition['item_identity'] ?? 'id');
                        foreach ($items as $itemIndex => $item) {
                            $itemPath = "{$fieldPath}.{$itemIndex}";
                            if (! is_array($item)) {
                                $errors[$itemPath] = 'This saved list item is not in a usable format.';

                                continue;
                            }
                            foreach (array_diff(array_keys($item), array_keys($itemFields)) as $key) {
                                $errors["{$itemPath}.{$key}"] = 'This saved list item contains an unsupported setting.';
                            }
                            $recordIdentity($item[$itemIdentity] ?? null, "{$itemPath}.{$itemIdentity}", 'repeater');
                            foreach ($itemFields as $itemField => $itemDefinition) {
                                if ($itemField === $itemIdentity) {
                                    continue;
                                }
                                if (! array_key_exists($itemField, $item)) {
                                    if ((bool) ($itemDefinition['required'] ?? false)) {
                                        $errors["{$itemPath}.{$itemField}"] = 'This saved list item is missing part of its structure.';
                                    }

                                    continue;
                                }
                                $itemShapeIssue = $this->storedFieldShapeIssue($item[$itemField], $itemDefinition);
                                if ($itemShapeIssue !== null) {
                                    $errors["{$itemPath}.{$itemField}"] = $itemShapeIssue;
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $definition */
    private function storedFieldShapeIssue(mixed $value, array $definition): ?string
    {
        if ($value === null && ! (bool) ($definition['required'] ?? false)) {
            return null;
        }

        $kind = (string) ($definition['kind'] ?? 'plain_text');
        if ($kind === 'repeater') {
            return is_array($value) ? null : 'This saved list is damaged.';
        }
        if ($kind === 'boolean') {
            return is_bool($value) ? null : 'This saved value is damaged.';
        }
        if ($kind === 'integer') {
            return is_int($value) ? null : 'This saved number is damaged.';
        }
        if ($kind === 'uuid') {
            return $this->isStableLayoutId($value) ? null : 'This saved identity is damaged.';
        }
        if (! is_string($value)) {
            return 'This saved value is damaged.';
        }
        if (in_array($kind, ['choice', 'approved_icon'], true)
            && ! array_key_exists($value, (array) ($definition['options'] ?? []))) {
            return 'This saved value uses an unsupported choice.';
        }

        return null;
    }

    /**
     * A duplicated section is a new translation family. Give its nested
     * identities the same clean break while preserving all authored content.
     */
    public function regenerateIdentifiers(array $content): array
    {
        $content['schema_version'] = self::CURRENT_SCHEMA_VERSION;

        if (!isset($content['rows']) || !is_array($content['rows'])) {
            return $content;
        }

        foreach ($content['rows'] as &$row) {
            if (!is_array($row)) {
                continue;
            }
            $row['id'] = (string) Str::uuid();
            if (!isset($row['columns']) || !is_array($row['columns'])) {
                continue;
            }
            foreach ($row['columns'] as &$column) {
                if (!is_array($column)) {
                    continue;
                }
                $column['id'] = (string) Str::uuid();
                if (!isset($column['elements']) || !is_array($column['elements'])) {
                    continue;
                }
                foreach ($column['elements'] as &$element) {
                    if (is_array($element)) {
                        $element['id'] = (string) Str::uuid();
                        $type = is_string($element['type'] ?? null) ? $element['type'] : '';
                        if (in_array($type, self::STATIC_ELEMENT_TYPES, true)) {
                            foreach ($this->staticElementDefinition($type)['fields'] as $field => $fieldDefinition) {
                                if (($fieldDefinition['kind'] ?? null) !== 'repeater'
                                    || ! is_array($element[$field] ?? null)) {
                                    continue;
                                }
                                $identity = (string) ($fieldDefinition['item_identity'] ?? '');
                                if ($identity === '') {
                                    continue;
                                }
                                foreach ($element[$field] as &$item) {
                                    if (is_array($item)) {
                                        $item[$identity] = (string) Str::uuid();
                                    }
                                }
                                unset($item);
                            }
                        }
                    }
                }
                unset($element);
            }
            unset($column);
        }
        unset($row);

        return $content;
    }

    private function assertPayloadBound(array $content, string $errorPrefix): void
    {
        $encoded = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $limit = $this->limit('payload_bytes', 524288);

        if (!is_string($encoded) || strlen($encoded) > $limit) {
            throw ValidationException::withMessages([
                $errorPrefix => 'This visual layout is too large to save. Reduce the number or length of its elements.',
            ]);
        }
    }

    /**
     * Check list sizes before Laravel expands wildcard rules. This keeps a
     * crafted oversized request cheap to reject even when its shape is wrong.
     */
    private function assertCollectionBounds(array $content, string $errorPrefix): void
    {
        $rows = $content['rows'] ?? null;
        if (!is_array($rows)) {
            return;
        }
        if (count($rows) > $this->limit('rows', 12)) {
            throw ValidationException::withMessages([
                "{$errorPrefix}.rows" => 'A visual layout can contain at most 12 rows.',
            ]);
        }

        foreach ($rows as $rowIndex => $row) {
            if (!is_array($row) || !is_array($row['columns'] ?? null)) {
                continue;
            }
            if (count($row['columns']) > $this->limit('columns', 4)) {
                throw ValidationException::withMessages([
                    "{$errorPrefix}.rows.{$rowIndex}.columns" => 'A row can contain at most 4 columns.',
                ]);
            }

            foreach ($row['columns'] as $columnIndex => $column) {
                if (!is_array($column) || !is_array($column['elements'] ?? null)) {
                    continue;
                }
                if (count($column['elements']) > $this->limit('elements_per_column', 12)) {
                    throw ValidationException::withMessages([
                        "{$errorPrefix}.rows.{$rowIndex}.columns.{$columnIndex}.elements" =>
                            'A column can contain at most 12 elements.',
                    ]);
                }

                foreach ($column['elements'] as $elementIndex => $element) {
                    if (! is_array($element)) {
                        continue;
                    }
                    $type = is_string($element['type'] ?? null) ? $element['type'] : '';
                    if (! in_array($type, self::STATIC_ELEMENT_TYPES, true)) {
                        continue;
                    }
                    $definition = $this->staticElementDefinition($type);
                    $elementPath = "{$errorPrefix}.rows.{$rowIndex}.columns.{$columnIndex}.elements.{$elementIndex}";
                    $encoded = json_encode($element, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $byteLimit = (int) data_get($definition, 'safe_bounds.max_serialized_bytes', 65536);
                    if (! is_string($encoded) || strlen($encoded) > $byteLimit) {
                        throw ValidationException::withMessages([
                            $elementPath => 'This element is too large to save.',
                        ]);
                    }

                    foreach ($definition['fields'] as $field => $fieldDefinition) {
                        if (($fieldDefinition['kind'] ?? null) !== 'repeater'
                            || ! is_array($element[$field] ?? null)) {
                            continue;
                        }
                        $maxItems = (int) data_get($fieldDefinition, 'bounds.max_items', 12);
                        if (count($element[$field]) > $maxItems) {
                            throw ValidationException::withMessages([
                                "{$elementPath}.{$field}" => "This list can contain at most {$maxItems} items.",
                            ]);
                        }
                    }
                }
            }
        }
    }

    /** @return array<string, array<int, string|object>> */
    private function elementRules(string $type, bool $requiresStableIds): array
    {
        $definition = $this->staticElementDefinition($type);
        $rules = [
            'id' => $this->identityRules($requiresStableIds),
            'type' => ['required', Rule::in([$type])],
        ];

        foreach ($definition['fields'] as $field => $fieldDefinition) {
            $rules[$field] = $this->fieldRules($fieldDefinition);
            if (($fieldDefinition['kind'] ?? null) !== 'repeater') {
                continue;
            }

            $rules["{$field}.*"] = ['required', 'array'];
            $itemIdentity = (string) ($fieldDefinition['item_identity'] ?? '');
            foreach ((array) ($fieldDefinition['item_fields'] ?? []) as $itemField => $itemDefinition) {
                $rules["{$field}.*.{$itemField}"] = $itemField === $itemIdentity
                    ? $this->identityRules($requiresStableIds)
                    : $this->fieldRules($itemDefinition);
            }
        }

        return $rules;
    }

    /** @return array<int, string|\Closure> */
    private function identityRules(bool $required): array
    {
        $rules = $required ? ['required', 'string'] : ['sometimes'];
        $rules[] = function (string $attribute, mixed $value, \Closure $fail) use ($required): void {
            if (! $required && ($value === null || $value === '')) {
                return;
            }
            if (! $this->isStableLayoutId($value)) {
                $fail('This item has an invalid stable identity. Reload the editor and try again.');
            }
        };

        return $rules;
    }

    /** @return array<int, string|object> */
    private function fieldRules(array $definition): array
    {
        $kind = (string) ($definition['kind'] ?? 'plain_text');
        $required = (bool) ($definition['required'] ?? false);
        $rules = $required
            ? (in_array($kind, ['boolean', 'repeater'], true) ? ['present'] : ['required'])
            : ['sometimes', 'nullable'];
        $bounds = (array) ($definition['bounds'] ?? []);

        if (in_array($kind, ['plain_text', 'rich_text', 'safe_link', 'managed_image', 'managed_file', 'approved_video_source'], true)) {
            $rules[] = 'string';
            if (isset($bounds['max_length'])) {
                $rules[] = 'max:' . (int) $bounds['max_length'];
            }
        } elseif (in_array($kind, ['choice', 'approved_icon'], true)) {
            $rules[] = 'string';
            $rules[] = Rule::in(array_keys((array) ($definition['options'] ?? [])));
        } elseif ($kind === 'boolean') {
            $rules[] = 'boolean';
        } elseif ($kind === 'uuid') {
            $rules[] = 'uuid';
            $rules[] = 'regex:' . self::STABLE_ID_PATTERN;
        } elseif ($kind === 'repeater') {
            $rules[] = 'array';
            if (isset($bounds['min_items'])) {
                $rules[] = 'min:' . (int) $bounds['min_items'];
            }
            if (isset($bounds['max_items'])) {
                $rules[] = 'max:' . (int) $bounds['max_items'];
            }
        } elseif ($kind === 'integer') {
            $rules[] = 'integer';
            if (isset($bounds['min'])) {
                $rules[] = 'min:' . (int) $bounds['min'];
            }
            if (isset($bounds['max'])) {
                $rules[] = 'max:' . (int) $bounds['max'];
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function elementMessages(string $type): array
    {
        $name = $this->staticElementDefinition($type)['label'];
        $pathRequired = $type === 'file'
            ? 'Choose a document from the Media Library.'
            : 'Choose an image from the Media Library.';
        $labelRequired = match ($type) {
            'file' => 'Enter link text for this download.',
            'stat' => 'Describe what this impact number measures.',
            default => 'Enter text for this button.',
        };

        return [
            'id.required' => 'This element lost its stable identity. Reload the editor and try again.',
            'id.string' => 'This element has an invalid stable identity. Reload the editor and try again.',
            'id.uuid' => 'This element has an invalid stable identity. Reload the editor and try again.',
            'text.required' => 'Enter text for this heading.',
            'text.max' => 'Heading text may not exceed :max characters.',
            'level.in' => 'Choose Heading 2, Heading 3, or Heading 4.',
            'body.present' => 'Add a body field to this rich text element.',
            'body.max' => 'Rich text may not exceed :max characters.',
            'path.required' => $pathRequired,
            'alt.present' => 'Add image alternative text, or leave it empty only when the image is decorative.',
            'alt.max' => 'Image alternative text may not exceed :max characters.',
            'caption.max' => 'An image caption may not exceed :max characters.',
            'source_type.in' => 'Choose an uploaded video or YouTube.',
            'source.required' => 'Choose or enter a source for this video.',
            'title.required' => 'Enter an accessible title for this video.',
            'label.required' => $labelRequired,
            'value.required' => 'Enter the impact number or value to show.',
            'heading.required' => "Enter a heading for this {$name}.",
            'quote.required' => 'Enter the quotation to show.',
            'url.required' => 'Choose a destination for this button.',
            'style.in' => 'Choose a supported button style.',
            'size.in' => 'Choose a supported spacer size.',
            'type.in' => "Choose a supported {$name} element type.",
            'items.array' => 'Add items using the provided list editor.',
            'items.max' => 'This list contains too many items.',
            'items.*.id.required' => 'This list item lost its stable identity. Reload the editor and try again.',
            'items.*.id.string' => 'This list item has an invalid stable identity. Reload the editor and try again.',
            'items.*.id.uuid' => 'This list item has an invalid stable identity. Reload the editor and try again.',
            'items.*.id.regex' => 'This list item has an invalid stable identity. Reload the editor and try again.',
            'items.*.path.required' => 'Choose an image for every gallery item.',
            'items.*.question.required' => 'Enter a question for every answer item.',
            'items.*.heading.required' => 'Enter a heading for every timeline item.',
        ];
    }

    /** @return array<string, string> */
    private function unknownKeyErrors(array $content, string $errorPrefix): array
    {
        $errors = [];
        $allowedContentKeys = array_merge(['schema_version', 'rows'], self::GLOBAL_DESIGN_KEYS);
        foreach (array_diff(array_keys($content), $allowedContentKeys) as $key) {
            $errors["{$errorPrefix}.{$key}"] = 'This visual layout setting is not supported.';
        }

        foreach ($content['rows'] as $rowIndex => $row) {
            foreach (array_diff(array_keys($row), ['id', 'layout', 'width', 'background', 'spacing', 'columns']) as $key) {
                $errors["{$errorPrefix}.rows.{$rowIndex}.{$key}"] = 'This row setting is not supported.';
            }
            foreach ($row['columns'] as $columnIndex => $column) {
                foreach (array_diff(array_keys($column), ['id', 'elements']) as $key) {
                    $errors["{$errorPrefix}.rows.{$rowIndex}.columns.{$columnIndex}.{$key}"] = 'This column setting is not supported.';
                }
                foreach ($column['elements'] as $elementIndex => $element) {
                    $type = (string) $element['type'];
                    $definition = $this->staticElementDefinition($type);
                    $elementPath = "{$errorPrefix}.rows.{$rowIndex}.columns.{$columnIndex}.elements.{$elementIndex}";
                    foreach (array_diff(array_keys($element), $definition['allowed_fields']) as $key) {
                        $errors["{$elementPath}.{$key}"] =
                            'This element setting is not supported.';
                    }

                    foreach ($definition['fields'] as $field => $fieldDefinition) {
                        if (($fieldDefinition['kind'] ?? null) !== 'repeater'
                            || ! is_array($element[$field] ?? null)) {
                            continue;
                        }
                        $allowedItemKeys = array_keys((array) ($fieldDefinition['item_fields'] ?? []));
                        foreach ($element[$field] as $itemIndex => $item) {
                            if (! is_array($item)) {
                                continue;
                            }
                            foreach (array_diff(array_keys($item), $allowedItemKeys) as $key) {
                                $errors["{$elementPath}.{$field}.{$itemIndex}.{$key}"] =
                                    'This list item setting is not supported.';
                            }
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, string|array<int, array<string, mixed>>>  $element
     * @param  array<string, mixed>  $definition
     * @param  array<string, string>  $errors
     * @param  array<string, list<array{allowed_mime_types:list<string>,error_path:string,message:string}>>  $mediaReferences
     * @param  array<string, true>  $subitemIds
     */
    private function validateElementSemantics(
        array $element,
        array $definition,
        string $elementPath,
        array &$errors,
        array &$mediaReferences,
        array &$subitemIds
    ): void {
        foreach ($definition['fields'] as $field => $fieldDefinition) {
            if (! array_key_exists($field, $element)) {
                continue;
            }
            $fieldPath = "{$elementPath}.{$field}";
            if (($fieldDefinition['kind'] ?? null) !== 'repeater') {
                $this->validateFieldSemantics(
                    $element[$field],
                    $fieldDefinition,
                    $fieldPath,
                    $element,
                    $errors,
                    $mediaReferences
                );
                continue;
            }

            if (! is_array($element[$field])) {
                continue;
            }
            if (! array_is_list($element[$field])) {
                $errors[$fieldPath] = 'List items must stay in an ordered list.';
            }

            $identityField = (string) ($fieldDefinition['item_identity'] ?? '');
            foreach ($element[$field] as $itemIndex => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $itemPath = "{$fieldPath}.{$itemIndex}";
                if ($identityField !== '' && isset($item[$identityField])) {
                    $identity = strtolower((string) $item[$identityField]);
                    if (isset($subitemIds[$identity])) {
                        $errors["{$itemPath}.{$identityField}"] = 'Every list item must have a unique identity.';
                    }
                    $subitemIds[$identity] = true;
                }
                foreach ((array) ($fieldDefinition['item_fields'] ?? []) as $itemField => $itemDefinition) {
                    if (! array_key_exists($itemField, $item)) {
                        continue;
                    }
                    $this->validateFieldSemantics(
                        $item[$itemField],
                        $itemDefinition,
                        "{$itemPath}.{$itemField}",
                        $item,
                        $errors,
                        $mediaReferences
                    );
                }
            }
        }

        $type = (string) $element['type'];
        if ($type === 'icon'
            && ! (bool) ($element['decorative'] ?? true)
            && trim((string) ($element['accessible_label'] ?? '')) === '') {
            $errors["{$elementPath}.accessible_label"] = 'Describe this meaningful icon for screen readers.';
        }

        if (in_array($type, ['card', 'callout'], true)
            && trim((string) ($element['url'] ?? '')) !== ''
            && trim((string) ($element['link_label'] ?? '')) === '') {
            $errors["{$elementPath}.link_label"] = 'Add link text for this destination.';
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $owner
     * @param  array<string, string>  $errors
     * @param  array<string, list<array{allowed_mime_types:list<string>,error_path:string,message:string}>>  $mediaReferences
     */
    private function validateFieldSemantics(
        mixed $value,
        array $definition,
        string $fieldPath,
        array $owner,
        array &$errors,
        array &$mediaReferences
    ): void {
        $kind = (string) ($definition['kind'] ?? '');
        if ($kind === 'safe_link') {
            if (trim((string) $value) !== '' && $this->normalizeSafeLink((string) $value) === null) {
                $errors[$fieldPath] = 'Use an internal path beginning with /, a page anchor beginning with #, or a secure https:// link.';
            }

            return;
        }

        if ($kind === 'approved_video_source') {
            if (($owner['source_type'] ?? null) === 'youtube') {
                if ($this->normalizeYouTubeUrl((string) $value) === null) {
                    $errors[$fieldPath] = 'Enter a secure YouTube link with a valid 11-character video ID.';
                }

                return;
            }

            $this->collectManagedMediaReference(
                (string) $value,
                'video',
                self::VIDEO_MIME_TYPES,
                $fieldPath,
                'Choose an MP4 or WebM video from the Media Library.',
                $errors,
                $mediaReferences
            );

            return;
        }

        if (! in_array($kind, ['managed_image', 'managed_file'], true) || trim((string) $value) === '') {
            return;
        }

        $isImage = $kind === 'managed_image';
        $this->collectManagedMediaReference(
            (string) $value,
            $isImage ? 'image' : 'file',
            $isImage ? self::IMAGE_MIME_TYPES : array_values((array) data_get($definition, 'bounds.mime_types', [])),
            $fieldPath,
            $isImage
                ? 'Choose an image from the Media Library.'
                : 'Choose a supported public document from the Media Library.',
            $errors,
            $mediaReferences
        );
    }

    /**
     * @param  list<string>  $allowedMimeTypes
     * @param  array<string, string>  $errors
     * @param  array<string, list<array{allowed_mime_types:list<string>,error_path:string,message:string}>>  $mediaReferences
     */
    private function collectManagedMediaReference(
        string $value,
        string $kind,
        array $allowedMimeTypes,
        string $errorPath,
        string $message,
        array &$errors,
        array &$mediaReferences
    ): void {
        $relative = $this->managedMediaRelativePath($value, $kind);
        if ($relative === null) {
            $errors[$errorPath] = $message;

            return;
        }

        $mediaReferences[$relative][] = [
            'allowed_mime_types' => $allowedMimeTypes,
            'error_path' => $errorPath,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, list<array{allowed_mime_types:list<string>,error_path:string,message:string}>>  $references
     * @return array<string, string>
     */
    private function missingManagedMediaErrors(array $references): array
    {
        if ($references === []) {
            return [];
        }

        $assets = MediaAsset::query()
            ->where('disk', 'public')
            ->whereIn('path', array_keys($references))
            ->get(['path', 'mime_type'])
            ->keyBy(fn (MediaAsset $asset): string => ltrim(str_replace('\\', '/', (string) $asset->path), '/'));
        $errors = [];

        foreach ($references as $relative => $uses) {
            $asset = $assets->get($relative);
            foreach ($uses as $use) {
                $valid = $asset instanceof MediaAsset
                    && Storage::disk('public')->exists($relative)
                    && in_array((string) $asset->mime_type, $use['allowed_mime_types'], true);
                if (!$valid) {
                    $errors[$use['error_path']] = $use['message'];
                }
            }
        }

        return $errors;
    }

    private function normalize(array $content): array
    {
        $normalized = ['schema_version' => self::CURRENT_SCHEMA_VERSION];
        foreach (self::GLOBAL_DESIGN_KEYS as $key) {
            if (array_key_exists($key, $content)) {
                $normalized[$key] = $content[$key];
            }
        }

        $normalized['rows'] = array_map(function (array $row): array {
            return [
                'id' => $this->isStableLayoutId($row['id'] ?? null)
                    ? (string) $row['id']
                    : (string) Str::uuid(),
                'layout' => $row['layout'],
                'width' => $row['width'],
                'background' => $row['background'],
                'spacing' => $row['spacing'],
                'columns' => array_map(fn (array $column): array => [
                    'id' => $this->isStableLayoutId($column['id'] ?? null)
                        ? (string) $column['id']
                        : (string) Str::uuid(),
                    'elements' => array_map(
                        fn (array $element): array => $this->normalizeElement($element),
                        $column['elements']
                    ),
                ], $row['columns']),
            ];
        }, $content['rows']);

        return $normalized;
    }

    private function normalizeElement(array $element): array
    {
        $definition = $this->staticElementDefinition((string) $element['type']);
        $normalized = [
            'id' => $this->isStableLayoutId($element['id'] ?? null)
                ? (string) $element['id']
                : (string) Str::uuid(),
            'type' => $element['type'],
        ];

        foreach ($definition['fields'] as $field => $fieldDefinition) {
            $value = array_key_exists($field, $element)
                ? $element[$field]
                : ($fieldDefinition['default'] ?? null);
            $normalized[$field] = $this->normalizeField($value, $fieldDefinition, $element);
        }

        return $normalized;
    }

    /** @param array<string, mixed> $definition @param array<string, mixed> $owner */
    private function normalizeField(mixed $value, array $definition, array $owner): mixed
    {
        $kind = (string) ($definition['kind'] ?? 'plain_text');

        if ($kind === 'plain_text') {
            return $this->normalizePlainText($value);
        }
        if ($kind === 'rich_text') {
            return $this->sanitizer->sanitizeLayoutRichText((string) $value);
        }
        if ($kind === 'safe_link') {
            return trim((string) $value) === '' ? '' : $this->normalizeSafeLink((string) $value);
        }
        if ($kind === 'managed_image') {
            return trim((string) $value) === ''
                ? ''
                : '/storage/' . $this->managedMediaRelativePath((string) $value, 'image');
        }
        if ($kind === 'managed_file') {
            return trim((string) $value) === ''
                ? ''
                : '/storage/' . $this->managedMediaRelativePath((string) $value, 'file');
        }
        if ($kind === 'approved_video_source') {
            return ($owner['source_type'] ?? null) === 'youtube'
                ? $this->normalizeYouTubeUrl((string) $value)
                : '/storage/' . $this->managedMediaRelativePath((string) $value, 'video');
        }
        if ($kind === 'boolean') {
            return (bool) $value;
        }
        if ($kind === 'integer') {
            return (int) $value;
        }
        if (in_array($kind, ['choice', 'approved_icon'], true)) {
            return trim((string) $value);
        }
        if ($kind === 'uuid') {
            return $this->isStableLayoutId($value) ? $value : (string) Str::uuid();
        }
        if ($kind === 'repeater') {
            return array_map(function (array $item) use ($definition): array {
                $normalized = [];
                foreach ((array) ($definition['item_fields'] ?? []) as $field => $fieldDefinition) {
                    $itemValue = array_key_exists($field, $item)
                        ? $item[$field]
                        : ($fieldDefinition['default'] ?? null);
                    $normalized[$field] = $this->normalizeField($itemValue, $fieldDefinition, $item);
                }

                return $normalized;
            }, is_array($value) ? $value : []);
        }

        return is_string($value) ? trim($value) : $value;
    }

    private function normalizePlainText(mixed $value): string
    {
        return trim(strip_tags(str_replace("\0", '', (string) $value)));
    }

    private function isStableLayoutId(mixed $value): bool
    {
        return is_string($value) && preg_match(self::STABLE_ID_PATTERN, $value) === 1;
    }

    /** @return array<string, mixed> */
    private function staticElementDefinition(string $type): array
    {
        if (! in_array($type, self::STATIC_ELEMENT_TYPES, true)) {
            throw new \InvalidArgumentException("Unsupported static page-builder element [{$type}].");
        }

        $definition = PageBuilderElementManifest::get($type);
        if (($definition['mode'] ?? null) !== 'static') {
            throw new \LogicException("Managed page-builder element [{$type}] cannot be used inside a visual layout.");
        }

        return $definition;
    }

    private function normalizeSafeLink(string $value): ?string
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === ''
            || str_contains($value, '\\')
            || preg_match('/[\x00-\x20\x7F]/u', $value) === 1) {
            return null;
        }

        if (str_starts_with($value, '#')) {
            return $this->sanitizer->sanitizeUrl($value) ?: null;
        }
        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            return $this->sanitizer->sanitizeUrl($value) ?: null;
        }
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($value);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        return $this->sanitizer->sanitizeUrl($value) ?: null;
    }

    private function normalizeYouTubeUrl(string $value): ?string
    {
        $value = trim($value);
        $sanitized = $this->sanitizer->sanitizeUrl($value);
        if (!str_starts_with(strtolower($value), 'https://')
            || $sanitized === ''
            || !hash_equals($value, $sanitized)
            || preg_match('/[\x00-\x20\x7F]/u', $value) === 1
            || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($value);
        if (!is_array($parts)
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            return null;
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $path = (string) ($parts['path'] ?? '');
        $videoId = null;

        if ($host === 'youtu.be') {
            $candidate = trim($path, '/');
            $videoId = str_contains($candidate, '/') ? null : $candidate;
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com'], true)) {
            if (rtrim($path, '/') === '/watch') {
                parse_str((string) ($parts['query'] ?? ''), $query);
                $videoId = is_string($query['v'] ?? null) ? $query['v'] : null;
            } elseif (preg_match('#\A/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})/?\z#', $path, $match) === 1) {
                $videoId = $match[1];
            }
        } elseif (in_array($host, ['youtube-nocookie.com', 'www.youtube-nocookie.com'], true)
            && preg_match('#\A/embed/([A-Za-z0-9_-]{11})/?\z#', $path, $match) === 1) {
            $videoId = $match[1];
        }

        return is_string($videoId) && preg_match('/\A[A-Za-z0-9_-]{11}\z/', $videoId) === 1
            ? 'https://www.youtube.com/watch?v=' . $videoId
            : null;
    }

    private function managedMediaRelativePath(string $value, string $kind): ?string
    {
        $value = trim($value);
        $sanitized = $this->sanitizer->sanitizeUrl($value);
        if ($value === ''
            || $sanitized === ''
            || !hash_equals($value, $sanitized)
            || str_contains($value, '\\')
            || preg_match('/[\x00-\x20\x7F]/u', $value) === 1) {
            return null;
        }

        $parts = parse_url($value);
        if (!is_array($parts)
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            return null;
        }

        $extensions = match ($kind) {
            'image' => 'avif|gif|jpe?g|png|webp',
            'video' => 'mp4|webm',
            'file' => 'docx?|pdf|xlsx?',
            default => '(?!)',
        };
        $path = (string) ($parts['path'] ?? '');
        if (preg_match(
            '#\A/storage/media/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9][A-Za-z0-9._-]*\.(?:' . $extensions . ')\z#iD',
            $path
        ) !== 1) {
            return null;
        }

        return ltrim(substr($path, strlen('/storage/')), '/');
    }

    /** @param array<string, list<string>> $errors */
    private function prefixErrors(array $errors, string $prefix): array
    {
        $prefixed = [];
        foreach ($errors as $path => $messages) {
            $prefixed[$prefix . ($path === '' ? '' : '.' . $path)] = $messages;
        }

        return $prefixed;
    }

    private function limit(string $key, int $fallback): int
    {
        return max(1, (int) config("page-builder.layout.limits.{$key}", $fallback));
    }
}
