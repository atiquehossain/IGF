<link rel="stylesheet" href="{{ asset('admin-assets/application-form-builder/form-builder.css') }}">

@php
    $ui = static fn (string $key, array $replace = []): string => \App\Support\AdminUi::text("application_forms.{$key}", $replace);
@endphp

<main class="afb-page afb-page--narrow" aria-labelledby="afb-create-title">
    <nav class="afb-breadcrumb" aria-label="{{ $ui('create.breadcrumb') }}"><a href="{{ route($routeNames['index']) }}">{{ $ui('create.all_forms') }}</a><span aria-hidden="true">/</span><span>{{ $ui('create.create') }}</span></nav>
    <section class="card afb-create-card">
        <div class="card-header">
            <p class="afb-eyebrow">{{ $sectionLabel }}</p>
            <h1 id="afb-create-title">{{ $ui('create.title') }}</h1>
            <p>{{ $ui('create.intro') }}</p>
        </div>
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger" role="alert"><strong>{{ $ui('create.validation_title') }}</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="post" action="{{ route($routeNames['store']) }}" class="afb-create-form">
                @csrf
                <label>
                    <span>{{ $ui('create.name') }} <b aria-hidden="true">*</b></span>
                    <input class="form-control" name="name" maxlength="150" required value="{{ old('name') }}" autocomplete="off" aria-describedby="afb-name-help">
                    <small id="afb-name-help">{{ $ui('create.name_help') }}</small>
                </label>
                <label>
                    <span>{{ $ui('create.start_from') }}</span>
                    <select class="form-control" name="template_uuid">
                        <option value="">{{ $ui('create.blank_form', ['area' => $sectionLabel]) }}</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->uuid }}" @selected(old('template_uuid') === $template->uuid)>{{ $template->name }}</option>
                        @endforeach
                    </select>
                    <small>{{ $ui('create.template_help') }}</small>
                </label>
                <input type="hidden" name="is_template" value="0">
                <label class="afb-check afb-check--card">
                    <input type="checkbox" name="is_template" value="1" @checked(old('is_template'))>
                    <span><strong>{{ $ui('create.template_label') }}</strong><small>{{ $ui('create.template_description') }}</small></span>
                </label>
                <div class="afb-form-actions">
                    <a class="btn igf-btn igf-btn-secondary" href="{{ route($routeNames['index']) }}">{{ $ui('create.cancel') }}</a>
                    <button class="btn igf-btn igf-btn-primary" type="submit"><i class="fa fa-arrow-right" aria-hidden="true"></i> {{ $ui('create.submit') }}</button>
                </div>
            </form>
        </div>
    </section>
</main>
