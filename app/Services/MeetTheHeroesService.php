<?php

namespace App\Services;

use App\Models\District;
use App\Models\Division;
use App\Models\LatestNews;
use App\Models\NoticeBoard;
use App\Support\BangladeshLocationLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MeetTheHeroesService
{
    private const SOCIAL_PLATFORM_LABELS = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'twitter' => 'X / Twitter',
        'x' => 'X / Twitter',
        'youtube' => 'YouTube',
        'website' => 'Website',
    ];

    public function __construct(
        private ContentSanitizer $sanitizer,
        private TranslationCenterService $translations,
        private SiteSettingService $settings,
        private SeoMetadataService $seo,
    ) {
    }

    /** @return array{title: string, presentation: array<string, mixed>, data: array<string, mixed>} */
    public function national(string $locale): array
    {
        $presentation = $this->presentation('national', $locale);

        return [
            'title' => $presentation['title'],
            'presentation' => $presentation,
            'data' => [
                'members' => $this->members($locale),
                'divisions' => $this->divisions($locale),
                'districts' => [],
                'current_division' => null,
                'current_district' => null,
                'activities' => [],
            ],
        ];
    }

    /** @return array{title: string, presentation: array<string, mixed>, data: array<string, mixed>} */
    public function division(Division $division, string $locale): array
    {
        $currentDivision = $this->divisionPayload($division, $locale);
        $presentation = $this->presentation('division', $locale, $currentDivision);
        $description = $this->localizedDescription($division, $locale);
        if ($description !== '') {
            $presentation['about_body'] = $description;
        }

        return [
            'title' => $presentation['title'],
            'presentation' => $presentation,
            'data' => [
                'members' => $this->members($locale, $division),
                'divisions' => $this->divisions($locale),
                'districts' => $this->districts($division, $locale),
                'current_division' => $currentDivision,
                'current_district' => null,
                'activities' => $this->activities($locale, $division),
            ],
        ];
    }

    /** @return array{title: string, presentation: array<string, mixed>, data: array<string, mixed>} */
    public function district(District $district, string $locale): array
    {
        $division = $district->division;
        $currentDivision = $division ? $this->divisionPayload($division, $locale) : null;
        $currentDistrict = $this->districtPayload($district, $locale);
        $presentation = $this->presentation(
            'district',
            $locale,
            $currentDivision ?? [],
            $currentDistrict
        );
        $description = $this->localizedDescription($district, $locale);
        if ($description !== '') {
            $presentation['about_body'] = $description;
        }

        return [
            'title' => $presentation['title'],
            'presentation' => $presentation,
            'data' => [
                // The reference district page leads with its managed group
                // image and activities. Keep the records available to clients
                // without forcing an individual-member carousel into that UI.
                'members' => $this->members($locale, $division, $district),
                'divisions' => $this->divisions($locale),
                'districts' => $division ? $this->districts($division, $locale) : [],
                'current_division' => $currentDivision,
                'current_district' => $currentDistrict,
                'activities' => $this->activities($locale, $division, $district),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function members(
        string $locale,
        ?Division $division = null,
        ?District $district = null,
    ): array {
        $sourceLocale = $this->memberSourceLocale($locale, $division, $district);
        $members = $this->memberQuery($sourceLocale, $division, $district)
            ->with(['teamGroup', 'division', 'district.division'])
            ->orderByDesc('order_by')
            ->orderByDesc('id')
            ->get();
        $fallbacks = $members->mapWithKeys(fn (LatestNews $member): array => [
            (string) $member->getKey() => [
                'name' => (string) $member->name,
                'description' => (string) $member->description,
                'biography' => (string) $member->biography,
                'qualification' => (string) $member->qualification,
            ],
        ])->all();
        $localized = $this->translations->localizedContentValues(
            'team_member',
            $fallbacks,
            $locale,
        );

        return $members->map(function (LatestNews $member) use ($localized, $locale): array {
            $copy = $localized[(string) $member->getKey()] ?? [];
            $name = $this->plainText($copy['name'] ?? $member->name, 255);
            $designation = $this->plainText($copy['description'] ?? $member->description, 500);
            $biography = $this->plainText($copy['biography'] ?? $member->biography, 5000);
            $qualification = $this->plainText($copy['qualification'] ?? $member->qualification, 500);
            $memberDivision = $member->district?->division ?: $member->division;
            $memberDistrict = $member->district;
            $divisionPayload = $memberDivision
                ? $this->divisionPayload($memberDivision, $locale)
                : null;
            $districtPayload = $memberDistrict
                ? $this->districtPayload($memberDistrict, $locale)
                : null;
            $profileUrl = $this->sanitizer->sanitizeUrl($member->url ?: '');

            return [
                'id' => (int) $member->getKey(),
                'heading' => $name,
                'name' => $name,
                'designation' => $designation,
                'body' => $designation,
                'biography' => $biography,
                'qualification' => $qualification,
                'image' => $this->publicImage($member->path ?: $member->image, 'our_members'),
                'image_alt' => $name,
                'url' => $profileUrl,
                'social_links' => $this->normalizedSocialLinks($member->social_links, $profileUrl),
                'division_id' => $divisionPayload['id'] ?? null,
                'division_slug' => $divisionPayload['slug'] ?? '',
                'division_name' => $divisionPayload['name'] ?? '',
                'division_label' => $divisionPayload['label'] ?? '',
                'division' => $divisionPayload,
                'district_id' => $districtPayload['id'] ?? null,
                'district_slug' => $districtPayload['slug'] ?? '',
                'district_name' => $districtPayload['name'] ?? '',
                'district_label' => $districtPayload['label'] ?? '',
                'district' => $districtPayload,
                'group' => $member->teamGroup ? [
                    'id' => (int) $member->teamGroup->getKey(),
                    'slug' => (string) $member->teamGroup->slug,
                    'name' => $this->plainText($member->teamGroup->name, 255),
                ] : null,
            ];
        })->values()->all();
    }

    private function memberSourceLocale(
        string $locale,
        ?Division $division,
        ?District $district,
    ): string {
        if ($locale === 'en' || $this->memberQuery($locale, $division, $district)->exists()) {
            return $locale;
        }

        return 'en';
    }

    private function memberQuery(
        string $locale,
        ?Division $division,
        ?District $district,
    ): Builder {
        return LatestNews::query()
            ->where('type', 'our-members')
            ->where('status', 1)
            ->where('language', $locale)
            ->where(function (Builder $query) use ($locale): void {
                $query->whereNull('team_group_id')
                    ->orWhereHas('teamGroup', fn (Builder $group) => $group
                        ->where('status', 1)
                        ->where('language', $locale));
            })
            ->where(function (Builder $query): void {
                $query->whereNull('division_id')
                    ->orWhereHas('division', fn (Builder $division) => $division->where('status', 1));
            })
            ->where(function (Builder $query): void {
                $query->whereNull('district_id')
                    ->orWhereHas('district', fn (Builder $district) => $district
                        ->where('status', 1)
                        ->whereColumn('districts.division_id', 'latest_news.division_id')
                        ->whereHas('division', fn (Builder $division) => $division->where('status', 1)));
            })
            ->when($district, fn (Builder $query) => $query
                ->where('district_id', $district->getKey()))
            ->when(!$district && $division, fn (Builder $query) => $query
                ->where('division_id', $division->getKey()))
            ->when(!$district && !$division, fn (Builder $query) => $query
                ->whereNull('division_id')
                ->whereNull('district_id'));
    }

    /** @return array<int, array<string, mixed>> */
    private function divisions(string $locale): array
    {
        return Division::query()
            ->where('status', 1)
            ->whereNotNull('slug')
            ->where('slug', '<>', '')
            ->orderBy('name')
            ->get()
            ->map(fn (Division $division): array => $this->divisionPayload($division, $locale))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function districts(Division $division, string $locale): array
    {
        return $division->districts()
            ->where('status', 1)
            ->whereNotNull('slug')
            ->where('slug', '<>', '')
            ->orderBy('name')
            ->get()
            ->each->setRelation('division', $division)
            ->map(fn (District $district): array => $this->districtPayload($district, $locale))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function divisionPayload(Division $division, string $locale): array
    {
        $slug = (string) $division->slug;
        $label = BangladeshLocationLabels::division((string) $division->name, $locale);

        return [
            'id' => (int) $division->getKey(),
            'slug' => $slug,
            'name' => (string) $division->name,
            'label' => $label,
            'description' => $this->localizedDescription($division, $locale),
            'url' => $slug !== '' ? $this->localizedRoute(
                'frontend.heroes.division',
                ['division' => $slug],
                $locale,
            ) : '',
        ];
    }

    /** @return array<string, mixed> */
    private function districtPayload(District $district, string $locale): array
    {
        $division = $district->division;
        $divisionName = (string) ($division?->name ?: '');
        $slug = (string) $district->slug;
        $label = BangladeshLocationLabels::district(
            $divisionName,
            (string) $district->name,
            $locale,
        );
        $heroAlt = $this->plainText(
            $locale === 'bn' && filled($district->hero_image_alt_bn)
                ? $district->hero_image_alt_bn
                : $district->hero_image_alt,
            255,
        );

        return [
            'id' => (int) $district->getKey(),
            'slug' => $slug,
            'name' => (string) $district->name,
            'label' => $label,
            'description' => $this->localizedDescription($district, $locale),
            'hero_image' => $this->publicImage($district->hero_image, 'districts'),
            'hero_image_alt' => $heroAlt !== ''
                ? $heroAlt
                : ($locale === 'bn' ? $label . '-এর কমিউনিটি হিরো' : $label . ' community heroes'),
            'division_id' => $division ? (int) $division->getKey() : null,
            'division_slug' => (string) ($division?->slug ?: ''),
            'division_name' => $divisionName,
            'division_label' => $division
                ? BangladeshLocationLabels::division($divisionName, $locale)
                : '',
            'url' => $slug !== '' ? $this->localizedRoute(
                'frontend.heroes.district',
                ['district' => $slug],
                $locale,
            ) : '',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function activities(
        string $locale,
        ?Division $division,
        ?District $district = null,
    ): array {
        if (!$division && !$district) {
            return [];
        }

        $activities = NoticeBoard::query()
            ->publiclyReleased()
            ->where('language', $locale)
            ->whereIn('content_kind', ['event', 'article'])
            ->where(function (Builder $query): void {
                $query->whereNull('division_id')
                    ->orWhereHas('division', fn (Builder $division) => $division->where('status', 1));
            })
            ->where(function (Builder $query): void {
                $query->whereNull('district_id')
                    ->orWhereHas('district', fn (Builder $district) => $district
                        ->where('status', 1)
                        ->whereColumn('districts.division_id', 'notice_boards.division_id')
                        ->whereHas('division', fn (Builder $division) => $division->where('status', 1)));
            })
            ->when($district, fn (Builder $query) => $query
                ->where('district_id', $district->getKey()))
            ->when(!$district && $division, function (Builder $query) use ($division): void {
                $query->where(function (Builder $scope) use ($division): void {
                    $scope->where(function (Builder $direct) use ($division): void {
                        $direct->whereNull('district_id')
                            ->where('division_id', $division->getKey());
                    })->orWhereHas('district', fn (Builder $districts) => $districts
                        ->where('division_id', $division->getKey()));
                });
            })
            ->orderByDesc('published_at')
            ->orderByDesc('order_by')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        return $activities->map(function (NoticeBoard $activity) use ($locale): array {
            $slug = trim((string) $activity->slug);
            $publishedAt = filled($activity->published_at)
                ? Carbon::parse($activity->published_at)->toDateString()
                : null;

            return [
                'id' => (int) $activity->getKey(),
                'uuid' => (string) $activity->translation_key,
                'title' => $this->plainText($activity->title, 500),
                'sub_title' => $this->plainText($activity->sub_title, 1000),
                'excerpt' => Str::limit($this->plainText($activity->description, 2000), 240),
                'slug' => $slug,
                'content_kind' => (string) $activity->content_kind,
                'location' => $this->plainText($activity->location, 500),
                'published_at' => $publishedAt,
                'event_start_at' => $activity->event_start_at?->toISOString(),
                'event_end_at' => $activity->event_end_at?->toISOString(),
                'event_status' => $activity->event_status,
                'event_attendance_mode' => $activity->event_attendance_mode,
                'image_url' => $this->publicImage($activity->getRawOriginal('image_path'), 'notice_board'),
                'image_alt' => $this->plainText($activity->image_alt, 255)
                    ?: $this->plainText($activity->title, 255),
                'division_id' => $activity->division_id ? (int) $activity->division_id : null,
                'district_id' => $activity->district_id ? (int) $activity->district_id : null,
                'archive_url' => $this->localizedRoute(
                    $activity->content_kind === 'article' ? 'frontend.news' : 'frontend.events',
                    [],
                    $locale,
                ),
                // This application intentionally uses the shared event/{slug}
                // detail controller for both published events and articles.
                'url' => $slug !== '' ? $this->localizedRoute(
                    'frontend.event',
                    ['slug' => $slug],
                    $locale,
                ) : '',
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function presentation(
        string $scope,
        string $locale,
        array $division = [],
        array $district = [],
    ): array {
        $bangla = str_starts_with(strtolower($locale), 'bn');
        $settings = (array) data_get(
            $this->settings->values($locale, true),
            'meet_the_heroes',
            [],
        );
        $divisionLabel = (string) ($division['label'] ?? $division['name'] ?? '');
        $districtLabel = (string) ($district['label'] ?? $district['name'] ?? '');
        $placeLabel = $scope === 'district' ? $districtLabel : $divisionLabel;
        $variables = [
            '{division}' => $divisionLabel,
            '{district}' => $districtLabel,
            '{place}' => $placeLabel,
        ];
        $fallbacks = match ($scope) {
            'division' => [
                'eyebrow' => $bangla ? 'টিম' : 'Team',
                'title' => $bangla
                    ? '{division} বিভাগের নায়কেরা'
                    : 'Meet the Heroes from {division} Division',
                'introduction' => '',
                'map_heading' => $bangla ? 'জেলা অনুযায়ী নায়কদের দেখুন' : 'Explore heroes by district',
                'map_help' => $bangla
                    ? 'মানচিত্রে একটি জেলা বেছে নিয়ে সেই এলাকার দল ও স্থানীয় কার্যক্রম দেখুন।'
                    : 'Choose a district on the map to meet its team and explore local activities.',
                'about_heading' => $bangla ? '{division} বিভাগ সম্পর্কে' : 'About {division}',
                'about_body' => $bangla
                    ? '{division} বিভাগের মানুষ ও কমিউনিটি-নেতৃত্বাধীন কার্যক্রম সম্পর্কে জানুন।'
                    : 'Learn about the people and community-led work connected with {division} Division.',
                'activities_heading' => $bangla ? '{division}-এর কার্যক্রম' : 'Activities at {division}',
                'activities_empty' => $bangla
                    ? 'এই এলাকায় এখনো কোনো প্রকাশিত কার্যক্রম নেই।'
                    : 'No published activities are available for this area yet.',
            ],
            'district' => [
                'eyebrow' => $bangla ? 'টিম' : 'Team',
                'title' => $bangla
                    ? '{district} জেলার নায়কেরা'
                    : 'Meet the Heroes from {district} District',
                'introduction' => '',
                'map_heading' => '',
                'map_help' => '',
                'about_heading' => $bangla ? '{district} জেলা সম্পর্কে' : 'About {district}',
                'about_body' => $bangla
                    ? '{district} জেলার মানুষ ও কমিউনিটি-নেতৃত্বাধীন কার্যক্রম সম্পর্কে জানুন।'
                    : 'Learn about the people and community-led work connected with {district} District.',
                'activities_heading' => $bangla ? '{district}-এর কার্যক্রম' : 'Activities at {district}',
                'activities_empty' => $bangla
                    ? 'এই এলাকায় এখনো কোনো প্রকাশিত কার্যক্রম নেই।'
                    : 'No published activities are available for this area yet.',
            ],
            default => [
                'eyebrow' => $bangla ? 'হিরো' : 'Heroes',
                'title' => $bangla ? 'পরিবর্তনের নায়কদের সঙ্গে পরিচিত হোন' : 'Meet the Heroes',
                'introduction' => '',
                'map_heading' => $bangla ? 'বিভাগ অনুযায়ী নায়কদের দেখুন' : 'Explore heroes by division',
                'map_help' => $bangla
                    ? 'মানচিত্রে একটি বিভাগ বেছে নিয়ে সেই এলাকার দল ও স্থানীয় কার্যক্রম দেখুন।'
                    : 'Choose a division on the map to meet its team and explore local activities.',
                'about_heading' => '',
                'about_body' => '',
                'activities_heading' => '',
                'activities_empty' => '',
            ],
        };
        $settingKeys = match ($scope) {
            'division' => [
                'eyebrow' => 'division_eyebrow',
                'title' => 'division_title_template',
                'introduction' => 'division_introduction',
                'about_heading' => 'division_about_heading',
                'about_body' => 'division_about_body',
                'map_heading' => 'division_map_heading',
                'map_help' => 'division_map_help',
            ],
            'district' => [
                'eyebrow' => 'district_eyebrow',
                'title' => 'district_title_template',
                'introduction' => 'district_introduction',
                'about_heading' => 'district_about_heading',
                'about_body' => 'district_about_body',
            ],
            default => [
                'eyebrow' => 'national_eyebrow',
                'title' => 'national_title',
                'introduction' => 'national_introduction',
                'map_heading' => 'national_map_heading',
                'map_help' => 'national_map_help',
            ],
        } + [
            'activities_heading' => 'activities_heading',
            'activities_empty' => 'activities_empty',
        ];

        $presentation = collect($fallbacks)->mapWithKeys(function (string $fallback, string $field) use (
            $settingKeys,
            $settings,
            $variables,
        ): array {
            $setting = $settings[$settingKeys[$field] ?? ''] ?? '';
            if ($setting === '' && in_array($field, ['map_heading', 'map_help'], true)) {
                // Preserve copy customized before the national and division
                // map controls were separated. A new scoped value takes
                // priority as soon as an administrator saves one.
                $setting = $settings[$field] ?? '';
            }
            $value = $this->plainText($setting, 5000);
            $value = $value !== '' ? $value : $fallback;

            return [$field => strtr($value, $variables)];
        })->all();

        // These settings are behavior flags, not localized copy. Preserve
        // their boolean types so the client can reliably distinguish an
        // explicit administrator choice from a translated string value.
        $presentation['autoplay'] = $this->booleanSetting($settings['autoplay'] ?? true, true);
        $presentation['animation_enabled'] = $this->booleanSetting(
            $settings['animation_enabled'] ?? true,
            true,
        );

        return $presentation;
    }

    private function booleanSetting(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value !== 0;
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }

    private function localizedDescription(Division|District $location, string $locale): string
    {
        $value = str_starts_with(strtolower($locale), 'bn')
            ? ($location->description_bn ?: $location->description)
            : $location->description;

        return $this->plainText($value, 5000);
    }

    private function localizedRoute(string $name, array $parameters, string $locale): string
    {
        return (string) ($this->seo->localizedUrl(route($name, $parameters), $locale) ?: '');
    }

    private function publicImage(mixed $value, string $legacyDirectory): string
    {
        $image = trim((string) $value);
        if ($image === '') {
            return '';
        }

        $safe = $this->sanitizer->sanitizeUrl($image);
        if ($safe === '') {
            return '';
        }

        if (str_starts_with($safe, '/') || preg_match('#^https?://#i', $safe)) {
            return $safe;
        }

        if (str_contains($safe, '..') || str_contains($safe, '\\')) {
            return '';
        }

        return '/storage/photos/1/' . $legacyDirectory . '/' . ltrim($safe, '/');
    }

    /** @return array<int, array{platform: string, label: string, url: string}> */
    private function normalizedSocialLinks(mixed $links, string $legacyUrl): array
    {
        if (is_string($links)) {
            $decoded = json_decode($links, true);
            $links = is_array($decoded) ? $decoded : [];
        }

        $normalized = [];
        $seen = [];
        foreach (is_array($links) ? $links : [] as $link) {
            if (!is_array($link)) {
                continue;
            }

            $url = $this->sanitizer->sanitizeUrl($link['url'] ?? '');
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if ($url === '' || !in_array($scheme, ['http', 'https'], true)) {
                continue;
            }

            $dedupeKey = strtolower(rtrim($url, '/'));
            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $platform = strtolower(trim((string) ($link['platform'] ?? '')));
            $platform = trim((string) preg_replace('/[^a-z0-9]+/', '-', $platform), '-');
            $platform = mb_substr($platform ?: 'website', 0, 50);
            $label = $this->plainText($link['label'] ?? '', 80);
            $normalized[] = [
                'platform' => $platform,
                'label' => $label !== ''
                    ? $label
                    : (self::SOCIAL_PLATFORM_LABELS[$platform] ?? ucwords(str_replace('-', ' ', $platform))),
                'url' => $url,
            ];
            $seen[$dedupeKey] = true;
        }

        $legacyScheme = strtolower((string) parse_url($legacyUrl, PHP_URL_SCHEME));
        if ($normalized === [] && in_array($legacyScheme, ['http', 'https'], true)) {
            $normalized[] = [
                'platform' => 'website',
                'label' => 'View profile',
                'url' => $legacyUrl,
            ];
        }

        return $normalized;
    }

    private function plainText(mixed $value, int $limit): string
    {
        $value = is_scalar($value) ? (string) $value : '';
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return mb_substr($value, 0, $limit);
    }
}
