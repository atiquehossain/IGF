<?php

namespace App\Services;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Illuminate\Database\Eloquent\Builder;

final class ApplicationListingService
{
    public const JOB_SORTS = [
        'last_submitted_at', 'first_submitted_at', 'name', 'workflow_status',
        'submission_count', 'average_score',
    ];
    public const WORKSHOP_SORTS = [
        'last_submitted_at', 'first_submitted_at', 'name', 'workflow_status',
        'submission_count', 'waitlisted_at', 'confirmed_at',
    ];

    /** @param array<string, mixed> $filters */
    public function jobs(JobPosting $job, array $filters, string $privateSearch = ''): Builder
    {
        $sort = in_array($filters['sort'] ?? '', self::JOB_SORTS, true)
            ? $filters['sort']
            : 'last_submitted_at';
        $direction = ($filters['direction'] ?? '') === 'asc' ? 'asc' : 'desc';
        $query = JobApplication::query()
            ->where('job_posting_id', $job->id)
            ->with(['assignedAdmin:id,name,username', 'documents:id,uuid,job_application_id,application_form_field_id,document_kind,original_name,bytes'])
            ->withAvg('scores', 'score');

        $this->applyCommonFilters($query, $filters, $privateSearch, JobApplication::STATUSES);
        if ($sort === 'average_score') {
            $query->orderBy('scores_avg_score', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        return $query->orderBy('id', 'desc');
    }

    /** @param array<string, mixed> $filters */
    public function workshops(Workshop $workshop, array $filters, string $privateSearch = ''): Builder
    {
        $sort = in_array($filters['sort'] ?? '', self::WORKSHOP_SORTS, true)
            ? $filters['sort']
            : 'last_submitted_at';
        $direction = ($filters['direction'] ?? '') === 'asc' ? 'asc' : 'desc';
        $query = WorkshopRegistration::query()
            ->where('workshop_id', $workshop->id)
            ->with('assignedAdmin:id,name,username');

        $this->applyCommonFilters($query, $filters, $privateSearch, WorkshopRegistration::STATUSES);

        return $query->orderBy($sort, $direction)->orderBy('id', 'desc');
    }

    /** @param array<string, mixed> $filters
     *  @param list<string> $statuses
     */
    private function applyCommonFilters(Builder $query, array $filters, string $privateSearch, array $statuses): void
    {
        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, $statuses, true)) {
            $query->where('workflow_status', $status);
        }
        $assigned = filter_var($filters['assigned_to'] ?? null, FILTER_VALIDATE_INT);
        if ($assigned !== false && $assigned > 0) {
            $query->where('assigned_to_admin_id', $assigned);
        } elseif (($filters['assigned_to'] ?? null) === 'unassigned') {
            $query->whereNull('assigned_to_admin_id');
        }
        if (($filters['from'] ?? '') !== '') {
            $query->where('last_submitted_at', '>=', (string) $filters['from'] . ' 00:00:00');
        }
        if (($filters['to'] ?? '') !== '') {
            $query->where('last_submitted_at', '<=', (string) $filters['to'] . ' 23:59:59');
        }
        if ($privateSearch !== '') {
            $pattern = '%' . $privateSearch . '%';
            $query->where(function (Builder $fields) use ($pattern): void {
                $fields->where('name', 'like', $pattern)
                    ->orWhere('email', 'like', $pattern)
                    ->orWhere('phone', 'like', $pattern)
                    ->orWhere('reference_number', 'like', $pattern);
            });
        }
    }
}
