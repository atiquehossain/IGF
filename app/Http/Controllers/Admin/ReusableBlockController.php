<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Permission;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\ReusableBlock;
use App\Services\ContentSanitizer;
use App\Services\PageBlockContentResolver;
use App\Services\PageEditorVersionService;
use App\Services\PageRevisionService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ReusableBlockController extends Controller
{
    public function __construct(
        private ContentSanitizer $sanitizer,
        private PageEditorVersionService $pageVersions,
        private PageBuilderController $pageBuilder
    ) {
    }

    public function index(Request $request)
    {
        $query = ReusableBlock::query()
            ->withCount('instances')
            ->latest();

        if ($request->boolean('trash')) {
            $query->onlyTrashed();
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->string('search')->trim() . '%');
        }
        if ($request->filled('locale')) {
            $query->whereIn('locale', ['*', $request->string('locale')->toString()]);
        }

        return view('admin.reusable-blocks.index', [
            'title' => 'Reusable sections',
            'blocks' => $query->paginate(20)->withQueryString(),
            'blockTypes' => config('page-builder.block_types'),
            'isTrash' => $request->boolean('trash'),
            'search' => $request->string('search')->toString(),
            'locale' => $request->string('locale', app()->getLocale())->toString(),
        ]);
    }

    public function show(ReusableBlock $reusableBlock)
    {
        return $this->editor($reusableBlock, false);
    }

    public function edit(ReusableBlock $reusableBlock)
    {
        return $this->editor($reusableBlock, true);
    }

    public function preview(
        ReusableBlock $reusableBlock,
        Request $request,
        PageBlockContentResolver $resolver
    ) {
        $supportedLocales = array_values(config('localization.public_locales', ['en']));
        $requestedLocale = (string) $request->query('locale', config('app.fallback_locale', 'en'));
        $locale = $reusableBlock->locale === '*' ? $requestedLocale : (string) $reusableBlock->locale;
        abort_unless(in_array($locale, $supportedLocales, true), 404);
        app()->setLocale($locale);

        // Resolve automatic sources through the same service used by visitor
        // pages. The preview remains available when the library item is
        // disabled, so editors can safely review it before enabling it.
        $previewBlock = new PageBlock([
            'uuid' => (string) $reusableBlock->uuid,
            'type' => (string) $reusableBlock->type,
            'label' => (string) $reusableBlock->name,
            'content' => (array) $reusableBlock->content,
            'settings' => (array) $reusableBlock->settings,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);

        return Inertia::render('reusable-block-preview', [
            'title' => 'Preview: '.$reusableBlock->name,
            'meta_tag' => [
                'meta_title' => 'Preview: '.$reusableBlock->name,
                'robots' => 'noindex,nofollow',
                'canonical_url' => null,
            ],
            'block' => [
                'uuid' => (string) $reusableBlock->uuid,
                'type' => (string) $reusableBlock->type,
                'label' => (string) $reusableBlock->name,
                'content' => $resolver->resolve($previewBlock),
                'settings' => $previewBlock->resolvedSettings(),
                'is_enabled' => true,
                'show_on_desktop' => true,
                'show_on_mobile' => true,
                'is_reusable' => true,
            ],
            'libraryStatus' => $reusableBlock->is_enabled ? 'active' : 'disabled',
            'previewLocale' => $locale,
        ]);
    }

    public function update(ReusableBlock $reusableBlock, Request $request)
    {
        $this->normalizeJsonPayload($request, 'content');
        $this->normalizeJsonPayload($request, 'settings');

        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:0'],
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', Rule::in(array_merge(
                ['*'],
                array_keys(config('localization.editor_locales', ['en' => []]))
            ))],
            'content' => ['nullable', 'array'],
            'content.section_presentation' => [
                'sometimes',
                'string',
                Rule::in(array_keys(config('page-builder.section_presentations', []))),
            ],
            'settings' => ['nullable', 'array'],
            'is_enabled' => ['required', 'boolean'],
            'library_editor' => ['sometimes', 'boolean'],
            'impact_acknowledged' => ['nullable', 'boolean'],
            'expected_page_uuids_json' => ['required_if:library_editor,1', 'nullable', 'json'],
            'expected_connected_page_ids_json' => ['required_if:library_editor,1', 'nullable', 'json'],
        ]);
        // Laravel only returns explicitly validated nested array members when
        // a child rule is present. The complete block payload was already
        // type-checked above, so restore it before sanitizing every value.
        $data['content'] = $request->input('content', []);
        $validationLocale = $data['locale'] === '*'
            ? (string) config('app.fallback_locale', 'en')
            : (string) $data['locale'];
        $this->pageBuilder->validateReusableBlockPayload(
            (string) $reusableBlock->type,
            (array) $data['content'],
            $validationLocale
        );

        $anticipatedPageUuids = $this->affectedPageUuids($reusableBlock->getKey());
        $connectedPageIds = $this->connectedPageIds($reusableBlock->getKey());
        if ($request->boolean('library_editor')) {
            $expectedPageUuids = $this->validatedIdentifierList(
                (string) $request->input('expected_page_uuids_json', '[]'),
                'expected_page_uuids_json',
                true
            );
            $expectedConnectedPageIds = $this->validatedIdentifierList(
                (string) $request->input('expected_connected_page_ids_json', '[]'),
                'expected_connected_page_ids_json',
                false
            );
            abort_if(
                $expectedPageUuids !== $anticipatedPageUuids
                    || $expectedConnectedPageIds !== $connectedPageIds,
                409,
                'The pages using this section changed while it was open. Reload the editor and review the updated page list before saving.'
            );
            if ($connectedPageIds !== [] && !$request->boolean('impact_acknowledged')) {
                throw ValidationException::withMessages([
                    'impact_acknowledged' => 'Confirm that you reviewed the affected pages before saving.',
                ]);
            }
        }

        $reusableBlock = DB::transaction(function () use ($reusableBlock, $data, $anticipatedPageUuids): ReusableBlock {
            $this->pageVersions->advanceMany($anticipatedPageUuids);
            $locked = ReusableBlock::withTrashed()
                ->whereKey($reusableBlock->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertExpectedVersion($locked, (int) $data['expected_version']);
            // Attach and revision restore lock the shared row before they can
            // insert/delete PageBlock instances. Match that order after the
            // deterministic Page locks to avoid a MySQL next-key deadlock.
            $instances = $this->lockInstances($locked->getKey());
            $this->assertAffectedPagesUnchanged($instances, $anticipatedPageUuids);
            $locked->fill([
                ...collect($data)->except('expected_version')->all(),
                'content' => $this->sanitizer->sanitizeBlockContent($data['content'] ?? []),
                'updated_by' => auth('admin')->id(),
            ]);
            $locked->editor_version = ((int) $locked->editor_version) + 1;
            $locked->save();

            return $locked->fresh();
        });

        return $request->expectsJson()
            ? response()->json([
                'message' => 'Reusable section saved.',
                'block' => $reusableBlock->fresh(),
                'connected_page_count' => count($this->connectedPageIds($reusableBlock->getKey())),
            ])
            : back()->with(['message' => 'Reusable section saved.', 'alert-type' => 'success']);
    }

    public function destroy(ReusableBlock $reusableBlock, Request $request)
    {
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:0'],
        ]);
        $anticipatedPageUuids = $this->affectedPageUuids($reusableBlock->getKey());

        DB::transaction(function () use ($reusableBlock, $data, $anticipatedPageUuids): void {
            $this->pageVersions->advanceMany($anticipatedPageUuids);
            $locked = ReusableBlock::query()
                ->whereKey($reusableBlock->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertExpectedVersion($locked, (int) $data['expected_version']);
            $instances = $this->lockInstances($locked->getKey());
            $this->assertAffectedPagesUnchanged($instances, $anticipatedPageUuids);

            $instances->each(function (PageBlock $instance) use ($locked): void {
                $instance->update([
                    'reusable_block_id' => null,
                    'label' => $locked->name,
                    'content' => $locked->content,
                    'settings' => $locked->settings,
                    'updated_by' => auth('admin')->id(),
                ]);
            });
            $locked->editor_version = ((int) $locked->editor_version) + 1;
            $locked->save();
            $locked->delete();
        });

        return $request->expectsJson()
            ? response()->json(['message' => 'Reusable section moved to trash; page instances were safely detached.'])
            : back()->with(['message' => 'Reusable section moved to trash; page instances were safely detached.', 'alert-type' => 'success']);
    }

    public function restore(string $uuid, Request $request)
    {
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:0'],
        ]);
        $candidate = ReusableBlock::onlyTrashed()->where('uuid', $uuid)->firstOrFail();
        $anticipatedPageUuids = $this->affectedPageUuids($candidate->getKey());
        $block = DB::transaction(function () use ($candidate, $data, $anticipatedPageUuids): ReusableBlock {
            $this->pageVersions->advanceMany($anticipatedPageUuids);
            $locked = ReusableBlock::onlyTrashed()
                ->whereKey($candidate->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertExpectedVersion($locked, (int) $data['expected_version']);
            $instances = $this->lockInstances($locked->getKey());
            $this->assertAffectedPagesUnchanged($instances, $anticipatedPageUuids);
            $locked->editor_version = ((int) $locked->editor_version) + 1;
            $locked->restore();

            return $locked->fresh();
        });

        return $request->expectsJson()
            ? response()->json(['message' => 'Reusable section restored.', 'block' => $block])
            : back()->with(['message' => 'Reusable section restored.', 'alert-type' => 'success']);
    }

    public function forceDestroy(string $uuid, Request $request)
    {
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:0'],
        ]);
        $candidate = ReusableBlock::onlyTrashed()->where('uuid', $uuid)->firstOrFail();
        $anticipatedPageUuids = $this->affectedPageUuids($candidate->getKey());
        DB::transaction(function () use ($candidate, $data, $anticipatedPageUuids): void {
            $this->pageVersions->lockForMutation($anticipatedPageUuids);
            $block = ReusableBlock::onlyTrashed()
                ->whereKey($candidate->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertExpectedVersion($block, (int) $data['expected_version']);
            $instances = $this->lockInstances($block->getKey());
            $this->assertAffectedPagesUnchanged($instances, $anticipatedPageUuids);
            abort_if($instances->isNotEmpty(), 422, 'This reusable section still has page instances.');
            $block->forceDelete();
        });

        return $request->expectsJson()
            ? response()->json(['message' => 'Reusable section permanently deleted.'])
            : back()->with(['message' => 'Reusable section permanently deleted.', 'alert-type' => 'success']);
    }

    /** @return list<string> */
    private function affectedPageUuids(int $reusableBlockId): array
    {
        return Page::withTrashed()
            ->whereIn('id', PageBlock::withTrashed()
                ->where('reusable_block_id', $reusableBlockId)
                ->select('page_id'))
            ->pluck('uuid')
            ->map(fn ($uuid): string => (string) $uuid)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function connectedPageIds(int $reusableBlockId): array
    {
        return Page::query()
            ->whereIn('id', PageBlock::query()
                ->where('reusable_block_id', $reusableBlockId)
                ->select('page_id'))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function editor(ReusableBlock $reusableBlock, bool $editing)
    {
        $admin = auth('admin')->user();
        $permissions = app(Permission::class);
        $canEdit = $permissions->allows($admin, 'reusable-blocks.edit');
        abort_if($editing && !$canEdit, 403, 'You do not have permission to edit reusable sections.');

        $connectedPages = Page::query()
            ->whereIn('id', PageBlock::query()
                ->where('reusable_block_id', $reusableBlock->getKey())
                ->select('page_id'))
            ->orderBy('name')
            ->orderBy('language')
            ->get([
                'id', 'uuid', 'name', 'slug', 'language', 'status',
                'publication_status', 'visibility',
            ]);
        $mutationPageUuids = $this->affectedPageUuids($reusableBlock->getKey());
        $editorLocale = $reusableBlock->locale === '*'
            ? (string) config('app.fallback_locale', 'en')
            : (string) $reusableBlock->locale;

        return view('admin.reusable-blocks.editor', [
            'title' => ($canEdit && $editing ? 'Edit reusable section — ' : 'Reusable section — ').$reusableBlock->name,
            'block' => $reusableBlock,
            'blockLabel' => config('page-builder.block_types.'.$reusableBlock->type, $reusableBlock->type),
            'canEdit' => $canEdit,
            'editing' => $editing,
            'connectedPages' => $connectedPages,
            'mutationPageUuids' => $mutationPageUuids,
            'canEditPages' => $permissions->allows($admin, 'page.builder.edit'),
            'canPreviewPages' => $permissions->allows($admin, 'page.builder.preview'),
            'canOpenMedia' => $permissions->allows($admin, 'media.index'),
            'locales' => config('localization.editor_locales', []),
            'mediaAssets' => MediaAsset::query()
                ->where(function ($query) {
                    $query->where('mime_type', 'like', 'image/%')
                        ->orWhereIn('mime_type', ['video/mp4', 'video/webm']);
                })
                ->latest()
                ->limit(120)
                ->get(['uuid', 'disk', 'path', 'original_name', 'mime_type', 'alt_text']),
            'defaultContent' => config('page-builder.default_content.'.$reusableBlock->type, []),
            'managedContent' => $this->pageBuilder->reusableBlockEditorOptions($editorLocale),
            'fieldChoices' => [
                'section_presentation' => config('page-builder.section_presentations', []),
                'section_spacing' => config('page-builder.section_spacing_options', []),
                'content_alignment' => config('page-builder.content_alignment_options', []),
                'column_count' => config('page-builder.column_count_options', []),
                'content_source' => config('page-builder.automatic_sources.'.$reusableBlock->type, []),
                'sort' => config('page-builder.automatic_sort_options', []),
                'presentation' => config('page-builder.cause_presentations', []),
            ],
        ]);
    }

    private function normalizeJsonPayload(Request $request, string $key): void
    {
        $jsonKey = $key.'_json';
        if (!$request->has($jsonKey)) {
            return;
        }

        $validator = Validator::make($request->all(), [
            $jsonKey => ['required', 'string', 'json'],
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $decoded = json_decode((string) $request->input($jsonKey), true);
        if (!is_array($decoded)) {
            throw ValidationException::withMessages([
                $jsonKey => 'The section data is invalid. Reload the editor and try again.',
            ]);
        }
        $request->merge([$key => $decoded]);
    }

    /** @return list<int|string> */
    private function validatedIdentifierList(string $json, string $field, bool $uuids): array
    {
        $values = json_decode($json, true);
        if (!is_array($values)) {
            throw ValidationException::withMessages([$field => 'The affected-page list is invalid.']);
        }

        $validator = Validator::make(
            ['values' => $values],
            ['values' => ['array'], 'values.*' => [$uuids ? 'uuid' : 'integer']]
        );
        if ($validator->fails()) {
            throw ValidationException::withMessages([$field => 'The affected-page list is invalid.']);
        }

        $normalized = collect($values)
            ->map(fn ($value) => $uuids ? (string) $value : (int) $value)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $normalized;
    }

    /** @return EloquentCollection<int, PageBlock> */
    private function lockInstances(int $reusableBlockId): EloquentCollection
    {
        return PageBlock::withTrashed()
            ->where('reusable_block_id', $reusableBlockId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * A page may attach the section after the read used to choose lock order.
     * Fail closed and retry rather than acquiring a newly discovered Page lock
     * after the reusable row, which would invert the builder's lock order.
     *
     * @param EloquentCollection<int, PageBlock> $instances
     * @param list<string> $anticipatedPageUuids
     */
    private function assertAffectedPagesUnchanged(EloquentCollection $instances, array $anticipatedPageUuids): void
    {
        $actual = Page::withTrashed()
            ->whereIn('id', $instances->pluck('page_id')->unique())
            ->pluck('uuid')
            ->map(fn ($uuid): string => (string) $uuid)
            ->unique()
            ->sort()
            ->values()
            ->all();

        abort_if($actual !== $anticipatedPageUuids, 409, PageRevisionService::SHARED_CONFLICT_MESSAGE);
    }

    private function assertExpectedVersion(ReusableBlock $reusableBlock, int $expectedVersion): void
    {
        abort_if(
            $expectedVersion !== (int) $reusableBlock->editor_version,
            409,
            PageRevisionService::SHARED_CONFLICT_MESSAGE
        );
    }
}
