<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class LayoutBlockContentService
{
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

        $presets = (array) config('page-builder.layout.presets', []);
        $widths = (array) config('page-builder.layout.widths', []);
        $backgrounds = (array) config('page-builder.layout.backgrounds', []);
        $spacings = (array) config('page-builder.layout.spacings', []);
        $elementTypes = (array) config('page-builder.layout.element_types', []);

        $validator = Validator::make($content, [
            'rows' => ['present', 'array', 'max:' . $this->limit('rows', 12)],
            'rows.*' => ['required', 'array'],
            'rows.*.id' => ['sometimes', 'uuid'],
            'rows.*.layout' => ['required', 'string', Rule::in(array_keys($presets))],
            'rows.*.width' => ['required', 'string', Rule::in(array_keys($widths))],
            'rows.*.background' => ['required', 'string', Rule::in(array_keys($backgrounds))],
            'rows.*.spacing' => ['required', 'string', Rule::in(array_keys($spacings))],
            'rows.*.columns' => ['present', 'array', 'max:' . $this->limit('columns', 4)],
            'rows.*.columns.*' => ['required', 'array'],
            'rows.*.columns.*.elements' => [
                'present',
                'array',
                'max:' . $this->limit('elements_per_column', 12),
            ],
            'rows.*.columns.*.elements.*' => ['required', 'array'],
            'rows.*.columns.*.elements.*.id' => ['sometimes', 'uuid'],
            'rows.*.columns.*.elements.*.type' => [
                'required',
                'string',
                Rule::in(array_keys($elementTypes)),
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
            'rows.*.columns.*.elements.present' => 'Each column must contain an elements list.',
            'rows.*.columns.*.elements.array' => 'Column elements must be a list.',
            'rows.*.columns.*.elements.max' => 'A column can contain at most :max elements.',
            'rows.*.columns.*.elements.*.type.required' => 'Choose an element type.',
            'rows.*.columns.*.elements.*.type.in' => 'Choose one of the supported element types.',
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
        $elementIds = [];

        foreach ($content['rows'] as $rowIndex => $row) {
            if (isset($row['id'])) {
                if (isset($rowIds[$row['id']])) {
                    $errors["{$errorPrefix}.rows.{$rowIndex}.id"] = 'Every layout row must have a unique identity.';
                }
                $rowIds[$row['id']] = true;
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
                if (!array_is_list($column['elements'])) {
                    $errors["{$errorPrefix}.rows.{$rowIndex}.columns.{$columnIndex}.elements"] =
                        'Column elements must be an ordered list.';
                }
                foreach ($column['elements'] as $elementIndex => $element) {
                    $path = "rows.{$rowIndex}.columns.{$columnIndex}.elements.{$elementIndex}";
                    $fullPath = "{$errorPrefix}.{$path}";
                    $type = (string) $element['type'];
                    if (isset($element['id'])) {
                        if (isset($elementIds[$element['id']])) {
                            $errors["{$fullPath}.id"] = 'Every layout element must have a unique identity.';
                        }
                        $elementIds[$element['id']] = true;
                    }
                    $elementValidator = Validator::make(
                        $element,
                        $this->elementRules($type),
                        $this->elementMessages($type)
                    )->stopOnFirstFailure(false);

                    if ($elementValidator->fails()) {
                        $errors += $this->prefixErrors(
                            $elementValidator->errors()->messages(),
                            $fullPath
                        );
                        continue;
                    }

                    if ($type === 'button') {
                        if ($this->normalizeSafeLink((string) $element['url']) === null) {
                            $errors["{$fullPath}.url"] = 'Use an internal path beginning with /, a page anchor beginning with #, or a secure https:// link.';
                        }
                    } elseif ($type === 'image') {
                        $relative = $this->managedMediaRelativePath((string) $element['path'], 'image');
                        if ($relative === null) {
                            $errors["{$fullPath}.path"] = 'Choose an image from the Media Library.';
                        } else {
                            $mediaReferences[$relative][] = ['kind' => 'image', 'error_path' => "{$fullPath}.path"];
                        }
                    } elseif ($type === 'video' && $element['source_type'] === 'upload') {
                        $relative = $this->managedMediaRelativePath((string) $element['source'], 'video');
                        if ($relative === null) {
                            $errors["{$fullPath}.source"] = 'Choose an MP4 or WebM video from the Media Library.';
                        } else {
                            $mediaReferences[$relative][] = ['kind' => 'video', 'error_path' => "{$fullPath}.source"];
                        }
                    } elseif ($type === 'video'
                        && $this->normalizeYouTubeUrl((string) $element['source']) === null) {
                        $errors["{$fullPath}.source"] = 'Enter a secure YouTube link with a valid 11-character video ID.';
                    }
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
     * A duplicated section is a new translation family. Give its nested
     * identities the same clean break while preserving all authored content.
     */
    public function regenerateIdentifiers(array $content): array
    {
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
                if (!isset($column['elements']) || !is_array($column['elements'])) {
                    continue;
                }
                foreach ($column['elements'] as &$element) {
                    if (is_array($element)) {
                        $element['id'] = (string) Str::uuid();
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
            }
        }
    }

    /** @return array<string, array<int, string|object>> */
    private function elementRules(string $type): array
    {
        return match ($type) {
            'heading' => [
                'type' => ['required', Rule::in(['heading'])],
                'text' => ['required', 'string', 'max:500'],
                'level' => ['required', 'string', Rule::in(array_keys((array) config('page-builder.layout.heading_levels', [])))],
            ],
            'rich_text' => [
                'type' => ['required', Rule::in(['rich_text'])],
                'body' => ['present', 'nullable', 'string', 'max:20000'],
            ],
            'image' => [
                'type' => ['required', Rule::in(['image'])],
                'path' => ['required', 'string', 'max:2048'],
                'alt' => ['present', 'nullable', 'string', 'max:255'],
                'caption' => ['sometimes', 'nullable', 'string', 'max:1000'],
            ],
            'video' => [
                'type' => ['required', Rule::in(['video'])],
                'source_type' => [
                    'required',
                    'string',
                    Rule::in(array_keys((array) config('page-builder.layout.video_source_types', []))),
                ],
                'source' => ['required', 'string', 'max:2048'],
                'title' => ['required', 'string', 'max:255'],
            ],
            'button' => [
                'type' => ['required', Rule::in(['button'])],
                'label' => ['required', 'string', 'max:120'],
                'url' => ['required', 'string', 'max:2048'],
                'style' => ['required', 'string', Rule::in(array_keys((array) config('page-builder.layout.button_styles', [])))],
            ],
            'divider' => [
                'type' => ['required', Rule::in(['divider'])],
            ],
            'spacer' => [
                'type' => ['required', Rule::in(['spacer'])],
                'size' => ['required', 'string', Rule::in(array_keys((array) config('page-builder.layout.spacer_sizes', [])))],
            ],
            default => [
                'type' => ['required', Rule::in(array_keys((array) config('page-builder.layout.element_types', [])))],
            ],
        };
    }

    /** @return array<string, string> */
    private function elementMessages(string $type): array
    {
        $name = (string) data_get(config('page-builder.layout.element_types', []), "{$type}", 'layout');

        return [
            'text.required' => 'Enter text for this heading.',
            'text.max' => 'Heading text may not exceed :max characters.',
            'level.in' => 'Choose Heading 2, Heading 3, or Heading 4.',
            'body.present' => 'Add a body field to this rich text element.',
            'body.max' => 'Rich text may not exceed :max characters.',
            'path.required' => 'Choose an image from the Media Library.',
            'alt.present' => 'Add image alternative text, or leave it empty only when the image is decorative.',
            'alt.max' => 'Image alternative text may not exceed :max characters.',
            'caption.max' => 'An image caption may not exceed :max characters.',
            'source_type.in' => 'Choose an uploaded video or YouTube.',
            'source.required' => 'Choose or enter a source for this video.',
            'title.required' => 'Enter an accessible title for this video.',
            'label.required' => 'Enter a label for this button.',
            'url.required' => 'Choose a destination for this button.',
            'style.in' => 'Choose a supported button style.',
            'size.in' => 'Choose a supported spacer size.',
            'type.in' => "Choose a supported {$name} element type.",
        ];
    }

    /** @return array<string, string> */
    private function unknownKeyErrors(array $content, string $errorPrefix): array
    {
        $errors = [];
        $allowedContentKeys = array_merge(['rows'], self::GLOBAL_DESIGN_KEYS);
        foreach (array_diff(array_keys($content), $allowedContentKeys) as $key) {
            $errors["{$errorPrefix}.{$key}"] = 'This visual layout setting is not supported.';
        }

        $allowedElementKeys = [
            'heading' => ['id', 'type', 'text', 'level'],
            'rich_text' => ['id', 'type', 'body'],
            'image' => ['id', 'type', 'path', 'alt', 'caption'],
            'video' => ['id', 'type', 'source_type', 'source', 'title'],
            'button' => ['id', 'type', 'label', 'url', 'style'],
            'divider' => ['id', 'type'],
            'spacer' => ['id', 'type', 'size'],
        ];

        foreach ($content['rows'] as $rowIndex => $row) {
            foreach (array_diff(array_keys($row), ['id', 'layout', 'width', 'background', 'spacing', 'columns']) as $key) {
                $errors["{$errorPrefix}.rows.{$rowIndex}.{$key}"] = 'This row setting is not supported.';
            }
            foreach ($row['columns'] as $columnIndex => $column) {
                foreach (array_diff(array_keys($column), ['elements']) as $key) {
                    $errors["{$errorPrefix}.rows.{$rowIndex}.columns.{$columnIndex}.{$key}"] = 'This column setting is not supported.';
                }
                foreach ($column['elements'] as $elementIndex => $element) {
                    $type = (string) $element['type'];
                    foreach (array_diff(array_keys($element), $allowedElementKeys[$type] ?? ['type']) as $key) {
                        $errors["{$errorPrefix}.rows.{$rowIndex}.columns.{$columnIndex}.elements.{$elementIndex}.{$key}"] =
                            'This element setting is not supported.';
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, list<array{kind:string,error_path:string}>>  $references
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
                    && ($use['kind'] === 'image'
                        ? str_starts_with((string) $asset->mime_type, 'image/')
                        : in_array((string) $asset->mime_type, ['video/mp4', 'video/webm'], true));
                if (!$valid) {
                    $errors[$use['error_path']] = $use['kind'] === 'image'
                        ? 'Choose an image that is still available in the Media Library.'
                        : 'Choose an MP4 or WebM video that is still available in the Media Library.';
                }
            }
        }

        return $errors;
    }

    private function normalize(array $content): array
    {
        $normalized = [];
        foreach (self::GLOBAL_DESIGN_KEYS as $key) {
            if (array_key_exists($key, $content)) {
                $normalized[$key] = $content[$key];
            }
        }

        $normalized['rows'] = array_map(function (array $row): array {
            return [
                'id' => (string) ($row['id'] ?? Str::uuid()),
                'layout' => $row['layout'],
                'width' => $row['width'],
                'background' => $row['background'],
                'spacing' => $row['spacing'],
                'columns' => array_map(fn (array $column): array => [
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
        $normalized = match ($element['type']) {
            'heading' => [
                'text' => trim($element['text']),
                'level' => $element['level'],
            ],
            'rich_text' => [
                'body' => $this->sanitizer->sanitizeLayoutRichText((string) ($element['body'] ?? '')),
            ],
            'image' => array_filter([
                'path' => '/storage/' . $this->managedMediaRelativePath($element['path'], 'image'),
                'alt' => trim((string) ($element['alt'] ?? '')),
                'caption' => array_key_exists('caption', $element)
                    ? trim((string) ($element['caption'] ?? ''))
                    : null,
            ], static fn (mixed $value, string $key): bool => $key !== 'caption' || $value !== null, ARRAY_FILTER_USE_BOTH),
            'video' => [
                'source_type' => $element['source_type'],
                'source' => $element['source_type'] === 'youtube'
                    ? $this->normalizeYouTubeUrl($element['source'])
                    : '/storage/' . $this->managedMediaRelativePath($element['source'], 'video'),
                'title' => trim($element['title']),
            ],
            'button' => [
                'label' => trim($element['label']),
                'url' => $this->normalizeSafeLink($element['url']),
                'style' => $element['style'],
            ],
            'divider' => [],
            'spacer' => ['size' => $element['size']],
        };

        return [
            'id' => (string) ($element['id'] ?? Str::uuid()),
            'type' => $element['type'],
            ...$normalized,
        ];
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

        $extensions = $kind === 'image'
            ? 'avif|gif|jpe?g|png|webp'
            : 'mp4|webm';
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
