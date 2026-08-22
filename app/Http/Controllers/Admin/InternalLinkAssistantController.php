<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Permission;
use App\Services\InternalLinkAssistantService;
use App\Services\LocalizationManager;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class InternalLinkAssistantController extends Controller
{
    public function __construct(
        private InternalLinkAssistantService $assistant,
        private LocalizationManager $localization
    ) {
    }

    public function index(Request $request)
    {
        $locales = $this->localization->editorLocales();
        $allowedLocales = $locales->pluck('id')->map(fn ($locale): string => (string) $locale);
        $fallback = (string) ($allowedLocales->first() ?: config('app.fallback_locale', 'en'));
        $requestedLocale = (string) $request->query('locale', app()->getLocale() ?: $fallback);
        $locale = $allowedLocales->contains($requestedLocale) ? $requestedLocale : $fallback;

        $search = Str::limit(trim((string) $request->query('search')), 100, '');
        $status = (string) $request->query('status', 'all');
        if (!in_array($status, ['all', 'orphan', 'weak'], true)) {
            $status = 'all';
        }

        $analysis = $this->assistant->recommendations($locale);
        $targets = collect($analysis['targets'])
            ->filter(fn (array $target): bool => $status === 'all' || $target['status'] === $status)
            ->filter(fn (array $target): bool => $search === '' || str_contains(
                Str::lower($target['title'] . ' ' . $target['public_url'] . ' ' . $target['focus_phrase']),
                Str::lower($search)
            ))
            ->values();

        $pageName = 'link_page';
        $perPage = 8;
        $page = max(1, (int) $request->query($pageName, 1));
        $pagination = new LengthAwarePaginator(
            $targets->forPage($page, $perPage)->values(),
            $targets->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => $pageName]
        );
        $pagination->withQueryString();

        $permission = app(Permission::class);
        $admin = $request->user('admin');
        $localeIsPublic = in_array($locale, $this->localization->publicLocales(), true);

        return view('admin.seo.internal-links', [
            'title' => 'Contextual link assistant',
            'locales' => $locales,
            'locale' => $locale,
            'analysis' => $analysis,
            'visibleTargets' => $pagination->getCollection(),
            'pagination' => $pagination,
            'filters' => compact('search', 'status'),
            'localeIsPublic' => $localeIsPublic,
            'canEditPageContent' => $permission->allows($admin, 'page.builder.edit'),
            'canViewTechnicalSeo' => $permission->allows($admin, 'seo.technical.index'),
        ]);
    }
}
