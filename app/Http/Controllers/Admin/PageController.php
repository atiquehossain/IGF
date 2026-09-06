<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Comment;
use App\Models\DonationType;
use App\Models\Page;
use App\Models\Tag;
use App\Models\PageTagModule;
use App\Models\SeoMetadata;
use App\Services\PageRevisionService;
use App\Services\PageEditorVersionService;
use App\Services\AdminAuditService;
use App\Services\ContentSanitizer;
use App\Services\PageCategoryTranslationMapper;
use App\Services\TranslationCenterService;
use App\Services\LocalizationManager;
use App\Services\SeoHealthService;
use App\Services\SeoEditorialReviewService;
use App\Services\SeoMetadataService;
use App\Services\AdminPrivateSearch;
use App\Services\SafeMediaReplacementService;
use App\Services\LogicalPageTagService;
use App\Http\Middleware\Permission;

use App\Rules\ValidateUniqueRule;

use App\Helper\Seq;
use App\Helper\StaticUtil;
use App\Helper\Translation;
use App\Helper\IgfFile;

use Exception;
use Throwable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PageController extends Controller
{
    private const REQUIRED_SYSTEM_PAGE_SLUGS = ['home', 'about-us', 'zakat'];

    public function __construct(
        private ContentSanitizer $sanitizer,
        private PageCategoryTranslationMapper $categoryMapper,
        private TranslationCenterService $translationCenter,
        private SeoHealthService $seoHealth,
        private AdminPrivateSearch $privateSearch,
        private SafeMediaReplacementService $media,
        private PageEditorVersionService $editorVersions,
        private SeoEditorialReviewService $seoReviews,
        private SeoMetadataService $seoMetadata,
        private LogicalPageTagService $logicalTags,
    )
    {
    }

    public function index(Request $request)
    {
        $title = $request->Lang->Menu->Page;
        $search = $request->search;
        $status = $request->string('status')->toString();
        $language = $request->query->has('language')
            ? $request->string('language')->toString()
            : 'en';
        $category = $request->integer('category');
        $needsTranslation = $request->boolean('needs_translation');
        $languages = Page::query()
            ->whereNotNull('language')
            ->select('language')
            ->distinct()
            ->orderBy('language')
            ->pluck('language');
        $categories = Category::query()->where('status', 1)->orderBy('name')->get(['id', 'name']);

        $pages = Page::select('pages.*', 'c.name as c_name')
            ->selectSub(function ($query) {
                $query->from('pages as page_translations')
                    ->selectRaw('COUNT(DISTINCT page_translations.language)')
                    ->whereColumn('page_translations.uuid', 'pages.uuid')
                    ->whereNull('page_translations.deleted_at');
            }, 'translation_count')
            ->leftJoin('categories as c', 'c.id', '=', 'pages.category_id')
            ->when($language, fn ($query) => $query->where('pages.language', $language))
            ->when($category, fn ($query) => $query->where('pages.category_id', $category))
            ->when($status && in_array($status, ['draft', 'pending_review', 'scheduled', 'published', 'private'], true), function ($query) use ($status) {
                $query->where('pages.publication_status', $status);
            })
            ->when($needsTranslation && $languages->count() > 1, function ($query) use ($languages) {
                $query->where(function ($missing) use ($languages) {
                    foreach ($languages as $requiredLanguage) {
                        $missing->orWhereNotExists(function ($translation) use ($requiredLanguage) {
                            $translation->selectRaw('1')
                                ->from('pages as required_translation')
                                ->whereColumn('required_translation.uuid', 'pages.uuid')
                                ->where('required_translation.language', $requiredLanguage)
                                ->whereNull('required_translation.deleted_at');
                        });
                    }
                });
            })
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->where('pages.name', 'like', '%' . $search . '%');
                    $query->orWhere('pages.slug', 'like', '%' . $search . '%');
                    $query->orWhere('c.name', 'like', '%' . $search . '%');
                    $query->orWhere('c.slug', 'like', '%' . $search . '%');
                }
            })
            ->orderBy('pages.created_at', 'DESC')
            ->paginate(15);

        $seoRows = SeoMetadata::query()
            ->where('seoable_type', Page::class)
            ->whereIn('seoable_id', $pages->getCollection()->pluck('id'))
            ->get()
            ->keyBy(fn (SeoMetadata $metadata) => $metadata->seoable_id . ':' . $metadata->locale);
        $pages->getCollection()->each(function (Page $page) use ($seoRows): void {
            $metadata = $seoRows->get($page->id . ':' . $page->language);
            $fallback = [
                'meta_title' => (string) ($page->meta_title ?: $page->name),
                'meta_description' => trim(strip_tags((string) ($page->meta_description ?: $page->sub_title ?: $page->description))),
                'meta_image' => (string) ($page->thumbnail ?: ''),
            ];
            $meta = $metadata ? $metadata->toMetaArray($fallback) : $fallback + [
                'canonical_url' => null,
                'og_image' => $fallback['meta_image'],
            ];
            $defaultUrl = $this->seoMetadata->publicUrlForPage($page);
            $page->setAttribute('public_url', $defaultUrl);
            $health = $this->seoHealth->evaluate([
                'title' => $meta['meta_title'] ?? '',
                'description' => $meta['meta_description'] ?? '',
                'focus_keyword' => (string) ($metadata?->focus_keyword ?? ''),
                'image' => $meta['og_image'] ?? '',
                'image_alt' => $this->seoMetadata->socialImageAltText($meta['social_image_alt'] ?? ''),
                'canonical' => $meta['canonical_url'] ?? '',
                'default_url' => $defaultUrl,
                'indexable' => (bool) ($metadata?->robots_index ?? true),
                'excluded' => (bool) ($metadata?->exclude_from_sitemap ?? false),
            ]);
            $page->setAttribute('seo_admin_status', $health['status']);
            $page->setAttribute('seo_admin_score', $health['score']);
            $page->setAttribute(
                'seo_review_status',
                $this->seoReviews->effectiveState($metadata, $fallback, $defaultUrl)['status']
            );
        });

        $counts = Page::query()
            ->selectRaw('publication_status, COUNT(*) as total')
            ->groupBy('publication_status')
            ->pluck('total', 'publication_status');

        return view('admin.page.index')->with(compact(
            'title', 'pages', 'search', 'status', 'counts', 'language', 'category',
            'needsTranslation', 'languages', 'categories'
        ));
    }

    public function bulkCopy(Request $request)
    {
        $data = $request->validate([
            'page_ids' => ['required', 'array', 'min:1', 'max:100'],
            'page_ids.*' => ['integer', 'distinct', 'exists:pages,id'],
            'action' => ['required', 'in:duplicate,translate'],
            'target_language' => ['nullable', 'required_if:action,translate', 'string', 'max:10'],
        ]);

        $created = 0;
        $skipped = 0;
        $changedLogicalPages = [];

        DB::transaction(function () use ($data, &$created, &$skipped, &$changedLogicalPages) {
            $sourceUuids = Page::query()
                ->whereIn('id', $data['page_ids'])
                ->pluck('uuid')
                ->filter()
                ->unique()
                ->sort()
                ->values();
            $pageLocks = $this->editorVersions->lockForMutation($sourceUuids);
            $requestedIds = collect($data['page_ids'])->map(fn ($id): int => (int) $id);
            $sources = $pageLocks
                ->flatten(1)
                ->filter(fn (Page $page): bool =>
                    !$page->trashed() && $requestedIds->contains((int) $page->id)
                )
                ->sortBy(fn (Page $page): string => $page->uuid . ':' . str_pad((string) $page->id, 20, '0', STR_PAD_LEFT))
                ->values();
            abort_unless($sources->count() === $requestedIds->unique()->count(), 404);
            $createdTargets = [];

            foreach ($sources as $source) {
                $source->setRelation(
                    'blocks',
                    $source->blocks()->orderBy('id')->lockForUpdate()->get()
                );
                $source->setRelation(
                    'pageTags',
                    $source->pageTags()->orderBy('id')->lockForUpdate()->get()
                );
                $this->logicalTags->hydrate([$source]);
                $language = $data['action'] === 'translate'
                    ? $data['target_language']
                    : $source->language;

                $targetIdentity = (string) $source->uuid . '|' . (string) $language;
                $targetExists = $pageLocks
                    ->get((string) $source->uuid, collect())
                    ->contains(fn (Page $page): bool =>
                        !$page->trashed() && $page->language === $language
                    );
                if ($data['action'] === 'translate' && ($targetExists || isset($createdTargets[$targetIdentity]))) {
                    $skipped++;
                    continue;
                }

                $newUuid = $data['action'] === 'translate' ? $source->uuid : Seq::uuidV4();
                $copy = $source->replicate();
                $copy->uuid = $newUuid;
                $copy->language = $language;
                if ($data['action'] === 'translate') {
                    $copy->category_id = $this->categoryMapper->targetCategoryId($source, $language);
                    $this->translationCenter->preparePageTranslationDraft($copy);
                    $changedLogicalPages[] = (string) $source->uuid;
                } else {
                    $copy->name = $source->name . ' (Copy)';
                    $copy->editor_version = 0;
                }
                $copy->slug = $data['action'] === 'translate'
                    ? $source->slug
                    : $source->slug . '-copy-' . substr(str_replace('-', '', $newUuid), 0, 8);
                if ($data['action'] !== 'translate') {
                    $copy->status = false;
                    $copy->publication_status = 'draft';
                    // Zakat eligibility is a financial control for one logical
                    // project, not ordinary editorial content to inherit into
                    // a brand-new duplicate UUID.
                    $copy->is_funding_project = false;
                    $copy->is_zakat_eligible = false;
                    $copy->scheduled_for = null;
                    $copy->last_published_at = null;
                    $copy->published_by = null;
                }
                $copy->save();
                if ($data['action'] === 'translate') {
                    $createdTargets[$targetIdentity] = true;
                }

                foreach ($source->blocks as $sourceBlock) {
                    $block = $sourceBlock->replicate();
                    $block->page_id = $copy->id;
                    $block->uuid = Seq::uuidV4();
                    if ($data['action'] === 'translate') {
                        $block->translation_key = $sourceBlock->translation_key ?: $sourceBlock->uuid;
                        $block->reusable_block_id = null;
                        $block->content = $this->translationCenter
                            ->prepareBlockTranslationContent($sourceBlock->resolvedContent());
                        $block->settings = $sourceBlock->resolvedSettings();
                    }
                    $block->save();
                }

                $this->logicalTags->sync(
                    $copy,
                    $source->pageTags->pluck('tag_id')->map(fn ($tagId): int => (int) $tagId)->all()
                );

                $sourceSeo = SeoMetadata::where('seoable_type', Page::class)
                    ->where('seoable_id', $source->id)
                    ->where('locale', $source->language)
                    ->lockForUpdate()
                    ->first();
                if ($sourceSeo && $data['action'] !== 'translate') {
                    $seo = $sourceSeo->replicate();
                    $seo->seoable_type = Page::class;
                    $seo->seoable_id = $copy->id;
                    $seo->locale = $language;
                    $seo->canonical_url = null;
                    $seo->robots_index = false;
                    $seo->review_status = 'draft';
                    $seo->review_note = null;
                    $seo->review_content_hash = null;
                    $seo->review_requested_by = null;
                    $seo->review_requested_at = null;
                    $seo->reviewed_by = null;
                    $seo->reviewed_at = null;
                    if (array_key_exists('review_request_version', $seo->getAttributes())) {
                        $seo->review_request_version = 0;
                    }
                    if (array_key_exists('editor_version', $seo->getAttributes())) {
                        $seo->editor_version = 0;
                    }
                    $seo->created_by = auth('admin')->id();
                    $seo->updated_by = auth('admin')->id();
                    $seo->save();
                }

                app(PageRevisionService::class)->capture($copy, 'Initial snapshot created by bulk ' . $data['action']);
                $created++;
            }
            $this->editorVersions->advanceLocked($pageLocks, $changedLogicalPages);
        });

        return response()->json([
            'message' => $created . ' draft' . ($created === 1 ? '' : 's') . ' created.'
                . ($skipped ? ' ' . $skipped . ' existing translation' . ($skipped === 1 ? ' was' : 's were') . ' skipped.' : ''),
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'page_ids' => ['required', 'array', 'min:1', 'max:100'],
            'page_ids.*' => ['integer', 'distinct', 'exists:pages,id'],
            'force_unpublish_dependencies' => ['sometimes', 'boolean'],
        ]);

        $uuids = Page::whereIn('id', $data['page_ids'])->pluck('uuid')->unique();
        $forceUnpublishDependencies = (bool) ($data['force_unpublish_dependencies'] ?? false);

        return DB::transaction(function () use ($uuids, $forceUnpublishDependencies, $request) {
            $pageLocks = $this->editorVersions->lockForMutation($uuids);
            $pages = $pageLocks
                ->flatten(1)
                ->filter(fn (Page $page): bool => !$page->trashed())
                ->sortBy(fn (Page $page): string => $page->uuid . ':' . str_pad((string) $page->id, 20, '0', STR_PAD_LEFT))
                ->values();
            if ($this->requiredSystemPageNames($pages)->isNotEmpty()) {
                return $this->requiredSystemPageProtectionResponse($pages, 'move to trash');
            }
            $causes = $this->activeDonationCauses($uuids, true);
            if ($causes->isNotEmpty()) {
                $dependencyResponse = $this->resolveDonationDependenciesForTrash(
                    $request,
                    $pages,
                    $causes,
                    $forceUnpublishDependencies
                );
                if ($dependencyResponse !== null) {
                    return $dependencyResponse;
                }
            }

            $unpublished = $this->unpublishDonationDependencies($request, $causes, $uuids);
            $deleted = 0;
            foreach ($pages as $page) {
                app(PageRevisionService::class)->capture($page, 'Before page moved to trash by bulk action');
                SeoMetadata::where('seoable_type', Page::class)->where('seoable_id', $page->id)->delete();
                $page->delete();
                $deleted++;
            }

            return response()->json([
                'message' => $deleted . ' page version' . ($deleted === 1 ? '' : 's') . ' moved to trash.'
                    . ($unpublished > 0
                        ? ' ' . $unpublished . ' donation cause' . ($unpublished === 1 ? ' was' : 's were') . ' unpublished.'
                        : ''),
                'deleted_versions' => $deleted,
                'unpublished_donation_causes' => $unpublished,
            ]);
        });
    }

    public function create(Request $request)
    {
        $title = 'Create a page draft';
        $locales = app(LocalizationManager::class)->editorLocales();
        $localeIds = $locales->pluck('id')->all();
        $categorylist = Category::where('status', 1)
            ->whereIn('language', $localeIds)
            ->where(function ($query) {
                $query->whereNull('type')
                    ->orWhere('type', '!=', 'category-services');
            })->orderBy('name', 'ASC')->get();
        $bannerList = Banner::where('type', 'banner-page')
            ->whereIn('language', $localeIds)
            ->where('status', 1)
            ->orderBy('name')
            ->get();
        $tags = Tag::where('status', 1)->orderBy('name', 'ASC')->get();

        return view('admin.page.add')->with(compact('title', 'categorylist', 'bannerList', 'locales', 'tags'));
    }

    public function store(Request $request)
    {
        if ($request->input('creation_mode') === 'guided' || !is_array($request->input('name'))) {
            return $this->storeGuidedDraft($request);
        }

        return $this->storeLegacyDrafts($request);
    }

    private function storeGuidedDraft(Request $request)
    {
        $localeIds = app(LocalizationManager::class)->editorLocales()->pluck('id')->all();
        $locale = (string) $request->input('language');

        $data = $request->validate([
            'creation_mode' => ['nullable', 'in:guided'],
            'language' => ['required', 'string', 'max:10', Rule::in($localeIds)],
            'name' => ['required', 'string', 'max:255'],
            'sub_title' => ['nullable', 'string', 'max:2000'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('status', 1)
                    ->where('language', $locale)
                    ->whereNull('deleted_at')
                    ->where(fn ($types) => $types->whereNull('type')->orWhere('type', '!=', 'category-services'))),
            ],
            'banner_id' => [
                'nullable',
                'integer',
                Rule::exists('banners', 'id')->where(fn ($query) => $query
                    ->where('status', 1)
                    ->where('language', $locale)
                    ->whereNull('deleted_at')
                    ->where('type', 'banner-page')),
            ],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => [
                'integer',
                'distinct',
                Rule::exists('tags', 'id')->where(fn ($query) => $query
                    ->where('status', 1)
                    ->whereNull('deleted_at')),
            ],
        ], [
            'language.required' => 'Choose the language you will write this page in.',
            'name.required' => 'Enter a clear page title.',
            'category_id.exists' => 'Choose an active category for the selected language.',
            'banner_id.exists' => 'Choose an active banner for the selected language.',
            'tags.*.exists' => 'Choose active projects only.',
        ]);

        try {
            $page = DB::transaction(function () use ($data): Page {
                $uuid = Seq::uuidV4();
                $tagIds = collect($data['tags'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
                $page = Page::create([
                    'uuid' => $uuid,
                    'name' => trim($data['name']),
                    'sub_title' => trim((string) ($data['sub_title'] ?? '')),
                    'slug' => $this->uniqueDraftSlug($data['name'], $data['language'], $uuid),
                    'category_id' => $data['category_id'] ?? null,
                    'banner_id' => $data['banner_id'] ?? null,
                    'description' => null,
                    'inline_css' => null,
                    'language' => $data['language'],
                    'published_at' => null,
                    'order_by' => null,
                    'name_enabled' => 1,
                    'sub_title_enabled' => filled($data['sub_title'] ?? null) ? 1 : 0,
                    'is_relationship' => $tagIds->isNotEmpty() ? 1 : 0,
                    'status' => 0,
                    'publication_status' => 'draft',
                    'visibility' => 'public',
                ]);

                $this->logicalTags->sync($page, $tagIds->all());

                app(PageRevisionService::class)->capture($page, 'Initial snapshot from guided page creation');

                return $page;
            });

            return redirect(route('page.builder.edit', [
                'uuid' => $page->uuid,
                'locale' => $page->language,
            ]))->with([
                'message' => 'Draft created. Add the page sections below, then preview it before publishing.',
                'alert-type' => 'success',
            ]);
        } catch (Exception $e) {
            report($e);

            return back()->withInput()->with([
                'message' => 'The draft could not be created. Please review the details and try again.',
                'alert-type' => 'error',
            ]);
        }
    }

    private function storeLegacyDrafts(Request $request)
    {
        $request->validate([
            'language' => ['required', 'array', 'min:1', 'max:10'],
            'language.*' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9_-]+$/', 'distinct'],
            'thumbnail.*' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp', 'max:500'],
            'name' => ['required', 'array'],
            'name.*' => ['required', new ValidateUniqueRule('pages'), 'nullable'],
        ]);

        $languages = array_values(array_unique(array_filter((array) $request->input('language'))));
        foreach ($languages as $language) {
            $request->validate(["name.{$language}" => ['required', 'string', 'max:255']]);
        }

        $stagedAssets = [];
        $committed = false;
        try {
            DB::beginTransaction();
            $uuid = Seq::uuidV4();
            $names = (array) $request->input('name');
            $primaryLanguage = in_array('en', $languages, true) ? 'en' : $languages[0];
            $slug = $this->uniqueDraftSlug((string) $names[$primaryLanguage], $primaryLanguage, $uuid);
            $logicalTagIds = collect($request->input('tags', []))
                ->flatten()
                ->map(fn ($tagId): int => (int) $tagId)
                ->filter(fn (int $tagId): bool => $tagId > 0)
                ->unique()
                ->values();
            $createdPages = collect();

            foreach ($languages as $language) {
                $description = $this->sanitizer->sanitizeHtml(
                    StaticUtil::pageRemoveNewLine(@$request->description[$language])
                );
                $inline_css = $this->sanitizer->sanitizeCss(
                    StaticUtil::pageRemoveNewLine(@$request->inline_css[$language])
                );
                $published_date = @$request->published_at[$language];

                if (!$published_date) {
                    $published_date = date("Y-m-d");
                }
                $published_at = date('Y-m-d', strtotime(@$published_date));
                $page = Page::create([
                    'uuid' => $uuid,
                    'name' => $request->name[$language],
                    'sub_title' => (string) data_get($request->input('sub_title', []), $language, ''),
                    'slug' => $slug,
                    'category_id' => @$request->category_id[$language],
                    'banner_id' => @$request->banner_id[$language],
                    'description' => @$description,
                    'inline_css' => @$inline_css,
                    'language' => $language,
                    'published_at' => @$published_at,
                    'publish_by' => @$request->publish_by[$language],
                    'order_by' => @$request->order_by[$language],
                    'name_enabled' => @$request->name_enabled[$language] ?? 1,
                    'sub_title_enabled' => @$request->sub_title_enabled[$language] ?? 1,
                    'is_relationship' => $logicalTagIds->isNotEmpty(),
                    'status' => 0,
                    'publication_status' => 'draft',
                ]);

                $thumbnail = $request->file("thumbnail.{$language}");
                if ($thumbnail) {
                    $asset = $this->media->stageResizedPublicImage($thumbnail, 'page', 410, 240);
                    $stagedAssets[] = $asset;
                    $page->update([
                        'thumbnail' => $asset->databaseValue,
                        'path' => $asset->databaseValue,
                    ]);
                }

                $createdPages->push($page);
            }

            if ($createdPages->isNotEmpty()) {
                $this->logicalTags->sync($createdPages->first(), $logicalTagIds->all());
                foreach ($createdPages as $createdPage) {
                    app(PageRevisionService::class)->capture(
                        $createdPage,
                        'Initial snapshot from legacy page creation'
                    );
                }
            }

            DB::commit();
            $committed = true;

            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success',
            );
            if (@$request->save_and_update) {
                return redirect(route('page.edit', $uuid))->with($notification);
            } else {
                return redirect(route('page.index'))->with($notification);
            }
        } catch (Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if (!$committed) {
                $this->media->discardMany($stagedAssets);
            }
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }

    private function uniqueDraftSlug(string $title, string $language, string $uuid): string
    {
        $base = \Illuminate\Support\Str::slug(trim($title));
        if ($base === '') {
            $base = trim((string) preg_replace('/[^\pL\pN]+/u', '-', mb_strtolower(trim($title), 'UTF-8')), '-');
        }
        $base = mb_substr($base !== '' ? $base : 'page', 0, 220);

        if (!Page::withTrashed()->where('language', $language)->where('slug', $base)->exists()) {
            return $base;
        }

        $suffix = substr(str_replace('-', '', $uuid), 0, 8);
        $candidate = rtrim(mb_substr($base, 0, 246), '-') . '-' . $suffix;
        $counter = 2;
        while (Page::withTrashed()->where('language', $language)->where('slug', $candidate)->exists()) {
            $tail = '-' . $suffix . '-' . $counter++;
            $candidate = rtrim(mb_substr($base, 0, 255 - mb_strlen($tail)), '-') . $tail;
        }

        return $candidate;
    }

    public function edit($id = null, Request $request)
    {
        $title = $request->Lang->Menu->Page . " " . $request->Lang->Common->Update;
        try {
            $translations = Translation::languageList();
            $bannerList = Banner::whereIN('type', ['banner-home', 'banner-page'])->where('status', 1)->get();

            $pages =  Page::with('pageTags')
                ->where('uuid', $id)
                ->get();
            $this->logicalTags->hydrate($pages);
            $editorVersion = (int) $pages->max('editor_version');

            $categorylist = Category::select('categories.*')
                ->where(function ($query) {
                    $query->whereNull('type')
                        ->orWhere('type', '!=', 'category-services');
                })
                ->where('status', 1)->get();

            $tags = Tag::where('status', 1)->orderBy('name', 'ASC')->get();

            return view('admin.page.edit')->with(compact('title', 'id', 'pages', 'editorVersion', 'categorylist', 'bannerList', 'translations', 'tags'));
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->DataNotFound], 403);
        }
    }

    public function update(Request $request)
    {
        $this->validate(request(), [
            'uuid' => ['required', 'string', 'max:255'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'language' => ['required', 'array', 'min:1'],
            'language.*' => ['required', 'string', 'max:10', 'distinct'],
            'name.*' =>  ['required', new ValidateUniqueRule('pages|uuid,' . $request->uuid), 'nullable'],
            'thumbnail.*' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp', 'max:500'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'array', 'max:50'],
            'tags.*.*' => [
                'integer',
                Rule::exists('tags', 'id')->where(fn ($query) => $query
                    ->where('status', 1)
                    ->whereNull('deleted_at')),
            ],
        ]);

        $stagedAssets = [];
        $oldImages = [];
        $committed = false;
        try {
            $uuid = $request->uuid;
            DB::beginTransaction();

            // Resolve rows only through the request's logical page UUID and
            // locale. Hidden numeric ids are presentation data and must never
            // be allowed to move an unrelated row into this logical page.
            $pageLocks = $this->editorVersions->lockForMutation([$uuid]);
            $this->editorVersions->assertExpected(
                $pageLocks,
                $uuid,
                'en',
                (int) $request->integer('expected_version')
            );
            $logicalPages = $pageLocks
                ->get($uuid, collect())
                ->filter(fn (Page $page): bool => !$page->trashed())
                ->values();
            $find_page = $logicalPages->firstWhere('language', 'en');
            if (!$find_page) {
                throw ValidationException::withMessages([
                    'uuid' => 'The English source page no longer exists.',
                ]);
            }
            $logicalTagIds = collect($request->input('tags', []))
                ->flatten()
                ->map(fn ($tagId): int => (int) $tagId)
                ->filter(fn (int $tagId): bool => $tagId > 0)
                ->unique()
                ->values();
            foreach ($logicalPages as $logicalPage) {
                app(PageRevisionService::class)->capture($logicalPage, 'Before legacy page editor update');
            }

            foreach ($request->language as $language) {
                $page = $logicalPages->firstWhere('language', $language);
                $description = $this->sanitizer->sanitizeHtml(
                    StaticUtil::pageRemoveNewLine(@$request->description[$language])
                );
                $inline_css = $this->sanitizer->sanitizeCss(
                    StaticUtil::pageRemoveNewLine(@$request->inline_css[$language])
                );
                $published_at = date('Y-m-d', strtotime(@$request->published_at[$language]));
                $published_date = @$request->published_at[$language];

                if ($page) {
                    $page->update([
                        'name' => $request->name[$language],
                        'sub_title' => (string) data_get($request->input('sub_title', []), $language, ''),
                        'category_id' => @$request->category_id[$language],
                        'banner_id' => @$request->banner_id[$language],
                        'description' => @$description,
                        'inline_css' => @$inline_css,
                        'published_at' => @$published_at,
                        'publish_by' => @$request->publish_by[$language],
                        'order_by' => @$request->order_by[$language],
                        'name_enabled' => @$request->name_enabled[$language] ?? 0,
                        'sub_title_enabled' => @$request->sub_title_enabled[$language] ?? 0,
                        'is_relationship' => @$request->is_relationship[$language] ?? 0
                    ]);

                    if ($request->hasFile('thumbnail') && @$request->thumbnail[$language]) {
                        $asset = $this->media->stageResizedPublicImage(
                            $request->file('thumbnail')[$language],
                            'page',
                            410,
                            240,
                        );
                        $stagedAssets[] = $asset;
                        $oldImages[] = [$page->thumbnail, $page->path];
                        $page->update([
                            'thumbnail' => $asset->databaseValue,
                            'path' => $asset->databaseValue,
                        ]);
                    }

                } else {
                    if (!$published_date) {
                        $published_date = date("Y-m-d");
                    }
                    $published_at = date('Y-m-d', strtotime(@$published_date));
                    $page = Page::create([
                        'uuid' => $uuid,
                        'name' => $request->name[$language],
                        'sub_title' => (string) data_get($request->input('sub_title', []), $language, ''),
                        'slug' => $find_page->slug,
                        'category_id' => @$request->category_id[$language],
                        'banner_id' => @$request->banner_id[$language],
                        'description' => @$description,
                        'inline_css' => @$inline_css,
                        'language' => $language,
                        'published_at' => @$published_at,
                        'publish_by' => @$request->publish_by[$language],
                        'order_by' => @$request->order_by[$language],
                        'name_enabled' => @$request->name_enabled[$language] ?? 1,
                        'sub_title_enabled' => @$request->sub_title_enabled[$language] ?? 1,
                        'is_relationship' => @$request->is_relationship[$language] ?? 0,
                        // A translation shares the logical project UUID, so it
                        // inherits (but cannot independently edit) the global
                        // funding controls.
                        'is_funding_project' => (bool) $find_page->is_funding_project,
                        'is_zakat_eligible' => (bool) $find_page->is_zakat_eligible,
                        'status' => 0,
                    ]);
                    $logicalPages->push($page);

                    if ($request->hasFile('thumbnail') && @$request->thumbnail[$language]) {
                        $asset = $this->media->stageResizedPublicImage(
                            $request->file('thumbnail')[$language],
                            'page',
                            410,
                            240,
                        );
                        $stagedAssets[] = $asset;
                        $page->update([
                            'thumbnail' => $asset->databaseValue,
                            'path' => $asset->databaseValue,
                        ]);
                    }

                }
            }
            $this->logicalTags->sync($find_page, $logicalTagIds->all());
            $this->editorVersions->advanceLocked($pageLocks, [$uuid]);
            DB::commit();
            $committed = true;
            foreach ($oldImages as $names) {
                $this->media->deleteLegacyFlatImages('page', $names);
            }

            $notification = array(
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success',
            );

            if (@$request->save_and_update) {
                return redirect(route('page.edit', $uuid))->with($notification);
            } else {
                return redirect(route('page.index'))->with($notification);
            }
        } catch (Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if (!$committed) {
                $this->media->discardMany($stagedAssets);
            }
            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 409) {
                throw $e;
            }

            $notification = array(
                'message' => $request->Lang->Common->Form->NotUpdate,
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }

    public function view(Request $request, $id = null)
    {
        if ($request->query->has('search')) {
            return redirect()->route('page.view', [
                'id' => $id,
                'order_by' => $request->query('order_by'),
                'status' => $request->query('status'),
            ]);
        }

        $title = ($request->Lang->Menu->Page ?? 'Page') . ' ' . ($request->Lang->Common->Comment ?? 'comments');
        $order_by = $request->string('order_by')->toString();
        $status = $request->string('status')->toString();
        abort_unless(in_array($order_by, ['', '0', '1'], true), 422);
        abort_unless(in_array($status, ['', '1', '2'], true), 422);

        $page = Page::query()
            ->where('uuid', $id)
            ->where('language', app()->getLocale())
            ->firstOrFail();
        $search = $this->privateSearch->current($request, 'page-comments:' . $page->uuid);

        $comments = Comment::query()
            ->select('id', 'text', 'page_id', 'user_id', 'status')
            ->selectRaw('(SELECT count(id) FROM likes where comment_id = comments.id) as total_like')
            ->when($search !== '', fn ($query) => $query->where('text', 'like', '%' . $search . '%'))
            ->when($status === '1', fn ($query) => $query->where('status', 1))
            ->when($status === '2', fn ($query) => $query->where('status', 0))
            ->where('comments.is_delete', '!=', 1)
            ->where('page_id', $page->id)
            ->orderBy('id', $order_by === '1' ? 'ASC' : 'DESC')
            ->paginate(15);

        $permission = app(Permission::class);
        $canModerate = $permission->allows($request->user('admin'), 'comment.publish');
        $canToggleComments = $permission->allows($request->user('admin'), 'page.status');

        return view('admin.page.view')->with(compact(
            'title', 'page', 'comments', 'search', 'order_by', 'status', 'canModerate', 'canToggleComments'
        ));
    }

    public function statusIsComment(Request $request, int $id)
    {
        $validated = $request->validate([
            'is_comment' => ['required', 'boolean'],
        ]);
        try {
            $data = DB::transaction(function () use ($id, $validated): Page {
                $uuid = (string) Page::query()->whereKey($id)->value('uuid');
                abort_if($uuid === '', 404);
                $pageLocks = $this->editorVersions->lockForMutation([$uuid]);
                $pages = $pageLocks->get($uuid, collect());
                $page = $pages->firstWhere('id', $id);
                abort_unless($page instanceof Page && !$page->trashed(), 404);
                $page->update(['is_comment' => (bool) $validated['is_comment']]);
                $this->editorVersions->advanceLocked($pageLocks, [$uuid]);

                return $page->fresh();
            });

            return response()->json([
                'message' => $data->is_comment ? 'Visitor comments enabled.' : 'Visitor comments disabled.',
                'is_comment' => (bool) $data->is_comment,
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => 'The page comment setting could not be changed.'], 409);
        }
    }

    public function statusComment(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:comments,id'],
        ]);
        try {
            $data = DB::transaction(function () use ($validated): Comment {
                $comment = Comment::query()
                    ->whereKey($validated['id'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $comment->update(['status' => !(bool) $comment->status]);

                return $comment->fresh();
            });

            $notification = array(
                'message' => ($data->status ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully),
                'alert-type' => 'success',
            );
            return back()->with($notification);
        } catch (Exception $e) {
            $notification = array(
                'message' => $request->Lang->Common->Form->NotFound,
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }

    public function status(Request $request, $id = null)
    {
        try {
            if ($request->ajax()) {
                return DB::transaction(function () use ($request, $id) {
                    $uuid = (string) $id;
                    $pageLocks = $this->editorVersions->lockForMutation([$uuid]);
                    $pages = $pageLocks
                        ->get($uuid, collect())
                        ->filter(fn (Page $page): bool => !$page->trashed())
                        ->values();
                    $data = $pages->firstWhere('language', 'en') ?? $pages->first();
                    if (!$data) {
                        return response(['message' => $request->Lang->Common->Form->NotFound], 404);
                    }
                    if ($data->status && $this->requiredSystemPageNames($pages)->isNotEmpty()) {
                        return $this->requiredSystemPageProtectionResponse($pages, 'unpublished');
                    }
                    if ($data->status && $this->hasActiveDonationCause((string) $data->uuid, true)) {
                        return response([
                            'message' => 'An active donation cause sends gifts directly to this page. Reassign or unpublish the cause before unpublishing the page.',
                        ], 422);
                    }
                    $nextStatus = !$data->status;
                    Page::where('uuid', $id)->update([
                        'status' => $nextStatus,
                        'publication_status' => $nextStatus ? 'published' : 'draft',
                        'last_published_at' => $nextStatus ? now() : $data->last_published_at,
                        'published_by' => $nextStatus ? auth('admin')->id() : $data->published_by,
                        'scheduled_for' => null,
                    ]);
                    $this->editorVersions->advanceLocked($pageLocks, [$uuid]);
                    return response(['message' => ($nextStatus ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully)], 200);
                });
            }
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotUpdate], 403);
        }
    }

    public function destroy($id = null, Request $request)
    {
        $data = $request->validate([
            'force_unpublish_dependencies' => ['sometimes', 'boolean'],
        ]);
        $forceUnpublishDependencies = (bool) ($data['force_unpublish_dependencies'] ?? false);

        try {
            return DB::transaction(function () use ($id, $request, $forceUnpublishDependencies) {
                $pages = Page::where('uuid', $id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                abort_if($pages->isEmpty(), 404);
                if ($this->requiredSystemPageNames($pages)->isNotEmpty()) {
                    return $this->requiredSystemPageProtectionResponse($pages, 'move to trash');
                }
                $pageUuids = collect([(string) $id]);
                $causes = $this->activeDonationCauses($pageUuids, true);
                if ($causes->isNotEmpty()) {
                    $dependencyResponse = $this->resolveDonationDependenciesForTrash(
                        $request,
                        $pages,
                        $causes,
                        $forceUnpublishDependencies
                    );
                    if ($dependencyResponse !== null) {
                        return $dependencyResponse;
                    }
                }

                $unpublished = $this->unpublishDonationDependencies($request, $causes, $pageUuids);
                foreach ($pages as $page) {
                    app(PageRevisionService::class)->capture($page, 'Before page moved to trash');
                    SeoMetadata::where('seoable_type', Page::class)
                        ->where('seoable_id', $page->id)
                        ->delete();
                    $page->delete();
                }

                return response([
                    'message' => $request->Lang->Common->Form->DeleteSuccessfully
                        . ($unpublished > 0
                            ? ' ' . $unpublished . ' donation cause' . ($unpublished === 1 ? ' was' : 's were') . ' unpublished.'
                            : ''),
                    'deleted_versions' => $pages->count(),
                    'unpublished_donation_causes' => $unpublished,
                ], 200);
            });
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

    public function trash(Request $request)
    {
        $title = 'Page trash';
        $search = $request->search;
        $pages = Page::onlyTrashed()
            ->where('language', app()->getLocale())
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('slug', 'like', '%' . $search . '%');
                }
            })
            ->latest('deleted_at')
            ->paginate(15);

        return view('admin.page.trash')->with(compact('title', 'pages', 'search'));
    }

    public function restore($id = null, Request $request)
    {
        try {
            DB::transaction(function () use ($id) {
                $uuid = (string) $id;
                $pageLocks = $this->editorVersions->lockForMutation([$uuid]);
                $pages = $pageLocks
                    ->get($uuid, collect())
                    ->filter(fn (Page $page): bool => $page->trashed())
                    ->values();
                abort_if($pages->isEmpty(), 404);

                foreach ($pages as $page) {
                    $page->restore();
                    SeoMetadata::onlyTrashed()
                        ->where('seoable_type', Page::class)
                        ->where('seoable_id', $page->id)
                        ->restore();
                }
                $this->editorVersions->advanceLocked($pageLocks, [$uuid]);
            });

            return response(['message' => 'Page restored successfully.'], 200);
        } catch (Exception $e) {
            return response(['message' => 'The page could not be restored.'], 403);
        }
    }

    public function forceDestroy($id = null, Request $request)
    {
        try {
            return DB::transaction(function () use ($id) {
                $pages = Page::onlyTrashed()
                    ->where('uuid', $id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                abort_if($pages->isEmpty(), 404);
                if ($this->requiredSystemPageNames($pages)->isNotEmpty()) {
                    return $this->requiredSystemPageProtectionResponse($pages, 'permanently delete');
                }
                if ($this->hasActiveDonationCause((string) $id, true)) {
                    return response([
                        'message' => 'An active donation cause sends gifts directly to this page. Reassign or unpublish the cause before permanently deleting the page.',
                    ], 422);
                }

                foreach ($pages as $page) {
                    PageTagModule::where('page_id', $page->id)->delete();
                    SeoMetadata::withTrashed()
                        ->where('seoable_type', Page::class)
                        ->where('seoable_id', $page->id)
                        ->forceDelete();
                    $page->forceDelete();
                }

                return response(['message' => 'Page permanently deleted.'], 200);
            });
        } catch (Exception $e) {
            return response(['message' => 'The page could not be permanently deleted.'], 403);
        }
    }

    public function thumbnail($img = null)
    {
        return IgfFile::image('/page/' . $img);
    }

    private function hasActiveDonationCause(string $uuid, bool $lock = false): bool
    {
        return $this->activeDonationCauses(collect([$uuid]), $lock)->isNotEmpty();
    }

    private function requiredSystemPageNames(Collection $pages): Collection
    {
        return $pages
            ->pluck('slug')
            ->filter(fn ($slug): bool => in_array((string) $slug, self::REQUIRED_SYSTEM_PAGE_SLUGS, true))
            ->unique()
            ->map(fn (string $slug): string => match ($slug) {
                'home' => 'Home',
                'about-us' => 'About us',
                'zakat' => 'Zakat',
            })
            ->values();
    }

    private function requiredSystemPageProtectionResponse(Collection $pages, string $action): JsonResponse
    {
        $names = $this->requiredSystemPageNames($pages);
        $subject = $names->count() === 1
            ? $names->first()
            : $names->join(', ', ' and ');

        return response()->json([
            'message' => $subject . ' ' . ($names->count() === 1 ? 'is a required website page' : 'are required website pages')
                . ' and cannot be ' . $action . '. You can still edit content, images, sections, and search settings normally.',
            'code' => 'required_system_page',
            'protected_pages' => $names->all(),
        ], 422);
    }

    private function activeDonationCauses(Collection $uuids, bool $lock = false): Collection
    {
        $pageUuids = $uuids
            ->map(fn ($uuid): string => trim((string) $uuid))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($pageUuids->isEmpty()) {
            return collect();
        }

        $query = DonationType::query()
            ->where('status', 1)
            ->where('destination_type', 'page')
            ->whereIn('destination_page_uuid', $pageUuids->all())
            ->orderBy('id');

        return $lock
            ? $query->lockForUpdate()->get()
            : $query->get();
    }

    private function resolveDonationDependenciesForTrash(
        Request $request,
        Collection $pages,
        Collection $causes,
        bool $forceRequested
    ): ?JsonResponse {
        $protected = $causes->filter(fn (DonationType $cause): bool => in_array(
            (string) $cause->purpose_key,
            DonationType::PROTECTED_PURPOSE_KEYS,
            true
        ));
        $actor = $request->user('admin') ?? auth('admin')->user();
        $canUnpublishCauses = app(Permission::class)->allows($actor, 'donationType.status');
        $forceAvailable = $protected->isEmpty() && $canUnpublishCauses;

        if (!$forceRequested) {
            return response()->json(
                $this->donationDependencyPayload(
                    $pages,
                    $causes,
                    $forceAvailable,
                    true,
                    $protected->isNotEmpty()
                        ? 'One or more selected pages is an active donation destination for a protected direct or Zakat cause. Reassign that cause before moving the page to trash.'
                        : ($canUnpublishCauses
                            ? 'One or more selected pages is an active donation destination. Confirm that the affected donation cause may be unpublished before moving the page to trash.'
                            : 'One or more selected pages is an active donation destination. An administrator with permission to publish or unpublish donation causes must confirm this cleanup.')
                ),
                422
            );
        }

        if ($protected->isNotEmpty()) {
            return response()->json(
                $this->donationDependencyPayload(
                    $pages,
                    $causes,
                    false,
                    false,
                    'One or more selected pages is an active donation destination for a protected direct or Zakat cause. Reassign that cause before moving the page to trash.'
                ),
                422
            );
        }

        if (!$canUnpublishCauses) {
            return response()->json([
                ...$this->donationDependencyPayload(
                    $pages,
                    $causes,
                    false,
                    false,
                    'You do not have permission to unpublish the donation causes that use this active donation destination.'
                ),
                'code' => 'donation_dependency_permission_required',
            ], 403);
        }

        return null;
    }

    private function donationDependencyPayload(
        Collection $pages,
        Collection $causes,
        bool $forceAvailable,
        bool $requiresConfirmation,
        string $message
    ): array {
        $logicalPages = $pages
            ->groupBy(fn (Page $page): string => (string) $page->uuid);
        $pageNames = $logicalPages->map(function (Collection $translations): string {
            $preferred = $translations->firstWhere('language', 'en') ?? $translations->first();

            return (string) ($preferred?->name ?? 'Page');
        });
        $protectedCount = $causes->filter(fn (DonationType $cause): bool => in_array(
            (string) $cause->purpose_key,
            DonationType::PROTECTED_PURPOSE_KEYS,
            true
        ))->count();

        return [
            'message' => $message,
            'code' => 'active_donation_destinations',
            'requires_confirmation' => $requiresConfirmation,
            'force_available' => $forceAvailable,
            'effect' => $forceAvailable
                ? 'Continuing will unpublish the listed donation causes and automatically remove the trashed pages from public managed cards. Donation and accounting history will be preserved.'
                : 'Reassign or unpublish the listed donation causes before moving these pages to trash. Donation and accounting history will be preserved.',
            'impact' => [
                'logical_pages' => $logicalPages->count(),
                'page_versions' => $pages->count(),
                'donation_causes_to_unpublish' => $causes->count() - $protectedCount,
                'protected_donation_causes' => $protectedCount,
            ],
            'dependencies' => [
                'donation_causes' => $causes->map(function (DonationType $cause) use ($pageNames): array {
                    $isProtected = in_array(
                        (string) $cause->purpose_key,
                        DonationType::PROTECTED_PURPOSE_KEYS,
                        true
                    );

                    return [
                        'id' => (int) $cause->id,
                        'uuid' => (string) $cause->uuid,
                        'name' => (string) $cause->name,
                        'slug' => (string) $cause->slug,
                        'purpose_key' => $cause->purpose_key ?: null,
                        'page_uuid' => (string) $cause->destination_page_uuid,
                        'page_name' => (string) ($pageNames->get((string) $cause->destination_page_uuid) ?? 'Page'),
                        'protected' => $isProtected,
                    ];
                })->values()->all(),
            ],
        ];
    }

    private function unpublishDonationDependencies(
        Request $request,
        Collection $causes,
        Collection $pageUuids
    ): int {
        if ($causes->isEmpty()) {
            return 0;
        }

        $actor = $request->user('admin') ?? auth('admin')->user();
        foreach ($causes as $cause) {
            $cause->status = false;
            $cause->save();
            app(AdminAuditService::class)->record(
                $actor,
                'donation_cause.unpublished_for_page_trash',
                $cause,
                ['status' => ['before' => true, 'after' => false]],
                [
                    'destination_page_uuid' => (string) $cause->destination_page_uuid,
                    'page_uuids' => $pageUuids->values()->all(),
                    'forced_dependency_cleanup' => true,
                ]
            );
        }

        return $causes->count();
    }
}
