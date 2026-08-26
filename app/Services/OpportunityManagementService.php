<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\JobPosting;
use App\Models\JobScorecardCriterion;
use App\Models\Workshop;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OpportunityManagementService
{
    public function __construct(
        private ApplicationFormSchemaService $forms,
        private ContentSanitizer $sanitizer,
        private AdminAuditService $audit,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function createJob(array $data, Admin $actor): JobPosting
    {
        $normalized = $this->normalizeJob($data);

        return DB::transaction(function () use ($normalized, $actor): JobPosting {
            [$form, $version] = $this->resolveForm(ApplicationForm::PURPOSE_JOB, $normalized, $actor);
            $job = JobPosting::query()->create($normalized['listing'] + [
                'application_form_id' => $form->id,
                'current_form_version_id' => $version->id,
                'publication_status' => JobPosting::PUBLICATION_DRAFT,
                'editor_version' => 1,
                'created_by_admin_id' => $actor->id,
                'updated_by_admin_id' => $actor->id,
            ]);
            $this->persistTranslations($job, $normalized['translations']);
            $this->syncScorecardCriteria($job, $normalized['scorecard_criteria']);
            $this->audit->record($actor, 'recruitment.job.created', $job, context: ['editor_version' => 1]);

            return $job->fresh(['translations', 'currentFormVersion', 'form', 'scorecardCriteria']);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function updateJob(JobPosting $job, int $expectedVersion, array $data, Admin $actor): JobPosting
    {
        $normalized = $this->normalizeJob($data);

        return DB::transaction(function () use ($job, $expectedVersion, $normalized, $actor): JobPosting {
            $locked = JobPosting::query()->lockForUpdate()->findOrFail($job->id);
            $this->assertEditorVersion($locked, $expectedVersion);
            [$form, $version] = $this->resolveForm(
                ApplicationForm::PURPOSE_JOB,
                $normalized,
                $actor,
                $locked->form,
                $locked->currentFormVersion,
            );
            $locked->update($normalized['listing'] + [
                'application_form_id' => $form->id,
                'current_form_version_id' => $version->id,
                'editor_version' => $expectedVersion + 1,
                'updated_by_admin_id' => $actor->id,
            ]);
            $this->persistTranslations($locked, $normalized['translations']);
            if ($normalized['scorecard_criteria'] !== null) {
                $this->syncScorecardCriteria($locked, $normalized['scorecard_criteria']);
            }
            $this->audit->record($actor, 'recruitment.job.updated', $locked, changes: [
                'editor_version' => ['from' => $expectedVersion, 'to' => $expectedVersion + 1],
            ]);

            return $locked->fresh(['translations', 'currentFormVersion', 'form', 'scorecardCriteria']);
        }, 3);
    }

    public function publishJob(JobPosting $job, int $expectedVersion, Admin $actor): JobPosting
    {
        return DB::transaction(function () use ($job, $expectedVersion, $actor): JobPosting {
            $locked = JobPosting::query()->with(['translations', 'currentFormVersion'])->lockForUpdate()->findOrFail($job->id);
            $this->assertEditorVersion($locked, $expectedVersion);
            $this->assertPublishable($locked, ApplicationForm::PURPOSE_JOB);
            $locked->update([
                'publication_status' => JobPosting::PUBLICATION_PUBLISHED,
                'visible_from_at' => $locked->visible_from_at ?: now(),
                'published_by_admin_id' => $actor->id,
                'updated_by_admin_id' => $actor->id,
                'editor_version' => $expectedVersion + 1,
            ]);
            $this->audit->record($actor, 'recruitment.job.published', $locked, context: ['editor_version' => $expectedVersion + 1]);

            return $locked->fresh(['translations', 'currentFormVersion']);
        }, 3);
    }

    public function closeJob(JobPosting $job, Admin $actor): JobPosting
    {
        return DB::transaction(function () use ($job, $actor): JobPosting {
            $locked = JobPosting::query()->lockForUpdate()->findOrFail($job->id);
            abort_unless($locked->publication_status === JobPosting::PUBLICATION_PUBLISHED, 409, 'Only a published job can be closed.');
            if ($locked->application_closes_at->isFuture()) {
                $locked->application_closes_at = now();
            }
            $locked->editor_version++;
            $locked->updated_by_admin_id = $actor->id;
            $locked->save();
            $this->audit->record($actor, 'recruitment.job.closed', $locked);

            return $locked->fresh();
        }, 3);
    }

    public function withdrawJob(JobPosting $job, Admin $actor): JobPosting
    {
        return $this->withdraw($job, $actor, 'recruitment.job.withdrawn');
    }

    public function duplicateJob(JobPosting $job, Admin $actor): JobPosting
    {
        return DB::transaction(function () use ($job, $actor): JobPosting {
            $source = JobPosting::query()->with(['translations', 'form.versions'])->lockForUpdate()->findOrFail($job->id);
            $form = $this->forms->duplicate($source->form, 'Copy of ' . $source->form->name, $actor);
            $version = $this->forms->publish($form, (int) $form->fresh()->editor_version, $actor);
            $copy = $source->replicate([
                'uuid', 'publication_status', 'visible_from_at', 'editor_version',
                'created_by_admin_id', 'updated_by_admin_id', 'published_by_admin_id',
                'deleted_at',
            ]);
            $copy->forceFill([
                'application_form_id' => $form->id,
                'current_form_version_id' => $version->id,
                'publication_status' => JobPosting::PUBLICATION_DRAFT,
                'visible_from_at' => null,
                'editor_version' => 1,
                'created_by_admin_id' => $actor->id,
                'updated_by_admin_id' => $actor->id,
                'published_by_admin_id' => null,
            ])->save();
            $this->copyTranslations($source, $copy);
            foreach ($source->scorecardCriteria()->where('is_enabled', true)->get() as $position => $criterion) {
                $copy->scorecardCriteria()->create([
                    'label' => $criterion->label,
                    'description' => $criterion->description,
                    'maximum_score' => $criterion->maximum_score,
                    'position' => $position + 1,
                    'is_enabled' => true,
                ]);
            }
            $this->audit->record($actor, 'recruitment.job.duplicated', $copy, context: ['source_uuid' => $source->uuid]);

            return $copy->fresh(['translations', 'currentFormVersion', 'form', 'scorecardCriteria']);
        }, 3);
    }

    public function deleteJobDraft(JobPosting $job, Admin $actor): void
    {
        DB::transaction(function () use ($job, $actor): void {
            $locked = JobPosting::query()->lockForUpdate()->findOrFail($job->id);
            abort_unless(
                $locked->publication_status === JobPosting::PUBLICATION_DRAFT && !$locked->applications()->exists(),
                409,
                'Published jobs and jobs with applications must be closed or withdrawn, not deleted.',
            );
            $this->audit->record($actor, 'recruitment.job.deleted', $locked);
            $locked->delete();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function createWorkshop(array $data, Admin $actor): Workshop
    {
        $normalized = $this->normalizeWorkshop($data);

        return DB::transaction(function () use ($normalized, $actor): Workshop {
            [$form, $version] = $this->resolveForm(ApplicationForm::PURPOSE_WORKSHOP, $normalized, $actor);
            $workshop = Workshop::query()->create($normalized['listing'] + [
                'application_form_id' => $form->id,
                'current_form_version_id' => $version->id,
                'publication_status' => Workshop::PUBLICATION_DRAFT,
                'editor_version' => 1,
                'created_by_admin_id' => $actor->id,
                'updated_by_admin_id' => $actor->id,
            ]);
            $this->persistTranslations($workshop, $normalized['translations']);
            $this->audit->record($actor, 'workshop.created', $workshop, context: ['editor_version' => 1, 'always_free' => true]);

            return $workshop->fresh(['translations', 'currentFormVersion', 'form']);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function updateWorkshop(Workshop $workshop, int $expectedVersion, array $data, Admin $actor): Workshop
    {
        $normalized = $this->normalizeWorkshop($data);

        return DB::transaction(function () use ($workshop, $expectedVersion, $normalized, $actor): Workshop {
            $locked = Workshop::query()->lockForUpdate()->findOrFail($workshop->id);
            $this->assertEditorVersion($locked, $expectedVersion);
            [$form, $version] = $this->resolveForm(
                ApplicationForm::PURPOSE_WORKSHOP,
                $normalized,
                $actor,
                $locked->form,
                $locked->currentFormVersion,
            );
            $locked->update($normalized['listing'] + [
                'application_form_id' => $form->id,
                'current_form_version_id' => $version->id,
                'editor_version' => $expectedVersion + 1,
                'updated_by_admin_id' => $actor->id,
            ]);
            $this->persistTranslations($locked, $normalized['translations']);
            $this->audit->record($actor, 'workshop.updated', $locked, changes: [
                'editor_version' => ['from' => $expectedVersion, 'to' => $expectedVersion + 1],
            ]);

            return $locked->fresh(['translations', 'currentFormVersion', 'form']);
        }, 3);
    }

    public function publishWorkshop(Workshop $workshop, int $expectedVersion, Admin $actor): Workshop
    {
        return DB::transaction(function () use ($workshop, $expectedVersion, $actor): Workshop {
            $locked = Workshop::query()->with(['translations', 'currentFormVersion'])->lockForUpdate()->findOrFail($workshop->id);
            $this->assertEditorVersion($locked, $expectedVersion);
            $this->assertPublishable($locked, ApplicationForm::PURPOSE_WORKSHOP);
            if (!$locked->registration_closes_at->isFuture()) {
                throw ValidationException::withMessages([
                    'schedule' => 'Update the registration deadline and workshop dates before publishing. The registration deadline must still be in the future.',
                ]);
            }
            $locked->update([
                'publication_status' => Workshop::PUBLICATION_PUBLISHED,
                'visible_from_at' => $locked->visible_from_at ?: now(),
                'published_by_admin_id' => $actor->id,
                'updated_by_admin_id' => $actor->id,
                'editor_version' => $expectedVersion + 1,
            ]);
            $this->audit->record($actor, 'workshop.published', $locked, context: ['editor_version' => $expectedVersion + 1, 'always_free' => true]);

            return $locked->fresh(['translations', 'currentFormVersion']);
        }, 3);
    }

    public function closeWorkshop(Workshop $workshop, Admin $actor): Workshop
    {
        return DB::transaction(function () use ($workshop, $actor): Workshop {
            $locked = Workshop::query()->lockForUpdate()->findOrFail($workshop->id);
            abort_unless($locked->publication_status === Workshop::PUBLICATION_PUBLISHED, 409, 'Only a published workshop can be closed.');
            if ($locked->registration_closes_at->isFuture()) {
                $locked->registration_closes_at = now();
            }
            $locked->editor_version++;
            $locked->updated_by_admin_id = $actor->id;
            $locked->save();
            $this->audit->record($actor, 'workshop.closed', $locked);

            return $locked->fresh();
        }, 3);
    }

    public function withdrawWorkshop(Workshop $workshop, Admin $actor): Workshop
    {
        return $this->withdraw($workshop, $actor, 'workshop.withdrawn');
    }

    public function duplicateWorkshop(Workshop $workshop, Admin $actor): Workshop
    {
        return DB::transaction(function () use ($workshop, $actor): Workshop {
            $source = Workshop::query()->with(['translations', 'form.versions'])->lockForUpdate()->findOrFail($workshop->id);
            $form = $this->forms->duplicate($source->form, 'Copy of ' . $source->form->name, $actor);
            $version = $this->forms->publish($form, (int) $form->fresh()->editor_version, $actor);
            $copy = $source->replicate([
                'uuid', 'publication_status', 'visible_from_at', 'editor_version',
                'created_by_admin_id', 'updated_by_admin_id', 'published_by_admin_id',
                'deleted_at',
            ]);
            $copy->forceFill([
                'application_form_id' => $form->id,
                'current_form_version_id' => $version->id,
                'publication_status' => Workshop::PUBLICATION_DRAFT,
                'visible_from_at' => null,
                'editor_version' => 1,
                'created_by_admin_id' => $actor->id,
                'updated_by_admin_id' => $actor->id,
                'published_by_admin_id' => null,
            ])->save();
            $this->copyTranslations($source, $copy);
            $this->audit->record($actor, 'workshop.duplicated', $copy, context: ['source_uuid' => $source->uuid, 'always_free' => true]);

            return $copy->fresh(['translations', 'currentFormVersion', 'form']);
        }, 3);
    }

    public function deleteWorkshopDraft(Workshop $workshop, Admin $actor): void
    {
        DB::transaction(function () use ($workshop, $actor): void {
            $locked = Workshop::query()->lockForUpdate()->findOrFail($workshop->id);
            abort_unless(
                $locked->publication_status === Workshop::PUBLICATION_DRAFT && !$locked->registrations()->exists(),
                409,
                'Published workshops and workshops with registrations must be closed or withdrawn, not deleted.',
            );
            $this->audit->record($actor, 'workshop.deleted', $locked);
            $locked->delete();
        }, 3);
    }

    /** @param array<string, mixed> $data
     *  @return array{listing:array<string,mixed>,translations:array<string,array<string,mixed>>,application_form_id:?int,form_version_id:?int,scorecard_criteria:?list<array{uuid:?string,label:string,description:?string,maximum_score:float,is_enabled:bool}>}
     */
    private function normalizeJob(array $data): array
    {
        $opens = $this->date($data['application_opens_at'] ?? null, 'application_opens_at');
        $closes = $this->date($data['application_closes_at'] ?? null, 'application_closes_at');
        $visible = $this->optionalDate($data['visible_from_at'] ?? null, 'visible_from_at');
        if ($opens >= $closes || ($visible && $visible > $opens)) {
            throw ValidationException::withMessages(['schedule' => 'Visibility must begin no later than applications, and applications must close after they open.']);
        }
        $employment = (string) ($data['employment_type'] ?? '');
        $arrangement = (string) ($data['work_arrangement'] ?? '');
        $vacancies = filter_var($data['vacancy_count'] ?? null, FILTER_VALIDATE_INT);
        if (!in_array($employment, JobPosting::EMPLOYMENT_TYPES, true)
            || !in_array($arrangement, JobPosting::WORK_ARRANGEMENTS, true)
            || $vacancies === false || $vacancies < 1 || $vacancies > 10_000) {
            throw ValidationException::withMessages(['job' => 'Choose valid employment, work-arrangement and vacancy values.']);
        }

        return [
            'listing' => [
                'visible_from_at' => $visible,
                'application_opens_at' => $opens,
                'application_closes_at' => $closes,
                'employment_type' => $employment,
                'work_arrangement' => $arrangement,
                'vacancy_count' => $vacancies,
            ],
            'translations' => $this->translations($data['translations'] ?? [], true, $arrangement),
            'application_form_id' => $this->positiveInt($data['application_form_id'] ?? null),
            'form_version_id' => $this->positiveInt($data['form_version_id'] ?? null),
            'scorecard_criteria' => array_key_exists('scorecard_criteria', $data)
                ? $this->scorecardCriteria($data['scorecard_criteria'])
                : null,
        ];
    }

    /** @param array<string, mixed> $data
     *  @return array{listing:array<string,mixed>,translations:array<string,array<string,mixed>>,application_form_id:?int,form_version_id:?int}
     */
    private function normalizeWorkshop(array $data): array
    {
        $opens = $this->date($data['registration_opens_at'] ?? null, 'registration_opens_at');
        $closes = $this->date($data['registration_closes_at'] ?? null, 'registration_closes_at');
        $starts = $this->date($data['starts_at'] ?? null, 'starts_at');
        $ends = $this->date($data['ends_at'] ?? null, 'ends_at');
        $visible = $this->optionalDate($data['visible_from_at'] ?? null, 'visible_from_at');
        if ($opens >= $closes || $closes > $starts || $starts >= $ends || ($visible && $visible > $opens)) {
            throw ValidationException::withMessages(['schedule' => 'Visibility, registration and event dates must be in chronological order.']);
        }
        $attendance = (string) ($data['attendance_mode'] ?? '');
        $registration = (string) ($data['registration_mode'] ?? '');
        $capacity = ($data['capacity'] ?? null) === null || ($data['capacity'] ?? '') === ''
            ? null
            : filter_var($data['capacity'], FILTER_VALIDATE_INT);
        if (!in_array($attendance, Workshop::ATTENDANCE_MODES, true)
            || !in_array($registration, Workshop::REGISTRATION_MODES, true)
            || ($capacity !== null && ($capacity === false || $capacity < 1 || $capacity > 1_000_000))
            || ($registration === Workshop::REGISTRATION_WAITLIST && $capacity === null)) {
            throw ValidationException::withMessages(['workshop' => 'Choose valid workshop modes and a capacity for waitlist mode.']);
        }
        $meetingUrl = trim((string) ($data['private_meeting_url'] ?? ''));
        if ($meetingUrl !== '' && (strlen($meetingUrl) > 2000 || filter_var($meetingUrl, FILTER_VALIDATE_URL) === false || !str_starts_with(strtolower($meetingUrl), 'https://'))) {
            throw ValidationException::withMessages(['private_meeting_url' => 'The private meeting URL must be a valid HTTPS URL.']);
        }

        return [
            'listing' => [
                'visible_from_at' => $visible,
                'registration_opens_at' => $opens,
                'registration_closes_at' => $closes,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'attendance_mode' => $attendance,
                'registration_mode' => $registration,
                'capacity' => $capacity,
                'private_meeting_url' => $meetingUrl ?: null,
            ],
            'translations' => $this->translations($data['translations'] ?? [], false, $attendance),
            'application_form_id' => $this->positiveInt($data['application_form_id'] ?? null),
            'form_version_id' => $this->positiveInt($data['form_version_id'] ?? null),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function translations(mixed $input, bool $job, string $mode): array
    {
        if (!is_array($input)) {
            throw ValidationException::withMessages(['translations' => 'English and Bangla content is required.']);
        }
        $normalized = [];
        $englishSlug = '';
        foreach (['en', 'bn'] as $locale) {
            $translation = is_array($input[$locale] ?? null) ? $input[$locale] : [];
            $title = $this->plain($translation['title'] ?? '', 255);
            $slug = Str::slug((string) ($translation['slug'] ?? $title));
            if ($locale === 'en') {
                $englishSlug = $slug;
            }
            $slug = $slug ?: $englishSlug;
            $description = $this->sanitizer->sanitizeHtml((string) ($translation['description'] ?? ''));
            if ($title === '' || $slug === '' || strlen($slug) > 190 || $description === '') {
                throw ValidationException::withMessages(["translations.{$locale}.title" => 'Each language requires a title, slug and description.']);
            }
            if ($job) {
                $requirements = $this->sanitizer->sanitizeHtml((string) ($translation['requirements'] ?? ''));
                $department = $this->plain($translation['department'] ?? '', 150);
                $location = $this->plain($translation['location'] ?? '', 255);
                if ($requirements === '' || $department === '' || $location === '') {
                    throw ValidationException::withMessages(["translations.{$locale}.requirements" => 'Each job language requires department, location and requirements.']);
                }
                $normalized[$locale] = [
                    'slug' => $slug,
                    'title' => $title,
                    'department' => $department,
                    'location' => $location,
                    'summary' => $this->plain($translation['summary'] ?? '', 2000) ?: null,
                    'description' => $description,
                    'responsibilities' => $this->sanitizer->sanitizeHtml((string) ($translation['responsibilities'] ?? '')) ?: null,
                    'requirements' => $requirements,
                ];
            } else {
                $venueName = $this->plain($translation['venue_name'] ?? '', 255);
                $venueAddress = $this->plain($translation['venue_address'] ?? '', 2000);
                if (in_array($mode, [Workshop::ATTENDANCE_OFFLINE, Workshop::ATTENDANCE_HYBRID], true)
                    && ($venueName === '' || $venueAddress === '')) {
                    throw ValidationException::withMessages(["translations.{$locale}.venue_name" => 'Physical and hybrid workshops require a venue in each language.']);
                }
                $normalized[$locale] = [
                    'slug' => $slug,
                    'title' => $title,
                    'summary' => $this->plain($translation['summary'] ?? '', 2000) ?: null,
                    'description' => $description,
                    'facilitator_name' => $this->plain($translation['facilitator_name'] ?? '', 255) ?: null,
                    'venue_name' => $venueName ?: null,
                    'venue_address' => $venueAddress ?: null,
                    'registration_instructions' => $this->sanitizer->sanitizeHtml((string) ($translation['registration_instructions'] ?? '')) ?: null,
                ];
            }
        }

        return $normalized;
    }

    /** @param array<string, mixed> $data
     *  @return array{ApplicationForm,ApplicationFormVersion}
     */
    private function resolveForm(
        string $purpose,
        array $data,
        Admin $actor,
        ?ApplicationForm $existingForm = null,
        ?ApplicationFormVersion $existingVersion = null,
    ): array {
        $formId = $data['application_form_id'];
        $versionId = $data['form_version_id'];
        if ($formId === null && $existingForm) {
            return [$existingForm, $existingVersion];
        }
        if ($formId === null) {
            $form = $this->forms->create($purpose, $purpose === ApplicationForm::PURPOSE_JOB ? 'Job application' : 'Workshop registration', $actor);
            $version = $this->forms->publish($form, (int) $form->editor_version, $actor);

            return [$form, $version];
        }

        $form = ApplicationForm::query()->where('purpose', $purpose)->findOrFail($formId);
        $versions = $form->versions()->where('state', ApplicationFormVersion::STATE_PUBLISHED);
        $version = $versionId ? $versions->whereKey($versionId)->first() : $versions->latest('version')->first();
        if (!$version) {
            throw ValidationException::withMessages(['application_form_id' => 'Choose a published form of the correct type.']);
        }

        return [$form, $version];
    }

    /** @param array<string, array<string, mixed>> $translations */
    private function persistTranslations(JobPosting|Workshop $listing, array $translations): void
    {
        foreach ($translations as $locale => $attributes) {
            $collision = $listing->translations()->where('locale', $locale)->where('slug', $attributes['slug'])->whereKeyNot(
                $listing->translations()->where('locale', $locale)->value('id') ?: 0,
            )->exists();
            $globalModel = $listing instanceof JobPosting ? \App\Models\JobPostingTranslation::class : \App\Models\WorkshopTranslation::class;
            $globalCollision = $globalModel::query()
                ->where('locale', $locale)
                ->where('slug', $attributes['slug'])
                ->where(($listing instanceof JobPosting ? 'job_posting_id' : 'workshop_id'), '!=', $listing->id)
                ->exists();
            if ($collision || $globalCollision) {
                throw ValidationException::withMessages(["translations.{$locale}.slug" => 'This public slug is already in use for that language.']);
            }
            $listing->translations()->updateOrCreate(['locale' => $locale], $attributes);
        }
    }

    /**
     * @return list<array{uuid:?string,label:string,description:?string,maximum_score:float,is_enabled:bool}>
     */
    private function scorecardCriteria(mixed $input): array
    {
        if (!is_array($input) || count($input) > 20) {
            throw ValidationException::withMessages(['scorecard_criteria' => 'Provide at most 20 scorecard criteria.']);
        }

        $criteria = [];
        $seenUuids = [];
        foreach (array_values($input) as $index => $criterion) {
            if (!is_array($criterion)) {
                throw ValidationException::withMessages(["scorecard_criteria.{$index}" => 'Enter a valid scorecard criterion.']);
            }
            $uuid = trim((string) ($criterion['uuid'] ?? '')) ?: null;
            if ($uuid !== null && (!Str::isUuid($uuid) || isset($seenUuids[$uuid]))) {
                throw ValidationException::withMessages(["scorecard_criteria.{$index}.uuid" => 'The scorecard criterion is invalid or duplicated.']);
            }
            if ($uuid !== null) {
                $seenUuids[$uuid] = true;
            }
            $label = $this->plain($criterion['label'] ?? '', 255);
            $description = $this->plain($criterion['description'] ?? '', 2000);
            $maximum = filter_var($criterion['maximum_score'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($label === '' || $maximum === false || $maximum <= 0 || $maximum > 1000) {
                throw ValidationException::withMessages(["scorecard_criteria.{$index}" => 'Each criterion needs a label and a maximum score between 0.01 and 1,000.']);
            }
            $criteria[] = [
                'uuid' => $uuid,
                'label' => $label,
                'description' => $description ?: null,
                'maximum_score' => round((float) $maximum, 2),
                'is_enabled' => filter_var($criterion['is_enabled'] ?? true, FILTER_VALIDATE_BOOL),
            ];
        }

        return $criteria;
    }

    /**
     * Synchronize editable criteria without deleting scored history. Positions
     * are allocated monotonically so the unique index remains safe even if a
     * historical criterion has been soft-deleted outside this workflow.
     *
     * @param list<array{uuid:?string,label:string,description:?string,maximum_score:float,is_enabled:bool}>|null $input
     */
    private function syncScorecardCriteria(JobPosting $job, ?array $input): void
    {
        if ($input === null) {
            return;
        }

        $existing = JobScorecardCriterion::query()
            ->where('job_posting_id', $job->getKey())
            ->lockForUpdate()
            ->get()
            ->keyBy('uuid');
        $submittedUuids = collect($input)->pluck('uuid')->filter()->values();
        $unknown = $submittedUuids->diff($existing->keys());
        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages(['scorecard_criteria' => 'One or more scorecard criteria do not belong to this job.']);
        }

        $nextPosition = (int) JobScorecardCriterion::withTrashed()
            ->where('job_posting_id', $job->getKey())
            ->max('position');
        foreach ($input as $attributes) {
            $criterion = $attributes['uuid'] ? $existing->get($attributes['uuid']) : null;
            $criterion ??= new JobScorecardCriterion(['job_posting_id' => $job->getKey()]);
            $criterion->fill([
                'label' => $attributes['label'],
                'description' => $attributes['description'],
                'maximum_score' => $attributes['maximum_score'],
                'position' => ++$nextPosition,
                'is_enabled' => $attributes['is_enabled'],
            ])->save();
        }

        $existing
            ->reject(fn (JobScorecardCriterion $criterion): bool => $submittedUuids->containsStrict($criterion->uuid))
            ->each(fn (JobScorecardCriterion $criterion) => $criterion->update([
                'is_enabled' => false,
                'position' => ++$nextPosition,
            ]));
    }

    private function assertPublishable(JobPosting|Workshop $listing, string $purpose): void
    {
        abort_unless($listing->translations->pluck('locale')->sort()->values()->all() === ['bn', 'en'], 422, 'English and Bangla content must be complete before publishing.');
        abort_unless(
            $listing->currentFormVersion
                && $listing->currentFormVersion->state === ApplicationFormVersion::STATE_PUBLISHED
                && $listing->currentFormVersion->form()->where('purpose', $purpose)->exists(),
            422,
            'A published form of the correct type is required.',
        );
    }

    private function assertEditorVersion(Model $listing, int $expected): void
    {
        abort_if((int) $listing->editor_version !== $expected, 409, 'This listing changed after you opened it. Reload before saving.');
    }

    private function withdraw(JobPosting|Workshop $listing, Admin $actor, string $action): JobPosting|Workshop
    {
        return DB::transaction(function () use ($listing, $actor, $action): JobPosting|Workshop {
            $locked = $listing->newQuery()->lockForUpdate()->findOrFail($listing->id);
            $locked->publication_status = $listing instanceof JobPosting
                ? JobPosting::PUBLICATION_WITHDRAWN
                : Workshop::PUBLICATION_WITHDRAWN;
            $locked->editor_version++;
            $locked->updated_by_admin_id = $actor->id;
            $locked->save();
            $this->audit->record($actor, $action, $locked);

            return $locked->fresh();
        }, 3);
    }

    private function copyTranslations(JobPosting|Workshop $source, JobPosting|Workshop $copy): void
    {
        $suffix = '-copy-' . Str::lower(Str::random(6));
        foreach ($source->translations as $translation) {
            $attributes = $translation->only(array_diff($translation->getFillable(), [
                $source instanceof JobPosting ? 'job_posting_id' : 'workshop_id',
                'locale',
            ]));
            $attributes['slug'] = Str::limit($attributes['slug'], 190 - strlen($suffix), '') . $suffix;
            $copy->translations()->create(['locale' => $translation->locale] + $attributes);
        }
    }

    private function date(mixed $value, string $key): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::parse((string) $value);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$key => 'Enter a valid date and time.']);
        }
        if ((string) $value === '') {
            throw ValidationException::withMessages([$key => 'Enter a valid date and time.']);
        }

        return $date;
    }

    private function optionalDate(mixed $value, string $key): ?CarbonImmutable
    {
        return $value === null || $value === '' ? null : $this->date($value, $key);
    }

    private function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer < 1) {
            throw ValidationException::withMessages(['application_form_id' => 'Choose a valid published form.']);
        }

        return $integer;
    }

    private function plain(mixed $value, int $maximum): string
    {
        $value = trim(strip_tags(str_replace("\0", '', (string) $value)));

        return mb_substr($value, 0, $maximum);
    }
}
