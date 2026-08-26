<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ApplicationFormField;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Support\SafeCsv;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ApplicationExportService
{
    public const JOB_COLUMNS = [
        'reference', 'name', 'email', 'phone', 'status', 'assigned_to',
        'submission_count', 'first_submitted_at', 'last_submitted_at', 'average_score',
    ];
    public const WORKSHOP_COLUMNS = [
        'reference', 'name', 'email', 'phone', 'status', 'assigned_to',
        'submission_count', 'first_submitted_at', 'last_submitted_at',
        'waitlisted_at', 'confirmed_at',
    ];

    public function __construct(private AdminAuditService $audit)
    {
    }

    /** @param list<string> $columns
     *  @param array<string, mixed> $filterSummary
     */
    public function jobs(JobPosting $job, Builder $query, array $columns, Admin $actor, array $filterSummary = []): StreamedResponse
    {
        [$columns, $fields] = $this->columns($this->fieldsForQuery($query), $columns, self::JOB_COLUMNS);
        $rowCount = (clone $query)->count();
        $this->audit->record($actor, 'recruitment.applications.exported', $job, context: [
            'row_count' => $rowCount,
            'columns' => $columns,
            'filters' => $this->auditFilters($filterSummary),
        ]);

        return $this->response(
            'job-applications-' . now()->format('Y-m-d') . '.csv',
            $query,
            $columns,
            $fields,
            $rowCount,
            true,
        );
    }

    /** @param list<string> $columns
     *  @param array<string, mixed> $filterSummary
     */
    public function workshops(Workshop $workshop, Builder $query, array $columns, Admin $actor, array $filterSummary = []): StreamedResponse
    {
        [$columns, $fields] = $this->columns($this->fieldsForQuery($query), $columns, self::WORKSHOP_COLUMNS);
        $rowCount = (clone $query)->count();
        $this->audit->record($actor, 'workshop.registrations.exported', $workshop, context: [
            'row_count' => $rowCount,
            'columns' => $columns,
            'filters' => $this->auditFilters($filterSummary),
        ]);

        return $this->response(
            'workshop-registrations-' . now()->format('Y-m-d') . '.csv',
            $query,
            $columns,
            $fields,
            $rowCount,
            false,
        );
    }

    /** @param iterable<int, ApplicationFormField> $availableFields
     *  @param list<string> $requested
     *  @param list<string> $fixed
     *  @return array{list<string>,array<string,ApplicationFormField>}
     */
    private function columns(iterable $availableFields, array $requested, array $fixed): array
    {
        $fields = collect($availableFields)
            ->whereNull('system_key')
            ->reject(fn (ApplicationFormField $field): bool => $field->type === ApplicationFormField::TYPE_FILE)
            ->keyBy(fn (ApplicationFormField $field): string => 'answer:' . $field->field_key);
        $allowed = collect($fixed)->merge($fields->keys());
        $columns = collect($requested)
            ->filter(fn (mixed $column): bool => is_string($column) && $allowed->containsStrict($column))
            ->unique()
            ->take(50)
            ->values();
        if ($columns->isEmpty()) {
            $columns = collect($fixed);
        }

        return [$columns->all(), $fields->only($columns->all())->all()];
    }

    /** @return list<ApplicationFormField> */
    private function fieldsForQuery(Builder $query): array
    {
        $versionColumn = $query->getModel()->qualifyColumn('application_form_version_id');
        $versionIds = (clone $query)
            ->reorder()
            ->select($versionColumn)
            ->distinct()
            ->pluck($versionColumn)
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($versionIds->isEmpty()) {
            return [];
        }

        return ApplicationFormField::query()
            ->with(['translations', 'version:id,version'])
            ->whereIn('application_form_version_id', $versionIds->all())
            ->get()
            ->sortBy(fn (ApplicationFormField $field): string => sprintf(
                '%010d:%020d:%010d',
                (int) ($field->version?->version ?? 0),
                (int) $field->application_form_version_id,
                (int) $field->position,
            ))
            ->values()
            ->all();
    }

    /** @param list<string> $columns
     *  @param array<string, ApplicationFormField> $fields
     */
    private function response(
        string $filename,
        Builder $query,
        array $columns,
        array $fields,
        int $rowCount,
        bool $job,
    ): StreamedResponse {
        return response()->streamDownload(function () use ($query, $columns, $fields, $rowCount, $job): void {
            $stream = fopen('php://output', 'wb');
            if (!is_resource($stream)) {
                throw new \RuntimeException('The CSV output stream is unavailable.');
            }
            fwrite($stream, SafeCsv::UTF8_BOM);
            SafeCsv::writeRow($stream, array_map(fn (string $column): string => $this->heading($column, $fields), $columns));

            if ($rowCount === 0) {
                return;
            }
            $exportQuery = clone $query;
            $exportQuery->with([
                'assignedAdmin:id,name,username',
                'answers.field:id,field_key,type',
            ]);
            if ($job) {
                $exportQuery->withAvg('scores', 'score');
            }
            $exportQuery->reorder('id')->chunkById(250, function ($records) use ($stream, $columns, $job): void {
                foreach ($records as $record) {
                    $answers = $record->answers->keyBy(fn ($answer): string => 'answer:' . $answer->field->field_key);
                    SafeCsv::writeRow($stream, array_map(
                        fn (string $column): mixed => $this->value($record, $column, $answers, $job),
                        $columns,
                    ));
                }
            });
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'attachment',
        ]);
    }

    /** @param array<string, ApplicationFormField> $fields */
    private function heading(string $column, array $fields): string
    {
        if (isset($fields[$column])) {
            $field = $fields[$column];
            $field->loadMissing('translations');

            return (string) ($field->translations->firstWhere('locale', 'en')?->label ?: $field->field_key);
        }

        return match ($column) {
            'reference' => 'Reference',
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'status' => 'Status',
            'assigned_to' => 'Assigned to',
            'submission_count' => 'Submissions',
            'first_submitted_at' => 'First submitted at',
            'last_submitted_at' => 'Last submitted at',
            'average_score' => 'Average score',
            'waitlisted_at' => 'Waitlisted at',
            'confirmed_at' => 'Confirmed at',
            default => $column,
        };
    }

    private function value(JobApplication|WorkshopRegistration $record, string $column, $answers, bool $job): mixed
    {
        if (str_starts_with($column, 'answer:')) {
            $answer = $answers->get($column);
            if (!$answer) {
                return '';
            }

            return $answer->value_json !== null
                ? implode('; ', (array) $answer->value_json)
                : ($answer->value_boolean !== null
                    ? ($answer->value_boolean ? 'Yes' : 'No')
                    : ($answer->value_date?->format('Y-m-d')
                        ?? $answer->value_number
                        ?? $answer->value_text
                        ?? ''));
        }

        return match ($column) {
            'reference' => $record->reference_number,
            'name' => $record->name,
            'email' => $record->email,
            'phone' => $record->phone,
            'status' => $record->workflow_status,
            'assigned_to' => $record->assignedAdmin?->name,
            'submission_count' => $record->submission_count,
            'first_submitted_at' => $record->first_submitted_at,
            'last_submitted_at' => $record->last_submitted_at,
            'average_score' => $job ? ($record->scores_avg_score ?? '') : '',
            'waitlisted_at' => $record instanceof WorkshopRegistration ? $record->waitlisted_at : '',
            'confirmed_at' => $record instanceof WorkshopRegistration ? $record->confirmed_at : '',
            default => '',
        };
    }

    /** @param array<string, mixed> $filters
     *  @return array<string, scalar|null>
     */
    private function auditFilters(array $filters): array
    {
        return collect($filters)
            ->only(['status', 'assigned_to', 'from', 'to', 'sort', 'direction'])
            ->map(fn (mixed $value): mixed => is_scalar($value) || $value === null ? $value : null)
            ->all();
    }
}
