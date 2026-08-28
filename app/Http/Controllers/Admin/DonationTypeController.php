<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Helper\Seq;
use App\Models\Category;
use App\Models\DonationCauseGroup;
use App\Models\DonationType;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Services\DonationDestinationService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DonationTypeController extends Controller
{
    public function __construct(private DonationDestinationService $destinations)
    {
    }

    public function index(Request $request)
    {
        $title = $request->Lang->DonationTitle;
        $search = $request->search;
        $donationTypes = DonationType::query()
            ->with(['imageAsset', 'causeGroup'])
            ->when($search, fn ($query) => $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('slug', 'like', '%' . $search . '%')
                    ->orWhere('destination_name', 'like', '%' . $search . '%')
                    ->orWhereHas('causeGroup', fn ($groups) => $groups
                        ->where('name', 'like', '%' . $search . '%'));
            }))
            ->orderByRaw('CASE WHEN display_order IS NULL THEN 1 ELSE 0 END')
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate(15);
        $donationTypes->getCollection()->each(function (DonationType $cause): void {
            $candidate = clone $cause;
            $candidate->status = true;
            $cause->setAttribute('destination_label', $this->destinations->destinationName($cause));
            $cause->setAttribute('description_ready', $this->destinations->hasReviewedDescription($cause->description));
            $cause->setAttribute('destination_ready', $this->destinations->isOperational($candidate));
        });
        $purposeOptions = DonationType::PURPOSE_OPTIONS;
        $destinationOptions = DonationType::DESTINATION_OPTIONS;
        $iconOptions = DonationType::ICON_OPTIONS;
        $nextDisplayOrder = ((int) DonationType::query()->max('display_order')) + 10;
        $causeGroups = DonationCauseGroup::query()
            ->withCount([
                'causes as attached_causes_count' => fn ($query) => $query->withTrashed(),
                'causes as published_causes_count' => fn ($query) => $query->where('status', 1),
            ])
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
        $nextGroupDisplayOrder = ((int) DonationCauseGroup::query()->max('display_order')) + 10;
        $categories = $this->categoryOptions(app()->getLocale());
        $pages = $this->pageOptions(app()->getLocale());
        $mediaAssets = MediaAsset::query()
            ->where('mime_type', 'like', 'image/%')
            ->latest()
            ->limit(150)
            ->get();
        $referencedMediaUuids = DonationType::query()
            ->whereNotNull('image_media_uuid')
            ->pluck('image_media_uuid')
            ->filter()
            ->unique();
        if ($referencedMediaUuids->isNotEmpty()) {
            $mediaAssets = $mediaAssets
                ->concat(MediaAsset::query()
                    ->whereIn('uuid', $referencedMediaUuids)
                    ->where('mime_type', 'like', 'image/%')
                    ->get())
                ->unique('uuid')
                ->values();
        }

        return view('admin.donationType.index')->with(compact(
            'title',
            'donationTypes',
            'search',
            'purposeOptions',
            'destinationOptions',
            'iconOptions',
            'nextDisplayOrder',
            'causeGroups',
            'nextGroupDisplayOrder',
            'categories',
            'pages',
            'mediaAssets'
        ));
    }

    public function create()
    {
        // Donation causes use the guided inline builder on the index screen.
        // Keep the conventional route useful for bookmarks and permission links
        // instead of returning an empty page.
        return redirect()->to(route('donationType.index') . '#new_donation_type');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('donation_types', 'name')],
            'description' => ['nullable', 'string', 'max:2000'],
            'purpose_key' => ['nullable', 'string', Rule::in(array_keys(DonationType::PURPOSE_OPTIONS)), Rule::unique('donation_types', 'purpose_key')],
            ...$this->presentationRules(),
            ...$this->destinationRules(),
        ]);
        if (($validated['purpose_key'] ?? null) === 'zakat') {
            throw ValidationException::withMessages([
                'purpose_key' => 'Save this cause as a regular draft first. After an authorized administrator publishes it, edit it again to assign the Zakat page role.',
            ]);
        }
        $attributes = $this->normalizedDestinationAttributes($validated);
        $presentation = $this->normalizedPresentationAttributes($validated, true);

        try {
            DonationType::create([
                'uuid' => Seq::uuidV4(),
                'name' => trim((string) $validated['name']),
                'description' => trim((string) ($validated['description'] ?? '')) ?: null,
                'purpose_key' => $validated['purpose_key'] ?: null,
                'status' => 0,
                ...$attributes,
                ...$presentation,
            ]);

            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success'
            );
            return back()->with($notification);
        } catch (Exception $e) {
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }
    }

    public function show($id = null, Request $request)
    {
        $cause = DonationType::query()->find($id);
        if (!$cause) {
            return redirect()->route('donationType.index')->withErrors([
                'donation_type' => $request->Lang->Common->Form->DataNotFound,
            ]);
        }

        // Editing also happens in the index modal. The row fragment gives a
        // meaningful destination for legacy/show links without a blank screen.
        return redirect()->to(route('donationType.index') . '#' . $cause->getKey());
    }

    public function edit($id = null, Request $request)
    {
        try {
            $donation = DonationType::select([
                'id',
                'name',
                'slug',
                'description',
                'purpose_key',
                'destination_type',
                'destination_name',
                'destination_category_uuid',
                'destination_page_uuid',
                'image_media_uuid',
                'display_order',
                'icon_key',
                'donation_cause_group_id',
            ])->where('id', $id)->firstOrFail();
            $response = ['data' => $donation];
            return response($response, 200);
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->DataNotFound], 403);
        }
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('donation_types', 'name')->ignore($request->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'purpose_key' => ['nullable', 'string', Rule::in(array_keys(DonationType::PURPOSE_OPTIONS))],
            'id' => ['required', 'integer'],
            ...$this->presentationRules(),
            ...$this->destinationRules(),
        ]);
        $attributes = $this->normalizedDestinationAttributes($validated);
        $presentation = $this->normalizedPresentationAttributes($validated);
        try {
            $donationType = DonationType::find($validated['id']);
            if (empty($donationType)) {
                $notification = array(
                    'message' => $request->Lang->Common->Form->NotFound,
                    'alert-type' => 'warning'
                );
                return back()->with($notification);
            }
            
            $purpose = $validated['purpose_key'] ?: null;
            if ($donationType->purpose_key === 'zakat' && $purpose !== 'zakat') {
                return back()->withErrors([
                    'purpose_key' => 'The Zakat page must always have an active donation cause. To change it, edit the replacement cause and choose “Use for the Zakat donation page”.',
                ]);
            }

            $candidate = clone $donationType;
            $candidate->fill([
                'name' => trim((string) $validated['name']),
                'description' => trim((string) ($validated['description'] ?? '')) ?: null,
                'purpose_key' => $purpose,
                ...$attributes,
                ...$presentation,
            ]);
            if ($purpose === 'zakat' && (!$candidate->status || !$this->destinations->isReadyForPublication($candidate))) {
                return back()->withErrors([
                    'purpose_key' => 'Publish this fully reviewed cause first. Then assign it to the Zakat page; editing alone cannot publish a draft.',
                ]);
            }

            DB::transaction(function () use ($donationType, $validated, $attributes, $presentation, $purpose): void {
                // Page/Category mutations use target -> cause as their lock order.
                // Lock the requested destination first so cause editing cannot form
                // the opposite half of a database deadlock.
                $requestedCandidate = clone $donationType;
                $requestedCandidate->fill([
                    'destination_type' => $attributes['destination_type'],
                    'destination_category_uuid' => $attributes['destination_category_uuid'],
                    'destination_page_uuid' => $attributes['destination_page_uuid'],
                ]);
                $this->destinations->lockDestinationRows($requestedCandidate);

                $lockedQuery = DonationType::query()->orderBy('id')->lockForUpdate();
                $lockedRows = $purpose === 'zakat'
                    ? $lockedQuery->get()
                    : $lockedQuery->whereKey($donationType->getKey())->get();
                $locked = $lockedRows->firstWhere('id', $donationType->getKey());
                if (!$locked) {
                    throw ValidationException::withMessages([
                        'id' => 'This donation cause no longer exists.',
                    ]);
                }
                if ($locked->purpose_key === 'zakat' && $purpose !== 'zakat') {
                    throw ValidationException::withMessages([
                        'purpose_key' => 'Assign the Zakat purpose to a published replacement cause instead of removing it directly.',
                    ]);
                }

                $lockedCandidate = clone $locked;
                $lockedCandidate->fill([
                    'name' => trim((string) $validated['name']),
                    'description' => trim((string) ($validated['description'] ?? '')) ?: null,
                    'purpose_key' => $purpose,
                    ...$attributes,
                    ...$presentation,
                ]);
                if ($lockedCandidate->status && !$this->destinations->isReadyForPublication($lockedCandidate)) {
                    throw ValidationException::withMessages([
                        'destination_type' => 'This cause cannot remain published until its description is reviewed and its destination is active and public.',
                    ]);
                }

                if ($purpose === 'zakat') {
                    DonationType::query()
                        ->where('purpose_key', 'zakat')
                        ->whereKeyNot($locked->getKey())
                        ->update(['purpose_key' => null]);
                }

                $locked->update([
                    'name' => trim((string) $validated['name']),
                    'description' => trim((string) ($validated['description'] ?? '')) ?: null,
                    'purpose_key' => $purpose,
                    ...$attributes,
                    ...$presentation,
                ]);
            });

            $notification = array(
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success'
            );
            return back()->with($notification);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            $notification = array(
                'message' => $request->Lang->Common->Form->NotUpdate,
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }
    }

    public function status(Request $request)
    {
        try {
            if ($request->ajax()) {
                $causeId = $request->route('id') ?? $request->id;
                for ($attempt = 0; $attempt < 3; $attempt++) {
                    $snapshot = DonationType::query()->whereKey($causeId)->first();
                    if (!$snapshot) {
                        return response(['message' => $request->Lang->Common->Form->NotFound], 404);
                    }

                    try {
                        return DB::transaction(function () use ($request, $snapshot) {
                            // The destination can change between the initial read and
                            // the row locks. In that rare case, rollback and retry so
                            // we never acquire a target lock after a cause lock.
                            $this->destinations->lockDestinationRows($snapshot);
                            $data = DonationType::query()
                                ->whereKey($snapshot->getKey())
                                ->lockForUpdate()
                                ->first();
                            if (!$data) {
                                return response(['message' => $request->Lang->Common->Form->NotFound], 404);
                            }
                            if ($this->destinationLockIdentity($data) !== $this->destinationLockIdentity($snapshot)) {
                                throw new RuntimeException('donation_destination_changed_during_lock');
                            }
                            if ($data->purpose_key === 'zakat' && (bool) $data->status) {
                                return response([
                                    'message' => 'The active Zakat cause cannot be unpublished. Assign the Zakat purpose to another cause first.',
                                ], 422);
                            }
                            if (!$data->status) {
                                $candidate = clone $data;
                                $candidate->status = true;
                                if (!$this->destinations->isReadyForPublication($candidate)) {
                                    return response([
                                        'message' => !$this->destinations->hasReviewedDescription($data->description)
                                            ? 'This cause cannot be published yet. Replace the blank or internal draft description with visitor-ready wording first.'
                                            : 'This cause cannot be published yet. Choose an active public destination and review its settings first.',
                                    ], 422);
                                }
                            }
                            $data->status = $data->status ^ 1;
                            $data->update();

                            return response(['message' => ($data->status ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully)], 200);
                        });
                    } catch (RuntimeException $e) {
                        if ($e->getMessage() !== 'donation_destination_changed_during_lock') {
                            throw $e;
                        }
                    }
                }

                return response([
                    'message' => 'This cause changed while it was being published. Please try again.',
                ], 409);
            }
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotUpdate], 403);
        }
    }

    public function destroy($id = null, Request $request)
    {
        try {
            return DB::transaction(function () use ($id, $request) {
                $donation = DonationType::query()->whereKey($id)->lockForUpdate()->first();
                if (!$donation) {
                    return response(['message' => $request->Lang->Common->Form->NotFound], 404);
                }
                if ($donation->purpose_key === 'zakat') {
                    return response([
                        'message' => 'The Zakat cause cannot be deleted. Assign the Zakat purpose to another cause first.',
                    ], 422);
                }
                $donation->delete();

                return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
            });
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

    private function presentationRules(): array
    {
        return [
            'display_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'icon_key' => ['nullable', 'string', Rule::in(array_keys(DonationType::ICON_OPTIONS))],
            'donation_cause_group_id' => [
                'nullable',
                'integer',
                Rule::exists('donation_cause_groups', 'id'),
            ],
        ];
    }

    private function normalizedPresentationAttributes(array $validated, bool $withDefaultOrder = false): array
    {
        $attributes = [];
        if (array_key_exists('display_order', $validated) && $validated['display_order'] !== null) {
            $attributes['display_order'] = (int) $validated['display_order'];
        } elseif ($withDefaultOrder) {
            $attributes['display_order'] = ((int) DonationType::query()->max('display_order')) + 10;
        }
        if (array_key_exists('icon_key', $validated)) {
            $attributes['icon_key'] = filled($validated['icon_key']) ? (string) $validated['icon_key'] : null;
        }
        if (array_key_exists('donation_cause_group_id', $validated)) {
            $attributes['donation_cause_group_id'] = filled($validated['donation_cause_group_id'])
                ? (int) $validated['donation_cause_group_id']
                : null;
        }

        return $attributes;
    }

    private function destinationRules(): array
    {
        return [
            'destination_type' => ['required', 'string', Rule::in(array_keys(DonationType::DESTINATION_OPTIONS))],
            'destination_name' => ['nullable', 'string', 'max:255', 'required_if:destination_type,restricted_fund'],
            'destination_category_uuid' => [
                'nullable',
                'uuid',
                'required_if:destination_type,category',
                Rule::exists('categories', 'uuid')->whereNull('deleted_at'),
            ],
            'destination_page_uuid' => [
                'nullable',
                'uuid',
                'required_if:destination_type,page',
                Rule::exists('pages', 'uuid')->whereNull('deleted_at'),
            ],
            'image_media_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('media_assets', 'uuid')
                    ->whereNull('deleted_at')
                    ->where(fn ($query) => $query->where('mime_type', 'like', 'image/%')),
            ],
        ];
    }

    private function normalizedDestinationAttributes(array $validated): array
    {
        $type = (string) $validated['destination_type'];
        $purpose = (string) ($validated['purpose_key'] ?? '');

        if ($purpose === 'zakat' && $type === 'unrestricted') {
            throw ValidationException::withMessages([
                'destination_type' => 'Zakat must use a restricted fund, program, or a specifically approved project.',
            ]);
        }

        $pageUuid = $type === 'page' ? (string) ($validated['destination_page_uuid'] ?? '') : null;
        if ($type === 'category' && !$this->destinations->isFundingCategory(
            (string) ($validated['destination_category_uuid'] ?? '')
        )) {
            throw ValidationException::withMessages([
                'destination_category_uuid' => 'Choose an active program that contains at least one published page marked as a fundable program or project.',
            ]);
        }

        $fundingPage = $type === 'page'
            ? $this->destinations->preferredFundingPublicPage($pageUuid, app()->getLocale())
            : null;
        if ($type === 'page' && !$fundingPage) {
            throw ValidationException::withMessages([
                'destination_page_uuid' => 'Choose a published public page marked as a fundable program or project.',
            ]);
        }
        if ($purpose === 'zakat' && $type === 'page' && !(bool) $fundingPage?->is_zakat_eligible) {
            throw ValidationException::withMessages([
                'destination_page_uuid' => 'Choose a page marked Zakat eligible before assigning the Zakat role.',
            ]);
        }

        $asset = !empty($validated['image_media_uuid'])
            ? MediaAsset::query()->where('uuid', $validated['image_media_uuid'])->firstOrFail()
            : null;

        return [
            'destination_type' => $type,
            'destination_name' => $type === 'restricted_fund'
                ? trim((string) ($validated['destination_name'] ?? ''))
                : null,
            'destination_category_uuid' => $type === 'category'
                ? (string) $validated['destination_category_uuid']
                : null,
            'destination_page_uuid' => $type === 'page' ? $pageUuid : null,
            'image_media_uuid' => $asset?->uuid,
            'image' => $asset?->url,
        ];
    }

    private function categoryOptions(string $locale): Collection
    {
        $options = Category::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Category $category): string => (string) ($category->uuid ?: 'row-' . $category->id))
            ->map(fn (Collection $rows) => $rows->firstWhere('language', $locale)
                ?? $rows->firstWhere('language', config('app.fallback_locale', 'en'))
                ?? $rows->first())
            ->filter(fn (?Category $category): bool => $category !== null
                && $this->destinations->isFundingCategory((string) $category->uuid))
            ->values();

        $available = $options->pluck('uuid')->filter();
        $referenced = DonationType::query()->whereNotNull('destination_category_uuid')
            ->pluck('destination_category_uuid')->filter()->unique()->diff($available);
        foreach ($referenced as $uuid) {
            $category = Category::withTrashed()->where('uuid', $uuid)->get()
                ->firstWhere('language', $locale)
                ?? Category::withTrashed()->where('uuid', $uuid)->first();
            if ($category) {
                $category->setAttribute('destination_unavailable', true);
                $options->push($category);
            }
        }

        return $options;
    }

    private function pageOptions(string $locale): Collection
    {
        $options = Page::query()
            ->publiclyAvailable()
            ->where('visibility', 'public')
            ->where('is_funding_project', true)
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Page $page): string => (string) ($page->uuid ?: 'row-' . $page->id))
            ->map(fn (Collection $rows) => $rows->firstWhere('language', $locale)
                ?? $rows->firstWhere('language', config('app.fallback_locale', 'en'))
                ?? $rows->first())
            ->filter()
            ->values()
            ->map(function (Page $page) use ($locale): Page {
                $category = Category::query()
                    ->where(fn ($query) => $query
                        ->where('id', $page->category_id)
                        ->orWhere('uuid', $page->category_id))
                    ->whereIn('language', [$locale, config('app.fallback_locale', 'en')])
                    ->first();
                $page->setAttribute('category_label', $category?->name ?: 'No program');

                return $page;
            });

        $available = $options->pluck('uuid')->filter();
        $referenced = DonationType::query()->whereNotNull('destination_page_uuid')
            ->pluck('destination_page_uuid')->filter()->unique()->diff($available);
        foreach ($referenced as $uuid) {
            $page = Page::withTrashed()->where('uuid', $uuid)->get()
                ->firstWhere('language', $locale)
                ?? Page::withTrashed()->where('uuid', $uuid)->first();
            if ($page) {
                $page->setAttribute('destination_unavailable', true);
                $page->setAttribute('category_label', 'Unavailable destination');
                $options->push($page);
            }
        }

        return $options;
    }

    /** @return array{type: string, category: string, page: string} */
    private function destinationLockIdentity(DonationType $cause): array
    {
        return [
            'type' => (string) $cause->destination_type,
            'category' => (string) $cause->destination_category_uuid,
            'page' => (string) $cause->destination_page_uuid,
        ];
    }
}
