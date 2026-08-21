@extends('admin.layouts.master')

@php
    $admin = auth('admin')->user();
    $permissions = app(\App\Http\Middleware\Permission::class);
    $canUploadMedia = $permissions->allows($admin, 'media.create');
    $canRestoreMedia = $permissions->allows($admin, 'media.edit');
    $canDeleteMedia = $permissions->allows($admin, 'media.destroy');
    $screenIsReadOnly = $isTrash
        ? !$canRestoreMedia && !$canDeleteMedia
        : !$canUploadMedia && !$canDeleteMedia;
@endphp

@section('content')
<style>
    .igf-library{--brand:#9c4500;--accent:#ff7500;--ink:#191c1d;--muted:#686868;max-width:1320px;margin:28px auto;padding:0 22px;color:var(--ink)}
    .igf-library__head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:22px}.igf-library h1{margin:0;font:700 40px/1.1 Georgia,serif}.igf-library__head p{margin:7px 0 0;color:var(--muted)}
    .igf-library__toolbar{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 20px}.igf-library input,.igf-library select,.igf-library textarea{padding:10px 12px;border:1px solid #ded9d3;border-radius:8px;background:#fff}.igf-library__search{min-width:260px;flex:1}.igf-btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 15px;border:1px solid #ded9d3;border-radius:8px;background:#fff;color:var(--ink);font-weight:700;text-decoration:none;cursor:pointer}.igf-btn--primary{border-color:var(--accent);background:var(--accent);color:#fff}.igf-btn--danger{color:#a02920}
    .igf-upload{display:grid;grid-template-columns:minmax(220px,1.3fr) minmax(180px,1fr) minmax(180px,1fr) auto;gap:10px;align-items:end;margin-bottom:22px;padding:18px;border:1px dashed #ccbfb5;border-radius:12px;background:#fffaf6}.igf-field label{display:block;margin-bottom:6px;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.igf-field input,.igf-field textarea{width:100%}
    .igf-media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px}.igf-media{overflow:hidden;border:1px solid #e8e3de;border-radius:12px;background:#fff;box-shadow:0 8px 22px rgba(25,28,29,.035)}.igf-media__preview{display:flex;align-items:center;justify-content:center;aspect-ratio:4/3;background:#f0f0ee}.igf-media__preview img{width:100%;height:100%;object-fit:cover}.igf-media__doc{font-size:42px;color:#8b817b}.igf-media__body{padding:13px}.igf-media__body strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.igf-media__meta{margin:4px 0 12px;color:var(--muted);font-size:12px}.igf-media__actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.igf-media__actions form{margin:0}.igf-empty{padding:64px;border:1px dashed #d8d1ca;border-radius:12px;text-align:center;background:#fff;color:var(--muted)}
    .igf-read-only{margin:0 0 20px;padding:14px 16px;border:1px solid #d8e3ef;border-radius:10px;background:#f4f8fc;color:#30475e}.igf-read-only strong{display:block;margin-bottom:2px}.igf-view-only{color:var(--muted);font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
    .igf-library__pagination{display:flex;justify-content:flex-end;margin-top:24px}.igf-library__pagination nav{max-width:100%;overflow-x:auto;padding:3px}.igf-library__pagination .pagination{display:flex;align-items:center;gap:6px;margin:0;padding:0;list-style:none}.igf-library__pagination .page-item{margin:0}.igf-library__pagination .page-link{display:inline-flex;width:40px;height:40px;align-items:center;justify-content:center;padding:0;border:1px solid #ded9d3;border-radius:8px;background:#fff;color:#4d4b49;font-size:14px;font-weight:750;line-height:1;text-decoration:none;box-shadow:none}.igf-library__pagination .page-link:hover{border-color:var(--accent);background:#fff8f2;color:var(--brand)}.igf-library__pagination .page-link:focus-visible{outline:3px solid rgba(255,117,0,.24);outline-offset:2px}.igf-library__pagination .page-item.active .page-link{border-color:var(--accent);background:var(--accent);color:#fff}.igf-library__pagination .page-item.disabled .page-link{background:#f3f1ef;color:#aaa5a0;cursor:not-allowed}
    @media(max-width:800px){.igf-upload{grid-template-columns:1fr}.igf-library__head{align-items:start;flex-direction:column}}
</style>

<main class="igf-library">
    <header class="igf-library__head">
        <div><h1>{{ $isTrash ? 'Media trash' : 'Media library' }}</h1><p>Upload once, reuse everywhere, and keep every deletion recoverable.</p></div>
        <a class="igf-btn" href="{{ route('media.index', $isTrash ? [] : ['trash' => 1]) }}">{{ $isTrash ? 'Back to library' : 'View trash' }}</a>
    </header>

    @if($screenIsReadOnly)
        <div class="igf-read-only" role="status">
            <strong>Read-only access</strong>
            <span>You can search, preview, and copy media URLs, but your role cannot {{ $isTrash ? 'restore or permanently delete files in the trash' : 'upload or move files to the trash' }}.</span>
        </div>
    @endif

    @if(!$isTrash && $canUploadMedia)
        <form class="igf-upload" method="POST" action="{{ route('media.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="igf-field"><label for="media-file">File</label><input id="media-file" type="file" name="file" required accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,video/mp4,video/webm"></div>
            <div class="igf-field"><label for="media-alt">Alternative text</label><input id="media-alt" name="alt_text" placeholder="Describe the image"></div>
            <div class="igf-field"><label for="media-caption">Caption</label><input id="media-caption" name="caption" placeholder="Optional caption"></div>
            <button class="igf-btn igf-btn--primary" type="submit">Upload media</button>
        </form>
    @endif

    <form class="igf-library__toolbar" method="GET" action="{{ route('media.index') }}">
        @if($isTrash)<input type="hidden" name="trash" value="1">@endif
        <label class="sr-only" for="media-library-search">Search media</label>
        <input id="media-library-search" class="igf-library__search" name="search" value="{{ $search }}" placeholder="Search file name, alt text, or caption">
        <label class="sr-only" for="media-library-type">Media type</label>
        <select id="media-library-type" name="type"><option value="">All media</option><option value="image" @selected($type === 'image')>Images</option><option value="document" @selected($type === 'document')>Documents & video</option></select>
        <button class="igf-btn" type="submit">Filter</button>
    </form>

    @if($assets->count())
        <section class="igf-media-grid" aria-label="Media assets">
            @foreach($assets as $asset)
                <article class="igf-media">
                    <div class="igf-media__preview">@if($asset->is_image)<img src="{{ $asset->url }}" alt="{{ $asset->alt_text ?: '' }}">@else<span class="igf-media__doc" aria-label="Document">&#128196;</span>@endif</div>
                    <div class="igf-media__body">
                        <strong title="{{ $asset->original_name }}">{{ $asset->original_name }}</strong>
                        <div class="igf-media__meta">{{ number_format($asset->bytes / 1024, 1) }} KB @if($asset->width)&middot; {{ $asset->width }}&times;{{ $asset->height }}@endif</div>
                        <div class="igf-media__actions">
                            <button class="igf-btn" type="button" data-copy="{{ $asset->url }}">Copy URL</button>
                            @if($isTrash)
                                @if($canRestoreMedia)
                                    <form method="POST" action="{{ route('media.restore', $asset->uuid) }}">@csrf<button class="igf-btn" type="submit">Restore</button></form>
                                @endif
                                @if($canDeleteMedia)
                                    <form method="POST" action="{{ route('media.force-destroy', $asset->uuid) }}" onsubmit="return confirm('Permanently delete this file? This cannot be undone.')">@csrf @method('DELETE')<button class="igf-btn igf-btn--danger" type="submit">Delete forever</button></form>
                                @endif
                                @if(!$canRestoreMedia && !$canDeleteMedia)<span class="igf-view-only">View only</span>@endif
                            @else
                                @if($canDeleteMedia)
                                    <form method="POST" action="{{ route('media.destroy', $asset) }}" onsubmit="return confirm('Move this file to trash?')">@csrf @method('DELETE')<button class="igf-btn igf-btn--danger" type="submit">Trash</button></form>
                                @else
                                    <span class="igf-view-only">View only</span>
                                @endif
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </section>
        <div class="igf-library__pagination">{{ $assets->links('vendor.pagination.bootstrap-4') }}</div>
    @else
        <div class="igf-empty"><h2>No media found</h2><p>{{ $isTrash ? 'The media trash is empty.' : ($canUploadMedia ? 'Upload your first image, document, or video.' : 'No files are available in this view.') }}</p></div>
    @endif
</main>
@endsection

@section('custom-js')
<script>
document.querySelectorAll('[data-copy]').forEach(button => button.addEventListener('click', async () => {
    await navigator.clipboard.writeText(button.dataset.copy);
    button.textContent = 'Copied';
    setTimeout(() => { button.textContent = 'Copy URL'; }, 1500);
}));
</script>
@endsection
