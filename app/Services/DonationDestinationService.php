<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Donation;
use App\Models\DonationType;
use App\Models\MediaAsset;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
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

    /** @return Collection<int, DonationType> */
    public function activeCauses(?string $locale = null): Collection
    {
        return DonationType::query()
            ->active()
            ->with('imageAsset')
            ->orderBy('id')
            ->get()
            ->filter(fn (DonationType $cause): bool => $this->isOperational($cause, $locale))
            ->values();
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

    public function publicOption(
        DonationType $cause,
        ?string $locale = null,
        ?string $localizedName = null,
        ?string $localizedDescription = null,
        ?string $localizedDestinationName = null
    ): array {
        $locale ??= app()->getLocale();
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
        $legacyAsset = MediaAsset::withTrashed()
            ->get(['uuid', 'disk', 'path'])
            ->first(function (MediaAsset $asset) use ($stored, $storedPath): bool {
                $assetPath = ltrim(str_replace('\\', '/', (string) $asset->path), '/');
                $currentPath = ltrim(str_replace('\\', '/', (string) (parse_url($asset->url, PHP_URL_PATH) ?: '')), '/');

                return hash_equals((string) $asset->url, $stored)
                    || in_array($storedPath, [$assetPath, 'storage/' . $assetPath, $currentPath], true);
            });

        return $legacyAsset ? (string) $legacyAsset->url : '';
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

    private function publicPageOption(Page $page, string $locale): array
    {
        $category = $this->categoryForPage($page, $locale);

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
