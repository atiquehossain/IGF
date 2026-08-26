<?php

namespace App\Services;

use App\Models\Album;
use App\Models\AnnualReport;
use App\Models\Banner;
use App\Models\Category;
use App\Models\DonationType;
use App\Models\Gallery;
use App\Models\LatestNews;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\TeamGroup;
use App\Models\PageMenu;
use App\Models\SiteSetting;
use App\Models\SplashScreen;
use App\Models\Testimonial;
use App\Models\TranslationLocale;
use App\Models\TranslationString;
use App\Models\VolunteerCause;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TranslationCenterService
{
    private const STALE_MESSAGE = 'One or more translation rows changed after this page was opened. Nothing was saved. Refresh the Translation Center, review the latest wording, and try again.';

    private const PAGE_FIELDS = [
        'name' => ['label' => 'Page title', 'group' => 'pages', 'format' => 'text'],
        'sub_title' => ['label' => 'Page introduction', 'group' => 'pages', 'format' => 'textarea'],
        'description' => ['label' => 'Legacy page content', 'group' => 'pages', 'format' => 'html'],
    ];

    private const GENERIC_CONTENT = [
        'category' => ['class' => Category::class, 'label' => 'Category', 'key' => 'uuid', 'fields' => ['name', 'description']],
        'banner' => ['class' => Banner::class, 'label' => 'Banner', 'key' => 'uuid', 'fields' => ['name', 'eyebrow', 'headline', 'subheadline', 'description', 'image_alt', 'cta_label']],
        'gallery' => ['class' => Gallery::class, 'label' => 'Gallery', 'key' => 'uuid', 'fields' => ['name', 'description']],
        'album' => ['class' => Album::class, 'label' => 'Album', 'key' => 'uuid', 'fields' => ['name']],
        'testimonial' => ['class' => Testimonial::class, 'label' => 'Testimonial', 'key' => 'uuid', 'fields' => ['name', 'designation', 'testimonial']],
        'event' => ['class' => NoticeBoard::class, 'label' => 'Event or publication', 'key' => 'translation_key', 'fields' => ['title', 'sub_title', 'description', 'location', 'publisher_name']],
        'annual_report' => ['class' => AnnualReport::class, 'label' => 'Annual report', 'key' => 'translation_key', 'fields' => ['title', 'sub_title', 'description', 'location', 'publisher_name']],
        'splash_screen' => ['class' => SplashScreen::class, 'label' => 'Visitor announcement', 'key' => 'uuid', 'fields' => ['title', 'details']],
    ];

    /**
     * Cross-workspace mutations must acquire shared content owners in this
     * exact order. The first three models are also SEO bulk-edit owners, so
     * keeping Category -> Event -> Annual report stable prevents an external
     * SEO transaction from bridging two Translation Center lock plans.
     */
    private const GENERIC_CONTENT_LOCK_ORDER = [
        'category',
        'event',
        'annual_report',
        'album',
        'banner',
        'gallery',
        'splash_screen',
        'testimonial',
    ];

    /**
     * These legacy content types do not have a safe source/target row pairing.
     * Their translated visitor-facing fields therefore live in the managed
     * translation dictionary and are overlaid at read time.
     */
    private const OVERLAY_CONTENT = [
        'team_group' => ['class' => TeamGroup::class, 'label' => 'Team group', 'key' => 'uuid', 'fields' => ['name', 'description']],
        'team_member' => ['class' => LatestNews::class, 'label' => 'Team member', 'key' => 'id', 'fields' => ['name', 'description', 'biography', 'qualification']],
        'donation_cause' => ['class' => DonationType::class, 'label' => 'Donation cause', 'key' => 'uuid', 'fields' => ['name', 'description', 'destination_name']],
        'volunteer_opportunity' => ['class' => VolunteerCause::class, 'label' => 'Volunteer opportunity', 'key' => 'id', 'fields' => ['name', 'description']],
    ];

    private const NON_TRANSLATABLE_BLOCK_KEYS = [
        'url', 'primary_url', 'secondary_url', 'report_url', 'link_url', 'video_url', 'youtube_url',
        'image', 'photo', 'poster', 'background_image', 'icon', 'value', 'limit',
        'animation_type', 'media_type', 'image_position', 'overlay_opacity', 'interval', 'size',
        'content_source', 'category_slug', 'tag_slug', 'sort', 'selection_mode',
        'selected_items', 'id', 'uuid', 'translation_key', 'slug', 'type',
        'locale', 'language', 'platform', 'variant', 'layout', 'presentation', 'section_presentation', 'target', 'rel',
    ];

    public function __construct(
        private SiteSettingService $settings,
        private ContentSanitizer $sanitizer,
        private PageCategoryTranslationMapper $categoryMapper,
        private PageEditorVersionService $editorVersions
    ) {
    }

    public function rows(string $sourceLocale = 'en', string $targetLocale = 'bn'): Collection
    {
        return collect()
            ->concat($this->interfaceRows($sourceLocale, $targetLocale))
            ->concat($this->settingRows($sourceLocale, $targetLocale))
            ->concat($this->pageRows($sourceLocale, $targetLocale))
            ->concat($this->menuRows($sourceLocale, $targetLocale))
            ->concat($this->genericContentRows($sourceLocale, $targetLocale))
            ->concat($this->overlayContentRows($sourceLocale, $targetLocale))
            ->sortBy(fn (array $row) => [$row['group'], $row['context'], $row['field']])
            ->values();
    }

    public function summary(Collection $rows): array
    {
        $requiredRows = $rows->where('required', true);
        $total = $requiredRows->count();
        $translated = $requiredRows->where('status', 'translated')->count();
        $missing = $total - $translated;

        return [
            'total' => $total,
            'translated' => $translated,
            'missing' => $missing,
            'percent' => $total === 0 ? 100 : (int) floor(($translated / $total) * 100),
            'available_total' => $rows->count(),
            'optional' => $rows->where('required', false)->count(),
        ];
    }

    /**
     * Convert a replicated page into a safe, unpublished translation shell.
     * Visitor-facing copy must remain empty until a translator supplies it.
     */
    public function preparePageTranslationDraft(Page $page): Page
    {
        foreach (array_merge(array_keys(self::PAGE_FIELDS), [
            'meta_title',
            'meta_keyword',
            'meta_description',
        ]) as $field) {
            $page->{$field} = '';
        }

        $page->status = false;
        $page->publication_status = 'draft';
        $page->scheduled_for = null;
        $page->last_published_at = null;
        $page->published_by = null;

        return $page;
    }

    /**
     * Preserve media, destinations, numeric facts and presentation settings,
     * while blanking only strings that require a human translation.
     */
    public function prepareBlockTranslationContent(array $content): array
    {
        return $this->blankTranslatedBlockValues($content);
    }

    public function save(string $sourceLocale, string $targetLocale, array $updates, ?int $adminId): int
    {
        $available = $this->rows($sourceLocale, $targetLocale)->keyBy('key');
        $prepared = collect($updates)->map(function (array $update) use ($available): array {
            $row = $available->get($update['key'] ?? '');
            if (!$row) {
                throw ValidationException::withMessages(['translations' => 'One translation row is no longer available. Refresh and try again.']);
            }

            $this->assertCurrentPrecondition($row, $update['precondition'] ?? null);

            return [
                'row' => $row,
                'value' => is_string($update['value'] ?? null) ? trim($update['value']) : '',
                'precondition' => (string) $update['precondition'],
            ];
        })->values();
        $pageUuids = $this->affectedPageUuids($prepared->pluck('row'), $sourceLocale);

        return DB::transaction(function () use ($prepared, $pageUuids, $sourceLocale, $targetLocale, $adminId): int {
            // This one locale row serializes target-row creation between two
            // Translation Center sessions. Page and record locks below still
            // protect the same state from editors outside this workspace.
            $this->lockWorkspace($targetLocale);

            // Every logical Page is locked in UUID order before PageBlock or
            // Category rows. Page Builder and publication sync use this same
            // ordering, eliminating the former Page <-> Category lock cycle.
            $pageLocks = $this->editorVersions->lockForMutation($pageUuids);
            $this->lockPageBlocks($pageLocks);
            $this->lockNonPageTranslationRows($prepared->pluck('row'), $sourceLocale, $targetLocale);

            // Rebuild row state only after all affected records are locked.
            // Checking the whole submitted batch before the first write keeps
            // mixed page/block saves atomic when even one browser row is stale.
            $current = $this->rows($sourceLocale, $targetLocale)->keyBy('key');
            foreach ($prepared as $update) {
                $row = $current->get($update['row']['key']);
                if (!$row) {
                    $this->throwStalePrecondition();
                }
                $this->assertCurrentPrecondition($row, $update['precondition']);
            }

            $saved = 0;
            $changedPageUuids = [];
            foreach ($prepared as $update) {
                $row = $current->get($update['row']['key']);
                $value = $update['value'];
                if ($value === (string) $row['target']) {
                    continue;
                }

                $this->validatePlaceholders((string) $row['source'], $value, $row['field']);
                $changedPageUuids = array_merge(
                    $changedPageUuids,
                    $this->saveRow(
                        $row['identity'],
                        $sourceLocale,
                        $targetLocale,
                        $value,
                        $adminId,
                        $pageLocks
                    )
                );
                $saved++;
            }

            $this->editorVersions->advanceLocked($pageLocks, $changedPageUuids);

            return $saved;
        });
    }

    /**
     * Mirror the source editorial state after a locale is fully translated.
     * Completed translations should become visible wherever their source is
     * visible, while source drafts and hidden records remain safely hidden.
     */
    public function syncPublicationState(string $sourceLocale, string $targetLocale): array
    {
        return DB::transaction(function () use ($sourceLocale, $targetLocale): array {
            $counts = ['pages' => 0, 'menus' => 0, 'content' => 0];

            $this->lockWorkspace($targetLocale);

            $pageSources = Page::query()
                ->where('language', $sourceLocale)
                ->orderBy('uuid')
                ->get()
                ->keyBy('uuid');
            $pageTargets = Page::query()
                ->where('language', $targetLocale)
                ->whereIn('uuid', $pageSources->keys())
                ->orderBy('uuid')
                ->get()
                ->keyBy('uuid');
            $pageUuids = $pageTargets->keys()->filter()->sort()->values();

            // Match the Translation Center, Page Builder and SEO ordering:
            // locale, sorted logical Pages, canonical generic content owners,
            // then dependent donation causes.
            $pageLocks = $this->editorVersions->lockForMutation($pageUuids);
            $lockedPages = $pageLocks
                ->flatMap(fn ($pages) => $pages->all())
                ->values();
            $lockedGenericContent = $this->lockGenericContentRows(
                collect(array_keys(self::GENERIC_CONTENT)),
                $sourceLocale,
                $targetLocale
            );
            $lockedCategories = $lockedGenericContent->get('category', collect());

            // The initial reads only discover the target UUID set. Rebuild all
            // publication plans from the rows returned by the locking read so
            // a wait behind another editor cannot apply stale source state.
            $pageSources = $lockedPages
                ->filter(fn (Page $page): bool => !$page->trashed() && $page->language === $sourceLocale)
                ->keyBy('uuid');
            $pageTargets = $lockedPages
                ->filter(fn (Page $page): bool => !$page->trashed() && $page->language === $targetLocale)
                ->keyBy('uuid')
                ->filter(fn (Page $page, string $uuid): bool => $pageSources->has($uuid));
            $categorySources = $lockedCategories
                ->filter(fn (Category $category): bool => !$category->trashed() && $category->language === $sourceLocale)
                ->keyBy('uuid');
            $categoryTargets = $lockedCategories
                ->filter(fn (Category $category): bool => !$category->trashed() && $category->language === $targetLocale)
                ->keyBy('uuid')
                ->filter(fn (Category $category, string $uuid): bool => $categorySources->has($uuid));
            $categoryUuids = $categoryTargets->keys()->filter()->sort()->values();
            $fundingCauses = collect();
            if ($pageUuids->isNotEmpty() || $categoryUuids->isNotEmpty()) {
                $fundingCauses = DonationType::query()
                    ->where('status', 1)
                    ->where(function ($query) use ($pageUuids, $categoryUuids): void {
                        if ($pageUuids->isNotEmpty()) {
                            $query->where(function ($pages) use ($pageUuids): void {
                                $pages->where('destination_type', 'page')
                                    ->whereIn('destination_page_uuid', $pageUuids);
                            });
                        }
                        if ($categoryUuids->isNotEmpty()) {
                            $method = $pageUuids->isNotEmpty() ? 'orWhere' : 'where';
                            $query->{$method}(function ($categories) use ($categoryUuids): void {
                                $categories->where('destination_type', 'category')
                                    ->whereIn('destination_category_uuid', $categoryUuids);
                            });
                        }
                    })
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            $pagePlans = $pageTargets->mapWithKeys(function (Page $target, string $uuid) use ($pageSources): array {
                $source = $pageSources->get($uuid);

                return [$target->id => [
                    'status' => (bool) $source->status,
                    'publication_status' => (string) $source->publication_status,
                    'visibility' => (string) $source->visibility,
                ]];
            });
            $categoryPlans = $categoryTargets->mapWithKeys(function (Category $target, string $uuid) use ($categorySources): array {
                return [$target->id => (bool) $categorySources->get($uuid)->status];
            });
            $this->assertFundingDestinationsRemainAvailable(
                $fundingCauses,
                $lockedPages,
                $pagePlans,
                $lockedCategories,
                $categoryPlans
            );

            $changedPageUuids = [];
            foreach ($pageSources as $source) {
                $target = $pageTargets->get($source->uuid);
                if (!$target) {
                    continue;
                }
                $categoryBefore = (string) ($target->category_id ?? '');
                $this->categoryMapper->remap($source, $target, $targetLocale);
                $categoryChanged = (string) ($target->category_id ?? '') !== $categoryBefore;
                $target->fill([
                    'status' => (bool) $source->status,
                    'publication_status' => $source->publication_status,
                    'visibility' => $source->visibility,
                    'published_at' => $source->published_at,
                    'scheduled_for' => $source->scheduled_for,
                    'last_published_at' => $source->last_published_at,
                ]);
                $publicationChanged = $target->isDirty();
                if ($publicationChanged) {
                    $target->save();
                }
                if ($categoryChanged || $publicationChanged) {
                    $changedPageUuids[] = (string) $source->uuid;
                }
                $counts['pages']++;
            }
            $this->editorVersions->advanceLocked($pageLocks, $changedPageUuids);

            $menuTargets = PageMenu::query()->where('language', $targetLocale)->get()->keyBy('uuid');
            foreach (PageMenu::query()->where('language', $sourceLocale)->get() as $source) {
                $target = $menuTargets->get($source->uuid);
                if (!$target) {
                    continue;
                }
                $target->update(['status' => (bool) $source->status]);
                $counts['menus']++;
            }

            foreach ($this->genericContentAliasesInLockOrder(collect(array_keys(self::GENERIC_CONTENT))) as $alias) {
                $definition = self::GENERIC_CONTENT[$alias];
                $keyField = $definition['key'];
                $rows = $lockedGenericContent->get($alias, collect())
                    ->filter(fn (Model $model): bool => !$model->trashed());
                $sources = $rows
                    ->filter(fn (Model $model): bool => $model->getAttribute('language') === $sourceLocale
                        && filled($model->getAttribute($keyField)));
                $targets = $rows
                    ->filter(fn (Model $model): bool => $model->getAttribute('language') === $targetLocale
                        && $sources->pluck($keyField)->contains($model->getAttribute($keyField)))
                    ->keyBy($keyField);
                foreach ($sources as $source) {
                    $target = $targets->get($source->{$keyField});
                    if (!$target || !array_key_exists('status', $source->getAttributes())) {
                        continue;
                    }
                    $target->update(['status' => (bool) $source->status]);
                    $counts['content']++;
                }
            }

            return $counts;
        });
    }

    private function assertFundingDestinationsRemainAvailable(
        Collection $causes,
        Collection $pages,
        Collection $pagePlans,
        Collection $categories,
        Collection $categoryPlans
    ): void {
        foreach ($causes as $cause) {
            if ($cause->destination_type === 'page') {
                $available = $pages
                    ->where('uuid', $cause->destination_page_uuid)
                    ->contains(function (Page $page) use ($pagePlans): bool {
                        if ($page->trashed()) {
                            return false;
                        }
                        $plan = $pagePlans->get($page->id, []);

                        return (bool) ($plan['status'] ?? $page->status)
                            && (string) ($plan['publication_status'] ?? $page->publication_status) === 'published'
                            && (string) ($plan['visibility'] ?? $page->visibility) === 'public';
                    });
                if (!$available) {
                    throw ValidationException::withMessages([
                        'translations' => 'Publication sync would hide the last public version of a page used by an active donation cause. Reassign or unpublish that cause before synchronizing drafts or private pages.',
                    ]);
                }
            }

            if ($cause->destination_type === 'category') {
                $available = $categories
                    ->where('uuid', $cause->destination_category_uuid)
                    ->contains(function (Category $category) use ($categoryPlans): bool {
                        return !$category->trashed()
                            && (bool) $categoryPlans->get($category->id, (bool) $category->status);
                    });
                if (!$available) {
                    throw ValidationException::withMessages([
                        'translations' => 'Publication sync would hide the last active program used by a donation cause. Reassign or unpublish that cause before synchronizing inactive translations.',
                    ]);
                }
            }
        }
    }

    /**
     * Translation operations touch shared source-locale records even when
     * their target locales differ. Locking the complete locale registry in a
     * stable order serializes those cross-locale batches and prevents model
     * lock inversions between save(), publication sync, and locale toggles.
     */
    public function lockWorkspace(string $targetLocale): TranslationLocale
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('The Translation Center workspace lock requires an active transaction.');
        }

        $locales = TranslationLocale::query()
            ->orderBy('locale')
            ->lockForUpdate()
            ->get();

        return $locales->firstWhere('locale', $targetLocale) ?? throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())
            ->setModel(TranslationLocale::class, [$targetLocale]);
    }

    public function localizedContentValue(
        string $model,
        string $identity,
        string $field,
        string $fallback,
        ?string $locale = null
    ): string {
        $locale = $locale ?: app()->getLocale();
        if ($locale === 'en' || !isset(self::OVERLAY_CONTENT[$model])) {
            return $fallback;
        }

        $definition = self::OVERLAY_CONTENT[$model];
        if (!in_array($field, $definition['fields'], true)) {
            return $fallback;
        }

        $translation = TranslationString::query()
            ->where('key', $this->overlayStorageKey($model, $identity, $field))
            ->where('locale', $locale)
            ->where('status', 'translated')
            ->value('value');

        return is_string($translation) && trim(strip_tags($translation)) !== ''
            ? $translation
            : $fallback;
    }

    /**
     * Resolve translation overlays for many records in one query.
     *
     * @param array<string|int, array<string, string>> $fallbacks
     * @return array<string|int, array<string, string>>
     */
    public function localizedContentValues(
        string $model,
        array $fallbacks,
        ?string $locale = null
    ): array {
        $locale = $locale ?: app()->getLocale();
        if ($locale === 'en' || !isset(self::OVERLAY_CONTENT[$model]) || $fallbacks === []) {
            return $fallbacks;
        }

        $allowedFields = self::OVERLAY_CONTENT[$model]['fields'];
        $storageKeys = [];

        foreach ($fallbacks as $identity => $fields) {
            if (!is_array($fields)) {
                continue;
            }

            foreach ($fields as $field => $fallback) {
                if (!in_array($field, $allowedFields, true)) {
                    continue;
                }

                $storageKeys[$this->overlayStorageKey($model, (string) $identity, $field)] = [
                    (string) $identity,
                    $field,
                ];
            }
        }

        if ($storageKeys === []) {
            return $fallbacks;
        }

        $translations = TranslationString::query()
            ->whereIn('key', array_keys($storageKeys))
            ->where('locale', $locale)
            ->where('status', 'translated')
            ->pluck('value', 'key');

        foreach ($storageKeys as $key => [$identity, $field]) {
            $translation = $translations->get($key);
            if (is_string($translation) && trim(strip_tags($translation)) !== '') {
                $fallbacks[$identity][$field] = $translation;
            }
        }

        return $fallbacks;
    }

    private function interfaceRows(string $sourceLocale, string $targetLocale): Collection
    {
        $source = json_decode((string) file_get_contents(resource_path('lang/en.json')), true) ?: [];
        $source = $source['vue'] ?? [];
        $target = TranslationString::query()
            ->where('locale', $targetLocale)
            ->pluck('value', 'key');
        $rows = collect();

        $walk = function (array $values, string $path = 'vue') use (&$walk, $rows, $target, $sourceLocale, $targetLocale): void {
            foreach ($values as $key => $value) {
                $currentPath = $path . '.' . $key;
                if (is_array($value)) {
                    $walk($value, $currentPath);
                    continue;
                }
                if (!is_string($value) || trim($value) === '') {
                    continue;
                }

                $parts = explode('.', $currentPath);
                $field = Str::headline((string) array_pop($parts));
                $context = 'Website interface · ' . collect(array_slice($parts, 1))->map(fn ($part) => Str::headline($part))->implode(' / ');
                $rows->push($this->row(
                    ['type' => 'interface', 'path' => $currentPath],
                    'interface',
                    $context,
                    $field,
                    $value,
                    (string) ($target[$currentPath] ?? ''),
                    'textarea',
                    $sourceLocale,
                    $targetLocale
                ));
            }
        };
        $walk($source);

        return $rows;
    }

    private function settingRows(string $sourceLocale, string $targetLocale): Collection
    {
        $sourceValues = $this->settings->values($sourceLocale);
        $targetValues = SiteSetting::query()
            ->where('locale', $targetLocale)
            ->get()
            ->mapWithKeys(fn (SiteSetting $setting) => [$setting->group . '.' . $setting->key => $setting->typed_value]);
        $rows = collect();

        foreach (config('site-settings.groups', []) as $groupKey => $group) {
            foreach ($group['fields'] ?? [] as $key => $field) {
                if (!($field['localized'] ?? false) || !($field['public'] ?? false) || !in_array($field['type'], ['text', 'textarea'], true)) {
                    continue;
                }
                $source = data_get($sourceValues, "{$groupKey}.{$key}");
                if (!is_string($source) || trim($source) === '') {
                    continue;
                }
                $rows->push($this->row(
                    ['type' => 'setting', 'group' => $groupKey, 'field' => $key, 'setting_type' => $field['type']],
                    'settings',
                    'Website settings · ' . ($group['label'] ?? Str::headline($groupKey)),
                    $field['label'] ?? Str::headline($key),
                    $source,
                    (string) ($targetValues[$groupKey . '.' . $key] ?? ''),
                    $field['type'],
                    $sourceLocale,
                    $targetLocale
                ));
            }
        }

        return $rows;
    }

    private function pageRows(string $sourceLocale, string $targetLocale): Collection
    {
        $sources = Page::query()->where('language', $sourceLocale)->with('blocks.reusableBlock')->orderBy('name')->get();
        $targets = Page::query()->where('language', $targetLocale)->with('blocks.reusableBlock')->get()->keyBy('uuid');
        $publicPageIds = Page::query()
            ->where('language', $sourceLocale)
            ->publiclyAvailable()
            ->pluck('id')
            ->flip();
        $visibleBlockIds = PageBlock::query()
            ->whereIn('page_id', $publicPageIds->keys())
            ->visible()
            ->pluck('id')
            ->flip();
        $rows = collect();

        foreach ($sources as $source) {
            $target = $targets->get($source->uuid);
            $pageIsRequired = $publicPageIds->has($source->id);
            foreach (self::PAGE_FIELDS as $field => $definition) {
                $sourceValue = (string) ($source->{$field} ?? '');
                if (trim(strip_tags($sourceValue)) === '') {
                    continue;
                }
                $rows->push($this->row(
                    ['type' => 'page', 'source_id' => $source->id, 'field' => $field],
                    $definition['group'],
                    'Page · ' . $source->name,
                    $definition['label'],
                    $sourceValue,
                    (string) ($target?->{$field} ?? ''),
                    $definition['format'],
                    $sourceLocale,
                    $targetLocale,
                    $pageIsRequired
                ));
            }

            $targetBlocks = $target?->blocks?->keyBy(fn (PageBlock $block) => (string) ($block->translation_key ?: $block->uuid)) ?? collect();
            foreach ($source->blocks as $block) {
                $translationKey = (string) ($block->translation_key ?: $block->uuid);
                $targetBlock = $targetBlocks->get($translationKey);
                foreach ($this->flattenBlockContent($block->resolvedContent()) as $path => $sourceValue) {
                    $rows->push($this->row(
                        ['type' => 'block', 'source_page_id' => $source->id, 'source_block_id' => $block->id, 'path' => $path],
                        'pages',
                        'Page · ' . $source->name . ' / ' . ($block->resolvedLabel() ?: config("page-builder.block_types.{$block->type}", Str::headline($block->type))),
                        Str::headline((string) last(explode('.', $path))),
                        $sourceValue,
                        (string) data_get($targetBlock?->reusable_block_id ? [] : ($targetBlock?->content ?? []), $path, ''),
                        str_contains($sourceValue, '<') ? 'html' : 'textarea',
                        $sourceLocale,
                        $targetLocale,
                        $pageIsRequired && $visibleBlockIds->has($block->id)
                    ));
                }
            }
        }

        return $rows;
    }

    private function menuRows(string $sourceLocale, string $targetLocale): Collection
    {
        $targets = PageMenu::query()->where('language', $targetLocale)->get()->keyBy('uuid');

        return PageMenu::query()
            ->where('language', $sourceLocale)
            ->orderBy('type')
            ->orderBy('order_by')
            ->get()
            ->filter(fn (PageMenu $menu) => trim((string) $menu->name) !== '')
            ->flatMap(function (PageMenu $menu) use ($targets, $sourceLocale, $targetLocale): array {
                $target = $targets->get($menu->uuid);
                $context = $menu->type === 'footer' ? 'Footer navigation' : 'Header & mobile navigation';
                $rows = [
                    $this->row(
                        ['type' => 'menu', 'source_id' => $menu->id, 'field' => 'name'],
                        'navigation',
                        $context,
                        'Menu label · ' . $menu->name,
                        (string) $menu->name,
                        (string) ($target?->name ?? ''),
                        'text',
                        $sourceLocale,
                        $targetLocale,
                        (bool) $menu->status
                    ),
                ];

                if (trim((string) $menu->description) !== '') {
                    $rows[] = $this->row(
                        ['type' => 'menu', 'source_id' => $menu->id, 'field' => 'description'],
                        'navigation',
                        $context,
                        'Menu description · ' . $menu->name,
                        (string) $menu->description,
                        (string) ($target?->description ?? ''),
                        'text',
                        $sourceLocale,
                        $targetLocale,
                        (bool) $menu->status
                    );
                }

                return $rows;
            });
    }

    private function genericContentRows(string $sourceLocale, string $targetLocale): Collection
    {
        $rows = collect();
        foreach (self::GENERIC_CONTENT as $alias => $definition) {
            /** @var class-string<Model> $class */
            $class = $definition['class'];
            $keyField = $definition['key'];
            $sources = $class::query()->where('language', $sourceLocale)->whereNotNull($keyField)->get();
            $targets = $class::query()->where('language', $targetLocale)->whereIn($keyField, $sources->pluck($keyField))->get()->keyBy($keyField);
            foreach ($sources as $source) {
                $target = $targets->get($source->{$keyField});
                $title = (string) ($source->name ?? $source->title ?? 'Content #' . $source->id);
                foreach ($definition['fields'] as $field) {
                    $sourceValue = (string) ($source->{$field} ?? '');
                    if (trim(strip_tags($sourceValue)) === '') {
                        continue;
                    }
                    $rows->push($this->row(
                        ['type' => 'content', 'model' => $alias, 'source_id' => $source->id, 'field' => $field],
                        'content',
                        $definition['label'] . ' · ' . $title,
                        Str::headline($field),
                        $sourceValue,
                        (string) ($target?->{$field} ?? ''),
                        in_array($field, ['description', 'testimonial'], true) ? 'html' : 'text',
                        $sourceLocale,
                        $targetLocale,
                        $this->isActiveSource($source)
                    ));
                }
            }
        }

        return $rows;
    }

    private function overlayContentRows(string $sourceLocale, string $targetLocale): Collection
    {
        $rows = collect();
        $targets = TranslationString::query()
            ->where('locale', $targetLocale)
            ->where('key', 'like', 'content.%')
            ->pluck('value', 'key');

        foreach (self::OVERLAY_CONTENT as $alias => $definition) {
            /** @var class-string<Model> $class */
            $class = $definition['class'];
            $query = $class::query();
            if (in_array($alias, ['team_member', 'team_group'], true)) {
                $query->where('language', $sourceLocale);
                if ($alias === 'team_member') {
                    $query->where('type', 'our-members');
                }
            } elseif ($sourceLocale !== 'en') {
                continue;
            }

            foreach ($query->orderBy('id')->get() as $source) {
                $identity = (string) $source->{$definition['key']};
                if ($identity === '') {
                    continue;
                }
                $title = (string) ($source->name ?? 'Content #' . $source->id);
                foreach ($definition['fields'] as $field) {
                    $sourceValue = (string) ($source->{$field} ?? '');
                    if (trim(strip_tags($sourceValue)) === '') {
                        continue;
                    }
                    $storageKey = $this->overlayStorageKey($alias, $identity, $field);
                    $rows->push($this->row(
                        [
                            'type' => 'content_overlay',
                            'model' => $alias,
                            'source_id' => $source->id,
                            'identity' => $identity,
                            'field' => $field,
                        ],
                        'content',
                        $definition['label'] . ' · ' . $title,
                        Str::headline($field),
                        $sourceValue,
                        (string) ($targets[$storageKey] ?? ''),
                        $field === 'description' && $alias !== 'team_group' ? 'html' : 'text',
                        $sourceLocale,
                        $targetLocale,
                        $this->isActiveSource($source)
                    ));
                }
            }
        }

        return $rows;
    }

    private function row(array $identity, string $group, string $context, string $field, string $source, string $target, string $format, string $sourceLocale, string $targetLocale, bool $required = true): array
    {
        $identityJson = json_encode($identity, JSON_UNESCAPED_SLASHES);
        $translated = trim(strip_tags($target)) !== '';
        $state = json_encode([
            'source_locale' => $sourceLocale,
            'target_locale' => $targetLocale,
            'identity' => $identity,
            'source' => $source,
            'target' => $target,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'key' => hash('sha256', $sourceLocale . '|' . $targetLocale . '|' . $identityJson),
            'precondition' => hash_hmac('sha256', (string) $state, (string) config('app.key')),
            'identity' => $identity,
            'group' => $group,
            'context' => $context,
            'field' => $field,
            'source' => $source,
            'target' => $target,
            'format' => $format,
            'status' => $translated ? 'translated' : 'missing',
            'required' => $required,
        ];
    }

    private function isActiveSource(Model $source): bool
    {
        return !array_key_exists('status', $source->getAttributes()) || (bool) $source->status;
    }

    private function flattenBlockContent(array $content, string $path = ''): array
    {
        $result = [];
        foreach ($content as $key => $value) {
            $currentPath = $path === '' ? (string) $key : $path . '.' . $key;
            if (!$this->isTranslatableBlockKey((string) $key)) {
                continue;
            }
            if (is_array($value)) {
                $result += $this->flattenBlockContent($value, $currentPath);
                continue;
            }
            if (!is_string($value) || trim(strip_tags($value)) === '' || !$this->isTranslatableBlockKey((string) $key)) {
                continue;
            }
            $result[$currentPath] = $value;
        }

        return $result;
    }

    private function isTranslatableBlockKey(string $key): bool
    {
        if (in_array($key, self::NON_TRANSLATABLE_BLOCK_KEYS, true)) {
            return false;
        }

        foreach (['_url', '_image', '_icon', '_id', '_ids', '_uuid', '_slug', '_key'] as $machineSuffix) {
            if (str_ends_with($key, $machineSuffix)) {
                return false;
            }
        }

        return true;
    }

    private function assertCurrentPrecondition(array $row, mixed $submitted): void
    {
        if (!is_string($submitted)
            || !preg_match('/\A[a-f0-9]{64}\z/', $submitted)
            || !hash_equals((string) $row['precondition'], $submitted)) {
            $this->throwStalePrecondition();
        }
    }

    private function throwStalePrecondition(): never
    {
        throw ValidationException::withMessages([
            'translations' => self::STALE_MESSAGE,
        ])->status(409);
    }

    /**
     * Discover every Page identity a submitted batch may mutate. Category
     * translation can repair locale-specific category references, so those
     * Pages are included before any Category record is locked.
     */
    private function affectedPageUuids(Collection $rows, string $sourceLocale): Collection
    {
        $sourcePageIds = $rows
            ->flatMap(function (array $row): array {
                $identity = $row['identity'] ?? [];

                return match ($identity['type'] ?? null) {
                    'page' => [(int) ($identity['source_id'] ?? 0)],
                    'block' => [(int) ($identity['source_page_id'] ?? 0)],
                    default => [],
                };
            })
            ->filter()
            ->unique();
        $pageUuids = Page::query()
            ->where('language', $sourceLocale)
            ->whereIn('id', $sourcePageIds)
            ->pluck('uuid');

        $categorySourceIds = $rows
            ->filter(fn (array $row): bool =>
                ($row['identity']['type'] ?? null) === 'content'
                && ($row['identity']['model'] ?? null) === 'category'
            )
            ->pluck('identity.source_id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique();

        if ($categorySourceIds->isNotEmpty()) {
            $categoryReferences = Category::query()
                ->where('language', $sourceLocale)
                ->whereIn('id', $categorySourceIds)
                ->get(['id', 'uuid'])
                ->flatMap(fn (Category $category): array => array_values(array_filter([
                    (string) $category->id,
                    (string) $category->uuid,
                ])))
                ->unique();

            if ($categoryReferences->isNotEmpty()) {
                $pageUuids = $pageUuids->concat(
                    Page::query()
                        ->where('language', $sourceLocale)
                        ->whereIn('category_id', $categoryReferences)
                        ->pluck('uuid')
                );
            }
        }

        return $pageUuids
            ->map(fn ($uuid): string => trim((string) $uuid))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * @param \Illuminate\Support\Collection<string, \Illuminate\Database\Eloquent\Collection<int, Page>> $pageLocks
     */
    private function lockPageBlocks($pageLocks): void
    {
        $pageIds = $pageLocks
            ->flatMap(fn ($pages) => $pages->pluck('id'))
            ->filter()
            ->unique()
            ->sort()
            ->values();
        if ($pageIds->isEmpty()) {
            return;
        }

        PageBlock::withTrashed()
            ->whereIn('page_id', $pageIds)
            ->orderBy('page_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Lock the non-Page records represented by the submitted rows. The model
     * order is stable across requests, and Category always follows Page locks.
     */
    private function lockNonPageTranslationRows(Collection $rows, string $sourceLocale, string $targetLocale): void
    {
        if ($rows->contains(fn (array $row): bool => ($row['identity']['type'] ?? null) === 'setting')) {
            SiteSetting::query()
                ->whereIn('locale', ['*', $sourceLocale, $targetLocale])
                ->orderBy('group')
                ->orderBy('key')
                ->orderBy('locale')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
        }

        if ($rows->contains(fn (array $row): bool => ($row['identity']['type'] ?? null) === 'menu')) {
            PageMenu::query()
                ->whereIn('language', [$sourceLocale, $targetLocale])
                ->orderBy('uuid')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
        }

        $contentAliases = $rows
            ->filter(fn (array $row): bool => ($row['identity']['type'] ?? null) === 'content')
            ->pluck('identity.model')
            ->filter(fn ($alias): bool => isset(self::GENERIC_CONTENT[$alias]))
            ->unique();
        $this->lockGenericContentRows($contentAliases, $sourceLocale, $targetLocale);

        $overlayAliases = $rows
            ->filter(fn (array $row): bool => ($row['identity']['type'] ?? null) === 'content_overlay')
            ->pluck('identity.model')
            ->filter(fn ($alias): bool => isset(self::OVERLAY_CONTENT[$alias]))
            ->unique()
            ->sort();
        foreach ($overlayAliases as $alias) {
            $definition = self::OVERLAY_CONTENT[$alias];
            /** @var class-string<Model> $class */
            $class = $definition['class'];
            $sourceIds = $rows
                ->filter(fn (array $row): bool =>
                    ($row['identity']['type'] ?? null) === 'content_overlay'
                    && ($row['identity']['model'] ?? null) === $alias
                )
                ->pluck('identity.source_id')
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->sort();
            $class::query()
                ->whereIn('id', $sourceIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
        }

        if ($rows->contains(fn (array $row): bool => in_array(
            $row['identity']['type'] ?? null,
            ['interface', 'content_overlay'],
            true
        ))) {
            TranslationString::query()
                ->where('locale', $targetLocale)
                ->orderBy('key')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
        }
    }

    /**
     * Lock generic translation owners after Page rows and before any dependent
     * writes. Rows use primary-key order because the SEO bulk editor locks the
     * same models by primary key.
     *
     * @param Collection<int, string> $aliases
     * @return Collection<string, Collection<int, Model>>
     */
    private function lockGenericContentRows(Collection $aliases, string $sourceLocale, string $targetLocale): Collection
    {
        return $this->genericContentAliasesInLockOrder($aliases)
            ->mapWithKeys(function (string $alias) use ($sourceLocale, $targetLocale): array {
                /** @var class-string<Model> $class */
                $class = self::GENERIC_CONTENT[$alias]['class'];
                $models = $class::withTrashed()
                    ->whereIn('language', array_values(array_unique([$sourceLocale, $targetLocale])))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                return [$alias => $models];
            });
    }

    /** @param Collection<int, string> $aliases */
    private function genericContentAliasesInLockOrder(Collection $aliases): Collection
    {
        $requested = $aliases
            ->filter(fn ($alias): bool => is_string($alias) && isset(self::GENERIC_CONTENT[$alias]))
            ->unique()
            ->flip();

        return collect(self::GENERIC_CONTENT_LOCK_ORDER)
            ->filter(fn (string $alias): bool => $requested->has($alias))
            ->values();
    }

    /**
     * Category rows are already protected by a Page-first lock plan. Reuse it
     * instead of calling remapPagesForCategory(), which would invert the order
     * by acquiring Page locks after the Category write.
     *
     * @param \Illuminate\Support\Collection<string, \Illuminate\Database\Eloquent\Collection<int, Page>> $pageLocks
     * @return array<int, string>
     */
    private function remapLockedPagesForCategory(Category $sourceCategory, Category $targetCategory, $pageLocks): array
    {
        if (blank($sourceCategory->uuid)
            || (string) $sourceCategory->uuid !== (string) $targetCategory->uuid
            || blank($sourceCategory->language)
            || blank($targetCategory->language)) {
            return [];
        }

        $sourceReferences = collect([
            (string) $sourceCategory->id,
            (string) $sourceCategory->uuid,
        ])->filter()->unique();
        $changed = [];

        foreach ($pageLocks->sortKeys() as $uuid => $logicalPages) {
            $source = $logicalPages->first(fn (Page $page): bool =>
                !$page->trashed()
                && $page->language === $sourceCategory->language
                && $sourceReferences->contains((string) ($page->category_id ?? ''))
            );
            $target = $logicalPages->first(fn (Page $page): bool =>
                !$page->trashed() && $page->language === $targetCategory->language
            );
            if (!$source instanceof Page || !$target instanceof Page) {
                continue;
            }

            $before = (string) ($target->category_id ?? '');
            $this->categoryMapper->remap($source, $target, (string) $targetCategory->language);
            if ((string) ($target->category_id ?? '') !== $before) {
                $changed[] = (string) $uuid;
            }
        }

        return $changed;
    }

    /**
     * @param \Illuminate\Support\Collection<string, \Illuminate\Database\Eloquent\Collection<int, Page>> $pageLocks
     * @return array<int, string> Logical Page UUIDs changed by this row.
     */
    private function saveRow(
        array $identity,
        string $sourceLocale,
        string $targetLocale,
        string $value,
        ?int $adminId,
        $pageLocks
    ): array
    {
        return match ($identity['type']) {
            'interface' => $this->withoutPageChanges(fn () => $this->saveInterface($identity, $sourceLocale, $targetLocale, $value, $adminId)),
            'setting' => $this->withoutPageChanges(fn () => $this->saveSetting($identity, $targetLocale, $value, $adminId)),
            'page' => $this->savePage($identity, $sourceLocale, $targetLocale, $value),
            'block' => $this->saveBlock($identity, $sourceLocale, $targetLocale, $value),
            'menu' => $this->withoutPageChanges(fn () => $this->saveMenu($identity, $sourceLocale, $targetLocale, $value)),
            'content' => $this->saveContent($identity, $sourceLocale, $targetLocale, $value, $pageLocks),
            'content_overlay' => $this->withoutPageChanges(fn () => $this->saveContentOverlay($identity, $sourceLocale, $targetLocale, $value, $adminId)),
            default => throw ValidationException::withMessages(['translations' => 'Unsupported translation row.']),
        };
    }

    /** @return array<int, string> */
    private function withoutPageChanges(callable $save): array
    {
        $save();

        return [];
    }

    private function saveInterface(array $identity, string $sourceLocale, string $targetLocale, string $value, ?int $adminId): void
    {
        $source = data_get(json_decode((string) file_get_contents(resource_path('lang/en.json')), true), $identity['path'], '');
        TranslationString::query()->updateOrCreate(
            ['key' => $identity['path'], 'locale' => $targetLocale],
            [
                'value' => $value,
                'source_hash' => hash('sha256', $sourceLocale . '|' . $source),
                'status' => $value === '' ? 'draft' : 'translated',
                'updated_by' => $adminId,
            ]
        );
    }

    private function saveSetting(array $identity, string $targetLocale, string $value, ?int $adminId): void
    {
        SiteSetting::query()->updateOrCreate(
            ['group' => $identity['group'], 'key' => $identity['field'], 'locale' => $targetLocale],
            [
                'value' => trim(strip_tags($value)),
                'type' => 'text',
                'is_public' => true,
                'updated_by' => $adminId,
                'created_by' => $adminId,
            ]
        );
    }

    /** @return array<int, string> */
    private function savePage(array $identity, string $sourceLocale, string $targetLocale, string $value): array
    {
        $source = Page::query()->whereKey($identity['source_id'])->where('language', $sourceLocale)->firstOrFail();
        $target = $this->ensureTargetPage($source, $targetLocale);
        $field = $identity['field'];
        $target->{$field} = $field === 'description'
            ? $this->sanitizer->sanitizeHtml($value)
            : trim(strip_tags($value));
        $target->save();

        return [(string) $target->uuid];
    }

    /** @return array<int, string> */
    private function saveBlock(array $identity, string $sourceLocale, string $targetLocale, string $value): array
    {
        $sourcePage = Page::query()->whereKey($identity['source_page_id'])->where('language', $sourceLocale)->firstOrFail();
        $sourceBlock = $sourcePage->blocks()->whereKey($identity['source_block_id'])->firstOrFail();
        $targetPage = $this->ensureTargetPage($sourcePage, $targetLocale);
        $translationKey = (string) ($sourceBlock->translation_key ?: $sourceBlock->uuid);
        $targetBlock = $targetPage->blocks()->where('translation_key', $translationKey)->firstOrFail();
        $content = $targetBlock->content ?? [];
        data_set($content, $identity['path'], $value);
        $targetBlock->update(['content' => $this->sanitizer->sanitizeBlockContent($content)]);

        return [(string) $targetPage->uuid];
    }

    private function saveMenu(array $identity, string $sourceLocale, string $targetLocale, string $value): void
    {
        $source = PageMenu::query()->whereKey($identity['source_id'])->where('language', $sourceLocale)->firstOrFail();
        $target = $this->ensureTargetMenu($source, $sourceLocale, $targetLocale);
        $field = $identity['field'] ?? 'name';
        if (!in_array($field, ['name', 'description'], true)) {
            throw ValidationException::withMessages(['translations' => 'Unsupported navigation translation row.']);
        }
        $target->update([$field => trim(strip_tags($value))]);
    }

    /**
     * @param \Illuminate\Support\Collection<string, \Illuminate\Database\Eloquent\Collection<int, Page>> $pageLocks
     * @return array<int, string>
     */
    private function saveContent(array $identity, string $sourceLocale, string $targetLocale, string $value, $pageLocks): array
    {
        $definition = self::GENERIC_CONTENT[$identity['model']] ?? null;
        if (!$definition || !in_array($identity['field'], $definition['fields'], true)) {
            throw ValidationException::withMessages(['translations' => 'Unsupported content translation row.']);
        }
        $class = $definition['class'];
        $source = $class::query()->whereKey($identity['source_id'])->where('language', $sourceLocale)->firstOrFail();
        $keyField = $definition['key'];
        $target = $class::query()->where($keyField, $source->{$keyField})->where('language', $targetLocale)->first();
        if (!$target) {
            $target = $source->replicate();
            $target->language = $targetLocale;
            foreach ($definition['fields'] as $field) {
                if (array_key_exists($field, $source->getAttributes())) {
                    $target->{$field} = '';
                }
            }
            foreach (['meta_title', 'meta_keyword', 'meta_description'] as $legacySeoField) {
                if (array_key_exists($legacySeoField, $target->getAttributes())) {
                    $target->{$legacySeoField} = '';
                }
            }
            if ($target->isFillable('status')) {
                $target->status = 0;
            }
            $target->save();
        }
        $target->{$identity['field']} = in_array($identity['field'], ['description', 'testimonial'], true)
            ? $this->sanitizer->sanitizeHtml($value)
            : trim(strip_tags($value));
        $target->save();

        if (($identity['model'] ?? null) === 'category'
            && $source instanceof Category
            && $target instanceof Category) {
            return $this->remapLockedPagesForCategory($source, $target, $pageLocks);
        }

        return [];
    }

    private function saveContentOverlay(
        array $identity,
        string $sourceLocale,
        string $targetLocale,
        string $value,
        ?int $adminId
    ): void {
        $definition = self::OVERLAY_CONTENT[$identity['model']] ?? null;
        if (!$definition || !in_array($identity['field'], $definition['fields'], true)) {
            throw ValidationException::withMessages(['translations' => 'Unsupported content translation row.']);
        }

        $class = $definition['class'];
        $source = $class::query()->whereKey($identity['source_id'])->firstOrFail();
        if (in_array($identity['model'], ['team_member', 'team_group'], true)
            && $source->language !== $sourceLocale) {
            throw ValidationException::withMessages(['translations' => 'The team source language changed. Refresh and try again.']);
        }

        $field = $identity['field'];
        $cleanValue = $field === 'description' && $identity['model'] !== 'team_group'
            ? $this->sanitizer->sanitizeHtml($value)
            : trim(strip_tags($value));
        $sourceValue = (string) ($source->{$field} ?? '');
        $storageKey = $this->overlayStorageKey(
            $identity['model'],
            (string) $source->{$definition['key']},
            $field
        );

        TranslationString::query()->updateOrCreate(
            ['key' => $storageKey, 'locale' => $targetLocale],
            [
                'value' => $cleanValue,
                'source_hash' => hash('sha256', $sourceLocale . '|' . $sourceValue),
                'status' => $cleanValue === '' ? 'draft' : 'translated',
                'updated_by' => $adminId,
            ]
        );
    }

    private function overlayStorageKey(string $model, string $identity, string $field): string
    {
        return 'content.' . $model . '.' . $identity . '.' . $field;
    }

    private function ensureTargetPage(Page $source, string $targetLocale): Page
    {
        $target = Page::query()->where('uuid', $source->uuid)->where('language', $targetLocale)->first();
        if (!$target) {
            $target = $source->replicate();
            $target->language = $targetLocale;
            $target->category_id = $this->categoryMapper->targetCategoryId($source, $targetLocale);
            $this->preparePageTranslationDraft($target);
            $target->save();
        } else {
            $this->categoryMapper->remap($source, $target, $targetLocale);
        }

        $existing = $target->blocks()->get()->keyBy('translation_key');
        foreach ($source->blocks()->get() as $sourceBlock) {
            $translationKey = (string) ($sourceBlock->translation_key ?: $sourceBlock->uuid);
            if ($existing->has($translationKey)) {
                $existingTarget = $existing->get($translationKey);
                if ($existingTarget?->reusable_block_id) {
                    $existingTarget->update([
                        'reusable_block_id' => null,
                        'content' => $this->prepareBlockTranslationContent($sourceBlock->resolvedContent()),
                        'settings' => $sourceBlock->resolvedSettings(),
                    ]);
                }
                continue;
            }
            $copy = $sourceBlock->replicate();
            $copy->page_id = $target->id;
            $copy->uuid = (string) Str::uuid();
            $copy->translation_key = $translationKey;
            $copy->reusable_block_id = null;
            $copy->content = $this->prepareBlockTranslationContent($sourceBlock->resolvedContent());
            $copy->settings = $sourceBlock->resolvedSettings();
            $copy->save();
        }

        return $target;
    }

    private function blankTranslatedBlockValues(array $content): array
    {
        foreach ($content as $key => $value) {
            if (!$this->isTranslatableBlockKey((string) $key)) {
                continue;
            }
            if (is_array($value)) {
                $content[$key] = $this->blankTranslatedBlockValues($value);
            } elseif (is_string($value)) {
                $content[$key] = '';
            }
        }

        return $content;
    }

    private function ensureTargetMenu(PageMenu $source, string $sourceLocale, string $targetLocale): PageMenu
    {
        $target = PageMenu::query()->where('uuid', $source->uuid)->where('language', $targetLocale)->first();
        if ($target) {
            return $target;
        }

        $targetParent = null;
        if ($source->parent_id) {
            $sourceParent = PageMenu::query()->whereKey($source->parent_id)->where('language', $sourceLocale)->first();
            if ($sourceParent) {
                $targetParent = $this->ensureTargetMenu($sourceParent, $sourceLocale, $targetLocale);
            }
        }
        $target = $source->replicate();
        $target->language = $targetLocale;
        $target->name = '';
        $target->description = '';
        $target->parent_id = $targetParent?->id;
        $target->status = 0;
        $target->save();

        return $target;
    }

    private function validatePlaceholders(string $source, string $target, string $field): void
    {
        if ($target === '') {
            return;
        }
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*|\{\{\s*[A-Za-z_][A-Za-z0-9_]*\s*\}\}|(?<!\{)\{[A-Za-z_][A-Za-z0-9_]*\}(?!\})/', $source, $sourceTokens);
        foreach (array_unique($sourceTokens[0] ?? []) as $token) {
            if (!str_contains($target, $token)) {
                throw ValidationException::withMessages(['translations' => "{$field} must keep the placeholder {$token}."]);
            }
        }
    }
}
