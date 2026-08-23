@extends('admin.layouts.master')

@php
    $admin = auth('admin')->user();
    $permissions = app(\App\Http\Middleware\Permission::class);
    $canViewPages = $permissions->allows($admin, 'page.index');
    $canRestoreContent = $permissions->allows($admin, 'content.trash.edit');
    $canPermanentlyDeleteContent = $permissions->allows($admin, 'content.trash.destroy');
    $screenIsReadOnly = !$canRestoreContent && !$canPermanentlyDeleteContent;
@endphp

@section('content')
<div class="content pb-0">
    <h1 class="sr-only">Content trash</h1>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Content trash</strong>
            <a class="btn igf-btn igf-btn-secondary" href="{{ $canViewPages ? route('page.index') : route('dashboard.index') }}"><i class="fa fa-arrow-left" aria-hidden="true"></i> {{ $canViewPages ? 'Back to Content Hub' : 'Back to dashboard' }}</a>
        </div>
        <div class="card-body">
            <p class="text-muted">Editorial items are recoverable here. Their media and SEO settings stay attached until an owner deletes them permanently.</p>
            @if($screenIsReadOnly)
                <div class="alert alert-info" role="status"><strong>Read-only access.</strong> You can filter and review deleted content and retention notes, but your role cannot restore or permanently delete content.</div>
            @endif
            <form class="form-row mb-3" method="get">
                <div class="col-md-6 mb-2"><label class="sr-only" for="trash-search">Search trash</label><input id="trash-search" class="form-control" type="search" name="search" value="{{ $search }}" placeholder="Search trash"></div>
                <div class="col-md-4 mb-2"><label class="sr-only" for="trash-type">Content type</label><select id="trash-type" class="form-control" name="type"><option value="">All content types</option>@foreach($types as $value => $label)<option value="{{ $value }}" @selected($typeFilter === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-2 mb-2"><button class="btn btn-info btn-block" type="submit">Filter</button></div>
            </form>
            <div class="table-responsive" tabindex="0" role="region" aria-label="Deleted content">
                <table class="table" id="content-trash-table">
                    <thead><tr><th>Type</th><th>Content</th><th>Locale / slug</th><th>Deleted</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>{{ $item->type_label }}</td>
                                <td>{{ $item->title }} @if($item->retention_note)<small class="d-block text-warning">{{ $item->retention_note }}</small>@endif</td>
                                <td>{{ $item->detail ?: '—' }}</td>
                                <td>{{ $item->deleted_at?->format('M j, Y g:i A') }}</td>
                                <td class="text-right text-nowrap">
                                    @if($canRestoreContent)<button type="button" class="btn btn-sm btn-success trash-action" data-method="POST" data-url="{{ route('content.trash.restore', [$item->type, $item->id]) }}">Restore</button>@endif
                                    @if($canPermanentlyDeleteContent)
                                        @if($item->can_force_delete)
                                            <button type="button" class="btn btn-sm btn-danger trash-action" data-method="DELETE" data-confirm="Permanently delete this content and its SEO/media? This cannot be undone." data-url="{{ route('content.trash.force-destroy', [$item->type, $item->id]) }}">Delete permanently</button>
                                        @else
                                            <button class="btn btn-sm btn-danger" type="button" disabled title="{{ $item->retention_note }}">Retained for records</button>
                                        @endif
                                    @endif
                                    @if($screenIsReadOnly)<span class="badge badge-light">View only</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">The content trash is empty.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $items->links('vendor.pagination.bootstrap-4') }}
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

    document.querySelectorAll('.trash-action').forEach(button => button.addEventListener('click', async () => {
        if (button.disabled || (button.dataset.confirm && !window.confirm(button.dataset.confirm))) return;

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        setAdminBusy(true);

        try {
            const response = await fetch(button.dataset.url, {
                method: button.dataset.method,
                credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token}
            });
            const payload = await jsonPayload(response);
            if (!response.ok) {
                throw new Error(payload.message || 'The action could not be completed.');
            }
            toastrMsg('success', payload.message || 'Content trash was updated.');
            const row = button.closest('tr');
            const nextRowAction = row?.nextElementSibling?.querySelector('button, a');
            row?.remove();
            const tableRegion = document.getElementById('content-trash-table')?.closest('.table-responsive');
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
    }));
})();
</script>
@endsection
