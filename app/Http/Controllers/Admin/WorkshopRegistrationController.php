<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\BuildsApplicationDashboard;
use App\Http\Controllers\Controller;
use App\Http\Middleware\Permission;
use App\Models\Admin;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Models\WorkshopRegistrationDocument;
use App\Services\AdminAuditService;
use App\Services\AdminAuthorityService;
use App\Services\AdminPrivateSearch;
use App\Services\ApplicationExportService;
use App\Services\ApplicationListingService;
use App\Services\ApplicationPrivacyService;
use App\Services\PrivateApplicationDocumentService;
use App\Services\WorkshopRegistrationWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class WorkshopRegistrationController extends Controller
{
    use BuildsApplicationDashboard;

    private const SEARCH_SCOPE = 'workshop-registrations';

    public function __construct(
        private readonly AdminPrivateSearch $searches,
        private readonly ApplicationListingService $listings,
        private readonly ApplicationExportService $exports,
        private readonly WorkshopRegistrationWorkflowService $workflow,
        private readonly ApplicationPrivacyService $privacy,
        private readonly PrivateApplicationDocumentService $documents,
        private readonly AdminAuthorityService $authority,
        private readonly AdminAuditService $audit,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $this->validatedDashboardFilters(
            $request,
            ApplicationListingService::WORKSHOP_SORTS,
            WorkshopRegistration::STATUSES,
            'workshops',
        );
        $actor = $this->actor($request);
        $workshops = Workshop::query()
            ->with('translations:id,workshop_id,locale,title')
            ->withCount('registrations')
            ->latest('updated_at')
            ->get(['id', 'uuid', 'current_form_version_id', 'publication_status', 'updated_at']);
        $workshop = isset($filters['listing'])
            ? $workshops->firstWhere('uuid', $filters['listing'])
            : $workshops->first();
        if (!$workshop) {
            return view('admin.workshops.registrations.index', $this->indexViewData(
                $actor,
                $workshops,
                null,
                $filters,
                collect(),
                collect(),
                [],
                [],
                '',
            ));
        }

        $availableColumns = $this->dashboardAnswerColumns(
            WorkshopRegistration::class,
            'workshop_id',
            (int) $workshop->id,
            $workshop->current_form_version_id ? (int) $workshop->current_form_version_id : null,
        );
        $preference = $this->dashboardPreference($actor, $this->preferenceKey($workshop), $availableColumns);
        $filters['sort'] ??= in_array($preference['sort'], ApplicationListingService::WORKSHOP_SORTS, true)
            ? $preference['sort']
            : 'last_submitted_at';
        $filters['direction'] ??= in_array($preference['direction'], ['asc', 'desc'], true)
            ? $preference['direction']
            : 'desc';
        $privateSearch = $this->searches->current($request, self::SEARCH_SCOPE);
        $query = $this->listings->workshops($workshop, $filters, $privateSearch);
        $answerFieldKeys = collect($preference['columns'])
            ->map(fn (string $column): string => substr($column, 7))
            ->filter()
            ->values();
        if ($answerFieldKeys->isNotEmpty()) {
            $query->with(['answers' => fn ($answers) => $answers
                ->whereHas('field', fn ($fields) => $fields->whereIn('field_key', $answerFieldKeys))
                ->with(['field:id,field_key,position', 'field.options.translations'])]);
        }
        $records = $query
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->appends($this->safeDashboardQuery($filters, (string) $workshop->uuid));
        $answerValues = $answerFieldKeys->isEmpty()
            ? []
            : $records->getCollection()->mapWithKeys(fn (WorkshopRegistration $registration): array => [
                $registration->id => $this->dashboardAnswerValues($registration),
            ])->all();

        return view('admin.workshops.registrations.index', $this->indexViewData(
            $actor,
            $workshops,
            $workshop,
            $filters,
            $records,
            $availableColumns,
            $preference['columns'],
            $answerValues,
            $privateSearch,
        ));
    }

    public function search(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'listing' => ['required', 'uuid', Rule::exists('workshops', 'uuid')->whereNull('deleted_at')],
            'search' => ['required', 'string', 'max:100'],
        ]);
        if ($this->searches->store($request, self::SEARCH_SCOPE, $data['search']) === '') {
            return redirect()->route('workshop.registrations.index', ['listing' => $data['listing']])
                ->withErrors(['search' => 'Enter a name, contact detail, or registration reference.']);
        }
        $this->audit->record($this->actor($request), 'private_search.started', 'private-listing-search', context: [
            'scope' => self::SEARCH_SCOPE,
            'expires_in_minutes' => 10,
        ]);

        return redirect()->route('workshop.registrations.index', ['listing' => $data['listing']]);
    }

    public function clearSearch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'listing' => ['nullable', 'uuid', Rule::exists('workshops', 'uuid')->whereNull('deleted_at')],
        ]);
        $this->searches->forget($request, self::SEARCH_SCOPE);
        $this->audit->record($this->actor($request), 'private_search.cleared', 'private-listing-search', context: [
            'scope' => self::SEARCH_SCOPE,
        ]);

        return redirect()->route('workshop.registrations.index', array_filter([
            'listing' => $data['listing'] ?? null,
        ]));
    }

    public function show(Request $request, WorkshopRegistration $registration): View
    {
        $actor = $this->actor($request);
        $registration->load([
            'workshop.translations',
            'formVersion:id,uuid,version,state',
            'assignedAdmin:id,name,username',
            'answers.field.translations',
            'answers.field.options.translations',
            'documents.field.translations',
            'notes',
            'statusEvents',
        ]);

        return view('admin.workshops.registrations.show', [
            'title' => 'Workshop registration ' . $registration->reference_number,
            'record' => $registration,
            'listing' => $registration->workshop,
            'listingLabel' => $this->workshopLabel($registration->workshop),
            'answerRows' => $this->dashboardAnswerRows($registration),
            'assignees' => $this->activeDashboardAssignees(),
            'transitions' => WorkshopRegistrationWorkflowService::TRANSITIONS[$registration->workflow_status] ?? [],
            'criteria' => collect(),
            'actor' => $actor,
            'canEdit' => $this->allows($actor, 'workshop.registrations.edit'),
            'canDownload' => $this->allows($actor, 'workshop.registrations.download'),
            'canAnonymize' => $this->authority->isOwner($actor)
                && $this->allows($actor, 'workshop.registrations.anonymize'),
            'canDelete' => $this->authority->isOwner($actor)
                && $this->allows($actor, 'workshop.registrations.delete'),
            'routeNames' => $this->routeNames(),
            'isJob' => false,
        ]);
    }

    public function workflow(Request $request, WorkshopRegistration $registration): RedirectResponse
    {
        $data = $request->validate([
            'workflow_status' => ['required', Rule::in(WorkshopRegistration::STATUSES)],
        ]);
        $this->workflow->transition($registration, $data['workflow_status'], $this->actor($request));

        return redirect()->route('workshop.registrations.show', $registration)
            ->with(['message' => 'Registration status updated.', 'alert-type' => 'success']);
    }

    public function assign(Request $request, WorkshopRegistration $registration): RedirectResponse
    {
        $data = $request->validate([
            'assigned_to_admin_id' => [
                'nullable',
                Rule::exists('admins', 'id')->where(fn ($query) => $query->where('status', 1)),
            ],
        ]);
        $assignee = $this->activeDashboardAssignee($data['assigned_to_admin_id'] ?? null);
        $this->workflow->assign($registration, $assignee, $this->actor($request));

        return redirect()->route('workshop.registrations.show', $registration)
            ->with(['message' => 'Registration assignment updated.', 'alert-type' => 'success']);
    }

    public function addNote(Request $request, WorkshopRegistration $registration): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:20000']]);
        $this->workflow->addNote($registration, $data['body'], $this->actor($request));

        return redirect()->route('workshop.registrations.show', $registration)
            ->with(['message' => 'Private note added.', 'alert-type' => 'success']);
    }

    public function bulk(Request $request): RedirectResponse
    {
        $base = $request->validate([
            'listing' => ['required', 'uuid', Rule::exists('workshops', 'uuid')->whereNull('deleted_at')],
            'operation' => ['required', Rule::in(['status', 'assignment', 'preferences'])],
        ]);
        $actor = $this->actor($request);
        $workshop = Workshop::query()->where('uuid', $base['listing'])->firstOrFail();

        if ($base['operation'] === 'preferences') {
            $columns = $this->dashboardAnswerColumns(
                WorkshopRegistration::class,
                'workshop_id',
                (int) $workshop->id,
                $workshop->current_form_version_id ? (int) $workshop->current_form_version_id : null,
            );
            $this->saveDashboardPreference(
                $request,
                $actor,
                $this->preferenceKey($workshop),
                $columns,
                ApplicationListingService::WORKSHOP_SORTS,
            );

            return redirect()->route('workshop.registrations.index', ['listing' => $workshop->uuid])
                ->with(['message' => 'Table preferences saved.', 'alert-type' => 'success']);
        }

        $data = $request->validate([
            'registration_ids' => ['required', 'array', 'min:1', 'max:' . WorkshopRegistrationWorkflowService::MAX_BULK_RECORDS],
            'registration_ids.*' => ['required', 'integer', 'distinct'],
            'workflow_status' => ['required_if:operation,status', 'nullable', Rule::in(WorkshopRegistration::STATUSES)],
            'assigned_to_admin_id' => [
                'nullable',
                Rule::exists('admins', 'id')->where(fn ($query) => $query->where('status', 1)),
            ],
        ]);
        $ids = collect($data['registration_ids'])->map(fn (mixed $id): int => (int) $id)->unique()->values();
        $ownedIds = WorkshopRegistration::query()
            ->where('workshop_id', $workshop->id)
            ->whereKey($ids)
            ->pluck('id');
        if ($ownedIds->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'registration_ids' => 'Every selected registration must belong to the chosen workshop.',
            ]);
        }

        if ($base['operation'] === 'status') {
            $this->workflow->bulkTransition($ids, (string) $data['workflow_status'], $actor);
            $message = $ids->count() . ' registration statuses updated.';
        } else {
            $assignee = $this->activeDashboardAssignee($data['assigned_to_admin_id'] ?? null);
            $this->workflow->bulkAssign($ids, $assignee, $actor);
            $message = $ids->count() . ' registration assignments updated.';
        }

        return redirect()->route('workshop.registrations.index', ['listing' => $workshop->uuid])
            ->with(['message' => $message, 'alert-type' => 'success']);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedDashboardFilters(
            $request,
            ApplicationListingService::WORKSHOP_SORTS,
            WorkshopRegistration::STATUSES,
            'workshops',
        );
        if (!isset($filters['listing'])) {
            throw ValidationException::withMessages(['listing' => 'Choose a workshop to export.']);
        }
        $request->validate([
            'columns' => ['nullable', 'array', 'max:50'],
            'columns.*' => ['string', 'max:120', 'distinct'],
        ]);
        $workshop = Workshop::query()->where('uuid', $filters['listing'])->firstOrFail();
        $query = $this->listings->workshops(
            $workshop,
            $filters,
            $this->searches->current($request, self::SEARCH_SCOPE),
        );

        return $this->exports->workshops(
            $workshop,
            $query,
            array_values($request->input('columns', [])),
            $this->actor($request),
            $filters,
        );
    }

    public function download(
        Request $request,
        WorkshopRegistration $registration,
        WorkshopRegistrationDocument $document,
    ): BinaryFileResponse {
        abort_unless((int) $document->workshop_registration_id === (int) $registration->id, 404);
        $response = $this->documents->download(
            (string) $document->disk,
            (string) $document->path,
            (int) $document->bytes,
            (string) $document->sha256,
            (string) $document->original_name,
        );
        $this->audit->record(
            $this->actor($request),
            'workshop.registration.document_downloaded',
            $registration,
            context: [
                'document_id' => (int) $document->id,
                'workshop_id' => (int) $registration->workshop_id,
            ],
        );

        return $response;
    }

    public function anonymize(Request $request, WorkshopRegistration $registration): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->authority->assertOwner($actor);
        $this->assertDashboardConfirmation($request, 'ANONYMIZE ' . $registration->reference_number);
        $this->privacy->anonymizeWorkshop($registration, $actor);

        return redirect()->route('workshop.registrations.show', $registration)
            ->with(['message' => 'Registrant identity and submitted content anonymized.', 'alert-type' => 'success']);
    }

    public function delete(Request $request, WorkshopRegistration $registration): RedirectResponse
    {
        return $this->deleteRecord($request, $registration);
    }

    public function destroy(Request $request, WorkshopRegistration $registration): RedirectResponse
    {
        return $this->deleteRecord($request, $registration);
    }

    private function deleteRecord(Request $request, WorkshopRegistration $registration): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->authority->assertOwner($actor);
        $this->assertDashboardConfirmation($request, 'DELETE ' . $registration->reference_number);
        $workshop = $registration->workshop()->firstOrFail();
        $this->privacy->deleteWorkshop($registration, $actor);

        return redirect()->route('workshop.registrations.index', ['listing' => $workshop->uuid])
            ->with(['message' => 'Registration permanently deleted.', 'alert-type' => 'success']);
    }

    /** @return array<string, mixed> */
    private function indexViewData(
        Admin $actor,
        Collection $workshops,
        ?Workshop $workshop,
        array $filters,
        mixed $records,
        Collection $availableColumns,
        array $visibleColumns,
        array $answerValues,
        string $privateSearch,
    ): array {
        return [
            'title' => 'Workshop registrations',
            'sectionLabel' => 'Workshops',
            'recordLabel' => 'registration',
            'recordsLabel' => 'registrations',
            'listings' => $workshops,
            'listing' => $workshop,
            'listingLabel' => $workshop ? $this->workshopLabel($workshop) : '',
            'listingTitle' => fn (Workshop $listing): string => $this->workshopLabel($listing),
            'records' => $records,
            'statuses' => WorkshopRegistration::STATUSES,
            'sorts' => ApplicationListingService::WORKSHOP_SORTS,
            'filters' => $filters,
            'privateSearch' => $privateSearch,
            'assignees' => $this->activeDashboardAssignees(),
            'availableColumns' => $availableColumns,
            'visibleColumns' => $visibleColumns,
            'answerValues' => $answerValues,
            'fixedExportColumns' => ApplicationExportService::WORKSHOP_COLUMNS,
            'canEdit' => $this->allows($actor, 'workshop.registrations.edit'),
            'canExport' => $this->allows($actor, 'workshop.registrations.export'),
            'routeNames' => $this->routeNames(),
            'isJob' => false,
        ];
    }

    private function actor(Request $request): Admin
    {
        $actor = $request->user('admin');
        abort_unless($actor instanceof Admin, 401);

        return $actor;
    }

    private function allows(Admin $actor, string $routeName): bool
    {
        return app(Permission::class)->allows($actor, $routeName);
    }

    private function preferenceKey(Workshop $workshop): string
    {
        return 'workshop.registrations:' . $workshop->id;
    }

    private function workshopLabel(Workshop $workshop): string
    {
        return (string) ($workshop->translations->firstWhere('locale', 'en')?->title
            ?: 'Workshop #' . $workshop->id);
    }

    /** @return array<string, string> */
    private function routeNames(): array
    {
        return [
            'index' => 'workshop.registrations.index',
            'show' => 'workshop.registrations.show',
            'search' => 'workshop.registrations.search',
            'search_clear' => 'workshop.registrations.search.clear',
            'bulk' => 'workshop.registrations.bulk',
            'workflow' => 'workshop.registrations.workflow',
            'assign' => 'workshop.registrations.assign',
            'note' => 'workshop.registrations.notes.store',
            'export' => 'workshop.registrations.export',
            'download' => 'workshop.registrations.download',
            'anonymize' => 'workshop.registrations.anonymize',
            'delete' => 'workshop.registrations.delete',
        ];
    }
}
