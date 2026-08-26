@extends('admin.layouts.master')

@php
    $admin = Auth::guard('admin')->user();
    $permissions = app(\App\Http\Middleware\Permission::class);
    $canCreate = $permissions->allows($admin, 'recruitment.jobs.create');
    $canEdit = $permissions->allows($admin, 'recruitment.jobs.edit');
    $canPublish = $permissions->allows($admin, 'recruitment.jobs.status');
    $canDelete = $permissions->allows($admin, 'recruitment.jobs.destroy');
    $canReview = $permissions->allows($admin, 'recruitment.applications.index');
@endphp

@section('content')
<main class="content pb-0" aria-labelledby="jobs-page-title">
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
        <div>
            <h1 id="jobs-page-title" class="h3 mb-1">Recruitment jobs</h1>
            <p class="text-muted mb-0">Schedule bilingual vacancies, control application windows, and review applicants.</p>
        </div>
        @if($canCreate)
            <a class="btn igf-btn igf-btn-primary mt-2 mt-md-0" href="{{ route('recruitment.jobs.create') }}">
                <i class="fa fa-plus" aria-hidden="true"></i> Create job
            </a>
        @endif
    </div>

    @if($errors->any())
        <div class="alert alert-danger" role="alert"><strong>The request could not be completed.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="card mb-3" aria-label="Filter jobs">
        <div class="card-body">
            <form method="get" action="{{ route('recruitment.jobs.index') }}" class="form-inline">
                <label class="mr-2" for="job-status">Publication state</label>
                <select id="job-status" class="form-control mr-2 mb-2 mb-sm-0" name="status">
                    <option value="">All states</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ Str::headline($status) }}</option>
                    @endforeach
                </select>
                <button class="btn igf-btn igf-btn-secondary mr-2" type="submit">Apply</button>
                <a class="btn igf-btn igf-btn-tertiary" href="{{ route('recruitment.jobs.index') }}">Clear</a>
            </form>
        </div>
    </section>

    <section class="card" aria-labelledby="jobs-list-title">
        <div class="card-header"><strong id="jobs-list-title">{{ $jobs->total() }} {{ Str::plural('job', $jobs->total()) }}</strong></div>
        @if($jobs->isEmpty())
            <div class="card-body text-center py-5">
                <h2 class="h5">No jobs found</h2>
                <p class="text-muted">Create a draft or clear the current filter.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th scope="col">Job</th><th scope="col">Window</th><th scope="col">State</th><th scope="col">Applications</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    @foreach($jobs as $job)
                        @php($english = $job->translations->firstWhere('locale', 'en') ?? $job->translations->first())
                        <tr>
                            <td>
                                <strong>{{ $english?->title ?? 'Untitled job' }}</strong>
                                <small class="d-block text-muted">{{ $english?->department }}@if($english?->location) · {{ $english->location }}@endif</small>
                            </td>
                            <td>
                                <small class="d-block">Opens <time datetime="{{ $job->application_opens_at?->toAtomString() }}">{{ $job->application_opens_at?->format('d M Y, H:i') }}</time></small>
                                <small class="d-block">Closes <time datetime="{{ $job->application_closes_at?->toAtomString() }}">{{ $job->application_closes_at?->format('d M Y, H:i') }}</time></small>
                            </td>
                            <td><span class="badge badge-{{ $job->publication_status === 'published' ? 'success' : ($job->publication_status === 'draft' ? 'warning' : 'secondary') }}">{{ Str::headline($job->publication_status) }}</span></td>
                            <td>@if($canReview)<a href="{{ route('recruitment.applications.index', ['listing' => $job->uuid]) }}">{{ $job->applications_count }}</a>@else{{ $job->applications_count }}@endif</td>
                            <td>
                                <div class="d-flex flex-wrap justify-content-end" style="gap:.35rem">
                                    @if($canEdit)<a class="btn igf-btn igf-btn-secondary igf-btn-compact" href="{{ route('recruitment.jobs.edit', $job) }}">Edit</a>@endif
                                    @if($canPublish && $job->publication_status !== 'withdrawn')
                                        <form method="post" action="{{ route('recruitment.jobs.status', $job) }}">@csrf @method('patch')
                                            <input type="hidden" name="editor_version" value="{{ $job->editor_version }}">
                                            <input type="hidden" name="action" value="{{ $job->publication_status === 'draft' ? 'publish' : 'close' }}">
                                            <button class="btn igf-btn igf-btn-tertiary igf-btn-compact" type="submit">{{ $job->publication_status === 'draft' ? 'Publish' : 'Close now' }}</button>
                                        </form>
                                        <form method="post" action="{{ route('recruitment.jobs.status', $job) }}" onsubmit="return confirm('Withdraw this job from all public pages? Its applications will remain private and available to authorized staff.')">@csrf @method('patch')
                                            <input type="hidden" name="action" value="withdraw">
                                            <button class="btn btn-outline-secondary btn-sm" type="submit">Withdraw</button>
                                        </form>
                                    @endif
                                    @if($canCreate)
                                        <form method="post" action="{{ route('recruitment.jobs.duplicate', $job) }}">@csrf<button class="btn igf-btn igf-btn-tertiary igf-btn-compact" type="submit">Duplicate</button></form>
                                    @endif
                                    @if($canDelete && $job->publication_status === 'draft' && $job->applications_count === 0)
                                        <form method="post" action="{{ route('recruitment.jobs.destroy', $job) }}" onsubmit="return confirm('Delete this unused draft?')">@csrf @method('delete')<button class="btn btn-outline-danger btn-sm" type="submit">Delete</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $jobs->links('vendor.pagination.bootstrap-4') }}</div>
        @endif
    </section>
</main>
@endsection
