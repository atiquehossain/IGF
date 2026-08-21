@php
    $defaults = fn (string $group) => collect(config("site-settings.groups.$group.fields", []))->mapWithKeys(fn ($field, $key) => [$key => $field['default'] ?? ''])->all();
    try {
        $publicSettings = app(\App\Services\SiteSettingService::class)->values(app()->getLocale(), true);
    } catch (\Throwable) {
        $publicSettings = [];
    }
    $branding = array_merge($defaults('branding'), $publicSettings['branding'] ?? []);
    $messages = array_merge($defaults('system_pages'), $publicSettings['system_pages'] ?? []);
    $theme = array_merge($defaults('theme'), $publicSettings['theme'] ?? []);
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,follow">
    <title>{{ $messages['not_found_eyebrow'] }} | {{ $branding['site_name'] }}</title>
    <style>
        :root{--primary:{{ $theme['primary_color'] }};--ink:{{ $theme['ink_color'] }}}*{box-sizing:border-box}body{margin:0;background:#202223;color:#fff;font-family:Arial,sans-serif}.shell{display:grid;min-height:100vh;padding:32px 20px;place-items:center;text-align:center}.card{width:min(100%,720px)}.logo{display:block;width:130px;height:auto;margin:0 auto 62px}.code{margin-bottom:16px;color:var(--primary);font:700 clamp(90px,18vw,180px)/.8 Georgia,serif;letter-spacing:-.08em}.eyebrow{margin:0 0 15px;color:#ffb070;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}h1{margin:0;font:650 clamp(38px,6vw,58px)/1.08 Georgia,serif;letter-spacing:-.035em}p{max-width:590px;margin:22px auto 0;color:#d5d6d7;font-size:17px;line-height:1.65}.actions{display:flex;flex-wrap:wrap;justify-content:center;gap:12px;margin-top:32px}.button{display:inline-flex;min-height:50px;align-items:center;justify-content:center;padding:0 24px;border:1px solid rgba(255,255,255,.45);border-radius:999px;color:#fff;font-size:13px;font-weight:800;text-decoration:none}.primary{border-color:var(--primary);background:var(--primary)}@media(max-width:480px){.actions{flex-direction:column}.button{width:100%}}
    </style>
</head>
<body>
<main class="shell">
    <section class="card">
        <a href="{{ route('frontend.home') }}"><img class="logo" src="{{ $branding['footer_logo'] }}" alt="{{ $branding['logo_alt'] }}"></a>
        <div class="code" aria-hidden="true">404</div>
        <p class="eyebrow">{{ $messages['not_found_eyebrow'] }}</p>
        <h1>{{ $messages['not_found_title'] }}</h1>
        <p>{{ $messages['not_found_body'] }}</p>
        <div class="actions"><a class="button primary" href="{{ route('frontend.home') }}">{{ $messages['home_label'] }}</a><a class="button" href="{{ route('search') }}">{{ $messages['search_label'] }}</a></div>
    </section>
</main>
</body>
</html>
