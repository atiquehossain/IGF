@extends('admin.layouts.master')

@section('content')
@php
    $admin = auth('admin')->user();
    $permissions = app(\App\Http\Middleware\Permission::class);
    $canViewNavigation = $permissions->allows($admin, 'page.menu.index');
    $canRestoreMenu = $permissions->allows($admin, 'page.menu.restore');
    $canPermanentlyDeleteMenu = $permissions->allows($admin, 'page.menu.force-destroy');
    $ui = static fn (string $key, array $replace = []): string => \App\Support\AdminUi::text($key, $replace);
@endphp
<style>
    .igf-trash{max-width:1100px;margin:28px auto;padding:0 22px}.igf-trash__head{display:flex;justify-content:space-between;align-items:end;gap:16px;margin-bottom:22px}.igf-trash h1{margin:0;font:700 38px Georgia,serif}.igf-trash p{color:#6e6965}.igf-btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:9px 14px;border:1px solid #ddd5ce;border-radius:8px;background:#fff;color:#202020;font-weight:700;text-decoration:none;cursor:pointer}.igf-btn--danger{color:#a32920}.igf-search{display:flex;gap:10px;margin-bottom:18px}.igf-search input{flex:1 1 280px;min-width:0;min-height:44px;padding:10px;border:1px solid #ddd5ce;border-radius:8px}.igf-table-wrap{max-width:100%;overflow-x:auto;border:1px solid #e8e2dc;border-radius:10px;-webkit-overflow-scrolling:touch}.igf-table{width:100%;min-width:650px;border-collapse:collapse;background:#fff}.igf-table th,.igf-table td{padding:14px;border-bottom:1px solid #eee;text-align:left}.igf-actions{display:flex;align-items:center;gap:8px;white-space:nowrap}.igf-actions form{margin:0}@media(max-width:650px){.igf-trash{padding:0 12px}.igf-trash__head{align-items:flex-start;flex-direction:column}.igf-trash__head .igf-btn,.igf-search .igf-btn{width:100%}.igf-search{flex-direction:column}}
</style>
<main class="igf-trash">
    <header class="igf-trash__head"><div><h1>{{ $ui('navigation.trash_title') }}</h1><p>{{ $ui($canRestoreMenu || $canPermanentlyDeleteMenu ? 'navigation.trash_help' : 'navigation.trash_readonly_help') }}</p></div>@if($canViewNavigation)<a class="btn igf-btn igf-btn-secondary" href="{{ route('page.menu.index') }}"><i class="fa fa-arrow-left" aria-hidden="true"></i> {{ $ui('navigation.back_to_navigation') }}</a>@endif</header>
    <form class="igf-search" method="GET"><label class="sr-only" for="navigation-trash-search">{{ $ui('navigation.search_trash') }}</label><input id="navigation-trash-search" type="search" name="search" value="{{ $search }}" placeholder="{{ $ui('navigation.search_trash') }}"><button class="igf-btn" type="submit">{{ $ui('common.search') }}</button></form>
    <div class="igf-table-wrap table-responsive" role="region" aria-label="{{ $ui('navigation.scrollable_trash') }}" tabindex="0"><table class="igf-table"><thead><tr><th>{{ $ui('common.name') }}</th><th>{{ $ui('common.location') }}</th><th>{{ $ui('navigation.deleted') }}</th><th>{{ $ui('common.actions') }}</th></tr></thead><tbody>
    @forelse($pageMenus as $menu)
        <tr><td><strong>{{ $menu->name }}</strong></td><td>{{ ucfirst($menu->type) }} &middot; {{ strtoupper($menu->language) }}</td><td>{{ $menu->deleted_at?->diffForHumans() }}</td><td><div class="igf-actions">@if($canRestoreMenu)<form method="POST" action="{{ route('page.menu.restore', $menu->uuid) }}">@csrf<button class="igf-btn">{{ $ui('common.restore') }}</button></form>@endif @if($canPermanentlyDeleteMenu)<form method="POST" action="{{ route('page.menu.force-destroy', $menu->uuid) }}" onsubmit="return confirm(@js($ui('navigation.delete_forever_confirm')))">@csrf @method('DELETE')<button class="igf-btn igf-btn--danger">{{ $ui('common.delete_forever') }}</button></form>@endif @if(!$canRestoreMenu && !$canPermanentlyDeleteMenu)<span>{{ $ui('common.view_only') }}</span>@endif</div></td></tr>
    @empty<tr><td colspan="4" style="padding:40px;text-align:center;color:#777">{{ $ui('navigation.empty_trash') }}</td></tr>@endforelse
    </tbody></table></div><div style="margin-top:20px">{{ $pageMenus->links() }}</div>
</main>
@endsection
