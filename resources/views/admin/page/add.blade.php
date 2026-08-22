@extends('admin.layouts.master')

@section('content')
@php($selectedLanguage = old('language', $locales->first()->id ?? 'en'))
<style>
    .draft-wizard{--dw-orange:#ff7500;--dw-brown:#9c4500;--dw-ink:#191c1d;--dw-muted:#66636a;--dw-line:#e4ddd7;max-width:1120px;margin:0 auto;padding:34px 28px 80px;color:var(--dw-ink);font-family:'Hanken Grotesk',sans-serif}.draft-back{display:inline-flex;align-items:center;gap:7px;margin-bottom:22px;color:var(--dw-brown);font-size:13px;font-weight:800;text-decoration:none}.draft-head{max-width:780px;margin-bottom:26px}.draft-kicker{margin:0 0 7px;color:var(--dw-brown);font-size:11px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}.draft-head h1{margin:0 0 10px;font:700 clamp(34px,5vw,54px)/1.06 'Literata',serif;letter-spacing:-.04em}.draft-head>p{margin:0;color:var(--dw-muted);font-size:16px;line-height:1.6}.draft-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:24px 0}.draft-step{display:flex;align-items:flex-start;gap:10px;padding:13px 14px;border:1px solid var(--dw-line);border-radius:10px;background:#fff}.draft-step span{display:grid;flex:0 0 26px;width:26px;height:26px;place-items:center;border-radius:50%;background:#fff0e5;color:var(--dw-brown);font-size:11px;font-weight:900}.draft-step strong{display:block;font-size:12px}.draft-step small{display:block;margin-top:2px;color:var(--dw-muted);font-size:11px;line-height:1.35}.draft-form{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:18px;align-items:start}.draft-card{overflow:hidden;border:1px solid var(--dw-line);border-radius:13px;background:#fff;box-shadow:0 12px 32px rgba(25,28,29,.04)}.draft-section{padding:24px}.draft-section+.draft-section{border-top:1px solid var(--dw-line)}.draft-section h2{margin:0 0 5px;font:600 21px/1.25 'Literata',serif}.draft-section>p{margin:0 0 20px;color:var(--dw-muted);font-size:13px;line-height:1.5}.draft-grid{display:grid;grid-template-columns:1fr 1fr;gap:17px}.draft-field--wide{grid-column:1/-1}.draft-field label,.draft-tags legend{display:block;margin:0 0 6px;color:#4c494d;font-size:12px;font-weight:900}.draft-field label span,.draft-tags legend span{color:#8b8580;font-weight:600}.draft-field input,.draft-field select{width:100%;min-height:45px;border:1px solid #d8d1cb;border-radius:8px;padding:9px 12px;background:#fff;color:var(--dw-ink);font-size:14px}.draft-field input:focus,.draft-field select:focus{border-color:var(--dw-orange);outline:3px solid rgba(255,117,0,.12)}.draft-field small{display:block;margin-top:6px;color:var(--dw-muted);font-size:11px;line-height:1.45}.draft-error{display:block!important;color:#a3261b!important;font-weight:700}.draft-alert{margin-bottom:18px;padding:14px 16px;border:1px solid #efb9b0;border-radius:9px;background:#fff1ee;color:#87271d;font-size:13px}.draft-alert strong{display:block;margin-bottom:5px}.draft-alert ul{margin:0;padding-left:18px}.draft-tags{grid-column:1/-1;margin:0;padding:0;border:0}.draft-tag-list{display:flex;flex-wrap:wrap;gap:8px}.draft-tag{position:relative}.draft-tag input{position:absolute;opacity:0;pointer-events:none}.draft-tag span{display:inline-flex;align-items:center;min-height:36px;padding:7px 12px;border:1px solid #ded7d1;border-radius:999px;background:#fff;color:#565156;font-size:12px;font-weight:700;cursor:pointer}.draft-tag input:checked+span{border-color:var(--dw-orange);background:#fff0e5;color:var(--dw-brown);box-shadow:0 0 0 2px rgba(255,117,0,.08)}.draft-tag input:focus-visible+span{outline:3px solid rgba(255,117,0,.25)}.draft-empty{color:var(--dw-muted);font-size:12px}.draft-next{position:sticky;top:86px;padding:22px}.draft-next h2{margin:0 0 13px;font:600 20px 'Literata',serif}.draft-next ul{display:grid;gap:12px;margin:0 0 20px;padding:0;list-style:none}.draft-next li{display:flex;gap:9px;color:var(--dw-muted);font-size:12px;line-height:1.45}.draft-next li i{margin-top:2px;color:#299257}.draft-note{margin-bottom:18px;padding:12px;border-radius:8px;background:#f7f4f1;color:#625d59;font-size:11px;line-height:1.5}.draft-submit{display:flex;width:100%;min-height:46px;align-items:center;justify-content:center;gap:8px;border:0;border-radius:9px;background:linear-gradient(135deg,var(--dw-orange),#e55f00);box-shadow:0 7px 18px rgba(255,117,0,.2);color:#fff;font-size:13px;font-weight:900;cursor:pointer}.draft-submit:hover{filter:brightness(.97)}.draft-cancel{display:block;margin-top:12px;color:#6f6965;font-size:12px;font-weight:800;text-align:center;text-decoration:none}@media(max-width:850px){.draft-form{grid-template-columns:1fr}.draft-next{position:static}.draft-steps{grid-template-columns:1fr}}@media(max-width:600px){.draft-wizard{padding:24px 13px 70px}.draft-grid{grid-template-columns:1fr}.draft-field--wide,.draft-tags{grid-column:auto}.draft-section{padding:19px}}
</style>

<main class="draft-wizard">
    <a class="draft-back" href="{{ route('page.index') }}"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back to Pages</a>

    <header class="draft-head">
        <p class="draft-kicker">Pages · Quick Create</p>
        <h1>Create one page draft</h1>
        <p>Start with the few details people use to find and recognize the page. We will create a safe, unpublished draft in one language and open it in the Simple Editor.</p>
        <div class="draft-steps" aria-label="What happens next">
            <div class="draft-step"><span>1</span><div><strong>Add the basics</strong><small>Choose one language and enter a clear title.</small></div></div>
            <div class="draft-step"><span>2</span><div><strong>Build the page</strong><small>Add text, images and sections in the Simple Editor.</small></div></div>
            <div class="draft-step"><span>3</span><div><strong>Preview and publish</strong><small>The draft stays off the website until it is approved.</small></div></div>
        </div>
    </header>

    @if($errors->any())
        <div class="draft-alert" role="alert">
            <strong>Please check the highlighted details.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form class="draft-form" action="{{ route('page.store') }}" method="POST">
        @csrf
        <input type="hidden" name="creation_mode" value="guided">

        <div class="draft-card">
            <section class="draft-section" aria-labelledby="draft-basics-heading">
                <h2 id="draft-basics-heading">Page basics</h2>
                <p>Only the title is required. You can change these details later.</p>
                <div class="draft-grid">
                    <div class="draft-field">
                        <label for="draft-language">Language</label>
                        <select id="draft-language" name="language" required data-e2e="page-language">
                            @foreach($locales as $locale)
                                <option value="{{ $locale->id }}" @selected($selectedLanguage === $locale->id)>{{ $locale->name }} · {{ $locale->native_name }}</option>
                            @endforeach
                        </select>
                        <small>Create this language now. Other translations can be prepared separately.</small>
                        @error('language')<small class="draft-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="draft-field">
                        <label for="draft-title">Page title</label>
                        <input id="draft-title" name="name" value="{{ old('name') }}" maxlength="255" required autofocus placeholder="For example: Our education programs" data-e2e="page-name">
                        <small>The web address will be generated safely from this title.</small>
                        @error('name')<small class="draft-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="draft-field draft-field--wide">
                        <label for="draft-subtitle">Short subtitle <span>· optional</span></label>
                        <input id="draft-subtitle" name="sub_title" value="{{ old('sub_title') }}" maxlength="2000" placeholder="One sentence that explains what this page is about" data-e2e="page-subtitle">
                        @error('sub_title')<small class="draft-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </section>

            <section class="draft-section" aria-labelledby="draft-organize-heading">
                <h2 id="draft-organize-heading">Help people find it</h2>
                <p>These choices are optional. Only active choices for the selected language are available.</p>
                <div class="draft-grid">
                    <div class="draft-field">
                        <label for="draft-category">Category <span>· optional</span></label>
                        <select id="draft-category" name="category_id" data-locale-select>
                            <option value="">No category yet</option>
                            @foreach($categorylist as $category)
                                <option value="{{ $category->id }}" data-locale-option="{{ $category->language }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <small>Categories update when the language changes.</small>
                        @error('category_id')<small class="draft-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="draft-field">
                        <label for="draft-banner">Header banner <span>· optional</span></label>
                        <select id="draft-banner" name="banner_id" data-locale-select>
                            <option value="">Choose later in Simple Editor</option>
                            @foreach($bannerList as $banner)
                                <option value="{{ $banner->id }}" data-locale-option="{{ $banner->language }}" @selected((string) old('banner_id') === (string) $banner->id)>{{ $banner->name }}</option>
                            @endforeach
                        </select>
                        <small>Use an existing active banner in this page language.</small>
                        @error('banner_id')<small class="draft-error">{{ $message }}</small>@enderror
                    </div>

                    <fieldset class="draft-tags">
                        <legend>Related projects <span>· optional</span></legend>
                        @if($tags->isEmpty())
                            <div class="draft-empty">No active projects are available. You can continue without one.</div>
                        @else
                            <div class="draft-tag-list">
                                @foreach($tags as $tag)
                                    <label class="draft-tag">
                                        <input type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked(in_array((string) $tag->id, array_map('strval', old('tags', [])), true))>
                                        <span>{{ $tag->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        @error('tags')<small class="draft-error">{{ $message }}</small>@enderror
                        @error('tags.*')<small class="draft-error">{{ $message }}</small>@enderror
                    </fieldset>
                </div>
            </section>
        </div>

        <aside class="draft-card draft-next" aria-labelledby="draft-next-heading">
            <h2 id="draft-next-heading">After you create it</h2>
            <ul>
                <li><i class="fa fa-check-circle" aria-hidden="true"></i><span>One unpublished draft is created in the language you chose.</span></li>
                <li><i class="fa fa-check-circle" aria-hidden="true"></i><span>The Simple Editor opens so you can add page sections and images.</span></li>
                <li><i class="fa fa-check-circle" aria-hidden="true"></i><span>Search &amp; Sharing settings remain in the guided SEO editor.</span></li>
                <li><i class="fa fa-check-circle" aria-hidden="true"></i><span>An initial restore point is saved automatically.</span></li>
            </ul>
            <div class="draft-note"><strong>Nothing goes live now.</strong> A draft is not visible to website visitors until an authorized editor publishes it.</div>
            <button class="draft-submit" type="submit" data-e2e="create-page-draft"><i class="fa fa-arrow-right" aria-hidden="true"></i> Create draft and open Simple Editor</button>
            <a class="draft-cancel" href="{{ route('page.index') }}">Cancel</a>
        </aside>
    </form>
</main>
@endsection

@section('custom-js')
<script>
(() => {
    const language = document.getElementById('draft-language');
    if (!language) return;

    const syncLocaleChoices = () => {
        document.querySelectorAll('[data-locale-select]').forEach(select => {
            let available = 0;
            select.querySelectorAll('[data-locale-option]').forEach(option => {
                const matches = option.dataset.localeOption === language.value;
                option.hidden = !matches;
                option.disabled = !matches;
                if (matches) available += 1;
                if (!matches && option.selected) select.value = '';
            });
            select.dataset.availableChoices = String(available);
        });
    };

    language.addEventListener('change', syncLocaleChoices);
    syncLocaleChoices();
})();
</script>
@endsection
