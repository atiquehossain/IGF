<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Donation;
use App\Models\DonationType;
use App\Models\MediaAsset;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DonationDestinationService
{
    public function resolveActiveCause(string $identifier, ?string $locale = null): ?DonationType
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $cause = DonationType::query()
            ->active()
            ->where(fn (Builder $query) => $query
                ->where('uuid', $identifier)
                ->orWhere('slug', $identifier))
            ->first();

        return $cause && $this->isOperational($cause, $locale) ? $cause : null;
    }

    public function isOperational(DonationType $cause, ?string $locale = null): bool
    {
        if (!$cause->status || !array_key_exists((string) $cause->destination_type, DonationType::DESTINATION_OPTIONS)) {
            return false;
        }

        $locale ??= app()->getLocale();
        $isZakat = $cause->purpose_key === 'zakat';

        return match ($cause->destination_type) {
            'unrestricted' => !$isZakat,
            'restricted_fund' => trim((string) $cause->destination_name) !== '',
            // A category designation is a valid restricted fund in its own
            // right. Child projects may temporarily be unavailable without
            // silently withdrawing the published cause from donors.
            'category' => $this->preferredCategory(
                (string) $cause->destination_category_uuid,
                $locale,
                true
            ) !== null,
            'page' => ($page = $this->preferredFundingPublicPage((string) $cause->destination_page_uuid, $locale)) !== null
                && (!$isZakat || (bool) $page->is_zakat_eligible),
            default => false,
        };
    }

    public function hasReviewedDescription(?string $description): bool
    {
        $description = trim((string) $description);

        return $description !== ''
            && !str_starts_with(mb_strtolower($description), 'draft giving option.');
    }

    public function isReadyForPublication(DonationType $cause, ?string $locale = null): bool
    {
        $candidate = clone $cause;
        $candidate->status = true;

        return $this->hasReviewedDescription($candidate->description)
            && $this->isOperational($candidate, $locale);
    }

    /** @return Collection<int, DonationType> */
    public function activeCauses(?string $locale = null): Collection
    {
        $locale ??= app()->getLocale();
        $causes = DonationType::query()
            ->active()
            ->with(['imageAsset', 'causeGroup'])
            ->orderByRaw('CASE WHEN display_order IS NULL THEN 1 ELSE 0 END')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        $this->hydrateLegacyCauseImages($causes);

        $categoryUuids = $causes
            ->where('destination_type', 'category')
            ->pluck('destination_category_uuid')
            ->filter()
            ->unique()
            ->values();
        $categories = $categoryUuids->isEmpty()
            ? collect()
            : Category::query()
                ->whereIn('uuid', $categoryUuids)
                ->where('status', 1)
                ->get()
                ->groupBy('uuid');
        $pageUuids = $causes
            ->where('destination_type', 'page')
            ->pluck('destination_page_uuid')
            ->filter()
            ->unique()
            ->values();
        $pages = $pageUuids->isEmpty()
            ? collect()
            : Page::query()
                ->publiclyAvailable()
                ->where('visibility', 'public')
                ->where('is_funding_project', true)
                ->whereIn('uuid', $pageUuids)
                ->get()
                ->groupBy('uuid');

        return $causes
            ->filter(fn (DonationType $cause): bool => $this->isOperationalFromBatch(
                $cause,
                $categories,
                $pages,
                $locale
            ))
            ->values();
    }

    private function isOperationalFromBatch(
        DonationType $cause,
        Collection $categories,
        Collection $pages,
        string $locale
    ): bool {
        if (!$cause->status || !array_key_exists((string) $cause->destination_type, DonationType::DESTINATION_OPTIONS)) {
            return false;
        }

        $isZakat = $cause->purpose_key === 'zakat';

        return match ($cause->destination_type) {
            'unrestricted' => !$isZakat,
            'restricted_fund' => trim((string) $cause->destination_name) !== '',
            'category' => $this->preferredLocalized(
                $categories->get((string) $cause->destination_category_uuid, collect()),
                $locale
            ) !== null,
            'page' => ($page = $this->preferredLocalized(
                $pages->get((string) $cause->destination_page_uuid, collect()),
                $locale
            )) instanceof Page && (!$isZakat || (bool) $page->is_zakat_eligible),
            default => false,
        };
    }

    /**
     * @return array{cause: DonationType, project: ?Page, destination_type: string, destination_uuid: ?string, destination_name: string}
     */
    public function resolveCheckoutSelection(
        DonationType $cause,
        ?string $projectUuid,
        ?string $locale = null
    ): array {
        $locale ??= app()->getLocale();
        $projectUuid = trim((string) $projectUuid);

        if (!$cause->status || !$this->isOperational($cause, $locale)) {
            throw ValidationException::withMessages([
                'payment_cause' => 'This donation cause is not currently available.',
            ]);
        }

        $project = null;
        if ($cause->destination_type === 'page') {
            $fixedUuid = (string) $cause->destination_page_uuid;
            if ($projectUuid !== '' && !hash_equals($fixedUuid, $projectUuid)) {
                throw ValidationException::withMessages([
                    'project_uuid' => 'This cause can only support its configured project.',
                ]);
            }
            $project = $this->preferredFundingPublicPage($fixedUuid, $locale);
        } elseif ($cause->destination_type === 'category' && $projectUuid !== '') {
            $project = $this->selectablePages($cause, $locale)->firstWhere('uuid', $projectUuid);
            if (!$project) {
                throw ValidationException::withMessages([
                    'project_uuid' => 'Choose a published project within the selected program.',
                ]);
            }
        } elseif ($projectUuid !== '') {
            throw ValidationException::withMessages([
                'project_uuid' => 'A project cannot be attached to this donation cause.',
            ]);
        }

        if ($project && $cause->purpose_key === 'zakat' && !(bool) $project->is_zakat_eligible) {
            throw ValidationException::withMessages([
                'project_uuid' => 'Choose a project that is approved for Zakat allocations.',
            ]);
        }

        return [
            'cause' => $cause,
            'project' => $project,
            'destination_type' => (string) $cause->destination_type,
            'destination_uuid' => $this->destinationUuid($cause),
            'destination_name' => $this->destinationName($cause, $locale),
        ];
    }

    /**
     * Build every public catalog option from a fixed number of destination and
     * media queries. The localized values are keyed by immutable cause UUID.
     *
     * @param Collection<int, DonationType> $causes
     * @param array<string, array{name?: string, description?: string, destination_name?: ?string}> $localizedByUuid
     * @return Collection<int, array<string, mixed>>
     */
    public function publicOptions(
        Collection $causes,
        ?string $locale = null,
        array $localizedByUuid = []
    ): Collection {
        $locale ??= app()->getLocale();
        if ($causes instanceof EloquentCollection) {
            $causes->loadMissing('causeGroup');
        }
        $causes = $causes->values();
        if ($causes->isEmpty()) {
            return collect();
        }

        $this->hydrateLegacyCauseImages($causes);

        $categoryUuids = $causes
            ->where('destination_type', 'category')
            ->pluck('destination_category_uuid')
            ->filter()
            ->unique()
            ->values();
        $categoryRows = $categoryUuids->isEmpty()
            ? collect()
            : Category::query()->whereIn('uuid', $categoryUuids)->get();
        $categoryKeysByUuid = $categoryRows
            ->groupBy('uuid')
            ->map(fn (Collection $rows): Collection => $rows
                ->flatMap(fn (Category $category): array => [(string) $category->id, (string) $category->uuid])
                ->filter()
                ->unique()
                ->values());
        $allCategoryKeys = $categoryKeysByUuid->flatten()->unique()->values();
        $pageUuids = $causes
            ->where('destination_type', 'page')
            ->pluck('destination_page_uuid')
            ->filter()
            ->unique()
            ->values();

        $pages = collect();
        if ($pageUuids->isNotEmpty() || $allCategoryKeys->isNotEmpty()) {
            $pages = Page::query()
                ->publiclyAvailable()
                ->where('visibility', 'public')
                ->where('is_funding_project', true)
                ->where(function (Builder $query) use ($pageUuids, $allCategoryKeys): void {
                    if ($pageUuids->isNotEmpty()) {
                        $query->whereIn('uuid', $pageUuids);
                    }
                    if ($allCategoryKeys->isNotEmpty()) {
                        $pageUuids->isNotEmpty()
                            ? $query->orWhereIn('category_id', $allCategoryKeys)
                            : $query->whereIn('category_id', $allCategoryKeys);
                    }
                })
                ->orderBy('order_by')
                ->orderBy('id')
                ->get();
        }

        $pageCategoryIdentifiers = $pages
            ->pluck('category_id')
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values();
        $pageCategoryRows = collect();
        if ($pageCategoryIdentifiers->isNotEmpty()) {
            $numericIds = $pageCategoryIdentifiers->filter(fn (string $value): bool => ctype_digit($value));
            $uuidIds = $pageCategoryIdentifiers->reject(fn (string $value): bool => ctype_digit($value));
            $pageCategoryRows = Category::withTrashed()
                ->where(function (Builder $query) use ($numericIds, $uuidIds): void {
                    if ($numericIds->isNotEmpty()) {
                        $query->whereIn('id', $numericIds);
                    }
                    if ($uuidIds->isNotEmpty()) {
                        $numericIds->isNotEmpty()
                            ? $query->orWhereIn('uuid', $uuidIds)
                            : $query->whereIn('uuid', $uuidIds);
                    }
                })
                ->get();
        }

        return $causes->map(function (DonationType $cause) use (
            $locale,
            $localizedByUuid,
            $categoryRows,
            $categoryKeysByUuid,
            $pages,
            $pageCategoryRows
        ): array {
            $localized = $localizedByUuid[(string) $cause->uuid] ?? [];
            $name = array_key_exists('name', $localized)
                ? (string) $localized['name']
                : (string) $cause->name;
            $description = array_key_exists('description', $localized)
                ? (string) $localized['description']
                : (string) $cause->description;
            $eligiblePages = match ((string) $cause->destination_type) {
                'page' => $pages->where('uuid', (string) $cause->destination_page_uuid),
                'category' => $pages->filter(fn (Page $page): bool => $categoryKeysByUuid
                    ->get((string) $cause->destination_category_uuid, collect())
                    ->contains((string) $page->category_id)),
                default => collect(),
            };
            if ($cause->purpose_key === 'zakat') {
                $eligiblePages = $eligiblePages->where('is_zakat_eligible', true);
            }
            $preferredPages = $this->preferredLogicalPages($eligiblePages, $locale);
            $projects = $preferredPages
                ->map(fn (Page $page): array => $this->publicPageOption($page, $locale, $pageCategoryRows))
                ->values()
                ->all();
            $destinationName = match ((string) $cause->destination_type) {
                'unrestricted' => $name,
                'restricted_fund' => array_key_exists('destination_name', $localized)
                    ? (string) $localized['destination_name']
                    : (string) $cause->destination_name,
                'category' => (string) ($this->preferredLocalized(
                    $categoryRows->where('uuid', (string) $cause->destination_category_uuid),
                    $locale
                )?->name ?? 'Unavailable program'),
                'page' => (string) ($preferredPages->first()?->name ?? 'Unavailable project'),
                default => 'Unavailable destination',
            };

            return [
                'uuid' => (string) $cause->uuid,
                'slug' => (string) $cause->slug,
                'name' => $name,
                'description' => $description,
                'image' => $this->causeImageUrl($cause),
                'icon_key' => (string) ($cause->icon_key ?? ''),
                'group_uuid' => $cause->causeGroup && $cause->causeGroup->status
                    ? (string) $cause->causeGroup->uuid
                    : null,
                'destination_type' => (string) $cause->destination_type,
                'destination_uuid' => $this->destinationUuid($cause),
                'destination_name' => $destinationName,
                'project_selection' => match ($cause->destination_type) {
                    'category' => 'optional',
                    'page' => 'fixed',
                    default => 'none',
                },
                'projects' => $projects,
            ];
        })->values();
    }

    public function publicOption(
        DonationType $cause,
        ?string $locale = null,
        ?string $localizedName = null,
        ?string $localizedDescription = null,
        ?string $localizedDestinationName = null
    ): array {
        $locale ??= app()->getLocale();
        $cause->loadMissing('causeGroup');
        $projects = $this->selectablePages($cause, $locale)
            ->map(fn (Page $page): array => $this->publicPageOption($page, $locale))
            ->values()
            ->all();

        return [
            'uuid' => (string) $cause->uuid,
            'slug' => (string) $cause->slug,
            'name' => $localizedName ?? (string) $cause->name,
            'description' => $localizedDescription ?? (string) $cause->description,
            'image' => $this->causeImageUrl($cause),
            'icon_key' => (string) ($cause->icon_key ?? ''),
            'group_uuid' => $cause->causeGroup && $cause->causeGroup->status
                ? (string) $cause->causeGroup->uuid
                : null,
            'destination_type' => (string) $cause->destination_type,
            'destination_uuid' => $this->destinationUuid($cause),
            'destination_name' => match ((string) $cause->destination_type) {
                'unrestricted' => $localizedName ?? (string) $cause->name,
                'restricted_fund' => $localizedDestinationName ?? $this->destinationName($cause, $locale),
                default => $this->destinationName($cause, $locale),
            },
            'project_selection' => match ($cause->destination_type) {
                'category' => 'optional',
                'page' => 'fixed',
                default => 'none',
            },
            'projects' => $projects,
        ];
    }

    /** @return Collection<int, Page> */
    public function selectablePages(DonationType $cause, ?string $locale = null): Collection
    {
        $locale ??= app()->getLocale();
        $query = Page::query()
            ->publiclyAvailable()
            ->where('visibility', 'public')
            ->where('is_funding_project', true);

        if ($cause->destination_type === 'page') {
            $query->where('uuid', $cause->destination_page_uuid);
        } elseif ($cause->destination_type === 'category') {
            $keys = $this->categoryKeys((string) $cause->destination_category_uuid, false);
            if ($keys === []) {
                return collect();
            }
            $query->whereIn('category_id', $keys);
        } else {
            return collect();
        }

        if ($cause->purpose_key === 'zakat') {
            $query->where('is_zakat_eligible', true);
        }

        return $this->preferredLogicalPages($query->orderBy('order_by')->orderBy('id')->get(), $locale);
    }

    /** @return Collection<int, Page> */
    public function allocationPages(Donation $donation, ?string $locale = null): Collection
    {
        if ($donation->project_uuid_snapshot || $donation->destination_type_snapshot === 'page') {
            return collect();
        }
        if (!$this->hasResolvedDesignation($donation) || !in_array(
            (string) $donation->destination_type_snapshot,
            ['unrestricted', 'restricted_fund', 'category'],
            true
        )) {
            // Unknown legacy donor intent must be reconciled through an
            // explicit audited workflow, never inferred as broad funding.
            return collect();
        }

        $locale ??= app()->getLocale();
        $query = Page::query()
            ->publiclyAvailable()
            ->where('visibility', 'public')
            ->where('is_funding_project', true);
        if ($donation->destination_type_snapshot === 'category') {
            $keys = $this->categoryKeys((string) $donation->destination_uuid_snapshot, true);
            if ($keys === []) {
                return collect();
            }
            $query->whereIn('category_id', $keys);
        }
        if ($donation->purpose_key_snapshot === 'zakat') {
            $query->where('is_zakat_eligible', true);
        }

        return $this->preferredLogicalPages($query->orderBy('order_by')->orderBy('id')->get(), $locale);
    }

    public function hasResolvedDesignation(Donation $donation): bool
    {
        return filled($donation->cause_uuid_snapshot)
            && in_array((string) $donation->destination_type_snapshot, [
                'unrestricted', 'restricted_fund', 'category', 'page',
            ], true)
            && !in_array((string) $donation->cause_slug_snapshot, [
                'unspecified-legacy-donation', 'unresolved-legacy-gift',
            ], true)
            && (string) $donation->cause_name_snapshot !== 'Unspecified legacy donation';
    }

    public function destinationUuid(DonationType $cause): ?string
    {
        return match ($cause->destination_type) {
            'category' => $cause->destination_category_uuid ?: null,
            'page' => $cause->destination_page_uuid ?: null,
            default => null,
        };
    }

    public function causeImageUrl(DonationType $cause): string
    {
        // The immutable Media Library UUID is authoritative. Always derive a
        // fresh URL so an APP_URL, CDN or storage-host change cannot make a
        // trusted cause image disappear.
        if ($cause->imageAsset
            && hash_equals((string) $cause->image_media_uuid, (string) $cause->imageAsset->uuid)) {
            return (string) $cause->imageAsset->url;
        }

        $stored = trim((string) $cause->image);
        if ($stored === '') {
            return '';
        }
        $storedPath = ltrim(str_replace('\\', '/', (string) (parse_url($stored, PHP_URL_PATH) ?: $stored)), '/');
        if ($cause->relationLoaded('legacyImageAsset')) {
            $legacyAsset = $cause->getRelation('legacyImageAsset');

            return $legacyAsset instanceof MediaAsset ? (string) $legacyAsset->url : '';
        }

        return (string) ($this->legacyAssetForStoredImage($stored, $storedPath)?->url ?? '');
    }

    /** @param Collection<int, DonationType> $causes */
    private function hydrateLegacyCauseImages(Collection $causes): void
    {
        $causes->loadMissing('imageAsset');

        $pending = $causes->filter(function (DonationType $cause): bool {
            if ($cause->relationLoaded('legacyImageAsset')) {
                return false;
            }

            return !($cause->imageAsset
                && hash_equals((string) $cause->image_media_uuid, (string) $cause->imageAsset->uuid))
                && trim((string) $cause->image) !== '';
        });
        $candidatePaths = $pending
            ->flatMap(fn (DonationType $cause): array => $this->legacyImagePathCandidates((string) $cause->image))
            ->unique()
            ->values();
        $assets = $candidatePaths->isEmpty()
            ? collect()
            : MediaAsset::withTrashed()
                ->whereIn('path', $candidatePaths)
                ->get(['uuid', 'disk', 'path']);

        $pending->each(function (DonationType $cause) use ($assets): void {
            $stored = trim((string) $cause->image);
            $storedPath = ltrim(str_replace('\\', '/', (string) (parse_url($stored, PHP_URL_PATH) ?: $stored)), '/');
            $cause->setRelation(
                'legacyImageAsset',
                $assets->first(fn (MediaAsset $asset): bool => $this->legacyAssetMatchesStoredImage(
                    $asset,
                    $stored,
                    $storedPath
                ))
            );
        });
    }

    private function legacyAssetForStoredImage(string $stored, string $storedPath): ?MediaAsset
    {
        $candidates = $this->legacyImagePathCandidates($stored);
        if ($candidates === []) {
            return null;
        }

        return MediaAsset::withTrashed()
            ->whereIn('path', $candidates)
            ->get(['uuid', 'disk', 'path'])
            ->first(fn (MediaAsset $asset): bool => $this->legacyAssetMatchesStoredImage($asset, $stored, $storedPath));
    }

    /** @return list<string> */
    private function legacyImagePathCandidates(string $stored): array
    {
        $path = ltrim(str_replace('\\', '/', (string) (parse_url($stored, PHP_URL_PATH) ?: $stored)), '/');
        $withoutStoragePrefix = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;

        return array_values(array_unique(array_filter([$path, $withoutStoragePrefix])));
    }

    private function legacyAssetMatchesStoredImage(MediaAsset $asset, string $stored, string $storedPath): bool
    {
        $assetPath = ltrim(str_replace('\\', '/', (string) $asset->path), '/');
        $currentPath = ltrim(str_replace('\\', '/', (string) (parse_url($asset->url, PHP_URL_PATH) ?: '')), '/');

        return hash_equals((string) $asset->url, $stored)
            || in_array($storedPath, [$assetPath, 'storage/' . $assetPath, $currentPath], true);
    }

    public function destinationName(DonationType $cause, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return match ($cause->destination_type) {
            'unrestricted' => 'Where it is needed most',
            'restricted_fund' => trim((string) $cause->destination_name),
            'category' => (string) ($this->preferredCategory(
                (string) $cause->destination_category_uuid,
                $locale,
                false
            )?->name ?? 'Unavailable program'),
            'page' => (string) ($this->preferredPageIncludingDrafts(
                (string) $cause->destination_page_uuid,
                $locale
            )?->name ?? 'Unavailable project'),
            default => 'Unavailable destination',
        };
    }

    public function preferredPublicPage(string $uuid, ?string $locale = null): ?Page
    {
        if (trim($uuid) === '') {
            return null;
        }

        return $this->preferredLogicalPages(
            Page::query()->publiclyAvailable()->where('visibility', 'public')->where('uuid', $uuid)->get(),
            $locale ?? app()->getLocale()
        )->first();
    }

    public function preferredFundingPublicPage(string $uuid, ?string $locale = null): ?Page
    {
        if (trim($uuid) === '') {
            return null;
        }

        return $this->preferredLogicalPages(
            Page::query()
                ->publiclyAvailable()
                ->where('visibility', 'public')
                ->where('is_funding_project', true)
                ->where('uuid', $uuid)
                ->get(),
            $locale ?? app()->getLocale()
        )->first();
    }

    public function isFundingCategory(string $uuid): bool
    {
        $keys = $this->categoryKeys($uuid, false);
        if ($keys === [] || !$this->preferredCategory($uuid, app()->getLocale(), true)) {
            return false;
        }

        return Page::query()
            ->publiclyAvailable()
            ->where('visibility', 'public')
            ->where('is_funding_project', true)
            ->whereIn('category_id', $keys)
            ->exists();
    }

    /**
     * Lock every locale row for one logical page before a financial
     * eligibility decision. The caller must already be inside a transaction.
     */
    public function lockPageRows(string $uuid): void
    {
        if (trim($uuid) === '') {
            return;
        }

        Page::withTrashed()
            ->where('uuid', $uuid)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    /** The caller must already be inside a transaction. */
    public function lockDestinationRows(DonationType $cause): void
    {
        if ($cause->destination_type === 'page') {
            $this->lockPageRows((string) $cause->destination_page_uuid);
        } elseif ($cause->destination_type === 'category' && $cause->destination_category_uuid) {
            Category::withTrashed()
                ->where('uuid', $cause->destination_category_uuid)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
        }
    }

    public function preferredPageIncludingDrafts(string $uuid, ?string $locale = null): ?Page
    {
        if (trim($uuid) === '') {
            return null;
        }

        return $this->preferredLogicalPages(
            Page::query()->where('uuid', $uuid)->get(),
            $locale ?? app()->getLocale()
        )->first();
    }

    public function preferredCategory(
        string $uuid,
        ?string $locale = null,
        bool $activeOnly = false,
        bool $withTrashed = false
    ): ?Category {
        if (trim($uuid) === '') {
            return null;
        }

        $query = $withTrashed ? Category::withTrashed() : Category::query();
        $categories = $query->where('uuid', $uuid)
            ->when($activeOnly, fn ($builder) => $builder->where('status', 1))
            ->get();

        return $this->preferredLocalized($categories, $locale ?? app()->getLocale());
    }

    /** @return list<string> */
    private function categoryKeys(string $uuid, bool $withTrashed): array
    {
        if (trim($uuid) === '') {
            return [];
        }

        $query = $withTrashed ? Category::withTrashed() : Category::query();

        return $query->where('uuid', $uuid)
            ->get(['id', 'uuid'])
            ->flatMap(fn (Category $category): array => [(string) $category->id, (string) $category->uuid])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function publicPageOption(Page $page, string $locale, ?Collection $categories = null): array
    {
        $category = $categories === null
            ? $this->categoryForPage($page, $locale)
            : $this->categoryForPageFromRows($page, $locale, $categories);

        return [
            'uuid' => (string) $page->uuid,
            'name' => (string) $page->name,
            'slug' => (string) $page->slug,
            'category_uuid' => $category?->uuid ? (string) $category->uuid : null,
            'is_funding_project' => (bool) $page->is_funding_project,
            'is_zakat_eligible' => (bool) $page->is_zakat_eligible,
        ];
    }

    private function categoryForPage(Page $page, string $locale): ?Category
    {
        $value = trim((string) $page->category_id);
        if ($value === '') {
            return null;
        }

        $categories = Category::withTrashed()
            ->where(fn ($query) => $query->where('id', $value)->orWhere('uuid', $value))
            ->get();

        return $this->preferredLocalized($categories, $locale);
    }

    private function categoryForPageFromRows(Page $page, string $locale, Collection $categories): ?Category
    {
        $value = trim((string) $page->category_id);
        if ($value === '') {
            return null;
        }

        return $this->preferredLocalized(
            $categories->filter(fn (Category $category): bool => in_array(
                $value,
                [(string) $category->id, (string) $category->uuid],
                true
            )),
            $locale
        );
    }

    /** @return Collection<int, Page> */

    private function preferredLogicalPages(Collection $pages, string $locale): Collection
    {
        return $pages
            ->groupBy(fn (Page $page): string => (string) ($page->uuid ?: 'row-' . $page->id))
            ->map(fn (Collection $translations): ?Page => $this->preferredLocalized($translations, $locale))
            ->filter()
            ->values();
    }

    private function preferredLocalized(Collection $models, string $locale): mixed
    {
        $fallback = (string) config('app.fallback_locale', 'en');

        return $models->firstWhere('language', $locale)
            ?? $models->firstWhere('language', $fallback)
            ?? $models->first();
    }
}
