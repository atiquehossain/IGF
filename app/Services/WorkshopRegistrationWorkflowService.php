<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Models\WorkshopRegistrationNote;
use App\Models\WorkshopRegistrationStatusEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WorkshopRegistrationWorkflowService
{
    public const MAX_BULK_RECORDS = 100;

    public const TRANSITIONS = [
        WorkshopRegistration::STATUS_PENDING => [
            WorkshopRegistration::STATUS_CONFIRMED,
            WorkshopRegistration::STATUS_WAITLISTED,
            WorkshopRegistration::STATUS_REJECTED,
            WorkshopRegistration::STATUS_CANCELLED,
        ],
        WorkshopRegistration::STATUS_CONFIRMED => [WorkshopRegistration::STATUS_CANCELLED],
        WorkshopRegistration::STATUS_WAITLISTED => [
            WorkshopRegistration::STATUS_CONFIRMED,
            WorkshopRegistration::STATUS_REJECTED,
            WorkshopRegistration::STATUS_CANCELLED,
        ],
        WorkshopRegistration::STATUS_REJECTED => [
            WorkshopRegistration::STATUS_PENDING,
            WorkshopRegistration::STATUS_WAITLISTED,
        ],
        WorkshopRegistration::STATUS_CANCELLED => [
            WorkshopRegistration::STATUS_PENDING,
            WorkshopRegistration::STATUS_WAITLISTED,
        ],
    ];

    public function __construct(private AdminAuditService $audit)
    {
    }

    public function transition(WorkshopRegistration $registration, string $toStatus, Admin $actor): WorkshopRegistration
    {
        $this->assertActor($actor);

        return DB::transaction(function () use ($registration, $toStatus, $actor): WorkshopRegistration {
            [$workshop, $locked] = $this->lockRegistration($registration);
            $this->applyTransition($workshop, $locked, $toStatus, $actor);

            return $locked->fresh(['statusEvents', 'assignedAdmin']);
        }, 3);
    }

    public function assign(WorkshopRegistration $registration, ?Admin $assignee, Admin $actor): WorkshopRegistration
    {
        $this->assertActor($actor);
        $this->assertAssignee($assignee);

        return DB::transaction(function () use ($registration, $assignee, $actor): WorkshopRegistration {
            [$workshop, $locked] = $this->lockRegistration($registration);
            $before = $locked->assigned_to_admin_id;
            $after = $assignee?->getKey();
            if ((string) $before === (string) $after) {
                return $locked;
            }

            $locked->update(['assigned_to_admin_id' => $after]);
            $this->audit->record(
                $actor,
                'workshop_registration.assigned',
                $locked,
                changes: ['assigned_to_admin_id' => ['from' => $before, 'to' => $after]],
                context: ['workshop_id' => (int) $workshop->getKey()],
            );

            return $locked->fresh(['assignedAdmin']);
        }, 3);
    }

    public function addNote(WorkshopRegistration $registration, string $body, Admin $actor): WorkshopRegistrationNote
    {
        $this->assertActor($actor);
        $body = $this->validatedNote($body);

        return DB::transaction(function () use ($registration, $body, $actor): WorkshopRegistrationNote {
            [$workshop, $locked] = $this->lockRegistration($registration);
            $note = $locked->notes()->create([
                'author_admin_id' => $actor->getKey(),
                'author_name_snapshot' => $this->actorSnapshot($actor),
                'body' => $body,
            ]);
            $this->audit->record(
                $actor,
                'workshop_registration.note_added',
                $locked,
                context: [
                    'workshop_id' => (int) $workshop->getKey(),
                    'note_id' => (int) $note->getKey(),
                ],
            );

            return $note;
        }, 3);
    }

    /** @param iterable<int, int|string> $registrationIds
     *  @return Collection<int, WorkshopRegistration>
     */
    public function bulkTransition(iterable $registrationIds, string $toStatus, Admin $actor): Collection
    {
        $this->assertActor($actor);
        $ids = $this->normalizeIds($registrationIds);
        $workshopIds = $this->workshopIdsFor($ids);

        return DB::transaction(function () use ($ids, $workshopIds, $toStatus, $actor): Collection {
            $workshops = $this->lockWorkshops($workshopIds)->keyBy('id');
            $registrations = WorkshopRegistration::query()
                ->whereKey($ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $this->assertCompleteSet($registrations, $ids);

            foreach ($registrations as $registration) {
                $this->assertTransitionAllowed($registration->workflow_status, $toStatus);
            }
            foreach ($registrations as $registration) {
                $workshop = $workshops->get($registration->workshop_id);
                $this->applyTransitionWithoutPrimaryAudit(
                    $workshop,
                    $registration,
                    $toStatus,
                    $actor,
                    $ids,
                );
            }

            $this->audit->record(
                $actor,
                'workshop_registration.bulk_status_changed',
                'workshop-registration-bulk',
                context: [
                    'record_count' => count($ids),
                    'listing_count' => count($workshopIds),
                    'to_status' => $toStatus,
                ],
            );

            return WorkshopRegistration::query()->whereKey($ids)->orderBy('id')->get();
        }, 3);
    }

    /** @param iterable<int, int|string> $registrationIds
     *  @return Collection<int, WorkshopRegistration>
     */
    public function bulkAssign(iterable $registrationIds, ?Admin $assignee, Admin $actor): Collection
    {
        $this->assertActor($actor);
        $this->assertAssignee($assignee);
        $ids = $this->normalizeIds($registrationIds);
        $workshopIds = $this->workshopIdsFor($ids);

        return DB::transaction(function () use ($ids, $workshopIds, $assignee, $actor): Collection {
            $this->lockWorkshops($workshopIds);
            $registrations = WorkshopRegistration::query()
                ->whereKey($ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $this->assertCompleteSet($registrations, $ids);
            $assigneeId = $assignee?->getKey();
            foreach ($registrations as $registration) {
                $registration->update(['assigned_to_admin_id' => $assigneeId]);
            }

            $this->audit->record(
                $actor,
                'workshop_registration.bulk_assigned',
                'workshop-registration-bulk',
                context: [
                    'record_count' => count($ids),
                    'listing_count' => count($workshopIds),
                    'assigned_to_admin_id' => $assigneeId,
                ],
            );

            return WorkshopRegistration::query()->whereKey($ids)->orderBy('id')->get();
        }, 3);
    }

    /** @return array{0: Workshop, 1: WorkshopRegistration} */
    private function lockRegistration(WorkshopRegistration $registration): array
    {
        $id = $registration->getKey();
        $workshopId = $registration->workshop_id
            ?: WorkshopRegistration::query()->whereKey($id)->value('workshop_id');
        $workshop = Workshop::query()->whereKey($workshopId)->lockForUpdate()->firstOrFail();
        $locked = WorkshopRegistration::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        if ((int) $locked->workshop_id !== (int) $workshop->getKey()) {
            throw ValidationException::withMessages(['registration' => 'The registration changed. Reload and try again.']);
        }

        return [$workshop, $locked];
    }

    private function applyTransition(
        Workshop $workshop,
        WorkshopRegistration $registration,
        string $toStatus,
        Admin $actor,
    ): void {
        $fromStatus = $registration->workflow_status;
        $this->applyTransitionWithoutPrimaryAudit($workshop, $registration, $toStatus, $actor);
        $this->audit->record(
            $actor,
            'workshop_registration.status_changed',
            $registration,
            changes: ['workflow_status' => ['from' => $fromStatus, 'to' => $toStatus]],
            context: ['workshop_id' => (int) $workshop->getKey()],
        );
    }

    /** @param list<int> $promotionExclusions */
    private function applyTransitionWithoutPrimaryAudit(
        Workshop $workshop,
        WorkshopRegistration $registration,
        string $toStatus,
        Admin $actor,
        array $promotionExclusions = [],
    ): void {
        $fromStatus = $registration->workflow_status;
        $this->assertTransitionAllowed($fromStatus, $toStatus);
        if ($toStatus === WorkshopRegistration::STATUS_CONFIRMED && !$this->hasAvailableCapacity($workshop)) {
            throw ValidationException::withMessages(['workflow_status' => 'The workshop has no available seats.']);
        }

        $now = now();
        $attributes = [
            'workflow_status' => $toStatus,
            'status_changed_at' => $now,
            'status_changed_by_admin_id' => $actor->getKey(),
        ];
        if ($toStatus === WorkshopRegistration::STATUS_CONFIRMED) {
            $attributes['confirmed_at'] = $now;
        }
        if ($toStatus === WorkshopRegistration::STATUS_WAITLISTED) {
            $attributes['waitlisted_at'] = $now;
        }
        if ($toStatus === WorkshopRegistration::STATUS_CANCELLED) {
            $attributes['cancelled_at'] = $now;
        } elseif ($fromStatus === WorkshopRegistration::STATUS_CANCELLED) {
            $attributes['cancelled_at'] = null;
        }

        $registration->update($attributes);
        $registration->statusEvents()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_admin_id' => $actor->getKey(),
            'actor_name_snapshot' => $this->actorSnapshot($actor),
            'source' => WorkshopRegistrationStatusEvent::SOURCE_ADMIN,
            'created_at' => $now,
        ]);

        if ($fromStatus === WorkshopRegistration::STATUS_CONFIRMED
            && $toStatus === WorkshopRegistration::STATUS_CANCELLED) {
            $this->promoteOldestWaitlisted($workshop, $registration, $actor, $promotionExclusions);
        }
    }

    /** @param list<int> $exclusions */
    private function promoteOldestWaitlisted(
        Workshop $workshop,
        WorkshopRegistration $trigger,
        Admin $actor,
        array $exclusions,
    ): void {
        $now = now();
        if (!$this->isEligibleForAutomaticWaitlistPromotion($workshop, $now)
            || !$this->hasAvailableCapacity($workshop)) {
            return;
        }

        $promoted = WorkshopRegistration::query()
            ->where('workshop_id', $workshop->getKey())
            ->where('workflow_status', WorkshopRegistration::STATUS_WAITLISTED)
            ->when($exclusions !== [], fn ($query) => $query->whereNotIn('id', $exclusions))
            ->orderBy('waitlisted_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
        if (!$promoted) {
            return;
        }

        $promoted->update([
            'workflow_status' => WorkshopRegistration::STATUS_CONFIRMED,
            'confirmed_at' => $now,
            'status_changed_at' => $now,
            'status_changed_by_admin_id' => null,
        ]);
        $promoted->statusEvents()->create([
            'from_status' => WorkshopRegistration::STATUS_WAITLISTED,
            'to_status' => WorkshopRegistration::STATUS_CONFIRMED,
            'source' => WorkshopRegistrationStatusEvent::SOURCE_SYSTEM,
            'created_at' => $now,
        ]);
        $this->audit->record(
            $actor,
            'workshop_registration.waitlist_promoted',
            $promoted,
            context: [
                'workshop_id' => (int) $workshop->getKey(),
                'trigger_registration_id' => (int) $trigger->getKey(),
            ],
        );
    }

    private function isEligibleForAutomaticWaitlistPromotion(Workshop $workshop, CarbonInterface $at): bool
    {
        return $workshop->registration_mode === Workshop::REGISTRATION_WAITLIST
            && $workshop->publication_status === Workshop::PUBLICATION_PUBLISHED
            && $workshop->visible_from_at !== null
            && $workshop->visible_from_at->lessThanOrEqualTo($at)
            && $workshop->registration_opens_at->lessThanOrEqualTo($at)
            && $workshop->registration_closes_at->isAfter($at)
            && $workshop->starts_at->isAfter($at);
    }

    private function hasAvailableCapacity(Workshop $workshop): bool
    {
        if ($workshop->capacity === null) {
            return true;
        }

        return WorkshopRegistration::query()
            ->where('workshop_id', $workshop->getKey())
            ->where('workflow_status', WorkshopRegistration::STATUS_CONFIRMED)
            ->count() < (int) $workshop->capacity;
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
                'registrations' => 'Select between 1 and ' . self::MAX_BULK_RECORDS . ' registrations.',
            ]);
        }

        return $normalized;
    }

    /** @param list<int> $registrationIds
     *  @return list<int>
     */
    private function workshopIdsFor(array $registrationIds): array
    {
        $rows = WorkshopRegistration::query()->whereKey($registrationIds)->get(['id', 'workshop_id']);
        $this->assertCompleteSet($rows, $registrationIds);

        return $rows->pluck('workshop_id')->map(fn (mixed $id): int => (int) $id)->unique()->sort()->values()->all();
    }

    /** @param list<int> $workshopIds
     *  @return Collection<int, Workshop>
     */
    private function lockWorkshops(array $workshopIds): Collection
    {
        $locked = Workshop::query()->whereKey($workshopIds)->orderBy('id')->lockForUpdate()->get();
        if ($locked->count() !== count($workshopIds)) {
            throw ValidationException::withMessages(['registrations' => 'One or more workshops are unavailable.']);
        }

        return $locked;
    }

    /** @param Collection<int, mixed> $models
     *  @param list<int> $ids
     */
    private function assertCompleteSet(Collection $models, array $ids): void
    {
        if ($models->count() !== count($ids)) {
            throw ValidationException::withMessages(['registrations' => 'One or more registrations are unavailable.']);
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
