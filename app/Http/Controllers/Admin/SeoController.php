<?php

namespace App\Http\Controllers\Admin;

use App\Data\SeoMetadataPayload;
use App\Http\Controllers\Controller;
use App\Http\Middleware\Permission;
use App\Models\AnnualReport;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\SeoMetadata;
use App\Models\SeoMetadataRevision;
use App\Models\SeoRedirect;
use App\Models\Tag;
use App\Services\SeoHealthService;
use App\Services\LocalizationManager;
use App\Services\PageEditorVersionService;
use App\Services\SeoEditorialReviewService;
use App\Services\SeoMetadataEditorVersionService;
use App\Services\SeoMetadataRevisionService;
use App\Services\SeoMetadataService;
use App\Services\SeoRedirectService;
use App\Services\SeoRouteRegistry;
use App\Services\SeoSchemaTemplateService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SeoController extends Controller
{
    public function __construct(
        private SeoMetadataService $seo,
        private SeoRouteRegistry $routeRegistry,
        private SeoRedirectService $redirects,
        private SeoMetadataRevisionService $revisions,
        private SeoSchemaTemplateService $schemaTemplates,
        private SeoHealthService $health,
        private LocalizationManager $localization,
        private PageEditorVersionService $pageEditorVersions,
        private SeoMetadataEditorVersionService $seoEditorVersions,
        private SeoEditorialReviewService $seoReviews
    ) {
    }

    public function index(Request $request)
    {
        $routes = $this->routeRegistry->routes();
        abort_if($routes->isEmpty(), 500, 'No safe SEO routes are configured.');

        $locales = $this->locales();
        $locale = $this->requestedLocale($request, $locales);
        $selectedName = (string) $request->query('route', $this->defaultRouteName());
        abort_unless($this->routeRegistry->has($selectedName), 404);

        $definition = $this->routeRegistry->definition($selectedName) ?: [];
        $managedPage = $this->managedPageForRoute($definition, $locale);
        $missingManagedPageTranslation = false;
        if (!$managedPage && $locale !== $this->defaultLocale() && filled($definition['page_slug'] ?? null)) {
            $sourcePage = Page::query()
                ->where('slug', (string) $definition['page_slug'])
                ->where('language', $this->defaultLocale())
                ->first();
            $missingManagedPageTranslation = $sourcePage instanceof Page && filled($sourcePage->uuid);
        }
        $pageEditorVersion = null;
        if ($managedPage instanceof Page) {
            [$managedPage, $pageEditorVersion] = $this->pageEditorRenderSnapshot($managedPage, $locale);
        }
        $selectedPath = (string) $this->routeRegistry->path($selectedName);
        $defaultCanonical = (string) $this->seo->localizedUrl(url($selectedPath), $locale, $this->defaultLocale());
        $seoSnapshot = $managedPage
            ? $this->metadataSnapshotForModel($managedPage, $locale)
            : SeoMetadata::withTrashed()->where('route_name', $selectedName)->where('locale', $locale)->first();
        $seo = $this->visibleSeoSnapshot($seoSnapshot);
        [$copySeo, $copyFallback] = $request->query('copy') === 'en' && $locale !== 'en'
            ? ($managedPage
                ? $this->englishSource($managedPage, 'page')
                : [SeoMetadata::where('route_name', $selectedName)->where('locale', 'en')->first(), $this->fallbackForRoute($selectedName, $definition)])
            : [null, null];
        $fallback = $managedPage
            ? $this->fallbackForModel($managedPage, 'page')
            : $this->fallbackForRoute($selectedName, $definition);
        $editor = $this->editorState(
            $seo,
            $fallback,
            $defaultCanonical,
            $locale,
            $managedPage ? 'page' : 'route',
            $selectedName,
            $managedPage,
            $copySeo,
            $copyFallback,
            $pageEditorVersion,
            $seoSnapshot
        );
        $dashboard = $this->dashboard($request, $locale);
        $permission = app(Permission::class);
        $admin = $request->user('admin');
        $canEditMetadata = $permission->allows($admin, 'seo.update');
        $canRestoreRevisions = $permission->allows($admin, 'seo.revisions.restore');
        $canReviewMetadata = $permission->allows($admin, 'seo.review.resolve');
        $canViewTranslations = $permission->allows($admin, 'translations.index');
        $selectedLabel = $this->routeLabel($selectedName, $definition);
        return view('admin.seo.index', [
            'title' => 'Search & Sharing',
            'routes' => $routes,
            'routeDefinitions' => $this->routeRegistry->all(),
            'selectedName' => $selectedName,
            'selectedPath' => $selectedPath,
            'selectedLabel' => $selectedLabel,
            'seo' => $seo,
            'editor' => $editor,
            'editorFormAction' => $managedPage
                ? route('seo.content.update', ['type' => 'page', 'id' => $managedPage->getKey()])
                : route('seo.update'),
            'editorRouteName' => $managedPage ? null : $selectedName,
            'editorContentType' => $managedPage ? 'page' : null,
            'editorContentId' => $managedPage?->getKey(),
            'seoRevisions' => $this->revisions->recentFor($seo),
            'seoRevisionRestoreUrl' => fn (SeoMetadataRevision $revision): string => route('seo.revisions.restore', $revision),
            'locales' => $locales,
            'locale' => $locale,
            'mediaAssets' => collect(),
            'dashboardTargets' => $dashboard['targets'],
            'dashboardCounts' => $dashboard['counts'],
            'dashboardFilters' => $dashboard['filters'],
            'dashboardTypes' => $dashboard['types'],
            'languageSummary' => $this->languageSummary($locales),
            'canManageRedirects' => $permission->allows($admin, 'seo.redirects.index'),
            'canEditMetadata' => $canEditMetadata,
            'canRestoreRevisions' => $canRestoreRevisions,
            'canReviewMetadata' => $canReviewMetadata,
            'canViewTranslations' => $canViewTranslations,
            'editorCanEditMetadata' => $canEditMetadata && !$missingManagedPageTranslation,
            'editorCanRestoreRevisions' => $canRestoreRevisions && !$missingManagedPageTranslation,
            'editorCanReviewMetadata' => $canReviewMetadata && !$missingManagedPageTranslation,
            'editorCanOpenPage' => !$missingManagedPageTranslation,
            'missingManagedPageTranslation' => $missingManagedPageTranslation,
            'translationCenterUrl' => $missingManagedPageTranslation && $canViewTranslations
                ? route('translations.index', ['search' => $selectedLabel])
                : null,
            'canViewMedia' => $permission->allows($admin, 'media.index'),
            'canUploadMedia' => $permission->allows($admin, 'media.store'),
            'canUseExternalCanonical' => $permission->allows($admin, 'seo.canonical.external'),
            'seoRevisionDiffs' => $this->revisionDiffs($this->revisions->recentFor($seo), $seo),
            'seoRevisionCanonicalPolicies' => $this->revisionCanonicalPolicies($this->revisions->recentFor($seo), $defaultCanonical),
            'technicalSeoUrl' => Route::has('seo.technical.index') && $permission->allows($admin, 'seo.technical.index')
                ? route('seo.technical.index')
                : null,
            'technicalOrphanUrl' => Route::has('seo.technical.index') && $permission->allows($admin, 'seo.technical.index')
                ? route('seo.technical.index', ['issue_type' => 'orphan_page', 'visibility' => 'open'])
                : null,
        ]);
    }

    public function update(Request $request)
    {
        $routes = $this->routeRegistry->routes();
        $this->normalizeSeoInput($request);
        $data = $request->validate($this->metadataRules($routes->keys()->all(), $this->localeIds()));
        $data['seo'] = $this->safeVisibility($data['seo']);
        $defaultCanonical = (string) $this->seo->localizedUrl(
            url((string) $this->routeRegistry->path($data['route_name'])),
            $data['locale'],
            $this->defaultLocale()
        );
        $this->enforceCanonicalSafety($request, (string) ($data['seo']['canonical_url'] ?? ''), $defaultCanonical);
        $definition = $this->routeRegistry->definition($data['route_name']) ?: [];
        $managedPage = $this->managedPageForRoute($definition, $data['locale']);
        $this->seoMutationTransaction(function () use ($data, $definition, $managedPage): void {
            [$currentManagedPage, $lockedPages] = $this->lockAndVerifyManagedRouteOwner(
                $definition,
                $data['locale'],
                $managedPage
            );
            if ($currentManagedPage) {
                $uuid = trim((string) $currentManagedPage->uuid);
                $lockedPages ??= $this->pageEditorVersions->lockForMutation([$uuid]);
                $lockedPage = $this->pageEditorVersions->assertExpected(
                    $lockedPages,
                    $uuid,
                    $data['locale'],
                    $this->expectedPageEditorVersion($data['expected_editor_version'] ?? null)
                );
                $identity = $this->seoEditorVersions->modelIdentity($lockedPage, $data['locale']);
                $lockedMetadata = $this->seoEditorVersions->lockAndAssertMany([[
                    'identity' => $identity,
                    'context' => $this->seoEditorVersions->modelFingerprint($lockedPage),
                    'assert' => false,
                ]]);
                $existing = $lockedMetadata[$this->seoEditorVersions->key($identity)];
                if ($existing) {
                    $this->revisions->capture($existing, 'Before guided SEO update');
                }
                $saved = $this->seo->updateForModel($lockedPage, $data['seo'], $data['locale'], $existing);
                $this->pageEditorVersions->advanceLocked($lockedPages, [$uuid]);
            } else {
                $routePath = (string) $this->routeRegistry->path($data['route_name']);
                $identity = $this->seoEditorVersions->routeIdentity($data['route_name'], $data['locale']);
                $lockedMetadata = $this->seoEditorVersions->lockAndAssertMany([[
                    'identity' => $identity,
                    'context' => $this->seoEditorVersions->routeFingerprint($routePath),
                    'expected' => $data['expected_seo_version'] ?? null,
                ]]);
                $existing = $lockedMetadata[$this->seoEditorVersions->key($identity)];
                if ($existing) {
                    $this->revisions->capture($existing, 'Before guided SEO update');
                }
                $saved = $this->seo->updateForRoute(
                    $data['route_name'],
                    $routePath,
                    $data['locale'],
                    $data['seo'],
                    $existing
                );
            }
            $this->resetReviewAfterEdit($saved);
        });

        return redirect()->route('seo.index', ['route' => $data['route_name'], 'locale' => $data['locale']])
            ->with(['message' => 'Search and sharing settings saved.', 'alert-type' => 'success']);
    }

    public function redirectsIndex(Request $request)
    {
        $locales = $this->locales();
        $locale = $this->requestedLocale($request, $locales);
        $trash = $request->boolean('redirect_trash');
        $redirectQuery = $trash ? SeoRedirect::onlyTrashed() : SeoRedirect::query();
        $permission = app(Permission::class);
        $admin = $request->user('admin');

        return view('admin.seo.redirects', [
            'title' => 'Redirects',
            'redirects' => $redirectQuery->latest()->paginate(20)->withQueryString(),
            'redirectTrash' => $trash,
            'redirectDestinations' => $this->dashboardTargets($locale)->sortBy('label')->values(),
            'locale' => $locale,
            'locales' => $locales,
            'canManageMetadata' => $permission->allows($admin, 'seo.index'),
            'canCreateRedirects' => $permission->allows($admin, 'seo.redirects.store'),
            'canDestroyRedirects' => $permission->allows($admin, 'seo.redirects.destroy'),
        ]);
    }

    public function editContent(string $type, string $id)
    {
        [$model, $label] = $this->contentTarget($type, $id);
        $locales = $this->locales();
        $locale = $this->boundContentLocale($model, $type) ?: $this->requestedLocale(request(), $locales);
        $pageEditorVersion = null;
        if ($model instanceof Page) {
            [$model, $pageEditorVersion] = $this->pageEditorRenderSnapshot($model, $locale);
        }
        $defaultCanonical = $this->publicUrl($model, $type, $locale);
        $seoSnapshot = $this->metadataSnapshotForModel($model, $locale);
        $seo = $this->visibleSeoSnapshot($seoSnapshot);
        [$copySeo, $copyFallback] = request()->query('copy') === 'en' && $locale !== 'en'
            ? $this->englishSource($model, $type)
            : [null, null];
        $editor = $this->editorState(
            $seo,
            $this->fallbackForModel($model, $type),
            $defaultCanonical,
            $locale,
            $type,
            null,
            $model,
            $copySeo,
            $copyFallback,
            $pageEditorVersion,
            $seoSnapshot
        );

        $permission = app(Permission::class);
        $admin = request()->user('admin');

        return view('admin.seo.content', [
            'title' => 'Search & Sharing',
            'type' => $type,
            'model' => $model,
            'contentLabel' => $label,
            'contentTitle' => $this->modelTitle($model),
            'locale' => $locale,
            'locales' => $locales,
            'defaultCanonical' => $defaultCanonical,
            'seo' => $seo,
            'editor' => $editor,
            'editorFormAction' => route('seo.content.update', [$type, $model->getKey()]),
            'editorRouteName' => null,
            'editorContentType' => $type,
            'editorContentId' => $model->getKey(),
            'mediaAssets' => collect(),
            'seoRevisions' => $this->revisions->recentFor($seo),
            'seoRevisionRestoreUrl' => fn (SeoMetadataRevision $revision): string => route('seo.revisions.restore', $revision),
            'canEditMetadata' => $permission->allows($admin, 'seo.content.update'),
            'canRestoreRevisions' => $permission->allows($admin, 'seo.revisions.restore'),
            'canReviewMetadata' => $permission->allows($admin, 'seo.review.resolve'),
            'canViewMedia' => $permission->allows($admin, 'media.index'),
            'canUploadMedia' => $permission->allows($admin, 'media.store'),
            'canUseExternalCanonical' => $permission->allows($admin, 'seo.canonical.external'),
            'seoRevisionDiffs' => $this->revisionDiffs($this->revisions->recentFor($seo), $seo),
            'seoRevisionCanonicalPolicies' => $this->revisionCanonicalPolicies($this->revisions->recentFor($seo), $defaultCanonical),
        ]);
    }

    public function updateContent(Request $request, string $type, string $id)
    {
        [$model] = $this->contentTarget($type, $id);
        $this->normalizeSeoInput($request);
        if ($request->filled('permalink_slug')) {
            $request->merge(['permalink_slug' => Str::slug((string) $request->input('permalink_slug'))]);
        }
        $allowedLocales = $this->localeIds();
        $contentLocale = $this->boundContentLocale($model, $type);
        $data = $request->validate(array_merge([
            'locale' => ['required', 'string', Rule::in($contentLocale ? [$contentLocale] : $allowedLocales)],
            'permalink_slug' => array_merge(['sometimes'], $this->slugRules($model, $type)),
            'expected_editor_version' => ['nullable', 'integer', 'min:0'],
            'expected_seo_version' => ['nullable', 'string', 'max:100'],
            'schema_template' => $this->schemaTemplateRules(),
            'seo' => ['required', 'array'],
        ], $this->seoRules()));
        $data['seo'] = $this->safeVisibility($data['seo']);

        $message = 'Search and sharing settings saved.';
        $this->seoMutationTransaction(function () use ($request, $model, $type, $data, &$message): void {
            $lockedPages = null;
            $uuid = null;
            $currentModel = $model;
            if ($model instanceof Page) {
                $uuid = trim((string) $model->uuid);
                $lockedPages = $this->pageEditorVersions->lockForMutation([$uuid]);
                $currentModel = $this->pageEditorVersions->assertExpected(
                    $lockedPages,
                    $uuid,
                    $data['locale'],
                    $this->expectedPageEditorVersion($data['expected_editor_version'] ?? null)
                );
            } else {
                $currentModel = $this->lockNonPageContentOwner($type, (string) $model->getKey());
            }

            $identity = $this->seoEditorVersions->modelIdentity($currentModel, $data['locale']);
            $lockedMetadata = $this->seoEditorVersions->lockAndAssertMany([[
                'identity' => $identity,
                'context' => $this->seoEditorVersions->modelFingerprint($currentModel),
                'expected' => $data['expected_seo_version'] ?? null,
                'assert' => !($currentModel instanceof Page),
            ]]);
            $existing = $lockedMetadata[$this->seoEditorVersions->key($identity)];

            // Page URLs and slugs are intentionally derived only after the
            // owner and SEO generations have both been locked and checked.
            $canonicalDefault = $this->publicUrl($currentModel, $type, $data['locale']);
            $this->enforceCanonicalSafety(
                $request,
                (string) ($data['seo']['canonical_url'] ?? ''),
                $canonicalDefault
            );
            $oldUrl = $this->publicUrl($currentModel, $type);
            $oldSlug = (string) $currentModel->getAttribute('slug');
            $newSlug = (string) (($data['permalink_slug'] ?? null) ?: $oldSlug);
            if ($newSlug !== $oldSlug && !$this->permalinkEditable($currentModel, $type)) {
                throw ValidationException::withMessages([
                    'permalink_slug' => $this->permalinkRestrictionMessage($currentModel, $type),
                ]);
            }

            if ($existing) {
                $this->revisions->capture($existing, 'Before guided SEO update');
            }

            if ($newSlug !== $oldSlug) {
                $currentModel->forceFill(['slug' => $newSlug])->save();
                $currentModel->refresh();
                $newUrl = $this->publicUrl($currentModel, $type);
                if (($data['seo']['canonical_url'] ?? null) === $oldUrl) {
                    $data['seo']['canonical_url'] = $newUrl;
                }
                $redirectLocale = in_array($type, ['page', 'category', 'event', 'annual_report'], true)
                    ? $data['locale']
                    : null;
                $this->upsertAutomaticRedirect($oldUrl, $newUrl, $redirectLocale);
                $message = 'Search settings saved and the old address now redirects permanently.';
            }

            $saved = $this->seo->updateForModel($currentModel, $data['seo'], $data['locale'], $existing);
            $this->resetReviewAfterEdit($saved);
            if ($lockedPages !== null && $uuid !== null) {
                $this->pageEditorVersions->advanceLocked($lockedPages, [$uuid]);
            }
        });

        $redirectParameters = [
            'type' => $type,
            'id' => $model->getKey(),
        ];
        if ($contentLocale === null) {
            $redirectParameters['locale'] = $data['locale'];
        }

        return redirect()->route('seo.content.edit', $redirectParameters)
            ->with(['message' => $message, 'alert-type' => 'success']);
    }

    public function bulkIndex(Request $request)
    {
        [$targets, $filters] = $this->bulkTargets($request);
        $page = max(1, $request->integer('page', 1));
        $perPage = 25;
        $paginator = new LengthAwarePaginator(
            $targets->forPage($page, $perPage)->values(),
            $targets->count(),
            $perPage,
            $page,
            ['path' => route('seo.bulk.index'), 'query' => $request->query()]
        );
        $permission = app(Permission::class);
        $admin = $request->user('admin');

        return view('admin.seo.bulk', [
            'title' => 'Bulk search metadata editor',
            'targets' => $paginator,
            'filters' => $filters,
            'locales' => $this->locales(),
            'schemaOptions' => $this->schemaTemplates->options() + ['expert' => 'Keep expert JSON'],
            'canEditMetadata' => $permission->allows($admin, 'seo.bulk.update'),
            'canViewMedia' => $permission->allows($admin, 'media.index'),
            'canViewTranslations' => $permission->allows($admin, 'translations.index'),
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $templateKeys = array_merge(array_keys($this->schemaTemplates->options()), ['expert']);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:25'],
            'items.*.owner_type' => ['required', Rule::in(['route', 'page', 'category', 'event', 'annual_report', 'project'])],
            'items.*.owner_id' => ['nullable', 'integer'],
            'items.*.route_name' => ['nullable', 'string', 'max:150'],
            'items.*.locale' => ['required', 'string', Rule::in($this->localeIds())],
            'items.*.expected_editor_version' => ['nullable', 'integer', 'min:0'],
            'items.*.expected_seo_version' => ['nullable', 'string', 'max:100'],
            'items.*.mode' => ['required', Rule::in(['auto', 'custom'])],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.image' => ['nullable', 'url:http,https', 'max:2048'],
            'items.*.indexable' => ['required', 'boolean'],
            'items.*.schema_template' => ['required', Rule::in($templateKeys)],
        ]);

        $routeOwnerExpectations = $this->specialRouteOwnerExpectationsForBulkItems($data['items']);
        $pageRows = $this->pageRowsForBulkItems($data['items'], $routeOwnerExpectations);
        $routeOwnerUuids = $routeOwnerExpectations
            ->flatMap(fn (array $expectation): array => [
                $expectation['source_uuid'],
                $expectation['owner_uuid'],
            ])
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $saved = 0;
        $this->seoMutationTransaction(function () use ($data, $pageRows, $routeOwnerExpectations, $routeOwnerUuids, &$saved): void {
            // Acquire all logical Page locks in UUID order, assert every
            // per-row UI token, and only then resolve or touch SEO rows.
            $lockedPages = $this->pageEditorVersions->lockForMutation(
                $pageRows->pluck('uuid')->merge($routeOwnerUuids)
            );
            foreach ($pageRows as $pageRow) {
                $this->pageEditorVersions->assertExpected(
                    $lockedPages,
                    $pageRow['uuid'],
                    $pageRow['locale'],
                    $this->expectedPageEditorVersion($pageRow['expected_editor_version'])
                );
            }
            $lockedRouteOwners = $this->lockAndVerifySpecialRouteOwners(
                $routeOwnerExpectations,
                $lockedPages
            );

            $lockedOwners = $this->lockNonPageBulkOwners($data['items']);
            $resolvedItems = collect($data['items'])->map(fn (array $item): array => [
                'item' => $item,
                'owner' => $this->resolveBulkOwner($item, $lockedOwners, $lockedRouteOwners),
            ]);
            $claims = $resolvedItems->map(function (array $resolved): array {
                $item = $resolved['item'];
                [$model, $routeName, $routePath] = $resolved['owner'];
                if ($item['owner_type'] === 'route') {
                    $expectedPageOwner = array_key_exists('expected_editor_version', $item)
                        && $item['expected_editor_version'] !== null;
                    abort_if(
                        ($model instanceof Page) !== $expectedPageOwner,
                        409,
                        SeoMetadataEditorVersionService::CONFLICT_MESSAGE
                    );
                }
                if ($model) {
                    return [
                        'identity' => $this->seoEditorVersions->modelIdentity($model, $item['locale']),
                        'context' => $this->seoEditorVersions->modelFingerprint($model),
                        'expected' => $item['expected_seo_version'] ?? null,
                        'assert' => !($model instanceof Page),
                    ];
                }

                return [
                    'identity' => $this->seoEditorVersions->routeIdentity($routeName, $item['locale']),
                    'context' => $this->seoEditorVersions->routeFingerprint($routePath),
                    'expected' => $item['expected_seo_version'] ?? null,
                ];
            });
            $lockedMetadata = $this->seoEditorVersions->lockAndAssertMany($claims);

            foreach ($resolvedItems as $resolved) {
                $item = $resolved['item'];
                [$model, $routeName, $routePath, , $fallback, $url] = $resolved['owner'];
                $identity = $model
                    ? $this->seoEditorVersions->modelIdentity($model, $item['locale'])
                    : $this->seoEditorVersions->routeIdentity($routeName, $item['locale']);
                $metadata = $lockedMetadata[$this->seoEditorVersions->key($identity)];
                if ($metadata) {
                    $this->revisions->capture($metadata, 'Before bulk SEO update');
                }

                $payload = $this->bulkPayload($metadata?->trashed() ? null : $metadata, $item, $fallback, $url);
                $updated = $model
                    ? $this->seo->updateForModel($model, $payload, $item['locale'], $metadata)
                    : $this->seo->updateForRoute($routeName, $routePath, $item['locale'], $payload, $metadata);
                $this->resetReviewAfterEdit($updated);
                $saved++;
            }

            $this->pageEditorVersions->advanceLocked($lockedPages, $pageRows->pluck('uuid'));
        });

        return back()->with([
            'message' => "{$saved} search metadata row" . ($saved === 1 ? '' : 's') . ' saved.',
            'alert-type' => 'success',
        ]);
    }

    public function bulkExport(Request $request)
    {
        [$targets] = $this->bulkTargets($request);
        $filename = 'seo-metadata-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($targets): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Content', 'Type', 'Language', 'Publication', 'SEO status', 'Search title', 'Search description', 'Social image', 'Visibility', 'Schema template', 'Public URL']);
            foreach ($targets as $target) {
                fputcsv($stream, array_map(fn ($value) => $this->safeCsvCell((string) $value), [
                    $target['label'],
                    $target['type_label'],
                    strtoupper($target['locale']),
                    data_get($target, 'publication.label', 'Live'),
                    $target['status'],
                    $target['effective_title'],
                    $target['effective_description'],
                    $target['effective_image'],
                    $target['stored']['indexable'] ? 'Show in search' : 'Hidden',
                    $target['stored']['schema_template'],
                    $target['url'],
                ]));
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function mediaIndex(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        $search = trim((string) ($data['search'] ?? ''));
        $assets = MediaAsset::query()
            ->where('mime_type', 'like', 'image/%')
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%' . addcslashes($search, '%_') . '%';
                $query->where(fn ($nested) => $nested
                    ->where('original_name', 'like', $term)
                    ->orWhere('alt_text', 'like', $term)
                    ->orWhere('caption', 'like', $term));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(24);
        $canViewMedia = app(Permission::class)->allows($request->user('admin'), 'media.index');

        return response()->json([
            'data' => $assets->getCollection()->map(fn (MediaAsset $asset) => $this->mediaPickerAsset($asset, $canViewMedia))->values(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'total' => $assets->total(),
            ],
        ]);
    }

    public function requestReview(Request $request)
    {
        $data = $request->validate($this->reviewIdentityRules() + [
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($data, $request): void {
            [$metadata, $fallback, $url] = $this->resolveReviewMetadata($data, true);
            $health = $this->healthForMetadata($metadata, $fallback, $url);
            if ($health['required_count'] > 0) {
                throw ValidationException::withMessages([
                    'review' => 'Complete every required SEO checklist item before requesting review.',
                ]);
            }

            $metadata->forceFill([
                'review_status' => 'pending',
                'review_note' => trim((string) ($data['note'] ?? '')) ?: null,
                'review_content_hash' => $this->reviewContentHash($metadata, $fallback, $url),
                'review_request_version' => (int) $metadata->review_request_version + 1,
                'review_requested_by' => $request->user('admin')->id,
                'review_requested_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
            ])->save();
        }, 3);

        return back()->with(['message' => 'SEO review requested.', 'alert-type' => 'success']);
    }

    public function resolveReview(Request $request)
    {
        $data = $request->validate($this->reviewIdentityRules() + [
            'decision' => ['required', Rule::in(['approve', 'changes'])],
            'note' => ['nullable', 'required_if:decision,changes', 'string', 'max:2000'],
            'expected_review_hash' => ['required', 'string', 'size:64'],
            'expected_review_version' => ['required', 'integer', 'min:1'],
        ]);
        $stale = DB::transaction(function () use ($data, $request): bool {
            [$metadata, $fallback, $url] = $this->resolveReviewMetadata($data, true);
            if ($metadata->review_status !== 'pending') {
                throw ValidationException::withMessages(['review' => 'Only a pending SEO review can be resolved.']);
            }

            $requestedHash = (string) $metadata->review_content_hash;
            abort_if(
                !hash_equals($requestedHash, (string) $data['expected_review_hash'])
                    || (int) $metadata->review_request_version !== (int) $data['expected_review_version'],
                409,
                'This SEO review request changed after you opened it. Reload and review the current request before deciding.'
            );
            $currentHash = $this->reviewContentHash($metadata, $fallback, $url);
            if ($requestedHash === '' || !hash_equals($requestedHash, $currentHash)) {
                $metadata->forceFill([
                    'review_status' => 'draft',
                    'review_note' => 'Content changed after review was requested. Submit the current version again.',
                    'review_content_hash' => null,
                    'review_requested_by' => null,
                    'review_requested_at' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ])->save();

                return true;
            }

            $metadata->forceFill([
                'review_status' => $data['decision'] === 'approve' ? 'approved' : 'changes_requested',
                'review_note' => trim((string) ($data['note'] ?? '')) ?: null,
                // Keep the requested hash with the decision so the sign-off is
                // auditable against the exact effective metadata version.
                'review_content_hash' => $requestedHash,
                'reviewed_by' => $request->user('admin')->id,
                'reviewed_at' => now(),
            ])->save();

            return false;
        }, 3);
        if ($stale) {
            throw ValidationException::withMessages([
                'review' => 'This SEO version changed after review was requested. Review was reset; submit the current version again.',
            ]);
        }

        return back()->with([
            'message' => $data['decision'] === 'approve' ? 'SEO sign-off recorded.' : 'SEO changes requested.',
            'alert-type' => 'success',
        ]);
    }

    public function restoreRevision(Request $request, SeoMetadataRevision $revision)
    {
        $this->assertRestorableRevisionTarget($revision);
        $restoreInput = $request->validate([
            'expected_editor_version' => ['nullable', 'integer', 'min:0'],
            'expected_seo_version' => ['nullable', 'string', 'max:100'],
        ]);
        $metadata = DB::transaction(function () use ($request, $revision, $restoreInput): SeoMetadata {
            $lockedPages = null;
            $pageUuid = null;
            $lockedOwner = null;
            if ((string) $revision->seoable_type === Page::class) {
                $page = Page::withTrashed()->findOrFail($revision->seoable_id);
                $pageUuid = trim((string) $page->uuid);
                $lockedPages = $this->pageEditorVersions->lockForMutation([$pageUuid]);
                $this->pageEditorVersions->assertExpected(
                    $lockedPages,
                    $pageUuid,
                    (string) $revision->locale,
                    $this->expectedPageEditorVersion($restoreInput['expected_editor_version'] ?? null)
                );
            } elseif (filled($revision->seoable_type)) {
                $modelClass = (string) $revision->seoable_type;
                $lockedOwner = $modelClass::withTrashed()
                    ->whereKey($revision->seoable_id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $metadata = $this->revisions->restore(
                $revision,
                function (SeoMetadataRevision $lockedRevision, SeoMetadata $lockedMetadata) use ($request, $restoreInput, $lockedOwner): void {
                    if ((string) $lockedRevision->seoable_type !== Page::class) {
                        if (filled($lockedRevision->route_name)) {
                            $routeName = (string) $lockedRevision->route_name;
                            $identity = $this->seoEditorVersions->routeIdentity($routeName, (string) $lockedRevision->locale);
                            $context = $this->seoEditorVersions->routeFingerprint(
                                (string) $this->routeRegistry->path($routeName)
                            );
                        } else {
                            abort_unless($lockedOwner instanceof Model, 409, 'The content for this restore point no longer exists.');
                            $identity = $this->seoEditorVersions->modelIdentity($lockedOwner, (string) $lockedRevision->locale);
                            $context = $this->seoEditorVersions->modelFingerprint($lockedOwner);
                        }
                        $this->seoEditorVersions->assertExpectedLocked(
                            $lockedMetadata,
                            $identity,
                            $context,
                            $restoreInput['expected_seo_version'] ?? null
                        );
                    }
                    $this->enforceCanonicalSafety(
                        $request,
                        (string) data_get($lockedRevision->snapshot, 'canonical_url', ''),
                        $this->defaultCanonicalForMetadata($lockedMetadata)
                    );
                }
            );
            if ($lockedPages !== null && $pageUuid !== null) {
                $this->pageEditorVersions->advanceLocked($lockedPages, [$pageUuid]);
            }

            return $metadata;
        });
        $message = 'Earlier search and sharing settings restored. You can undo this from Recent changes.';

        if ($metadata->route_name && $this->routeRegistry->has($metadata->route_name)) {
            return redirect(route('seo.index', [
                'route' => $metadata->route_name,
                'locale' => $metadata->locale,
            ]) . '#seo-editor')->with(['message' => $message, 'alert-type' => 'success']);
        }

        $modelClass = (string) $metadata->seoable_type;
        $type = $this->revisionContentType($modelClass);
        $model = $type ? $modelClass::withTrashed()->find($metadata->seoable_id) : null;
        if ($type && $model && !$model->trashed()) {
            return redirect()->route('seo.content.edit', [
                'type' => $type,
                'id' => $metadata->seoable_id,
                'locale' => $metadata->locale,
            ])->with(['message' => $message, 'alert-type' => 'success']);
        }

        return redirect()->route('seo.index', ['locale' => $metadata->locale])->with([
            'message' => 'The SEO restore point was recovered. Its content item is currently in trash.',
            'alert-type' => 'success',
        ]);
    }

    public function storeRedirect(Request $request)
    {
        $action = (string) $request->input('action', 'save');
        if (in_array($action, ['toggle', 'restore'], true)) {
            $data = $request->validate([
                'redirect_id' => ['required', 'integer'],
                'is_active' => ['nullable', 'boolean'],
            ]);
            if ($action === 'restore') {
                $this->redirects->restore((int) $data['redirect_id']);
                $message = 'Redirect restored in a disabled state. Review it before enabling.';
            } else {
                $redirect = SeoRedirect::findOrFail($data['redirect_id']);
                $this->redirects->setActive($redirect, (bool) ($data['is_active'] ?? false));
                $message = (bool) ($data['is_active'] ?? false) ? 'Redirect enabled.' : 'Redirect paused.';
            }

            return back()->with(['message' => $message, 'alert-type' => 'success']);
        }

        $data = $request->validate([
            'redirect_id' => ['nullable', 'integer'],
            'from_path' => ['required', 'string', 'max:2048', 'regex:#^/(?!/)#'],
            'to_url' => ['required', 'string', 'max:2048', 'regex:#^(?:/(?!/)|https://)#i'],
            'status_code' => ['required', Rule::in(SeoRedirectService::SAFE_STATUS_CODES)],
            'is_active' => ['required', 'boolean'],
            'locale' => ['nullable', 'string', Rule::in($this->localeIds())],
        ]);

        if (!empty($data['redirect_id'])) {
            $redirect = SeoRedirect::findOrFail($data['redirect_id']);
            $this->redirects->update($redirect, $data);
            $message = 'Redirect updated.';
        } else {
            $this->redirects->create($data);
            $message = 'Redirect created.';
        }

        return back()->with(['message' => $message, 'alert-type' => 'success']);
    }

    public function destroyRedirect(SeoRedirect $redirect)
    {
        $this->redirects->delete($redirect);

        return back()->with(['message' => 'Redirect moved to trash and stopped.', 'alert-type' => 'success']);
    }

    /** @return array<string, mixed> */
    private function editorState(
        ?SeoMetadata $seo,
        array $fallback,
        string $defaultCanonical,
        string $locale,
        string $kind,
        ?string $routeName = null,
        ?Model $model = null,
        ?SeoMetadata $copySeo = null,
        ?array $copyFallback = null,
        ?int $pageEditorVersion = null,
        ?SeoMetadata $seoSnapshot = null
    ): array {
        $defaultCanonical = (string) $this->seo->localizedUrl($defaultCanonical, $locale, $this->defaultLocale());
        $copying = $copySeo !== null || $copyFallback !== null;
        $source = $copying ? $copySeo : $seo;
        $sourceFallback = $copying ? ($copyFallback ?: $fallback) : $fallback;
        $raw = fn (string $field, mixed $default = '') => $source?->getAttribute($field) ?? $default;
        $title = $copying ? ((string) $raw('title') ?: (string) ($sourceFallback['meta_title'] ?? '')) : (string) $raw('title');
        $description = $copying ? ((string) $raw('description') ?: (string) ($sourceFallback['meta_description'] ?? '')) : (string) $raw('description');
        $image = $this->absoluteImageUrl((string) ($raw('og_image') ?: $raw('twitter_image') ?: ($copying ? ($sourceFallback['meta_image'] ?? '') : '')));
        $schema = $source?->schema_markup;
        $effectiveTitle = $title ?: (string) ($fallback['meta_title'] ?? '');
        $effectiveDescription = $description ?: (string) ($fallback['meta_description'] ?? '');
        $effectiveImage = $image ?: $this->absoluteImageUrl((string) ($fallback['meta_image'] ?? ''));
        $suggestedTemplate = $this->schemaTemplates->suggestedFor($kind, $routeName);
        $schemaContext = [
            'name' => $effectiveTitle,
            'url' => $defaultCanonical,
            'description' => $effectiveDescription,
            'image' => $effectiveImage,
            'locale' => $locale,
        ];
        $generatedSchemas = collect($this->schemaTemplates->options())
            ->mapWithKeys(fn (string $label, string $template) => [
                $template => json_encode($this->schemaTemplates->generate($template, $schemaContext), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ])->all();

        $effectiveUrl = (string) $this->seo->localizedUrl(
            $copying ? $defaultCanonical : ((string) $raw('canonical_url') ?: $defaultCanonical),
            $locale,
            $this->defaultLocale()
        );
        $health = $this->health->evaluate([
            'title' => $effectiveTitle,
            'description' => $effectiveDescription,
            'focus_keyword' => (string) $raw('focus_keyword'),
            'image' => $effectiveImage,
            'canonical' => (string) $raw('canonical_url'),
            'default_url' => $defaultCanonical,
            'indexable' => (bool) ($source?->robots_index ?? true),
            'excluded' => (bool) ($source?->exclude_from_sitemap ?? false),
        ]);
        $publication = $this->publicationState($model, $kind);
        $seoEditorVersion = null;
        if ($model && !($model instanceof Page)) {
            $seoEditorVersion = $this->seoEditorVersions->forModelSnapshot($model, $locale, $seoSnapshot);
        } elseif (!$model && $routeName) {
            $seoEditorVersion = $this->seoEditorVersions->forRouteSnapshot(
                $routeName,
                (string) $this->routeRegistry->path($routeName),
                $locale,
                $seoSnapshot
            );
        }
        $reviewState = $this->seoReviews->effectiveState($seo, $fallback, $defaultCanonical);

        return [
            'locale' => $locale,
            'kind' => $kind,
            'page_editor_version' => $pageEditorVersion,
            'seo_editor_version' => $seoEditorVersion,
            'default_url' => $defaultCanonical,
            'fallback' => [
                'title' => (string) ($fallback['meta_title'] ?? ''),
                'description' => (string) ($fallback['meta_description'] ?? ''),
                'image' => $this->absoluteImageUrl((string) ($fallback['meta_image'] ?? '')),
            ],
            'values' => [
                'title' => $title,
                'description' => $description,
                'focus_keyword' => (string) $raw('focus_keyword'),
                'canonical_url' => $copying ? '' : (string) $raw('canonical_url'),
                'robots_index' => $copying ? (bool) ($seo?->robots_index ?? true) : (bool) ($source?->robots_index ?? true),
                'robots_follow' => $copying ? (bool) ($seo?->robots_follow ?? true) : (bool) ($source?->robots_follow ?? true),
                'exclude_from_sitemap' => $copying ? (bool) ($seo?->exclude_from_sitemap ?? false) : (bool) ($source?->exclude_from_sitemap ?? false),
                'og_title' => (string) $raw('og_title'),
                'og_description' => (string) $raw('og_description'),
                'og_image' => $image,
                'twitter_card' => (string) $raw('twitter_card', 'summary_large_image'),
                'twitter_title' => (string) $raw('twitter_title'),
                'twitter_description' => (string) $raw('twitter_description'),
                'twitter_image' => $this->absoluteImageUrl((string) ($raw('twitter_image') ?: $image)),
                'schema_markup' => $schema ? json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
                'sitemap_priority' => (float) ($seo?->sitemap_priority ?? 0.5),
                'sitemap_change_frequency' => (string) ($seo?->sitemap_change_frequency ?? 'monthly'),
            ],
            'effective' => [
                'title' => $effectiveTitle,
                'description' => $effectiveDescription,
                'image' => $effectiveImage,
                'url' => $effectiveUrl,
            ],
            'auto_content' => !$copying && trim((string) ($seo?->title ?? '')) === '' && trim((string) ($seo?->description ?? '')) === '',
            'copying_english' => $copying,
            'copy_url' => $locale !== 'en' ? $this->copyEnglishUrl($kind, $routeName, $model, $locale) : null,
            'schema_options' => $this->schemaTemplates->options(),
            'schema_selected' => $this->schemaTemplates->detect(is_array($schema) ? $schema : null),
            'schema_suggested' => $suggestedTemplate,
            'generated_schemas' => $generatedSchemas,
            'health' => $health,
            'publication' => $publication,
            'review' => [
                'status' => $reviewState['status'],
                'note' => $reviewState['note'],
                'stale' => $reviewState['stale'],
                'content_hash' => (string) ($seo?->review_content_hash ?: ''),
                'request_version' => (int) ($seo?->review_request_version ?? 0),
                'requested_at' => $seo?->review_requested_at,
                'reviewed_at' => $seo?->reviewed_at,
                'exists' => (bool) $seo?->exists,
                'owner_type' => $model ? $kind : 'route',
                'owner_id' => $model?->getKey(),
                'route_name' => $model ? null : $routeName,
            ],
            'permalink' => $model ? [
                'slug' => (string) $model->getAttribute('slug'),
                'editable' => $this->permalinkEditable($model, $kind),
                'prefix' => $this->permalinkPrefix($kind),
                'restriction' => $this->permalinkEditable($model, $kind) ? null : $this->permalinkRestrictionMessage($model, $kind),
            ] : null,
        ];
    }

    private function dashboard(Request $request, string $locale): array
    {
        $allTargets = $this->dashboardTargets($locale);
        $filters = [
            'search' => trim((string) $request->query('search')),
            'type' => (string) $request->query('type', 'all'),
            'issue' => (string) $request->query('issue', 'all'),
        ];
        $targets = $allTargets->filter(function (array $target) use ($filters): bool {
            $matchesSearch = $filters['search'] === '' || str_contains(
                mb_strtolower($target['label'] . ' ' . $target['url']),
                mb_strtolower($filters['search'])
            );
            $matchesType = $filters['type'] === 'all' || $target['type'] === $filters['type'];
            $matchesIssue = $filters['issue'] === 'all'
                || ($filters['issue'] === 'needs_attention' && $target['status'] === 'Needs attention')
                || in_array($filters['issue'], $target['issue_keys'], true);

            return $matchesSearch && $matchesType && $matchesIssue;
        })->values();
        $liveTargets = $allTargets->where('is_live', true);
        $visible = $liveTargets->where('status', '!=', 'Hidden');

        return [
            'allTargets' => $allTargets,
            'targets' => $targets,
            'counts' => [
                'total' => $allTargets->count(),
                'live' => $liveTargets->count(),
                'draft' => $allTargets->where('is_live', false)->count(),
                'ready' => $liveTargets->where('status', 'Ready')->count(),
                'attention' => $liveTargets->where('status', 'Needs attention')->count(),
                'hidden' => $liveTargets->where('status', 'Hidden')->count(),
                'average' => (int) round($visible->avg('score') ?: 0),
            ],
            'filters' => $filters,
            'types' => [
                'all' => 'All content',
                'page' => 'Pages',
                'category' => 'Categories',
                'event' => 'Events & publications',
                'annual_report' => 'Annual reports',
                'project' => 'Projects',
                'route' => 'Website features',
            ],
        ];
    }

    private function dashboardTargets(string $locale, bool $includeMissingTranslations = true): Collection
    {
        // Read each logical Page (the displayed locale row and its generation)
        // in one snapshot, before reading owned metadata. If a Page SEO write
        // commits between these reads it advances the Page generation and the
        // rendered form remains safely stale.
        $pageSnapshots = Page::withTrashed()->orderBy('uuid')->orderBy('id')->get();
        $pages = $pageSnapshots
            ->filter(fn (Page $page): bool => !$page->trashed() && (string) $page->language === $locale)
            ->sortBy('name')
            ->values();
        $pageVersions = $pageSnapshots
            ->groupBy('uuid')
            ->map(fn (Collection $translations): int => (int) $translations->max('editor_version'));
        $activePageSlugs = $pageSnapshots
            ->reject(fn (Page $page): bool => $page->trashed())
            ->pluck('slug')
            ->filter()
            ->flip();

        // Include tombstones in the version snapshot while keeping deleted
        // metadata out of the visible form values.
        $metadata = SeoMetadata::withTrashed()->where('locale', $locale)->get();
        $modelMetadata = $metadata->whereNotNull('seoable_type')->keyBy(fn (SeoMetadata $seo) => $seo->seoable_type . ':' . $seo->seoable_id);
        $routeMetadata = $metadata->whereNotNull('route_name')->keyBy('route_name');
        $targets = collect();

        foreach ($pages as $page) {
            $targets->push($this->dashboardTarget(
                $page,
                'page',
                $locale,
                $modelMetadata->get(Page::class . ':' . $page->getKey()),
                (int) $pageVersions->get($page->uuid, $page->editor_version)
            ));
        }
        foreach (Category::query()->where('language', $locale)->orderBy('name')->get() as $category) {
            $targets->push($this->dashboardTarget($category, 'category', $locale, $modelMetadata->get(Category::class . ':' . $category->getKey())));
        }
        foreach (NoticeBoard::query()->where('language', $locale)->orderByDesc('published_at')->get() as $event) {
            $targets->push($this->dashboardTarget($event, 'event', $locale, $modelMetadata->get(NoticeBoard::class . ':' . $event->getKey())));
        }
        foreach (AnnualReport::query()->where('language', $locale)->orderByDesc('published_at')->get() as $report) {
            $targets->push($this->dashboardTarget($report, 'annual_report', $locale, $modelMetadata->get(AnnualReport::class . ':' . $report->getKey())));
        }
        foreach (Tag::query()->orderBy('name')->get() as $project) {
            $targets->push($this->dashboardTarget($project, 'project', $locale, $modelMetadata->get(Tag::class . ':' . $project->getKey())));
        }

        foreach ($this->routeRegistry->all() as $name => $definition) {
            if (!empty($definition['page_slug']) && $activePageSlugs->has((string) $definition['page_slug'])) {
                continue;
            }
            $fallback = $this->fallbackForRoute($name, $definition);
            $seoSnapshot = $routeMetadata->get($name);
            $seo = $this->visibleSeoSnapshot($seoSnapshot);
            $targets->push($this->targetArray(
                'route:' . $name,
                'route',
                'Website feature',
                $this->routeLabel($name, $definition),
                (string) $this->seo->localizedUrl(url($definition['path']), $locale, $this->defaultLocale()),
                route('seo.index', ['route' => $name, 'locale' => $locale]) . '#seo-editor',
                $locale,
                $seo,
                $fallback,
                [
                    'owner_type' => 'route',
                    'owner_id' => null,
                    'route_name' => $name,
                    'expected_seo_version' => $this->seoEditorVersions->forRouteSnapshot(
                        $name,
                        (string) $definition['path'],
                        $locale,
                        $seoSnapshot
                    ),
                    'publication' => $this->publicationState(null, 'route'),
                ]
            ));
        }

        $duplicateTitles = $targets->groupBy(fn (array $target) => mb_strtolower(trim($target['effective_title'])))
            ->filter(fn (Collection $items, string $title) => $title !== '' && $items->count() > 1)
            ->keys();
        $duplicateDescriptions = $targets->groupBy(fn (array $target) => mb_strtolower(trim($target['effective_description'])))
            ->filter(fn (Collection $items, string $description) => $description !== '' && $items->count() > 1)
            ->keys();
        $targets = $targets->map(function (array $target) use ($duplicateTitles, $duplicateDescriptions): array {
            if ($duplicateTitles->contains(mb_strtolower(trim($target['effective_title'])))) {
                $target['issues'][] = $this->health->issue('duplicate_title', 'Search title is also used elsewhere', 'warning');
                $target['issue_keys'][] = 'duplicate_title';
                $target['score'] = max(0, $target['score'] - 10);
                if ($target['status'] === 'Ready') {
                    $target['status'] = 'Needs attention';
                }
            }
            if ($duplicateDescriptions->contains(mb_strtolower(trim($target['effective_description'])))) {
                $target['issues'][] = $this->health->issue('duplicate_description', 'Search description is also used elsewhere', 'warning');
                $target['issue_keys'][] = 'duplicate_description';
                $target['score'] = max(0, $target['score'] - 10);
                if ($target['status'] === 'Ready') {
                    $target['status'] = 'Needs attention';
                }
            }
            return $target;
        });

        if ($includeMissingTranslations && $locale !== $this->defaultLocale()) {
            $existingKeys = $targets->pluck('key')->flip();
            $this->dashboardTargets($this->defaultLocale(), false)
                ->reject(fn (array $target) => $existingKeys->has($target['key']))
                ->each(function (array $target) use ($locale, $targets): void {
                    $target['locale'] = $locale;
                    $target['score'] = 0;
                    $target['status'] = 'Needs attention';
                    $target['is_live'] = false;
                    $target['publication'] = ['state' => 'missing_translation', 'label' => 'Translation missing', 'is_live' => false];
                    $target['issues'] = [$this->health->issue('missing_translation', 'Create the translation before editing SEO', 'danger')];
                    $target['issue_keys'] = ['missing_translation'];
                    $target['type_label'] .= ' · translation missing';
                    $target['edit_url'] = route('translations.index', ['search' => $target['label']]);
                    $target['is_editable'] = false;
                    $targets->push($target);
                });
        }

        return $targets->sortBy([['status', 'desc'], ['score', 'asc'], ['label', 'asc']])->values();
    }

    private function dashboardTarget(
        Model $model,
        string $type,
        string $locale,
        ?SeoMetadata $seoSnapshot,
        ?int $pageEditorVersion = null
    ): array
    {
        $seo = $this->visibleSeoSnapshot($seoSnapshot);

        return $this->targetArray(
            $this->contentIdentity($model, $type),
            $type,
            match ($type) {
                'page' => 'Page',
                'category' => 'Category',
                'event' => 'Event / publication',
                'annual_report' => 'Annual report',
                default => 'Project · shared across languages',
            },
            $this->modelTitle($model),
            $this->publicUrl($model, $type, $locale),
            route('seo.content.edit', ['type' => $type, 'id' => $model->getKey(), 'locale' => $locale]),
            $locale,
            $seo,
            $this->fallbackForModel($model, $type),
            [
                'owner_type' => $type,
                'owner_id' => $model->getKey(),
                'route_name' => null,
                'expected_editor_version' => $type === 'page' ? $pageEditorVersion : null,
                'expected_seo_version' => $type === 'page'
                    ? null
                    : $this->seoEditorVersions->forModelSnapshot($model, $locale, $seoSnapshot),
                'publication' => $this->publicationState($model, $type),
            ]
        );
    }

    private function targetArray(string $key, string $type, string $typeLabel, string $label, string $url, string $editUrl, string $locale, ?SeoMetadata $seo, array $fallback, array $owner = []): array
    {
        $meta = $seo ? $seo->toMetaArray($fallback) : array_merge($fallback, [
            'canonical_url' => null,
            'robots' => 'index,follow',
            'og_image' => $fallback['meta_image'] ?? '',
        ]);
        $indexable = !$seo || $seo->robots_index;
        $health = $this->health->evaluate([
            'title' => $meta['meta_title'] ?? '',
            'description' => $meta['meta_description'] ?? '',
            'focus_keyword' => (string) ($seo?->focus_keyword ?? ''),
            'image' => $meta['og_image'] ?? '',
            'canonical' => $meta['canonical_url'] ?? '',
            'default_url' => $url,
            'indexable' => $indexable,
            'excluded' => (bool) ($seo?->exclude_from_sitemap ?? false),
        ]);
        $reviewState = $this->seoReviews->effectiveState($seo, $fallback, $url);

        return [
            'key' => $key,
            'type' => $type,
            'type_label' => $typeLabel,
            'label' => $label,
            'url' => $url,
            'path' => parse_url($url, PHP_URL_PATH) ?: '/',
            'edit_url' => $editUrl,
            'locale' => $locale,
            'score' => $health['score'],
            'status' => $health['status'],
            'issues' => $health['issues'],
            'issue_keys' => collect($health['issues'])->pluck('key')->all(),
            'effective_title' => (string) ($meta['meta_title'] ?? ''),
            'effective_description' => (string) ($meta['meta_description'] ?? ''),
            'effective_image' => (string) ($meta['og_image'] ?? ''),
            'owner_type' => $owner['owner_type'] ?? $type,
            'owner_id' => $owner['owner_id'] ?? null,
            'route_name' => $owner['route_name'] ?? null,
            'expected_editor_version' => $owner['expected_editor_version'] ?? null,
            'expected_seo_version' => $owner['expected_seo_version'] ?? null,
            'is_editable' => (bool) ($owner['is_editable'] ?? true),
            'publication' => $owner['publication'] ?? $this->publicationState(null, 'route'),
            'is_live' => (bool) data_get($owner, 'publication.is_live', true),
            'stored' => [
                'mode' => $seo && (filled($seo->title) || filled($seo->description)) ? 'custom' : 'auto',
                'title' => (string) ($seo?->title ?? ''),
                'description' => (string) ($seo?->description ?? ''),
                'image' => (string) ($seo?->og_image ?? ''),
                'indexable' => (bool) ($seo?->robots_index ?? true),
                'schema_template' => $this->schemaTemplates->detect(is_array($seo?->schema_markup) ? $seo->schema_markup : null),
                'review_status' => $reviewState['status'],
                'review_stale' => $reviewState['stale'],
            ],
        ];
    }

    private function fallbackForModel(Model $model, string $type): array
    {
        $title = $this->modelTitle($model);
        $description = match ($type) {
            'page' => $model->getAttribute('meta_description') ?: $model->getAttribute('sub_title') ?: $model->getAttribute('description'),
            'category' => $model->getAttribute('meta_description') ?: $model->getAttribute('description'),
            'event' => $model->getAttribute('description') ?: $model->getAttribute('sub_title'),
            'annual_report' => $model->getAttribute('description') ?: $model->getAttribute('sub_title'),
            default => '',
        };
        $image = match ($type) {
            'page' => $model->getAttribute('thumbnail'),
            'category' => $model->getAttribute('path') ?: $model->getAttribute('image'),
            'event' => $model->getAttribute('image_path'),
            'annual_report' => $this->annualReportImage($model),
            default => '',
        };

        return [
            'meta_title' => (string) ($model->getAttribute('meta_title') ?: $title),
            'meta_description' => trim(strip_tags((string) $description)),
            'meta_keyword' => (string) $model->getAttribute('meta_keyword'),
            'meta_image' => (string) $image,
        ];
    }

    private function fallbackForRoute(string $routeName, array $definition): array
    {
        return [
            'meta_title' => $this->routeLabel($routeName, $definition) . ' | ' . config('app.name'),
            'meta_description' => '',
            'meta_keyword' => '',
            'meta_image' => '',
        ];
    }

    private function annualReportImage(Model $model): string
    {
        $path = trim((string) $model->getRawOriginal('image_path'));
        if ($path === '' || str_ends_with(strtolower($path), '.pdf')) {
            return '';
        }
        if (Str::startsWith($path, ['/', 'http://', 'https://'])) {
            return $path;
        }

        return '/storage/photos/1/notice_board/' . ltrim($path, '/');
    }

    private function publicUrl(Model $model, string $type, ?string $locale = null): string
    {
        $url = match ($type) {
            'page' => $this->pagePublicUrl($model),
            'category' => route('frontend.category', ['slug' => $model->getAttribute('slug')]),
            'event' => route('frontend.event', ['slug' => $model->getAttribute('slug')]),
            'annual_report' => route('frontend.annual_report.show', ['slug' => $model->getAttribute('slug')]),
            'project' => route('frontend.project', ['slug' => $model->getAttribute('slug')]),
            default => url('/'),
        };

        $locale ??= (string) ($model->getAttribute('language') ?: $this->defaultLocale());

        return (string) $this->seo->localizedUrl($url, $locale, $this->defaultLocale());
    }

    private function pagePublicUrl(Model $page): string
    {
        $slug = (string) $page->getAttribute('slug');
        $definition = $this->routeRegistry->all()->first(fn (array $definition) => ($definition['page_slug'] ?? null) === $slug);
        if (!$definition && filled($page->getAttribute('uuid'))) {
            $source = Page::query()
                ->where('uuid', $page->getAttribute('uuid'))
                ->where('language', $this->defaultLocale())
                ->first();
            if ($source) {
                $definition = $this->routeRegistry->all()->first(
                    fn (array $candidate) => ($candidate['page_slug'] ?? null) === $source->slug
                );
            }
        }

        return $definition ? url($definition['path']) : route('frontend.page', ['slug' => $slug]);
    }

    private function routeLabel(string $name, array $definition): string
    {
        if (!empty($definition['label'])) {
            return (string) $definition['label'];
        }
        if (($definition['path'] ?? '/') === '/') {
            return 'Home';
        }

        return Str::headline(trim((string) ($definition['path'] ?? ''), '/'));
    }

    private function defaultRouteName(): string
    {
        return $this->routeRegistry->has('frontend.home')
            ? 'frontend.home'
            : (string) $this->routeRegistry->routes()->keys()->first();
    }

    private function managedPageForRoute(array $definition, string $locale): ?Page
    {
        $slug = $definition['page_slug'] ?? null;
        if (!$slug) {
            return null;
        }

        $source = Page::query()
            ->where('slug', $slug)
            ->where('language', $this->defaultLocale())
            ->first();
        if ($source && filled($source->uuid)) {
            return Page::query()->where('uuid', $source->uuid)->where('language', $locale)->first();
        }

        return $locale === $this->defaultLocale()
            ? $source
            : Page::query()->where('slug', $slug)->where('language', $locale)->first();
    }

    /** @return array{0: ?Page, 1: mixed} */
    private function lockAndVerifyManagedRouteOwner(array $definition, string $locale, ?Page $expected): array
    {
        $slug = trim((string) ($definition['page_slug'] ?? ''));
        if ($slug === '') {
            return [null, null];
        }

        // The expected UUID was discovered before this transaction. Lock that
        // logical Page first, then perform one current-read of the special
        // slug and inspect the rows it returns. A later plain SELECT would use
        // MySQL's older repeatable-read snapshot and could miss a Page that
        // appeared while this form was open.
        $expectedUuid = trim((string) $expected?->uuid);
        $lockedPages = $expectedUuid !== ''
            ? $this->pageEditorVersions->lockForMutation([$expectedUuid])
            : null;
        $routeRows = Page::withTrashed()
            ->where('slug', $slug)
            ->whereIn('language', array_values(array_unique([$this->defaultLocale(), $locale])))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $source = $routeRows->first(fn (Page $page): bool =>
            !$page->trashed() && (string) $page->language === $this->defaultLocale()
        );
        $sourceUuid = trim((string) $source?->uuid);

        abort_if(
            $sourceUuid !== '' && $sourceUuid !== $expectedUuid,
            409,
            SeoMetadataEditorVersionService::CONFLICT_MESSAGE
        );
        abort_if(
            $sourceUuid !== ''
                && (!$lockedPages || !$lockedPages->get($sourceUuid)?->contains(
                    fn (Page $page): bool => (int) $page->getKey() === (int) $source?->getKey()
                )),
            409,
            SeoMetadataEditorVersionService::CONFLICT_MESSAGE
        );

        if ($sourceUuid !== '') {
            $current = $lockedPages?->get($sourceUuid)?->first(fn (Page $page): bool =>
                !$page->trashed() && (string) $page->language === $locale
            );
        } else {
            $current = $locale === $this->defaultLocale()
                ? $source
                : $routeRows->first(fn (Page $page): bool =>
                    !$page->trashed() && (string) $page->language === $locale
                );
        }
        abort_if(
            (string) ($current?->getKey() ?? '') !== (string) ($expected?->getKey() ?? ''),
            409,
            SeoMetadataEditorVersionService::CONFLICT_MESSAGE
        );

        return [$current, $lockedPages];
    }

    private function metadataForModel(Model $model, string $locale): ?SeoMetadata
    {
        return SeoMetadata::where('seoable_type', $model::class)
            ->where('seoable_id', $model->getKey())
            ->where('locale', $locale)
            ->first();
    }

    private function metadataSnapshotForModel(Model $model, string $locale): ?SeoMetadata
    {
        return SeoMetadata::withTrashed()
            ->where('seoable_type', $model::class)
            ->where('seoable_id', $model->getKey())
            ->where('locale', $locale)
            ->first();
    }

    private function visibleSeoSnapshot(?SeoMetadata $metadata): ?SeoMetadata
    {
        return $metadata?->trashed() ? null : $metadata;
    }

    /** @return array{0: Page, 1: int} */
    private function pageEditorRenderSnapshot(Page $page, string $locale): array
    {
        $uuid = trim((string) $page->uuid);
        $pages = Page::withTrashed()
            ->when(
                $uuid !== '',
                fn ($query) => $query->where('uuid', $uuid),
                fn ($query) => $query->whereKey($page->getKey())
            )
            ->orderBy('id')
            ->get();
        $current = $pages->first(fn (Page $candidate): bool =>
            !$candidate->trashed() && (string) $candidate->language === $locale
        );

        abort_unless($current instanceof Page, 404);

        return [$current, (int) $pages->max('editor_version')];
    }

    private function englishSource(Model $model, string $type): array
    {
        $source = $model;
        if ($type !== 'project' && $model->getAttribute('language') !== 'en') {
            $source = match ($type) {
                'page', 'category' => $model->getAttribute('uuid')
                    ? $model::query()->where('uuid', $model->getAttribute('uuid'))->where('language', 'en')->first()
                    : null,
                'event', 'annual_report' => $model->getAttribute('translation_key')
                    ? $model::query()->where('translation_key', $model->getAttribute('translation_key'))->where('language', 'en')->first()
                    : null,
                default => null,
            };
        }
        if (!$source) {
            return [null, null];
        }

        return [$this->metadataForModel($source, 'en'), $this->fallbackForModel($source, $type)];
    }

    private function copyEnglishUrl(string $kind, ?string $routeName, ?Model $model, string $locale): ?string
    {
        if ($locale === 'en') {
            return null;
        }
        if ($kind === 'route' && $routeName) {
            return route('seo.index', ['route' => $routeName, 'locale' => $locale, 'copy' => 'en']) . '#seo-editor';
        }

        return $model ? route('seo.content.edit', ['type' => $kind, 'id' => $model->getKey(), 'locale' => $locale, 'copy' => 'en']) : null;
    }

    private function permalinkEditable(Model $model, string $type): bool
    {
        return $type !== 'page'
            || !$this->routeRegistry->all()->contains(fn (array $definition) => ($definition['page_slug'] ?? null) === $model->getAttribute('slug'));
    }

    private function permalinkRestrictionMessage(Model $model, string $type): string
    {
        return 'This is a protected primary website address. Change its navigation label instead of its URL.';
    }

    private function permalinkPrefix(string $type): string
    {
        return match ($type) {
            'category' => '/category/',
            'event' => '/event/',
            'annual_report' => '/annual-report/',
            'project' => '/projects/',
            default => '/page/',
        };
    }

    private function slugRules(Model $model, string $type): array
    {
        $rule = Rule::unique($model->getTable(), 'slug')->ignore($model->getKey());
        if (in_array($type, ['page', 'category', 'event', 'annual_report'], true) && $model->getAttribute('language')) {
            $rule->where(fn ($query) => $query->where('language', $model->getAttribute('language')));
        }

        return ['nullable', 'string', 'max:255', $rule];
    }

    private function upsertAutomaticRedirect(string $oldUrl, string $newUrl, ?string $locale = null): void
    {
        $from = (string) (parse_url($oldUrl, PHP_URL_PATH) ?: '/');
        $to = (string) (parse_url($newUrl, PHP_URL_PATH) ?: '/');
        $query = (string) (parse_url($newUrl, PHP_URL_QUERY) ?: '');
        if ($query !== '') {
            $to .= '?' . $query;
        }
        if ($from === '/' || $from === $to) {
            return;
        }

        $from = $this->redirects->normalizeSourcePath($from);
        $sourceHash = $this->redirects->sourceHash($from);
        $locale = $this->redirects->normalizeLocale($locale);
        $scopeHash = $this->redirects->scopeHash($sourceHash, $locale);
        $existing = SeoRedirect::withTrashed()->where('source_scope_hash', $scopeHash)->first();
        if (!$existing && $locale !== null) {
            // Upgrade a legacy path-global automatic rule to the edited
            // language instead of leaving it able to hijack sibling locales.
            $existing = SeoRedirect::withTrashed()
                ->where('from_path_hash', $sourceHash)
                ->whereNull('locale')
                ->first();
        }
        if ($existing?->trashed()) {
            $existing = $this->redirects->restore($existing);
        }
        $payload = [
            'from_path' => $from,
            'to_url' => $to,
            'status_code' => 301,
            'is_active' => true,
            'locale' => $locale,
        ];
        $existing ? $this->redirects->update($existing, $payload) : $this->redirects->create($payload);
    }

    private function safeVisibility(array $seo): array
    {
        $seo['robots_index'] = (bool) ($seo['robots_index'] ?? true);
        $seo['robots_follow'] = (bool) ($seo['robots_follow'] ?? true);
        $seo['exclude_from_sitemap'] = !$seo['robots_index'] || (bool) ($seo['exclude_from_sitemap'] ?? false);
        if (trim((string) ($seo['schema_markup'] ?? '')) === '[]') {
            $seo['schema_markup'] = null;
        }

        return $seo;
    }

    private function normalizeSeoInput(Request $request): void
    {
        $seo = $request->input('seo');
        if (!is_array($seo)) {
            return;
        }

        if (trim((string) ($seo['schema_markup'] ?? '')) === '[]') {
            $seo['schema_markup'] = '';
        }
        foreach (['og_image', 'twitter_image'] as $field) {
            if (!empty($seo[$field])) {
                $seo[$field] = $this->absoluteImageUrl((string) $seo[$field]);
            }
        }

        $request->merge(['seo' => $seo]);
    }

    private function absoluteImageUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '' || Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        return url('/' . ltrim($value, '/'));
    }

    private function enforceCanonicalSafety(Request $request, string $canonical, string $defaultCanonical): void
    {
        $canonical = trim($canonical);
        if ($canonical === '') {
            return;
        }

        $canonicalOrigin = $this->canonicalOrigin($canonical);
        $defaultOrigin = $this->canonicalOrigin($defaultCanonical);
        if ($canonicalOrigin === null || $defaultOrigin === null) {
            throw ValidationException::withMessages([
                'seo.canonical_url' => 'The preferred URL must be a valid HTTP or HTTPS address without embedded credentials.',
            ]);
        }
        if (hash_equals($defaultOrigin, $canonicalOrigin)) {
            return;
        }

        $canUseExternal = app(Permission::class)->allows(
            $request->user('admin'),
            'seo.canonical.external'
        );
        if (!$canUseExternal) {
            throw ValidationException::withMessages([
                'seo.canonical_url' => 'This address points to another website. External canonical URLs require the dedicated specialist permission.',
            ]);
        }
        if (!$request->boolean('external_canonical_confirm')) {
            throw ValidationException::withMessages([
                'external_canonical_confirm' => 'Confirm that this page should credit another website before saving or restoring it.',
            ]);
        }
    }

    private function canonicalOrigin(string $url): ?string
    {
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if ($port < 1 || $port > 65535) {
            return null;
        }

        return $scheme . '://' . $host . ':' . $port;
    }

    private function isExternalCanonical(string $canonical, string $defaultCanonical): bool
    {
        $canonical = trim($canonical);
        if ($canonical === '') {
            return false;
        }
        $canonicalOrigin = $this->canonicalOrigin($canonical);
        $defaultOrigin = $this->canonicalOrigin($defaultCanonical);

        return $canonicalOrigin === null
            || $defaultOrigin === null
            || !hash_equals($defaultOrigin, $canonicalOrigin);
    }

    /** @return Collection<string, array{external: bool, canonical: string}> */
    private function revisionCanonicalPolicies(Collection $revisions, string $defaultCanonical): Collection
    {
        return $revisions->mapWithKeys(function (SeoMetadataRevision $revision) use ($defaultCanonical): array {
            $canonical = trim((string) data_get($revision->snapshot, 'canonical_url', ''));

            return [(string) $revision->uuid => [
                'external' => $this->isExternalCanonical($canonical, $defaultCanonical),
                'canonical' => $canonical,
            ]];
        });
    }

    private function defaultCanonicalForMetadata(SeoMetadata $metadata): string
    {
        if ($metadata->route_name) {
            abort_unless($this->routeRegistry->has((string) $metadata->route_name), 409, 'This SEO record no longer has a public route.');

            return (string) $this->seo->localizedUrl(
                url((string) $this->routeRegistry->path((string) $metadata->route_name)),
                (string) $metadata->locale,
                $this->defaultLocale()
            );
        }

        $modelClass = (string) $metadata->seoable_type;
        $type = $this->revisionContentType($modelClass);
        abort_unless($type, 409, 'This SEO record no longer has a supported content owner.');
        $model = $modelClass::withTrashed()->find($metadata->seoable_id);
        abort_if(!$model, 409, 'This SEO record no longer has a content owner.');

        return $this->publicUrl($model, $type, (string) $metadata->locale);
    }

    /** @return array{0: Collection, 1: array<string, string>} */
    private function bulkTargets(Request $request): array
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'locale' => (string) $request->query('locale', 'all'),
            'type' => (string) $request->query('type', 'all'),
        ];
        if ($filters['locale'] !== 'all' && !in_array($filters['locale'], $this->localeIds(), true)) {
            $filters['locale'] = 'all';
        }
        if (!in_array($filters['type'], ['all', 'route', 'page', 'category', 'event', 'annual_report', 'project'], true)) {
            $filters['type'] = 'all';
        }

        $targets = collect($this->localeIds())
            ->flatMap(fn (string $locale) => $this->dashboardTargets($locale, true))
            ->filter(function (array $target) use ($filters): bool {
                $matchesSearch = $filters['search'] === '' || str_contains(
                    mb_strtolower($target['label'] . ' ' . $target['url']),
                    mb_strtolower($filters['search'])
                );
                $matchesLocale = $filters['locale'] === 'all' || $target['locale'] === $filters['locale'];
                $matchesType = $filters['type'] === 'all' || $target['type'] === $filters['type'];

                return $matchesSearch && $matchesLocale && $matchesType;
            })
            ->sortBy([['locale', 'asc'], ['type', 'asc'], ['label', 'asc']])
            ->values();

        return [$targets, $filters];
    }

    /** @return array{0: ?Model, 1: ?string, 2: ?string, 3: ?SeoMetadata, 4: array, 5: string} */
    private function resolveBulkOwner(
        array $item,
        array $lockedOwners = [],
        array $lockedRouteOwners = []
    ): array
    {
        $locale = (string) $item['locale'];
        if ($item['owner_type'] === 'route') {
            $routeName = (string) ($item['route_name'] ?? '');
            if (!$this->routeRegistry->has($routeName)) {
                throw ValidationException::withMessages(['items' => 'A selected website feature is not available for SEO editing.']);
            }
            $definition = $this->routeRegistry->definition($routeName) ?: [];
            $path = (string) $this->routeRegistry->path($routeName);
            $routeKey = $this->specialRouteOwnerKey($routeName, $locale);
            $managedPage = array_key_exists($routeKey, $lockedRouteOwners)
                ? $lockedRouteOwners[$routeKey]
                : null;
            if ($managedPage) {
                return [
                    $managedPage,
                    null,
                    null,
                    $this->metadataForModel($managedPage, $locale),
                    $this->fallbackForModel($managedPage, 'page'),
                    $this->publicUrl($managedPage, 'page', $locale),
                ];
            }
            $metadata = SeoMetadata::where('route_name', $routeName)->where('locale', $locale)->first();
            $fallback = $this->fallbackForRoute($routeName, $definition);
            $url = (string) $this->seo->localizedUrl(url($path), $locale, $this->defaultLocale());

            return [null, $routeName, $path, $metadata, $fallback, $url];
        }

        $ownerKey = (string) $item['owner_type'] . ':' . (string) ($item['owner_id'] ?? '');
        if (array_key_exists($ownerKey, $lockedOwners)) {
            $model = $lockedOwners[$ownerKey];
        } else {
            [$model] = $this->contentTarget((string) $item['owner_type'], (string) ($item['owner_id'] ?? ''));
        }
        $boundLocale = $this->boundContentLocale($model, (string) $item['owner_type']);
        if ($boundLocale !== null && $boundLocale !== $locale) {
            throw ValidationException::withMessages(['items' => 'A bulk row was assigned to the wrong language and was not saved.']);
        }
        $metadata = $this->metadataForModel($model, $locale);
        $fallback = $this->fallbackForModel($model, (string) $item['owner_type']);
        $url = $this->publicUrl($model, (string) $item['owner_type'], $locale);

        return [$model, null, null, $metadata, $fallback, $url];
    }

    /**
     * Resolve only Page lock identities before the transaction. SEO owners,
     * metadata, fallbacks, and URLs are deliberately resolved after all of
     * these identities have been locked and their UI tokens asserted.
     *
     * @param array<int, array<string, mixed>> $items
     */
    private function pageRowsForBulkItems(array $items, Collection $routeOwnerExpectations): Collection
    {
        $items = collect($items);
        $directPages = Page::query()
            ->whereIn(
                'id',
                $items
                    ->where('owner_type', 'page')
                    ->pluck('owner_id')
                    ->filter()
                    ->unique()
            )
            ->get()
            ->keyBy(fn (Page $page): int => (int) $page->getKey());

        return $items
            ->map(function (array $item) use ($directPages, $routeOwnerExpectations): ?array {
                $page = null;
                if ($item['owner_type'] === 'page') {
                    $page = $directPages->get((int) ($item['owner_id'] ?? 0));
                } elseif ($item['owner_type'] === 'route') {
                    $routeName = (string) ($item['route_name'] ?? '');
                    if (!$this->routeRegistry->has($routeName)) {
                        return null;
                    }
                    $page = data_get(
                        $routeOwnerExpectations->get(
                            $this->specialRouteOwnerKey($routeName, (string) $item['locale'])
                        ),
                        'owner'
                    );
                }

                if (!$page instanceof Page) {
                    return null;
                }

                return [
                    'uuid' => trim((string) $page->uuid),
                    'locale' => (string) $item['locale'],
                    'expected_editor_version' => $item['expected_editor_version'] ?? null,
                    'route_name' => $item['owner_type'] === 'route' ? (string) $item['route_name'] : null,
                    'owner_id' => (int) $page->getKey(),
                ];
            })
            ->filter()
            ->values();
    }

    /** @param array<int, array<string, mixed>> $items */
    private function specialRouteOwnerExpectationsForBulkItems(array $items): Collection
    {
        return collect($items)
            ->where('owner_type', 'route')
            ->mapWithKeys(function (array $item): array {
                $routeName = (string) ($item['route_name'] ?? '');
                if (!$this->routeRegistry->has($routeName)) {
                    return [];
                }
                $slug = trim((string) data_get($this->routeRegistry->definition($routeName), 'page_slug'));
                if ($slug === '') {
                    return [];
                }
                $locale = (string) $item['locale'];
                $source = Page::query()
                    ->where('slug', $slug)
                    ->where('language', $this->defaultLocale())
                    ->first();
                $owner = $source && filled($source->uuid)
                    ? Page::query()->where('uuid', $source->uuid)->where('language', $locale)->first()
                    : ($locale === $this->defaultLocale()
                        ? $source
                        : Page::query()->where('slug', $slug)->where('language', $locale)->first());

                return [$this->specialRouteOwnerKey($routeName, $locale) => [
                    'route_name' => $routeName,
                    'slug' => $slug,
                    'locale' => $locale,
                    'source_id' => $source?->getKey(),
                    'source_uuid' => trim((string) $source?->uuid),
                    'owner_id' => $owner?->getKey(),
                    'owner_uuid' => trim((string) $owner?->uuid),
                    'owner' => $owner,
                ]];
            })
            ->sortKeys();
    }

    /**
     * Inspect the current-read rows returned by each special-route gap lock.
     * Never re-resolve through a plain SELECT in this transaction: under
     * MySQL repeatable read that would revive the pre-lock snapshot.
     *
     * @return array<string, Page|null>
     */
    private function lockAndVerifySpecialRouteOwners(Collection $expectations, Collection $lockedPages): array
    {
        $owners = [];

        foreach ($expectations as $key => $expectation) {
            $routeRows = Page::withTrashed()
                ->where('slug', $expectation['slug'])
                ->whereIn('language', array_values(array_unique([
                    $this->defaultLocale(),
                    $expectation['locale'],
                ])))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $source = $routeRows->first(fn (Page $page): bool =>
                !$page->trashed() && (string) $page->language === $this->defaultLocale()
            );
            $sourceUuid = trim((string) $source?->uuid);

            abort_if(
                (string) ($source?->getKey() ?? '') !== (string) ($expectation['source_id'] ?? '')
                    || $sourceUuid !== (string) ($expectation['source_uuid'] ?? ''),
                409,
                SeoMetadataEditorVersionService::CONFLICT_MESSAGE
            );
            if ($sourceUuid !== '') {
                $logicalPages = $lockedPages->get($sourceUuid);
                abort_if(
                    !$logicalPages
                        || !$logicalPages->contains(fn (Page $page): bool =>
                            (int) $page->getKey() === (int) $source?->getKey()
                        ),
                    409,
                    SeoMetadataEditorVersionService::CONFLICT_MESSAGE
                );
                $owner = $logicalPages->first(fn (Page $page): bool =>
                    !$page->trashed() && (string) $page->language === (string) $expectation['locale']
                );
            } else {
                $owner = (string) $expectation['locale'] === $this->defaultLocale()
                    ? $source
                    : $routeRows->first(fn (Page $page): bool =>
                        !$page->trashed() && (string) $page->language === (string) $expectation['locale']
                    );
            }

            abort_if(
                (string) ($owner?->getKey() ?? '') !== (string) ($expectation['owner_id'] ?? ''),
                409,
                SeoMetadataEditorVersionService::CONFLICT_MESSAGE
            );
            $owners[$key] = $owner;
        }

        return $owners;
    }

    private function specialRouteOwnerKey(string $routeName, string $locale): string
    {
        return $routeName . "\0" . $locale;
    }

    /**
     * Lock non-Page owners before their SEO rows. The stable type order and
     * primary-key order are shared by single-row and bulk mutations.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<string, Model>
     */
    private function lockNonPageBulkOwners(array $items): array
    {
        $requested = collect($items)
            ->filter(fn (array $item): bool => $this->nonPageContentClass((string) $item['owner_type']) !== null)
            ->groupBy('owner_type');
        $locked = [];

        // This cross-feature order is shared with Translation Center. Keep
        // every transaction that can touch several translated owner tables
        // on the same sequence before any owned SEO metadata is locked.
        foreach (['category', 'event', 'annual_report', 'project'] as $type) {
            $rows = $requested->get($type);
            if (!$rows) {
                continue;
            }
            $modelClass = $this->nonPageContentClass((string) $type);
            abort_unless($modelClass, 404);
            $ids = $rows->pluck('owner_id')->map(fn ($id): int => (int) $id)->filter()->unique()->sort()->values();
            $models = $modelClass::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            abort_unless($models->count() === $ids->count(), 404);

            foreach ($models as $model) {
                $locked[$type . ':' . $model->getKey()] = $model;
            }
        }

        return $locked;
    }

    private function lockNonPageContentOwner(string $type, string $id): Model
    {
        $modelClass = $this->nonPageContentClass($type);
        abort_unless($modelClass, 404);

        return $modelClass::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** @return class-string<Model>|null */
    private function nonPageContentClass(string $type): ?string
    {
        return match ($type) {
            'category' => Category::class,
            'event' => NoticeBoard::class,
            'annual_report' => AnnualReport::class,
            'project' => Tag::class,
            default => null,
        };
    }

    private function bulkPayload(?SeoMetadata $metadata, array $item, array $fallback, string $url): array
    {
        $indexable = (bool) $item['indexable'];
        $image = $this->absoluteImageUrl((string) ($item['image'] ?? ''));
        $payload = [
            'title' => $item['mode'] === 'auto' ? null : trim((string) ($item['title'] ?? '')),
            'description' => $item['mode'] === 'auto' ? null : trim((string) ($item['description'] ?? '')),
            'og_image' => $image ?: null,
            'twitter_image' => $image ?: null,
            'robots_index' => $indexable,
            'robots_follow' => (bool) ($metadata?->robots_follow ?? true),
            'exclude_from_sitemap' => !$indexable,
        ];
        $template = (string) $item['schema_template'];
        if ($template !== 'expert') {
            $title = $payload['title'] ?: (string) ($fallback['meta_title'] ?? '');
            $description = $payload['description'] ?: (string) ($fallback['meta_description'] ?? '');
            $payload['schema_markup'] = $this->schemaTemplates->generate($template, [
                'name' => $title,
                'url' => $url,
                'description' => $description,
                'image' => $image ?: (string) ($fallback['meta_image'] ?? ''),
                'locale' => (string) $item['locale'],
            ]);
        }

        return $payload;
    }

    /** @return array{state: string, label: string, is_live: bool} */
    private function publicationState(?Model $model, string $type): array
    {
        if (!$model || $type === 'route') {
            return ['state' => 'live', 'label' => 'Live website feature', 'is_live' => true];
        }

        if ($type === 'annual_report') {
            $publishedAt = $model->getAttribute('published_at');
            $scheduled = $publishedAt && \Illuminate\Support\Carbon::parse($publishedAt)->isFuture();
            $enabled = (bool) $model->getAttribute('status');
            $isLive = $enabled && !$scheduled;

            return [
                'state' => $isLive ? 'published' : ($scheduled && $enabled ? 'scheduled' : 'draft'),
                'label' => $isLive ? 'Live report' : ($scheduled && $enabled ? 'Scheduled report' : 'Draft report'),
                'is_live' => $isLive,
            ];
        }

        $state = trim((string) $model->getAttribute('publication_status'));
        if ($state === '') {
            $state = (bool) $model->getAttribute('status') ? 'published' : 'draft';
        }
        $scheduledFor = $model->getAttribute('scheduled_for');
        $scheduledIsLive = $state === 'scheduled'
            && $scheduledFor
            && method_exists($scheduledFor, 'isPast')
            && $scheduledFor->isPast();
        $isLive = ($state === 'published' || $scheduledIsLive)
            && (bool) ($model->getAttribute('status') ?? true)
            && (string) ($model->getAttribute('visibility') ?? 'public') !== 'private';

        return [
            'state' => $state,
            'label' => match ($state) {
                'published' => $isLive ? 'Live' : 'Unpublished',
                'pending_review' => 'Content awaiting review',
                'scheduled' => $scheduledIsLive ? 'Live (scheduled)' : 'Scheduled content',
                'private' => 'Private content',
                default => 'Draft content',
            },
            'is_live' => $isLive,
        ];
    }

    private function resetReviewAfterEdit(SeoMetadata $metadata): void
    {
        $metadata->forceFill([
            'review_status' => 'draft',
            'review_note' => null,
            'review_content_hash' => null,
            'review_requested_by' => null,
            'review_requested_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ])->save();
    }

    private function seoMutationTransaction(callable $callback): mixed
    {
        try {
            return DB::transaction($callback, 3);
        } catch (QueryException $exception) {
            if ($this->seoEditorVersions->isOwnershipCollision($exception)) {
                abort(409, SeoMetadataEditorVersionService::CONFLICT_MESSAGE);
            }

            throw $exception;
        }
    }

    /** @return Collection<string, array<int, array{field: string, before: string, after: string}>> */
    private function revisionDiffs(Collection $revisions, ?SeoMetadata $current): Collection
    {
        $newer = $current ? $current->only(SeoMetadataPayload::WRITABLE_FIELDS) : [];
        $diffs = collect();
        foreach ($revisions as $revision) {
            $before = (array) ($revision->snapshot ?: []);
            $changes = collect(SeoMetadataPayload::WRITABLE_FIELDS)
                ->filter(fn (string $field) => $this->diffValue($before[$field] ?? null) !== $this->diffValue($newer[$field] ?? null))
                ->map(fn (string $field) => [
                    'field' => $this->seoFieldLabel($field),
                    'before' => $this->diffValue($before[$field] ?? null),
                    'after' => $this->diffValue($newer[$field] ?? null),
                ])->values()->all();
            $diffs->put((string) $revision->uuid, $changes);
            $newer = $before;
        }

        return $diffs;
    }

    private function seoFieldLabel(string $field): string
    {
        return match ($field) {
            'title' => 'Search title',
            'description' => 'Search description',
            'focus_keyword' => 'Focus phrase',
            'canonical_url' => 'Canonical URL',
            'robots_index' => 'Search visibility',
            'robots_follow' => 'Follow links',
            'og_image', 'twitter_image' => 'Social image',
            'og_title', 'twitter_title' => 'Social title',
            'og_description', 'twitter_description' => 'Social description',
            'schema_markup' => 'Structured data',
            'exclude_from_sitemap' => 'Sitemap visibility',
            default => Str::headline($field),
        };
    }

    private function diffValue(mixed $value): string
    {
        if (is_bool($value) || in_array($value, [0, 1, '0', '1'], true)) {
            return (bool) $value ? 'Enabled' : 'Disabled';
        }
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        }
        $value = trim((string) $value);

        return $value === '' ? 'Not set' : Str::limit($value, 140);
    }

    /** @return array<string, mixed> */
    private function mediaPickerAsset(MediaAsset $asset, bool $canViewMedia): array
    {
        $width = (int) ($asset->width ?? 0);
        $height = (int) ($asset->height ?? 0);
        $ratio = $height > 0 ? $width / $height : 0.0;
        $warnings = [];
        if (blank($asset->alt_text)) {
            $warnings[] = 'Alternative text is missing';
        }
        if ($width < 1200 || $height < 630) {
            $warnings[] = 'Smaller than the recommended 1200 × 630';
        }
        if ($ratio > 0 && abs($ratio - (1200 / 630)) > 0.2) {
            $warnings[] = 'May crop in a 1.91:1 social card';
        }

        return [
            'uuid' => $asset->uuid,
            'name' => $asset->original_name,
            'url' => Str::startsWith($asset->url, ['http://', 'https://']) ? $asset->url : url($asset->url),
            'alt_text' => (string) ($asset->alt_text ?? ''),
            'width' => $width ?: null,
            'height' => $height ?: null,
            'warnings' => $warnings,
            'edit_url' => $canViewMedia
                ? route('media.index', ['type' => 'image', 'search' => $asset->original_name])
                : null,
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function reviewIdentityRules(): array
    {
        return [
            'owner_type' => ['required', Rule::in(['route', 'page', 'category', 'event', 'annual_report', 'project'])],
            'owner_id' => ['nullable', 'integer'],
            'route_name' => ['nullable', 'string', 'max:150'],
            'locale' => ['required', 'string', Rule::in($this->localeIds())],
        ];
    }

    /** @return array{0: SeoMetadata, 1: array, 2: string} */
    private function resolveReviewMetadata(array $data, bool $lock = false): array
    {
        $locale = (string) $data['locale'];
        if ($data['owner_type'] === 'route') {
            $routeName = (string) ($data['route_name'] ?? '');
            if (!$this->routeRegistry->has($routeName)) {
                throw ValidationException::withMessages(['review' => 'This website feature is not available for SEO review.']);
            }
            $definition = $this->routeRegistry->definition($routeName) ?: [];
            $path = (string) $this->routeRegistry->path($routeName);
            $query = SeoMetadata::query()->where('route_name', $routeName)->where('locale', $locale);
            $metadata = ($lock ? $query->lockForUpdate() : $query)->first();
            $fallback = $this->fallbackForRoute($routeName, $definition);
            $url = (string) $this->seo->localizedUrl(url($path), $locale, $this->defaultLocale());
        } else {
            [$unlocked] = $this->contentTarget((string) $data['owner_type'], (string) ($data['owner_id'] ?? ''));
            $modelQuery = $unlocked->newQuery()->whereKey($unlocked->getKey());
            $model = ($lock ? $modelQuery->lockForUpdate() : $modelQuery)->firstOrFail();
            $boundLocale = $this->boundContentLocale($model, (string) $data['owner_type']);
            if ($boundLocale !== null && $boundLocale !== $locale) {
                throw ValidationException::withMessages(['review' => 'This SEO review is assigned to the wrong language.']);
            }
            $metadataQuery = SeoMetadata::query()
                ->where('seoable_type', $model::class)
                ->where('seoable_id', $model->getKey())
                ->where('locale', $locale);
            $metadata = ($lock ? $metadataQuery->lockForUpdate() : $metadataQuery)->first();
            $fallback = $this->fallbackForModel($model, (string) $data['owner_type']);
            $url = $this->publicUrl($model, (string) $data['owner_type'], $locale);
        }

        if (!$metadata) {
            throw ValidationException::withMessages(['review' => 'Save the SEO metadata once before starting review.']);
        }

        return [$metadata, $fallback, $url];
    }

    private function reviewContentHash(SeoMetadata $metadata, array $fallback, string $url): string
    {
        return $this->seoReviews->contentHash($metadata, $fallback, $url);
    }

    /** @return array<string, mixed> */
    private function healthForMetadata(SeoMetadata $metadata, array $fallback, string $url): array
    {
        $meta = $metadata->toMetaArray($fallback);

        return $this->health->evaluate([
            'title' => $meta['meta_title'] ?? '',
            'description' => $meta['meta_description'] ?? '',
            'focus_keyword' => (string) ($metadata->focus_keyword ?? ''),
            'image' => $meta['og_image'] ?? '',
            'canonical' => $meta['canonical_url'] ?? '',
            'default_url' => $url,
            'indexable' => (bool) $metadata->robots_index,
            'excluded' => (bool) $metadata->exclude_from_sitemap,
        ]);
    }

    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
    }

    private function metadataRules(array $routeNames, array $localeIds): array
    {
        return array_merge([
            'route_name' => ['required', Rule::in($routeNames)],
            'locale' => ['required', 'string', Rule::in($localeIds)],
            'expected_editor_version' => ['nullable', 'integer', 'min:0'],
            'expected_seo_version' => ['nullable', 'string', 'max:100'],
            'schema_template' => $this->schemaTemplateRules(),
            'seo' => ['required', 'array'],
        ], $this->seoRules());
    }

    private function schemaTemplateRules(): array
    {
        return [
            'sometimes',
            'nullable',
            Rule::in(array_merge(array_keys($this->schemaTemplates->options()), ['expert'])),
        ];
    }

    private function seoRules(): array
    {
        return [
            'seo.title' => ['nullable', 'string', 'max:255'],
            'seo.description' => ['nullable', 'string', 'max:500'],
            'seo.focus_keyword' => ['nullable', 'string', 'max:255'],
            'seo.canonical_url' => ['nullable', 'url:http,https', 'max:2048'],
            'seo.robots_index' => ['required', 'boolean'],
            'seo.robots_follow' => ['required', 'boolean'],
            'seo.og_title' => ['nullable', 'string', 'max:255'],
            'seo.og_description' => ['nullable', 'string', 'max:500'],
            'seo.og_image' => ['nullable', 'url:http,https', 'max:2048'],
            'seo.twitter_card' => ['required', Rule::in(['summary', 'summary_large_image'])],
            'seo.twitter_title' => ['nullable', 'string', 'max:255'],
            'seo.twitter_description' => ['nullable', 'string', 'max:500'],
            'seo.twitter_image' => ['nullable', 'url:http,https', 'max:2048'],
            'seo.schema_markup' => ['nullable', 'json', 'max:50000'],
            'seo.sitemap_priority' => ['required', 'numeric', 'between:0,1'],
            'seo.sitemap_change_frequency' => ['required', Rule::in(['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'])],
            'seo.exclude_from_sitemap' => ['required', 'boolean'],
        ];
    }

    private function expectedPageEditorVersion(mixed $value): int
    {
        abort_if(
            $value === null || $value === '',
            409,
            PageEditorVersionService::CONFLICT_MESSAGE
        );

        return (int) $value;
    }

    private function contentTarget(string $type, string $id): array
    {
        $definition = match ($type) {
            'page' => [Page::class, 'Page'],
            'category' => [Category::class, 'Category'],
            'event' => [NoticeBoard::class, 'Event / publication'],
            'annual_report' => [AnnualReport::class, 'Annual report'],
            'project' => [Tag::class, 'Project'],
            default => null,
        };
        abort_unless($definition, 404);

        return [$definition[0]::findOrFail($id), $definition[1]];
    }

    private function assertRestorableRevisionTarget(SeoMetadataRevision $revision): void
    {
        abort_unless(in_array((string) $revision->locale, $this->localeIds(), true), 409, 'This restore point uses an unavailable language.');

        $isRoute = filled($revision->route_name)
            && blank($revision->seoable_type)
            && blank($revision->seoable_id);
        $isContent = blank($revision->route_name)
            && filled($revision->seoable_type)
            && filled($revision->seoable_id);
        abort_unless($isRoute xor $isContent, 409, 'This restore point has an invalid owner.');

        if ($isRoute) {
            abort_unless($this->routeRegistry->has((string) $revision->route_name), 404);
            return;
        }

        $modelClass = (string) $revision->seoable_type;
        abort_unless($this->revisionContentType($modelClass), 404);
        abort_unless($modelClass::withTrashed()->whereKey($revision->seoable_id)->exists(), 409, 'The content for this restore point no longer exists.');
    }

    private function revisionContentType(string $modelClass): ?string
    {
        return match ($modelClass) {
            Page::class => 'page',
            Category::class => 'category',
            NoticeBoard::class => 'event',
            AnnualReport::class => 'annual_report',
            Tag::class => 'project',
            default => null,
        };
    }

    private function boundContentLocale(Model $model, string $type): ?string
    {
        if (!in_array($type, ['page', 'category', 'event', 'annual_report'], true)) {
            return null;
        }

        $locale = (string) $model->getAttribute('language');

        return in_array($locale, $this->localeIds(), true) ? $locale : $this->defaultLocale();
    }

    private function contentIdentity(Model $model, string $type): string
    {
        $identity = match ($type) {
            'page', 'category', 'project' => $model->getAttribute('uuid') ?: $model->getAttribute('slug'),
            'event' => $model->getAttribute('translation_key') ?: 'record:' . $model->getKey(),
            'annual_report' => $model->getAttribute('translation_key') ?: 'record:' . $model->getKey(),
            default => null,
        };

        return $type . ':' . ($identity ?: $model->getKey());
    }

    private function modelTitle(Model $model): string
    {
        return (string) ($model->getAttribute('name') ?: $model->getAttribute('title') ?: $model->getAttribute('slug'));
    }

    private function mediaAssets(): Collection
    {
        return MediaAsset::query()->where('mime_type', 'like', 'image/%')->latest()->limit(120)->get();
    }

    private function locales(): Collection
    {
        return $this->localization->editorLocales();
    }

    private function defaultLocale(): string
    {
        $configured = (string) config('app.fallback_locale', 'en');
        $ids = $this->localeIds();

        return in_array($configured, $ids, true) ? $configured : (string) ($ids[0] ?? 'en');
    }

    /** @return array<int, string> */
    private function localeIds(): array
    {
        return $this->locales()->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    private function requestedLocale(Request $request, Collection $locales): string
    {
        $fallback = (string) (app()->getLocale() ?: 'en');
        $requested = (string) $request->query('locale', $fallback);
        $allowed = $locales->pluck('id')->map(fn ($id) => (string) $id);

        return $allowed->contains($requested) ? $requested : ($allowed->first() ?: $fallback);
    }

    private function languageSummary(Collection $locales): Collection
    {
        $baseTargets = $this->dashboardTargets($this->defaultLocale(), false)->keyBy('key');

        return $locales->map(function ($locale) use ($baseTargets): array {
            $id = (string) $locale->id;
            $targets = $this->dashboardTargets($id, false)->keyBy('key');
            $ready = $baseTargets->keys()->filter(
                fn (string $key) => ($targets->get($key)['status'] ?? null) === 'Ready'
            )->count();

            return [
                'id' => $id,
                'name' => (string) $locale->name,
                'ready' => $ready,
                'total' => $baseTargets->count(),
                'missing' => $baseTargets->keys()->diff($targets->keys())->count(),
            ];
        });
    }
}
