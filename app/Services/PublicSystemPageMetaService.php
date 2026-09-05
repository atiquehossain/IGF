<?php

namespace App\Services;

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PublicSystemPageMetaService
{
    /**
     * Special public routes that can render completely from localized Website
     * Customizer settings when no translated Page model exists.
     *
     * @var array<string, string>
     */
    private const LOCALIZED_ROUTE_FALLBACKS = [
        'frontend.sponsor_child' => 'sponsor_page.eyebrow',
    ];

    public function __construct(private SiteSettingService $settings)
    {
    }

    /**
     * Build a localized metadata fallback for a public system page.
     *
     * The visible page title and description come from existing Website
     * Customizer fields, so an editor changes the page and its fallback search
     * presentation in one place. Curated Search & Sharing records remain the
     * higher-authority layer whenever one exists for the route. Route SEO is
     * intentionally kept in Inertia's separate `routeSeo` layer instead of
     * being copied into this controller fallback.
     *
     * @param  array{title?: string, meta_title?: string, description?: string}  $fallback
     * @return array{title: string, meta_tag: array<string, mixed>}
     */
    public function resolve(
        Request $request,
        string $titlePath,
        ?string $descriptionPath,
        array $fallback = [],
    ): array {
        $locale = (string) app()->getLocale();
        $values = $this->settings->values($locale, true);
        $siteName = $this->plainText(
            data_get($values, 'branding.site_name'),
            (string) config('app.name', 'Ignite Global Foundation'),
            100,
        );
        $fallbackTitle = $this->plainText(
            $fallback['title'] ?? '',
            '',
            120,
        );
        $configuredTitle = $this->configuredDefault($titlePath, $locale);
        $pageTitle = $this->plainText(
            data_get($values, $titlePath),
            $configuredTitle ?: $fallbackTitle,
            120,
        );
        if ($pageTitle === '') {
            $pageTitle = $siteName;
        }

        $configuredDescription = $descriptionPath === null
            ? ''
            : $this->configuredDefault($descriptionPath, $locale, 320);
        $description = $this->plainText(
            $descriptionPath === null ? null : data_get($values, $descriptionPath),
            $configuredDescription ?: (string) ($fallback['description'] ?? ''),
            320,
        );
        if ($description === '') {
            $description = $pageTitle === $siteName
                ? $siteName
                : $pageTitle.' — '.$siteName;
        }

        $keywords = collect([$pageTitle, $siteName])
            ->filter()
            ->unique(fn (string $value): string => mb_strtolower($value))
            ->implode(', ');

        $metaPageTitle = $pageTitle;
        $establishedTitle = $this->plainText($fallback['meta_title'] ?? '', '', 120);
        $configuredDefault = $configuredTitle;
        if ($locale === (string) config('app.fallback_locale', 'en')
            && $establishedTitle !== ''
            && $configuredDefault !== ''
            && $pageTitle === $configuredDefault) {
            // Keep the established English search title until an editor
            // actually customizes the managed display label.
            $metaPageTitle = $establishedTitle;
        }

        $fallbackMeta = [
            'meta_keyword' => mb_substr($keywords, 0, 255),
            'meta_title' => $this->brandedTitle($metaPageTitle, $siteName),
            'meta_description' => $description,
        ];

        return [
            'title' => $pageTitle,
            'meta_tag' => $fallbackMeta,
        ];
    }

    /**
     * Build a localized metadata fallback for an editor-managed content item.
     *
     * The content title and description are already localized by the owning
     * model. Branding is resolved through Website Customizer. Controllers may
     * then pass this result to SeoMetadataService::metaForModel(), which keeps
     * the item's Search & Sharing record as the final authority. Static route
     * SEO remains in the separate `routeSeo` response layer.
     *
     * @return array<string, mixed>
     */
    public function forContent(
        string $title,
        ?string $description = null,
        ?Request $request = null,
    ): array {
        $values = $this->settings->values((string) app()->getLocale(), true);
        $siteName = $this->plainText(
            data_get($values, 'branding.site_name'),
            (string) config('app.name', 'Ignite Global Foundation'),
            100,
        );
        $pageTitle = $this->plainText($title, $siteName, 120);
        $summary = $this->plainText($description, '', 320);
        if ($summary === '') {
            $summary = $pageTitle === $siteName
                ? $siteName
                : $pageTitle.' — '.$siteName;
        }

        $keywords = collect([$pageTitle, $siteName])
            ->filter()
            ->unique(fn (string $value): string => mb_strtolower($value))
            ->implode(', ');

        return [
            'meta_keyword' => mb_substr($keywords, 0, 255),
            'meta_title' => $this->brandedTitle($pageTitle, $siteName),
            'meta_description' => $summary,
        ];
    }

    /**
     * Build the fallback underneath a Page model's dedicated Search & Sharing
     * record while retaining the older inline SEO fields when editors use them.
     *
     * @return array<string, mixed>
     */
    public function forPage(
        Page $page,
        ?Request $request = null,
        ?string $descriptionFallback = null,
    ): array {
        $description = $this->plainText(
            $page->meta_description
                ?: $page->sub_title
                ?: $page->description
                ?: $descriptionFallback,
            '',
            320,
        );
        $metadata = $this->forContent((string) $page->name, $description, $request);
        $legacyTitle = $this->plainText($page->meta_title, '', 180);
        $legacyKeywords = $this->plainText($page->meta_keyword, '', 255);
        if ($legacyTitle !== '') {
            $metadata['meta_title'] = $legacyTitle;
        }
        if ($legacyKeywords !== '') {
            $metadata['meta_keyword'] = $legacyKeywords;
        }

        $thumbnail = trim((string) $page->getRawOriginal('thumbnail'));
        if ($thumbnail !== ''
            && !str_contains($thumbnail, '/')
            && parse_url($thumbnail, PHP_URL_SCHEME) === null) {
            $thumbnail = '/storage/photos/1/page/'.$thumbnail;
        }
        $metadata['meta_image'] = $thumbnail;

        return $metadata;
    }

    /**
     * Determine whether a route has a real, public title fallback for the
     * requested locale and therefore does not depend on a translated Page row.
     */
    public function supportsLocalizedRouteFallback(string $routeName, string $locale): bool
    {
        $path = self::LOCALIZED_ROUTE_FALLBACKS[$routeName] ?? null;
        if (!is_string($path)) {
            return false;
        }

        [$group, $key] = array_pad(explode('.', $path, 2), 2, '');
        $field = (array) config("site-settings.groups.{$group}.fields.{$key}", []);
        if (!($field['public'] ?? false) || !($field['localized'] ?? false)) {
            return false;
        }

        if ($this->plainText(data_get($field, "localized_defaults.{$locale}"), '', 120) !== '') {
            return true;
        }

        if (!Schema::hasTable('site_settings')) {
            return false;
        }

        $setting = SiteSetting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->where('locale', $locale)
            ->where('is_public', true)
            ->first();

        return $setting !== null
            && $this->plainText($setting->typed_value, '', 120) !== '';
    }

    private function brandedTitle(string $pageTitle, string $siteName): string
    {
        if ($siteName === '' || mb_stripos($pageTitle, $siteName) !== false) {
            return mb_substr($pageTitle, 0, 180);
        }

        return mb_substr($pageTitle.' | '.$siteName, 0, 180);
    }

    private function configuredDefault(string $path, string $locale, int $limit = 120): string
    {
        [$group, $key] = array_pad(explode('.', $path, 2), 2, '');
        if ($group === '' || $key === '') {
            return '';
        }

        $field = (array) config("site-settings.groups.{$group}.fields.{$key}", []);
        $value = data_get($field, "localized_defaults.{$locale}", $field['default'] ?? '');

        return $this->plainText($value, '', $limit);
    }

    private function plainText(mixed $value, string $fallback, int $limit): string
    {
        $value = is_scalar($value) ? (string) $value : '';
        $value = trim($value) === '' ? $fallback : $value;
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return mb_substr($value, 0, $limit);
    }
}
