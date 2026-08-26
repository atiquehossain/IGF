<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ApplicationFormField;
use App\Models\ApplicationImportBatch;
use App\Models\ApplicationImportRow;
use App\Models\JobApplication;
use App\Models\JobApplicationStatusEvent;
use App\Models\JobPosting;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Models\WorkshopRegistrationStatusEvent;
use App\Support\ApplicationIdentity;
use App\Support\SafeCsv;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class ApplicationImportService
{
    public const DISK = 'applicant_imports';
    public const POLICIES = ['update', 'skip', 'reject'];
    private const PREVIEW_TTL_HOURS = 24;

    public function __construct(
        private ApplicationCsvParser $parser,
        private ApplicationFormSubmissionValidator $validator,
        private AdminAuditService $audit,
    ) {
    }

    public function upload(JobPosting|Workshop $listing, UploadedFile $file, Admin $actor): ApplicationImportBatch
    {
        if (!$file->isValid() || strtolower((string) $file->getClientOriginalExtension()) !== 'csv') {
            throw ValidationException::withMessages(['file' => 'Upload a valid UTF-8 CSV file.']);
        }
        $inspection = $this->parser->inspect($file);
        $version = $listing->currentFormVersion()->firstOrFail();
        if (!$version->schema_hash) {
            throw ValidationException::withMessages(['file' => 'The listing requires a published form before records can be imported.']);
        }
        $contents = file_get_contents((string) $file->getRealPath());
        if (!is_string($contents) || !hash_equals($inspection['checksum'], hash('sha256', $contents))) {
            throw new RuntimeException('The CSV changed while it was being inspected.');
        }
        $path = 'imports/' . bin2hex(random_bytes(24)) . '.csv';
        $storage = Storage::disk(self::DISK);
        if (!$storage->put($path, $contents, ['visibility' => 'private'])
            || !hash_equals($inspection['checksum'], hash('sha256', $storage->get($path)))) {
            $storage->delete($path);
            throw new RuntimeException('The private CSV copy could not be stored safely.');
        }

        try {
            $batch = DB::transaction(function () use ($listing, $file, $actor, $version, $inspection, $path): ApplicationImportBatch {
                $this->lockListing($listing);
                $attributes = [
                    'target_kind' => $listing instanceof JobPosting ? ApplicationImportBatch::TARGET_JOB : ApplicationImportBatch::TARGET_WORKSHOP,
                    'application_form_version_id' => $version->id,
                    'form_schema_hash' => $version->schema_hash,
                    'state' => ApplicationImportBatch::STATE_UPLOADED,
                    'source_disk' => self::DISK,
                    'source_path' => $path,
                    'source_name' => $this->safeName($file->getClientOriginalName()),
                    'source_sha256' => $inspection['checksum'],
                    'total_rows' => count($inspection['rows']),
                    'uploaded_by_admin_id' => $actor->id,
                ];
                if ($listing instanceof JobPosting) {
                    $attributes['job_posting_id'] = $listing->id;
                } else {
                    $attributes['workshop_id'] = $listing->id;
                }
                $batch = ApplicationImportBatch::query()->create($attributes);
                $this->audit->record($actor, 'application_import.uploaded', $batch, context: [
                    'target_kind' => $batch->target_kind,
                    'row_count' => $batch->total_rows,
                    'bytes' => (int) $file->getSize(),
                ]);

                return $batch;
            }, 3);
        } catch (Throwable $exception) {
            $storage->delete($path);
            throw $exception;
        }

        return $batch->fresh(['formVersion.fields.translations', 'formVersion.fields.options']);
    }

    /** @param array<string, string> $mapping */
    public function preview(ApplicationImportBatch $batch, array $mapping, string $duplicatePolicy, Admin $actor): ApplicationImportBatch
    {
        if (!in_array($duplicatePolicy, self::POLICIES, true)) {
            throw ValidationException::withMessages(['duplicate_policy' => 'Choose update, skip or reject for duplicate emails.']);
        }

        return DB::transaction(function () use ($batch, $mapping, $duplicatePolicy, $actor): ApplicationImportBatch {
            $listing = $this->lockListing($this->listing($batch));
            $locked = ApplicationImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            abort_unless(in_array($locked->state, [ApplicationImportBatch::STATE_UPLOADED, ApplicationImportBatch::STATE_PREVIEWED], true), 409, 'This import can no longer be previewed.');
            $this->assertListingFormMatchesBatch($listing, $locked);
            $preview = $this->buildPreview($locked, $mapping, $duplicatePolicy);

            DB::table('application_import_rows')->where('application_import_batch_id', $locked->id)->delete();
            foreach ($preview['rows'] as $row) {
                $locked->rows()->create($row);
            }
            $locked->update([
                'state' => ApplicationImportBatch::STATE_PREVIEWED,
                'column_mapping' => $mapping,
                'options' => [
                    'duplicate_policy' => $duplicatePolicy,
                    'preview_digest' => $preview['digest'],
                    'expires_at' => now()->addHours(self::PREVIEW_TTL_HOURS)->toIso8601String(),
                ],
                'total_rows' => count($preview['rows']),
                'valid_rows' => $preview['valid'],
                'invalid_rows' => $preview['invalid'],
                'duplicate_rows' => $preview['duplicates'],
                'imported_rows' => 0,
                'previewed_at' => now(),
                'confirmed_at' => null,
                'confirmed_by_admin_id' => null,
            ]);
            $this->audit->record($actor, 'application_import.previewed', $locked, context: [
                'target_kind' => $locked->target_kind,
                'total_rows' => count($preview['rows']),
                'valid_rows' => $preview['valid'],
                'invalid_rows' => $preview['invalid'],
                'duplicate_rows' => $preview['duplicates'],
                'duplicate_policy' => $duplicatePolicy,
            ]);

            return $locked->fresh('rows');
        }, 3);
    }

    public function confirm(ApplicationImportBatch $batch, Admin $actor): ApplicationImportBatch
    {
        return DB::transaction(function () use ($batch, $actor): ApplicationImportBatch {
            $listing = $this->lockListing($this->listing($batch));
            $locked = ApplicationImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            abort_unless($locked->state === ApplicationImportBatch::STATE_PREVIEWED, 409, 'Preview this import before confirming it.');
            $this->assertListingFormMatchesBatch($listing, $locked);
            abort_if(!$locked->previewed_at || $locked->previewed_at->lt(now()->subHours(self::PREVIEW_TTL_HOURS)), 409, 'This import preview expired. Preview it again.');
            $policy = (string) data_get($locked->options, 'duplicate_policy', '');
            $preview = $this->buildPreview($locked, (array) $locked->column_mapping, $policy);
            abort_unless(hash_equals((string) data_get($locked->options, 'preview_digest', ''), $preview['digest']), 409, 'The source, form or duplicate state changed. Review a fresh preview.');
            if ($preview['invalid'] > 0) {
                throw ValidationException::withMessages(['rows' => 'Resolve all invalid rows before confirming the import.']);
            }

            $locked->update([
                'state' => ApplicationImportBatch::STATE_PROCESSING,
                'confirmed_at' => now(),
                'confirmed_by_admin_id' => $actor->id,
            ]);
            $imported = 0;
            foreach ($preview['rows'] as $row) {
                if (($row['action'] ?? null) === ApplicationImportRow::ACTION_SKIP) {
                    continue;
                }
                $target = $listing instanceof JobPosting
                    ? $this->importJob($listing, $locked, $row)
                    : $this->importWorkshop($listing, $locked, $row);
                ApplicationImportRow::query()
                    ->where('application_import_batch_id', $locked->id)
                    ->where('row_number', $row['row_number'])
                    ->update([
                        'state' => ApplicationImportRow::STATE_IMPORTED,
                        'imported_target_uuid' => $target->uuid,
                        'updated_at' => now(),
                    ]);
                $imported++;
            }
            $locked->update([
                'state' => ApplicationImportBatch::STATE_COMPLETED,
                'imported_rows' => $imported,
            ]);
            $this->audit->record($actor, 'application_import.completed', $locked, context: [
                'target_kind' => $locked->target_kind,
                'imported_rows' => $imported,
                'skipped_rows' => count($preview['rows']) - $imported,
                'duplicate_policy' => $policy,
            ]);

            return $locked->fresh('rows');
        }, 3);
    }

    public function errorReport(ApplicationImportBatch $batch, Admin $actor): StreamedResponse
    {
        $rows = $batch->rows()
            ->whereIn('state', [ApplicationImportRow::STATE_INVALID, ApplicationImportRow::STATE_DUPLICATE, ApplicationImportRow::STATE_FAILED])
            ->orderBy('row_number')
            ->get();
        $this->audit->record($actor, 'application_import.error_report_downloaded', $batch, context: [
            'target_kind' => $batch->target_kind,
            'row_count' => $rows->count(),
        ]);

        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'wb');
            if (!is_resource($stream)) {
                throw new RuntimeException('The CSV output stream is unavailable.');
            }
            fwrite($stream, SafeCsv::UTF8_BOM);
            SafeCsv::writeRow($stream, ['CSV row', 'State', 'Action', 'Validation errors']);
            foreach ($rows as $row) {
                SafeCsv::writeRow($stream, [
                    $row->row_number,
                    $row->state,
                    $row->action,
                    collect((array) $row->validation_errors)->flatten()->implode(' | '),
                ]);
            }
        }, 'application-import-errors-' . $batch->uuid . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @param array<string, string> $mapping
     *  @return array{rows:list<array<string,mixed>>,valid:int,invalid:int,duplicates:int,digest:string}
     */
    private function buildPreview(ApplicationImportBatch $batch, array $mapping, string $policy): array
    {
        if (!in_array($policy, self::POLICIES, true)) {
            throw ValidationException::withMessages(['duplicate_policy' => 'The duplicate policy is invalid.']);
        }
        $this->assertStoredSource($batch);
        $inspection = $this->parser->inspect(Storage::disk(self::DISK)->path($batch->source_path));
        $version = $batch->formVersion()->with(['fields.translations', 'fields.options', 'fields.visibilityConditions.sourceField'])->firstOrFail();
        if (!hash_equals((string) $batch->form_schema_hash, (string) $version->schema_hash)) {
            throw ValidationException::withMessages(['form' => 'The form schema no longer matches this import.']);
        }
        $mapping = $this->validateMapping($inspection['headers'], $mapping, $version->fields);
        $lastOccurrence = [];
        foreach ($inspection['rows'] as $sourceRow) {
            $assoc = array_combine($inspection['headers'], $sourceRow['values']);
            $emailHeader = array_search('email', $mapping, true);
            if ($emailHeader !== false) {
                try {
                    $lastOccurrence[ApplicationIdentity::emailHash((string) $assoc[$emailHeader])] = $sourceRow['row_number'];
                } catch (\InvalidArgumentException) {
                    // The authoritative schema validator records the row error.
                }
            }
        }

        $rows = [];
        $valid = 0;
        $invalid = 0;
        $duplicates = 0;
        $seen = [];
        foreach ($inspection['rows'] as $sourceRow) {
            $raw = array_combine($inspection['headers'], $sourceRow['values']);
            $payload = $this->payload($raw, $mapping);
            $row = [
                'row_number' => $sourceRow['row_number'],
                'state' => ApplicationImportRow::STATE_VALID,
                'action' => ApplicationImportRow::ACTION_CREATE,
                'raw_data' => $raw,
                'normalized_data' => null,
                'validation_errors' => null,
            ];
            try {
                $submission = $this->validator->validate($version, $payload, 'en', requireCv: false);
                $hash = ApplicationIdentity::emailHash($submission->email);
                $existing = $this->existing($batch, $hash);
                $repeated = isset($seen[$hash]);
                $notLast = ($lastOccurrence[$hash] ?? $sourceRow['row_number']) !== $sourceRow['row_number'];
                $row['normalized_data'] = [
                    'name' => $submission->name,
                    'email' => $submission->email,
                    'phone' => $submission->phone,
                    'email_hash' => $hash,
                    'answers' => $submission->answers,
                ];

                if ($existing || $repeated || ($policy === 'update' && $notLast)) {
                    $duplicates++;
                    $row['state'] = ApplicationImportRow::STATE_DUPLICATE;
                    if ($policy === 'reject') {
                        $row['action'] = null;
                        $row['validation_errors'] = ['email' => ['This email is duplicated in the listing or CSV.']];
                        $invalid++;
                    } elseif ($policy === 'skip' || $notLast) {
                        $row['action'] = ApplicationImportRow::ACTION_SKIP;
                        $valid++;
                    } else {
                        $row['action'] = ApplicationImportRow::ACTION_UPDATE;
                        $valid++;
                    }
                } else {
                    $valid++;
                }
                $seen[$hash] = true;
            } catch (ValidationException $exception) {
                $row['state'] = ApplicationImportRow::STATE_INVALID;
                $row['action'] = null;
                $row['validation_errors'] = $exception->errors();
                $invalid++;
            }
            $rows[] = $row;
        }
        $digest = hash('sha256', json_encode([
            'source' => $inspection['checksum'],
            'schema' => $version->schema_hash,
            'mapping' => $mapping,
            'policy' => $policy,
            'rows' => $rows,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return compact('rows', 'valid', 'invalid', 'duplicates', 'digest');
    }

    /** @param list<string> $headers
     *  @param array<string, string> $mapping
     *  @param iterable<int, ApplicationFormField> $fields
     *  @return array<string, string>
     */
    private function validateMapping(array $headers, array $mapping, iterable $fields): array
    {
        $fieldMap = collect($fields)->keyBy('field_key');
        $allowed = collect(['ignore', 'applicant_name', 'email', 'phone'])
            ->merge($fieldMap->whereNull('system_key')->reject(fn ($field): bool => $field->type === ApplicationFormField::TYPE_FILE)->keys());
        $normalized = [];
        foreach ($mapping as $header => $destination) {
            $header = (string) $header;
            $destination = (string) $destination;
            if (!in_array($header, $headers, true) || !$allowed->containsStrict($destination)) {
                throw ValidationException::withMessages(['mapping' => 'The column mapping contains an unknown header or protected destination.']);
            }
            $normalized[$header] = $destination;
        }
        $destinations = array_values(array_filter($normalized, fn (string $value): bool => $value !== 'ignore'));
        if (!in_array('applicant_name', $destinations, true) || !in_array('email', $destinations, true)) {
            throw ValidationException::withMessages(['mapping' => 'Map one CSV column to applicant name and one to email.']);
        }
        if (count($destinations) !== count(array_unique($destinations))) {
            throw ValidationException::withMessages(['mapping' => 'Each form field can be mapped only once.']);
        }

        return $normalized;
    }

    /** @param array<string, string> $raw
     *  @param array<string, string> $mapping
     *  @return array<string, mixed>
     */
    private function payload(array $raw, array $mapping): array
    {
        $payload = ['responses' => []];
        foreach ($mapping as $header => $destination) {
            if ($destination === 'ignore') {
                continue;
            }
            $value = trim(strip_tags((string) ($raw[$header] ?? '')));
            if (in_array($destination, ['applicant_name', 'email', 'phone'], true)) {
                $payload[$destination] = $value;
            } else {
                $payload['responses'][$destination] = $value;
            }
        }

        return $payload;
    }

    /** @param array<string, mixed> $row */
    private function importJob(JobPosting $job, ApplicationImportBatch $batch, array $row): JobApplication
    {
        $data = $row['normalized_data'];
        $application = JobApplication::query()
            ->where('job_posting_id', $job->id)
            ->where('email_hash', $data['email_hash'])
            ->lockForUpdate()
            ->first();
        if ($application) {
            $application->forceFill([
                'application_form_version_id' => $batch->application_form_version_id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'submission_count' => $application->submission_count + 1,
                'last_submitted_at' => now(),
                'source' => JobApplication::SOURCE_IMPORT,
                'last_import_batch_id' => $batch->id,
            ])->save();
        } else {
            $application = JobApplication::query()->create([
                'job_posting_id' => $job->id,
                'application_form_version_id' => $batch->application_form_version_id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'workflow_status' => JobApplication::STATUS_NEW,
                'source' => JobApplication::SOURCE_IMPORT,
                'last_import_batch_id' => $batch->id,
            ]);
            $application->statusEvents()->create([
                'from_status' => null,
                'to_status' => JobApplication::STATUS_NEW,
                'source' => JobApplicationStatusEvent::SOURCE_IMPORT,
                'created_at' => now(),
            ]);
        }
        $application->answers()->delete();
        $application->answers()->createMany($data['answers']);

        return $application;
    }

    /** @param array<string, mixed> $row */
    private function importWorkshop(Workshop $workshop, ApplicationImportBatch $batch, array $row): WorkshopRegistration
    {
        $data = $row['normalized_data'];
        $registration = WorkshopRegistration::query()
            ->where('workshop_id', $workshop->id)
            ->where('email_hash', $data['email_hash'])
            ->lockForUpdate()
            ->first();
        if ($registration) {
            $registration->forceFill([
                'application_form_version_id' => $batch->application_form_version_id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'submission_count' => $registration->submission_count + 1,
                'last_submitted_at' => now(),
                'source' => WorkshopRegistration::SOURCE_IMPORT,
                'last_import_batch_id' => $batch->id,
            ])->save();
        } else {
            $registration = WorkshopRegistration::query()->create([
                'workshop_id' => $workshop->id,
                'application_form_version_id' => $batch->application_form_version_id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'workflow_status' => WorkshopRegistration::STATUS_PENDING,
                'source' => WorkshopRegistration::SOURCE_IMPORT,
                'last_import_batch_id' => $batch->id,
            ]);
            $registration->statusEvents()->create([
                'from_status' => null,
                'to_status' => WorkshopRegistration::STATUS_PENDING,
                'source' => WorkshopRegistrationStatusEvent::SOURCE_IMPORT,
                'created_at' => now(),
            ]);
        }
        $registration->answers()->delete();
        $registration->answers()->createMany($data['answers']);

        return $registration;
    }

    private function listing(ApplicationImportBatch $batch): JobPosting|Workshop
    {
        return $batch->target_kind === ApplicationImportBatch::TARGET_JOB
            ? JobPosting::query()->findOrFail($batch->job_posting_id)
            : Workshop::query()->findOrFail($batch->workshop_id);
    }

    private function lockListing(JobPosting|Workshop $listing): JobPosting|Workshop
    {
        return $listing->newQuery()->whereKey($listing->id)->lockForUpdate()->firstOrFail();
    }

    private function assertListingFormMatchesBatch(
        JobPosting|Workshop $listing,
        ApplicationImportBatch $batch,
    ): void {
        $currentVersion = $listing->currentFormVersion()
            ->select(['id', 'schema_hash'])
            ->first();
        $matchesVersion = $currentVersion
            && (int) $listing->current_form_version_id === (int) $batch->application_form_version_id
            && (int) $currentVersion->id === (int) $batch->application_form_version_id;
        $matchesSchema = $matchesVersion
            && is_string($currentVersion->schema_hash)
            && is_string($batch->form_schema_hash)
            && hash_equals($batch->form_schema_hash, $currentVersion->schema_hash);

        if (!$matchesSchema) {
            throw ValidationException::withMessages([
                'mapping' => 'The listing form changed after this CSV was uploaded. Upload it again and review a fresh column mapping.',
            ]);
        }
    }

    private function existing(ApplicationImportBatch $batch, string $emailHash): bool
    {
        return $batch->target_kind === ApplicationImportBatch::TARGET_JOB
            ? JobApplication::query()->where('job_posting_id', $batch->job_posting_id)->where('email_hash', $emailHash)->exists()
            : WorkshopRegistration::query()->where('workshop_id', $batch->workshop_id)->where('email_hash', $emailHash)->exists();
    }

    private function assertStoredSource(ApplicationImportBatch $batch): void
    {
        if ($batch->source_disk !== self::DISK
            || preg_match('#\Aimports/[a-f0-9]{48}\.csv\z#D', (string) $batch->source_path) !== 1
            || !Storage::disk(self::DISK)->exists($batch->source_path)
            || !hash_equals((string) $batch->source_sha256, hash('sha256', Storage::disk(self::DISK)->get($batch->source_path)))) {
            throw ValidationException::withMessages(['file' => 'The private CSV source is missing or failed integrity verification.']);
        }
    }

    private function safeName(string $name): string
    {
        $base = pathinfo(str_replace(["\r", "\n", "\0", '/', '\\'], '-', $name), PATHINFO_FILENAME);
        $base = trim((string) preg_replace('/[^\pL\pN ._()-]+/u', '-', $base), ' .-_');

        return mb_substr($base === '' ? 'google-forms-export' : $base, 0, 140) . '.csv';
    }
}
