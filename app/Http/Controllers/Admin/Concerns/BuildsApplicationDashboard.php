<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Admin;
use App\Models\AdminListingPreference;
use App\Models\ApplicationFormField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait BuildsApplicationDashboard
{
    /**
     * @param list<string> $sorts
     * @param list<string> $statuses
     * @return array<string, mixed>
     */
    private function validatedDashboardFilters(
        Request $request,
        array $sorts,
        array $statuses,
        string $listingTable,
    ): array {
        $filters = $request->validate([
            'listing' => [
                'nullable',
                'uuid',
                Rule::exists($listingTable, 'uuid')->whereNull('deleted_at'),
            ],
            'status' => ['nullable', Rule::in($statuses)],
            'assigned_to' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '' || $value === 'unassigned') {
                        return;
                    }
                    $id = filter_var($value, FILTER_VALIDATE_INT);
                    if ($id === false || $id < 1 || !Admin::query()->whereKey($id)->where('status', 1)->exists()) {
                        $fail('Choose an active administrator or unassigned.');
                    }
                },
            ],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sort' => ['nullable', Rule::in($sorts)],
            'direction' => ['nullable', Rule::in(AdminListingPreference::SORT_DIRECTIONS)],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ]);

        return collect($filters)
            ->map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value)
            ->reject(fn (mixed $value): bool => $value === '' || $value === null)
            ->all();
    }

    /** @return Collection<int, Admin> */
    private function activeDashboardAssignees(): Collection
    {
        return Admin::query()
            ->where('status', 1)
            ->orderBy('name')
            ->orderBy('username')
            ->get(['id', 'name', 'username']);
    }

    private function activeDashboardAssignee(mixed $id): ?Admin
    {
        if ($id === null || $id === '') {
            return null;
        }

        return Admin::query()->whereKey($id)->where('status', 1)->firstOrFail();
    }

    /**
     * Return one English-labelled column for each stable custom field key used
     * by any form version represented in the selected listing.
     *
     * @param class-string<Model> $recordClass
     * @return Collection<int, array{key:string,label:string}>
     */
    private function dashboardAnswerColumns(
        string $recordClass,
        string $listingForeignKey,
        int $listingId,
        ?int $currentVersionId,
    ): Collection {
        $versionIds = $recordClass::query()
            ->where($listingForeignKey, $listingId)
            ->distinct()
            ->pluck('application_form_version_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id);
        if ($currentVersionId) {
            $versionIds->push($currentVersionId);
        }
        $versionIds = $versionIds->unique()->values();
        if ($versionIds->isEmpty()) {
            return collect();
        }

        return ApplicationFormField::query()
            ->whereIn('application_form_version_id', $versionIds)
            ->whereNull('system_key')
            ->where('type', '!=', ApplicationFormField::TYPE_FILE)
            ->with('translations')
            ->orderByDesc('application_form_version_id')
            ->orderBy('position')
            ->get()
            ->unique('field_key')
            ->map(function (ApplicationFormField $field): array {
                $label = $field->translations->firstWhere('locale', 'en')?->label;

                return [
                    'key' => 'answer:' . $field->field_key,
                    'label' => (string) ($label ?: str_replace('_', ' ', ucfirst($field->field_key))),
                ];
            })
            ->values();
    }

    /**
     * @param Collection<int, array{key:string,label:string}> $availableColumns
     * @return array{columns:list<string>,sort:?string,direction:?string}
     */
    private function dashboardPreference(
        Admin $actor,
        string $listingKey,
        Collection $availableColumns,
    ): array {
        $preference = AdminListingPreference::query()
            ->where('admin_id', $actor->getKey())
            ->where('listing_key', $listingKey)
            ->first();
        $allowed = $availableColumns->pluck('key');
        $columns = collect($preference?->visible_columns)
            ->filter(fn (mixed $column): bool => is_string($column) && $allowed->containsStrict($column))
            ->unique()
            ->take(20)
            ->values();
        if (!$preference) {
            $columns = $allowed->take(3)->values();
        }

        return [
            'columns' => $columns->all(),
            'sort' => $preference?->sort_column,
            'direction' => $preference?->sort_direction,
        ];
    }

    /**
     * @param Collection<int, array{key:string,label:string}> $availableColumns
     * @param list<string> $allowedSorts
     */
    private function saveDashboardPreference(
        Request $request,
        Admin $actor,
        string $listingKey,
        Collection $availableColumns,
        array $allowedSorts,
    ): void {
        $data = $request->validate([
            'visible_columns' => ['nullable', 'array', 'max:20'],
            'visible_columns.*' => ['string', 'max:120', 'distinct'],
            'sort' => ['required', Rule::in($allowedSorts)],
            'direction' => ['required', Rule::in(AdminListingPreference::SORT_DIRECTIONS)],
        ]);
        $allowed = $availableColumns->pluck('key');
        $columns = collect($data['visible_columns'] ?? [])
            ->filter(fn (mixed $column): bool => is_string($column) && $allowed->containsStrict($column))
            ->unique()
            ->take(20)
            ->values()
            ->all();

        AdminListingPreference::query()->updateOrCreate(
            ['admin_id' => $actor->getKey(), 'listing_key' => $listingKey],
            [
                'visible_columns' => $columns,
                'sort_column' => $data['sort'],
                'sort_direction' => $data['direction'],
            ],
        );
    }

    /** @return array<string, string> */
    private function dashboardAnswerValues(Model $record): array
    {
        return $record->answers
            ->filter(fn ($answer): bool => $answer->field !== null)
            ->mapWithKeys(fn ($answer): array => [
                'answer:' . $answer->field->field_key => $this->dashboardAnswerValue($answer),
            ])
            ->all();
    }

    /** @return list<array{label:string,value:string}> */
    private function dashboardAnswerRows(Model $record): array
    {
        return $record->answers
            ->filter(fn ($answer): bool => $answer->field !== null)
            ->sortBy(fn ($answer): int => (int) $answer->field->position)
            ->map(function ($answer): array {
                $label = $answer->field->translations->firstWhere('locale', 'en')?->label;

                return [
                    'label' => (string) ($label ?: str_replace('_', ' ', ucfirst($answer->field->field_key))),
                    'value' => $this->dashboardAnswerValue($answer),
                ];
            })
            ->values()
            ->all();
    }

    private function dashboardAnswerValue(Model $answer): string
    {
        if (is_array($answer->value_json)) {
            $optionLabels = $answer->field?->options
                ?->mapWithKeys(function ($option): array {
                    $label = $option->translations->firstWhere('locale', 'en')?->label;

                    return [$option->option_key => (string) ($label ?: $option->option_key)];
                }) ?? collect();

            return collect($answer->value_json)
                ->map(fn (mixed $value): string => (string) ($optionLabels->get((string) $value) ?: $value))
                ->implode(', ');
        }
        if ($answer->value_boolean !== null) {
            return $answer->value_boolean ? 'Yes' : 'No';
        }
        if ($answer->value_date !== null) {
            return $answer->value_date->format('Y-m-d');
        }
        if ($answer->value_number !== null) {
            return rtrim(rtrim((string) $answer->value_number, '0'), '.');
        }

        return (string) ($answer->value_text ?? '');
    }

    /** @return array<string, scalar> */
    private function safeDashboardQuery(array $filters, string $listingUuid): array
    {
        return collect($filters)
            ->only(['status', 'assigned_to', 'from', 'to', 'sort', 'direction', 'per_page'])
            ->filter(fn (mixed $value): bool => is_scalar($value) && $value !== '')
            ->prepend($listingUuid, 'listing')
            ->all();
    }

    private function assertDashboardConfirmation(Request $request, string $expected): void
    {
        $data = $request->validate([
            'confirmation' => ['required', 'string', 'max:100'],
        ]);
        if (!hash_equals($expected, trim($data['confirmation']))) {
            throw ValidationException::withMessages([
                'confirmation' => 'The confirmation text does not match.',
            ]);
        }
    }
}
