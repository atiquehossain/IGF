<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;
use Throwable;

final class TransactionalEmailDesignService
{
    private const PALETTES = [
        'brand_warm' => [
            'background_color' => '#f7efe8',
            'panel_color' => '#ffffff',
            'text_color' => '#202124',
            'button_color' => '#8c3d0a',
            'button_text_color' => '#ffffff',
            'border_color' => '#ead8ca',
        ],
        'clean' => [
            'background_color' => '#f5f5f5',
            'panel_color' => '#ffffff',
            'text_color' => '#202124',
            'button_color' => '#8c3d0a',
            'button_text_color' => '#ffffff',
            'border_color' => '#dedede',
        ],
        'high_contrast' => [
            'background_color' => '#e9edf0',
            'panel_color' => '#ffffff',
            'text_color' => '#111111',
            'button_color' => '#202124',
            'button_text_color' => '#ffffff',
            'border_color' => '#202124',
        ],
    ];

    private const WIDTHS = [
        'compact' => '560px',
        'standard' => '640px',
        'wide' => '720px',
    ];

    private const CORNERS = [
        'square' => '0',
        'rounded' => '12px',
        'soft' => '20px',
    ];

    public function __construct(private readonly SiteSettingService $settings)
    {
    }

    /**
     * Return only allowlisted CSS tokens and escaped-by-Blade text. This
     * intentionally excludes sender identity, recipients, headers, transport,
     * credentials and attachments.
     *
     * @return array<string, bool|string>
     */
    public function forLocale(?string $locale = null): array
    {
        $locale = in_array($locale, ['en', 'bn'], true) ? (string) $locale : 'en';
        $siteSettings = $this->configuredSiteSettings($locale);
        $configured = (array) data_get($siteSettings, 'email_design', []);
        $presentation = $this->allowlisted(
            $configured['presentation'] ?? null,
            array_keys(self::PALETTES),
            'brand_warm'
        );
        $width = $this->allowlisted(
            $configured['content_width'] ?? null,
            array_keys(self::WIDTHS),
            'standard'
        );
        $corners = $this->allowlisted(
            $configured['corner_style'] ?? null,
            array_keys(self::CORNERS),
            'rounded'
        );
        $siteName = $this->plainText(
            data_get($siteSettings, 'branding.site_name'),
            120
        );
        $brandHeading = $this->plainText($configured['brand_heading'] ?? null, 120);
        $footerText = $this->plainText($configured['footer_text'] ?? null, 500, true);

        if ($brandHeading === '') {
            $brandHeading = $siteName !== ''
                ? $siteName
                : ($locale === 'bn' ? 'ইগনাইট গ্লোবাল ফাউন্ডেশন' : 'Ignite Global Foundation');
        }

        return self::PALETTES[$presentation] + [
            'presentation' => $presentation,
            'content_width' => self::WIDTHS[$width],
            'corner_radius' => self::CORNERS[$corners],
            'show_brand_name' => filter_var(
                $configured['show_brand_name'] ?? true,
                FILTER_VALIDATE_BOOLEAN
            ),
            'brand_heading' => $brandHeading,
            'footer_text' => $footerText,
        ];
    }

    /** @return array<string, mixed> */
    private function configuredSiteSettings(string $locale): array
    {
        try {
            if (!Schema::hasTable('site_settings')) {
                return [];
            }

            return $this->settings->values($locale);
        } catch (Throwable) {
            // Email delivery must retain a safe code default while a new
            // installation is migrating or if an invalid row was inserted
            // outside the guarded Website Customizer.
            return [];
        }
    }

    /** @param array<int, string> $allowed */
    private function allowlisted(mixed $value, array $allowed, string $fallback): string
    {
        return is_string($value) && in_array($value, $allowed, true)
            ? $value
            : $fallback;
    }

    private function plainText(mixed $value, int $limit, bool $multiline = false): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = str_replace("\0", '', strip_tags($value));
        $value = $multiline
            ? preg_replace('/[\t ]+/', ' ', str_replace(["\r\n", "\r"], "\n", $value))
            : preg_replace('/\s+/u', ' ', $value);

        return mb_substr(trim((string) $value), 0, $limit);
    }
}
