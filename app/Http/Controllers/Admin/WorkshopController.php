<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\Workshop;
use App\Services\OpportunityManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class WorkshopController extends Controller
{
    public function __construct(private OpportunityManagementService $opportunities)
    {
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $workshops = Workshop::query()
            ->with(['translations', 'currentFormVersion:id,uuid,version,state'])
            ->withCount('registrations')
            ->when(in_array($status, Workshop::PUBLICATION_STATUSES, true), fn ($query) => $query->where('publication_status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.workshops.index', [
            'title' => 'Workshops',
            'workshops' => $workshops,
            'selectedStatus' => $status,
            'statuses' => Workshop::PUBLICATION_STATUSES,
        ]);
    }

    public function create(): View
    {
        return $this->formView(new Workshop(), 'Create workshop');
    }

    public function store(Request $request): RedirectResponse
    {
        $workshop = $this->opportunities->createWorkshop($this->validated($request, false), $request->user('admin'));

        return redirect()->route('workshops.edit', $workshop)->with($this->success('Free workshop draft created. Review and publish it when ready.'));
    }

    public function show(Workshop $workshop): RedirectResponse
    {
        return redirect()->route('workshops.edit', $workshop);
    }

    public function edit(Workshop $workshop): View
    {
        $workshop->load(['translations', 'form', 'currentFormVersion']);

        return $this->formView($workshop, 'Edit workshop');
    }

    public function update(Request $request, Workshop $workshop): RedirectResponse
    {
        $data = $this->validated($request, true);
        $updated = $this->opportunities->updateWorkshop($workshop, (int) $data['editor_version'], $data, $request->user('admin'));

        return redirect()->route('workshops.edit', $updated)->with($this->success('Workshop changes saved.'));
    }

    public function status(Request $request, Workshop $workshop): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['publish', 'close', 'withdraw'])],
            'editor_version' => ['nullable', 'integer', 'min:1'],
        ]);
        $actor = $request->user('admin');
        match ($validated['action']) {
            'publish' => $this->opportunities->publishWorkshop($workshop, (int) ($validated['editor_version'] ?? 0), $actor),
            'close' => $this->opportunities->closeWorkshop($workshop, $actor),
            'withdraw' => $this->opportunities->withdrawWorkshop($workshop, $actor),
        };

        return redirect()->route('workshops.index')
            ->with($this->success('Workshop publication state updated.'));
    }

    public function duplicate(Request $request, Workshop $workshop): RedirectResponse
    {
        $copy = $this->opportunities->duplicateWorkshop($workshop, $request->user('admin'));

        return redirect()->route('workshops.edit', $copy)->with($this->success('Independent workshop and form drafts created.'));
    }

    public function destroy(Request $request, Workshop $workshop): RedirectResponse
    {
        $this->opportunities->deleteWorkshopDraft($workshop, $request->user('admin'));

        return redirect()->route('workshops.index')->with($this->success('Unused workshop draft deleted.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $editing): array
    {
        $validated = $request->validate([
            'editor_version' => [$editing ? 'required' : 'nullable', 'integer', 'min:1'],
            'visible_from_at' => ['nullable', 'date'],
            'registration_opens_at' => ['required', 'date'],
            'registration_closes_at' => ['required', 'date'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'attendance_mode' => ['required', Rule::in(Workshop::ATTENDANCE_MODES)],
            'registration_mode' => ['required', Rule::in(Workshop::REGISTRATION_MODES)],
            'capacity_choice' => ['required', Rule::in(['unlimited', 'limited'])],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'private_meeting_url' => ['nullable', 'url:https', 'max:2000'],
            'application_form_id' => ['nullable', 'integer', 'exists:application_forms,id'],
            'form_version_id' => ['nullable', 'integer', 'exists:application_form_versions,id'],
            'translations' => ['required', 'array'],
            'translations.en' => ['required', 'array'],
            'translations.bn' => ['required', 'array'],
            'translations.*.slug' => ['nullable', 'string', 'max:190'],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.summary' => ['nullable', 'string', 'max:2000'],
            'translations.*.description' => ['required', 'string', 'max:100000'],
            'translations.*.facilitator_name' => ['nullable', 'string', 'max:255'],
            'translations.*.venue_name' => ['nullable', 'string', 'max:255'],
            'translations.*.venue_address' => ['nullable', 'string', 'max:2000'],
            'translations.*.registration_instructions' => ['nullable', 'string', 'max:100000'],
        ], [
            'attendance_mode.required' => 'Choose how people will attend.',
            'registration_mode.required' => 'Choose what happens after someone submits the form.',
            'capacity_choice.required' => 'Choose Unlimited or Limit participants.',
            'private_meeting_url.url' => 'Enter a complete HTTPS meeting link.',
        ]);

        if ($validated['capacity_choice'] === 'unlimited') {
            $validated['capacity'] = null;
        } elseif (($validated['capacity'] ?? null) === null) {
            throw ValidationException::withMessages([
                'capacity' => 'Enter the maximum number of participants.',
            ]);
        }

        if ($validated['registration_mode'] === Workshop::REGISTRATION_WAITLIST
            && $validated['capacity_choice'] !== 'limited') {
            throw ValidationException::withMessages([
                'capacity_choice' => 'Waitlist mode requires a participant limit.',
            ]);
        }

        return $validated;
    }

    private function formView(Workshop $workshop, string $title): View
    {
        if ($workshop->exists && !array_key_exists('registrations_count', $workshop->getAttributes())) {
            $workshop->loadCount('registrations');
        }

        $forms = ApplicationForm::query()
            ->where('purpose', ApplicationForm::PURPOSE_WORKSHOP)
            ->whereHas('versions', fn ($query) => $query->where('state', ApplicationFormVersion::STATE_PUBLISHED))
            ->with(['versions' => fn ($query) => $query->where('state', ApplicationFormVersion::STATE_PUBLISHED)->latest('version')])
            ->orderByDesc('is_template')
            ->orderBy('name')
            ->get();

        return view('admin.workshops.form', compact('workshop', 'title', 'forms'));
    }

    /** @return array{message:string,alert-type:string} */
    private function success(string $message): array
    {
        return ['message' => $message, 'alert-type' => 'success'];
    }
}
