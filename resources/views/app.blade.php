<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    @php
      $pageProps = data_get($page ?? [], 'props', []);
      // Public SEO has one explicit authority order, mirrored by App.vue:
      // defaults < controller fallback < curated route < owned content record
      // < deployment indexing policy.
      $seo = (object) array_merge(
        (array) data_get($pageProps, 'seoDefaults', ['canonical_url' => url()->current()]),
        (array) data_get($pageProps, 'meta_tag', []),
        (array) data_get($pageProps, 'routeSeo', []),
        (array) data_get($pageProps, 'contentSeo', []),
        (array) data_get($pageProps, 'seoPolicy', app(\App\Services\SeoIndexingPolicy::class)->metadataOverride())
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
      // Empty higher-authority fields mean "use the managed brand fallback",
      // not "emit an invalid social card" and not "reuse stale route media".
      $socialFallbackImage = $seoService->absolutePublicImageUrl(
        data_get($pageProps, 'seoDefaults.og_image', '')
      );
      $ogCandidate = collect([
        $seo->og_image ?? null,
        $seo->meta_image ?? null,
        $seo->twitter_image ?? null,
      ])->first(fn ($value) => is_string($value) && trim($value) !== '');
      $seo->og_image = $seoService->absolutePublicImageUrl(
        $ogCandidate ?: $socialFallbackImage
      ) ?: $socialFallbackImage;
      $seo->twitter_image = $seoService->absolutePublicImageUrl(
        $seo->twitter_image ?? $seo->og_image
      ) ?: $seo->og_image;
      $seo->social_image_alt = trim((string) data_get($pageProps, 'seoDefaults.social_image_alt', ''));
      $seo->uses_default_og_image = $socialFallbackImage !== '' && $seo->og_image === $socialFallbackImage;
      $seo->uses_default_twitter_image = $socialFallbackImage !== '' && $seo->twitter_image === $socialFallbackImage;
      $seoAlternates = $seoService->alternateUrls($seo->canonical_url, $seoPublicLocales, $seoDefaultLocale);
      $appName = (string) data_get($pageProps, 'appName', config('app.name'));
      if (empty($seo->schema_markup)) {
        $seo->schema_markup = app(\App\Services\PublicStructuredDataService::class)->fallbackForMetadata(
          (array) $seo,
          (array) data_get($pageProps, 'seoSchemaIdentity', [])
        );
      }
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
    @if($seo->uses_default_twitter_image && $seo->social_image_alt !== '')<meta inertia="twitter:image:alt" name="twitter:image:alt" content="{{ $seo->social_image_alt }}" />@endif

    <!-- Open Graph data -->
    <meta inertia="og:title" property="og:title" content="{{ $seo->og_title ?? $seo->meta_title ?? '' }}" />
    <meta inertia="og:type" property="og:type" content="website" />
    <meta inertia="og:url" property="og:url" content="{{ $seo->canonical_url }}" />
    @if(!empty($seo->og_image))<meta inertia="og:image" property="og:image" content="{{ $seo->og_image }}" />@endif
    @if($seo->uses_default_og_image && $seo->social_image_alt !== '')<meta inertia="og:image:alt" property="og:image:alt" content="{{ $seo->social_image_alt }}" />@endif
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
    @routes('frontend')
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
