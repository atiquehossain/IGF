@extends('admin.layouts.master')

@php
    $admin = auth('admin')->user();
    $permissions = app(\App\Http\Middleware\Permission::class);
    $canRestoreReusableBlocks = $permissions->allows($admin, 'reusable-blocks.edit');
    $canDeleteReusableBlocks = $permissions->allows($admin, 'reusable-blocks.destroy');
    $screenIsReadOnly = $isTrash
        ? !$canRestoreReusableBlocks && !$canDeleteReusableBlocks
        : !$canDeleteReusableBlocks;
@endphp

@section('content')
<style>
    .igf-reuse{--orange:#ff7500;--ink:#191c1d;max-width:1260px;margin:28px auto;padding:0 22px;color:var(--ink)}.igf-reuse h1{margin:0;font:700 40px Georgia,serif}.igf-reuse__head{display:flex;justify-content:space-between;align-items:end;margin-bottom:22px}.igf-reuse__head p{color:#6a6865}.igf-btn{display:inline-flex;padding:9px 14px;border:1px solid #ded8d2;border-radius:8px;background:#fff;color:var(--ink);font-weight:700;text-decoration:none;cursor:pointer}.igf-btn--danger{color:#a32920}.igf-toolbar{display:flex;gap:10px;margin-bottom:18px}.igf-toolbar input{min-width:280px;padding:10px;border:1px solid #ded8d2;border-radius:8px}.igf-table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border:1px solid #e7e2dc;border-radius:12px;background:#fff}.igf-table th,.igf-table td{padding:14px;border-bottom:1px solid #eeeae6;text-align:left}.igf-table th{font-size:12px;text-transform:uppercase;letter-spacing:.05em;background:#faf9f7}.igf-table tr:last-child td{border-bottom:0}.igf-badge{display:inline-flex;padding:4px 8px;border-radius:999px;background:#fff0e6;color:#873b0b;font-size:12px;font-weight:800}.igf-actions{display:flex;align-items:center;gap:8px}.igf-actions form{margin:0}.igf-read-only{margin:0 0 18px;padding:14px 16px;border:1px solid #d8e3ef;border-radius:10px;background:#f4f8fc;color:#30475e}.igf-read-only strong{display:block;margin-bottom:2px}.igf-view-only{color:#777;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}@media(max-width:700px){.igf-reuse__head{align-items:start;flex-direction:column}.igf-table{display:block;overflow:auto}}
</style>
<main class="igf-reuse">
    <header class="igf-reuse__head"><div><h1>{{ $isTrash ? 'Reusable section trash' : 'Reusable sections' }}</h1><p>One synchronized section can appear on any number of pages.</p></div><a class="igf-btn" href="{{ route('reusable-blocks.index', $isTrash ? [] : ['trash' => 1]) }}">{{ $isTrash ? 'Back to library' : 'View trash' }}</a></header>
    @if($screenIsReadOnly)
        <div class="igf-read-only" role="status"><strong>Read-only access</strong><span>You can search and review section types, languages, usage counts, and status, but your role cannot {{ $isTrash ? 'restore or permanently delete these sections' : 'move these sections to the trash' }}.</span></div>
    @endif
    <form class="igf-toolbar" method="GET"><input name="search" value="{{ $search }}" placeholder="Search reusable sections">@if($isTrash)<input type="hidden" name="trash" value="1">@endif<button class="igf-btn">Search</button></form>
    <table class="igf-table">
        <thead><tr><th>Name</th><th>Type</th><th>Language</th><th>Instances</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($blocks as $block)
            <tr><td><strong>{{ $block->name }}</strong></td><td>{{ $blockTypes[$block->type] ?? $block->type }}</td><td>{{ $block->locale === '*' ? 'All languages' : strtoupper($block->locale) }}</td><td>{{ $block->instances_count }}</td><td><span class="igf-badge">{{ $block->is_enabled ? 'Active' : 'Disabled' }}</span></td><td><div class="igf-actions">
                @if($isTrash)
                    @if($canRestoreReusableBlocks)<form method="POST" action="{{ route('reusable-blocks.restore', $block->uuid) }}">@csrf<input type="hidden" name="expected_version" value="{{ (int) $block->editor_version }}"><button class="igf-btn">Restore</button></form>@endif
                    @if($canDeleteReusableBlocks)<form method="POST" action="{{ route('reusable-blocks.force-destroy', $block->uuid) }}" onsubmit="return confirm('Permanently delete this section?')">@csrf @method('DELETE')<input type="hidden" name="expected_version" value="{{ (int) $block->editor_version }}"><button class="igf-btn igf-btn--danger">Delete forever</button></form>@endif
                    @if(!$canRestoreReusableBlocks && !$canDeleteReusableBlocks)<span class="igf-view-only">View only</span>@endif
                @else
                    @if($canDeleteReusableBlocks)
                        <form method="POST" action="{{ route('reusable-blocks.destroy', $block) }}" onsubmit="return confirm('Move this reusable section to trash? Every page instance will retain a detached copy.')">@csrf @method('DELETE')<input type="hidden" name="expected_version" value="{{ (int) $block->editor_version }}"><button class="igf-btn igf-btn--danger">Trash safely</button></form>
                    @else
                        <span class="igf-view-only">View only</span>
                    @endif
                @endif
            </div></td></tr>
        @empty<tr><td colspan="6" style="padding:40px;text-align:center;color:#777">No reusable sections found.</td></tr>@endforelse
        </tbody>
    </table>
    <div style="margin-top:22px">{{ $blocks->links() }}</div>
</main>
@endsection
