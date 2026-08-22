<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Helper\MyMenu;
use App\Helper\Translation;
use Auth;
use App\Services\SiteSettingService;
use App\Services\LocalizationManager;
use App\Services\SeoMetadataService;
use App\Services\SeoIndexingPolicy;
use App\Services\PublicStructuredDataService;
use App\Models\SplashScreen;
use App\Services\ContentSanitizer;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request)
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request)
    {
        return array_merge(parent::share($request), [
            'appName' => fn () => data_get(
                app(SiteSettingService::class)->values(app()->getLocale(), true),
                'branding.site_name',
                config('app.name')
            ),
            'locale' => fn () => app()->getLocale(),
            'user' => fn () => Auth::user() ?? null,
            'language' => fn () => Translation::language(app()->getLocale())['vue'],
            'appMenus' => fn () => MyMenu::frontMenus(app()->getLocale()),
            'appFooterMenus' => fn () => MyMenu::frontMenus(app()->getLocale(), 'footer'),
            'siteSettings' => fn () => app(SiteSettingService::class)->values(app()->getLocale(), true),
            'splashScreen' => function () {
                $splash = SplashScreen::query()
                    ->where('status', 1)
                    ->where('language', app()->getLocale())
                    ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
                    ->latest('published_at')
                    ->latest('id')
                    ->first();
                if (!$splash) {
                    return null;
                }
                $splash->setAttribute('details', app(ContentSanitizer::class)->sanitizeHtml($splash->details));

                return [
                    'uuid' => (string) $splash->uuid,
                    'title' => (string) $splash->title,
                    'details' => (string) $splash->details,
                    'published_at' => $splash->published_at?->format('Y-m-d'),
                    'public_version' => $splash->publicVersion(),
                ];
            },
            'publicLocaleSwitcherEnabled' => fn () => app(LocalizationManager::class)->switcherEnabled(),
            'routeSeo' => fn () => $request->attributes->get('route_seo', []),
            'seoDefaults' => function () use ($request): array {
                $metadata = app(SeoMetadataService::class);
                $social = $metadata->socialImageFallback(app()->getLocale());

                return [
                    'canonical_url' => $metadata->localizedUrl($request->url(), app()->getLocale()),
                    'og_image' => $social['image'],
                    'twitter_image' => $social['image'],
                    'social_image_alt' => $social['alt'],
                ];
            },
            // Identity is server-owned and reused when the final merged page
            // metadata does not contain an explicit schema document. The page
            // node itself is composed after all SEO authority layers merge.
            'seoSchemaIdentity' => fn () => app(PublicStructuredDataService::class)->identityDocument(),
            // This policy is deliberately merged after every controller,
            // route, and content owner in both the raw and hydrated heads.
            'seoPolicy' => fn () => app(SeoIndexingPolicy::class)->metadataOverride(),
            'seoLocale' => fn () => [
                'current' => app()->getLocale(),
                'default' => config('app.fallback_locale', 'en'),
                'public' => app(LocalizationManager::class)->publicLocales(),
                'query_parameter' => config('seo.locale_query_parameter', 'lang'),
            ],
            'seoAlternates' => function () use ($request): array {
                $service = app(SeoMetadataService::class);
                $defaultLocale = (string) config('app.fallback_locale', 'en');
                $routeCanonical = data_get($request->attributes->get('route_seo', []), 'canonical_url');
                $canonical = (string) $service->localizedUrl(
                    $routeCanonical ?: $request->url(),
                    (string) app()->getLocale(),
                    $defaultLocale
                );

                return $service->alternateUrls(
                    $canonical,
                    app(LocalizationManager::class)->publicLocales(),
                    $defaultLocale
                );
            },
        ]);
    }

}
