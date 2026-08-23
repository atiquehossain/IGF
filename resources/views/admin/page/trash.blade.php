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
            <a class="btn igf-btn igf-btn-secondary" href="{{ $canViewPages ? route('page.index') : route('dashboard.index') }}"><i class="fa fa-arrow-left" aria-hidden="true"></i> {{ $canViewPages ? 'Back to Content Hub' : 'Back to dashboard' }}</a>
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
            <div class="table-responsive" tabindex="0" role="region" aria-label="Deleted pages">
                <table class="table" id="page-trash-table">
                    <thead><tr><th>Page</th><th>Slug</th><th>Deleted</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($pages as $page)
                            <tr id="trash-{{ $page->uuid }}">
                                <td>{{ $page->name }}</td>
                                <td>{{ $page->slug }}</td>
                                <td>{{ $page->deleted_at?->format('M j, Y g:i A') }}</td>
                                <td class="text-right">
                                    @if($canRestorePages)<button type="button" class="btn btn-sm btn-success restore-page" data-url="{{ route('page.trash.restore', $page->uuid) }}">Restore</button>@endif
                                    @if($canPermanentlyDeletePages)<button type="button" class="btn btn-sm btn-danger force-delete-page" data-url="{{ route('page.trash.force-destroy', $page->uuid) }}">Delete permanently</button>@endif
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
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function jsonPayload(response) {
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            if (response.redirected || [401, 419].includes(response.status)) {
                throw new Error('Your admin session has expired. Reload the page, sign in, and try again.');
            }
            if (response.status === 403) {
                throw new Error('You no longer have permission to perform this action. Reload the page to refresh your access.');
            }
            throw new Error('The server returned an unexpected response. Reload the page and try again.');
        }

        try {
            return await response.json();
        } catch (error) {
            throw new Error('The server response could not be read. Please try again.');
        }
    }

    async function act(button, method, confirmMessage) {
        if (button.disabled || (confirmMessage && !window.confirm(confirmMessage))) return;

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        setAdminBusy(true);

        try {
            const response = await fetch(button.dataset.url, {
                method,
                credentials: 'same-origin',
                headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':token}
            });
            const payload = await jsonPayload(response);
            if (!response.ok) {
                throw new Error(payload.message || 'The action could not be completed.');
            }
            toastrMsg('success', payload.message || 'The page trash was updated.');
            const row = button.closest('tr');
            const nextRowAction = row?.nextElementSibling?.querySelector('button, a');
            row?.remove();
            const tableRegion = document.getElementById('page-trash-table')?.closest('.table-responsive');
            (nextRowAction || tableRegion)?.focus();
        } catch (error) {
            toastrMsg('error', error.message || 'The action could not be completed. Check your connection and try again.');
        } finally {
            if (document.contains(button)) {
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }
            setAdminBusy(false);
        }
    }
    document.querySelectorAll('.restore-page').forEach(button => button.addEventListener('click', () => act(button, 'POST')));
    document.querySelectorAll('.force-delete-page').forEach(button => button.addEventListener('click', () => act(button, 'DELETE', 'Permanently delete this page and all associated data? This cannot be undone.')));
})();
</script>
@endsection
