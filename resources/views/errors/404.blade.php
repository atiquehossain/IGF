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
    $design = array_merge($defaults('design'), $publicSettings['design'] ?? []);
    $preset = static fn (string $field, array $choices, string $fallback): array => $choices[(string) ($design[$field] ?? '')] ?? $choices[$fallback];
    $designTokens = array_merge(
        $preset('font_pairing', [
            'editorial' => ['--igf-font-body' => "'Hanken Grotesk',Arial,sans-serif", '--igf-font-heading' => "'Literata',Georgia,serif"],
            'modern' => ['--igf-font-body' => "'Hanken Grotesk',Arial,sans-serif", '--igf-font-heading' => "'Hanken Grotesk',Arial,sans-serif"],
            'classic' => ['--igf-font-body' => 'Arial,Helvetica,sans-serif', '--igf-font-heading' => "Georgia,'Times New Roman',serif"],
        ], 'editorial'),
        $preset('content_width', [
            'compact' => ['--igf-content-width' => '1040px'],
            'standard' => ['--igf-content-width' => '1240px'],
            'wide' => ['--igf-content-width' => '1400px'],
        ], 'standard'),
        $preset('heading_size', [
            'compact' => ['--igf-heading-1' => 'clamp(38px,5vw,64px)'],
            'standard' => ['--igf-heading-1' => 'clamp(42px,6vw,76px)'],
            'large' => ['--igf-heading-1' => 'clamp(48px,7vw,88px)'],
        ], 'standard'),
        $preset('body_text_size', [
            'compact' => ['--igf-body-size' => '15px'],
            'standard' => ['--igf-body-size' => '17px'],
            'large' => ['--igf-body-size' => '19px'],
        ], 'standard'),
        $preset('section_spacing', [
            'compact' => ['--igf-section-block' => 'clamp(56px,7vw,88px)'],
            'standard' => ['--igf-section-block' => 'clamp(72px,9vw,120px)'],
            'generous' => ['--igf-section-block' => 'clamp(88px,11vw,144px)'],
        ], 'standard'),
        $preset('button_shape', [
            'square' => ['--igf-button-radius' => '4px'],
            'rounded' => ['--igf-button-radius' => '10px'],
            'pill' => ['--igf-button-radius' => '999px'],
        ], 'pill'),
        $preset('logo_size', [
            'compact' => ['--igf-not-found-logo-width' => '112px'],
            'standard' => ['--igf-not-found-logo-width' => '130px'],
            'large' => ['--igf-not-found-logo-width' => '148px'],
        ], 'standard'),
        $preset('shadow_density', [
            'flat' => ['--igf-shadow-control' => 'none'],
            'subtle' => ['--igf-shadow-control' => '0 5px 14px rgba(255,117,0,.18)'],
            'strong' => ['--igf-shadow-control' => '0 8px 22px rgba(255,117,0,.3)'],
        ], 'subtle'),
    );
    $safeColor = static fn (mixed $value, string $fallback): string => is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) === 1 ? $value : $fallback;
    $primary = $safeColor($theme['primary_color'] ?? null, '#ff7500');
    $ink = $safeColor($theme['ink_color'] ?? null, '#191c1d');
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,follow">
    <title>{{ $messages['not_found_eyebrow'] }} | {{ $branding['site_name'] }}</title>
    <style>
        :root{--primary:{{ $primary }};--ink:{{ $ink }};@foreach($designTokens as $token => $value){{ $token }}:{!! $value !!};@endforeach}*{box-sizing:border-box}body{margin:0;background:#202223;color:#fff;font-family:var(--igf-font-body,'Hanken Grotesk',Arial,sans-serif)}.shell{display:grid;width:min(100%,var(--igf-content-width,1240px));min-height:100vh;margin-inline:auto;padding:var(--igf-section-block,clamp(72px,9vw,120px)) 20px;place-items:center;text-align:center}.card{width:min(100%,720px)}.logo{display:block;width:var(--igf-not-found-logo-width,130px);height:auto;margin:0 auto clamp(42px,6vw,72px)}.code{margin-bottom:16px;color:var(--primary);font:700 clamp(90px,18vw,180px)/.8 var(--igf-font-heading,'Literata',Georgia,serif);letter-spacing:-.08em}.eyebrow{margin:0 0 15px;color:#ffb070;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}h1{margin:0;font:650 var(--igf-heading-1,clamp(42px,6vw,76px))/1.08 var(--igf-font-heading,'Literata',Georgia,serif);letter-spacing:-.035em}p{max-width:590px;margin:22px auto 0;color:#d5d6d7;font-size:var(--igf-body-size,17px);line-height:1.65}.actions{display:flex;flex-wrap:wrap;justify-content:center;gap:12px;margin-top:32px}.button{display:inline-flex;min-height:50px;align-items:center;justify-content:center;padding:0 24px;border:1px solid rgba(255,255,255,.45);border-radius:var(--igf-button-radius,999px);color:#fff;font-size:13px;font-weight:800;text-decoration:none}.button:focus-visible{outline:3px solid #fff;outline-offset:3px}.primary{border-color:var(--primary);background:var(--primary);box-shadow:var(--igf-shadow-control,0 5px 14px rgba(255,117,0,.18))}@media(max-width:480px){.actions{flex-direction:column}.button{width:100%}}
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
