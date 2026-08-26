<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\JobApplication;
use App\Models\WorkshopRegistration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class ApplicationPrivacyService
{
    public function __construct(
        private AdminAuthorityService $authority,
        private AdminAuditService $audit,
        private PrivateFileCleanupService $cleanup,
    ) {
    }

    public function anonymizeJob(JobApplication $application, Admin $actor): JobApplication
    {
        $this->authority->assertOwner($actor);
        $cleanupIds = [];

        $result = DB::transaction(function () use ($application, $actor, &$cleanupIds): JobApplication {
            $this->assertLockedOwner($actor);
            $locked = $this->lockJob($application);
            abort_if($locked->anonymized_at !== null, 409, 'This application has already been anonymized.');
            $counts = $this->anonymizeJobChildren($locked, $cleanupIds);
            $this->replaceIdentity($locked, $actor);
            $this->audit->record($actor, 'recruitment.application.anonymized', $locked, context: $counts);

            return $locked->fresh();
        }, 3);

        $this->cleanup->processIdsAfterCommit($cleanupIds);

        return $result;
    }

    public function anonymizeWorkshop(WorkshopRegistration $registration, Admin $actor): WorkshopRegistration
    {
        $this->authority->assertOwner($actor);
        $cleanupIds = [];

        $result = DB::transaction(function () use ($registration, $actor, &$cleanupIds): WorkshopRegistration {
            $this->assertLockedOwner($actor);
            $locked = $this->lockWorkshop($registration);
            abort_if($locked->anonymized_at !== null, 409, 'This registration has already been anonymized.');
            $counts = $this->anonymizeWorkshopChildren($locked, $cleanupIds);
            $this->replaceIdentity($locked, $actor);
            $this->audit->record($actor, 'workshop.registration.anonymized', $locked, context: $counts);

            return $locked->fresh();
        }, 3);

        $this->cleanup->processIdsAfterCommit($cleanupIds);

        return $result;
    }

    public function deleteJob(JobApplication $application, Admin $actor): void
    {
        $this->authority->assertOwner($actor);
        $cleanupIds = [];

        DB::transaction(function () use ($application, $actor, &$cleanupIds): void {
            $this->assertLockedOwner($actor);
            $locked = $this->lockJob($application);
            $cleanupIds = $this->enqueueAndDeleteDocumentRecords($locked->documents()->lockForUpdate()->get());
            DB::table('job_application_scores')->where('job_application_id', $locked->id)->delete();
            DB::table('job_application_status_events')->where('job_application_id', $locked->id)->delete();
            DB::table('job_application_notes')->where('job_application_id', $locked->id)->delete();
            DB::table('job_application_answers')->where('job_application_id', $locked->id)->delete();
            $this->audit->record($actor, 'recruitment.application.deleted', $locked, context: [
                'reference_hash' => hash('sha256', (string) $locked->reference_number),
            ]);
            $locked->forceDelete();
        }, 3);

        $this->cleanup->processIdsAfterCommit($cleanupIds);
    }

    public function deleteWorkshop(WorkshopRegistration $registration, Admin $actor): void
    {
        $this->authority->assertOwner($actor);
        $cleanupIds = [];

        DB::transaction(function () use ($registration, $actor, &$cleanupIds): void {
            $this->assertLockedOwner($actor);
            $locked = $this->lockWorkshop($registration);
            $cleanupIds = $this->enqueueAndDeleteDocumentRecords($locked->documents()->lockForUpdate()->get());
            DB::table('workshop_registration_status_events')->where('workshop_registration_id', $locked->id)->delete();
            DB::table('workshop_registration_notes')->where('workshop_registration_id', $locked->id)->delete();
            DB::table('workshop_registration_answers')->where('workshop_registration_id', $locked->id)->delete();
            $this->audit->record($actor, 'workshop.registration.deleted', $locked, context: [
                'reference_hash' => hash('sha256', (string) $locked->reference_number),
            ]);
            $locked->forceDelete();
        }, 3);

        $this->cleanup->processIdsAfterCommit($cleanupIds);
    }

    private function lockJob(JobApplication $application): JobApplication
    {
        // Every mutation in this module locks the parent listing first. This
        // keeps lock ordering compatible with submissions and bulk workflows.
        DB::table('job_postings')->where('id', $application->job_posting_id)->lockForUpdate()->first();

        return JobApplication::withTrashed()->lockForUpdate()->findOrFail($application->id);
    }

    private function assertLockedOwner(Admin $actor): void
    {
        $lockedActor = Admin::query()->with('roleModel')->lockForUpdate()->findOrFail($actor->id);
        $this->authority->assertOwner($lockedActor);
    }

    private function lockWorkshop(WorkshopRegistration $registration): WorkshopRegistration
    {
        DB::table('workshops')->where('id', $registration->workshop_id)->lockForUpdate()->first();

        return WorkshopRegistration::withTrashed()->lockForUpdate()->findOrFail($registration->id);
    }

    /** @return array<string, int> */
    private function anonymizeJobChildren(JobApplication $application, array &$cleanupIds): array
    {
        $documents = $application->documents()->lockForUpdate()->get();
        $answerCount = DB::table('job_application_answers')->where('job_application_id', $application->id)->count();
        $noteCount = DB::table('job_application_notes')->where('job_application_id', $application->id)->count();
        $scoreCommentCount = DB::table('job_application_scores')
            ->where('job_application_id', $application->id)
            ->whereNotNull('comment')
            ->count();

        $cleanupIds = $this->enqueueAndDeleteDocumentRecords($documents);
        DB::table('job_application_answers')->where('job_application_id', $application->id)->delete();
        DB::table('job_application_notes')->where('job_application_id', $application->id)->update([
            'body' => '[removed during applicant anonymization]',
            'updated_at' => now(),
        ]);
        DB::table('job_application_scores')->where('job_application_id', $application->id)->update([
            'comment' => null,
            'updated_at' => now(),
        ]);

        return [
            'documents_removed' => $documents->count(),
            'answers_removed' => $answerCount,
            'notes_redacted' => $noteCount,
            'score_comments_redacted' => $scoreCommentCount,
        ];
    }

    /** @return array<string, int> */
    private function anonymizeWorkshopChildren(WorkshopRegistration $registration, array &$cleanupIds): array
    {
        $documents = $registration->documents()->lockForUpdate()->get();
        $answerCount = DB::table('workshop_registration_answers')->where('workshop_registration_id', $registration->id)->count();
        $noteCount = DB::table('workshop_registration_notes')->where('workshop_registration_id', $registration->id)->count();

        $cleanupIds = $this->enqueueAndDeleteDocumentRecords($documents);
        DB::table('workshop_registration_answers')->where('workshop_registration_id', $registration->id)->delete();
        DB::table('workshop_registration_notes')->where('workshop_registration_id', $registration->id)->update([
            'body' => '[removed during applicant anonymization]',
            'updated_at' => now(),
        ]);

        return [
            'documents_removed' => $documents->count(),
            'answers_removed' => $answerCount,
            'notes_redacted' => $noteCount,
        ];
    }

    private function replaceIdentity(JobApplication|WorkshopRegistration $record, Admin $actor): void
    {
        $record->forceFill([
            'name' => 'Anonymized applicant',
            'email' => 'anonymized-' . str_replace('-', '', (string) $record->uuid) . '@example.test',
            'phone' => null,
            'anonymized_at' => now(),
            'anonymized_by_admin_id' => $actor->id,
        ])->save();
    }

    /** @param iterable<int, Model> $records */
    /** @return list<int> */
    private function enqueueAndDeleteDocumentRecords(iterable $records): array
    {
        $records = collect($records);
        $cleanupIds = $this->cleanup->enqueueDocuments($records);
        foreach ($records as $document) {
            DB::table($document->getTable())->where('id', $document->getKey())->delete();
        }

        return $cleanupIds;
    }
}
