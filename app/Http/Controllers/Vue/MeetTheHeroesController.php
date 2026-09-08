<?php

namespace App\Http\Controllers\Vue;

use App\Helper\StaticUtil;
use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Division;
use App\Services\LocalizationManager;
use App\Services\MeetTheHeroesService;
use App\Services\PublicStructuredDataService;
use App\Services\SeoMetadataService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MeetTheHeroesController extends Controller
{
    public function __construct(
        private MeetTheHeroesService $heroes,
        private SeoMetadataService $seo,
        private PublicStructuredDataService $structuredData,
        private LocalizationManager $localization,
    ) {
    }

    public function index(Request $request): Response
    {
        $locale = (string) app()->getLocale();

        return $this->render(
            $request,
            'national',
            $this->heroes->national($locale),
            route('frontend.heroes.index'),
            'frontend.heroes.index',
        );
    }

    public function division(Request $request, string $division): Response
    {
        $record = Division::query()
            ->where('status', 1)
            ->where('slug', $division)
            ->firstOrFail();

        // Some database collations compare slugs case-insensitively. Public
        // geography URLs remain canonical and deterministic across drivers.
        abort_unless(hash_equals((string) $record->slug, $division), 404);

        return $this->render(
            $request,
            'division',
            $this->heroes->division($record, (string) app()->getLocale()),
            route('frontend.heroes.division', ['division' => $record->slug]),
            'frontend.heroes.division',
        );
    }

    public function district(Request $request, string $district): Response
    {
        $record = District::query()
            ->with('division')
            ->where('status', 1)
            ->where('slug', $district)
            ->whereHas('division', fn ($query) => $query->where('status', 1))
            ->firstOrFail();

        abort_unless(hash_equals((string) $record->slug, $district), 404);

        return $this->render(
            $request,
            'district',
            $this->heroes->district($record, (string) app()->getLocale()),
            route('frontend.heroes.district', ['district' => $record->slug]),
            'frontend.heroes.district',
        );
    }

    /** @param array{title: string, presentation: array<string, mixed>, data: array<string, mixed>} $page */
    private function render(
        Request $request,
        string $scope,
        array $page,
        string $baseUrl,
        string $routeName,
    ): Response {
        $locale = (string) app()->getLocale();
        $canonical = (string) ($this->seo->localizedUrl($baseUrl, $locale) ?: $baseUrl);
        $description = (string) ($page['presentation']['introduction'] ?? '');
        if ($description === '') {
            $description = str_starts_with(strtolower($locale), 'bn')
                ? 'ইগনাইট গ্লোবাল ফাউন্ডেশনের কমিউনিটি হিরো ও স্থানীয় কার্যক্রম সম্পর্কে জানুন।'
                : 'Meet Ignite Global Foundation community heroes and explore their local activities across Bangladesh.';
        }
        $image = $this->primaryImage($page['data']);
        $fallback = [
            'meta_keyword' => 'Ignite Global Foundation heroes, community leaders, Bangladesh',
            'meta_title' => $page['title'] . ' | Ignite Global Foundation',
            'meta_description' => $description,
            'meta_image' => $this->seo->absolutePublicImageUrl($image),
            'canonical_url' => $canonical,
        ];
        $curated = (array) $request->attributes->get(
            'route_seo',
            $this->seo->metaForRoute($routeName, $locale),
        );
        $metaTag = $this->metadata($fallback, $curated);
        // Parameterized regional routes must never inherit the national
        // canonical even if a future SEO record is accidentally shared.
        $metaTag['canonical_url'] = $canonical;
        if (empty($metaTag['schema_markup'])) {
            $metaTag['schema_markup'] = $this->structuredData->collection(
                $page['title'],
                (string) $metaTag['meta_description'],
                $canonical,
                $this->breadcrumbs($scope, $page['data'], $canonical),
            );
        }

        StaticUtil::ssr($metaTag);

        $data = $page['data'];
        // Keep the copy alongside the content payload for the dedicated page
        // while retaining the conventional top-level presentation prop.
        $data['presentation'] = $page['presentation'];

        return Inertia::render('heroes')->with([
            'status' => true,
            'scope' => $scope,
            'title' => $page['title'],
            'presentation' => $page['presentation'],
            'meta_tag' => $metaTag,
            'contentSeo' => $metaTag,
            'seoAlternates' => $this->seo->alternateUrls(
                $canonical,
                $this->localization->publicLocales(),
            ),
            'data' => $data,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function primaryImage(array $data): string
    {
        $districtImage = trim((string) data_get($data, 'current_district.hero_image', ''));
        if ($districtImage !== '') {
            return $districtImage;
        }

        return trim((string) data_get($data, 'members.0.image', ''));
    }

    /** @param array<string, mixed> $fallback @param array<string, mixed> $curated */
    private function metadata(array $fallback, array $curated): array
    {
        foreach ($curated as $key => $value) {
            if ($value !== null && $value !== '') {
                $fallback[$key] = $value;
            }
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array{name: string, url: string}>
     */
    private function breadcrumbs(string $scope, array $data, string $canonical): array
    {
        $locale = (string) app()->getLocale();
        $bangla = str_starts_with(strtolower($locale), 'bn');
        $items = [[
            'name' => $bangla ? 'হোম' : 'Home',
            'url' => (string) $this->seo->localizedUrl(route('frontend.home'), $locale),
        ], [
            'name' => $bangla ? 'হিরোদের সঙ্গে পরিচিত হোন' : 'Meet the Heroes',
            'url' => (string) $this->seo->localizedUrl(route('frontend.heroes.index'), $locale),
        ]];

        if ($scope === 'district' && is_array($data['current_division'] ?? null)) {
            $items[] = [
                'name' => (string) data_get($data, 'current_division.label', ''),
                'url' => (string) data_get($data, 'current_division.url', ''),
            ];
        }
        if ($scope !== 'national') {
            $current = $scope === 'district'
                ? (string) data_get($data, 'current_district.label', '')
                : (string) data_get($data, 'current_division.label', '');
            $items[] = ['name' => $current, 'url' => $canonical];
        }

        return $items;
    }
}
