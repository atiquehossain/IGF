@extends('admin.layouts.master')

@php
    $editing = $workshop->exists;
    $translations = $workshop->translations->keyBy('locale');
    $dateValue = static fn ($value) => $value?->format('Y-m-d\TH:i');
    $admin = Auth::guard('admin')->user();
    $permissions = app(\App\Http\Middleware\Permission::class);
    $canPublish = $editing && $permissions->allows($admin, 'workshops.status');
    $canManageForms = $permissions->allows($admin, 'workshops.templates.manage');
    $isPublished = $editing && $workshop->publication_status === \App\Models\Workshop::PUBLICATION_PUBLISHED;
    $isDraft = !$editing || $workshop->publication_status === \App\Models\Workshop::PUBLICATION_DRAFT;
    $englishTranslation = $translations->get('en');
    $selectedAttendance = old('attendance_mode', $editing ? $workshop->attendance_mode : '');
    $selectedRegistration = old('registration_mode', $editing ? $workshop->registration_mode : '');
    $capacityValue = old('capacity', $workshop->capacity);
    $capacityChoice = old('capacity_choice', $editing ? ($capacityValue ? 'limited' : 'unlimited') : '');
    $publicUrl = $isPublished && $englishTranslation
        ? route('frontend.workshops.show', ['workshop' => $englishTranslation->slug])
        : null;
@endphp

@section('custom-css')
    <link rel="stylesheet" href="{{ asset('admin-assets/workshop-form/workshop-form.css') }}">
@endsection

@section('content')
<main class="content pb-0" aria-labelledby="workshop-form-title">
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3 workshop-page-header">
        <div>
            <div class="d-flex flex-wrap align-items-center mb-1" style="gap:.55rem">
                <h1 id="workshop-form-title" class="h3 mb-0">{{ $title }}</h1>
                @if($editing)
                    <span class="badge badge-{{ $isPublished ? 'success' : ($isDraft ? 'warning' : 'secondary') }}">
                        {{ $isPublished ? 'Published · live' : Str::headline($workshop->publication_status) }}
                    </span>
                @endif
            </div>
            <p class="text-muted mb-0">
                <strong>Always free.</strong> Four guided steps; required fields are marked <span aria-hidden="true">*</span>.
            </p>
        </div>
        <div class="d-flex flex-wrap workshop-header-actions">
            @if($publicUrl)
                <a class="btn igf-btn igf-btn-secondary" href="{{ $publicUrl }}" target="_blank" rel="noopener">
                    <i class="fa fa-external-link" aria-hidden="true"></i> View public page
                </a>
            @endif
            <a class="btn igf-btn igf-btn-secondary" href="{{ route('workshops.index') }}">
                <i class="fa fa-arrow-left" aria-hidden="true"></i> Workshops
            </a>
        </div>
    </div>

    @if($errors->any())
        <div id="workshop-form-errors" class="alert alert-danger" role="alert" tabindex="-1">
            <strong>Please correct the information below.</strong>
            <ul class="mb-0">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if($isPublished)
        <div class="alert alert-warning workshop-state-message" role="status">
            <i class="fa fa-globe" aria-hidden="true"></i>
            <div>
                <strong>This workshop is live.</strong>
                Saving changes updates the public page and registration settings immediately.
                @if(($workshop->registrations_count ?? 0) > 0)
                    It already has {{ $workshop->registrations_count }} {{ Str::plural('registration', $workshop->registrations_count) }}.
                @endif
            </div>
        </div>
    @else
        <div class="alert alert-info workshop-state-message" role="status">
            <i class="fa fa-lock" aria-hidden="true"></i>
            <div><strong>Private draft.</strong> Saving does not publish the workshop. Review it, then use “Publish saved draft.”</div>
        </div>
    @endif

    @if($editing && $isDraft && $canPublish)
        <section class="card mb-3 workshop-publish-card" aria-labelledby="publish-saved-title">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
                <div class="mr-3">
                    <h2 id="publish-saved-title" class="h5 mb-1">Ready for the public?</h2>
                    <p class="text-muted mb-0">Only the last saved version will be published. Unsaved changes below are not included.</p>
                </div>
                <form method="post" action="{{ route('workshops.status', $workshop) }}" onsubmit="return confirm('Publish the saved workshop now? Please confirm the deadline, workshop time, attendance, registration decision and capacity first.')">
                    @csrf
                    @method('patch')
                    <input type="hidden" name="editor_version" value="{{ $workshop->editor_version }}">
                    <input type="hidden" name="action" value="publish">
                    <button class="btn igf-btn igf-btn-primary" type="submit" data-cy="publish-workshop">
                        <i class="fa fa-globe" aria-hidden="true"></i> Publish saved draft
                    </button>
                </form>
            </div>
        </section>
    @endif

    <nav class="workshop-steps mb-3" aria-label="Workshop creation steps">
        <span><strong>1</strong> Date &amp; place</span>
        <span><strong>2</strong> Registration</span>
        <span><strong>3</strong> English page</span>
        <span><strong>4</strong> Bangla page</span>
    </nav>

    <form
        id="workshop-form"
        class="workshop-guided-form"
        method="post"
        action="{{ $editing ? route('workshops.update', $workshop) : route('workshops.store') }}"
        data-workshop-form
        data-live-edit="{{ $isPublished ? '1' : '0' }}"
        data-registration-count="{{ $workshop->registrations_count ?? 0 }}"
    >
        @csrf
        @if($editing)
            @method('put')
            <input type="hidden" name="editor_version" value="{{ $workshop->editor_version }}">
        @endif

        <section class="card mb-3" aria-labelledby="workshop-schedule-title">
            <div class="card-header workshop-section-heading">
                <span class="workshop-step-number" aria-hidden="true">1</span>
                <div>
                    <h2 id="workshop-schedule-title" class="h5 mb-1">Date, time and place</h2>
                    <small class="text-muted">Enter every time in Bangladesh time (Asia/Dhaka, UTC+6).</small>
                </div>
            </div>
            <div class="card-body">
                @error('schedule')
                    <div class="alert alert-danger" role="alert">{{ $message }}</div>
                @enderror
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="starts_at">Workshop starts <span aria-hidden="true">*</span></label>
                        <input
                            id="starts_at"
                            class="form-control @error('starts_at') is-invalid @enderror"
                            type="datetime-local"
                            name="starts_at"
                            value="{{ old('starts_at', $dateValue($workshop->starts_at)) }}"
                            aria-describedby="starts-at-help"
                            @error('starts_at') aria-invalid="true" @enderror
                            required
                        >
                        <small id="starts-at-help" class="form-text text-muted">The date and time participants should join.</small>
                        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="ends_at">Workshop ends <span aria-hidden="true">*</span></label>
                        <input
                            id="ends_at"
                            class="form-control @error('ends_at') is-invalid @enderror"
                            type="datetime-local"
                            name="ends_at"
                            value="{{ old('ends_at', $dateValue($workshop->ends_at)) }}"
                            @error('ends_at') aria-invalid="true" @enderror
                            required
                        >
                        <button id="workshop-set-duration" class="btn btn-link workshop-inline-action" type="button">Set to 90 minutes after start</button>
                        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="registration_opens_at">Registration opens <span aria-hidden="true">*</span></label>
                        <input
                            id="registration_opens_at"
                            class="form-control @error('registration_opens_at') is-invalid @enderror"
                            type="datetime-local"
                            name="registration_opens_at"
                            value="{{ old('registration_opens_at', $dateValue($workshop->registration_opens_at)) }}"
                            @error('registration_opens_at') aria-invalid="true" @enderror
                            required
                        >
                        @error('registration_opens_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="registration_closes_at">Registration deadline <span aria-hidden="true">*</span></label>
                        <input
                            id="registration_closes_at"
                            class="form-control @error('registration_closes_at') is-invalid @enderror"
                            type="datetime-local"
                            name="registration_closes_at"
                            value="{{ old('registration_closes_at', $dateValue($workshop->registration_closes_at)) }}"
                            aria-describedby="registration-deadline-help"
                            @error('registration_closes_at') aria-invalid="true" @enderror
                            required
                        >
                        <small id="registration-deadline-help" class="form-text text-muted">Registration closes automatically at this time.</small>
                        @error('registration_closes_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="attendance_mode">How will people attend? <span aria-hidden="true">*</span></label>
                        <select
                            id="attendance_mode"
                            class="form-control @error('attendance_mode') is-invalid @enderror"
                            name="attendance_mode"
                            aria-describedby="attendance-help"
                            @error('attendance_mode') aria-invalid="true" @enderror
                            required
                        >
                            <option value="" disabled @selected($selectedAttendance === '')>Choose attendance type</option>
                            <option value="offline" @selected($selectedAttendance === 'offline')>In person at a venue</option>
                            <option value="online" @selected($selectedAttendance === 'online')>Online</option>
                            <option value="hybrid" @selected($selectedAttendance === 'hybrid')>Hybrid: venue and online</option>
                        </select>
                        <small id="attendance-help" class="form-text text-muted">Venue fields become required for in-person and hybrid workshops.</small>
                        @error('attendance_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 form-group" data-meeting-field>
                        <label for="private_meeting_url">Private HTTPS meeting link</label>
                        <input
                            id="private_meeting_url"
                            class="form-control @error('private_meeting_url') is-invalid @enderror"
                            type="url"
                            name="private_meeting_url"
                            maxlength="2000"
                            pattern="https://.*"
                            placeholder="https://meet.google.com/…"
                            value="{{ old('private_meeting_url', $workshop->private_meeting_url) }}"
                            aria-describedby="meeting-url-help meeting-url-warning"
                            @error('private_meeting_url') aria-invalid="true" @enderror
                        >
                        <small id="meeting-url-help" class="form-text text-muted">Stored privately for HR. It is never shown on the public workshop page.</small>
                        <small id="meeting-url-warning" class="form-text text-warning" data-stale-meeting-warning hidden>An old meeting link is still stored. Clear it if this workshop is now in person.</small>
                        @error('private_meeting_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <details class="workshop-advanced" @if(old('visible_from_at') || $workshop->visible_from_at?->isFuture() || $errors->has('visible_from_at')) open @endif>
                            <summary>Optional: schedule when the page becomes public</summary>
                            <div class="row pt-3">
                                <div class="col-md-6 form-group mb-md-0">
                                    <label for="visible_from_at">Public from</label>
                                    <input
                                        id="visible_from_at"
                                        class="form-control @error('visible_from_at') is-invalid @enderror"
                                        type="datetime-local"
                                        name="visible_from_at"
                                        value="{{ old('visible_from_at', $dateValue($workshop->visible_from_at)) }}"
                                        aria-describedby="visible-from-help"
                                        @error('visible_from_at') aria-invalid="true" @enderror
                                    >
                                    <small id="visible-from-help" class="form-text text-muted">Leave blank to show the page as soon as HR publishes it.</small>
                                    @error('visible_from_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </details>
                    </div>
                </div>
                <div id="workshop-schedule-summary" class="workshop-summary mt-3" role="status" aria-live="polite">
                    Enter the four required dates to check the schedule.
                </div>
            </div>
        </section>

        <section class="card mb-3" aria-labelledby="workshop-registration-title">
            <div class="card-header workshop-section-heading">
                <span class="workshop-step-number" aria-hidden="true">2</span>
                <div>
                    <h2 id="workshop-registration-title" class="h5 mb-1">Registration handling</h2>
                    <small class="text-muted">Choose what happens after someone submits the free form.</small>
                </div>
            </div>
            <div class="card-body">
                @error('workshop')
                    <div class="alert alert-danger" role="alert">{{ $message }}</div>
                @enderror
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="registration_mode">What happens after submission? <span aria-hidden="true">*</span></label>
                        <select
                            id="registration_mode"
                            class="form-control @error('registration_mode') is-invalid @enderror"
                            name="registration_mode"
                            aria-describedby="registration-mode-help"
                            @error('registration_mode') aria-invalid="true" @enderror
                            required
                        >
                            <option value="" disabled @selected($selectedRegistration === '')>Choose a registration decision</option>
                            <option value="automatic" @selected($selectedRegistration === 'automatic')>Confirm immediately</option>
                            <option value="manual" @selected($selectedRegistration === 'manual')>HR reviews each registration</option>
                            <option value="waitlist" @selected($selectedRegistration === 'waitlist')>Confirm until full, then waitlist</option>
                        </select>
                        <small id="registration-mode-help" class="form-text text-muted" data-registration-mode-help>Choose a method to see what it means.</small>
                        @error('registration_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <fieldset class="col-md-6 form-group">
                        <legend class="workshop-field-legend">Participant limit <span aria-hidden="true">*</span></legend>
                        <div class="workshop-choice-row">
                            <div class="custom-control custom-radio">
                                <input id="capacity-unlimited" class="custom-control-input" type="radio" name="capacity_choice" value="unlimited" @checked($capacityChoice === 'unlimited') required>
                                <label class="custom-control-label" for="capacity-unlimited">Unlimited</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input id="capacity-limited" class="custom-control-input" type="radio" name="capacity_choice" value="limited" @checked($capacityChoice === 'limited') required>
                                <label class="custom-control-label" for="capacity-limited">Limit participants</label>
                            </div>
                        </div>
                        @error('capacity_choice')<small class="text-danger d-block mt-2">{{ $message }}</small>@enderror
                        <div class="mt-2" data-capacity-field hidden>
                            <label for="capacity">Maximum participants <span aria-hidden="true">*</span></label>
                            <input
                                id="capacity"
                                class="form-control @error('capacity') is-invalid @enderror"
                                type="number"
                                min="1"
                                max="1000000"
                                name="capacity"
                                value="{{ $capacityValue }}"
                                inputmode="numeric"
                                @error('capacity') aria-invalid="true" @enderror
                            >
                            @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </fieldset>
                    <div class="col-12">
                        @if($editing && $workshop->form && $workshop->currentFormVersion)
                            <div class="workshop-current-form mb-3">
                                <strong>Current registration questions:</strong> {{ $workshop->form->name }}, version {{ $workshop->currentFormVersion->version }}.
                                @if($canManageForms)
                                    <a href="{{ route('workshop.forms.preview', [$workshop->form, 'locale' => 'en']) }}" target="_blank" rel="noopener">Preview questions</a>
                                    <span aria-hidden="true">·</span>
                                    <a href="{{ route('workshop.forms.edit', $workshop->form) }}">Customize a future version</a>
                                @endif
                            </div>
                        @else
                            <div class="workshop-current-form mb-3">
                                <strong>Recommended form:</strong> full name, email address and optional phone number.
                            </div>
                        @endif
                        <details class="workshop-advanced" @if(old('application_form_id') || $errors->has('application_form_id')) open @endif>
                            <summary>Advanced: choose different registration questions</summary>
                            <div class="pt-3">
                                <label for="application_form_id">Registration questions</label>
                                <select id="application_form_id" class="form-control @error('application_form_id') is-invalid @enderror" name="application_form_id" aria-describedby="registration-form-help">
                                    <option value="">
                                        {{ $editing ? 'Keep the current questions' : 'Recommended standard form: name, email and optional phone' }}
                                    </option>
                                    @foreach($forms as $form)
                                        @php
                                            $version = $form->versions->sortByDesc('version')->first();
                                        @endphp
                                        @if($version)
                                            <option value="{{ $form->id }}" @selected((string) old('application_form_id') === (string) $form->id)>
                                                Use {{ $form->name }} · version {{ $version->version }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                <small id="registration-form-help" class="form-text text-muted">An open workshop keeps the same saved questions so applicants are treated consistently.</small>
                                @error('application_form_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        </section>

        @foreach(['en' => 'English', 'bn' => 'Bangla'] as $locale => $language)
            @php
                $step = $locale === 'en' ? 3 : 4;
                $translation = $translations->get($locale);
                $titleKey = "translations.$locale.title";
                $slugKey = "translations.$locale.slug";
                $descriptionKey = "translations.$locale.description";
                $venueKey = "translations.$locale.venue_name";
                $addressKey = "translations.$locale.venue_address";
            @endphp
            <section class="card mb-3" aria-labelledby="workshop-{{ $locale }}-title">
                <div class="card-header workshop-section-heading">
                    <span class="workshop-step-number" aria-hidden="true">{{ $step }}</span>
                    <div>
                        <h2 id="workshop-{{ $locale }}-title" class="h5 mb-1">{{ $language }} public page</h2>
                        <small class="text-muted">Everything here is visible to visitors. Title and description are required.</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 form-group">
                            <label for="{{ $locale }}-title">Workshop title <span aria-hidden="true">*</span></label>
                            <input
                                id="{{ $locale }}-title"
                                class="form-control @error($titleKey) is-invalid @enderror"
                                name="translations[{{ $locale }}][title]"
                                maxlength="255"
                                value="{{ old($titleKey, $translation?->title) }}"
                                lang="{{ $locale }}"
                                @error($titleKey) aria-invalid="true" @enderror
                                required
                            >
                            @error($titleKey)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 form-group">
                            <label for="{{ $locale }}-summary">Short summary <span class="text-muted">(optional)</span></label>
                            <textarea id="{{ $locale }}-summary" class="form-control" name="translations[{{ $locale }}][summary]" rows="2" maxlength="2000" lang="{{ $locale }}" aria-describedby="{{ $locale }}-summary-help">{{ old("translations.$locale.summary", $translation?->summary) }}</textarea>
                            <small id="{{ $locale }}-summary-help" class="form-text text-muted">One or two sentences shown near the title on the workshop detail page.</small>
                        </div>
                        <div class="col-12 form-group">
                            <div class="d-flex flex-wrap align-items-center justify-content-between workshop-editor-label">
                                <label for="{{ $locale }}-description" class="mb-0">Description <span aria-hidden="true">*</span></label>
                                <button class="btn igf-btn igf-btn-secondary igf-btn-compact" type="button" data-insert-workshop-image="{{ $locale }}-description">
                                    <i class="fa fa-image" aria-hidden="true"></i> Add poster or image
                                </button>
                            </div>
                            <small id="{{ $locale }}-description-help" class="form-text text-muted mb-2">Use the button to choose or upload an image—no HTML or source code is needed.</small>
                            <textarea
                                id="{{ $locale }}-description"
                                class="form-control my-editor @error($descriptionKey) is-invalid @enderror"
                                name="translations[{{ $locale }}][description]"
                                rows="6"
                                lang="{{ $locale }}"
                                aria-describedby="{{ $locale }}-description-help"
                                @error($descriptionKey) aria-invalid="true" @enderror
                                required
                            >{{ old($descriptionKey, $translation?->description) }}</textarea>
                            @error($descriptionKey)<small class="text-danger d-block mt-2">{{ $message }}</small>@enderror
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="{{ $locale }}-facilitator">Trainer or facilitator <span class="text-muted">(optional)</span></label>
                            <input id="{{ $locale }}-facilitator" class="form-control" name="translations[{{ $locale }}][facilitator_name]" maxlength="255" value="{{ old("translations.$locale.facilitator_name", $translation?->facilitator_name) }}" lang="{{ $locale }}">
                        </div>
                        <div class="col-md-4 form-group" data-venue-field>
                            <label for="{{ $locale }}-venue"><span data-venue-name-label>Venue or platform name</span> <span data-venue-required-marker aria-hidden="true" hidden>*</span></label>
                            <input
                                id="{{ $locale }}-venue"
                                class="form-control @error($venueKey) is-invalid @enderror"
                                name="translations[{{ $locale }}][venue_name]"
                                maxlength="255"
                                value="{{ old($venueKey, $translation?->venue_name) }}"
                                lang="{{ $locale }}"
                                @error($venueKey) aria-invalid="true" @enderror
                            >
                            @error($venueKey)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 form-group" data-venue-field>
                            <label for="{{ $locale }}-address"><span data-venue-address-label>Venue address or online note</span> <span data-venue-required-marker aria-hidden="true" hidden>*</span></label>
                            <textarea
                                id="{{ $locale }}-address"
                                class="form-control @error($addressKey) is-invalid @enderror"
                                name="translations[{{ $locale }}][venue_address]"
                                rows="2"
                                maxlength="2000"
                                lang="{{ $locale }}"
                                @error($addressKey) aria-invalid="true" @enderror
                            >{{ old($addressKey, $translation?->venue_address) }}</textarea>
                            @error($addressKey)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 form-group">
                            <label for="{{ $locale }}-instructions">Public registration instructions <span class="text-muted">(optional)</span></label>
                            <div class="alert alert-light workshop-public-warning mb-2">
                                <i class="fa fa-eye" aria-hidden="true"></i>
                                Public content: do not paste a private meeting link or applicant information here.
                            </div>
                            <textarea id="{{ $locale }}-instructions" class="form-control my-editor" name="translations[{{ $locale }}][registration_instructions]" rows="4" lang="{{ $locale }}">{{ old("translations.$locale.registration_instructions", $translation?->registration_instructions) }}</textarea>
                        </div>
                        <div class="col-12">
                            <details class="workshop-advanced" @if(old($slugKey) || $errors->has($slugKey)) open @endif>
                                <summary>Advanced: customize the public URL</summary>
                                <div class="row pt-3">
                                    <div class="col-md-6 form-group mb-md-0">
                                        <label for="{{ $locale }}-slug">Public URL name</label>
                                        <input
                                            id="{{ $locale }}-slug"
                                            class="form-control @error($slugKey) is-invalid @enderror"
                                            name="translations[{{ $locale }}][slug]"
                                            maxlength="190"
                                            value="{{ old($slugKey, $translation?->slug) }}"
                                            lang="{{ $locale }}"
                                            aria-describedby="{{ $locale }}-slug-help"
                                            @error($slugKey) aria-invalid="true" @enderror
                                        >
                                        <small id="{{ $locale }}-slug-help" class="form-text text-muted">Usually leave this blank. The system creates it safely from the title.</small>
                                        @error($slugKey)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            </section>
        @endforeach

        <div class="workshop-save-bar mb-4">
            <div>
                @if($isPublished)
                    <strong>Changes will go live immediately.</strong>
                    <small>Review dates, public text and registration settings before saving.</small>
                @else
                    <strong>This saves a private draft.</strong>
                    <small>Nothing becomes public until an authorized user publishes it.</small>
                @endif
            </div>
            <button class="btn igf-btn igf-btn-primary" type="submit" data-cy="save-workshop">
                <i class="fa fa-save" aria-hidden="true"></i>
                {{ $editing ? 'Save workshop' : 'Create free workshop draft' }}
            </button>
        </div>
    </form>
</main>
@endsection

@section('custom-js')
    @include('admin.layouts.tinymce', [
        'editorHeight' => 360,
        'editorMenubar' => false,
        'editorToolbar' => 'undo redo | styleselect | bold italic | bullist numlist | image link | removeformat',
    ])
    <script src="{{ asset('admin-assets/workshop-form/workshop-form.js') }}" defer></script>
@endsection
