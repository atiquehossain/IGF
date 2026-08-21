<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    @php
      $pageProps = data_get($page ?? [], 'props', []);
      // Public SEO has one explicit authority order, mirrored by App.vue:
      // defaults < controller fallback < curated route < owned content record.
      $seo = (object) array_merge(
        (array) data_get($pageProps, 'seoDefaults', ['canonical_url' => url()->current()]),
        (array) data_get($pageProps, 'meta_tag', []),
        (array) data_get($pageProps, 'routeSeo', []),
        (array) data_get($pageProps, 'contentSeo', [])
      );
      $seoService = app(\App\Services\SeoMetadataService::class);
      $seoLocale = (string) data_get($pageProps, 'seoLocale.current', app()->getLocale());
      $seoDefaultLocale = (string) data_get($pageProps, 'seoLocale.default', config('app.fallback_locale', 'en'));
      $seoPublicLocales = (array) data_get($pageProps, 'seoLocale.public', [$seoDefaultLocale]);
      $seo->canonical_url = $seoService->localizedUrl(
        $seo->canonical_url ?? url()->current(),
        $seoLocale,
        $seoDefaultLocale
      );
      $seoAlternates = $seoService->alternateUrls($seo->canonical_url, $seoPublicLocales, $seoDefaultLocale);
      $appName = (string) data_get($pageProps, 'appName', config('app.name'));
    @endphp
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ $seo->meta_title ?? $appName }}</title>

    <meta inertia="description" name="description" content="{{ $seo->meta_description ?? '' }}"/>
    <meta inertia="robots" name="robots" content="{{ $seo->robots ?? 'index,follow' }}"/>
    <link inertia="canonical" rel="canonical" href="{{ $seo->canonical_url }}">
    @foreach($seoAlternates['links'] as $alternate)
    <link inertia="alternate-{{ $alternate['locale'] }}" rel="alternate" hreflang="{{ $alternate['locale'] }}" href="{{ $alternate['url'] }}">
    @endforeach
    <link inertia="alternate-x-default" rel="alternate" hreflang="x-default" href="{{ $seoAlternates['x_default'] }}">

    <!-- Twitter Card data -->
    <meta inertia="twitter:card" name="twitter:card" content="{{ $seo->twitter_card ?? 'summary_large_image' }}" />
    <meta inertia="twitter:title" name="twitter:title" content="{{ $seo->twitter_title ?? $seo->meta_title ?? '' }}" />
    <meta inertia="twitter:description" name="twitter:description" content="{{ $seo->twitter_description ?? $seo->meta_description ?? '' }}" />
    @if(!empty($seo->twitter_image) || !empty($seo->og_image))<meta inertia="twitter:image" name="twitter:image" content="{{ $seo->twitter_image ?? $seo->og_image }}" />@endif

    <!-- Open Graph data -->
    <meta inertia="og:title" property="og:title" content="{{ $seo->og_title ?? $seo->meta_title ?? '' }}" />
    <meta inertia="og:type" property="og:type" content="website" />
    <meta inertia="og:url" property="og:url" content="{{ $seo->canonical_url }}" />
    @if(!empty($seo->og_image))<meta inertia="og:image" property="og:image" content="{{ $seo->og_image }}" />@endif
    <meta inertia="og:description" property="og:description" content="{{ $seo->og_description ?? $seo->meta_description ?? '' }}" />
    <meta inertia="og:site_name" property="og:site_name" content="{{ $appName }}" />
    @if(!empty($seo->schema_markup))
      <script inertia="structured-data" type="application/ld+json">{!! json_encode($seo->schema_markup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif

    @php
      try {
        $favicon = \App\Models\SiteSetting::valueFor('branding', 'favicon', '*', '/image/favicon/favicon-32x32.png');
        $analyticsId = \App\Models\SiteSetting::valueFor('analytics', 'google_analytics_id', '*', '');
      } catch (\Throwable $exception) {
        $favicon = '/image/favicon/favicon-32x32.png';
        $analyticsId = '';
      }
      $faviconUrl = filter_var($favicon, FILTER_VALIDATE_URL) ? $favicon : asset(ltrim($favicon, '/'));
    @endphp
    <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $faviconUrl }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ $faviconUrl }}">
    <link rel="manifest" href="{{asset('image/favicon/site.webmanifest')}}">
    @routes
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
  </head>
  <body>
    @inertia
    @if(preg_match('/^G-[A-Z0-9]+$/', $analyticsId))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $analyticsId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        window.igfAnalyticsId = @json($analyticsId);

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());
        gtag('config', window.igfAnalyticsId);
    </script>
    @endif
    <!-- <script src="{{ asset('/sw.js') }}"></script> -->
    <script type="module">
        // if ('serviceWorker' in navigator) {
        //     navigator.serviceWorker.register('/sw.js', {
        //         scope: '.'
        //     }).then(function(registration) {
        //         // Registration was successful
        //         console.log('ServiceWorker registration successful with scope: ', registration.scope);
        //     }, function(err) {
        //         // registration failed :(
        //         console.log('ServiceWorker registration failed: ', err);
        //     });
        // }
    </script>
  </body>
</html>
