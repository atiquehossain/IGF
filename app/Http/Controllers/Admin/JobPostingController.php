<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\JobPosting;
use App\Services\OpportunityManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class JobPostingController extends Controller
{
    public function __construct(private OpportunityManagementService $opportunities)
    {
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $jobs = JobPosting::query()
            ->with(['translations', 'currentFormVersion:id,uuid,version,state'])
            ->withCount('applications')
            ->when(in_array($status, JobPosting::PUBLICATION_STATUSES, true), fn ($query) => $query->where('publication_status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.recruitment.jobs.index', [
            'title' => 'Recruitment jobs',
            'jobs' => $jobs,
            'selectedStatus' => $status,
            'statuses' => JobPosting::PUBLICATION_STATUSES,
        ]);
    }

    public function create(): View
    {
        return $this->formView(new JobPosting(), 'Create job');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, false);
        $job = $this->opportunities->createJob($data, $request->user('admin'));

        return redirect()->route('recruitment.jobs.edit', $job)->with($this->success('Job draft created. Review and publish it when ready.'));
    }

    public function show(JobPosting $job): RedirectResponse
    {
        return redirect()->route('recruitment.jobs.edit', $job);
    }

    public function edit(JobPosting $job): View
    {
        $job->load(['translations', 'form', 'currentFormVersion', 'scorecardCriteria']);

        return $this->formView($job, 'Edit job');
    }

    public function update(Request $request, JobPosting $job): RedirectResponse
    {
        $data = $this->validated($request, true);
        $updated = $this->opportunities->updateJob($job, (int) $data['editor_version'], $data, $request->user('admin'));

        return redirect()->route('recruitment.jobs.edit', $updated)->with($this->success('Job changes saved.'));
    }

    public function status(Request $request, JobPosting $job): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['publish', 'close', 'withdraw'])],
            'editor_version' => ['nullable', 'integer', 'min:1'],
        ]);
        $actor = $request->user('admin');
        match ($validated['action']) {
            'publish' => $this->opportunities->publishJob($job, (int) ($validated['editor_version'] ?? 0), $actor),
            'close' => $this->opportunities->closeJob($job, $actor),
            'withdraw' => $this->opportunities->withdrawJob($job, $actor),
        };

        return redirect()->route('recruitment.jobs.index')
            ->with($this->success('Job publication state updated.'));
    }

    public function duplicate(Request $request, JobPosting $job): RedirectResponse
    {
        $copy = $this->opportunities->duplicateJob($job, $request->user('admin'));

        return redirect()->route('recruitment.jobs.edit', $copy)->with($this->success('Independent job and form drafts created.'));
    }

    public function destroy(Request $request, JobPosting $job): RedirectResponse
    {
        $this->opportunities->deleteJobDraft($job, $request->user('admin'));

        return redirect()->route('recruitment.jobs.index')->with($this->success('Unused job draft deleted.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $editing): array
    {
        return $request->validate([
            'editor_version' => [$editing ? 'required' : 'nullable', 'integer', 'min:1'],
            'visible_from_at' => ['nullable', 'date'],
            'application_opens_at' => ['required', 'date'],
            'application_closes_at' => ['required', 'date'],
            'employment_type' => ['required', Rule::in(JobPosting::EMPLOYMENT_TYPES)],
            'work_arrangement' => ['required', Rule::in(JobPosting::WORK_ARRANGEMENTS)],
            'vacancy_count' => ['required', 'integer', 'min:1', 'max:10000'],
            'application_form_id' => ['nullable', 'integer', 'exists:application_forms,id'],
            'form_version_id' => ['nullable', 'integer', 'exists:application_form_versions,id'],
            'scorecard_criteria' => ['nullable', 'array', 'max:20'],
            'scorecard_criteria.*' => ['array'],
            'scorecard_criteria.*.uuid' => ['nullable', 'uuid', 'distinct'],
            'scorecard_criteria.*.label' => ['required', 'string', 'max:255'],
            'scorecard_criteria.*.description' => ['nullable', 'string', 'max:2000'],
            'scorecard_criteria.*.maximum_score' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'scorecard_criteria.*.is_enabled' => ['required', 'boolean'],
            'translations' => ['required', 'array'],
            'translations.en' => ['required', 'array'],
            'translations.bn' => ['required', 'array'],
            'translations.*.slug' => ['nullable', 'string', 'max:190'],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.department' => ['required', 'string', 'max:150'],
            'translations.*.location' => ['required', 'string', 'max:255'],
            'translations.*.summary' => ['nullable', 'string', 'max:2000'],
            'translations.*.description' => ['required', 'string', 'max:100000'],
            'translations.*.responsibilities' => ['nullable', 'string', 'max:100000'],
            'translations.*.requirements' => ['required', 'string', 'max:100000'],
        ]);
    }

    private function formView(JobPosting $job, string $title): View
    {
        $job->loadMissing('scorecardCriteria');
        $forms = ApplicationForm::query()
            ->where('purpose', ApplicationForm::PURPOSE_JOB)
            ->whereHas('versions', fn ($query) => $query->where('state', ApplicationFormVersion::STATE_PUBLISHED))
            ->with(['versions' => fn ($query) => $query->where('state', ApplicationFormVersion::STATE_PUBLISHED)->latest('version')])
            ->orderByDesc('is_template')
            ->orderBy('name')
            ->get();

        return view('admin.recruitment.jobs.form', compact('job', 'title', 'forms'));
    }

    /** @return array{message:string,alert-type:string} */
    private function success(string $message): array
    {
        return ['message' => $message, 'alert-type' => 'success'];
    }
}
