<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\BuildsApplicationDashboard;
use App\Http\Controllers\Controller;
use App\Http\Middleware\Permission;
use App\Models\Admin;
use App\Models\JobApplication;
use App\Models\JobApplicationDocument;
use App\Models\JobPosting;
use App\Models\JobScorecardCriterion;
use App\Services\AdminAuditService;
use App\Services\AdminAuthorityService;
use App\Services\AdminPrivateSearch;
use App\Services\ApplicationExportService;
use App\Services\ApplicationListingService;
use App\Services\ApplicationPrivacyService;
use App\Services\JobApplicationWorkflowService;
use App\Services\PrivateApplicationDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class JobApplicationController extends Controller
{
    use BuildsApplicationDashboard;

    private const SEARCH_SCOPE = 'recruitment-applications';

    public function __construct(
        private readonly AdminPrivateSearch $searches,
        private readonly ApplicationListingService $listings,
        private readonly ApplicationExportService $exports,
        private readonly JobApplicationWorkflowService $workflow,
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
            ApplicationListingService::JOB_SORTS,
            JobApplication::STATUSES,
            'job_postings',
        );
        $actor = $this->actor($request);
        $jobs = JobPosting::query()
            ->with('translations:id,job_posting_id,locale,title')
            ->withCount('applications')
            ->latest('updated_at')
            ->get(['id', 'uuid', 'current_form_version_id', 'publication_status', 'updated_at']);
        $job = isset($filters['listing'])
            ? $jobs->firstWhere('uuid', $filters['listing'])
            : $jobs->first();
        if (!$job) {
            return view('admin.recruitment.applications.index', $this->indexViewData(
                $actor,
                $jobs,
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
            JobApplication::class,
            'job_posting_id',
            (int) $job->id,
            $job->current_form_version_id ? (int) $job->current_form_version_id : null,
        );
        $preference = $this->dashboardPreference($actor, $this->preferenceKey($job), $availableColumns);
        $filters['sort'] ??= in_array($preference['sort'], ApplicationListingService::JOB_SORTS, true)
            ? $preference['sort']
            : 'last_submitted_at';
        $filters['direction'] ??= in_array($preference['direction'], ['asc', 'desc'], true)
            ? $preference['direction']
            : 'desc';
        $privateSearch = $this->searches->current($request, self::SEARCH_SCOPE);
        $query = $this->listings->jobs($job, $filters, $privateSearch);
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
            ->appends($this->safeDashboardQuery($filters, (string) $job->uuid));
        $answerValues = $answerFieldKeys->isEmpty()
            ? []
            : $records->getCollection()->mapWithKeys(fn (JobApplication $application): array => [
                $application->id => $this->dashboardAnswerValues($application),
            ])->all();

        return view('admin.recruitment.applications.index', $this->indexViewData(
            $actor,
            $jobs,
            $job,
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
            'listing' => ['required', 'uuid', Rule::exists('job_postings', 'uuid')->whereNull('deleted_at')],
            'search' => ['required', 'string', 'max:100'],
        ]);
        if ($this->searches->store($request, self::SEARCH_SCOPE, $data['search']) === '') {
            return redirect()->route('recruitment.applications.index', ['listing' => $data['listing']])
                ->withErrors(['search' => 'Enter a name, contact detail, or application reference.']);
        }
        $this->audit->record($this->actor($request), 'private_search.started', 'private-listing-search', context: [
            'scope' => self::SEARCH_SCOPE,
            'expires_in_minutes' => 10,
        ]);

        return redirect()->route('recruitment.applications.index', ['listing' => $data['listing']]);
    }

    public function clearSearch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'listing' => ['nullable', 'uuid', Rule::exists('job_postings', 'uuid')->whereNull('deleted_at')],
        ]);
        $this->searches->forget($request, self::SEARCH_SCOPE);
        $this->audit->record($this->actor($request), 'private_search.cleared', 'private-listing-search', context: [
            'scope' => self::SEARCH_SCOPE,
        ]);

        return redirect()->route('recruitment.applications.index', array_filter([
            'listing' => $data['listing'] ?? null,
        ]));
    }

    public function show(Request $request, JobApplication $application): View
    {
        $actor = $this->actor($request);
        $application->load([
            'jobPosting.translations',
            'formVersion:id,uuid,version,state',
            'assignedAdmin:id,name,username',
            'answers.field.translations',
            'answers.field.options.translations',
            'documents.field.translations',
            'notes',
            'statusEvents',
            'scores.reviewerAdmin:id,name,username',
        ]);
        $criteria = $application->jobPosting->scorecardCriteria()
            ->where('is_enabled', true)
            ->get();

        return view('admin.recruitment.applications.show', [
            'title' => 'Job application ' . $application->reference_number,
            'record' => $application,
            'listing' => $application->jobPosting,
            'listingLabel' => $this->jobLabel($application->jobPosting),
            'answerRows' => $this->dashboardAnswerRows($application),
            'assignees' => $this->activeDashboardAssignees(),
            'transitions' => JobApplicationWorkflowService::TRANSITIONS[$application->workflow_status] ?? [],
            'criteria' => $criteria,
            'actor' => $actor,
            'canEdit' => $this->allows($actor, 'recruitment.applications.edit'),
            'canDownload' => $this->allows($actor, 'recruitment.applications.download'),
            'canAnonymize' => $this->authority->isOwner($actor)
                && $this->allows($actor, 'recruitment.applications.anonymize'),
            'canDelete' => $this->authority->isOwner($actor)
                && $this->allows($actor, 'recruitment.applications.delete'),
            'routeNames' => $this->routeNames(),
            'isJob' => true,
        ]);
    }

    public function workflow(Request $request, JobApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'workflow_status' => ['required', Rule::in(JobApplication::STATUSES)],
        ]);
        $this->workflow->transition($application, $data['workflow_status'], $this->actor($request));

        return redirect()->route('recruitment.applications.show', $application)
            ->with(['message' => 'Application status updated.', 'alert-type' => 'success']);
    }

    public function assign(Request $request, JobApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'assigned_to_admin_id' => [
                'nullable',
                Rule::exists('admins', 'id')->where(fn ($query) => $query->where('status', 1)),
            ],
        ]);
        $assignee = $this->activeDashboardAssignee($data['assigned_to_admin_id'] ?? null);
        $this->workflow->assign($application, $assignee, $this->actor($request));

        return redirect()->route('recruitment.applications.show', $application)
            ->with(['message' => 'Application assignment updated.', 'alert-type' => 'success']);
    }

    public function addNote(Request $request, JobApplication $application): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:20000']]);
        $this->workflow->addNote($application, $data['body'], $this->actor($request));

        return redirect()->route('recruitment.applications.show', $application)
            ->with(['message' => 'Private note added.', 'alert-type' => 'success']);
    }

    public function score(Request $request, JobApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'criterion' => ['required', 'uuid'],
            'score' => ['required', 'numeric'],
            'comment' => ['nullable', 'string', 'max:20000'],
        ]);
        $criterion = JobScorecardCriterion::query()
            ->where('uuid', $data['criterion'])
            ->where('job_posting_id', $application->job_posting_id)
            ->where('is_enabled', true)
            ->firstOrFail();
        $this->workflow->score(
            $application,
            $criterion,
            $data['score'],
            $this->actor($request),
            $data['comment'] ?? null,
        );

        return redirect()->route('recruitment.applications.show', $application)
            ->with(['message' => 'Score saved.', 'alert-type' => 'success']);
    }

    public function bulk(Request $request): RedirectResponse
    {
        $base = $request->validate([
            'listing' => ['required', 'uuid', Rule::exists('job_postings', 'uuid')->whereNull('deleted_at')],
            'operation' => ['required', Rule::in(['status', 'assignment', 'preferences'])],
        ]);
        $actor = $this->actor($request);
        $job = JobPosting::query()->where('uuid', $base['listing'])->firstOrFail();

        if ($base['operation'] === 'preferences') {
            $columns = $this->dashboardAnswerColumns(
                JobApplication::class,
                'job_posting_id',
                (int) $job->id,
                $job->current_form_version_id ? (int) $job->current_form_version_id : null,
            );
            $this->saveDashboardPreference(
                $request,
                $actor,
                $this->preferenceKey($job),
                $columns,
                ApplicationListingService::JOB_SORTS,
            );

            return redirect()->route('recruitment.applications.index', ['listing' => $job->uuid])
                ->with(['message' => 'Table preferences saved.', 'alert-type' => 'success']);
        }

        $data = $request->validate([
            'application_ids' => ['required', 'array', 'min:1', 'max:' . JobApplicationWorkflowService::MAX_BULK_RECORDS],
            'application_ids.*' => ['required', 'integer', 'distinct'],
            'workflow_status' => ['required_if:operation,status', 'nullable', Rule::in(JobApplication::STATUSES)],
            'assigned_to_admin_id' => [
                'nullable',
                Rule::exists('admins', 'id')->where(fn ($query) => $query->where('status', 1)),
            ],
        ]);
        $ids = collect($data['application_ids'])->map(fn (mixed $id): int => (int) $id)->unique()->values();
        $ownedIds = JobApplication::query()
            ->where('job_posting_id', $job->id)
            ->whereKey($ids)
            ->pluck('id');
        if ($ownedIds->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'application_ids' => 'Every selected application must belong to the chosen job listing.',
            ]);
        }

        if ($base['operation'] === 'status') {
            $this->workflow->bulkTransition($ids, (string) $data['workflow_status'], $actor);
            $message = $ids->count() . ' application statuses updated.';
        } else {
            $assignee = $this->activeDashboardAssignee($data['assigned_to_admin_id'] ?? null);
            $this->workflow->bulkAssign($ids, $assignee, $actor);
            $message = $ids->count() . ' application assignments updated.';
        }

        return redirect()->route('recruitment.applications.index', ['listing' => $job->uuid])
            ->with(['message' => $message, 'alert-type' => 'success']);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedDashboardFilters(
            $request,
            ApplicationListingService::JOB_SORTS,
            JobApplication::STATUSES,
            'job_postings',
        );
        if (!isset($filters['listing'])) {
            throw ValidationException::withMessages(['listing' => 'Choose a job listing to export.']);
        }
        $request->validate([
            'columns' => ['nullable', 'array', 'max:50'],
            'columns.*' => ['string', 'max:120', 'distinct'],
        ]);
        $job = JobPosting::query()->where('uuid', $filters['listing'])->firstOrFail();
        $query = $this->listings->jobs($job, $filters, $this->searches->current($request, self::SEARCH_SCOPE));

        return $this->exports->jobs(
            $job,
            $query,
            array_values($request->input('columns', [])),
            $this->actor($request),
            $filters,
        );
    }

    public function download(
        Request $request,
        JobApplication $application,
        JobApplicationDocument $document,
    ): BinaryFileResponse {
        abort_unless((int) $document->job_application_id === (int) $application->id, 404);
        $response = $this->documents->download(
            (string) $document->disk,
            (string) $document->path,
            (int) $document->bytes,
            (string) $document->sha256,
            (string) $document->original_name,
        );
        $this->audit->record(
            $this->actor($request),
            'recruitment.application.document_downloaded',
            $application,
            context: [
                'document_id' => (int) $document->id,
                'job_posting_id' => (int) $application->job_posting_id,
            ],
        );

        return $response;
    }

    public function anonymize(Request $request, JobApplication $application): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->authority->assertOwner($actor);
        $this->assertDashboardConfirmation($request, 'ANONYMIZE ' . $application->reference_number);
        $this->privacy->anonymizeJob($application, $actor);

        return redirect()->route('recruitment.applications.show', $application)
            ->with(['message' => 'Applicant identity and submitted content anonymized.', 'alert-type' => 'success']);
    }

    public function delete(Request $request, JobApplication $application): RedirectResponse
    {
        return $this->deleteRecord($request, $application);
    }

    public function destroy(Request $request, JobApplication $application): RedirectResponse
    {
        return $this->deleteRecord($request, $application);
    }

    private function deleteRecord(Request $request, JobApplication $application): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->authority->assertOwner($actor);
        $this->assertDashboardConfirmation($request, 'DELETE ' . $application->reference_number);
        $job = $application->jobPosting()->firstOrFail();
        $this->privacy->deleteJob($application, $actor);

        return redirect()->route('recruitment.applications.index', ['listing' => $job->uuid])
            ->with(['message' => 'Application permanently deleted.', 'alert-type' => 'success']);
    }

    /** @return array<string, mixed> */
    private function indexViewData(
        Admin $actor,
        Collection $jobs,
        ?JobPosting $job,
        array $filters,
        mixed $records,
        Collection $availableColumns,
        array $visibleColumns,
        array $answerValues,
        string $privateSearch,
    ): array {
        return [
            'title' => 'Job applications',
            'sectionLabel' => 'Recruitment',
            'recordLabel' => 'application',
            'recordsLabel' => 'applications',
            'listings' => $jobs,
            'listing' => $job,
            'listingLabel' => $job ? $this->jobLabel($job) : '',
            'listingTitle' => fn (JobPosting $listing): string => $this->jobLabel($listing),
            'records' => $records,
            'statuses' => JobApplication::STATUSES,
            'sorts' => ApplicationListingService::JOB_SORTS,
            'filters' => $filters,
            'privateSearch' => $privateSearch,
            'assignees' => $this->activeDashboardAssignees(),
            'availableColumns' => $availableColumns,
            'visibleColumns' => $visibleColumns,
            'answerValues' => $answerValues,
            'fixedExportColumns' => ApplicationExportService::JOB_COLUMNS,
            'canEdit' => $this->allows($actor, 'recruitment.applications.edit'),
            'canExport' => $this->allows($actor, 'recruitment.applications.export'),
            'routeNames' => $this->routeNames(),
            'isJob' => true,
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

    private function preferenceKey(JobPosting $job): string
    {
        return 'recruitment.applications:' . $job->id;
    }

    private function jobLabel(JobPosting $job): string
    {
        return (string) ($job->translations->firstWhere('locale', 'en')?->title
            ?: 'Job listing #' . $job->id);
    }

    /** @return array<string, string> */
    private function routeNames(): array
    {
        return [
            'index' => 'recruitment.applications.index',
            'show' => 'recruitment.applications.show',
            'search' => 'recruitment.applications.search',
            'search_clear' => 'recruitment.applications.search.clear',
            'bulk' => 'recruitment.applications.bulk',
            'workflow' => 'recruitment.applications.workflow',
            'assign' => 'recruitment.applications.assign',
            'score' => 'recruitment.applications.score',
            'note' => 'recruitment.applications.notes.store',
            'export' => 'recruitment.applications.export',
            'download' => 'recruitment.applications.download',
            'anonymize' => 'recruitment.applications.anonymize',
            'delete' => 'recruitment.applications.delete',
        ];
    }
}
