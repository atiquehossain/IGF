<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Permission;
use App\Models\Banner;
use App\Models\Category;
use App\Models\DonationType;
use App\Models\Gallery;
use App\Models\LatestNews;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageRevision;
use App\Models\PageTagModule;
use App\Models\ReusableBlock;
use App\Models\MediaAsset;
use App\Models\Tag;
use App\Models\Testimonial;
use App\Services\PageRevisionService;
use App\Services\ContentSanitizer;
use App\Services\DonationDestinationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PageBuilderController extends Controller
{
    public function __construct(
        private PageRevisionService $revisions,
        private ContentSanitizer $sanitizer,
        private DonationDestinationService $destinations
    ) {
    }

    public function edit(string $uuid, Request $request)
    {
        $locale = $request->query('locale', app()->getLocale());
        [$page, $revisionReusableVersions] = DB::transaction(function () use ($uuid, $locale): array {
            // Hold the same canonical logical-Page lock used by every writer
            // while assembling the editable snapshot. The rendered fields,
            // blocks and generation therefore come from one state; a writer
            // cannot land between loading old content and issuing a new token.
            $logicalPages = Page::withTrashed()
                ->where('uuid', $uuid)
                ->orderBy('uuid')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $page = $logicalPages->first(fn (Page $candidate): bool =>
                !$candidate->trashed() && $candidate->language === $locale
            );
            abort_unless($page instanceof Page, 404);

            $page->load([
                'blocks',
                'pageTags.tag',
                'revisions' => fn ($query) => $query->limit(20),
            ]);

            $revisionReusableUuids = $page->revisions
                ->flatMap(fn (PageRevision $revision) => collect(data_get(
                    $revision->snapshot,
                    'reusable_blocks',
                    []
                ))->pluck('uuid'))
                ->filter(fn ($reusableUuid): bool => is_string($reusableUuid) && $reusableUuid !== '')
                ->unique()
                ->values();
            $revisionReusableIds = $revisionReusableUuids->isEmpty()
                ? collect()
                : ReusableBlock::withTrashed()
                    ->whereIn('uuid', $revisionReusableUuids)
                    ->pluck('id');
            $reusableIds = $page->blocks
                ->pluck('reusable_block_id')
                ->merge($revisionReusableIds)
                ->filter(fn ($id): bool => $id !== null && $id !== '')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->sort()
                ->values();
            $lockedReusableBlocks = $reusableIds->isEmpty()
                ? collect()
                : ReusableBlock::withTrashed()
                    ->whereIn('id', $reusableIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
            $page->blocks->each(function (PageBlock $block) use ($lockedReusableBlocks): void {
                $reusable = $block->reusable_block_id
                    ? $lockedReusableBlocks->get((int) $block->reusable_block_id)
                    : null;
                $block->setRelation('reusableBlock', $reusable && !$reusable->trashed() ? $reusable : null);
            });
            $lockedReusableBlocksByUuid = $lockedReusableBlocks->keyBy('uuid');
            $revisionReusableVersions = $page->revisions
                ->mapWithKeys(function (PageRevision $revision) use ($lockedReusableBlocksByUuid): array {
                    $uuids = collect(data_get($revision->snapshot, 'reusable_blocks', []))
                        ->pluck('uuid')
                        ->filter(fn ($reusableUuid): bool => is_string($reusableUuid) && $reusableUuid !== '')
                        ->unique()
                        ->values();

                    return [$revision->uuid => $uuids
                        ->mapWithKeys(fn (string $reusableUuid): array => [
                            $reusableUuid => $lockedReusableBlocksByUuid->has($reusableUuid)
                                ? (int) $lockedReusableBlocksByUuid->get($reusableUuid)->editor_version
                                : null,
                        ])
                        ->all()];
                });

            // Preserve safe convergence for legacy databases whose locale
            // rows may still carry different generations, without a second
            // post-snapshot query.
            $page->setAttribute('editor_version', (int) $logicalPages->max('editor_version'));

            return [$page, $revisionReusableVersions];
        }, 3);

        if ($page->slug === 'sponsor-a-child') {
            return redirect(route('site.settings.index', ['locale' => $page->language]) . '#settings-sponsor_page')
                ->with([
                    'message' => 'Sponsor-a-child uses the dedicated Sponsor customizer. Edit its wording, images and contribution amount there.',
                    'alert-type' => 'info',
                ]);
        }

        $page->blocks->each(fn (PageBlock $block) => $this->presentBlock($block));
        $locales = Page::where('uuid', $uuid)
            ->orderBy('language')
            ->get(['language', 'name']);

        $view = $request->query('mode') === 'advanced'
            ? 'admin.page.builder'
            : 'admin.page.builder-simple';

        $selectedThumbnailAsset = $this->thumbnailAssetForPage($page);
        $mediaAssets = MediaAsset::query()
            ->where('mime_type', 'like', 'image/%')
            ->latest()
            ->limit(120)
            ->get();
        $videoAssets = MediaAsset::query()
            ->whereIn('mime_type', ['video/mp4', 'video/webm'])
            ->latest()
            ->limit(120)
            ->get();
        if ($selectedThumbnailAsset && !$mediaAssets->contains('id', $selectedThumbnailAsset->id)) {
            $mediaAssets->prepend($selectedThumbnailAsset);
        }

        return view($view, [
            'title' => ($view === 'admin.page.builder' ? 'Page builder — ' : 'Simple editor — ') . $page->name,
            'page' => $page,
            'revisionReusableVersions' => $revisionReusableVersions,
            'locales' => $locales,
            'blockTypes' => config('page-builder.block_types'),
            'simpleSections' => config('page-builder.simple_sections'),
            'linkTargets' => $this->linkTargets($locale),
            'blockContentOptions' => $this->blockContentOptions($locale),
            'reusableBlocks' => ReusableBlock::query()
                ->where('is_enabled', true)
                ->whereIn('locale', ['*', $locale])
                ->orderBy('name')
                ->get(),
            'mediaAssets' => $mediaAssets,
            'videoAssets' => $videoAssets,
            'selectedThumbnailAssetUuid' => $selectedThumbnailAsset?->uuid,
            'canManageFundingEligibility' => app(Permission::class)
                ->allows(auth('admin')->user(), 'donationType.edit'),
            'pageCategories' => Category::query()
                ->where('language', $locale)
                ->where('status', 1)
                ->where(function ($query) {
                    $query->whereNull('type')->orWhere('type', '!=', 'category-services');
                })
                ->orderBy('name')
                ->get(['id', 'name']),
            'pageBanners' => $page->slug === 'home'
                ? collect()
                : Banner::query()
                    ->where('language', $locale)
                    ->where('status', 1)
                    ->where('type', 'banner-page')
                    ->orderBy('name')
                    ->get(['id', 'name', 'type']),
            'activeTags' => Tag::query()
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function saveSimple(string $uuid, Request $request)
    {
        $identity = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ]);
        $locale = $identity['locale'];
        $page = Page::where('uuid', $uuid)->where('language', $locale)->firstOrFail();

        $data = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'page' => ['nullable', 'array'],
            'page.name' => ['required_with:page', 'string', 'max:255'],
            'page.publication_status' => ['required_with:page', Rule::in(['draft', 'pending_review', 'scheduled', 'published', 'private'])],
            ...$this->pageMetadataRules('page.', $locale, $page->slug !== 'home'),
            'block' => ['nullable', 'array'],
            'block.uuid' => ['required_with:block', 'uuid'],
            'block.label' => ['nullable', 'string', 'max:255'],
            'block.content' => ['required_with:block', 'array'],
            'block.is_enabled' => ['required_with:block', 'boolean'],
            'block.expected_reusable_version' => ['nullable', 'integer', 'min:0'],
            'blocks' => ['nullable', 'array', 'max:100'],
            'blocks.*.uuid' => ['required', 'uuid', 'distinct'],
            'blocks.*.label' => ['nullable', 'string', 'max:255'],
            'blocks.*.content' => ['required', 'array'],
            'blocks.*.is_enabled' => ['required', 'boolean'],
            'blocks.*.expected_reusable_version' => ['nullable', 'integer', 'min:0'],
            'order' => ['nullable', 'array'],
            'order.*' => ['required', 'uuid', 'distinct'],
        ]);
        $this->authorizePublicationChanges($page, [
            'publication_status' => $data['page']['publication_status'] ?? $page->publication_status,
        ]);
        if (!empty($data['page'])) {
            $this->authorizeFundingEligibilityChanges($page, $data['page']);
            $this->authorizeFixedGivingTargetAvailability($page, [
                'publication_status' => $data['page']['publication_status'],
                'visibility' => $data['page']['publication_status'] === 'published'
                    && $page->publication_status === 'private'
                    ? 'public'
                    : $page->visibility,
            ]);
        }
        if (empty($data['page']) && empty($data['block']) && empty($data['blocks']) && empty($data['order'])) {
            throw ValidationException::withMessages(['page' => 'Make a page or section change before saving.']);
        }

        $blockPayloads = collect($data['blocks'] ?? (!empty($data['block']) ? [$data['block']] : []));

        [$savedBlocks, $editorVersion] = DB::transaction(function () use ($uuid, $blockPayloads, $data) {
            $page = $this->lockPageForMutation($uuid, $data['locale'], (int) $data['expected_version']);
            $lockedReusableBlocks = $this->revisions->lockReusableBlocksForPage($page);
            $blocks = $page->blocks()
                ->whereIn('uuid', $blockPayloads->pluck('uuid'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('uuid');
            if ($blocks->count() !== $blockPayloads->count()) {
                throw ValidationException::withMessages(['blocks' => 'One or more sections could not be found on this page.']);
            }
            $blocks->each(function (PageBlock $block) use ($lockedReusableBlocks): void {
                $block->setRelation('reusableBlock', $block->reusable_block_id
                    ? $lockedReusableBlocks->get((int) $block->reusable_block_id)
                    : null);
            });
            $blockUpdates = collect();
            foreach ($blockPayloads as $blockData) {
                $block = $blocks->get($blockData['uuid']);
                Validator::make([
                    'locale' => $data['locale'],
                    'expected_version' => $data['expected_version'],
                    'label' => $blockData['label'] ?? $block->resolvedLabel(),
                    'content' => $blockData['content'],
                    'is_enabled' => $blockData['is_enabled'],
                    'expected_reusable_version' => $blockData['expected_reusable_version'] ?? null,
                ], $this->blockRules(false))->validate();
                if ($block->type === 'ways_to_give') {
                    $this->validateWaysToGiveContent($blockData['content'], $data['locale'], 'blocks.' . $blockData['uuid'] . '.content');
                }
                if ($block->type === 'media_text') {
                    $this->validateMediaTextContent($blockData['content']);
                }

                $attributes = [
                    'label' => trim($blockData['label'] ?? $block->resolvedLabel()),
                    'content' => $this->sanitizer->sanitizeBlockContent($blockData['content']),
                    'is_enabled' => $blockData['is_enabled'],
                    'updated_by' => auth('admin')->id(),
                ];
                $this->authorizeReusableBlockChanges($block, $attributes);
                $blockUpdates->put($block->uuid, $attributes);
            }
            if (!empty($data['order'])) {
                $lockedOrder = $page->blocks()->orderBy('id')->lockForUpdate()->get(['id', 'uuid'])->pluck('uuid');
                if ($lockedOrder->count() !== count($data['order'])
                    || $lockedOrder->diff($data['order'])->isNotEmpty()
                    || collect($data['order'])->diff($lockedOrder)->isNotEmpty()) {
                    throw ValidationException::withMessages(['order' => 'The section order must contain every section exactly once.']);
                }
            }
            if (!empty($data['page'])) {
                $lockedPublication = [
                    'publication_status' => $data['page']['publication_status'],
                    'visibility' => $data['page']['publication_status'] === 'published'
                        && $page->publication_status === 'private'
                        ? 'public'
                        : $page->visibility,
                ];
                $this->authorizePublicationChanges($page, $lockedPublication);
                $this->authorizeFundingEligibilityChanges($page, $data['page']);
                $this->authorizeFixedGivingTargetAvailability($page, $lockedPublication, true);
            }
            $sharedUpdates = collect();
            foreach ($blockPayloads as $blockData) {
                $block = $blocks->get($blockData['uuid']);
                $attributes = $blockUpdates->get($block->uuid);
                if (!$this->sharedReusableFieldsChanged($block, $attributes)) {
                    continue;
                }

                $reusable = $lockedReusableBlocks->get((int) $block->reusable_block_id);
                abort_unless($reusable && !$reusable->trashed(), 409, PageRevisionService::SHARED_CONFLICT_MESSAGE);
                $this->assertExpectedReusableVersion($reusable, $blockData['expected_reusable_version'] ?? null);
                $desired = [
                    'name' => $attributes['label'],
                    'content' => $attributes['content'],
                    'updated_by' => auth('admin')->id(),
                ];
                abort_if(
                    $sharedUpdates->has($reusable->id) && $sharedUpdates->get($reusable->id) != $desired,
                    422,
                    'The same reusable section cannot be saved with two different versions in one request.'
                );
                $sharedUpdates->put($reusable->id, $desired);
            }

            $this->revisions->capture($page, 'Before simple editor update', $lockedReusableBlocks);

            if (!empty($data['page'])) {
                $publicationStatus = $data['page']['publication_status'];
                $page->update(array_merge([
                    'name' => trim($data['page']['name']),
                    'status' => in_array($publicationStatus, ['published', 'scheduled'], true),
                    'publication_status' => $publicationStatus,
                    'visibility' => $publicationStatus === 'published' && $page->publication_status === 'private'
                        ? 'public'
                        : $page->visibility,
                    'scheduled_for' => $publicationStatus === 'scheduled' ? $page->scheduled_for : null,
                    'last_published_at' => $publicationStatus === 'published'
                        ? ($page->last_published_at ?: now())
                        : $page->last_published_at,
                    'published_by' => $publicationStatus === 'published'
                        ? auth('admin')->id()
                        : $page->published_by,
                ], $this->pageMetadataAttributes($data['page'], 'page.thumbnail_asset_uuid')));

                if (array_key_exists('tag_ids', $data['page'])) {
                    $this->syncPageTags($page, $data['page']['tag_ids'] ?? []);
                }
                $this->syncFundingEligibility($page, $data['page']);
            }

            foreach ($sharedUpdates as $reusableId => $attributes) {
                $reusable = $lockedReusableBlocks->get((int) $reusableId);
                $reusable->fill($attributes);
                $reusable->editor_version = ((int) $reusable->editor_version) + 1;
                $reusable->save();
            }
            foreach ($blockPayloads as $blockData) {
                $block = $blocks->get($blockData['uuid']);
                $attributes = $blockUpdates->get($block->uuid);
                $block->update($attributes);
            }

            foreach ($data['order'] ?? [] as $index => $blockUuid) {
                $page->blocks()->where('uuid', $blockUuid)->update([
                    'sort_order' => $index,
                    'updated_by' => auth('admin')->id(),
                ]);
            }

            $savedBlocks = $blocks
                ->map(fn (PageBlock $block) => $this->presentBlock($block->fresh('reusableBlock')))
                ->values();

            return [$savedBlocks, $this->advanceEditorVersion($page)];
        });

        $freshPage = $page->fresh('pageTags');

        return response()->json([
            'message' => 'Changes saved. A revision was created automatically.',
            'page' => [
                'name' => $freshPage->name,
                'publication_status' => $freshPage->publication_status,
                ...$this->pageMetadataPayload($freshPage),
            ],
            'block' => $savedBlocks->count() === 1 ? $savedBlocks->first() : null,
            'blocks' => $savedBlocks,
            'editor_version' => $editorVersion,
        ]);
    }

    public function storeMedia(string $uuid, Request $request)
    {
        $mediaKind = $request->input('media_kind', 'image');
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'media_kind' => ['sometimes', 'string', Rule::in(['image', 'video'])],
            'file' => [
                'required',
                'file',
                'max:20480',
                $mediaKind === 'video'
                    ? 'mimetypes:video/mp4,video/webm'
                    : 'mimetypes:image/jpeg,image/png,image/webp,image/gif',
            ],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        Page::where('uuid', $uuid)->where('language', $data['locale'])->firstOrFail();

        $file = $data['file'];
        $path = $file->store('media/' . now()->format('Y/m'), 'public');
        $width = null;
        $height = null;
        if (str_starts_with((string) $file->getMimeType(), 'image/')) {
            $dimensions = @getimagesize($file->getRealPath());
            $width = $dimensions[0] ?? null;
            $height = $dimensions[1] ?? null;
        }

        $asset = MediaAsset::create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'extension' => strtolower($file->getClientOriginalExtension()),
            'bytes' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'alt_text' => $data['alt_text'] ?? null,
            'locale' => $data['locale'],
            'uploaded_by' => auth('admin')->id(),
        ]);

        return response()->json([
            'message' => 'Media uploaded and selected.',
            'asset' => $asset,
        ], 201);
    }

    public function updatePage(string $uuid, Request $request)
    {
        abort_if($request->has('seo'), 403, 'Search & Sharing must be edited in its dedicated workspace.');

        $identity = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ]);
        $locale = $identity['locale'];
        $page = Page::where('uuid', $uuid)->where('language', $locale)->firstOrFail();

        $data = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'name' => ['required', 'string', 'max:255'],
            'sub_title' => ['nullable', 'string'],
            'status' => ['required', 'boolean'],
            'publication_status' => ['required', Rule::in(['draft', 'pending_review', 'scheduled', 'published', 'private'])],
            'visibility' => ['required', Rule::in(['public', 'unlisted', 'private'])],
            'scheduled_for' => ['nullable', 'date', 'required_if:publication_status,scheduled'],
            ...$this->pageMetadataRules('', $locale, $page->slug !== 'home'),
        ]);
        $this->authorizePublicationChanges($page, $data);
        $this->authorizeFundingEligibilityChanges($page, $data);
        $this->authorizeFixedGivingTargetAvailability($page, $data);

        $editorVersion = DB::transaction(function () use ($uuid, $data) {
            $page = $this->lockPageForMutation($uuid, $data['locale'], (int) $data['expected_version']);
            $lockedReusableBlocks = $this->revisions->lockReusableBlocksForPage($page);
            $this->authorizePublicationChanges($page, $data);
            $this->authorizeFundingEligibilityChanges($page, $data);
            $this->authorizeFixedGivingTargetAvailability($page, $data, true);
            $this->revisions->capture($page, 'Before page settings update', $lockedReusableBlocks);
            $isPublicCandidate = in_array($data['publication_status'], ['published', 'scheduled'], true);
            $pageAttributes = [
                'name' => $data['name'],
                'sub_title' => (string) ($data['sub_title'] ?? ''),
                'status' => $isPublicCandidate,
                'publication_status' => $data['publication_status'],
                'visibility' => $data['visibility'],
                'scheduled_for' => $data['publication_status'] === 'scheduled' ? $data['scheduled_for'] : null,
                'last_published_at' => $data['publication_status'] === 'published'
                    ? ($page->last_published_at ?: now())
                    : $page->last_published_at,
                'published_by' => $data['publication_status'] === 'published'
                    ? auth('admin')->id()
                    : $page->published_by,
            ];

            $page->update(array_merge(
                $pageAttributes,
                $this->pageMetadataAttributes($data, 'thumbnail_asset_uuid')
            ));

            if (array_key_exists('tag_ids', $data)) {
                $this->syncPageTags($page, $data['tag_ids'] ?? []);
            }
            $this->syncFundingEligibility($page, $data);

            return $this->advanceEditorVersion($page);
        });

        $freshPage = $page->fresh('pageTags');

        return response()->json([
            'message' => 'Page settings and publishing saved.',
            'page' => [
                'name' => $freshPage->name,
                'sub_title' => $freshPage->sub_title,
                'publication_status' => $freshPage->publication_status,
                'visibility' => $freshPage->visibility,
                'scheduled_for' => $freshPage->scheduled_for?->format('Y-m-d\TH:i'),
                ...$this->pageMetadataPayload($freshPage),
            ],
            'editor_version' => $editorVersion,
        ]);
    }

    public function storeBlock(string $uuid, Request $request)
    {
        $data = $this->validateBlock($request);
        if ($data['type'] === 'ways_to_give') {
            $this->validateWaysToGiveContent(
                $data['content'] ?? config('page-builder.default_content.ways_to_give', []),
                $data['locale']
            );
        }
        if ($data['type'] === 'media_text') {
            $this->validateMediaTextContent(
                $data['content'] ?? config('page-builder.default_content.media_text', [])
            );
        }

        [$block, $editorVersion] = DB::transaction(function () use ($uuid, $data) {
            $page = $this->lockPageForMutation($uuid, $data['locale'], (int) $data['expected_version']);
            $lockedReusableBlocks = $this->revisions->lockReusableBlocksForPage($page);
            $this->revisions->capture(
                $page,
                'Before adding a ' . $data['type'] . ' block',
                $lockedReusableBlocks
            );
            $content = $this->sanitizer->sanitizeBlockContent(
                $data['content'] ?? config('page-builder.default_content.' . $data['type'], [])
            );
            $content['section_presentation'] ??= (string) config(
                'page-builder.section_presentation_default',
                'standard'
            );

            $block = $page->blocks()->create([
                'uuid' => (string) Str::uuid(),
                'type' => $data['type'],
                'label' => $data['label'] ?? config('page-builder.block_types.' . $data['type']),
                'content' => $content,
                'settings' => $data['settings'] ?? [],
                'sort_order' => ((int) $page->blocks()->max('sort_order')) + 1,
                'is_enabled' => $data['is_enabled'] ?? true,
                'show_on_desktop' => $data['show_on_desktop'] ?? true,
                'show_on_mobile' => $data['show_on_mobile'] ?? true,
                'created_by' => auth('admin')->id(),
                'updated_by' => auth('admin')->id(),
            ]);

            return [$block, $this->advanceEditorVersion($page)];
        });

        return response()->json([
            'message' => 'Block added.',
            'block' => $this->presentBlock($block),
            'editor_version' => $editorVersion,
        ], 201);
    }

    public function updateBlock(string $uuid, string $blockUuid, Request $request)
    {
        $data = $this->validateBlock($request, false);
        [$block, $updatesSharedFields, $editorVersion] = DB::transaction(function () use ($uuid, $blockUuid, $data) {
            $page = $this->lockPageForMutation($uuid, $data['locale'], (int) $data['expected_version']);
            $lockedReusableBlocks = $this->revisions->lockReusableBlocksForPage($page);
            $block = $page->blocks()
                ->where('uuid', $blockUuid)
                ->lockForUpdate()
                ->firstOrFail();
            $reusable = null;
            if ($block->reusable_block_id) {
                $reusable = $lockedReusableBlocks->get((int) $block->reusable_block_id);
                $block->setRelation('reusableBlock', $reusable);
            }
            if ($block->type === 'ways_to_give' && array_key_exists('content', $data)) {
                $this->validateWaysToGiveContent($data['content'], $data['locale']);
            }
            if ($block->type === 'media_text' && array_key_exists('content', $data)) {
                $this->validateMediaTextContent($data['content']);
            }
            $attributes = [
                'label' => $data['label'] ?? $block->resolvedLabel(),
                'content' => array_key_exists('content', $data)
                    ? $this->sanitizer->sanitizeBlockContent($data['content'])
                    : $block->resolvedContent(),
                'settings' => $data['settings'] ?? $block->resolvedSettings(),
                'is_enabled' => $data['is_enabled'] ?? $block->is_enabled,
                'show_on_desktop' => $data['show_on_desktop'] ?? $block->show_on_desktop,
                'show_on_mobile' => $data['show_on_mobile'] ?? $block->show_on_mobile,
                'available_from' => array_key_exists('available_from', $data)
                    ? $data['available_from']
                    : $block->available_from,
                'available_until' => array_key_exists('available_until', $data)
                    ? $data['available_until']
                    : $block->available_until,
                'updated_by' => auth('admin')->id(),
            ];
            $updatesSharedFields = $this->authorizeReusableBlockChanges($block, $attributes);
            if ($updatesSharedFields) {
                abort_unless($reusable && !$reusable->trashed(), 409, PageRevisionService::SHARED_CONFLICT_MESSAGE);
                $this->assertExpectedReusableVersion($reusable, $data['expected_reusable_version'] ?? null);
            }
            $this->revisions->capture(
                $page,
                'Before updating block ' . $block->label,
                $lockedReusableBlocks
            );

            if ($updatesSharedFields) {
                $reusable->fill([
                    'name' => $attributes['label'],
                    'content' => $attributes['content'],
                    'settings' => $attributes['settings'],
                    'updated_by' => auth('admin')->id(),
                ]);
                $reusable->editor_version = ((int) $reusable->editor_version) + 1;
                $reusable->save();
            }

            $block->update($attributes);

            return [$block->fresh('reusableBlock'), $updatesSharedFields, $this->advanceEditorVersion($page)];
        });

        return response()->json([
            'message' => $block->reusable_block_id
                ? ($updatesSharedFields
                    ? 'Reusable section saved everywhere it is used.'
                    : 'Page placement saved. Shared content was unchanged.')
                : 'Block saved.',
            'block' => $this->presentBlock($block),
            'editor_version' => $editorVersion,
        ]);
    }

    public function duplicateBlock(string $uuid, string $blockUuid, Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'as_draft' => ['sometimes', 'boolean'],
        ]);

        [$copy, $editorVersion] = DB::transaction(function () use ($uuid, $blockUuid, $data) {
            $page = $this->lockPageForMutation($uuid, $data['locale'], (int) $data['expected_version']);
            $lockedReusableBlocks = $this->revisions->lockReusableBlocksForPage($page);
            $source = $page->blocks()->where('uuid', $blockUuid)->lockForUpdate()->firstOrFail();
            $this->revisions->capture(
                $page,
                'Before duplicating block ' . $source->label,
                $lockedReusableBlocks
            );
            $copy = $source->replicate();
            $copy->uuid = (string) Str::uuid();
            $copy->label = trim($source->label . ' copy');
            $copy->sort_order = ((int) $page->blocks()->max('sort_order')) + 1;
            $copy->is_enabled = ($data['as_draft'] ?? false) ? false : $source->is_enabled;
            $copy->created_by = auth('admin')->id();
            $copy->updated_by = auth('admin')->id();
            $copy->save();

            return [$copy, $this->advanceEditorVersion($page)];
        });

        return response()->json([
            'message' => 'Block duplicated.',
            'block' => $this->presentBlock($copy),
            'editor_version' => $editorVersion,
        ], 201);
    }

    public function promoteBlock(string $uuid, string $blockUuid, Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'name' => ['required', 'string', 'max:255'],
            'library_locale' => ['required', 'string', 'max:10'],
        ]);
        [$reusable, $block, $editorVersion] = DB::transaction(function () use ($uuid, $blockUuid, $data) {
            $page = $this->lockPageForMutation($uuid, $data['locale'], (int) $data['expected_version']);
            $lockedReusableBlocks = $this->revisions->lockReusableBlocksForPage($page);
            $block = $page->blocks()->where('uuid', $blockUuid)->lockForUpdate()->firstOrFail();
            $this->revisions->capture(
                $page,
                'Before converting block to a reusable section',
                $lockedReusableBlocks
            );
            $reusable = ReusableBlock::create([
                'uuid' => (string) Str::uuid(),
                'name' => $data['name'],
                'type' => $block->type,
                'locale' => $data['library_locale'],
                'content' => $this->sanitizer->sanitizeBlockContent($block->content ?? []),
                'settings' => $block->settings ?? [],
                'is_enabled' => true,
                'created_by' => auth('admin')->id(),
                'updated_by' => auth('admin')->id(),
            ]);
            $block->update([
                'reusable_block_id' => $reusable->id,
                'label' => $reusable->name,
                'updated_by' => auth('admin')->id(),
            ]);

            return [$reusable, $block->fresh('reusableBlock'), $this->advanceEditorVersion($page)];
        });

        return response()->json([
            'message' => 'Section added to the reusable library.',
            'reusable' => $reusable,
            'block' => $this->presentBlock($block),
            'editor_version' => $editorVersion,
        ], 201);
    }

    public function attachReusableBlock(string $uuid, Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'reusable_uuid' => ['required', 'uuid'],
        ]);

        [$block, $editorVersion] = DB::transaction(function () use ($uuid, $data) {
            $page = $this->lockPageForMutation($uuid, $data['locale'], (int) $data['expected_version']);
            $candidate = ReusableBlock::query()
                ->where('uuid', $data['reusable_uuid'])
                ->where('is_enabled', true)
                ->whereIn('locale', ['*', $data['locale']])
                ->firstOrFail();
            $lockedReusableBlocks = $this->revisions->lockReusableBlocksForPage(
                $page,
                [$candidate->id]
            );
            $reusable = $lockedReusableBlocks->get($candidate->id);
            abort_unless(
                $reusable
                    && !$reusable->trashed()
                    && $reusable->is_enabled
                    && in_array($reusable->locale, ['*', $data['locale']], true),
                409,
                'This reusable section changed or became unavailable. Reload the editor and try again.'
            );
            $this->revisions->capture(
                $page,
                'Before adding reusable section ' . $reusable->name,
                $lockedReusableBlocks
            );

            $block = $page->blocks()->create([
                'reusable_block_id' => $reusable->id,
                'uuid' => (string) Str::uuid(),
                'type' => $reusable->type,
                'label' => $reusable->name,
                'content' => $reusable->content,
                'settings' => $reusable->settings,
                'sort_order' => ((int) $page->blocks()->max('sort_order')) + 1,
                'is_enabled' => true,
                'show_on_desktop' => true,
                'show_on_mobile' => true,
                'created_by' => auth('admin')->id(),
                'updated_by' => auth('admin')->id(),
            ]);

            return [$block, $this->advanceEditorVersion($page)];
        });

        return response()->json([
            'message' => 'Reusable section added to the page.',
            'block' => $this->presentBlock($block->load('reusableBlock')),
            'editor_version' => $editorVersion,
        ], 201);
    }

    public function detachReusableBlock(string $uuid, string $blockUuid, Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ]);

        [$block, $editorVersion] = DB::transaction(function () use ($uuid, $blockUuid, $data) {
            $page = $this->lockPageForMutation($uuid, $data['locale'], (int) $data['expected_version']);
            $lockedReusableBlocks = $this->revisions->lockReusableBlocksForPage($page);
            $block = $page->blocks()
                ->where('uuid', $blockUuid)
                ->lockForUpdate()
                ->firstOrFail();
            $reusable = $block->reusable_block_id
                ? $lockedReusableBlocks->get((int) $block->reusable_block_id)
                : null;
            abort_unless($reusable && !$reusable->trashed(), 422, 'This section is not linked to the reusable library.');
            $block->setRelation('reusableBlock', $reusable);
            $this->revisions->capture(
                $page,
                'Before detaching reusable section ' . $block->label,
                $lockedReusableBlocks
            );
            $block->update([
                'reusable_block_id' => null,
                'label' => $reusable->name,
                'content' => $reusable->content ?? [],
                'settings' => $reusable->settings ?? [],
                'updated_by' => auth('admin')->id(),
            ]);

            return [$block->fresh(), $this->advanceEditorVersion($page)];
        });

        return response()->json([
            'message' => 'Section detached. Future edits apply only to this page.',
            'block' => $this->presentBlock($block),
            'editor_version' => $editorVersion,
        ]);
    }

    public function reorder(string $uuid, Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'blocks' => ['required', 'array'],
            'blocks.*' => ['required', 'uuid', 'distinct'],
        ]);
        $editorVersion = DB::transaction(function () use ($uuid, $data) {
            $page = $this->lockPageForMutation($uuid, $data['locale'], (int) $data['expected_version']);
            $lockedReusableBlocks = $this->revisions->lockReusableBlocksForPage($page);
            $lockedBlocks = $page->blocks()->orderBy('id')->lockForUpdate()->get(['id', 'uuid']);
            abort_unless(
                $lockedBlocks->pluck('uuid')->diff($data['blocks'])->isEmpty()
                    && collect($data['blocks'])->diff($lockedBlocks->pluck('uuid'))->isEmpty()
                    && $lockedBlocks->count() === count($data['blocks']),
                422,
                'The block order must contain every block on this page exactly once.'
            );
            $this->revisions->capture($page, 'Before reordering page blocks', $lockedReusableBlocks);
            foreach ($data['blocks'] as $index => $blockUuid) {
                $page->blocks()->where('uuid', $blockUuid)->update([
                    'sort_order' => $index,
                    'updated_by' => auth('admin')->id(),
                ]);
            }

            return $this->advanceEditorVersion($page);
        });

        return response()->json(['message' => 'Block order saved.', 'editor_version' => $editorVersion]);
    }

    public function destroyBlock(string $uuid, string $blockUuid, Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ]);

        $editorVersion = DB::transaction(function () use ($uuid, $blockUuid, $data) {
            $page = $this->lockPageForMutation($uuid, $data['locale'], (int) $data['expected_version']);
            $lockedReusableBlocks = $this->revisions->lockReusableBlocksForPage($page);
            $block = $page->blocks()->where('uuid', $blockUuid)->lockForUpdate()->firstOrFail();
            $this->revisions->capture(
                $page,
                'Before deleting block ' . $block->label,
                $lockedReusableBlocks
            );
            $block->delete();

            return $this->advanceEditorVersion($page);
        });

        return response()->json(['message' => 'Block moved to trash.', 'editor_version' => $editorVersion]);
    }

    public function restoreRevision(string $uuid, string $revisionUuid, Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'expected_reusable_versions' => ['sometimes', 'array'],
            'expected_reusable_versions.*' => ['nullable', 'integer', 'min:0'],
        ]);
        $this->authorizeFullRevisionRestore();

        $editorVersion = DB::transaction(function () use ($uuid, $revisionUuid, $data) {
            $page = $this->lockPageForMutation($uuid, $data['locale'], (int) $data['expected_version']);
            $revision = PageRevision::query()
                ->where('page_id', $page->id)
                ->where('uuid', $revisionUuid)
                ->lockForUpdate()
                ->firstOrFail();
            $this->revisions->restore($page, $revision, $data['expected_reusable_versions'] ?? []);

            return $this->advanceEditorVersion($page->fresh());
        });

        return response()->json(['message' => 'Revision restored.', 'editor_version' => $editorVersion]);
    }

    /**
     * A page revision is an atomic snapshot: it can include publishing state,
     * SEO and globally shared reusable content as well as local page blocks.
     * Require every owning capability so the restore endpoint cannot be used
     * to bypass the permissions of those dedicated workspaces.
     */
    private function authorizeFullRevisionRestore(): void
    {
        $permission = app(Permission::class);
        $admin = auth('admin')->user();
        $required = ['page.status', 'seo.metadata.edit', 'reusable-blocks.edit'];
        $missing = collect($required)
            ->reject(fn (string $capability) => $permission->allows($admin, $capability));

        abort_if(
            $missing->isNotEmpty(),
            403,
            'A full revision can change publishing, Search & Sharing, and shared sections. Ask an administrator with all three permissions to restore it.'
        );
    }

    private function validateBlock(Request $request, bool $requireType = true): array
    {
        $validated = $request->validate($this->blockRules($requireType));

        // Builder block types intentionally have different content keys. The
        // nested rules above validate known structured fields, while this keeps
        // ordinary fields such as heading/body in the sanitized block payload.
        if ($request->has('content')) {
            $validated['content'] = $request->input('content');
        }

        return $validated;
    }

    private function blockRules(bool $requireType = true): array
    {
        return [
            'locale' => ['required', 'string', 'max:10'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'expected_reusable_version' => ['nullable', 'integer', 'min:0'],
            'type' => [$requireType ? 'required' : 'sometimes', 'string', Rule::in(array_keys(config('page-builder.block_types')))],
            'label' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'array'],
            'content.autoplay' => ['sometimes', 'boolean'],
            'content.interval' => ['sometimes', 'integer', 'between:3000,20000'],
            'content.pause_on_hover' => ['sometimes', 'boolean'],
            'content.animation_enabled' => ['sometimes', 'boolean'],
            'content.animation_type' => ['sometimes', 'string', Rule::in(['count_up', 'fade_up', 'pop'])],
            'content.animation_duration' => ['sometimes', 'integer', 'between:300,5000'],
            'content.animation_delay' => ['sometimes', 'integer', 'between:0,1000'],
            'content.media_type' => ['sometimes', 'string', Rule::in(['image', 'video', 'youtube'])],
            'content.image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'content.image_alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content.video_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'content.youtube_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'content.poster' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'content.caption' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'content.image_position' => ['sometimes', 'string', Rule::in(['left', 'right'])],
            'content.content_source' => ['sometimes', 'string', Rule::in(
                collect(config('page-builder.automatic_sources', []))->flatMap(fn (array $sources) => array_keys($sources))->unique()->values()->all()
            )],
            'content.category_slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content.tag_slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content.sort' => ['sometimes', 'string', Rule::in(array_keys(config('page-builder.automatic_sort_options', [])))],
            'content.limit' => ['sometimes', 'integer', 'between:1,12'],
            'content.selection_mode' => ['sometimes', 'string', Rule::in(['automatic', 'manual'])],
            'content.presentation' => ['sometimes', 'string', Rule::in(['card_grid', 'focus_areas'])],
            'content.section_presentation' => [
                'sometimes',
                'string',
                Rule::in(array_keys(config('page-builder.section_presentations', []))),
            ],
            'content.layout' => ['sometimes', 'string', Rule::in(['single_cta', 'card_grid', 'banner'])],
            'content.project_uuid' => ['sometimes', 'nullable', 'uuid'],
            'content.link_label' => ['sometimes', 'nullable', 'string', 'max:80'],
            'content.selected_items' => ['sometimes', 'array', 'max:12'],
            'content.selected_items.*' => ['required', 'string', 'max:100', 'distinct'],
            'content.items' => ['sometimes', 'array', 'max:60'],
            'content.items.*' => ['required', 'array'],
            'content.items.*.kind' => ['sometimes', 'string', Rule::in(PageBlock::UPDATE_ITEM_KINDS)],
            'content.items.*.eyebrow' => ['sometimes', 'nullable', 'string', 'max:120'],
            'content.items.*.heading' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content.items.*.body' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'content.items.*.image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'content.items.*.image_alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content.items.*.url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'content.items.*.link_label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'content.items.*.icon' => ['sometimes', 'nullable', 'string', 'max:50'],
            'content.items.*.features' => ['sometimes', 'array', 'max:20'],
            'content.items.*.features.*' => ['required', 'string', 'max:255'],
            'content.item_link_label' => ['sometimes', 'nullable', 'string', 'max:80'],
            'content.view_all_label' => ['sometimes', 'nullable', 'string', 'max:80'],
            'content.view_all_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'content.empty_state' => ['sometimes', 'nullable', 'string', 'max:300'],
            'content.slides' => ['sometimes', 'array', 'min:1', 'max:8'],
            'content.slides.*' => ['required', 'array'],
            'content.slides.*.eyebrow' => ['nullable', 'string', 'max:120'],
            'content.slides.*.heading' => ['required', 'string', 'max:180'],
            'content.slides.*.body' => ['nullable', 'string', 'max:1200'],
            'content.slides.*.primary_label' => ['nullable', 'string', 'max:80'],
            'content.slides.*.primary_url' => ['nullable', 'string', 'max:2048'],
            'content.slides.*.secondary_label' => ['nullable', 'string', 'max:80'],
            'content.slides.*.secondary_url' => ['nullable', 'string', 'max:2048'],
            'content.slides.*.report_label' => ['nullable', 'string', 'max:120'],
            'content.slides.*.report_url' => ['nullable', 'string', 'max:2048'],
            'content.slides.*.image' => ['nullable', 'string', 'max:2048'],
            'content.slides.*.overlay_opacity' => ['required', 'integer', 'between:0,100'],
            'settings' => ['nullable', 'array'],
            'is_enabled' => ['nullable', 'boolean'],
            'show_on_desktop' => ['nullable', 'boolean'],
            'show_on_mobile' => ['nullable', 'boolean'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after_or_equal:available_from'],
        ];
    }

    private function presentBlock(PageBlock $block): PageBlock
    {
        $block->loadMissing('reusableBlock');
        $block->setAttribute('content', $this->sanitizer->sanitizeBlockContent($block->resolvedContent()));
        $block->setAttribute('settings', $block->resolvedSettings());
        $block->setAttribute('label', $block->resolvedLabel());
        $block->setAttribute('is_reusable', (bool) $block->reusable_block_id);
        $block->setAttribute('reusable_name', $block->reusableBlock?->name);
        $block->setAttribute('reusable_uuid', $block->reusableBlock?->uuid);
        $block->setAttribute('reusable_version', $block->reusableBlock
            ? (int) $block->reusableBlock->editor_version
            : null);
        $block->unsetRelation('reusableBlock');

        return $block;
    }

    private function pageMetadataRules(string $prefix, string $locale, bool $allowsPageBanner): array
    {
        return [
            $prefix . 'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) use ($locale) {
                    $query->where('status', 1)
                        ->where('language', $locale)
                        ->whereNull('deleted_at')
                        ->where(function ($categoryQuery) {
                            $categoryQuery->whereNull('type')->orWhere('type', '!=', 'category-services');
                        });
                }),
            ],
            $prefix . 'banner_id' => [
                Rule::prohibitedIf(!$allowsPageBanner),
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('banners', 'id')->where(function ($query) use ($locale) {
                    $query->where('status', 1)
                        ->where('language', $locale)
                        ->where('type', 'banner-page')
                        ->whereNull('deleted_at');
                }),
            ],
            $prefix . 'thumbnail_asset_uuid' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('media_assets', 'uuid')->where(function ($query) {
                    $query->where('mime_type', 'like', 'image/%')->whereNull('deleted_at');
                }),
            ],
            $prefix . 'tag_ids' => ['sometimes', 'array', 'max:50'],
            $prefix . 'tag_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('tags', 'id')->where(function ($query) {
                    $query->where('status', 1)->whereNull('deleted_at');
                }),
            ],
            $prefix . 'is_funding_project' => ['sometimes', 'boolean'],
            $prefix . 'is_zakat_eligible' => ['sometimes', 'boolean'],
        ];
    }

    private function pageMetadataAttributes(array $data, string $thumbnailErrorKey): array
    {
        $attributes = [];

        foreach (['category_id', 'banner_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = filled($data[$field]) ? (int) $data[$field] : null;
            }
        }

        if (array_key_exists('thumbnail_asset_uuid', $data)) {
            if (blank($data['thumbnail_asset_uuid'])) {
                $attributes['thumbnail'] = null;
            } else {
                $asset = MediaAsset::query()
                    ->where('uuid', $data['thumbnail_asset_uuid'])
                    ->where('mime_type', 'like', 'image/%')
                    ->first();

                if (!$asset) {
                    throw ValidationException::withMessages([
                        $thumbnailErrorKey => 'Choose an image that is still available in the Media Library.',
                    ]);
                }

                $attributes['thumbnail'] = $asset->url;
            }
        }

        return $attributes;
    }

    private function syncPageTags(Page $page, array $tagIds): void
    {
        $wanted = collect($tagIds)->map(fn ($tagId) => (int) $tagId)->unique()->values();
        $links = $page->pageTags()->orderBy('id')->get();
        $kept = collect();

        foreach ($links as $link) {
            $tagId = (int) $link->tag_id;
            if (!$wanted->contains($tagId) || $kept->contains($tagId)) {
                $link->delete();
                continue;
            }

            $kept->push($tagId);
        }

        foreach ($wanted->diff($kept) as $tagId) {
            PageTagModule::create([
                'uuid' => (string) Str::uuid(),
                'page_id' => $page->id,
                'tag_id' => $tagId,
            ]);
        }

        $page->unsetRelation('pageTags');
    }

    private function pageMetadataPayload(Page $page): array
    {
        $page->loadMissing('pageTags');

        return [
            'category_id' => filled($page->category_id) ? (int) $page->category_id : null,
            'banner_id' => filled($page->banner_id) ? (int) $page->banner_id : null,
            'thumbnail' => $page->getRawOriginal('thumbnail'),
            'thumbnail_asset_uuid' => $this->thumbnailAssetForPage($page)?->uuid,
            'tag_ids' => $page->pageTags->pluck('tag_id')->map(fn ($tagId) => (int) $tagId)->values()->all(),
            'is_funding_project' => (bool) $page->is_funding_project,
            'is_zakat_eligible' => (bool) $page->is_zakat_eligible,
        ];
    }

    private function thumbnailAssetForPage(Page $page): ?MediaAsset
    {
        $thumbnail = trim((string) $page->getRawOriginal('thumbnail'));
        if ($thumbnail === '') {
            return null;
        }

        $urlPath = (string) (parse_url($thumbnail, PHP_URL_PATH) ?: $thumbnail);
        $normalized = ltrim(str_replace('\\', '/', $urlPath), '/');
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        return MediaAsset::query()
            ->where('mime_type', 'like', 'image/%')
            ->whereIn('path', array_values(array_unique(array_filter([
                $thumbnail,
                ltrim($thumbnail, '/'),
                $normalized,
            ]))))
            ->first();
    }

    /**
     * Page content editors may save copy and layout, but publishing remains a
     * separate responsibility. Keep this check in the controller so a crafted
     * request cannot bypass the permission-aware builder controls.
     */
    private function authorizePublicationChanges(Page $page, array $requested): void
    {
        $requestedStatus = (string) ($requested['publication_status'] ?? $page->publication_status);
        $requestedVisibility = (string) ($requested['visibility'] ?? $page->visibility);
        $currentSchedule = $page->publication_status === 'scheduled'
            ? $page->scheduled_for?->format('Y-m-d\TH:i')
            : null;
        $requestedSchedule = null;
        if ($requestedStatus === 'scheduled') {
            $requestedSchedule = array_key_exists('scheduled_for', $requested)
                ? (filled($requested['scheduled_for'])
                    ? date('Y-m-d\TH:i', strtotime((string) $requested['scheduled_for']))
                    : null)
                : $currentSchedule;
        }

        $changesPublication = $requestedStatus !== (string) $page->publication_status
            || $requestedVisibility !== (string) $page->visibility
            || $requestedSchedule !== $currentSchedule;

        if (!$changesPublication) {
            return;
        }

        $canPublish = app(Permission::class)->allows(auth('admin')->user(), 'page.status');
        abort_unless($canPublish, 403, 'A publisher must change page status, visibility, or schedule. Your content edits were not saved.');
    }

    /**
     * Reusable-section copy is global even when it is edited from an individual
     * page. Local placement controls remain available to page editors, while a
     * shared label/content/settings change requires the library permission.
     */
    private function authorizeReusableBlockChanges(PageBlock $block, array $attributes): bool
    {
        $changesSharedFields = $this->sharedReusableFieldsChanged($block, $attributes);
        if (!$changesSharedFields) {
            return false;
        }

        $canEditReusableBlocks = app(Permission::class)->allows(auth('admin')->user(), 'reusable-blocks.edit');
        abort_unless(
            $canEditReusableBlocks,
            403,
            'This is a shared section. Your role can change its page placement, but not its shared label or content. Detach it for a page-only copy, or ask a Reusable Sections editor.'
        );

        return true;
    }

    private function sharedReusableFieldsChanged(PageBlock $block, array $attributes): bool
    {
        if (!$block->reusableBlock) {
            return false;
        }

        $requestedLabel = trim((string) ($attributes['label'] ?? ''));
        $labelChanged = array_key_exists('label', $attributes)
            && $requestedLabel !== trim($block->resolvedLabel())
            // Legacy PageBlock rows retain the label copied when they were
            // attached. Treating that old copy as an intentional rename lets
            // an otherwise content-only save roll the shared name backwards.
            && $requestedLabel !== trim((string) $block->label);
        $contentChanged = array_key_exists('content', $attributes)
            && $attributes['content'] != $this->sanitizer->sanitizeBlockContent($block->resolvedContent());
        $settingsChanged = array_key_exists('settings', $attributes)
            && $attributes['settings'] != $block->resolvedSettings();

        return $labelChanged || $contentChanged || $settingsChanged;
    }

    private function assertExpectedReusableVersion(ReusableBlock $reusableBlock, mixed $expectedVersion): void
    {
        abort_unless(
            is_numeric($expectedVersion)
                && (int) $expectedVersion === (int) $reusableBlock->editor_version,
            409,
            PageRevisionService::SHARED_CONFLICT_MESSAGE
        );
    }

    /** Funding classification changes who may receive restricted funds. */
    private function authorizeFundingEligibilityChanges(Page $page, array $requested): void
    {
        $fields = array_values(array_intersect(
            ['is_funding_project', 'is_zakat_eligible'],
            array_keys($requested)
        ));
        if ($fields === []) {
            return;
        }

        $fundingProject = array_key_exists('is_funding_project', $requested)
            ? (bool) $requested['is_funding_project']
            : (bool) $page->is_funding_project;
        $zakatEligible = array_key_exists('is_zakat_eligible', $requested)
            ? (bool) $requested['is_zakat_eligible']
            : (bool) $page->is_zakat_eligible;
        if ($zakatEligible && !$fundingProject) {
            throw ValidationException::withMessages([
                'is_funding_project' => 'A page must first be marked as a fundable program or project before it can receive Zakat.',
            ]);
        }

        $changesAnyTranslation = Page::withTrashed()
            ->where('uuid', $page->uuid)
            ->get($fields)
            ->contains(function (Page $translation) use ($requested, $fields): bool {
                foreach ($fields as $field) {
                    if ((bool) $translation->{$field} !== (bool) $requested[$field]) {
                        return true;
                    }
                }

                return false;
            });
        if (!$changesAnyTranslation) {
            return;
        }

        abort_unless(
            app(Permission::class)->allows(auth('admin')->user(), 'donationType.edit'),
            403,
            'Only a Donation Causes editor can change fundable-project or Zakat eligibility.'
        );
    }

    /**
     * Page UUID is the shared logical identity of every language row. Apply a
     * financial eligibility change to all translations in the same transaction.
     */
    private function syncFundingEligibility(Page $page, array $requested): void
    {
        $hasFunding = array_key_exists('is_funding_project', $requested);
        $hasZakat = array_key_exists('is_zakat_eligible', $requested);
        if (!$hasFunding && !$hasZakat) {
            return;
        }

        $fundingProject = $hasFunding
            ? (bool) $requested['is_funding_project']
            : (bool) $page->is_funding_project;
        $zakatEligible = $hasZakat
            ? (bool) $requested['is_zakat_eligible']
            : (bool) $page->is_zakat_eligible;
        Page::withTrashed()
            ->where('uuid', $page->uuid)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
        if (!$fundingProject || !$zakatEligible) {
            $fixedCauses = DonationType::query()
                ->where('status', 1)
                ->where('destination_type', 'page')
                ->where('destination_page_uuid', $page->uuid)
                ->when($fundingProject && !$zakatEligible, fn ($query) => $query->where('purpose_key', 'zakat'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (!$fundingProject && $fixedCauses->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'is_funding_project' => 'This page is the destination of an active donation cause. Reassign or unpublish that cause before removing fundable-project status.',
                ]);
            }
            if (!$zakatEligible && $fixedCauses->contains(fn (DonationType $cause): bool => $cause->purpose_key === 'zakat')) {
                throw ValidationException::withMessages([
                    'is_zakat_eligible' => 'This project is the destination of the active Zakat cause. Choose another Zakat destination before removing its eligibility.',
                ]);
            }
        }

        $updates = ['updated_by' => auth('admin')->id()];
        if ($hasFunding) {
            $updates['is_funding_project'] = $fundingProject;
        }
        if ($hasZakat) {
            $updates['is_zakat_eligible'] = $zakatEligible;
        }
        Page::withTrashed()
            ->where('uuid', $page->uuid)
            ->update($updates);
    }

    /**
     * A live cause that is fixed to one logical Page UUID must never become a
     * broken public promise through an ordinary page publishing change. The
     * cause must be reassigned or unpublished before its target is hidden.
     */
    private function authorizeFixedGivingTargetAvailability(
        Page $page,
        array $requested,
        bool $lockCauses = false
    ): void
    {
        $publicationStatus = (string) ($requested['publication_status'] ?? $page->publication_status);
        $visibility = (string) ($requested['visibility'] ?? $page->visibility);
        if ($publicationStatus === 'published' && $visibility === 'public') {
            return;
        }

        $causeQuery = DonationType::query()
            ->where('status', 1)
            ->where('destination_type', 'page')
            ->where('destination_page_uuid', $page->uuid);
        $hasActiveFixedCause = $lockCauses
            ? $causeQuery->orderBy('id')->lockForUpdate()->get(['id'])->isNotEmpty()
            : $causeQuery->exists();
        if (!$hasActiveFixedCause) {
            return;
        }

        throw ValidationException::withMessages([
            'publication_status' => 'This page is the fixed destination of an active donation cause. Keep it published and publicly visible, or reassign or unpublish that cause before changing this page.',
        ]);
    }

    /** The caller must be inside a transaction before locking financial targets. */
    private function lockLogicalPageRows(Page $page): void
    {
        Page::withTrashed()
            ->where('uuid', $page->uuid)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    private function lockPageForMutation(string $uuid, string $locale, int $expectedVersion): Page
    {
        $logicalPages = Page::withTrashed()
            ->where('uuid', $uuid)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'editor_version']);

        $logicalVersion = (int) $logicalPages->max('editor_version');

        $page = Page::query()
            ->where('uuid', $uuid)
            ->where('language', $locale)
            ->lockForUpdate()
            ->firstOrFail();

        abort_if(
            $logicalVersion !== $expectedVersion,
            409,
            'This page changed in another editor. Your unsaved work is still here; reload the page, review the latest version, and apply your changes again.'
        );

        return $page;
    }

    private function advanceEditorVersion(Page $page): int
    {
        $nextVersion = (int) Page::withTrashed()
            ->where('uuid', $page->uuid)
            ->max('editor_version') + 1;

        // Use the query builder so synchronizing the concurrency token does
        // not falsely mark untranslated content as editorially modified.
        DB::table('pages')
            ->where('uuid', $page->uuid)
            ->update(['editor_version' => $nextVersion]);
        $page->refresh();

        return $nextVersion;
    }

    /**
     * Image-and-text sections are backwards compatible: content saved before
     * media choices were introduced is treated as an image section. Video and
     * YouTube choices must have the corresponding usable source before save.
     */
    private function validateMediaTextContent(array $content): void
    {
        $mediaType = (string) ($content['media_type'] ?? 'image');

        Validator::make(['content' => $content], [
            'content.media_type' => ['sometimes', 'string', Rule::in(['image', 'video', 'youtube'])],
            'content.image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'content.image_alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content.video_url' => [
                Rule::requiredIf($mediaType === 'video'),
                'nullable',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $url = $this->sanitizer->sanitizeUrl($value);
                    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
                    if (trim((string) $value) !== ''
                        && ($url === '' || ($scheme !== '' && !in_array($scheme, ['http', 'https'], true)))) {
                        $fail('Choose an uploaded video or enter a valid HTTP or HTTPS video URL.');
                    }
                },
            ],
            'content.youtube_url' => [
                Rule::requiredIf($mediaType === 'youtube'),
                'nullable',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (trim((string) $value) !== '' && $this->youtubeVideoId((string) $value) === null) {
                        $fail('Enter a valid YouTube link with an 11-character video ID.');
                    }
                },
            ],
            'content.poster' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'content.caption' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'content.image_position' => ['sometimes', 'string', Rule::in(['left', 'right'])],
        ])->validate();
    }

    private function youtubeVideoId(string $value): ?string
    {
        $url = trim($value);
        if ($url === '') {
            return null;
        }
        if (!preg_match('#\Ahttps?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
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
            ? $videoId
            : null;
    }

    /**
     * Ways to Give accepts only identities selected from managed records. An
     * inactive cause remains valid in saved content so editors can see the
     * warning and remove it; the public resolver always omits it.
     */
    private function validateWaysToGiveContent(array $content, string $locale, string $errorPrefix = 'content'): void
    {
        Validator::make(['content' => $content], [
            'content.layout' => ['required', Rule::in(['single_cta', 'card_grid', 'banner'])],
            'content.selection_mode' => ['required', Rule::in(['automatic', 'manual'])],
            // Empty is meaningful in manual mode (an intentional public empty
            // state) and automatic mode derives its list at render time.
            'content.selected_items' => ['present', 'array', 'max:12'],
            'content.selected_items.*' => ['required', 'string', 'max:100', 'distinct'],
            'content.project_uuid' => ['sometimes', 'nullable', 'uuid'],
            'content.eyebrow' => ['sometimes', 'nullable', 'string', 'max:120'],
            'content.heading' => ['required', 'string', 'max:180'],
            'content.body' => ['sometimes', 'nullable', 'string', 'max:1200'],
            'content.link_label' => ['sometimes', 'nullable', 'string', 'max:80'],
            'content.empty_state' => ['sometimes', 'nullable', 'string', 'max:300'],
        ])->validate();

        $causes = DonationType::withTrashed()
            ->get(['uuid', 'purpose_key', 'status', 'deleted_at', 'destination_type', 'destination_category_uuid', 'destination_page_uuid']);
        $knownCauseUuids = $causes
            ->whereNull('purpose_key')
            ->pluck('uuid')
            ->map(fn ($uuid) => (string) $uuid)
            ->all();
        $selected = collect($content['selected_items'])
            ->map(fn ($token) => (string) $token)
            ->values();
        $invalid = $selected->reject(function (string $token) use ($knownCauseUuids): bool {
            if (in_array($token, ['zakat', 'sponsor'], true)) {
                return true;
            }

            return str_starts_with($token, 'cause:')
                && in_array(substr($token, strlen('cause:')), $knownCauseUuids, true);
        });

        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages([
                $errorPrefix . '.selected_items' => 'Choose giving options from the managed list. One saved option is no longer recognized.',
            ]);
        }

        $projectUuid = trim((string) ($content['project_uuid'] ?? ''));
        if ($projectUuid === '') {
            return;
        }

        $projectContextAllowed = ($content['selection_mode'] ?? '') === 'manual'
            && in_array($content['layout'] ?? '', ['single_cta', 'banner'], true)
            && $selected->count() === 1
            && str_starts_with((string) $selected->first(), 'cause:');
        if (!$projectContextAllowed) {
            throw ValidationException::withMessages([
                $errorPrefix . '.project_uuid' => 'A project can be preselected only for one managed donation cause in a single CTA or banner. Clear the project or adjust the selection.',
            ]);
        }

        $project = $this->destinations->preferredFundingPublicPage($projectUuid, $locale);
        if (!$project) {
            throw ValidationException::withMessages([
                $errorPrefix . '.project_uuid' => 'Choose a published project from the list.',
            ]);
        }

        $selectedCauseUuid = substr((string) $selected->first(), strlen('cause:'));
        $compatible = $causes
            ->filter(fn (DonationType $cause) => (bool) $cause->status
                && $cause->deleted_at === null
                && $cause->purpose_key === null
                && $this->destinations->isOperational($cause, $locale)
                && (string) $cause->uuid === $selectedCauseUuid)
            ->contains(fn (DonationType $cause) => $this->destinations
                ->selectablePages($cause, $locale)
                ->contains(fn (Page $candidate) => (string) $candidate->uuid === (string) $project->uuid));

        if (!$compatible) {
            throw ValidationException::withMessages([
                $errorPrefix . '.project_uuid' => 'The selected project is not available for any of these managed donation causes.',
            ]);
        }
    }

    private function linkTargets(string $locale): array
    {
        $defaults = collect([
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Donate', 'url' => '/donate'],
            ['label' => 'Zakat', 'url' => '/zakat'],
            ['label' => 'Sponsor a child', 'url' => '/sponsor-child'],
            ['label' => 'Volunteer registration', 'url' => '/volunteer/register'],
            ['label' => 'Contact us', 'url' => '/contact-us'],
            ['label' => 'Projects', 'url' => '/projects'],
            ['label' => 'Events', 'url' => '/events'],
            ['label' => 'Gallery', 'url' => '/gallery'],
            ['label' => 'Annual reports', 'url' => '/annual-report'],
        ]);

        $pages = Page::query()
            ->where('language', $locale)
            ->publiclyAvailable()
            ->orderBy('name')
            ->get(['name', 'slug'])
            ->map(fn (Page $page) => [
                'label' => 'Page: ' . $page->name,
                'url' => match ($page->slug) {
                    'home' => '/',
                    'about-us' => '/about-us',
                    'zakat' => '/zakat',
                    'sponsor-a-child' => '/sponsor-child',
                    default => '/page/' . ltrim($page->slug, '/'),
                },
            ]);

        return $defaults->concat($pages)->unique('url')->values()->all();
    }

    private function blockContentOptions(string $locale): array
    {
        $pages = Page::query()
            ->where('language', $locale)
            ->publiclyAvailable()
            ->with(['category:id,uuid,name,slug,status', 'pageTags.tag:id,name,slug,status'])
            ->orderBy('name')
            ->get([
                'id', 'uuid', 'category_id', 'name', 'slug', 'sub_title', 'description',
                'thumbnail', 'published_at', 'order_by', 'is_funding_project', 'is_zakat_eligible',
            ]);
        $fallbackLocale = (string) config('app.fallback_locale', 'en');
        $givingProjects = Page::query()
            ->publiclyAvailable()
            ->where('visibility', 'public')
            ->where('is_funding_project', true)
            ->with('category:id,uuid')
            ->get(['id', 'uuid', 'category_id', 'language', 'name', 'slug', 'is_funding_project', 'is_zakat_eligible'])
            ->groupBy(fn (Page $page) => (string) $page->uuid)
            ->map(fn ($translations) => $translations->firstWhere('language', $locale)
                ?? $translations->firstWhere('language', $fallbackLocale)
                ?? $translations->first())
            ->filter()
            ->sortBy('name')
            ->values();
        $managedCauses = DonationType::withTrashed()
            ->whereNull('purpose_key')
            ->orderBy('name')
            ->get([
                'uuid', 'slug', 'name', 'status', 'deleted_at', 'destination_type',
                'destination_name', 'destination_category_uuid', 'destination_page_uuid',
            ]);
        $allOperationalCauses = $this->destinations->activeCauses($locale);
        $operationalCauseUuids = $allOperationalCauses
            ->whereNull('purpose_key')
            ->pluck('uuid')
            ->map(fn ($uuid) => (string) $uuid)
            ->all();
        $causeOption = function (DonationType $cause) use ($operationalCauseUuids, $locale): array {
            $operational = in_array((string) $cause->uuid, $operationalCauseUuids, true);
            $destination = $operational
                ? match ($cause->destination_type) {
                    'category' => 'Donor may choose an eligible published project in ' . $this->destinations->destinationName($cause, $locale) . '.',
                    'page' => 'Gifts go to ' . $this->destinations->destinationName($cause, $locale) . '.',
                    'restricted_fund' => 'Gifts go to the “' . $this->destinations->destinationName($cause, $locale) . '” managed fund.',
                    default => 'Gifts support the foundation’s unrestricted work.',
                }
                : 'This cause is unpublished or its managed destination is unavailable.';

            return [
                'value' => 'cause:' . $cause->uuid,
                'label' => $cause->name,
                'kind' => 'cause',
                'active' => $operational,
                'project_selection' => match ($cause->destination_type) {
                    'category' => 'optional',
                    'page' => 'fixed',
                    default => 'none',
                },
                'project_values' => $operational
                    ? $this->destinations->selectablePages($cause, $locale)
                        ->pluck('uuid')
                        ->map(fn ($uuid) => (string) $uuid)
                        ->values()
                        ->all()
                    : [],
                'destination' => $destination,
            ];
        };
        $activeCauseOptions = $managedCauses
            ->filter(fn (DonationType $cause) => in_array((string) $cause->uuid, $operationalCauseUuids, true))
            ->map($causeOption)
            ->values();
        $knownCauseOptions = $managedCauses->map($causeOption)->keyBy('value');
        $zakatIsOperational = Page::query()
            ->publiclyAvailable()
            ->where('visibility', 'public')
            ->where('slug', 'zakat')
            ->exists()
            && $allOperationalCauses->firstWhere('purpose_key', 'zakat') !== null;
        $specialGivingOptions = collect();
        if ($zakatIsOperational) {
            $specialGivingOptions->push([
                'value' => 'zakat',
                'label' => 'Zakat calculator & donation',
                'kind' => 'special',
                'active' => true,
                'project_selection' => 'none',
                'destination' => 'Opens the managed Zakat calculator and donation page.',
            ]);
        }
        $specialGivingOptions->push([
            'value' => 'sponsor',
            'label' => 'Sponsor a Child',
            'kind' => 'special',
            'active' => true,
            'project_selection' => 'none',
            'destination' => 'Opens the managed child sponsorship page.',
        ]);

        $pageOption = static function (Page $page): array {
            $thumbnail = trim((string) $page->getRawOriginal('thumbnail'));
            if ($thumbnail !== '' && !str_starts_with($thumbnail, '/') && !preg_match('#^https?://#i', $thumbnail)) {
                $thumbnail = '/storage/photos/1/page/' . ltrim($thumbnail, '/');
            }

            return [
                'value' => (string) $page->uuid,
                'label' => $page->name,
                'body' => $page->sub_title ?: str($page->description)->stripTags()->limit(140)->toString(),
                'image' => $thumbnail,
                'image_alt' => $page->name,
                'url' => '/page/' . ltrim($page->slug, '/'),
                'featured_order' => (int) ($page->order_by ?? 0),
                'published_at' => $page->published_at?->getTimestamp() ?? 0,
                'sort_id' => (int) $page->id,
            ];
        };
        $testimonialOption = static function (Testimonial $testimonial): array {
            $photo = trim((string) $testimonial->getRawOriginal('photo'));
            if ($photo !== '' && !str_starts_with($photo, '/') && !preg_match('#^https?://#i', $photo)) {
                $photo = '/storage/photos/1/testimonial/' . ltrim(str_replace('\\', '/', $photo), '/');
            }
            $quote = trim((string) preg_replace(
                '/\s+/u',
                ' ',
                html_entity_decode(strip_tags((string) $testimonial->testimonial), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            ));

            return [
                'value' => (string) $testimonial->uuid,
                'label' => $testimonial->name,
                'designation' => trim((string) $testimonial->designation),
                'quote' => $quote,
                'photo' => $photo,
                'featured_order' => (int) ($testimonial->order_by ?? 0),
                'published_at' => $testimonial->created_at?->getTimestamp() ?? 0,
                'sort_id' => (int) $testimonial->id,
            ];
        };

        return [
            'sources' => config('page-builder.automatic_sources', []),
            'sorts' => config('page-builder.automatic_sort_options', []),
            'presentations' => [
                'sections' => config('page-builder.section_presentations', []),
                'causes' => config('page-builder.cause_presentations', []),
            ],
            'categories' => Category::query()
                ->where('language', $locale)
                ->where('status', 1)
                ->orderBy('name')
                ->get(['name', 'slug'])
                ->map(fn (Category $category) => ['value' => $category->slug, 'label' => $category->name])
                ->values(),
            'tags' => Tag::query()
                ->where('status', 1)
                ->orderBy('name')
                ->get(['name', 'slug'])
                ->map(fn (Tag $tag) => ['value' => $tag->slug, 'label' => $tag->name])
                ->values(),
            'ways_to_give' => [
                'items' => $activeCauseOptions->concat($specialGivingOptions)->values(),
                'known_items' => $knownCauseOptions->values(),
                'projects' => $givingProjects->map(fn (Page $page) => [
                    'value' => (string) $page->uuid,
                    'label' => $page->name,
                    'category_uuid' => $page->category?->uuid,
                    'is_funding_project' => (bool) $page->is_funding_project,
                    'is_zakat_eligible' => (bool) $page->is_zakat_eligible,
                ])->values(),
            ],
            'items' => [
                'projects' => $pages
                    ->filter(fn (Page $page) => $page->pageTags->contains(fn ($link) => (bool) $link->tag?->status))
                    ->map(fn (Page $page) => $pageOption($page) + [
                        'tags' => $page->pageTags->pluck('tag')->filter()->pluck('slug')->values()->all(),
                    ])->values(),
                'category' => $pages
                    ->filter(fn (Page $page) => (bool) $page->category?->status)
                    ->map(fn (Page $page) => $pageOption($page) + [
                        'category' => $page->category?->slug,
                    ])->values(),
                'events' => NoticeBoard::query()
                    ->where('language', $locale)
                    ->where('status', 1)
                    ->orderBy('title')
                    ->get(['id', 'title'])
                    ->map(fn (NoticeBoard $event) => ['value' => (string) $event->id, 'label' => $event->title])
                    ->values(),
                'testimonials' => Testimonial::query()
                    ->where('language', $locale)
                    ->where('status', 1)
                    ->whereNotNull('uuid')
                    ->orderBy('name')
                    ->get(['id', 'uuid', 'name', 'designation', 'testimonial', 'photo', 'order_by', 'created_at'])
                    ->map($testimonialOption)
                    ->values(),
                'team' => LatestNews::query()
                    ->where('language', $locale)
                    ->where('type', 'our-members')
                    ->where('status', 1)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (LatestNews $member) => ['value' => (string) $member->id, 'label' => $member->name])
                    ->values(),
                'gallery' => Gallery::query()
                    ->where('language', $locale)
                    ->where('status', 1)
                    ->whereNotNull('uuid')
                    ->orderBy('name')
                    ->get(['uuid', 'name'])
                    ->map(fn (Gallery $photo) => ['value' => (string) $photo->uuid, 'label' => $photo->name])
                    ->values(),
            ],
        ];
    }
}
