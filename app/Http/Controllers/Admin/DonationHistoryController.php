<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Permission;
use Illuminate\Http\Request;

use App\Models\Donation;
use App\Models\DonationAllocation;
use App\Models\DonationType;
use App\Models\Category;
use App\Models\Page;
use App\Services\DonationDestinationService;
use App\Services\SSLCommerzService;
use App\Services\AdminPrivateSearch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DonationHistoryController extends Controller
{
    public function __construct(
        private SSLCommerzService $payments,
        private DonationDestinationService $destinations,
        private AdminPrivateSearch $privateSearch
    )
    {
    }

    // Show Donation Records
    public function index(Request $request)
    {
        if ($request->query->has('search')) {
            return redirect()->route('donations.index', $request->only([
                'status', 'destination_type', 'cause_uuid', 'project_uuid',
            ]));
        }

        $search = $this->privateSearch->current($request, 'donations');
        $query = Donation::query()
            ->with(['donationType', 'gatewayTransaction', 'allocations.allocator'])
            ->when($search !== '', function ($query) use ($search) {
                $pattern = '%' . $search . '%';
                $query->where(function ($builder) use ($pattern): void {
                    $builder->where('transaction_id', 'like', $pattern)
                        ->orWhere('donor_name', 'like', $pattern)
                        ->orWhere('email', 'like', $pattern)
                        ->orWhere('phone', 'like', $pattern)
                        ->orWhere('cause_name_snapshot', 'like', $pattern)
                        ->orWhere('destination_name_snapshot', 'like', $pattern)
                        ->orWhere('project_name_snapshot', 'like', $pattern)
                        ->orWhereHas('allocations', fn ($allocation) => $allocation
                            ->where('page_name_snapshot', 'like', $pattern)
                            ->orWhere('note', 'like', $pattern))
                        ->orWhereHas('donationType', fn ($cause) => $cause->where('name', 'like', $pattern));
                });
            });

        $status = in_array((string) $request->query('status'), ['Pending', 'Success', 'Review', 'Failed', 'Cancelled'], true)
            ? (string) $request->query('status')
            : '';
        $destinationType = array_key_exists((string) $request->query('destination_type'), DonationType::DESTINATION_OPTIONS)
            ? (string) $request->query('destination_type')
            : '';
        $causeUuid = trim((string) $request->query('cause_uuid'));
        $projectUuid = trim((string) $request->query('project_uuid'));

        $query
            ->when($status !== '', fn ($builder) => $builder->where('payment_status', $status))
            ->when($destinationType !== '', fn ($builder) => $builder->where('destination_type_snapshot', $destinationType))
            ->when($causeUuid !== '', fn ($builder) => $builder->where(function ($cause) use ($causeUuid): void {
                $cause->where('cause_uuid_snapshot', $causeUuid)->orWhere('payment_cause', $causeUuid);
            }));

        // Keep a copy without the project constraint. It is used to calculate
        // the amount actually attributed to a project (direct gifts plus only
        // the allocated portions of broad gifts).
        $baseQuery = clone $query;
        $query->when($projectUuid !== '', fn ($builder) => $builder->where(function ($project) use ($projectUuid): void {
            $project->where('project_uuid_snapshot', $projectUuid)
                ->orWhereHas('allocations', fn ($allocation) => $allocation->where('page_uuid', $projectUuid));
        }));

        $successfulQuery = (clone $query)->where('payment_status', 'Success');
        $successfulCount = (clone $successfulQuery)->count();
        if ($projectUuid !== '') {
            $successfulBase = (clone $baseQuery)->where('payment_status', 'Success');
            $directCents = $this->decimalToCents((string) ((clone $successfulBase)
                ->where('project_uuid_snapshot', $projectUuid)
                ->sum('amount') ?: '0.00'));
            $allocatedCents = $this->decimalToCents((string) (DonationAllocation::query()
                ->where('page_uuid', $projectUuid)
                ->whereIn('donation_id', (clone $successfulBase)->select('donations.id'))
                ->sum('amount') ?: '0.00'));
            $successfulTotal = $this->centsToDecimal($directCents + $allocatedCents);
        } else {
            $successfulTotal = $this->centsToDecimal($this->decimalToCents(
                (string) ((clone $successfulQuery)->sum('amount') ?: '0.00')
            ));
        }

        $causeAttribution = $this->causeAttributionSummary($baseQuery, $projectUuid);

        $donations = $query
            ->orderBy('id', 'desc')
            ->paginate(20);

        $canAllocate = app(Permission::class)->allows(
            Auth::guard('admin')->user(),
            'donations.allocate'
        );
        $donations->getCollection()->each(function (Donation $donation) use ($canAllocate): void {
            $allocatedCents = $donation->allocations
                ->sum(fn (DonationAllocation $allocation): int => $this->decimalToCents((string) $allocation->amount));
            $amountCents = $this->decimalToCents((string) $donation->amount);
            $direct = (bool) $donation->project_uuid_snapshot
                || $donation->destination_type_snapshot === 'page';
            $unresolvedLegacy = !$this->destinations->hasResolvedDesignation($donation);
            $remainingCents = $direct ? 0 : max(0, $amountCents - $allocatedCents);

            $donation->setAttribute('allocation_direct', $direct);
            $donation->setAttribute('allocation_unresolved_legacy', $unresolvedLegacy);
            $donation->setAttribute('allocated_amount', $this->centsToDecimal($direct ? $amountCents : $allocatedCents));
            $donation->setAttribute('allocation_remaining', $this->centsToDecimal($remainingCents));
            $donation->setAttribute('allocation_request_token', $canAllocate && !$unresolvedLegacy && $remainingCents > 0
                ? $this->issueAllocationToken($donation)
                : null);
            $donation->setAttribute('allocation_options', $canAllocate && !$unresolvedLegacy && $remainingCents > 0
                && strtolower((string) $donation->payment_status) === 'success'
                ? $this->destinations->allocationPages($donation)->map(fn (Page $page): array => [
                    'uuid' => (string) $page->uuid,
                    'name' => (string) $page->name,
                    'is_zakat_eligible' => (bool) $page->is_zakat_eligible,
                ])->values()->all()
                : []);
        });

        return view('admin.donations.index', [
            'title' => 'Donation Records',
            'donations' => $donations,
            'search' => $search,
            'statusFilter' => $status,
            'destinationTypeFilter' => $destinationType,
            'causeUuidFilter' => $causeUuid,
            'projectUuidFilter' => $projectUuid,
            'destinationOptions' => DonationType::DESTINATION_OPTIONS,
            'causeFilters' => $this->causeFilterOptions(),
            'projectFilters' => $this->projectFilterOptions(),
            'projectAttribution' => $this->projectAttributionSummary($baseQuery, $projectUuid),
            'causeAttribution' => $causeAttribution,
            'causeAttributionChart' => $this->causeAttributionChart($causeAttribution),
            'successfulCount' => $successfulCount,
            'successfulTotal' => $successfulTotal,
            'canAllocate' => $canAllocate,
            'canResolveReview' => app(Permission::class)->allows(
                Auth::guard('admin')->user(),
                'donations.review.resolve'
            ),
        ]);
    }

    public function resolveReview(Request $request, Donation $donation)
    {
        $validated = $request->validate([
            'resolution_note' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $resolved = $this->payments->resolveReviewedDonation(
            (string) $donation->transaction_id,
            (int) Auth::guard('admin')->id(),
            (string) $validated['resolution_note']
        );

        if (!$resolved) {
            return back()->withErrors([
                'resolution_note' => 'This donation could not be resolved. Confirm that the gateway payment is verified and still under review.',
            ]);
        }

        return back()->with([
            'message' => 'The reviewed donation was resolved and the audit details were recorded.',
            'alert-type' => 'success',
        ]);
    }

    public function allocate(Request $request, Donation $donation)
    {
        $validated = $request->validate([
            'request_token' => ['required', 'string', 'max:110'],
            'page_uuid' => ['required', 'uuid'],
            'amount' => ['required', 'string', 'regex:/^(?:0|[1-9]\d{0,5})(?:\.\d{1,2})?$/'],
            'note' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm_allocation' => ['accepted'],
        ]);

        if (!$this->validAllocationToken($donation, (string) $validated['request_token'])) {
            throw ValidationException::withMessages([
                'request_token' => 'This allocation form has expired. Refresh Donation Records and try again.',
            ]);
        }

        $duplicate = DB::transaction(function () use ($donation, $validated): bool {
            $locked = Donation::query()->whereKey($donation->getKey())->lockForUpdate()->firstOrFail();
            $existing = DonationAllocation::query()
                ->where('request_token', $validated['request_token'])
                ->first();
            if ($existing) {
                if ((int) $existing->donation_id !== (int) $locked->id) {
                    throw ValidationException::withMessages([
                        'request_token' => 'This allocation request belongs to a different donation.',
                    ]);
                }

                return true;
            }

            if (strtolower((string) $locked->payment_status) !== 'success') {
                throw ValidationException::withMessages([
                    'amount' => 'Only a successfully verified donation can be allocated.',
                ]);
            }
            if ($locked->project_uuid_snapshot || $locked->destination_type_snapshot === 'page') {
                throw ValidationException::withMessages([
                    'amount' => 'This gift was already fully attributed to the project chosen by the donor.',
                ]);
            }
            if (!$this->destinations->hasResolvedDesignation($locked) || !in_array(
                (string) $locked->destination_type_snapshot,
                ['unrestricted', 'restricted_fund', 'category'],
                true
            )) {
                throw ValidationException::withMessages([
                    'amount' => 'This legacy gift has no verified donor designation. Allocation is blocked until a separate audited reconciliation workflow records that evidence.',
                ]);
            }

            // Lock every locale row for the selected logical project before
            // re-reading publication and Zakat eligibility. A concurrent
            // builder save must either finish first (and be observed here) or
            // wait until this valid allocation is committed.
            $this->destinations->lockPageRows((string) $validated['page_uuid']);
            $page = $this->destinations->allocationPages($locked)
                ->firstWhere('uuid', (string) $validated['page_uuid']);
            if (!$page) {
                throw ValidationException::withMessages([
                    'page_uuid' => $locked->purpose_key_snapshot === 'zakat'
                        ? 'Choose a published project that is marked Zakat eligible and remains within this gift’s destination.'
                        : 'Choose a published project within this gift’s destination.',
                ]);
            }

            $amountCents = $this->decimalToCents((string) $validated['amount']);
            $donationCents = $this->decimalToCents((string) $locked->amount);
            $allocatedCents = DonationAllocation::query()
                ->where('donation_id', $locked->id)
                ->get(['amount'])
                ->sum(fn (DonationAllocation $allocation): int => $this->decimalToCents((string) $allocation->amount));
            $remainingCents = max(0, $donationCents - $allocatedCents);

            if ($amountCents < 1 || $amountCents > $remainingCents) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter an amount no greater than the remaining BDT ' . number_format($remainingCents / 100, 2) . '.',
                ]);
            }

            $category = $this->categoryForPage($page);
            DonationAllocation::create([
                'request_token' => $validated['request_token'],
                'donation_id' => $locked->id,
                'page_uuid' => $page->uuid,
                'page_name_snapshot' => $page->name,
                'category_uuid_snapshot' => $category?->uuid,
                'category_name_snapshot' => $category?->name,
                'amount' => $this->centsToDecimal($amountCents),
                'note' => trim((string) $validated['note']),
                'allocated_by' => (int) Auth::guard('admin')->id(),
                'allocated_by_name_snapshot' => trim((string) Auth::guard('admin')->user()?->name)
                    ?: 'Historical administrator',
            ]);

            return false;
        }, 3);

        return back()->with([
            'message' => $duplicate
                ? 'This allocation was already recorded. No duplicate amount was added.'
                : 'Allocation recorded. The donor’s original designation and this audit entry are preserved.',
            'alert-type' => 'success',
        ]);
    }

    private function issueAllocationToken(Donation $donation): string
    {
        $nonce = (string) Str::uuid();

        return $nonce . '.' . hash_hmac('sha256', $donation->uuid . '|' . $nonce, (string) config('app.key'));
    }

    private function validAllocationToken(Donation $donation, string $token): bool
    {
        if (!preg_match('/^([0-9a-f-]{36})\.([0-9a-f]{64})$/i', $token, $matches)) {
            return false;
        }

        $expected = hash_hmac('sha256', $donation->uuid . '|' . $matches[1], (string) config('app.key'));

        return hash_equals($expected, strtolower($matches[2]));
    }

    private function decimalToCents(string $value): int
    {
        $value = trim($value);
        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            return 0;
        }

        return ((int) $matches[1] * 100)
            + (int) str_pad((string) ($matches[2] ?? ''), 2, '0');
    }

    private function centsToDecimal(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function categoryForPage(Page $page): ?Category
    {
        $key = trim((string) $page->category_id);
        if ($key === '') {
            return null;
        }

        return Category::withTrashed()
            ->where(fn ($query) => $query->where('id', $key)->orWhere('uuid', $key))
            ->where('language', app()->getLocale())
            ->first()
            ?? Category::withTrashed()
                ->where(fn ($query) => $query->where('id', $key)->orWhere('uuid', $key))
                ->first();
    }

    private function causeFilterOptions(): \Illuminate\Support\Collection
    {
        $latestIds = Donation::query()
            ->whereNotNull('cause_uuid_snapshot')
            ->where('cause_uuid_snapshot', '!=', '')
            ->selectRaw('cause_uuid_snapshot, MAX(id) AS latest_id')
            ->groupBy('cause_uuid_snapshot');

        return DB::table('donations as cause_snapshots')
            ->joinSub($latestIds->toBase(), 'latest_causes', fn ($join) => $join
                ->on('cause_snapshots.id', '=', 'latest_causes.latest_id'))
            ->get([
                'cause_snapshots.cause_uuid_snapshot',
                'cause_snapshots.cause_name_snapshot',
            ])
            ->sortBy('cause_name_snapshot', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function projectFilterOptions(): \Illuminate\Support\Collection
    {
        $latestDirectIds = Donation::query()
            ->whereNotNull('project_uuid_snapshot')
            ->where('project_uuid_snapshot', '!=', '')
            ->selectRaw('project_uuid_snapshot AS uuid, MAX(id) AS latest_id')
            ->groupBy('project_uuid_snapshot');
        $direct = DB::table('donations as project_snapshots')
            ->joinSub($latestDirectIds->toBase(), 'latest_direct_projects', fn ($join) => $join
                ->on('project_snapshots.id', '=', 'latest_direct_projects.latest_id'))
            ->get([
                'project_snapshots.project_uuid_snapshot AS uuid',
                'project_snapshots.project_name_snapshot AS name',
            ])
            ->map(fn (object $project): array => [
                'uuid' => (string) $project->uuid,
                'name' => (string) ($project->name ?: 'Historical project'),
            ]);

        $latestAllocationIds = DonationAllocation::query()
            ->whereNotNull('page_uuid')
            ->where('page_uuid', '!=', '')
            ->selectRaw('page_uuid AS uuid, MAX(id) AS latest_id')
            ->groupBy('page_uuid');
        $allocated = DB::table('donation_allocations as allocation_snapshots')
            ->joinSub($latestAllocationIds->toBase(), 'latest_allocated_projects', fn ($join) => $join
                ->on('allocation_snapshots.id', '=', 'latest_allocated_projects.latest_id'))
            ->get([
                'allocation_snapshots.page_uuid AS uuid',
                'allocation_snapshots.page_name_snapshot AS name',
            ])
            ->map(fn (object $project): array => [
                'uuid' => (string) $project->uuid,
                'name' => (string) ($project->name ?: 'Historical project'),
            ]);

        return $direct->concat($allocated)
            ->filter(fn (array $project): bool => $project['uuid'] !== '')
            ->unique('uuid')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Successful giving grouped by the donor's immutable cause snapshot.
     * When a project is selected, direct gifts contribute their full amount
     * while broad gifts contribute only allocations made to that project.
     */
    private function causeAttributionSummary(
        \Illuminate\Database\Eloquent\Builder $filteredDonations,
        string $projectUuid = ''
    ): \Illuminate\Support\Collection
    {
        $isLegacyExpression = "CASE WHEN cause_uuid_snapshot IS NULL OR TRIM(cause_uuid_snapshot) = '' THEN 1 ELSE 0 END";
        $identityExpression = "CASE WHEN cause_uuid_snapshot IS NULL OR TRIM(cause_uuid_snapshot) = '' "
            . "THEN LOWER(COALESCE(NULLIF(TRIM(cause_slug_snapshot), ''), 'unresolved')) "
            . 'ELSE LOWER(cause_uuid_snapshot) END';
        $rows = DB::query()
            ->fromSub($this->attributedCauseRows($filteredDonations, $projectUuid), 'attributed')
            ->selectRaw("{$identityExpression} AS attribution_identity")
            ->selectRaw("{$isLegacyExpression} AS is_legacy")
            ->selectRaw('MAX(donation_id) AS latest_donation_id')
            ->selectRaw('ROUND(SUM(attributed_amount) * 100, 0) AS total_cents')
            ->selectRaw('COUNT(DISTINCT donation_id) AS donation_count')
            ->groupByRaw("{$identityExpression}, {$isLegacyExpression}")
            ->havingRaw('SUM(attributed_amount) > 0')
            ->orderByRaw('MIN(donation_id)')
            ->get();
        $names = DB::table('donations')
            ->whereIn('id', $rows->pluck('latest_donation_id')->filter())
            ->pluck('cause_name_snapshot', 'id');
        $totalCents = (int) $rows->sum(fn (object $row): int => (int) round((float) $row->total_cents));

        return $rows
            ->map(function (object $row) use ($names, $totalCents): array {
                $isLegacy = (bool) $row->is_legacy;
                $amountCents = (int) round((float) $row->total_cents);

                return [
                    'key' => ($isLegacy ? 'legacy:' : 'cause:') . (string) $row->attribution_identity,
                    'name' => $this->causeAttributionName(
                        (string) $names->get($row->latest_donation_id, ''),
                        $isLegacy
                    ),
                    'amount' => $this->centsToDecimal($amountCents),
                    'donation_count' => (int) $row->donation_count,
                    'percentage' => $this->percentageFromCents($amountCents, $totalCents),
                    'is_legacy' => $isLegacy,
                ];
            })
            ->sortByDesc(fn (array $cause): int => $this->decimalToCents($cause['amount']))
            ->values();
    }

    private function attributedCauseRows(
        \Illuminate\Database\Eloquent\Builder $filteredDonations,
        string $projectUuid
    ): \Illuminate\Database\Query\Builder {
        $successful = (clone $filteredDonations)->where('payment_status', 'Success');
        $columns = implode(', ', [
            'donations.id AS donation_id',
            'donations.cause_uuid_snapshot',
            'donations.cause_slug_snapshot',
            'donations.cause_name_snapshot',
            'donations.amount AS attributed_amount',
        ]);

        if ($projectUuid === '') {
            return (clone $successful)->selectRaw($columns)->toBase();
        }

        $direct = (clone $successful)
            ->where('project_uuid_snapshot', $projectUuid)
            ->selectRaw($columns)
            ->toBase();
        $eligible = (clone $successful)->select([
            'donations.id',
            'donations.cause_uuid_snapshot',
            'donations.cause_slug_snapshot',
            'donations.cause_name_snapshot',
        ]);
        $allocated = DB::table('donation_allocations as attributed_allocations')
            ->joinSub($eligible->toBase(), 'eligible_donations', fn ($join) => $join
                ->on('attributed_allocations.donation_id', '=', 'eligible_donations.id'))
            ->where('attributed_allocations.page_uuid', $projectUuid)
            ->selectRaw(implode(', ', [
                'eligible_donations.id AS donation_id',
                'eligible_donations.cause_uuid_snapshot',
                'eligible_donations.cause_slug_snapshot',
                'eligible_donations.cause_name_snapshot',
                'attributed_allocations.amount AS attributed_amount',
            ]));

        return $direct->unionAll($allocated);
    }

    private function causeAttributionName(string $snapshotName, bool $isLegacy): string
    {
        $snapshotName = trim($snapshotName);
        if (!$isLegacy) {
            return $snapshotName !== '' ? $snapshotName : 'Historical cause';
        }

        return $snapshotName === '' || strtolower($snapshotName) === 'unspecified legacy donation'
            ? 'Unresolved legacy designation'
            : $snapshotName . ' (legacy)';
    }

    /**
     * Keep the visual legible: show the eight largest causes and combine the
     * remainder. The full, accessible cause table still contains every row.
     */
    private function causeAttributionChart(\Illuminate\Support\Collection $summary): array
    {
        $chart = $summary->take(8)->map(fn (array $cause): array => [
            'name' => $cause['name'],
            'amount' => $cause['amount'],
            'donation_count' => $cause['donation_count'],
            'percentage' => $cause['percentage'],
        ])->values();
        $remaining = $summary->slice(8);

        if ($remaining->isNotEmpty()) {
            $otherCents = $remaining->sum(
                fn (array $cause): int => $this->decimalToCents($cause['amount'])
            );
            $totalCents = $summary->sum(
                fn (array $cause): int => $this->decimalToCents($cause['amount'])
            );
            $chart->push([
                'name' => 'Other causes',
                'amount' => $this->centsToDecimal($otherCents),
                'donation_count' => $remaining->sum('donation_count'),
                'percentage' => $this->percentageFromCents($otherCents, $totalCents),
            ]);
        }

        return $chart->all();
    }

    private function percentageFromCents(int $partCents, int $totalCents): string
    {
        if ($partCents <= 0 || $totalCents <= 0) {
            return '0.00';
        }

        $basisPoints = intdiv(($partCents * 10000) + intdiv($totalCents, 2), $totalCents);

        return $this->centsToDecimal($basisPoints);
    }

    /**
     * Successful attribution by project. A partially allocated broad gift
     * contributes only its allocation amount; it never inflates the project
     * total by the donor's full gift.
     */
    private function projectAttributionSummary(
        \Illuminate\Database\Eloquent\Builder $filteredDonations,
        string $projectUuid = ''
    ): \Illuminate\Support\Collection
    {
        $successful = (clone $filteredDonations)->where('payment_status', 'Success');
        $direct = (clone $successful)
            ->whereNotNull('project_uuid_snapshot')
            ->where('project_uuid_snapshot', '!=', '')
            ->when($projectUuid !== '', fn ($query) => $query->where('project_uuid_snapshot', $projectUuid))
            ->selectRaw(implode(', ', [
                'donations.project_uuid_snapshot AS project_uuid',
                'donations.id AS donation_id',
                'donations.amount AS direct_amount',
                '0 AS allocated_amount',
                'donations.id AS direct_reference_id',
                'NULL AS allocation_reference_id',
            ]))
            ->toBase();
        $eligible = (clone $successful)->select('donations.id');
        $allocated = DB::table('donation_allocations as project_allocations')
            ->joinSub($eligible->toBase(), 'eligible_donations', fn ($join) => $join
                ->on('project_allocations.donation_id', '=', 'eligible_donations.id'))
            ->when($projectUuid !== '', fn ($query) => $query->where('project_allocations.page_uuid', $projectUuid))
            ->selectRaw(implode(', ', [
                'project_allocations.page_uuid AS project_uuid',
                'project_allocations.donation_id',
                '0 AS direct_amount',
                'project_allocations.amount AS allocated_amount',
                'NULL AS direct_reference_id',
                'project_allocations.id AS allocation_reference_id',
            ]));
        $rows = DB::query()
            ->fromSub($direct->unionAll($allocated), 'project_attribution')
            ->select('project_uuid')
            ->selectRaw('ROUND(SUM(direct_amount) * 100, 0) AS direct_cents')
            ->selectRaw('ROUND(SUM(allocated_amount) * 100, 0) AS allocated_cents')
            ->selectRaw('COUNT(DISTINCT donation_id) AS donation_count')
            ->selectRaw('MIN(direct_reference_id) AS direct_reference_id')
            ->selectRaw('MIN(allocation_reference_id) AS allocation_reference_id')
            ->groupBy('project_uuid')
            ->get();
        $directNames = DB::table('donations')
            ->whereIn('id', $rows->pluck('direct_reference_id')->filter())
            ->pluck('project_name_snapshot', 'id');
        $allocationNames = DB::table('donation_allocations')
            ->whereIn('id', $rows->pluck('allocation_reference_id')->filter())
            ->pluck('page_name_snapshot', 'id');

        return $rows
            ->map(function (object $project) use ($directNames, $allocationNames): array {
                $directCents = (int) round((float) $project->direct_cents);
                $allocatedCents = (int) round((float) $project->allocated_cents);
                $name = (string) ($directNames->get($project->direct_reference_id)
                    ?: $allocationNames->get($project->allocation_reference_id)
                    ?: 'Historical project');

                return [
                    'uuid' => (string) $project->project_uuid,
                    'name' => $name,
                    'direct_amount' => $this->centsToDecimal($directCents),
                    'allocated_amount' => $this->centsToDecimal($allocatedCents),
                    'total_amount' => $this->centsToDecimal($directCents + $allocatedCents),
                    'donation_count' => (int) $project->donation_count,
                ];
            })
            ->sortByDesc(fn (array $project): int => $this->decimalToCents($project['total_amount']))
            ->values();
    }
}
