<link rel="stylesheet" href="{{ asset('admin-assets/application-form-builder/form-builder.css') }}">

<main class="afb-page afb-page--narrow" aria-labelledby="afb-create-title">
    <nav class="afb-breadcrumb" aria-label="Breadcrumb"><a href="{{ route($routeNames['index']) }}">Forms and templates</a><span aria-hidden="true">/</span><span>Create</span></nav>
    <section class="card afb-create-card">
        <div class="card-header">
            <p class="afb-eyebrow">{{ $sectionLabel }}</p>
            <h1 id="afb-create-title">Create a form</h1>
            <p>Begin with protected identity fields, or copy a reusable template.</p>
        </div>
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger" role="alert"><strong>Check the form details.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="post" action="{{ route($routeNames['store']) }}" class="afb-create-form">
                @csrf
                <label>
                    <span>Form name <b aria-hidden="true">*</b></span>
                    <input class="form-control" name="name" maxlength="150" required value="{{ old('name') }}" autocomplete="off" aria-describedby="afb-name-help">
                    <small id="afb-name-help">Use an internal name staff will recognize. Applicants do not see it.</small>
                </label>
                <label>
                    <span>Start from</span>
                    <select class="form-control" name="template_uuid">
                        <option value="">Blank {{ strtolower($sectionLabel) }} form</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->uuid }}" @selected(old('template_uuid') === $template->uuid)>{{ $template->name }}</option>
                        @endforeach
                    </select>
                    <small>A template is copied into a separate editable draft.</small>
                </label>
                <input type="hidden" name="is_template" value="0">
                <label class="afb-check afb-check--card">
                    <input type="checkbox" name="is_template" value="1" @checked(old('is_template'))>
                    <span><strong>Save as a reusable template</strong><small>Templates can be duplicated when staff create future forms.</small></span>
                </label>
                <div class="afb-form-actions">
                    <a class="btn igf-btn igf-btn-secondary" href="{{ route($routeNames['index']) }}">Cancel</a>
                    <button class="btn igf-btn igf-btn-primary" type="submit"><i class="fa fa-arrow-right" aria-hidden="true"></i> Create and open builder</button>
                </div>
            </form>
        </div>
    </section>
</main>
