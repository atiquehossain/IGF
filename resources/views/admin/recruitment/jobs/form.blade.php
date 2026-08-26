@extends('admin.layouts.master')

@php
    $editing = $job->exists;
    $translations = $job->translations->keyBy('locale');
    $dateValue = static fn ($value) => $value?->format('Y-m-d\TH:i');
    $scorecardCriteria = old('scorecard_criteria');
    if ($scorecardCriteria === null) {
        $scorecardCriteria = $job->scorecardCriteria->map(fn ($criterion) => [
            'uuid' => $criterion->uuid,
            'label' => $criterion->label,
            'description' => $criterion->description,
            'maximum_score' => $criterion->maximum_score,
            'is_enabled' => $criterion->is_enabled,
        ])->values()->all();
    }
@endphp

@section('content')
<main class="content pb-0" aria-labelledby="job-form-title">
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
        <div><h1 id="job-form-title" class="h3 mb-1">{{ $title }}</h1><p class="text-muted mb-0">Both languages and a valid application window are required.</p></div>
        <a class="btn igf-btn igf-btn-secondary" href="{{ route('recruitment.jobs.index') }}"><i class="fa fa-arrow-left" aria-hidden="true"></i> Jobs</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" role="alert" tabindex="-1"><strong>Please correct the highlighted information.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ $editing ? route('recruitment.jobs.update', $job) : route('recruitment.jobs.store') }}">
        @csrf
        @if($editing) @method('put') <input type="hidden" name="editor_version" value="{{ $job->editor_version }}"> @endif

        <section class="card mb-3" aria-labelledby="job-settings-title">
            <div class="card-header"><strong id="job-settings-title">Job settings</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 form-group"><label for="visible_from_at">Public from</label><input id="visible_from_at" class="form-control" type="datetime-local" name="visible_from_at" value="{{ old('visible_from_at', $dateValue($job->visible_from_at)) }}"><small class="form-text text-muted">Publishing uses the current time when left blank.</small></div>
                    <div class="col-md-4 form-group"><label for="application_opens_at">Applications open <span aria-hidden="true">*</span></label><input id="application_opens_at" class="form-control" type="datetime-local" name="application_opens_at" value="{{ old('application_opens_at', $dateValue($job->application_opens_at)) }}" required></div>
                    <div class="col-md-4 form-group"><label for="application_closes_at">Applications close <span aria-hidden="true">*</span></label><input id="application_closes_at" class="form-control" type="datetime-local" name="application_closes_at" value="{{ old('application_closes_at', $dateValue($job->application_closes_at)) }}" required></div>
                    <div class="col-md-4 form-group"><label for="employment_type">Employment type</label><select id="employment_type" class="form-control" name="employment_type" required>@foreach(\App\Models\JobPosting::EMPLOYMENT_TYPES as $value)<option value="{{ $value }}" @selected(old('employment_type', $job->employment_type ?? 'full_time') === $value)>{{ Str::headline($value) }}</option>@endforeach</select></div>
                    <div class="col-md-4 form-group"><label for="work_arrangement">Work arrangement</label><select id="work_arrangement" class="form-control" name="work_arrangement" required>@foreach(\App\Models\JobPosting::WORK_ARRANGEMENTS as $value)<option value="{{ $value }}" @selected(old('work_arrangement', $job->work_arrangement ?? 'on_site') === $value)>{{ Str::headline($value) }}</option>@endforeach</select></div>
                    <div class="col-md-4 form-group"><label for="vacancy_count">Vacancies</label><input id="vacancy_count" class="form-control" type="number" min="1" max="10000" name="vacancy_count" value="{{ old('vacancy_count', $job->vacancy_count ?? 1) }}" required></div>
                    <div class="col-md-12 form-group mb-0">
                        <label for="application_form_id">Application form</label>
                        <select id="application_form_id" class="form-control" name="application_form_id" aria-describedby="form-help">
                            <option value="">{{ $editing ? 'Keep the currently pinned form version' : 'Create the secure default job application form' }}</option>
                            @foreach($forms as $form)
                                @php($version = $form->versions->sortByDesc('version')->first())
                                @if($version)<option value="{{ $form->id }}" @selected((string) old('application_form_id') === (string) $form->id)>{{ $form->name }} · published v{{ $version->version }}</option>@endif
                            @endforeach
                        </select>
                        <small id="form-help" class="form-text text-muted">Listings pin an immutable published version. CV remains mandatory, PDF-only, and at most 5 MB.</small>
                    </div>
                </div>
            </div>
        </section>

        <section class="card mb-3" aria-labelledby="job-scorecard-title">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <div><strong id="job-scorecard-title">Application scorecard</strong><small class="d-block text-muted">Optional criteria available to authorized reviewers.</small></div>
                <button id="add-scorecard-criterion" class="btn igf-btn igf-btn-secondary igf-btn-compact" type="button">Add criterion</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th scope="col">Criterion</th><th scope="col">Guidance</th><th scope="col">Maximum</th><th scope="col">Enabled</th><th scope="col"><span class="sr-only">Remove</span></th></tr></thead>
                        <tbody id="scorecard-criteria" data-next-index="{{ count($scorecardCriteria) }}">
                        @forelse($scorecardCriteria as $index => $criterion)
                            <tr data-scorecard-row>
                                <td>
                                    <input type="hidden" name="scorecard_criteria[{{ $index }}][uuid]" value="{{ $criterion['uuid'] ?? '' }}">
                                    <label class="sr-only" for="criterion-{{ $index }}-label">Criterion label</label>
                                    <input id="criterion-{{ $index }}-label" class="form-control" name="scorecard_criteria[{{ $index }}][label]" maxlength="255" value="{{ $criterion['label'] ?? '' }}" required>
                                </td>
                                <td><label class="sr-only" for="criterion-{{ $index }}-description">Scoring guidance</label><textarea id="criterion-{{ $index }}-description" class="form-control" name="scorecard_criteria[{{ $index }}][description]" rows="2" maxlength="2000">{{ $criterion['description'] ?? '' }}</textarea></td>
                                <td><label class="sr-only" for="criterion-{{ $index }}-maximum">Maximum score</label><input id="criterion-{{ $index }}-maximum" class="form-control" type="number" min="0.01" max="1000" step="0.01" name="scorecard_criteria[{{ $index }}][maximum_score]" value="{{ $criterion['maximum_score'] ?? 10 }}" required></td>
                                <td class="text-center"><input type="hidden" name="scorecard_criteria[{{ $index }}][is_enabled]" value="0"><input id="criterion-{{ $index }}-enabled" type="checkbox" name="scorecard_criteria[{{ $index }}][is_enabled]" value="1" @checked(filter_var($criterion['is_enabled'] ?? true, FILTER_VALIDATE_BOOL))><label class="sr-only" for="criterion-{{ $index }}-enabled">Enable criterion</label></td>
                                <td><button class="btn btn-outline-danger btn-sm" type="button" data-remove-scorecard>Remove</button></td>
                            </tr>
                        @empty
                            <tr data-scorecard-empty><td colspan="5" class="text-center text-muted">No scorecard criteria. Reviewers can still use workflow statuses and private notes.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="form-text text-muted mb-0 mt-2">Removing a criterion disables it without deleting historical scores. Existing score snapshots remain unchanged.</p>
            </div>
        </section>

        <template id="scorecard-criterion-template">
            <tr data-scorecard-row>
                <td><input type="hidden" name="scorecard_criteria[__INDEX__][uuid]" value=""><label class="sr-only" for="criterion-__INDEX__-label">Criterion label</label><input id="criterion-__INDEX__-label" class="form-control" name="scorecard_criteria[__INDEX__][label]" maxlength="255" required></td>
                <td><label class="sr-only" for="criterion-__INDEX__-description">Scoring guidance</label><textarea id="criterion-__INDEX__-description" class="form-control" name="scorecard_criteria[__INDEX__][description]" rows="2" maxlength="2000"></textarea></td>
                <td><label class="sr-only" for="criterion-__INDEX__-maximum">Maximum score</label><input id="criterion-__INDEX__-maximum" class="form-control" type="number" min="0.01" max="1000" step="0.01" name="scorecard_criteria[__INDEX__][maximum_score]" value="10" required></td>
                <td class="text-center"><input type="hidden" name="scorecard_criteria[__INDEX__][is_enabled]" value="0"><input id="criterion-__INDEX__-enabled" type="checkbox" name="scorecard_criteria[__INDEX__][is_enabled]" value="1" checked><label class="sr-only" for="criterion-__INDEX__-enabled">Enable criterion</label></td>
                <td><button class="btn btn-outline-danger btn-sm" type="button" data-remove-scorecard>Remove</button></td>
            </tr>
        </template>

        @foreach(['en' => 'English', 'bn' => 'Bangla'] as $locale => $language)
            @php($translation = $translations->get($locale))
            <section class="card mb-3" aria-labelledby="job-{{ $locale }}-title">
                <div class="card-header"><strong id="job-{{ $locale }}-title">{{ $language }} public content</strong></div>
                <div class="card-body"><div class="row">
                    <div class="col-md-8 form-group"><label for="{{ $locale }}-title">Title</label><input id="{{ $locale }}-title" class="form-control" name="translations[{{ $locale }}][title]" maxlength="255" value="{{ old("translations.$locale.title", $translation?->title) }}" lang="{{ $locale }}" required></div>
                    <div class="col-md-4 form-group"><label for="{{ $locale }}-slug">Public URL slug</label><input id="{{ $locale }}-slug" class="form-control" name="translations[{{ $locale }}][slug]" maxlength="190" value="{{ old("translations.$locale.slug", $translation?->slug) }}" lang="{{ $locale }}"><small class="form-text text-muted">Leave blank to generate it from the title.</small></div>
                    <div class="col-md-6 form-group"><label for="{{ $locale }}-department">Department</label><input id="{{ $locale }}-department" class="form-control" name="translations[{{ $locale }}][department]" maxlength="150" value="{{ old("translations.$locale.department", $translation?->department) }}" lang="{{ $locale }}" required></div>
                    <div class="col-md-6 form-group"><label for="{{ $locale }}-location">Location</label><input id="{{ $locale }}-location" class="form-control" name="translations[{{ $locale }}][location]" maxlength="255" value="{{ old("translations.$locale.location", $translation?->location) }}" lang="{{ $locale }}" required></div>
                    <div class="col-12 form-group"><label for="{{ $locale }}-summary">Summary</label><textarea id="{{ $locale }}-summary" class="form-control" name="translations[{{ $locale }}][summary]" rows="2" maxlength="2000" lang="{{ $locale }}">{{ old("translations.$locale.summary", $translation?->summary) }}</textarea></div>
                    <div class="col-12 form-group"><label for="{{ $locale }}-description">Description</label><textarea id="{{ $locale }}-description" class="form-control my-editor" name="translations[{{ $locale }}][description]" rows="6" lang="{{ $locale }}" required>{{ old("translations.$locale.description", $translation?->description) }}</textarea></div>
                    <div class="col-md-6 form-group"><label for="{{ $locale }}-responsibilities">Responsibilities</label><textarea id="{{ $locale }}-responsibilities" class="form-control my-editor" name="translations[{{ $locale }}][responsibilities]" rows="6" lang="{{ $locale }}">{{ old("translations.$locale.responsibilities", $translation?->responsibilities) }}</textarea></div>
                    <div class="col-md-6 form-group"><label for="{{ $locale }}-requirements">Requirements</label><textarea id="{{ $locale }}-requirements" class="form-control my-editor" name="translations[{{ $locale }}][requirements]" rows="6" lang="{{ $locale }}" required>{{ old("translations.$locale.requirements", $translation?->requirements) }}</textarea></div>
                </div></div>
            </section>
        @endforeach

        <div class="d-flex justify-content-end mb-4"><button class="btn igf-btn igf-btn-primary" type="submit"><i class="fa fa-save" aria-hidden="true"></i> {{ $editing ? 'Save job' : 'Create draft' }}</button></div>
    </form>
</main>
@endsection

@section('custom-js')
    @include('admin.layouts.tinymce')
    <script src="{{ asset('admin-assets/job-scorecard/job-scorecard.js') }}" defer></script>
@endsection
