@extends('admin.layouts.master')

@section('content')
@include('admin.seo._styles')
<main class="seo2">
    <header class="seo2-head"><div><h1>Redirects</h1><p>Keep old bookmarks and search results working when a public page address changes.</p></div>@if($canManageMetadata)<div class="seo2-actions"><a class="seo2-btn" href="{{ route('seo.index', ['locale' => $locale]) }}">Search &amp; Sharing</a></div>@endif</header>
    @if(session('message'))<div class="seo2-alert" role="status">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="seo2-alert seo2-alert--error" role="alert"><strong>Please fix these settings:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @include('admin.seo._redirects')
</main>
@endsection

@section('custom-js')
<script>
(() => {
    document.querySelector('[data-redirect-destination]')?.addEventListener('change', event => { const input = event.currentTarget.closest('form').querySelector('[name=to_url]'); if (event.currentTarget.value) input.value = event.currentTarget.value; });
    document.querySelectorAll('[data-redirect-edit]').forEach(button => button.addEventListener('click', () => document.querySelector(`[data-redirect-editor="${button.dataset.redirectEdit}"]`)?.classList.toggle('is-open')));
    document.querySelectorAll('[data-redirect-cancel]').forEach(button => button.addEventListener('click', () => button.closest('[data-redirect-editor]').classList.remove('is-open')));
})();
</script>
@endsection
