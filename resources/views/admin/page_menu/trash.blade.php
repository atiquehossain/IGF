@extends('admin.layouts.master')

@section('content')
@php
    $admin = auth('admin')->user();
    $permissions = app(\App\Http\Middleware\Permission::class);
    $canViewNavigation = $permissions->allows($admin, 'page.menu.index');
    $canRestoreMenu = $permissions->allows($admin, 'page.menu.restore');
    $canPermanentlyDeleteMenu = $permissions->allows($admin, 'page.menu.force-destroy');
@endphp
<style>
    .igf-trash{max-width:1100px;margin:28px auto;padding:0 22px}.igf-trash__head{display:flex;justify-content:space-between;align-items:end;margin-bottom:22px}.igf-trash h1{margin:0;font:700 38px Georgia,serif}.igf-trash p{color:#6e6965}.igf-btn{display:inline-flex;padding:9px 14px;border:1px solid #ddd5ce;border-radius:8px;background:#fff;color:#202020;font-weight:700;text-decoration:none;cursor:pointer}.igf-btn--danger{color:#a32920}.igf-search{display:flex;gap:10px;margin-bottom:18px}.igf-search input{min-width:280px;padding:10px;border:1px solid #ddd5ce;border-radius:8px}.igf-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e8e2dc}.igf-table th,.igf-table td{padding:14px;border-bottom:1px solid #eee;text-align:left}.igf-actions{display:flex;gap:8px}.igf-actions form{margin:0}
</style>
<main class="igf-trash">
    <header class="igf-trash__head"><div><h1>Navigation trash</h1><p>@if($canRestoreMenu || $canPermanentlyDeleteMenu)Restore menu items or permanently remove them after their children are handled.@else Read-only access: you can review deleted navigation items, but your role cannot restore or permanently delete them.@endif</p></div>@if($canViewNavigation)<a class="igf-btn" href="{{ route('page.menu.index') }}">Back to navigation</a>@endif</header>
    <form class="igf-search" method="GET"><input name="search" value="{{ $search }}" placeholder="Search trashed navigation"><button class="igf-btn">Search</button></form>
    <table class="igf-table"><thead><tr><th>Name</th><th>Location</th><th>Deleted</th><th>Actions</th></tr></thead><tbody>
    @forelse($pageMenus as $menu)
        <tr><td><strong>{{ $menu->name }}</strong></td><td>{{ ucfirst($menu->type) }} &middot; {{ strtoupper($menu->language) }}</td><td>{{ $menu->deleted_at?->diffForHumans() }}</td><td><div class="igf-actions">@if($canRestoreMenu)<form method="POST" action="{{ route('page.menu.restore', $menu->uuid) }}">@csrf<button class="igf-btn">Restore</button></form>@endif @if($canPermanentlyDeleteMenu)<form method="POST" action="{{ route('page.menu.force-destroy', $menu->uuid) }}" onsubmit="return confirm('Permanently delete this navigation item?')">@csrf @method('DELETE')<button class="igf-btn igf-btn--danger">Delete forever</button></form>@endif @if(!$canRestoreMenu && !$canPermanentlyDeleteMenu)<span>View only</span>@endif</div></td></tr>
    @empty<tr><td colspan="4" style="padding:40px;text-align:center;color:#777">Navigation trash is empty.</td></tr>@endforelse
    </tbody></table><div style="margin-top:20px">{{ $pageMenus->links() }}</div>
</main>
@endsection
