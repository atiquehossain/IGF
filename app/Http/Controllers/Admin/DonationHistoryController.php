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
            'causeFilters' => Donation::query()
                ->whereNotNull('cause_uuid_snapshot')
                ->select(['cause_uuid_snapshot', 'cause_name_snapshot'])
                ->latest('id')
                ->get()
                ->unique('cause_uuid_snapshot')
                ->sortBy('cause_name_snapshot', SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
            'projectFilters' => $this->projectFilterOptions(),
            'projectAttribution' => $this->projectAttributionSummary($baseQuery, $projectUuid),
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

    private function projectFilterOptions(): \Illuminate\Support\Collection
    {
        $direct = Donation::query()
            ->whereNotNull('project_uuid_snapshot')
            ->where('project_uuid_snapshot', '!=', '')
            ->get(['project_uuid_snapshot', 'project_name_snapshot'])
            ->map(fn (Donation $donation): array => [
                'uuid' => (string) $donation->project_uuid_snapshot,
                'name' => (string) ($donation->project_name_snapshot ?: 'Historical project'),
            ]);
        $allocated = DonationAllocation::query()
            ->get(['page_uuid', 'page_name_snapshot'])
            ->map(fn (DonationAllocation $allocation): array => [
                'uuid' => (string) $allocation->page_uuid,
                'name' => (string) ($allocation->page_name_snapshot ?: 'Historical project'),
            ]);

        return $direct->concat($allocated)
            ->filter(fn (array $project): bool => $project['uuid'] !== '')
            ->unique('uuid')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
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
        $projects = [];
        $successfulIds = (clone $filteredDonations)
            ->where('payment_status', 'Success')
            ->select('donations.id');

        Donation::query()
            ->whereIn('id', clone $successfulIds)
            ->whereNotNull('project_uuid_snapshot')
            ->where('project_uuid_snapshot', '!=', '')
            ->when($projectUuid !== '', fn ($query) => $query->where('project_uuid_snapshot', $projectUuid))
            ->get(['id', 'project_uuid_snapshot', 'project_name_snapshot', 'amount'])
            ->each(function (Donation $donation) use (&$projects): void {
                $uuid = (string) $donation->project_uuid_snapshot;
                $projects[$uuid] ??= [
                    'uuid' => $uuid,
                    'name' => (string) ($donation->project_name_snapshot ?: 'Historical project'),
                    'direct_cents' => 0,
                    'allocated_cents' => 0,
                    'donation_ids' => [],
                ];
                $projects[$uuid]['direct_cents'] += $this->decimalToCents((string) $donation->amount);
                $projects[$uuid]['donation_ids'][(int) $donation->id] = true;
            });

        DonationAllocation::query()
            ->whereIn('donation_id', clone $successfulIds)
            ->when($projectUuid !== '', fn ($query) => $query->where('page_uuid', $projectUuid))
            ->with('donation:id,payment_status')
            ->get(['donation_id', 'page_uuid', 'page_name_snapshot', 'amount'])
            ->each(function (DonationAllocation $allocation) use (&$projects): void {
                $uuid = (string) $allocation->page_uuid;
                $projects[$uuid] ??= [
                    'uuid' => $uuid,
                    'name' => (string) ($allocation->page_name_snapshot ?: 'Historical project'),
                    'direct_cents' => 0,
                    'allocated_cents' => 0,
                    'donation_ids' => [],
                ];
                $projects[$uuid]['allocated_cents'] += $this->decimalToCents((string) $allocation->amount);
                $projects[$uuid]['donation_ids'][(int) $allocation->donation_id] = true;
            });

        return collect($projects)
            ->map(function (array $project): array {
                $totalCents = $project['direct_cents'] + $project['allocated_cents'];

                return [
                    'uuid' => $project['uuid'],
                    'name' => $project['name'],
                    'direct_amount' => $this->centsToDecimal($project['direct_cents']),
                    'allocated_amount' => $this->centsToDecimal($project['allocated_cents']),
                    'total_amount' => $this->centsToDecimal($totalCents),
                    'donation_count' => count($project['donation_ids']),
                ];
            })
            ->sortByDesc(fn (array $project): int => $this->decimalToCents($project['total_amount']))
            ->values();
    }
}
