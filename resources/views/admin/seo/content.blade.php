@extends('admin.layouts.master')

@section('content')
@include('admin.seo._styles')
<main class="seo2">
    <header class="seo2-head">
        <div><h1>{{ $contentTitle }}</h1><p>{{ $contentLabel }} search and sharing settings. Plain-language recommendations are shown beside live previews.</p></div>
        <div class="seo2-actions"><a class="seo2-btn" href="{{ route('seo.index', ['locale' => $locale]) }}"><i class="fa fa-arrow-left" aria-hidden="true"></i> Search &amp; Sharing</a></div>
    </header>
    @include('admin.seo._indexing-status')
    @if(session('message'))<div class="seo2-alert" role="status">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="seo2-alert seo2-alert--error" role="alert"><strong>Please fix the highlighted settings:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @unless($canEditMetadata)<div class="seo2-alert seo2-alert--warning" role="status"><strong>Read-only SEO access.</strong> You can inspect previews, checklist results and history. Editing and restoring need separate permissions.</div>@endunless
    @php($editorTitle = $contentTitle)
    @include('admin.seo._editor')
</main>
@endsection

@section('custom-js')
@include('admin.seo._scripts')
@endsection
