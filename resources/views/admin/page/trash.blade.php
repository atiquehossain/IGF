@extends('admin.layouts.master')

@php
    $admin = auth('admin')->user();
    $permissions = app(\App\Http\Middleware\Permission::class);
    $canViewPages = $permissions->allows($admin, 'page.index');
    $canRestorePages = $permissions->allows($admin, 'page.trash.edit');
    $canPermanentlyDeletePages = $permissions->allows($admin, 'page.trash.destroy');
    $screenIsReadOnly = !$canRestorePages && !$canPermanentlyDeletePages;
@endphp

@section('content')
<div class="content pb-0">
    <h1 class="sr-only">Page trash</h1>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Page trash</strong>
            <a class="btn btn-sm btn-secondary" href="{{ $canViewPages ? route('page.index') : route('dashboard.index') }}"><i class="fa fa-arrow-left"></i> {{ $canViewPages ? 'Back to pages' : 'Back to dashboard' }}</a>
        </div>
        <div class="card-body">
            <p class="text-muted">Deleted pages remain recoverable here. Permanent deletion also removes their blocks, revisions, and SEO metadata and cannot be undone.</p>
            @if($screenIsReadOnly)
                <div class="alert alert-info" role="status"><strong>Read-only access.</strong> You can search and review deleted page details, but your role cannot restore or permanently delete pages.</div>
            @endif
            <form class="mb-3" method="get">
                <div class="input-group">
                    <label class="sr-only" for="page-trash-search">Search page trash</label>
                    <input id="page-trash-search" class="form-control" type="search" name="search" value="{{ $search }}" placeholder="Search trash">
                    <div class="input-group-append"><button class="btn btn-info" type="submit">Search</button></div>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table" id="page-trash-table">
                    <thead><tr><th>Page</th><th>Slug</th><th>Deleted</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($pages as $page)
                            <tr id="trash-{{ $page->uuid }}">
                                <td>{{ $page->name }}</td>
                                <td>{{ $page->slug }}</td>
                                <td>{{ $page->deleted_at?->format('M j, Y g:i A') }}</td>
                                <td class="text-right">
                                    @if($canRestorePages)<button class="btn btn-sm btn-success restore-page" data-url="{{ route('page.trash.restore', $page->uuid) }}">Restore</button>@endif
                                    @if($canPermanentlyDeletePages)<button class="btn btn-sm btn-danger force-delete-page" data-url="{{ route('page.trash.force-destroy', $page->uuid) }}">Delete permanently</button>@endif
                                    @if($screenIsReadOnly)<span class="badge badge-light">View only</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">The page trash is empty.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $pages->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
        </div>
    </div>
</div>
@endsection

@section('custom-js')
<script>
(() => {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    async function act(button, method, confirmMessage) {
        if (confirmMessage && !confirm(confirmMessage)) return;
        const response = await fetch(button.dataset.url, {method, headers:{'Accept':'application/json','X-CSRF-TOKEN':token}});
        const payload = await response.json();
        if (!response.ok) { toastrMsg('error', payload.message); return; }
        toastrMsg('success', payload.message);
        button.closest('tr').remove();
    }
    document.querySelectorAll('.restore-page').forEach(button => button.addEventListener('click', () => act(button, 'POST')));
    document.querySelectorAll('.force-delete-page').forEach(button => button.addEventListener('click', () => act(button, 'DELETE', 'Permanently delete this page and all associated data? This cannot be undone.')));
})();
</script>
@endsection
