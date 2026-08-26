<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\ReusableBlock;
use App\Services\ContentSanitizer;
use App\Services\PageEditorVersionService;
use App\Services\PageRevisionService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReusableBlockController extends Controller
{
    public function __construct(
        private ContentSanitizer $sanitizer,
        private PageEditorVersionService $pageVersions
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

    public function update(ReusableBlock $reusableBlock, Request $request)
    {
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:0'],
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', 'max:10'],
            'content' => ['nullable', 'array'],
            'content.section_presentation' => [
                'sometimes',
                'string',
                Rule::in(array_keys(config('page-builder.section_presentations', []))),
            ],
            'settings' => ['nullable', 'array'],
            'is_enabled' => ['required', 'boolean'],
        ]);
        // Laravel only returns explicitly validated nested array members when
        // a child rule is present. The complete block payload was already
        // type-checked above, so restore it before sanitizing every value.
        $data['content'] = $request->input('content', []);

        $anticipatedPageUuids = $this->affectedPageUuids($reusableBlock->getKey());
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
            ? response()->json(['message' => 'Reusable section saved.', 'block' => $reusableBlock->fresh()])
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
