<?php

namespace App\Services;

use App\Models\AnnualReport;
use App\Models\Category;
use App\Models\DonationType;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\SeoAuditIssue;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

final class TechnicalSeoEditorLinkService
{
    /** @var array<string,list<array{label:string,url:string,permission:?string,kind:string}>> */
    private array $cache = [];

    public function __construct(
        private TechnicalSeoUrlPolicy $urls,
        private SeoRouteRegistry $routes,
    ) {
    }

    /** @return list<array{label:string,url:string,permission:?string,kind:string}> */
    public function actionsFor(SeoAuditIssue $issue): array
    {
        $source = trim((string) $issue->source_path);
        if (isset($this->cache[$source])) {
            return $this->cache[$source];
        }
        if ($source === '' || $this->urls->internalAuditTarget($source, '/') !== $source) {
            return $this->cache[$source] = [];
        }

        $parts = parse_url($source);
        $path = is_array($parts) ? (string) ($parts['path'] ?? '/') : '/';
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $localeKey = (string) config('seo.locale_query_parameter', 'lang');
        $locale = is_string($query[$localeKey] ?? null)
            ? (string) $query[$localeKey]
            : (string) config('app.fallback_locale', 'en');
        $actions = [[
            'label' => 'View affected page',
            'url' => rtrim((string) config('app.url'), '/') . $source,
            'permission' => null,
            'kind' => 'preview',
        ]];

        $routeName = $this->routes->routes()->search($path, true);
        if (is_string($routeName)) {
            if (Route::has('seo.index')) {
                $actions[] = [
                    'label' => 'Open SEO settings',
                    'url' => route('seo.index', ['route' => $routeName, 'locale' => $locale]) . '#seo-editor',
                    'permission' => 'seo.index',
                    'kind' => 'seo',
                ];
            }
            $definition = $this->routes->definition($routeName) ?? [];
            $page = $this->managedPage((string) ($definition['page_slug'] ?? ''), $locale);
            if ($page) {
                $this->appendContentAction($actions, $page, 'page');
            }

            return $this->cache[$source] = $this->unique($actions);
        }

        [$model, $type] = $this->dynamicTarget($path, $locale);
        if ($model && $type) {
            $this->appendContentAction($actions, $model, $type);
            if (Route::has('seo.content.edit')) {
                $actions[] = [
                    'label' => 'Open SEO settings',
                    'url' => route('seo.content.edit', [
                        'type' => $type,
                        'id' => $model->getKey(),
                        'locale' => $locale,
                    ]),
                    'permission' => 'seo.content.edit',
                    'kind' => 'seo',
                ];
            }
        }

        return $this->cache[$source] = $this->unique($actions);
    }

    private function managedPage(string $slug, string $locale): ?Page
    {
        if ($slug === '') {
            return null;
        }
        $fallback = (string) config('app.fallback_locale', 'en');
        $source = Page::query()->where('slug', $slug)->where('language', $fallback)->first();
        if ($source && $locale !== $fallback && filled($source->uuid)) {
            return Page::query()->where('uuid', $source->uuid)->where('language', $locale)->first();
        }

        return $locale === $fallback
            ? $source
            : Page::query()->where('slug', $slug)->where('language', $locale)->first();
    }

    /** @return array{0:?Model,1:?string} */
    private function dynamicTarget(string $path, string $locale): array
    {
        $segments = explode('/', trim($path, '/'));
        if (count($segments) !== 2) {
            return [null, null];
        }
        $slug = rawurldecode($segments[1]);
        if ($slug === '' || mb_strlen($slug) > 255 || str_contains($slug, '/') || str_contains($slug, '\\')) {
            return [null, null];
        }

        return match ($segments[0]) {
            'page' => [Page::query()->where('slug', $slug)->where('language', $locale)->first(), 'page'],
            'category' => [Category::query()->where('slug', $slug)->where('language', $locale)->first(), 'category'],
            'event' => [NoticeBoard::query()->where('slug', $slug)->where('language', $locale)->first(), 'event'],
            'annual-report' => [AnnualReport::query()->where('slug', $slug)->where('language', $locale)->first(), 'annual_report'],
            'projects' => [Tag::query()->where('slug', $slug)->first(), 'project'],
            'donate' => [DonationType::query()->where('slug', $slug)->first(), 'donation_cause'],
            default => [null, null],
        };
    }

    /** @param list<array{label:string,url:string,permission:?string,kind:string}> $actions */
    private function appendContentAction(array &$actions, Model $model, string $type): void
    {
        [$routeName, $label] = match ($type) {
            'page' => ['page.edit', 'Edit page content'],
            'category' => ['category.edit', 'Edit category content'],
            'event' => ['notice.board.edit', 'Edit event content'],
            'annual_report' => ['annual.report.edit', 'Edit report content'],
            'project' => ['tag.edit', 'Edit project content'],
            'donation_cause' => ['donationType.edit', 'Edit donation cause'],
            default => [null, null],
        };
        if ($routeName && $label && Route::has($routeName)) {
            $actions[] = [
                'label' => $label,
                'url' => route($routeName, $model->getKey()),
                'permission' => $routeName,
                'kind' => 'content',
            ];
        }
    }

    /**
     * @param list<array{label:string,url:string,permission:?string,kind:string}> $actions
     * @return list<array{label:string,url:string,permission:?string,kind:string}>
     */
    private function unique(array $actions): array
    {
        return collect($actions)->unique('url')->values()->all();
    }
}
