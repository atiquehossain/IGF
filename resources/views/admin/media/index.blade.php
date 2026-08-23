@extends('admin.layouts.master')

@php
    $admin = auth('admin')->user();
    $permissions = app(\App\Http\Middleware\Permission::class);
    $canUploadMedia = $permissions->allows($admin, 'media.create');
    $canEditMedia = $permissions->allows($admin, 'media.edit');
    $canRestoreMedia = $permissions->allows($admin, 'media.edit');
    $canDeleteMedia = $permissions->allows($admin, 'media.destroy');
    $screenIsReadOnly = $isTrash
        ? !$canRestoreMedia && !$canDeleteMedia
        : !$canUploadMedia && !$canEditMedia && !$canDeleteMedia;
@endphp

@section('content')
<style>
    .igf-library{--brand:#9c4500;--accent:#ff7500;--ink:#191c1d;--muted:#686868;max-width:1320px;margin:28px auto;padding:0 22px;color:var(--ink)}
    .igf-library__head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:22px}.igf-library h1{margin:0;font:700 40px/1.1 Georgia,serif}.igf-library__head p{margin:7px 0 0;color:var(--muted)}
    .igf-library__toolbar{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 20px}.igf-library input,.igf-library select,.igf-library textarea{min-height:44px;padding:10px 12px;border:1px solid #cfc8c2;border-radius:8px;background:#fff}.igf-library__search{min-width:260px;flex:1}.igf-btn{display:inline-flex;min-height:44px;align-items:center;justify-content:center;padding:10px 15px;border:1px solid #cfc8c2;border-radius:8px;background:#fff;color:var(--ink);font-weight:700;text-decoration:none;cursor:pointer}.igf-btn:hover{border-color:var(--accent);background:#fff8f2}.igf-btn:focus-visible,.igf-library summary:focus-visible,.igf-library input:focus-visible,.igf-library select:focus-visible,.igf-library textarea:focus-visible{outline:3px solid rgba(255,117,0,.3);outline-offset:2px}.igf-btn--primary{border-color:var(--brand);background:var(--brand);color:#fff}.igf-btn--primary:hover{border-color:#783300;background:#783300;color:#fff}.igf-btn--danger{border-color:#e6b9b5;color:#8d2018}.igf-btn--danger:hover{border-color:#b42d24;background:#fff4f3}.igf-library__quiet-link{color:#6b5b50;font-weight:700;text-underline-offset:3px}
    .igf-upload-panel{margin:0 0 20px;border:1px solid #e4d7cd;border-radius:12px;background:#fffaf6}.igf-upload-panel>summary{display:flex;min-height:48px;align-items:center;padding:0 16px;color:var(--brand);font-weight:800;cursor:pointer}.igf-upload-panel[open]>summary{border-bottom:1px solid #eadfd7}.igf-upload{display:grid;grid-template-columns:minmax(220px,1.3fr) minmax(180px,1fr) minmax(180px,1fr) auto;gap:10px;align-items:end;padding:18px}.igf-field label{display:block;margin-bottom:6px;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.igf-field input,.igf-field textarea{width:100%}
    .igf-media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}.igf-media{overflow:hidden;border:1px solid #e8e3de;border-radius:12px;background:#fff;box-shadow:0 8px 22px rgba(25,28,29,.035)}.igf-media__preview{display:flex;align-items:center;justify-content:center;aspect-ratio:4/3;background:#f0f0ee}.igf-media__preview img{width:100%;height:100%;object-fit:cover}.igf-media__doc{font-size:42px;color:#8b817b}.igf-media__body{padding:14px}.igf-media__title{display:block;overflow:hidden;color:#252525;font-size:16px;text-overflow:ellipsis;white-space:nowrap}.igf-media__meta{margin:4px 0 12px;color:var(--muted);font-size:12px}.igf-media__actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.igf-media__actions form{margin:0}.igf-media__details{margin-top:10px;border-top:1px solid #eee8e3}.igf-media__details>summary{display:flex;min-height:44px;align-items:center;color:#5e5047;font-size:13px;font-weight:800;cursor:pointer}.igf-media__details-body{padding:0 0 12px}.igf-media__filename{overflow-wrap:anywhere;color:var(--muted);font:12px/1.45 ui-monospace,SFMono-Regular,Consolas,monospace}.igf-media__edit{display:grid;gap:10px}.igf-media__edit textarea{min-height:76px;resize:vertical}.igf-media__usage{margin:10px 0 0;padding:10px;border-radius:8px;background:#f7f5f2;color:#5d554f;font-size:13px}.igf-media__usage ul{margin:6px 0 0;padding-left:18px}.igf-media__danger{margin-top:4px;border-top-color:#f1d9d6}.igf-media__danger>summary{color:#8d2018}.igf-media__danger-note{margin:0 0 10px;color:#74645f;font-size:12px;line-height:1.45}.igf-empty{padding:64px;border:1px dashed #d8d1ca;border-radius:12px;text-align:center;background:#fff;color:var(--muted)}
    .igf-read-only{margin:0 0 20px;padding:14px 16px;border:1px solid #d8e3ef;border-radius:10px;background:#f4f8fc;color:#30475e}.igf-read-only strong{display:block;margin-bottom:2px}.igf-view-only{color:var(--muted);font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
    .igf-library__pagination{display:flex;justify-content:flex-end;margin-top:24px}.igf-library__pagination nav{max-width:100%;overflow-x:auto;padding:3px}.igf-library__pagination .pagination{display:flex;align-items:center;gap:6px;margin:0;padding:0;list-style:none}.igf-library__pagination .page-item{margin:0}.igf-library__pagination .page-link{display:inline-flex;width:44px;height:44px;align-items:center;justify-content:center;padding:0;border:1px solid #ded9d3;border-radius:8px;background:#fff;color:#4d4b49;font-size:14px;font-weight:750;line-height:1;text-decoration:none;box-shadow:none}.igf-library__pagination .page-link:hover{border-color:var(--accent);background:#fff8f2;color:var(--brand)}.igf-library__pagination .page-link:focus-visible{outline:3px solid rgba(255,117,0,.24);outline-offset:2px}.igf-library__pagination .page-item.active .page-link{border-color:var(--brand);background:var(--brand);color:#fff}.igf-library__pagination .page-item.disabled .page-link{background:#f3f1ef;color:#aaa5a0;cursor:not-allowed}
    @media(max-width:800px){.igf-upload{grid-template-columns:1fr}.igf-library__head{align-items:start;flex-direction:column}.igf-library{padding:0 14px}.igf-library h1{font-size:32px}.igf-library__search{min-width:100%}}
</style>

<main class="igf-library">
    <header class="igf-library__head">
        <div><h1>{{ $isTrash ? 'Media trash' : 'Media library' }}</h1><p>Upload once, reuse everywhere, and keep every deletion recoverable.</p></div>
        <a class="{{ $isTrash ? 'btn igf-btn igf-btn-secondary' : 'igf-library__quiet-link' }}" href="{{ route('media.index', $isTrash ? [] : ['trash' => 1]) }}">@if($isTrash)<i class="fa fa-arrow-left" aria-hidden="true"></i> @endif{{ $isTrash ? 'Back to library' : 'View trash' }}</a>
    </header>

    @if($screenIsReadOnly)
        <div class="igf-read-only" role="status">
            <strong>Read-only access</strong>
            <span>You can search, preview, and copy media URLs, but your role cannot {{ $isTrash ? 'restore or permanently delete files in the trash' : 'upload or move files to the trash' }}.</span>
        </div>
    @endif

    @if(!$isTrash && $canUploadMedia)
        <details class="igf-upload-panel">
            <summary>Upload new media</summary>
            <form class="igf-upload" method="POST" action="{{ route('media.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="igf-field"><label for="media-file">File</label><input id="media-file" type="file" name="file" required accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,video/mp4,video/webm"></div>
                <div class="igf-field"><label for="media-alt">Alternative text</label><input id="media-alt" name="alt_text" placeholder="Describe the image"></div>
                <div class="igf-field"><label for="media-caption">Caption</label><input id="media-caption" name="caption" placeholder="Optional caption"></div>
                <button class="igf-btn igf-btn--primary" type="submit">Upload media</button>
            </form>
        </details>
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
                @php
                    $displayName = trim((string) ($asset->caption ?: $asset->alt_text));
                    $displayName = $displayName !== '' ? $displayName : ($asset->is_image ? 'Untitled image' : 'Untitled file');
                @endphp
                <article class="igf-media">
                    <div class="igf-media__preview">@if($asset->is_image)<img src="{{ $asset->url }}" alt="{{ $asset->alt_text ?: '' }}">@else<span class="igf-media__doc" aria-label="Document">&#128196;</span>@endif</div>
                    <div class="igf-media__body">
                        <strong class="igf-media__title" title="{{ $displayName }}">{{ $displayName }}</strong>
                        <div class="igf-media__meta">{{ number_format($asset->bytes / 1024, 1) }} KB @if($asset->width)&middot; {{ $asset->width }}&times;{{ $asset->height }}@endif</div>
                        <div class="igf-media__actions">
                            <button class="igf-btn" type="button" data-copy="{{ $asset->url }}">Copy URL</button>
                            <button class="igf-btn" type="button" data-usage-url="{{ route('media.index', ['usage' => $asset->uuid]) }}" aria-expanded="false">Check usage</button>
                            @if($isTrash)
                                @if($canRestoreMedia)
                                    <form method="POST" action="{{ route('media.restore', $asset->uuid) }}">@csrf<button class="igf-btn" type="submit">Restore</button></form>
                                @endif
                                @if(!$canRestoreMedia && !$canDeleteMedia)<span class="igf-view-only">View only</span>@endif
                            @else
                                @if(!$canEditMedia && !$canDeleteMedia)
                                    <span class="igf-view-only">View only</span>
                                @endif
                            @endif
                        </div>
                        <div class="igf-media__usage" data-usage-result hidden aria-live="polite"></div>
                        <details class="igf-media__details">
                            <summary>File details</summary>
                            <div class="igf-media__details-body">
                                <div class="igf-media__filename">{{ $asset->original_name }}</div>
                                <div class="igf-media__filename">{{ $asset->mime_type }} &middot; {{ $asset->locale ?: '*' }}</div>
                            </div>
                        </details>
                        @if(!$isTrash && $canEditMedia)
                            <details class="igf-media__details">
                                <summary>Edit description</summary>
                                <form class="igf-media__details-body igf-media__edit" method="POST" action="{{ route('media.update', $asset) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="locale" value="{{ $asset->locale ?: '*' }}">
                                    <div class="igf-field"><label for="media-alt-{{ $asset->uuid }}">Alternative text</label><textarea id="media-alt-{{ $asset->uuid }}" name="alt_text" placeholder="Describe the image for people who cannot see it">{{ $asset->alt_text }}</textarea></div>
                                    <div class="igf-field"><label for="media-caption-{{ $asset->uuid }}">Caption</label><textarea id="media-caption-{{ $asset->uuid }}" name="caption" placeholder="Optional public-facing caption">{{ $asset->caption }}</textarea></div>
                                    <button class="igf-btn" type="submit">Save details</button>
                                </form>
                            </details>
                        @endif
                        @if($canDeleteMedia)
                            <details class="igf-media__details igf-media__danger">
                                <summary>{{ $isTrash ? 'Permanent deletion' : 'Move to trash' }}</summary>
                                <div class="igf-media__details-body">
                                    <p class="igf-media__danger-note">{{ $isTrash ? 'Check usage first. Permanent deletion cannot be undone.' : 'The file remains recoverable from Media trash.' }}</p>
                                    @if($isTrash)
                                        <form method="POST" action="{{ route('media.force-destroy', $asset->uuid) }}" onsubmit="return confirm('Permanently delete this file? This cannot be undone.')">@csrf @method('DELETE')<button class="igf-btn igf-btn--danger" type="submit">Delete forever</button></form>
                                    @else
                                        <form method="POST" action="{{ route('media.destroy', $asset) }}" onsubmit="return confirm('Move this file to trash?')">@csrf @method('DELETE')<button class="igf-btn igf-btn--danger" type="submit">Move to trash</button></form>
                                    @endif
                                </div>
                            </details>
                        @endif
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
async function copyMediaUrl(value) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);
        return;
    }

    const input = document.createElement('textarea');
    input.value = value;
    input.setAttribute('readonly', '');
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.select();
    const copied = document.execCommand('copy');
    input.remove();
    if (!copied) throw new Error('Copy failed');
}

document.querySelectorAll('[data-copy]').forEach(button => button.addEventListener('click', async () => {
    const originalLabel = button.textContent;
    try {
        await copyMediaUrl(button.dataset.copy);
        button.textContent = 'URL copied';
    } catch (error) {
        button.textContent = 'Copy failed';
    }
    setTimeout(() => { button.textContent = originalLabel; }, 1800);
}));

document.querySelectorAll('[data-usage-url]').forEach(button => button.addEventListener('click', async () => {
    const result = button.closest('.igf-media__body').querySelector('[data-usage-result]');
    result.hidden = false;
    result.textContent = 'Checking usage…';
    button.disabled = true;

    try {
        const response = await fetch(button.dataset.usageUrl, {headers: {'Accept': 'application/json'}});
        if (!response.ok) throw new Error('Usage check failed');
        const data = await response.json();
        const references = Object.entries(data.references || {});
        result.replaceChildren();
        const summary = document.createElement('strong');
        summary.textContent = data.total
            ? `Used in ${data.total} content ${data.total === 1 ? 'record' : 'records'}`
            : 'Not currently used';
        result.appendChild(summary);
        if (references.length) {
            const list = document.createElement('ul');
            references.forEach(([type, count]) => {
                const item = document.createElement('li');
                item.textContent = `${type.replaceAll('_', ' ')}: ${count}`;
                list.appendChild(item);
            });
            result.appendChild(list);
        }
        button.textContent = 'Usage checked';
        button.setAttribute('aria-expanded', 'true');
    } catch (error) {
        result.textContent = 'Could not check usage. Please try again.';
        button.textContent = 'Retry usage check';
    } finally {
        button.disabled = false;
    }
}));
</script>
@endsection
