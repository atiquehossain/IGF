<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class BanglaTranslationCatalogImporter
{
    public const SCHEMA = 'igf-bangla-translation-catalog/v1';

    public const SOURCE_LOCALE = 'en';

    public const TARGET_LOCALE = 'bn';

    public const MAX_BATCH_SIZE = 100;

    public function __construct(private TranslationCenterService $translations)
    {
    }

    /**
     * Export the current Translation Center workspace as a deterministic,
     * human-reviewable catalog. The catalog deliberately stores the English
     * source beside every Bangla value so reviewers can inspect it in Git.
     */
    public function catalog(): array
    {
        $rows = $this->translations->rows(self::SOURCE_LOCALE, self::TARGET_LOCALE);

        return [
            'schema' => self::SCHEMA,
            'source_locale' => self::SOURCE_LOCALE,
            'target_locale' => self::TARGET_LOCALE,
            'entries' => $rows->map(fn (array $row): array => [
                'key' => (string) $row['key'],
                'source_hash' => hash('sha256', (string) $row['source']),
                'group' => (string) $row['group'],
                'context' => (string) $row['context'],
                'field' => (string) $row['field'],
                'format' => (string) $row['format'],
                'required' => (bool) $row['required'],
                'source' => (string) $row['source'],
                'translation' => (string) $row['target'],
                'suggested_translation' => $this->suggestedTranslation($row),
                'preserve_source' => false,
            ])->values()->all(),
        ];
    }

    /**
     * Surface reviewed, code-owned Bangla defaults without pretending that
     * they have already been saved to the Translation Center. Translators can
     * accept or refine the suggestion before the catalog is imported.
     */
    private function suggestedTranslation(array $row): string
    {
        $identity = $row['identity'] ?? [];
        if (($identity['type'] ?? null) !== 'setting') {
            return '';
        }

        $field = config(sprintf(
            'site-settings.groups.%s.fields.%s',
            (string) ($identity['group'] ?? ''),
            (string) ($identity['field'] ?? '')
        ));
        $suggestion = is_array($field)
            ? data_get($field, 'localized_defaults.'.self::TARGET_LOCALE)
            : null;

        return is_string($suggestion) ? trim($suggestion) : '';
    }

    /**
     * Validate a catalog and report exactly what an apply would change.
     * This method is read-only and is used by the command's default mode.
     */
    public function inspect(array $catalog, bool $requiredOnly = false, bool $overwrite = false): array
    {
        return $this->report($this->plan($catalog, $requiredOnly, $overwrite));
    }

    /**
     * Apply a fully validated catalog without changing locale availability.
     * One outer transaction makes all <=100-row service batches atomic.
     */
    public function apply(
        array $catalog,
        int $adminId,
        bool $requiredOnly = false,
        bool $overwrite = false,
        int $batchSize = self::MAX_BATCH_SIZE
    ): array {
        if ($batchSize < 1 || $batchSize > self::MAX_BATCH_SIZE) {
            throw new InvalidArgumentException('Translation batch size must be between 1 and 100.');
        }

        return DB::transaction(function () use ($catalog, $adminId, $requiredOnly, $overwrite, $batchSize): array {
            // Build and validate the complete plan before the first write. If
            // any row is stale or malformed, the whole import stays untouched.
            $plan = $this->plan($catalog, $requiredOnly, $overwrite);
            $saved = 0;

            foreach (array_chunk($plan['updates'], $batchSize) as $batch) {
                $saved += $this->translations->save(
                    self::SOURCE_LOCALE,
                    self::TARGET_LOCALE,
                    $batch,
                    $adminId
                );
            }

            return $this->report($plan) + ['saved' => $saved];
        }, 3);
    }

    private function plan(array $catalog, bool $requiredOnly, bool $overwrite): array
    {
        $this->assertCatalogEnvelope($catalog);

        $allRows = $this->translations
            ->rows(self::SOURCE_LOCALE, self::TARGET_LOCALE)
            ->values();
        $rowsByKey = $allRows->keyBy('key');
        $entriesByKey = $this->entriesByKey($catalog['entries']);

        $unknown = $entriesByKey->keys()->diff($rowsByKey->keys())->values();
        if ($unknown->isNotEmpty()) {
            throw new InvalidArgumentException(
                'The catalog contains rows that no longer exist in the Translation Center: '
                .$unknown->take(3)->implode(', ')
                .($unknown->count() > 3 ? ' …' : '')
                .'. Export a fresh catalog and carry reviewed translations forward.'
            );
        }

        $selectedRows = $requiredOnly
            ? $allRows->where('required', true)->values()
            : $allRows;
        $updates = [];
        $alreadyTranslated = 0;
        $unchanged = 0;
        $preservedSource = 0;

        foreach ($selectedRows as $row) {
            $entry = $entriesByKey->get($row['key']);
            if (!is_array($entry)) {
                throw new InvalidArgumentException(
                    "Missing Bangla catalog entry for {$row['context']} / {$row['field']} [{$row['key']}]."
                );
            }

            $translation = $this->validatedTranslation($row, $entry);
            if (!$overwrite && $row['status'] === 'translated') {
                $alreadyTranslated++;
                continue;
            }

            if ($translation === (string) $row['target']) {
                $unchanged++;
                continue;
            }

            if (($entry['preserve_source'] ?? false) === true) {
                $preservedSource++;
            }
            $updates[] = [
                'key' => (string) $row['key'],
                'precondition' => (string) $row['precondition'],
                'value' => $translation,
            ];
        }

        return [
            'total_rows' => $allRows->count(),
            'selected_rows' => $selectedRows->count(),
            'required_rows' => $selectedRows->where('required', true)->count(),
            'optional_rows' => $selectedRows->where('required', false)->count(),
            'catalog_entries' => $entriesByKey->count(),
            'already_translated' => $alreadyTranslated,
            'unchanged' => $unchanged,
            'preserved_source' => $preservedSource,
            'updates' => $updates,
        ];
    }

    private function assertCatalogEnvelope(array $catalog): void
    {
        if (($catalog['schema'] ?? null) !== self::SCHEMA) {
            throw new InvalidArgumentException('Unsupported Bangla translation catalog schema.');
        }
        if (($catalog['source_locale'] ?? null) !== self::SOURCE_LOCALE
            || ($catalog['target_locale'] ?? null) !== self::TARGET_LOCALE) {
            throw new InvalidArgumentException('The catalog must translate English [en] into Bangla [bn].');
        }
        if (!isset($catalog['entries']) || !is_array($catalog['entries']) || !array_is_list($catalog['entries'])) {
            throw new InvalidArgumentException('The catalog entries field must be a JSON list.');
        }
    }

    /** @return Collection<string, array<string, mixed>> */
    private function entriesByKey(array $entries): Collection
    {
        $indexed = collect();
        foreach ($entries as $position => $entry) {
            if (!is_array($entry)) {
                throw new InvalidArgumentException('Catalog entry '.($position + 1).' must be an object.');
            }

            $key = $entry['key'] ?? null;
            if (!is_string($key) || trim($key) === '') {
                throw new InvalidArgumentException('Catalog entry '.($position + 1).' has no row key.');
            }
            if ($indexed->has($key)) {
                throw new InvalidArgumentException("The catalog contains duplicate row key [{$key}].");
            }

            $indexed->put($key, $entry);
        }

        return $indexed;
    }

    private function validatedTranslation(array $row, array $entry): string
    {
        $source = (string) $row['source'];
        if (!isset($entry['source']) || !is_string($entry['source']) || !hash_equals($source, $entry['source'])) {
            throw new InvalidArgumentException(
                "The English source changed for {$row['context']} / {$row['field']}. Export a fresh catalog."
            );
        }

        $expectedHash = hash('sha256', $source);
        if (!isset($entry['source_hash'])
            || !is_string($entry['source_hash'])
            || !hash_equals($expectedHash, $entry['source_hash'])) {
            throw new InvalidArgumentException(
                "The source hash is stale for {$row['context']} / {$row['field']}. Export a fresh catalog."
            );
        }

        if (!array_key_exists('translation', $entry) || !is_string($entry['translation'])) {
            throw new InvalidArgumentException(
                "The Bangla translation is missing for {$row['context']} / {$row['field']}."
            );
        }
        $translation = trim($entry['translation']);
        if (trim(strip_tags($translation)) === '') {
            throw new InvalidArgumentException(
                "The Bangla translation is blank for {$row['context']} / {$row['field']}."
            );
        }

        $preserveSource = ($entry['preserve_source'] ?? false) === true;
        if ($preserveSource && $translation !== trim($source)) {
            throw new InvalidArgumentException(
                "A preserve_source entry must exactly retain the English source for {$row['context']} / {$row['field']}."
            );
        }
        if (!$preserveSource && $translation === trim($source)) {
            throw new InvalidArgumentException(
                "The translation still matches English for {$row['context']} / {$row['field']}; translate it or explicitly set preserve_source to true."
            );
        }
        if (!$preserveSource && preg_match('/[\x{0980}-\x{09FF}]/u', $translation) !== 1) {
            throw new InvalidArgumentException(
                "The translation has no Bangla characters for {$row['context']} / {$row['field']}; translate it or explicitly preserve the source."
            );
        }

        $this->assertPlaceholdersPreserved($source, $translation, $row);
        $this->assertHtmlPreserved($source, $translation, $row);

        return $translation;
    }

    private function assertPlaceholdersPreserved(string $source, string $translation, array $row): void
    {
        $pattern = '/:[A-Za-z_][A-Za-z0-9_]*|\{\{\s*[A-Za-z_][A-Za-z0-9_]*\s*\}\}|(?<!\{)\{[A-Za-z_][A-Za-z0-9_]*\}(?!\})|%(?:\d+\$)?[bcdeEfFgGosuxX]/';
        preg_match_all($pattern, $source, $sourceMatches);
        preg_match_all($pattern, $translation, $targetMatches);
        $sourceTokens = array_count_values($sourceMatches[0] ?? []);
        $targetTokens = array_count_values($targetMatches[0] ?? []);
        ksort($sourceTokens);
        ksort($targetTokens);

        if ($sourceTokens !== $targetTokens) {
            throw new InvalidArgumentException(
                "Placeholders were changed for {$row['context']} / {$row['field']}."
            );
        }
    }

    private function assertHtmlPreserved(string $source, string $translation, array $row): void
    {
        preg_match_all('/<\/?[A-Za-z][^>]*>/u', $source, $sourceTags);
        preg_match_all('/<\/?[A-Za-z][^>]*>/u', $translation, $targetTags);

        if (($sourceTags[0] ?? []) !== ($targetTags[0] ?? [])) {
            throw new InvalidArgumentException(
                "HTML tags or attributes were changed for {$row['context']} / {$row['field']}."
            );
        }
    }

    private function report(array $plan): array
    {
        $planned = count($plan['updates']);
        unset($plan['updates']);

        return $plan + ['planned' => $planned];
    }
}
