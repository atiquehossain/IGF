<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use App\Models\ApplicationFormVersion;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobPostingTranslation;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Models\WorkshopTranslation;
use App\Services\ApplicationFormSchemaService;
use App\Services\ContentSanitizer;
use App\Services\JobApplicationSubmissionService;
use App\Services\PublicCardImageService;
use App\Services\PublicFormTokenService;
use App\Services\WorkshopRegistrationService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    private const HONEYPOT = 'company_website';

    public function __construct(
        private ApplicationFormSchemaService $schemas,
        private ContentSanitizer $sanitizer,
        private PublicCardImageService $cardImages,
        private PublicFormTokenService $tokens,
    ) {
    }

    public function jobs(): Response
    {
        $locale = $this->locale();
        $now = now();
        $paginator = JobPosting::query()
            ->activeList($now)
            ->whereHas('translations', fn (Builder $query): Builder => $query->whereIn('locale', $this->translationLocales($locale)))
            ->with(['translations' => fn ($query) => $query->whereIn('locale', $this->translationLocales($locale))])
            ->orderBy('application_closes_at')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $items = $paginator->getCollection()
            ->map(fn (JobPosting $posting): array => $this->jobData($posting, $this->jobTranslation($posting, $locale), $now, false))
            ->values()
            ->all();
        $copy = $this->jobCopy($locale);

        return Inertia::render('careers', [
            'status' => true,
            'title' => $copy['title'],
            'meta_tag' => $this->meta(
                $copy['title'],
                $locale === 'bn'
                    ? 'ইগনাইট গ্লোবাল ফাউন্ডেশনের বর্তমান চাকরির সুযোগ দেখুন এবং অনলাইনে আবেদন করুন।'
                    : 'Explore current opportunities to work with Ignite Global Foundation and apply online.'
            ),
            'properties' => [
                'page' => $paginator->currentPage(),
                'total_page' => $paginator->lastPage(),
                'total_count' => $paginator->total(),
            ],
            'data' => [
                'items' => $items,
                'copy' => $copy,
            ],
        ]);
    }

    public function job(Request $request, string $job): Response
    {
        $locale = $this->locale();
        $now = now();
        $posting = $this->resolveJob($job, $locale, $now);
        $translation = $this->jobTranslation($posting, $locale);
        $isOpen = $this->jobIsOpen($posting, $now);
        $copy = $this->jobCopy($locale);
        $receipt = $this->receipt($request, 'job', (string) $posting->uuid);

        return Inertia::render('job', [
            'status' => true,
            'title' => (string) $translation->title,
            'meta_tag' => $this->meta(
                (string) $translation->title,
                $this->plainText((string) ($translation->summary ?: $translation->description))
            ),
            'data' => array_merge([
                'listing' => $this->jobData($posting, $translation, $now, true),
                'form' => $isOpen ? $this->publicForm($posting->currentFormVersion, 'job', (string) $posting->uuid, $locale, $request) : null,
                'copy' => $copy,
            ], $receipt),
        ]);
    }

    public function apply(
        Request $request,
        string $job,
        JobApplicationSubmissionService $submissions,
    ): RedirectResponse {
        $locale = $this->locale();
        $posting = $this->resolveJob($job, $locale, now());
        $this->assertPublicToken($request, 'job', (string) $posting->uuid, $locale);

        $application = $submissions->submit($posting, $this->submissionInput($request), $locale);
        $translation = $this->jobTranslation($posting, $locale);
        $request->session()->flash($this->receiptKey('job', (string) $posting->uuid), [
            'submission_reference' => (string) $application->reference_number,
            'submission_status_label' => $this->jobSubmissionStatus((string) $application->workflow_status, $locale),
            'submission_updated' => (int) $application->submission_count > 1,
        ]);

        return redirect()->route('frontend.jobs.show', ['job' => $translation->slug]);
    }

    public function workshops(): Response
    {
        $locale = $this->locale();
        $now = now();
        $paginator = Workshop::query()
            ->activeList($now)
            ->whereHas('translations', fn (Builder $query): Builder => $query->whereIn('locale', $this->translationLocales($locale)))
            ->with(['translations' => fn ($query) => $query->whereIn('locale', $this->translationLocales($locale))])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $items = $paginator->getCollection()
            ->map(fn (Workshop $workshop): array => $this->workshopData($workshop, $this->workshopTranslation($workshop, $locale), $now, false))
            ->values()
            ->all();
        $copy = $this->workshopCopy($locale);

        return Inertia::render('workshops', [
            'status' => true,
            'title' => $copy['title'],
            'meta_tag' => $this->meta(
                $copy['title'],
                $locale === 'bn'
                    ? 'ইগনাইট গ্লোবাল ফাউন্ডেশনের বিনামূল্যের কর্মশালাগুলো দেখুন এবং অনলাইনে নিবন্ধন করুন।'
                    : 'Explore free workshops from Ignite Global Foundation and register online.'
            ),
            'properties' => [
                'page' => $paginator->currentPage(),
                'total_page' => $paginator->lastPage(),
                'total_count' => $paginator->total(),
            ],
            'data' => [
                'items' => $items,
                'copy' => $copy,
            ],
        ]);
    }

    public function workshop(Request $request, string $workshop): Response
    {
        $locale = $this->locale();
        $now = now();
        $session = $this->resolveWorkshop($workshop, $locale, $now);
        $translation = $this->workshopTranslation($session, $locale);
        $isOpen = $this->workshopIsOpen($session, $now);
        $copy = $this->workshopCopy($locale);
        $receipt = $this->receipt($request, 'workshop', (string) $session->uuid);

        return Inertia::render('workshop', [
            'status' => true,
            'title' => (string) $translation->title,
            'meta_tag' => $this->meta(
                (string) $translation->title,
                $this->plainText((string) ($translation->summary ?: $translation->description))
            ),
            'data' => array_merge([
                'listing' => $this->workshopData($session, $translation, $now, true),
                'form' => $isOpen ? $this->publicForm($session->currentFormVersion, 'workshop', (string) $session->uuid, $locale, $request) : null,
                'copy' => $copy,
            ], $receipt),
        ]);
    }

    public function register(
        Request $request,
        string $workshop,
        WorkshopRegistrationService $registrations,
    ): RedirectResponse {
        $locale = $this->locale();
        $session = $this->resolveWorkshop($workshop, $locale, now());
        $this->assertPublicToken($request, 'workshop', (string) $session->uuid, $locale);

        $registration = $registrations->submit($session, $this->submissionInput($request), $locale);
        $translation = $this->workshopTranslation($session, $locale);
        $request->session()->flash($this->receiptKey('workshop', (string) $session->uuid), [
            'submission_reference' => (string) $registration->reference_number,
            'submission_status_label' => $this->workshopSubmissionStatus((string) $registration->workflow_status, $locale),
            'submission_updated' => (int) $registration->submission_count > 1,
        ]);

        return redirect()->route('frontend.workshops.show', ['workshop' => $translation->slug]);
    }

    private function resolveJob(string $slug, string $locale, CarbonInterface $now): JobPosting
    {
        foreach ($this->translationLocales($locale) as $translationLocale) {
            $posting = JobPosting::query()
                ->publicDetail($now)
                ->whereHas('translations', fn (Builder $query): Builder => $query
                    ->where('locale', $translationLocale)
                    ->where('slug', $slug))
                ->with(['translations' => fn ($query) => $query->whereIn('locale', $this->translationLocales($locale))])
                ->first();
            if ($posting) {
                return $posting;
            }
        }

        abort(404);
    }

    private function resolveWorkshop(string $slug, string $locale, CarbonInterface $now): Workshop
    {
        foreach ($this->translationLocales($locale) as $translationLocale) {
            $workshop = Workshop::query()
                ->publicDetail($now)
                ->whereHas('translations', fn (Builder $query): Builder => $query
                    ->where('locale', $translationLocale)
                    ->where('slug', $slug))
                ->with(['translations' => fn ($query) => $query->whereIn('locale', $this->translationLocales($locale))])
                ->first();
            if ($workshop) {
                return $workshop;
            }
        }

        abort(404);
    }

    private function jobTranslation(JobPosting $posting, string $locale): JobPostingTranslation
    {
        return $posting->translations->firstWhere('locale', $locale)
            ?? $posting->translations->firstWhere('locale', 'en')
            ?? abort(404);
    }

    private function workshopTranslation(Workshop $workshop, string $locale): WorkshopTranslation
    {
        return $workshop->translations->firstWhere('locale', $locale)
            ?? $workshop->translations->firstWhere('locale', 'en')
            ?? abort(404);
    }

    /** @return array<string, mixed> */
    private function jobData(
        JobPosting $posting,
        JobPostingTranslation $translation,
        CarbonInterface $now,
        bool $detail,
    ): array {
        $locale = $this->locale();
        $state = $this->jobState($posting, $now);
        $data = [
            'uuid' => (string) $posting->uuid,
            'slug' => (string) $translation->slug,
            'title' => (string) $translation->title,
            'department' => $this->plainText((string) $translation->department, 150),
            'location' => $this->plainText((string) $translation->location, 255),
            'summary' => $this->plainText((string) $translation->summary, 2000),
            'employment_type_label' => $this->employmentType((string) $posting->employment_type, $locale),
            'work_arrangement_label' => $this->workArrangement((string) $posting->work_arrangement, $locale),
            'vacancies' => (int) $posting->vacancy_count,
            'application_opens_label' => $this->dateLabel($posting->application_opens_at, $locale),
            'application_deadline_label' => $this->dateLabel($posting->application_closes_at, $locale),
            'application_state' => $state,
            'is_open' => $this->jobIsOpen($posting, $now),
            'status_label' => $this->publicStateLabel($state, 'job', $locale),
            'public_url' => route('frontend.jobs.show', ['job' => $translation->slug]),
        ];

        if ($detail) {
            $data['description'] = $this->sanitizer->sanitizeHtml($translation->description);
            $data['responsibilities'] = $this->sanitizer->sanitizeHtml($translation->responsibilities);
            $data['requirements'] = $this->sanitizer->sanitizeHtml($translation->requirements);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function workshopData(
        Workshop $workshop,
        WorkshopTranslation $translation,
        CarbonInterface $now,
        bool $detail,
    ): array {
        $locale = $this->locale();
        $state = $this->workshopState($workshop, $now);
        $cardImage = $this->cardImages->firstManagedImage($translation->description);
        $data = [
            'uuid' => (string) $workshop->uuid,
            'slug' => (string) $translation->slug,
            'title' => (string) $translation->title,
            'summary' => $this->plainText((string) $translation->summary, 2000),
            'image_url' => $cardImage['url'] ?? null,
            'image_alt' => $cardImage['alt'] ?? null,
            'facilitator_name' => $this->plainText((string) $translation->facilitator_name, 255),
            'venue' => $this->plainText((string) $translation->venue_name, 255),
            'workshop_date_label' => $this->dateRangeLabel($workshop->starts_at, $workshop->ends_at, $locale),
            'registration_opens_label' => $this->dateLabel($workshop->registration_opens_at, $locale),
            'registration_deadline_label' => $this->dateLabel($workshop->registration_closes_at, $locale),
            'format_label' => $this->workshopFormat((string) $workshop->attendance_mode, $locale),
            'registration_state' => $state,
            'is_open' => $this->workshopIsOpen($workshop, $now),
            'status_label' => $this->publicStateLabel($state, 'workshop', $locale),
            'public_url' => route('frontend.workshops.show', ['workshop' => $translation->slug]),
        ];

        if ($detail) {
            $data['description'] = $this->sanitizer->sanitizeHtml($translation->description);
            $data['venue_address'] = $this->plainText((string) $translation->venue_address, 2000);
            $data['registration_instructions'] = $this->sanitizer->sanitizeHtml($translation->registration_instructions);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function publicForm(
        ?ApplicationFormVersion $version,
        string $kind,
        string $listingUuid,
        string $locale,
        Request $request,
    ): ?array {
        if (!$version || !in_array($version->state, [
            ApplicationFormVersion::STATE_PUBLISHED,
            ApplicationFormVersion::STATE_RETIRED,
        ], true)) {
            return null;
        }

        return array_merge($this->schemas->publicSchema($version, $locale), [
            'token' => $this->tokens->issue($kind, $listingUuid),
            'honeypot_name' => self::HONEYPOT,
            'initial_values' => $this->initialValues($request),
        ]);
    }

    private function jobIsOpen(JobPosting $posting, CarbonInterface $now): bool
    {
        if (!$posting->current_form_version_id) {
            return false;
        }

        return JobPosting::query()
            ->openForSubmission($now)
            ->whereKey($posting->getKey())
            ->whereHas('currentFormVersion', fn (Builder $query): Builder => $query->whereIn('state', [
                ApplicationFormVersion::STATE_PUBLISHED,
                ApplicationFormVersion::STATE_RETIRED,
            ]))
            ->exists();
    }

    private function workshopIsOpen(Workshop $workshop, CarbonInterface $now): bool
    {
        if (!$workshop->current_form_version_id) {
            return false;
        }

        return Workshop::query()
            ->openForSubmission($now)
            ->whereKey($workshop->getKey())
            ->whereHas('currentFormVersion', fn (Builder $query): Builder => $query->whereIn('state', [
                ApplicationFormVersion::STATE_PUBLISHED,
                ApplicationFormVersion::STATE_RETIRED,
            ]))
            ->exists();
    }

    private function jobState(JobPosting $posting, CarbonInterface $now): string
    {
        if ($posting->application_closes_at->lte($now)) {
            return 'closed';
        }

        return $posting->application_opens_at->gt($now) ? 'upcoming' : 'open';
    }

    private function workshopState(Workshop $workshop, CarbonInterface $now): string
    {
        if ($workshop->registration_closes_at->lte($now)) {
            return 'closed';
        }

        return $workshop->registration_opens_at->gt($now) ? 'upcoming' : 'open';
    }

    /** @return array<string, mixed> */
    private function submissionInput(Request $request): array
    {
        return $request->only([
            'applicant_name',
            'email',
            'phone',
            'cv',
            'responses',
        ]);
    }

    private function assertPublicToken(Request $request, string $kind, string $listingUuid, string $locale): void
    {
        try {
            $this->tokens->assertValid(
                $request->input('form_token'),
                $kind,
                $listingUuid,
                $request->input(self::HONEYPOT),
            );
        } catch (ValidationException $exception) {
            if ($locale !== 'bn') {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'submission' => 'এই ফর্ম সেশনটি আর বৈধ নয়। পৃষ্ঠাটি রিফ্রেশ করে আবার চেষ্টা করুন।',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function initialValues(Request $request): array
    {
        $values = is_array($request->old('responses')) ? $request->old('responses') : [];
        foreach (['applicant_name', 'email', 'phone'] as $key) {
            if ($request->old($key) !== null) {
                $values[$key] = $request->old($key);
            }
        }

        return $values;
    }

    /** @return array<string, mixed> */
    private function receipt(Request $request, string $kind, string $listingUuid): array
    {
        $receipt = $request->session()->pull($this->receiptKey($kind, $listingUuid));
        if (!is_array($receipt) || trim((string) ($receipt['submission_reference'] ?? '')) === '') {
            return [];
        }

        return [
            'submission_reference' => (string) $receipt['submission_reference'],
            'submission_status_label' => (string) ($receipt['submission_status_label'] ?? ''),
            'submission_updated' => (bool) ($receipt['submission_updated'] ?? false),
        ];
    }

    private function receiptKey(string $kind, string $listingUuid): string
    {
        return "public_opportunity_receipt.{$kind}.{$listingUuid}";
    }

    /** @return list<string> */
    private function translationLocales(string $locale): array
    {
        return array_values(array_unique([$locale, 'en']));
    }

    private function locale(): string
    {
        return app()->getLocale() === 'bn' ? 'bn' : 'en';
    }

    private function plainText(string $value, int $limit = 3000): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return mb_substr(trim($value), 0, $limit);
    }

    private function dateLabel(?CarbonInterface $date, string $locale): string
    {
        return $date?->locale($locale)->translatedFormat('j F Y, g:i A') ?? '';
    }

    private function dateRangeLabel(?CarbonInterface $startsAt, ?CarbonInterface $endsAt, string $locale): string
    {
        if (!$startsAt) {
            return '';
        }

        $start = $this->dateLabel($startsAt, $locale);
        if (!$endsAt) {
            return $start;
        }

        return $start . ' – ' . $endsAt->locale($locale)->translatedFormat('j F Y, g:i A');
    }

    /** @return array<string, string> */
    private function meta(string $title, string $description): array
    {
        return [
            'meta_keyword' => $title . ', Ignite Global Foundation',
            'meta_title' => $title . ' | Ignite Global Foundation',
            'meta_description' => mb_substr($description, 0, 160),
        ];
    }

    private function employmentType(string $type, string $locale): string
    {
        $labels = $locale === 'bn'
            ? ['full_time' => 'পূর্ণকালীন', 'part_time' => 'খণ্ডকালীন', 'contract' => 'চুক্তিভিত্তিক', 'internship' => 'ইন্টার্নশিপ', 'consultancy' => 'পরামর্শক']
            : ['full_time' => 'Full time', 'part_time' => 'Part time', 'contract' => 'Contract', 'internship' => 'Internship', 'consultancy' => 'Consultancy'];

        return $labels[$type] ?? str($type)->replace('_', ' ')->title()->toString();
    }

    private function workshopFormat(string $mode, string $locale): string
    {
        $labels = $locale === 'bn'
            ? ['offline' => 'সশরীরে', 'online' => 'অনলাইন', 'hybrid' => 'হাইব্রিড']
            : ['offline' => 'In person', 'online' => 'Online', 'hybrid' => 'Hybrid'];

        return $labels[$mode] ?? str($mode)->replace('_', ' ')->title()->toString();
    }

    private function workArrangement(string $arrangement, string $locale): string
    {
        $labels = $locale === 'bn'
            ? ['on_site' => 'অফিসে', 'remote' => 'দূরবর্তী', 'hybrid' => 'হাইব্রিড']
            : ['on_site' => 'On site', 'remote' => 'Remote', 'hybrid' => 'Hybrid'];

        return $labels[$arrangement] ?? str($arrangement)->replace('_', ' ')->title()->toString();
    }

    private function publicStateLabel(string $state, string $kind, string $locale): string
    {
        if ($locale === 'bn') {
            return match ($state) {
                'open' => $kind === 'job' ? 'আবেদন চলছে' : 'নিবন্ধন চলছে',
                'upcoming' => $kind === 'job' ? 'আবেদন শিগগির শুরু হবে' : 'নিবন্ধন শিগগির শুরু হবে',
                default => $kind === 'job' ? 'আবেদন বন্ধ' : 'নিবন্ধন বন্ধ',
            };
        }

        return match ($state) {
            'open' => $kind === 'job' ? 'Applications open' : 'Registration open',
            'upcoming' => $kind === 'job' ? 'Applications open soon' : 'Registration opens soon',
            default => $kind === 'job' ? 'Applications closed' : 'Registration closed',
        };
    }

    private function jobSubmissionStatus(string $status, string $locale): string
    {
        $labels = $locale === 'bn'
            ? [
                JobApplication::STATUS_NEW => 'গৃহীত',
                JobApplication::STATUS_UNDER_REVIEW => 'পর্যালোচনাধীন',
                JobApplication::STATUS_SHORTLISTED => 'সংক্ষিপ্ত তালিকায়',
                JobApplication::STATUS_INTERVIEW => 'সাক্ষাৎকার পর্যায়ে',
                JobApplication::STATUS_OFFERED => 'প্রস্তাব দেওয়া হয়েছে',
                JobApplication::STATUS_HIRED => 'নিয়োগপ্রাপ্ত',
                JobApplication::STATUS_REJECTED => 'নির্বাচিত নয়',
                JobApplication::STATUS_WITHDRAWN => 'প্রত্যাহার করা হয়েছে',
            ]
            : [
                JobApplication::STATUS_NEW => 'Received',
                JobApplication::STATUS_UNDER_REVIEW => 'Under review',
                JobApplication::STATUS_SHORTLISTED => 'Shortlisted',
                JobApplication::STATUS_INTERVIEW => 'Interview stage',
                JobApplication::STATUS_OFFERED => 'Offer made',
                JobApplication::STATUS_HIRED => 'Hired',
                JobApplication::STATUS_REJECTED => 'Not selected',
                JobApplication::STATUS_WITHDRAWN => 'Withdrawn',
            ];

        return $labels[$status] ?? ($locale === 'bn' ? 'গৃহীত' : 'Received');
    }

    private function workshopSubmissionStatus(string $status, string $locale): string
    {
        $labels = $locale === 'bn'
            ? [
                WorkshopRegistration::STATUS_PENDING => 'অনুমোদনের অপেক্ষায়',
                WorkshopRegistration::STATUS_CONFIRMED => 'নিবন্ধন নিশ্চিত',
                WorkshopRegistration::STATUS_WAITLISTED => 'অপেক্ষমাণ তালিকায়',
                WorkshopRegistration::STATUS_REJECTED => 'অনুমোদিত নয়',
                WorkshopRegistration::STATUS_CANCELLED => 'বাতিল',
            ]
            : [
                WorkshopRegistration::STATUS_PENDING => 'Awaiting approval',
                WorkshopRegistration::STATUS_CONFIRMED => 'Registration confirmed',
                WorkshopRegistration::STATUS_WAITLISTED => 'Waiting list',
                WorkshopRegistration::STATUS_REJECTED => 'Not approved',
                WorkshopRegistration::STATUS_CANCELLED => 'Cancelled',
            ];

        return $labels[$status] ?? ($locale === 'bn' ? 'গৃহীত' : 'Received');
    }

    /** @return array<string, mixed> */
    private function jobCopy(string $locale): array
    {
        if ($locale === 'bn') {
            return [
                'eyebrow' => 'আমাদের দলে যোগ দিন',
                'title' => 'কর্মজীবন',
                'introduction' => 'ইগনাইট গ্লোবাল ফাউন্ডেশনের সঙ্গে কাজ করার বর্তমান সুযোগগুলো দেখুন।',
                'listing_title' => 'বর্তমান চাকরির সুযোগ',
                'empty_title' => 'এই মুহূর্তে কোনো পদ খালি নেই',
                'empty_message' => 'নতুন সুযোগের জন্য পরে আবার দেখুন।',
                'pagination_label' => 'চাকরির তালিকার পৃষ্ঠা',
                'back_label' => 'চাকরির তালিকায় ফিরুন',
                'location_label' => 'কর্মস্থল',
                'employment_type_label' => 'চাকরির ধরন',
                'work_arrangement_label' => 'কাজের ধরন',
                'vacancies_label' => 'পদসংখ্যা',
                'opens_label' => 'আবেদন শুরুর সময়',
                'deadline_label' => 'আবেদনের শেষ সময়',
                'requirements_title' => 'যোগ্যতা ও প্রয়োজনীয়তা',
                'responsibilities_title' => 'দায়িত্বসমূহ',
                'form_eyebrow' => 'আবেদনপত্র',
                'form_title' => 'এই পদের জন্য আবেদন করুন',
                'form_introduction' => 'নিচের তথ্য পূরণ করুন। তারকা চিহ্নিত ঘরগুলো আবশ্যক।',
                'applicant_name_label' => 'পূর্ণ নাম',
                'applicant_name_placeholder' => 'আপনার পূর্ণ নাম লিখুন',
                'email_label' => 'ইমেইল ঠিকানা',
                'phone_label' => 'ফোন নম্বর',
                'cv_label' => 'জীবনবৃত্তান্ত (PDF)',
                'cv_help' => 'একটি PDF আপলোড করুন। সর্বোচ্চ আকার ৫ এমবি।',
                'submit_label' => 'আবেদন জমা দিন',
                'submitting_label' => 'জমা দেওয়া হচ্ছে…',
                'privacy_message' => 'আপনার আবেদন ও জীবনবৃত্তান্ত শুধু অনুমোদিত নিয়োগকর্মীরা দেখতে পারবেন।',
                'closed_title' => 'আবেদন বন্ধ',
                'closed_message' => 'এই পদে আর আবেদন গ্রহণ করা হচ্ছে না।',
                'upcoming_title' => 'আবেদন এখনো শুরু হয়নি',
                'upcoming_message' => 'আবেদন শুরু হলে আবার আসুন।',
                'card' => ['link_label' => 'বিস্তারিত দেখুন ও আবেদন করুন'],
                ...$this->banglaFormMessages(),
                'error_summary' => [
                    'title' => 'ফর্মটি যাচাই করুন',
                    'introduction' => 'নিচের তথ্য ঠিক করে আবার জমা দিন।',
                    'submission_label' => 'আবেদন',
                    'general_label' => 'ফর্ম',
                ],
                'submission' => [
                    'eyebrow' => 'আবেদন গৃহীত হয়েছে',
                    'title' => 'ধন্যবাদ',
                    'message' => 'আপনার তথ্য গৃহীত হয়েছে। নির্বাচিত প্রার্থীদের সঙ্গে আমাদের দল যোগাযোগ করবে।',
                    'updated_message' => 'এই পদের জন্য আপনার আগের আবেদনটি সর্বশেষ তথ্য দিয়ে হালনাগাদ হয়েছে।',
                    'reference_label' => 'রেফারেন্স নম্বর',
                ],
            ];
        }

        return [
            'eyebrow' => 'Join our team',
            'title' => 'Careers',
            'introduction' => 'Explore current opportunities to work with Ignite Global Foundation.',
            'listing_title' => 'Current opportunities',
            'empty_title' => 'No open positions right now',
            'empty_message' => 'Please check again for future opportunities.',
            'back_label' => 'Back to careers',
            'card' => ['link_label' => 'View job and apply'],
            'error_summary' => [
                'title' => 'Please check the form',
                'introduction' => 'Correct the fields below and submit again.',
                'submission_label' => 'Application',
                'general_label' => 'Form',
            ],
            'submission' => [
                'eyebrow' => 'Application received',
                'title' => 'Thank you',
                'message' => 'Your information has been received. Our team will contact selected applicants.',
                'updated_message' => 'Your latest submission replaced your earlier application for this position.',
                'reference_label' => 'Reference number',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function workshopCopy(string $locale): array
    {
        if ($locale === 'bn') {
            return [
                'eyebrow' => 'একসঙ্গে শিখি',
                'title' => 'বিনামূল্যের কর্মশালা',
                'introduction' => 'ইগনাইট গ্লোবাল ফাউন্ডেশনের বিনামূল্যের কর্মশালায় নিবন্ধন করুন।',
                'listing_title' => 'আসন্ন কর্মশালা',
                'empty_title' => 'এই মুহূর্তে কোনো কর্মশালা খোলা নেই',
                'empty_message' => 'আসন্ন সেশনের জন্য পরে আবার দেখুন।',
                'pagination_label' => 'কর্মশালার তালিকার পৃষ্ঠা',
                'back_label' => 'কর্মশালার তালিকায় ফিরুন',
                'date_label' => 'কর্মশালার সময়',
                'registration_opens_label' => 'নিবন্ধন শুরুর সময়',
                'registration_deadline_label' => 'নিবন্ধনের শেষ সময়',
                'venue_label' => 'স্থান',
                'format_label' => 'আয়োজনের ধরন',
                'venue_details_title' => 'স্থানের বিস্তারিত',
                'registration_instructions_title' => 'নিবন্ধনের তথ্য',
                'form_eyebrow' => 'নিবন্ধন ফর্ম',
                'form_title' => 'এই কর্মশালায় নিবন্ধন করুন',
                'form_introduction' => 'নিচের তথ্য পূরণ করুন। তারকা চিহ্নিত ঘরগুলো আবশ্যক।',
                'applicant_name_label' => 'পূর্ণ নাম',
                'applicant_name_placeholder' => 'আপনার পূর্ণ নাম লিখুন',
                'email_label' => 'ইমেইল ঠিকানা',
                'phone_label' => 'ফোন নম্বর',
                'submit_label' => 'নিবন্ধন জমা দিন',
                'submitting_label' => 'জমা দেওয়া হচ্ছে…',
                'privacy_message' => 'আপনার নিবন্ধনের তথ্য শুধু অনুমোদিত কর্মশালা কর্মীরা দেখতে পারবেন।',
                'closed_title' => 'নিবন্ধন বন্ধ',
                'closed_message' => 'এই কর্মশালায় আর নিবন্ধন গ্রহণ করা হচ্ছে না।',
                'upcoming_title' => 'নিবন্ধন এখনো শুরু হয়নি',
                'upcoming_message' => 'নিবন্ধন শুরু হলে আবার আসুন।',
                'card' => ['link_label' => 'বিস্তারিত দেখুন ও নিবন্ধন করুন'],
                ...$this->banglaFormMessages(),
                'error_summary' => [
                    'title' => 'ফর্মটি যাচাই করুন',
                    'introduction' => 'নিচের তথ্য ঠিক করে আবার জমা দিন।',
                    'submission_label' => 'নিবন্ধন',
                    'general_label' => 'ফর্ম',
                ],
                'submission' => [
                    'eyebrow' => 'নিবন্ধন গৃহীত হয়েছে',
                    'title' => 'ধন্যবাদ',
                    'message' => 'আপনার তথ্য গৃহীত হয়েছে। প্রয়োজন হলে আমাদের দল যোগাযোগ করবে।',
                    'updated_message' => 'এই কর্মশালার জন্য আপনার আগের নিবন্ধনটি সর্বশেষ তথ্য দিয়ে হালনাগাদ হয়েছে।',
                    'reference_label' => 'রেফারেন্স নম্বর',
                ],
            ];
        }

        return [
            'eyebrow' => 'Learn together',
            'title' => 'Free workshops',
            'introduction' => 'Register for free workshops led by Ignite Global Foundation.',
            'listing_title' => 'Upcoming workshops',
            'empty_title' => 'No workshops are open right now',
            'empty_message' => 'Please check again for upcoming sessions.',
            'back_label' => 'Back to workshops',
            'card' => ['link_label' => 'View workshop and register'],
            'error_summary' => [
                'title' => 'Please check the form',
                'introduction' => 'Correct the fields below and submit again.',
                'submission_label' => 'Registration',
                'general_label' => 'Form',
            ],
            'submission' => [
                'eyebrow' => 'Registration received',
                'title' => 'Thank you',
                'message' => 'Your information has been received. Our team will contact you if needed.',
                'updated_message' => 'Your latest submission replaced your earlier registration for this workshop.',
                'reference_label' => 'Reference number',
            ],
        ];
    }

    /** @return array<string, string> */
    private function banglaFormMessages(): array
    {
        return [
            'required_label' => 'আবশ্যক',
            'required_message' => '{field} আবশ্যক।',
            'invalid_email_message' => 'একটি বৈধ ইমেইল ঠিকানা লিখুন।',
            'invalid_number_message' => 'একটি বৈধ সংখ্যা লিখুন।',
            'invalid_date_message' => 'একটি বৈধ তারিখ লিখুন।',
            'minimum_length_message' => '{field}-এ কমপক্ষে {min} অক্ষর থাকতে হবে।',
            'maximum_length_message' => '{field}-এ সর্বোচ্চ {max} অক্ষর থাকতে পারবে।',
            'minimum_value_message' => '{field}-এর মান কমপক্ষে {min} হতে হবে।',
            'maximum_value_message' => '{field}-এর মান সর্বোচ্চ {max} হতে পারবে।',
            'minimum_selections_message' => '{field}-এর জন্য কমপক্ষে {min}টি অপশন বেছে নিন।',
            'maximum_selections_message' => '{field}-এর জন্য সর্বোচ্চ {max}টি অপশন বেছে নিন।',
            'invalid_format_message' => '{field}-এর বিন্যাস যাচাই করুন।',
            'invalid_file_type_message' => 'একটি PDF ফাইল আপলোড করুন।',
            'file_too_large_message' => 'ফাইলটি {max} বা তার চেয়ে ছোট হতে হবে।',
            'select_placeholder' => 'একটি অপশন বেছে নিন',
            'yes_label' => 'হ্যাঁ',
            'no_label' => 'না',
            'form_unavailable' => 'এই ফর্মটি এখন উপলভ্য নয়।',
            'honeypot_label' => 'এই ঘরটি খালি রাখুন',
        ];
    }
}
