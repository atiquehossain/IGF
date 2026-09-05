<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Permission;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\ApplicationFormVersion;
use App\Services\ApplicationFormSchemaService;
use App\Support\AdminUi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use JsonException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ApplicationFormController extends Controller
{
    public function __construct(private readonly ApplicationFormSchemaService $schemas)
    {
    }

    public function index(Request $request): View
    {
        return $this->formList($request, false);
    }

    public function trash(Request $request): View
    {
        return $this->formList($request, true);
    }

    private function formList(Request $request, bool $trashed): View
    {
        $purpose = $this->purpose($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::in(['all', 'forms', 'templates'])],
            'state' => ['nullable', Rule::in(['all', ApplicationFormVersion::STATE_DRAFT, ApplicationFormVersion::STATE_PUBLISHED])],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $kind = (string) ($filters['kind'] ?? 'all');
        $state = (string) ($filters['state'] ?? 'all');

        $forms = ApplicationForm::query()
            ->when($trashed, fn ($query) => $query->onlyTrashed())
            ->where('purpose', $purpose)
            ->with('versions')
            ->withCount([
                'jobPostings as active_job_postings_count',
                'workshops as active_workshops_count',
                'jobPostings as total_job_postings_count' => fn ($query) => $query->withTrashed(),
                'workshops as total_workshops_count' => fn ($query) => $query->withTrashed(),
            ])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%' . $search . '%'))
            ->when($kind === 'forms', fn ($query) => $query->where('is_template', false))
            ->when($kind === 'templates', fn ($query) => $query->where('is_template', true))
            ->when($state !== 'all', fn ($query) => $query->whereHas(
                'versions',
                fn ($versions) => $versions->where('state', $state)
            ))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view($this->viewName($purpose, 'index'), [
            'title' => $this->ui('page_titles.index', ['area' => $this->sectionLabel($purpose)]),
            'sectionLabel' => $this->sectionLabel($purpose),
            'purpose' => $purpose,
            'forms' => $forms,
            'filters' => compact('search', 'kind', 'state'),
            'routeNames' => $this->routeNames($purpose),
            'isTrash' => $trashed,
            'canManage' => app(Permission::class)->allows(
                Auth::guard('admin')->user(),
                $this->routeNames($purpose)['update']
            ),
        ]);
    }

    public function create(Request $request): View
    {
        $purpose = $this->purpose($request);

        return view($this->viewName($purpose, 'create'), [
            'title' => $this->ui('page_titles.create', ['area' => $this->sectionLabel($purpose)]),
            'sectionLabel' => $this->sectionLabel($purpose),
            'purpose' => $purpose,
            'routeNames' => $this->routeNames($purpose),
            'templates' => ApplicationForm::query()
                ->where('purpose', $purpose)
                ->where('is_template', true)
                ->orderBy('name')
                ->get(['uuid', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $purpose = $this->purpose($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'is_template' => ['sometimes', 'boolean'],
            'template_uuid' => ['nullable', 'uuid'],
        ]);
        $actor = Auth::guard('admin')->user();
        $isTemplate = $request->boolean('is_template');

        if (!empty($data['template_uuid'])) {
            $template = ApplicationForm::query()
                ->where('uuid', $data['template_uuid'])
                ->where('purpose', $purpose)
                ->where('is_template', true)
                ->first();
            if (!$template) {
                throw ValidationException::withMessages([
                    'template_uuid' => $this->ui('messages.choose_area_template'),
                ]);
            }
            $form = $this->schemas->duplicate($template, $data['name'], $actor, $purpose, $isTemplate);
        } else {
            $form = $this->schemas->create($purpose, $data['name'], $actor, $isTemplate);
        }

        return redirect()->route($this->routeNames($purpose)['edit'], $form)->with([
            'message' => $this->ui($isTemplate ? 'messages.template_created' : 'messages.draft_created'),
            'alert-type' => 'success',
        ]);
    }

    public function edit(ApplicationForm $form, Request $request): View
    {
        $purpose = $this->purpose($request);
        $this->assertPurpose($form, $purpose);
        $version = $this->editableVersion($form);
        $schema = $this->schemas->schemaArray($version);

        return view($this->viewName($purpose, 'edit'), [
            'title' => $this->ui('page_titles.edit', ['name' => $form->name]),
            'sectionLabel' => $this->sectionLabel($purpose),
            'purpose' => $purpose,
            'form' => $form,
            'version' => $version,
            'hasDraft' => $version->state === ApplicationFormVersion::STATE_DRAFT,
            'schemaJson' => $this->encodeForHtmlControl($schema),
            'configJson' => $this->encodeForHtmlControl([
                'types' => collect(ApplicationFormField::TYPES)
                    ->map(fn (string $value): array => [
                        'value' => $value,
                        'label' => $this->ui("types.{$value}"),
                    ])
                    ->values()
                    ->all(),
                'operators' => collect(ApplicationFormSchemaService::OPERATORS)
                    ->map(fn (string $value): array => [
                        'value' => $value,
                        'label' => $this->ui("operators.{$value}"),
                    ])
                    ->values()
                    ->all(),
                'ui' => AdminUi::section('application_forms.builder_ui'),
                'system_fields' => AdminUi::section('application_forms.system_fields'),
                'choice_types' => [
                    ApplicationFormField::TYPE_DROPDOWN,
                    ApplicationFormField::TYPE_RADIO,
                    ApplicationFormField::TYPE_CHECKBOXES,
                ],
                'text_types' => [
                    ApplicationFormField::TYPE_SHORT_TEXT,
                    ApplicationFormField::TYPE_LONG_TEXT,
                    ApplicationFormField::TYPE_EMAIL,
                    ApplicationFormField::TYPE_PHONE,
                ],
                'number_types' => [ApplicationFormField::TYPE_NUMBER],
                'max_fields' => ApplicationFormSchemaService::MAX_FIELDS,
                'max_options' => ApplicationFormSchemaService::MAX_OPTIONS,
            ]),
            'routeNames' => $this->routeNames($purpose),
        ]);
    }

    public function update(Request $request, ApplicationForm $form): JsonResponse|RedirectResponse
    {
        $purpose = $this->purpose($request);
        $this->assertPurpose($form, $purpose);
        [$expectedVersion, $fields] = $this->validatedSchema($request);

        try {
            $draft = $this->schemas->replaceDraft($form, $expectedVersion, $fields, Auth::guard('admin')->user());
        } catch (HttpExceptionInterface $exception) {
            return $this->conflictResponse($request, $exception, $form);
        }

        $editorVersion = (int) $form->fresh()->editor_version;
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->ui('messages.draft_saved'),
                'editor_version' => $editorVersion,
                'form_version' => (int) $draft->version,
            ]);
        }

        return redirect()->route($this->routeNames($purpose)['edit'], $form)->with([
            'message' => $this->ui('messages.draft_saved'),
            'alert-type' => 'success',
        ]);
    }

    public function updateMetadata(Request $request, ApplicationForm $form): RedirectResponse
    {
        $purpose = $this->purpose($request);
        $this->assertPurpose($form, $purpose);
        $data = $request->validate([
            'editor_version' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:150'],
            'is_template' => ['sometimes', 'boolean'],
        ]);

        try {
            $updated = $this->schemas->updateMetadata(
                $form,
                (int) $data['editor_version'],
                $data['name'],
                $request->boolean('is_template'),
                Auth::guard('admin')->user(),
            );
        } catch (HttpExceptionInterface $exception) {
            return $this->lifecycleConflict($request, $exception);
        }

        return redirect()->route($this->routeNames($purpose)['edit'], $updated)->with([
            'message' => $this->ui('messages.details_saved'),
            'alert-type' => 'success',
        ]);
    }

    public function publish(Request $request, ApplicationForm $form): JsonResponse|RedirectResponse
    {
        $purpose = $this->purpose($request);
        $this->assertPurpose($form, $purpose);
        $data = $request->validate([
            'editor_version' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $published = $this->schemas->publish(
                $form,
                (int) $data['editor_version'],
                Auth::guard('admin')->user()
            );
        } catch (HttpExceptionInterface $exception) {
            return $this->conflictResponse($request, $exception, $form);
        }

        $editorVersion = (int) $form->fresh()->editor_version;
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->ui('messages.published'),
                'editor_version' => $editorVersion,
                'form_version' => (int) $published->version,
            ]);
        }

        return redirect()->route($this->routeNames($purpose)['edit'], $form)->with([
            'message' => $this->ui('messages.published'),
            'alert-type' => 'success',
        ]);
    }

    public function duplicate(Request $request, ApplicationForm $form): RedirectResponse
    {
        $purpose = $this->purpose($request);
        $this->assertPurpose($form, $purpose);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'is_template' => ['sometimes', 'boolean'],
        ]);
        $copy = $this->schemas->duplicate(
            $form,
            $data['name'],
            Auth::guard('admin')->user(),
            $purpose,
            $request->has('is_template') ? $request->boolean('is_template') : (bool) $form->is_template
        );

        return redirect()->route($this->routeNames($purpose)['edit'], $copy)->with([
            'message' => $this->ui('messages.duplicated'),
            'alert-type' => 'success',
        ]);
    }

    public function destroy(Request $request, ApplicationForm $form): RedirectResponse
    {
        $purpose = $this->purpose($request);
        $this->assertPurpose($form, $purpose);
        $data = $request->validate(['editor_version' => ['required', 'integer', 'min:1']]);

        try {
            $this->schemas->archive($form, (int) $data['editor_version'], Auth::guard('admin')->user());
        } catch (HttpExceptionInterface $exception) {
            return $this->lifecycleConflict($request, $exception);
        }

        return redirect()->route($this->routeNames($purpose)['index'])->with([
            'message' => $this->ui('messages.archived'),
            'alert-type' => 'success',
        ]);
    }

    public function restore(Request $request, string $form): RedirectResponse
    {
        $purpose = $this->purpose($request);
        $trashed = $this->trashedForm($form, $purpose);
        $data = $request->validate(['editor_version' => ['required', 'integer', 'min:1']]);

        try {
            $restored = $this->schemas->restore($trashed, (int) $data['editor_version'], Auth::guard('admin')->user());
        } catch (HttpExceptionInterface $exception) {
            return $this->lifecycleConflict($request, $exception);
        }

        return redirect()->route($this->routeNames($purpose)['edit'], $restored)->with([
            'message' => $this->ui('messages.restored'),
            'alert-type' => 'success',
        ]);
    }

    public function forceDestroy(Request $request, string $form): RedirectResponse
    {
        $purpose = $this->purpose($request);
        $trashed = $this->trashedForm($form, $purpose);
        $data = $request->validate(['editor_version' => ['required', 'integer', 'min:1']]);

        try {
            $this->schemas->permanentlyDelete($trashed, (int) $data['editor_version'], Auth::guard('admin')->user());
        } catch (HttpExceptionInterface $exception) {
            return $this->lifecycleConflict($request, $exception);
        }

        return redirect()->route($this->routeNames($purpose)['trash'])->with([
            'message' => $this->ui('messages.deleted'),
            'alert-type' => 'success',
        ]);
    }

    public function preview(ApplicationForm $form, Request $request): Response
    {
        $purpose = $this->purpose($request);
        $this->assertPurpose($form, $purpose);
        $data = $request->validate([
            'locale' => ['nullable', Rule::in(['en', 'bn'])],
            'state' => ['nullable', Rule::in([ApplicationFormVersion::STATE_DRAFT, ApplicationFormVersion::STATE_PUBLISHED])],
        ]);
        $locale = (string) ($data['locale'] ?? 'en');
        $state = (string) ($data['state'] ?? ApplicationFormVersion::STATE_DRAFT);
        $version = $form->versions()->where('state', $state)->latest('version')->first();
        if (!$version && $state === ApplicationFormVersion::STATE_DRAFT) {
            $version = $form->versions()->where('state', ApplicationFormVersion::STATE_PUBLISHED)->latest('version')->first();
        }
        abort_unless($version, 404);
        $previewSchema = $this->schemas->publicSchema($version, $locale);

        return response()->view($this->viewName($purpose, 'preview'), [
            'title' => $this->ui('page_titles.preview', ['name' => $form->name]),
            'sectionLabel' => $this->sectionLabel($purpose),
            'purpose' => $purpose,
            'form' => $form,
            'version' => $version,
            'locale' => $locale,
            'previewSchema' => $previewSchema,
            'previewSchemaJson' => $this->encodeForHtmlControl($previewSchema),
            'routeNames' => $this->routeNames($purpose),
        ])->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /** @return array{0:int,1:list<array<string,mixed>>} */
    private function validatedSchema(Request $request): array
    {
        $data = $request->validate([
            'editor_version' => ['required', 'integer', 'min:1'],
            'schema' => ['required', 'string', 'max:6000000', 'json'],
        ]);

        try {
            $fields = json_decode($data['schema'], true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['schema' => $this->ui('messages.invalid_json')]);
        }
        if (!is_array($fields) || !array_is_list($fields)) {
            throw ValidationException::withMessages(['schema' => $this->ui('messages.ordered_list')]);
        }

        return [(int) $data['editor_version'], $fields];
    }

    private function editableVersion(ApplicationForm $form): ApplicationFormVersion
    {
        $draft = $form->versions()->where('state', ApplicationFormVersion::STATE_DRAFT)->latest('version')->first();

        return $draft
            ?: $form->versions()->where('state', ApplicationFormVersion::STATE_PUBLISHED)->latest('version')->firstOrFail();
    }

    private function purpose(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();

        return match (true) {
            str_starts_with($routeName, 'recruitment.forms.') => ApplicationForm::PURPOSE_JOB,
            str_starts_with($routeName, 'workshop.forms.') => ApplicationForm::PURPOSE_WORKSHOP,
            default => abort(404),
        };
    }

    private function assertPurpose(ApplicationForm $form, string $purpose): void
    {
        abort_unless(hash_equals($purpose, (string) $form->purpose), 404);
    }

    /** @return array<string,string> */
    private function routeNames(string $purpose): array
    {
        $prefix = $purpose === ApplicationForm::PURPOSE_JOB ? 'recruitment.forms.' : 'workshop.forms.';

        return collect([
            'index', 'trash', 'create', 'store', 'edit', 'update', 'metadata', 'publish',
            'duplicate', 'preview', 'destroy', 'restore', 'force-destroy',
        ])
            ->mapWithKeys(fn (string $action): array => [$action => $prefix . $action])
            ->all();
    }

    private function trashedForm(string $uuid, string $purpose): ApplicationForm
    {
        return ApplicationForm::onlyTrashed()
            ->where('uuid', $uuid)
            ->where('purpose', $purpose)
            ->firstOrFail();
    }

    private function viewName(string $purpose, string $view): string
    {
        $area = $purpose === ApplicationForm::PURPOSE_JOB ? 'recruitment' : 'workshops';

        return "admin.{$area}.forms.{$view}";
    }

    private function sectionLabel(string $purpose): string
    {
        return $this->ui('areas.' . ($purpose === ApplicationForm::PURPOSE_JOB ? 'job' : 'workshop'));
    }

    /** @param array<string, scalar|\Stringable> $replace */
    private function ui(string $key, array $replace = []): string
    {
        return AdminUi::text("application_forms.{$key}", $replace);
    }

    private function encodeForHtmlControl(array $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    private function conflictResponse(
        Request $request,
        HttpExceptionInterface $exception,
        ApplicationForm $form,
    ): JsonResponse|RedirectResponse
    {
        if ($exception->getStatusCode() !== 409) {
            throw $exception;
        }
        $message = $exception->getMessage() ?: $this->ui('messages.changed');
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'conflict' => true,
            ], 409);
        }

        return redirect()->route($this->routeNames($this->purpose($request))['edit'], $form)
            ->withInput()
            ->withErrors(['editor_version' => $message]);
    }

    private function lifecycleConflict(Request $request, HttpExceptionInterface $exception): RedirectResponse
    {
        if ($exception->getStatusCode() !== 409) {
            throw $exception;
        }

        return back()
            ->withInput()
            ->withErrors(['editor_version' => $exception->getMessage() ?: $this->ui('messages.changed')]);
    }
}
