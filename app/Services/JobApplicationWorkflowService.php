<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\JobApplication;
use App\Models\JobApplicationNote;
use App\Models\JobApplicationScore;
use App\Models\JobApplicationStatusEvent;
use App\Models\JobPosting;
use App\Models\JobScorecardCriterion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class JobApplicationWorkflowService
{
    public const MAX_BULK_RECORDS = 100;

    public const TRANSITIONS = [
        JobApplication::STATUS_NEW => [
            JobApplication::STATUS_UNDER_REVIEW,
            JobApplication::STATUS_REJECTED,
            JobApplication::STATUS_WITHDRAWN,
        ],
        JobApplication::STATUS_UNDER_REVIEW => [
            JobApplication::STATUS_SHORTLISTED,
            JobApplication::STATUS_INTERVIEW,
            JobApplication::STATUS_REJECTED,
            JobApplication::STATUS_WITHDRAWN,
        ],
        JobApplication::STATUS_SHORTLISTED => [
            JobApplication::STATUS_UNDER_REVIEW,
            JobApplication::STATUS_INTERVIEW,
            JobApplication::STATUS_REJECTED,
            JobApplication::STATUS_WITHDRAWN,
        ],
        JobApplication::STATUS_INTERVIEW => [
            JobApplication::STATUS_SHORTLISTED,
            JobApplication::STATUS_OFFERED,
            JobApplication::STATUS_REJECTED,
            JobApplication::STATUS_WITHDRAWN,
        ],
        JobApplication::STATUS_OFFERED => [
            JobApplication::STATUS_INTERVIEW,
            JobApplication::STATUS_HIRED,
            JobApplication::STATUS_REJECTED,
            JobApplication::STATUS_WITHDRAWN,
        ],
        JobApplication::STATUS_HIRED => [],
        JobApplication::STATUS_REJECTED => [JobApplication::STATUS_UNDER_REVIEW],
        JobApplication::STATUS_WITHDRAWN => [JobApplication::STATUS_UNDER_REVIEW],
    ];

    public function __construct(private AdminAuditService $audit)
    {
    }

    public function transition(JobApplication $application, string $toStatus, Admin $actor): JobApplication
    {
        $this->assertActor($actor);

        return DB::transaction(function () use ($application, $toStatus, $actor): JobApplication {
            [$posting, $locked] = $this->lockApplication($application);
            $this->applyTransition($posting, $locked, $toStatus, $actor);

            return $locked->fresh(['statusEvents', 'assignedAdmin']);
        }, 3);
    }

    public function assign(JobApplication $application, ?Admin $assignee, Admin $actor): JobApplication
    {
        $this->assertActor($actor);
        $this->assertAssignee($assignee);

        return DB::transaction(function () use ($application, $assignee, $actor): JobApplication {
            [$posting, $locked] = $this->lockApplication($application);
            $before = $locked->assigned_to_admin_id;
            $after = $assignee?->getKey();
            if ((string) $before === (string) $after) {
                return $locked;
            }

            $locked->update(['assigned_to_admin_id' => $after]);
            $this->audit->record(
                $actor,
                'job_application.assigned',
                $locked,
                changes: ['assigned_to_admin_id' => ['from' => $before, 'to' => $after]],
                context: ['job_posting_id' => (int) $posting->getKey()],
            );

            return $locked->fresh(['assignedAdmin']);
        }, 3);
    }

    public function addNote(JobApplication $application, string $body, Admin $actor): JobApplicationNote
    {
        $this->assertActor($actor);
        $body = $this->validatedNote($body);

        return DB::transaction(function () use ($application, $body, $actor): JobApplicationNote {
            [$posting, $locked] = $this->lockApplication($application);
            $note = $locked->notes()->create([
                'author_admin_id' => $actor->getKey(),
                'author_name_snapshot' => $this->actorSnapshot($actor),
                'body' => $body,
            ]);
            $this->audit->record(
                $actor,
                'job_application.note_added',
                $locked,
                context: [
                    'job_posting_id' => (int) $posting->getKey(),
                    'note_id' => (int) $note->getKey(),
                ],
            );

            return $note;
        }, 3);
    }

    public function score(
        JobApplication $application,
        JobScorecardCriterion $criterion,
        float|int|string $score,
        Admin $actor,
        ?string $comment = null,
    ): JobApplicationScore {
        $this->assertActor($actor);
        if (!is_numeric($score) || !is_finite((float) $score)) {
            throw ValidationException::withMessages(['score' => 'Enter a valid score.']);
        }
        $comment = $this->validatedComment($comment);

        return DB::transaction(function () use ($application, $criterion, $score, $actor, $comment): JobApplicationScore {
            [$posting, $locked] = $this->lockApplication($application);
            $lockedCriterion = JobScorecardCriterion::query()
                ->whereKey($criterion->getKey())
                ->where('job_posting_id', $posting->getKey())
                ->lockForUpdate()
                ->first();
            if (!$lockedCriterion || !$lockedCriterion->is_enabled) {
                throw ValidationException::withMessages(['score' => 'This scorecard criterion is unavailable.']);
            }

            $numericScore = (float) $score;
            $maximum = (float) $lockedCriterion->maximum_score;
            if ($numericScore < 0 || $numericScore > $maximum) {
                throw ValidationException::withMessages(['score' => "The score must be between 0 and {$maximum}."]);
            }

            $existing = JobApplicationScore::query()
                ->where('job_application_id', $locked->getKey())
                ->where('job_scorecard_criterion_id', $lockedCriterion->getKey())
                ->where('reviewer_admin_id', $actor->getKey())
                ->lockForUpdate()
                ->first();
            $before = $existing?->score;
            $result = $existing ?: new JobApplicationScore();
            $result->fill([
                'job_application_id' => $locked->getKey(),
                'job_scorecard_criterion_id' => $lockedCriterion->getKey(),
                'reviewer_admin_id' => $actor->getKey(),
                'score' => $numericScore,
                'criterion_label_snapshot' => $lockedCriterion->label,
                'maximum_score_snapshot' => $lockedCriterion->maximum_score,
                'comment' => $comment,
            ])->save();

            $this->audit->record(
                $actor,
                $existing ? 'job_application.score_updated' : 'job_application.score_created',
                $locked,
                changes: ['score' => ['from' => $before, 'to' => $numericScore]],
                context: [
                    'job_posting_id' => (int) $posting->getKey(),
                    'criterion_id' => (int) $lockedCriterion->getKey(),
                ],
            );

            return $result->fresh();
        }, 3);
    }

    /** @param iterable<int, int|string> $applicationIds
     *  @return Collection<int, JobApplication>
     */
    public function bulkTransition(iterable $applicationIds, string $toStatus, Admin $actor): Collection
    {
        $this->assertActor($actor);
        $ids = $this->normalizeIds($applicationIds);
        $listingIds = $this->jobPostingIdsFor($ids);

        return DB::transaction(function () use ($ids, $listingIds, $toStatus, $actor): Collection {
            $this->lockPostings($listingIds);
            $applications = JobApplication::query()
                ->whereKey($ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $this->assertCompleteSet($applications, $ids);

            foreach ($applications as $application) {
                $this->assertTransitionAllowed($application->workflow_status, $toStatus);
            }
            foreach ($applications as $application) {
                $this->applyTransitionWithoutAudit($application, $toStatus, $actor);
            }

            $this->audit->record(
                $actor,
                'job_application.bulk_status_changed',
                'job-application-bulk',
                context: [
                    'record_count' => count($ids),
                    'listing_count' => count($listingIds),
                    'to_status' => $toStatus,
                ],
            );

            return JobApplication::query()->whereKey($ids)->orderBy('id')->get();
        }, 3);
    }

    /** @param iterable<int, int|string> $applicationIds
     *  @return Collection<int, JobApplication>
     */
    public function bulkAssign(iterable $applicationIds, ?Admin $assignee, Admin $actor): Collection
    {
        $this->assertActor($actor);
        $this->assertAssignee($assignee);
        $ids = $this->normalizeIds($applicationIds);
        $listingIds = $this->jobPostingIdsFor($ids);

        return DB::transaction(function () use ($ids, $listingIds, $assignee, $actor): Collection {
            $this->lockPostings($listingIds);
            $applications = JobApplication::query()
                ->whereKey($ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $this->assertCompleteSet($applications, $ids);
            $assigneeId = $assignee?->getKey();
            foreach ($applications as $application) {
                $application->update(['assigned_to_admin_id' => $assigneeId]);
            }

            $this->audit->record(
                $actor,
                'job_application.bulk_assigned',
                'job-application-bulk',
                context: [
                    'record_count' => count($ids),
                    'listing_count' => count($listingIds),
                    'assigned_to_admin_id' => $assigneeId,
                ],
            );

            return JobApplication::query()->whereKey($ids)->orderBy('id')->get();
        }, 3);
    }

    /** @return array{0: JobPosting, 1: JobApplication} */
    private function lockApplication(JobApplication $application): array
    {
        $id = $application->getKey();
        $listingId = $application->job_posting_id
            ?: JobApplication::query()->whereKey($id)->value('job_posting_id');
        $posting = JobPosting::query()->whereKey($listingId)->lockForUpdate()->firstOrFail();
        $locked = JobApplication::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        if ((int) $locked->job_posting_id !== (int) $posting->getKey()) {
            throw ValidationException::withMessages(['application' => 'The application changed. Reload and try again.']);
        }

        return [$posting, $locked];
    }

    private function applyTransition(
        JobPosting $posting,
        JobApplication $application,
        string $toStatus,
        Admin $actor,
    ): void {
        $fromStatus = $application->workflow_status;
        $this->applyTransitionWithoutAudit($application, $toStatus, $actor);
        $this->audit->record(
            $actor,
            'job_application.status_changed',
            $application,
            changes: ['workflow_status' => ['from' => $fromStatus, 'to' => $toStatus]],
            context: ['job_posting_id' => (int) $posting->getKey()],
        );
    }

    private function applyTransitionWithoutAudit(
        JobApplication $application,
        string $toStatus,
        Admin $actor,
    ): void {
        $fromStatus = $application->workflow_status;
        $this->assertTransitionAllowed($fromStatus, $toStatus);
        $now = now();
        $application->update([
            'workflow_status' => $toStatus,
            'status_changed_at' => $now,
            'status_changed_by_admin_id' => $actor->getKey(),
        ]);
        $application->statusEvents()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_admin_id' => $actor->getKey(),
            'actor_name_snapshot' => $this->actorSnapshot($actor),
            'source' => JobApplicationStatusEvent::SOURCE_ADMIN,
            'created_at' => $now,
        ]);
    }

    private function assertTransitionAllowed(string $fromStatus, string $toStatus): void
    {
        if (!in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'workflow_status' => "The transition from {$fromStatus} to {$toStatus} is not allowed.",
            ]);
        }
    }

    /** @param iterable<int, int|string> $ids
     *  @return list<int>
     */
    private function normalizeIds(iterable $ids): array
    {
        $normalized = collect($ids)
            ->map(fn (mixed $id): int => filter_var($id, FILTER_VALIDATE_INT) ?: 0)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
        if ($normalized === [] || count($normalized) > self::MAX_BULK_RECORDS) {
            throw ValidationException::withMessages([
                'applications' => 'Select between 1 and ' . self::MAX_BULK_RECORDS . ' applications.',
            ]);
        }

        return $normalized;
    }

    /** @param list<int> $applicationIds
     *  @return list<int>
     */
    private function jobPostingIdsFor(array $applicationIds): array
    {
        $rows = JobApplication::query()->whereKey($applicationIds)->get(['id', 'job_posting_id']);
        $this->assertCompleteSet($rows, $applicationIds);

        return $rows->pluck('job_posting_id')->map(fn (mixed $id): int => (int) $id)->unique()->sort()->values()->all();
    }

    /** @param list<int> $listingIds */
    private function lockPostings(array $listingIds): void
    {
        $locked = JobPosting::query()->whereKey($listingIds)->orderBy('id')->lockForUpdate()->get(['id']);
        if ($locked->count() !== count($listingIds)) {
            throw ValidationException::withMessages(['applications' => 'One or more job listings are unavailable.']);
        }
    }

    /** @param Collection<int, mixed> $models
     *  @param list<int> $ids
     */
    private function assertCompleteSet(Collection $models, array $ids): void
    {
        if ($models->count() !== count($ids)) {
            throw ValidationException::withMessages(['applications' => 'One or more applications are unavailable.']);
        }
    }

    private function validatedNote(string $body): string
    {
        $body = trim($body);
        if ($body === ''
            || mb_strlen($body) > 20_000
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $body) === 1) {
            throw ValidationException::withMessages(['body' => 'Notes must contain between 1 and 20,000 characters.']);
        }

        return $body;
    }

    private function validatedComment(?string $comment): ?string
    {
        $comment = $comment === null ? null : trim($comment);
        if ($comment !== null && (mb_strlen($comment) > 20_000
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $comment) === 1)) {
            throw ValidationException::withMessages(['comment' => 'Score comments cannot exceed 20,000 characters.']);
        }

        return $comment === '' ? null : $comment;
    }

    private function actorSnapshot(Admin $actor): string
    {
        return mb_substr((string) ($actor->username ?: $actor->name ?: 'Administrator'), 0, 100);
    }

    private function assertActor(Admin $actor): void
    {
        if (!$actor->exists || !$actor->getKey()) {
            throw ValidationException::withMessages(['actor' => 'A persisted administrator is required.']);
        }
    }

    private function assertAssignee(?Admin $assignee): void
    {
        if ($assignee && (!$assignee->exists || !$assignee->getKey() || (int) $assignee->status !== 1)) {
            throw ValidationException::withMessages(['assigned_to_admin_id' => 'Choose an active administrator.']);
        }
    }
}
