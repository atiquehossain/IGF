<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ApplicationFormField;
use App\Models\ApplicationImportBatch;
use App\Models\JobPosting;
use App\Models\Workshop;
use App\Services\ApplicationCsvParser;
use App\Services\ApplicationImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ApplicationImportController extends Controller
{
    private const NO_STORE_HEADERS = [
        'Cache-Control' => 'private, no-store, max-age=0',
        'Pragma' => 'no-cache',
        'X-Robots-Tag' => 'noindex, nofollow, noarchive',
    ];

    public function __construct(
        private readonly ApplicationImportService $imports,
        private readonly ApplicationCsvParser $parser,
    ) {
    }

    public function index(Request $request): Response
    {
        $kind = $this->kind($request);
        $listings = $this->listingQuery($kind)
            ->with('translations:id,' . $this->listingForeignKey($kind) . ',locale,title')
            ->withCount('importBatches')
            ->latest('updated_at')
            ->get();
        $selectedUuid = trim((string) $request->query('listing', ''));
        if ($selectedUuid !== '' && !Str::isUuid($selectedUuid)) {
            abort(404);
        }
        $listing = $selectedUuid === '' ? $listings->first() : $listings->firstWhere('uuid', $selectedUuid);
        if ($selectedUuid !== '' && !$listing) {
            abort(404);
        }

        $batches = $listing
            ? $this->scopedBatchQuery($kind, $listing)
                ->with(['uploadedByAdmin:id,name,username', 'confirmedByAdmin:id,name,username'])
                ->latest('id')
                ->paginate(20)
                ->withQueryString()
            : null;

        return $this->page($kind, 'index', [
            'title' => $this->sectionLabel($kind) . ' CSV imports',
            'sectionLabel' => $this->sectionLabel($kind),
            'recordLabel' => $this->recordLabel($kind),
            'listings' => $listings,
            'listing' => $listing,
            'listingLabel' => $listing ? $this->listingLabel($listing) : '',
            'batches' => $batches,
            'routeNames' => $this->routeNames($kind),
        ]);
    }

    public function create(Request $request): Response
    {
        $kind = $this->kind($request);
        $listing = $this->listing($request, $kind);
        $listing->loadMissing('translations');

        return $this->page($kind, 'create', [
            'title' => 'Upload ' . $this->recordLabel($kind) . ' CSV',
            'sectionLabel' => $this->sectionLabel($kind),
            'recordLabel' => $this->recordLabel($kind),
            'listing' => $listing,
            'listingLabel' => $this->listingLabel($listing),
            'routeNames' => $this->routeNames($kind),
            'maxBytes' => ApplicationCsvParser::MAX_BYTES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $kind = $this->kind($request);
        $listing = $this->listing($request, $kind);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:' . (int) (ApplicationCsvParser::MAX_BYTES / 1024)],
        ]);

        try {
            $batch = $this->imports->upload($listing, $data['file'], $this->actor($request));
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return redirect()->route($this->routeNames($kind)['preview'], [
            'batch' => $batch,
            'listing' => $listing->uuid,
        ])->with([
            'message' => 'Private CSV uploaded. Map its columns and review every row before importing.',
            'alert-type' => 'success',
        ]);
    }

    public function preview(Request $request): Response|RedirectResponse
    {
        $kind = $this->kind($request);
        $listing = $this->listing($request, $kind);
        $batch = $this->batch($request, $kind, $listing);

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'columns' => ['required', 'array', 'min:1', 'max:' . ApplicationCsvParser::MAX_COLUMNS],
                'columns.*' => ['required', 'array:header,destination'],
                'columns.*.header' => ['required', 'string', 'max:' . ApplicationCsvParser::MAX_CELL_BYTES],
                'columns.*.destination' => ['required', 'string', 'max:190'],
                'duplicate_policy' => ['required', Rule::in(ApplicationImportService::POLICIES)],
            ]);
            $mapping = [];
            foreach ($data['columns'] as $column) {
                $mapping[(string) $column['header']] = (string) $column['destination'];
            }
            $this->imports->preview(
                $batch,
                $mapping,
                (string) $data['duplicate_policy'],
                $this->actor($request),
            );

            return redirect()->route($this->routeNames($kind)['preview'], [
                'batch' => $batch,
                'listing' => $listing->uuid,
            ])->with([
                'message' => 'Preview generated. Review the row decisions before confirming.',
                'alert-type' => 'success',
            ]);
        }

        if ($batch->state === ApplicationImportBatch::STATE_COMPLETED) {
            return redirect()->route($this->routeNames($kind)['result'], [
                'batch' => $batch,
                'listing' => $listing->uuid,
            ]);
        }

        abort_unless(in_array($batch->state, [
            ApplicationImportBatch::STATE_UPLOADED,
            ApplicationImportBatch::STATE_PREVIEWED,
        ], true), 409, 'This import cannot be reviewed in its current state.');

        $listing->loadMissing('translations');
        $common = [
            'title' => 'Review CSV import',
            'sectionLabel' => $this->sectionLabel($kind),
            'recordLabel' => $this->recordLabel($kind),
            'listing' => $listing,
            'listingLabel' => $this->listingLabel($listing),
            'batch' => $batch,
            'routeNames' => $this->routeNames($kind),
        ];
        if ($batch->state === ApplicationImportBatch::STATE_UPLOADED || $request->boolean('remap')) {
            $headers = $this->storedHeaders($batch);
            $destinations = $this->mappingDestinations($batch);
            $suggestedMapping = $this->suggestedMapping($headers, $destinations);
            foreach ((array) $batch->column_mapping as $header => $destination) {
                if (array_key_exists((string) $header, $suggestedMapping)) {
                    $suggestedMapping[(string) $header] = (string) $destination;
                }
            }

            return $this->page($kind, 'preview', $common + [
                'screen' => 'mapping',
                'headers' => $headers,
                'destinations' => $destinations,
                'suggestedMapping' => $suggestedMapping,
                'duplicatePolicy' => (string) old(
                    'duplicate_policy',
                    data_get($batch->options, 'duplicate_policy', 'update')
                ),
            ]);
        }

        $rows = $batch->rows()
            ->select(['id', 'application_import_batch_id', 'row_number', 'state', 'action', 'validation_errors'])
            ->orderBy('row_number')
            ->paginate(50)
            ->withQueryString();

        return $this->page($kind, 'preview', $common + [
            'screen' => 'review',
            'rows' => $rows,
            'duplicatePolicy' => (string) data_get($batch->options, 'duplicate_policy', ''),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $kind = $this->kind($request);
        $listing = $this->listing($request, $kind);
        $batch = $this->batch($request, $kind, $listing);
        $request->validate([
            'confirm_import' => ['required', 'accepted'],
        ], [
            'confirm_import.accepted' => 'Confirm that you reviewed the preview and want to import these rows.',
        ]);

        $this->imports->confirm($batch, $this->actor($request));

        return redirect()->route($this->routeNames($kind)['result'], [
            'batch' => $batch,
            'listing' => $listing->uuid,
        ])->with([
            'message' => 'CSV import completed.',
            'alert-type' => 'success',
        ]);
    }

    public function result(Request $request): Response|RedirectResponse
    {
        $kind = $this->kind($request);
        $listing = $this->listing($request, $kind);
        $batch = $this->batch($request, $kind, $listing);
        if (in_array($batch->state, [ApplicationImportBatch::STATE_UPLOADED, ApplicationImportBatch::STATE_PREVIEWED], true)) {
            return redirect()->route($this->routeNames($kind)['preview'], [
                'batch' => $batch,
                'listing' => $listing->uuid,
            ]);
        }
        $listing->loadMissing('translations');

        return $this->page($kind, 'result', [
            'title' => 'CSV import result',
            'sectionLabel' => $this->sectionLabel($kind),
            'recordLabel' => $this->recordLabel($kind),
            'listing' => $listing,
            'listingLabel' => $this->listingLabel($listing),
            'batch' => $batch,
            'routeNames' => $this->routeNames($kind),
        ]);
    }

    public function downloadErrors(Request $request): StreamedResponse
    {
        $kind = $this->kind($request);
        $listing = $this->listing($request, $kind);
        $batch = $this->batch($request, $kind, $listing);

        return $this->imports->errorReport($batch, $this->actor($request));
    }

    private function kind(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();

        return match (true) {
            str_starts_with($routeName, 'recruitment.imports.') => ApplicationImportBatch::TARGET_JOB,
            str_starts_with($routeName, 'workshop.imports.') => ApplicationImportBatch::TARGET_WORKSHOP,
            default => abort(404),
        };
    }

    private function actor(Request $request): Admin
    {
        $actor = $request->user('admin');
        abort_unless($actor instanceof Admin, 401);

        return $actor;
    }

    private function listing(Request $request, string $kind): JobPosting|Workshop
    {
        $uuid = trim((string) ($request->query('listing') ?: $request->input('listing', '')));
        if ($uuid === '' || !Str::isUuid($uuid)) {
            if ($request->isMethod('get')) {
                abort(404);
            }
            throw ValidationException::withMessages(['listing' => 'Choose a valid listing.']);
        }

        return $this->listingQuery($kind)->where('uuid', $uuid)->firstOrFail();
    }

    private function batch(Request $request, string $kind, JobPosting|Workshop $listing): ApplicationImportBatch
    {
        $routeValue = $request->route('batch');
        $uuid = $routeValue instanceof ApplicationImportBatch ? $routeValue->uuid : (string) $routeValue;
        abort_unless(Str::isUuid($uuid), 404);

        return $this->scopedBatchQuery($kind, $listing)->where('uuid', $uuid)->firstOrFail();
    }

    private function listingQuery(string $kind): Builder
    {
        return $kind === ApplicationImportBatch::TARGET_JOB
            ? JobPosting::query()
            : Workshop::query();
    }

    private function scopedBatchQuery(string $kind, JobPosting|Workshop $listing): Builder
    {
        $query = ApplicationImportBatch::query()->where('target_kind', $kind);

        return $kind === ApplicationImportBatch::TARGET_JOB
            ? $query->where('job_posting_id', $listing->id)->whereNull('workshop_id')
            : $query->where('workshop_id', $listing->id)->whereNull('job_posting_id');
    }

    /** @return list<string> */
    private function storedHeaders(ApplicationImportBatch $batch): array
    {
        $storage = Storage::disk(ApplicationImportService::DISK);
        $validPath = preg_match('#\Aimports/[a-f0-9]{48}\.csv\z#D', (string) $batch->source_path) === 1;
        if ($batch->source_disk !== ApplicationImportService::DISK
            || !$validPath
            || !$storage->exists((string) $batch->source_path)) {
            throw ValidationException::withMessages(['file' => 'The private CSV source is missing or failed integrity verification.']);
        }
        $contents = $storage->get((string) $batch->source_path);
        if (!is_string($contents)
            || !hash_equals((string) $batch->source_sha256, hash('sha256', $contents))) {
            throw ValidationException::withMessages(['file' => 'The private CSV source is missing or failed integrity verification.']);
        }

        try {
            return $this->parser->inspect($storage->path((string) $batch->source_path))['headers'];
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }
    }

    /** @return array<string,array{label:string,type:string,required:bool}> */
    private function mappingDestinations(ApplicationImportBatch $batch): array
    {
        $version = $batch->formVersion()
            ->with(['fields.translations'])
            ->firstOrFail();
        $fields = $version->fields
            ->filter(fn (ApplicationFormField $field): bool => $field->system_key === null
                && $field->type !== ApplicationFormField::TYPE_FILE);
        $destinations = [
            'applicant_name' => ['label' => 'Applicant name', 'type' => 'Identity', 'required' => true],
            'email' => ['label' => 'Email address', 'type' => 'Identity', 'required' => true],
            'phone' => ['label' => 'Phone number', 'type' => 'Identity', 'required' => false],
        ];
        foreach ($fields as $field) {
            $label = $field->translations->firstWhere('locale', 'en')?->label;
            $destinations[(string) $field->field_key] = [
                'label' => trim((string) $label) ?: Str::headline((string) $field->field_key),
                'type' => Str::headline((string) $field->type),
                'required' => (bool) $field->is_required,
            ];
        }

        return $destinations;
    }

    /** @param list<string> $headers
     *  @param array<string,array{label:string,type:string,required:bool}> $destinations
     *  @return array<string,string>
     */
    private function suggestedMapping(array $headers, array $destinations): array
    {
        $aliases = [
            'name' => 'applicant_name',
            'full name' => 'applicant_name',
            'applicant name' => 'applicant_name',
            'email' => 'email',
            'email address' => 'email',
            'e-mail' => 'email',
            'phone' => 'phone',
            'phone number' => 'phone',
            'mobile' => 'phone',
            'mobile number' => 'phone',
        ];
        foreach ($destinations as $key => $destination) {
            $aliases[$this->normalizedHeader($key)] = $key;
            $aliases[$this->normalizedHeader($destination['label'])] = $key;
        }
        $used = [];
        $mapping = [];
        foreach ($headers as $header) {
            $destination = $aliases[$this->normalizedHeader($header)] ?? 'ignore';
            if ($destination !== 'ignore' && isset($used[$destination])) {
                $destination = 'ignore';
            }
            $mapping[$header] = $destination;
            if ($destination !== 'ignore') {
                $used[$destination] = true;
            }
        }

        return $mapping;
    }

    private function normalizedHeader(string $value): string
    {
        return preg_replace('/\s+/u', ' ', Str::lower(trim(str_replace(['_', '-'], ' ', $value)))) ?: '';
    }

    private function listingLabel(JobPosting|Workshop $listing): string
    {
        $title = $listing->translations->firstWhere('locale', 'en')?->title
            ?: $listing->translations->first()?->title;

        return trim((string) $title) ?: $this->sectionLabel(
            $listing instanceof JobPosting ? ApplicationImportBatch::TARGET_JOB : ApplicationImportBatch::TARGET_WORKSHOP
        ) . ' listing ' . Str::limit($listing->uuid, 12, '');
    }

    private function listingForeignKey(string $kind): string
    {
        return $kind === ApplicationImportBatch::TARGET_JOB ? 'job_posting_id' : 'workshop_id';
    }

    private function sectionLabel(string $kind): string
    {
        return $kind === ApplicationImportBatch::TARGET_JOB ? 'Recruitment' : 'Workshop';
    }

    private function recordLabel(string $kind): string
    {
        return $kind === ApplicationImportBatch::TARGET_JOB ? 'job application' : 'workshop registration';
    }

    /** @return array<string,string> */
    private function routeNames(string $kind): array
    {
        $prefix = $kind === ApplicationImportBatch::TARGET_JOB ? 'recruitment.imports.' : 'workshop.imports.';

        return collect(['index', 'create', 'store', 'preview', 'confirm', 'result', 'errors.download'])
            ->mapWithKeys(fn (string $action): array => [str_replace('.', '_', $action) => $prefix . $action])
            ->all();
    }

    /** @param array<string,mixed> $data */
    private function page(string $kind, string $view, array $data): Response
    {
        $area = $kind === ApplicationImportBatch::TARGET_JOB ? 'recruitment' : 'workshops';

        return response()->view("admin.{$area}.imports.{$view}", $data)->withHeaders(self::NO_STORE_HEADERS);
    }
}
