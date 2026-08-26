<?php

namespace App\Services;

use App\Models\ApplicationFormField;
use App\Models\ApplicationFormVersion;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Models\WorkshopRegistrationDocument;
use App\Models\WorkshopRegistrationStatusEvent;
use App\Support\ApplicationIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class WorkshopRegistrationService
{
    public function __construct(
        private ApplicationFormSubmissionValidator $validator,
        private PrivateApplicationDocumentService $documents,
        private PrivateFileCleanupService $cleanup,
        private AdminAuditService $audit,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function submit(Workshop $workshop, array $input, string $locale = 'en'): WorkshopRegistration
    {
        $snapshot = Workshop::query()
            ->with('currentFormVersion')
            ->find($workshop->getKey());
        $this->assertOpen($snapshot, now(), $locale);

        $version = $snapshot?->currentFormVersion;
        if (!$version instanceof ApplicationFormVersion) {
            throw $this->unavailable($locale);
        }

        $validated = $this->validator->validate($version, $input, $locale, false);
        $staged = $this->stageFiles($validated);
        $cleanupIds = [];

        try {
            $registration = DB::transaction(function () use (
                $workshop,
                $version,
                $validated,
                $staged,
                $locale,
                &$cleanupIds,
            ): WorkshopRegistration {
                $lockedWorkshop = Workshop::query()
                    ->whereKey($workshop->getKey())
                    ->lockForUpdate()
                    ->first();
                $now = now();
                $this->assertOpen($lockedWorkshop, $now, $locale);

                if ((int) $lockedWorkshop->current_form_version_id !== (int) $version->getKey()) {
                    throw ValidationException::withMessages([
                        'submission' => $this->message(
                            $locale,
                            'The form changed before your registration was saved. Reload the page and try again.',
                            'আপনার নিবন্ধন সংরক্ষণের আগে ফর্মটি পরিবর্তিত হয়েছে। পৃষ্ঠাটি রিলোড করে আবার চেষ্টা করুন।',
                        ),
                    ]);
                }

                $emailHash = ApplicationIdentity::emailHash($validated->email);
                $registration = WorkshopRegistration::withTrashed()
                    ->where('workshop_id', $lockedWorkshop->getKey())
                    ->where('email_hash', $emailHash)
                    ->lockForUpdate()
                    ->first();

                if ($registration?->trashed()) {
                    throw $this->unavailable($locale);
                }

                $isDuplicate = $registration !== null;
                if ($registration) {
                    $registration->fill([
                        'application_form_version_id' => $version->getKey(),
                        'name' => $validated->name,
                        'email' => $validated->email,
                        'phone' => $validated->phone,
                        'submission_count' => (int) $registration->submission_count + 1,
                        'last_submitted_at' => $now,
                        'source' => WorkshopRegistration::SOURCE_PUBLIC,
                        'last_import_batch_id' => null,
                    ])->save();
                } else {
                    $status = $this->initialStatus($lockedWorkshop, $locale);
                    $registration = WorkshopRegistration::create([
                        'workshop_id' => $lockedWorkshop->getKey(),
                        'application_form_version_id' => $version->getKey(),
                        'name' => $validated->name,
                        'email' => $validated->email,
                        'phone' => $validated->phone,
                        'workflow_status' => $status,
                        'submission_count' => 1,
                        'first_submitted_at' => $now,
                        'last_submitted_at' => $now,
                        'waitlisted_at' => $status === WorkshopRegistration::STATUS_WAITLISTED ? $now : null,
                        'confirmed_at' => $status === WorkshopRegistration::STATUS_CONFIRMED ? $now : null,
                        'source' => WorkshopRegistration::SOURCE_PUBLIC,
                        'status_changed_at' => $now,
                    ]);
                    $registration->statusEvents()->create([
                        'from_status' => null,
                        'to_status' => $status,
                        'source' => WorkshopRegistrationStatusEvent::SOURCE_SYSTEM,
                        'created_at' => $now,
                    ]);
                }

                $oldDocuments = $registration->documents()->get(['disk', 'path']);
                $cleanupIds = $this->cleanup->enqueueDocuments($oldDocuments);
                $registration->answers()->delete();
                $registration->documents()->delete();
                if ($validated->answers !== []) {
                    $registration->answers()->createMany($validated->answers);
                }
                $this->persistDocuments($registration, $version, $staged);

                $this->audit->record(
                    null,
                    $isDuplicate ? 'workshop_registration.resubmitted' : 'workshop_registration.submitted',
                    $registration,
                    context: [
                        'workshop_id' => (int) $lockedWorkshop->getKey(),
                        'workflow_status' => $registration->workflow_status,
                        'submission_count' => (int) $registration->submission_count,
                        'answer_count' => count($validated->answers),
                        'document_count' => count($staged),
                    ],
                );

                return $registration;
            }, 3);
        } catch (Throwable $exception) {
            $this->discardStaged($staged);
            throw $exception;
        }

        $this->cleanup->processIdsAfterCommit($cleanupIds);

        return $registration->fresh(['answers', 'documents', 'statusEvents']);
    }

    private function initialStatus(Workshop $workshop, string $locale): string
    {
        if ($workshop->registration_mode === Workshop::REGISTRATION_MANUAL) {
            return WorkshopRegistration::STATUS_PENDING;
        }
        if ($this->hasAvailableCapacity($workshop)) {
            return WorkshopRegistration::STATUS_CONFIRMED;
        }
        if ($workshop->registration_mode === Workshop::REGISTRATION_WAITLIST) {
            return WorkshopRegistration::STATUS_WAITLISTED;
        }

        throw ValidationException::withMessages([
            'submission' => $this->message(
                $locale,
                'Registration is full.',
                'নিবন্ধনের আসন পূর্ণ হয়ে গেছে।',
            ),
        ]);
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
        WorkshopRegistration $registration,
        ApplicationFormVersion $version,
        array $staged,
    ): void {
        $fields = $version->fields->keyBy('id');
        foreach ($staged as $fieldId => $document) {
            $model = new WorkshopRegistrationDocument([
                'application_form_field_id' => $fieldId,
                'document_kind' => $fields->get($fieldId)?->system_key === ApplicationFormField::SYSTEM_CV
                    ? WorkshopRegistrationDocument::KIND_CV
                    : WorkshopRegistrationDocument::KIND_ATTACHMENT,
                'disk' => $document->disk,
                'path' => $document->path,
                'original_name' => $document->originalName,
                'mime_type' => $document->mimeType,
                'bytes' => $document->bytes,
                'sha256' => $document->sha256,
            ]);
            $model->uuid = $document->uuid;
            $registration->documents()->save($model);
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

    private function assertOpen(?Workshop $workshop, mixed $at, string $locale): void
    {
        if (!$workshop
            || $workshop->publication_status !== Workshop::PUBLICATION_PUBLISHED
            || !$workshop->visible_from_at
            || $workshop->visible_from_at->isAfter($at)
            || $workshop->registration_opens_at->isAfter($at)
            || $workshop->registration_closes_at->lessThanOrEqualTo($at)) {
            throw $this->unavailable($locale);
        }
    }

    private function unavailable(string $locale): ValidationException
    {
        return ValidationException::withMessages([
            'submission' => $this->message(
                $locale,
                'This workshop is not accepting registrations.',
                'এই কর্মশালার জন্য এখন নিবন্ধন গ্রহণ করা হচ্ছে না।',
            ),
        ]);
    }

    private function message(string $locale, string $english, string $bangla): string
    {
        return $locale === 'bn' ? $bangla : $english;
    }
}
