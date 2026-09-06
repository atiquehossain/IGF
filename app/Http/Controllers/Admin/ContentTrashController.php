<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\AnnualReport;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Donation;
use App\Models\DonationType;
use App\Models\EventCalendar;
use App\Models\Gallery;
use App\Models\JobPosting;
use App\Models\JobScorecardCriterion;
use App\Models\LatestNews;
use App\Models\NoticeBoard;
use App\Models\SeoMetadata;
use App\Models\SeoMetadataRevision;
use App\Models\SplashScreen;
use App\Models\Tag;
use App\Models\Testimonial;
use App\Models\VolunteerCause;
use App\Models\Workshop;
use App\Models\YouTube;
use App\Services\ContentFileQuarantine;
use App\Services\AdminAuditService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ContentTrashController extends Controller
{
    public function __construct(
        private ContentFileQuarantine $quarantine,
        private AdminAuditService $audit
    )
    {
    }

    /**
     * The editorial resources covered by the shared, recoverable trash.
     * Private donor/member records intentionally remain under their own
     * retention policy rather than being exposed in an editorial recycle bin.
     *
     * @return array<string, array{model: class-string<Model>, label: string, title: list<string>}>
     */
    private function registry(): array
    {
        return [
            'album' => ['model' => Album::class, 'label' => 'Album', 'title' => ['name']],
            'annual-report' => ['model' => AnnualReport::class, 'label' => 'Annual report', 'title' => ['title', 'slug']],
            'banner' => ['model' => Banner::class, 'label' => 'Banner', 'title' => ['name']],
            'category' => ['model' => Category::class, 'label' => 'Category', 'title' => ['name', 'slug']],
            'donation-type' => ['model' => DonationType::class, 'label' => 'Donation cause', 'title' => ['name']],
            'event' => ['model' => EventCalendar::class, 'label' => 'Event', 'title' => ['title']],
            'gallery' => ['model' => Gallery::class, 'label' => 'Gallery item', 'title' => ['name']],
            'job' => ['model' => JobPosting::class, 'label' => 'Job draft', 'title' => []],
            'member' => ['model' => LatestNews::class, 'label' => 'Team member', 'title' => ['name']],
            'publication' => ['model' => NoticeBoard::class, 'label' => 'Publication', 'title' => ['title', 'slug']],
            'splash-screen' => ['model' => SplashScreen::class, 'label' => 'Splash screen', 'title' => ['title']],
            'tag' => ['model' => Tag::class, 'label' => 'Project', 'title' => ['name', 'slug']],
            'testimonial' => ['model' => Testimonial::class, 'label' => 'Testimonial', 'title' => ['name']],
            'volunteer-cause' => ['model' => VolunteerCause::class, 'label' => 'Volunteer cause', 'title' => ['name']],
            'video' => ['model' => YouTube::class, 'label' => 'Video', 'title' => ['title', 'name']],
            'workshop' => ['model' => Workshop::class, 'label' => 'Workshop draft', 'title' => []],
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $typeFilter = (string) $request->query('type');
        $registry = $this->registry();
        if ($typeFilter !== '' && !array_key_exists($typeFilter, $registry)) {
            return redirect()->route('content.trash.index');
        }
        $union = null;

        foreach ($registry as $type => $definition) {
            if ($typeFilter !== '' && $typeFilter !== $type) {
                continue;
            }

            $model = new $definition['model'];
            $query = $definition['model']::onlyTrashed()
                ->selectRaw($model->qualifyColumn($model->getKeyName()) . ' as trash_id')
                ->selectRaw('? as trash_type', [$type])
                ->addSelect($model->qualifyColumn('deleted_at'));

            if ($search !== '') {
                $columns = collect($definition['title'])
                    ->merge(['language', 'slug'])
                    ->unique()
                    ->filter(fn (string $column) => Schema::hasColumn($model->getTable(), $column));
                $hasTranslatedIdentity = $model instanceof JobPosting || $model instanceof Workshop;
                $query->where(function ($fields) use ($columns, $hasTranslatedIdentity, $model, $search): void {
                    foreach ($columns as $column) {
                        $fields->orWhere($model->qualifyColumn($column), 'like', '%' . $search . '%');
                    }
                    if ($hasTranslatedIdentity) {
                        $fields->orWhereHas('translations', function ($translations) use ($search): void {
                            $translations->where(function ($identity) use ($search): void {
                                $identity->where('title', 'like', '%' . $search . '%')
                                    ->orWhere('slug', 'like', '%' . $search . '%')
                                    ->orWhere('locale', 'like', '%' . $search . '%');
                            });
                        });
                    }
                });
            }

            $union = $union === null ? $query : $union->unionAll($query);
        }

        $items = DB::query()
            ->fromSub($union->toBase(), 'trash_items')
            ->orderByDesc('deleted_at')
            ->orderByDesc('trash_id')
            ->paginate(20)
            ->withQueryString();

        $items->setCollection($items->getCollection()->map(function ($row) use ($registry) {
                $type = (string) $row->trash_type;
                $definition = $registry[$type];
                $model = $definition['model']::onlyTrashed()->findOrFail($row->trash_id);
                [$title, $detail] = $this->identity($model, $definition['title']);
                $retentionNote = $this->retentionNote($model);

                return (object) [
                    'type' => $type,
                    'type_label' => $definition['label'],
                    'id' => (string) $model->getKey(),
                    'title' => $title,
                    'detail' => $detail,
                    'deleted_at' => $model->getAttribute('deleted_at'),
                    'can_force_delete' => $retentionNote === null,
                    'retention_note' => $retentionNote ?? '',
                ];
            }));

        return view('admin.content-trash.index', [
            'title' => 'Content trash',
            'items' => $items,
            'search' => $search,
            'typeFilter' => $typeFilter,
            'types' => collect($registry)->map(fn (array $definition) => $definition['label']),
        ]);
    }

    public function restore(string $type, string $id, Request $request)
    {
        try {
            DB::transaction(function () use ($type, $id, $request): void {
                $item = $this->trashed($type, $id);
                $item = $item::onlyTrashed()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
                $item->restore();

                SeoMetadata::onlyTrashed()
                    ->where('seoable_type', $item::class)
                    ->where('seoable_id', $item->getKey())
                    ->lockForUpdate()
                    ->restore();
                $this->audit->record($request->user('admin'), 'content_trash.restored', $item);
            }, 3);
        } catch (QueryException $exception) {
            if (!str_starts_with((string) $exception->getCode(), '23')) {
                throw $exception;
            }

            return response()->json([
                'message' => 'This content conflicts with an active slug or unique value. Resolve that conflict before restoring it.',
            ], 422);
        }

        return response()->json(['message' => 'Content restored successfully.']);
    }

    public function forceDestroy(string $type, string $id, ?Request $request = null)
    {
        $batch = null;
        DB::beginTransaction();
        try {
            $item = $this->trashed($type, $id);
            if ($item instanceof Category) {
                // Destination rows always precede cause rows in the financial
                // lock order. Recheck after both locks so a cause assignment
                // cannot race a permanent category deletion.
                Category::withTrashed()
                    ->where('uuid', $item->uuid)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id']);
                DonationType::withTrashed()
                    ->where('status', 1)
                    ->where('destination_type', 'category')
                    ->where('destination_category_uuid', $item->uuid)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id']);
                $item = Category::onlyTrashed()
                    ->whereKey($item->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
            } else {
                $item = $item::onlyTrashed()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            }

            if ($retentionNote = $this->retentionNote($item)) {
                DB::rollBack();

                return response()->json([
                    'message' => $retentionNote,
                ], 422);
            }

            $batch = $this->quarantine->stage($item);
            $this->purgeOwnedOpportunityContent($item);
            SeoMetadata::withTrashed()
                ->where('seoable_type', $item::class)
                ->where('seoable_id', $item->getKey())
                ->forceDelete();
            $item->forceDelete();
            $this->audit->record($request?->user('admin') ?? auth('admin')->user(), 'content_trash.purged', $item, context: [
                'content_type' => $type,
                'media_quarantined' => $batch !== null,
            ]);
            DB::commit();
            $this->quarantine->commit($batch);

            return response()->json(['message' => 'Content permanently deleted.']);
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
                try {
                    $this->quarantine->rollback($batch);
                } catch (Throwable $restoreException) {
                    report($restoreException);
                }
            }
            report($exception);

            return response()->json([
                'message' => 'The content could not be deleted. Its database record and media were left recoverable.',
            ], 500);
        }
    }

    private function trashed(string $type, string $id): Model
    {
        $definition = $this->registry()[$type] ?? null;
        abort_unless($definition, 404);

        return $definition['model']::onlyTrashed()->findOrFail($id);
    }

    private function retentionNote(Model $item): ?string
    {
        if ($item instanceof JobPosting) {
            if ($item->publication_status !== JobPosting::PUBLICATION_DRAFT) {
                return 'Only unused job drafts can be permanently deleted. Restore this job and use its close or withdraw action instead.';
            }
            if ($item->applications()->withTrashed()->exists()) {
                return 'This job is retained because applicant records reference it. Restore the job to manage it; applicant history cannot be removed from Content Trash.';
            }
            if ($item->importBatches()->exists()) {
                return 'This job is retained because an application import record references it. Restore the job to review that import history.';
            }
        }

        if ($item instanceof Workshop) {
            if ($item->publication_status !== Workshop::PUBLICATION_DRAFT) {
                return 'Only unused workshop drafts can be permanently deleted. Restore this workshop and use its close or withdraw action instead.';
            }
            if ($item->registrations()->withTrashed()->exists()) {
                return 'This workshop is retained because registration records reference it. Restore the workshop to manage it; registration history cannot be removed from Content Trash.';
            }
            if ($item->importBatches()->exists()) {
                return 'This workshop is retained because a registration import record references it. Restore the workshop to review that import history.';
            }
        }

        if ($item instanceof DonationType) {
            return Donation::query()
                ->where(fn ($query) => $query
                    ->where('payment_cause', $item->uuid)
                    ->orWhere('cause_uuid_snapshot', $item->uuid))
                ->exists()
                    ? 'This donation cause is retained because historical donations reference it. Restore it if you want to use it again.'
                    : 'Donation causes may be moved to trash, but cannot be permanently deleted. Their permanent slug protects old donation links from ever pointing to a different cause.';
        }

        if ($item instanceof Category && DonationType::withTrashed()
            ->where('status', 1)
            ->where('destination_type', 'category')
            ->where('destination_category_uuid', $item->uuid)
            ->exists()) {
            return 'An active donation cause uses this program. Reassign or unpublish that cause before permanently deleting the program.';
        }

        if ($item instanceof Category && Donation::query()
            ->where('payment_status', 'Success')
            ->where('destination_type_snapshot', 'category')
            ->where('destination_uuid_snapshot', $item->uuid)
            ->with('allocations:id,donation_id,amount')
            ->get(['id', 'amount'])
            ->contains(function (Donation $donation): bool {
                $allocated = $donation->allocations->sum(
                    fn ($allocation): int => $this->decimalToCents((string) $allocation->amount)
                );

                return $allocated < $this->decimalToCents((string) $donation->amount);
            })) {
            return 'A successful donation still has money available within this program. Fully allocate it or restore the program before permanently deleting it.';
        }

        return null;
    }

    /**
     * @param list<string> $titleAttributes
     * @return array{0:string,1:string}
     */
    private function identity(Model $model, array $titleAttributes): array
    {
        if ($model instanceof JobPosting || $model instanceof Workshop) {
            $translations = $model->translations()
                ->get(['locale', 'slug', 'title'])
                ->sortBy(fn (Model $translation): string =>
                    (strtolower((string) $translation->getAttribute('locale')) === 'en' ? '0' : '1')
                    . strtolower((string) $translation->getAttribute('locale'))
                )
                ->values();
            $preferred = $translations->first();
            $kind = $model instanceof JobPosting ? 'job' : 'workshop';
            $path = $model instanceof JobPosting ? '/careers/' : '/workshops/';
            $title = trim((string) $preferred?->getAttribute('title')) ?: 'Untitled ' . $kind . ' draft #' . $model->getKey();
            $detail = $translations->map(function (Model $translation) use ($path): string {
                $locale = strtoupper(trim((string) $translation->getAttribute('locale')));
                $slug = trim((string) $translation->getAttribute('slug'));

                return $locale . ($slug !== '' ? ': ' . $path . $slug : '');
            })->filter()->implode('; ');

            return [$title, $detail];
        }

        $title = collect($titleAttributes)
            ->map(fn (string $attribute) => trim((string) $model->getAttribute($attribute)))
            ->first(fn (string $value) => $value !== '') ?: 'Untitled item #' . $model->getKey();
        $detail = trim((string) ($model->getAttribute('language') ?? $model->getAttribute('slug') ?? ''));

        return [$title, $detail];
    }

    private function purgeOwnedOpportunityContent(Model $item): void
    {
        if (!$item instanceof JobPosting && !$item instanceof Workshop) {
            return;
        }

        SeoMetadataRevision::query()
            ->where('seoable_type', $item::class)
            ->where('seoable_id', $item->getKey())
            ->delete();

        if ($item instanceof JobPosting) {
            JobScorecardCriterion::withTrashed()
                ->where('job_posting_id', $item->getKey())
                ->forceDelete();
        }

        $item->translations()->delete();
    }

    private function decimalToCents(string $value): int
    {
        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', trim($value), $matches)) {
            return 0;
        }

        return ((int) $matches[1] * 100)
            + (int) str_pad((string) ($matches[2] ?? ''), 2, '0');
    }
}
