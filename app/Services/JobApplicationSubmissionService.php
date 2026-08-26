<?php

namespace App\Services;

use App\Models\ApplicationFormField;
use App\Models\ApplicationFormVersion;
use App\Models\JobApplication;
use App\Models\JobApplicationDocument;
use App\Models\JobApplicationStatusEvent;
use App\Models\JobPosting;
use App\Support\ApplicationIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class JobApplicationSubmissionService
{
    public function __construct(
        private ApplicationFormSubmissionValidator $validator,
        private PrivateApplicationDocumentService $documents,
        private PrivateFileCleanupService $cleanup,
        private AdminAuditService $audit,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function submit(JobPosting $posting, array $input, string $locale = 'en'): JobApplication
    {
        $snapshot = JobPosting::query()
            ->with('currentFormVersion')
            ->find($posting->getKey());
        $this->assertOpen($snapshot, now(), $locale);

        $version = $snapshot?->currentFormVersion;
        if (!$version instanceof ApplicationFormVersion) {
            throw $this->unavailable($locale);
        }

        $validated = $this->validator->validate($version, $input, $locale, true);
        $staged = $this->stageFiles($validated);
        $cleanupIds = [];

        try {
            $application = DB::transaction(function () use (
                $posting,
                $version,
                $validated,
                $staged,
                $locale,
                &$cleanupIds,
            ): JobApplication {
                $lockedPosting = JobPosting::query()
                    ->whereKey($posting->getKey())
                    ->lockForUpdate()
                    ->first();
                $now = now();
                $this->assertOpen($lockedPosting, $now, $locale);

                if ((int) $lockedPosting->current_form_version_id !== (int) $version->getKey()) {
                    throw ValidationException::withMessages([
                        'submission' => $this->message(
                            $locale,
                            'The form changed before your submission was saved. Reload the page and try again.',
                            'আপনার আবেদন সংরক্ষণের আগে ফর্মটি পরিবর্তিত হয়েছে। পৃষ্ঠাটি রিলোড করে আবার চেষ্টা করুন।',
                        ),
                    ]);
                }

                $emailHash = ApplicationIdentity::emailHash($validated->email);
                $application = JobApplication::withTrashed()
                    ->where('job_posting_id', $lockedPosting->getKey())
                    ->where('email_hash', $emailHash)
                    ->lockForUpdate()
                    ->first();

                if ($application?->trashed()) {
                    throw $this->unavailable($locale);
                }

                $isDuplicate = $application !== null;
                if ($application) {
                    $application->fill([
                        'application_form_version_id' => $version->getKey(),
                        'name' => $validated->name,
                        'email' => $validated->email,
                        'phone' => $validated->phone,
                        'submission_count' => (int) $application->submission_count + 1,
                        'last_submitted_at' => $now,
                        'source' => JobApplication::SOURCE_PUBLIC,
                        'last_import_batch_id' => null,
                    ])->save();
                } else {
                    $application = JobApplication::create([
                        'job_posting_id' => $lockedPosting->getKey(),
                        'application_form_version_id' => $version->getKey(),
                        'name' => $validated->name,
                        'email' => $validated->email,
                        'phone' => $validated->phone,
                        'workflow_status' => JobApplication::STATUS_NEW,
                        'submission_count' => 1,
                        'first_submitted_at' => $now,
                        'last_submitted_at' => $now,
                        'source' => JobApplication::SOURCE_PUBLIC,
                        'status_changed_at' => $now,
                    ]);
                    $application->statusEvents()->create([
                        'from_status' => null,
                        'to_status' => JobApplication::STATUS_NEW,
                        'source' => JobApplicationStatusEvent::SOURCE_SYSTEM,
                        'created_at' => $now,
                    ]);
                }

                $oldDocuments = $application->documents()->get(['disk', 'path']);
                $cleanupIds = $this->cleanup->enqueueDocuments($oldDocuments);
                $application->answers()->delete();
                $application->documents()->delete();
                if ($validated->answers !== []) {
                    $application->answers()->createMany($validated->answers);
                }
                $this->persistDocuments($application, $version, $staged);

                $this->audit->record(
                    null,
                    $isDuplicate ? 'job_application.resubmitted' : 'job_application.submitted',
                    $application,
                    context: [
                        'job_posting_id' => (int) $lockedPosting->getKey(),
                        'submission_count' => (int) $application->submission_count,
                        'answer_count' => count($validated->answers),
                        'document_count' => count($staged),
                    ],
                );

                return $application;
            }, 3);
        } catch (Throwable $exception) {
            $this->discardStaged($staged);
            throw $exception;
        }

        $this->cleanup->processIdsAfterCommit($cleanupIds);

        return $application->fresh(['answers', 'documents', 'statusEvents']);
    }

    /** @return array<int, StagedPrivateDocument> */
    private function stageFiles(ValidatedApplicationSubmission $submission): array
    {
        $staged = [];
        try {
            foreach ($submission->files as $fieldId => $file) {
                $staged[(int) $fieldId] = $this->documents->stagePdf($file);
            }
        } catch (Throwable $exception) {
            $this->discardStaged($staged);
            throw $exception;
        }

        return $staged;
    }

    /** @param array<int, StagedPrivateDocument> $staged */
    private function persistDocuments(
        JobApplication $application,
        ApplicationFormVersion $version,
        array $staged,
    ): void {
        $fields = $version->fields->keyBy('id');
        foreach ($staged as $fieldId => $document) {
            $model = new JobApplicationDocument([
                'application_form_field_id' => $fieldId,
                'document_kind' => $fields->get($fieldId)?->system_key === ApplicationFormField::SYSTEM_CV
                    ? JobApplicationDocument::KIND_CV
                    : JobApplicationDocument::KIND_ATTACHMENT,
                'disk' => $document->disk,
                'path' => $document->path,
                'original_name' => $document->originalName,
                'mime_type' => $document->mimeType,
                'bytes' => $document->bytes,
                'sha256' => $document->sha256,
            ]);
            $model->uuid = $document->uuid;
            $application->documents()->save($model);
        }
    }

    /** @param array<int, StagedPrivateDocument> $staged */
    private function discardStaged(array $staged): void
    {
        foreach ($staged as $document) {
            try {
                $this->documents->discard($document);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function assertOpen(?JobPosting $posting, mixed $at, string $locale): void
    {
        if (!$posting
            || $posting->publication_status !== JobPosting::PUBLICATION_PUBLISHED
            || !$posting->visible_from_at
            || $posting->visible_from_at->isAfter($at)
            || $posting->application_opens_at->isAfter($at)
            || $posting->application_closes_at->lessThanOrEqualTo($at)) {
            throw $this->unavailable($locale);
        }
    }

    private function unavailable(string $locale): ValidationException
    {
        return ValidationException::withMessages([
            'submission' => $this->message(
                $locale,
                'This job is not accepting applications.',
                'এই চাকরির জন্য এখন আবেদন গ্রহণ করা হচ্ছে না।',
            ),
        ]);
    }

    private function message(string $locale, string $english, string $bangla): string
    {
        return $locale === 'bn' ? $bangla : $english;
    }
}
