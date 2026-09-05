<?php

use App\Helper\MyMenu;
use App\Http\Middleware\Permission;
use App\Support\AdminUi;

$routeName = (string) Route::currentRouteName();
$admin = Auth::guard('admin')->user();
$permissions = app(Permission::class);
$canSettings = $permissions->allows($admin, 'site.settings.index');
$canUse = fn (string $route): bool => Route::has($route) && $permissions->allows($admin, $route);
$ui = static fn (string $key, array $replace = []): string => AdminUi::text($key, $replace);
$navGroups = [
    [
        'label' => $ui('sidebar.groups.website'), 'icon' => 'fa-globe',
        'items' => [
            ['route' => 'page.index', 'label' => $ui('sidebar.items.content_hub'), 'icon' => 'fa-file-text-o'],
            ['route' => 'page.menu.index', 'label' => $ui('sidebar.items.header_footer'), 'icon' => 'fa-sitemap'],
            ['route' => 'site.settings.index', 'label' => $ui('sidebar.items.brand_appearance'), 'icon' => 'fa-magic'],
            ['route' => 'media.index', 'label' => $ui('sidebar.items.media_library'), 'icon' => 'fa-picture-o'],
            ['route' => 'transactional-mail.index', 'label' => $ui('sidebar.items.email_templates'), 'icon' => 'fa-envelope-open-o'],
        ],
    ],
    [
        'label' => $ui('sidebar.groups.content'), 'icon' => 'fa-pencil-square-o',
        'items' => [
            ['route' => 'category.index', 'label' => $ui('sidebar.items.programs'), 'icon' => 'fa-compass'],
            ['route' => 'tag.index', 'label' => $ui('sidebar.items.projects'), 'icon' => 'fa-briefcase'],
            ['route' => 'notice.board.index', 'label' => $ui('sidebar.items.events_news'), 'icon' => 'fa-calendar'],
            ['route' => 'latest.news.index', 'label' => $ui('sidebar.items.team_members'), 'icon' => 'fa-users'],
            ['route' => 'testimonial.index', 'label' => $ui('sidebar.items.community_stories'), 'icon' => 'fa-quote-left'],
            ['route' => 'gallery.index', 'label' => $ui('sidebar.items.photo_gallery'), 'icon' => 'fa-camera'],
            ['route' => 'annual.report.index', 'label' => $ui('sidebar.items.reports'), 'icon' => 'fa-file-pdf-o'],
        ],
    ],
    [
        'label' => $ui('sidebar.groups.get_involved'), 'icon' => 'fa-heart-o',
        'items' => [
            ['route' => 'donationType.index', 'label' => $ui('sidebar.items.donation_causes'), 'icon' => 'fa-heart'],
            ['route' => 'donations.index', 'label' => $ui('sidebar.items.donation_records'), 'icon' => 'fa-money'],
            ['route' => 'sponsorships.index', 'label' => $ui('sidebar.items.sponsorship_enquiries'), 'icon' => 'fa-child'],
            ['route' => 'volunteerCause.index', 'label' => $ui('sidebar.items.volunteer_opportunities'), 'icon' => 'fa-hand-paper-o'],
            ['route' => 'volunteer.index', 'label' => $ui('sidebar.items.volunteer_applications'), 'icon' => 'fa-id-card-o'],
            ['route' => 'subscriber.index', 'label' => $ui('sidebar.items.subscribers'), 'icon' => 'fa-envelope-o'],
            ['route' => 'contact-message.index', 'label' => $ui('sidebar.items.contact_enquiries'), 'icon' => 'fa-inbox'],
            ['route' => 'chat.index', 'label' => $ui('sidebar.items.chat_inbox'), 'icon' => 'fa-comments-o'],
            ['route' => 'chat.faq.index', 'label' => $ui('sidebar.items.chat_answers'), 'icon' => 'fa-commenting-o'],
        ],
    ],
    [
        'label' => $ui('sidebar.groups.recruitment'), 'icon' => 'fa-briefcase',
        'items' => [
            ['route' => 'recruitment.jobs.index', 'label' => $ui('sidebar.items.jobs'), 'icon' => 'fa-briefcase'],
            ['route' => 'recruitment.applications.index', 'label' => $ui('sidebar.items.applications'), 'icon' => 'fa-id-card-o'],
            ['route' => 'recruitment.forms.index', 'label' => $ui('sidebar.items.form_templates'), 'icon' => 'fa-list-alt'],
            ['route' => 'recruitment.imports.index', 'label' => $ui('sidebar.items.csv_imports'), 'icon' => 'fa-upload'],
        ],
    ],
    [
        'label' => $ui('sidebar.groups.workshops'), 'icon' => 'fa-calendar',
        'items' => [
            ['route' => 'workshops.index', 'label' => $ui('sidebar.items.workshops'), 'icon' => 'fa-calendar'],
            ['route' => 'workshop.registrations.index', 'label' => $ui('sidebar.items.registrations'), 'icon' => 'fa-users'],
            ['route' => 'workshop.forms.index', 'label' => $ui('sidebar.items.form_templates'), 'icon' => 'fa-list-alt'],
            ['route' => 'workshop.imports.index', 'label' => $ui('sidebar.items.csv_imports'), 'icon' => 'fa-upload'],
        ],
    ],
    [
        'label' => $ui('sidebar.groups.search_languages'), 'icon' => 'fa-search',
        'items' => [
            ['route' => 'seo.index', 'label' => $ui('sidebar.items.search_sharing'), 'icon' => 'fa-line-chart'],
            ['route' => 'seo.performance.index', 'label' => $ui('sidebar.items.search_performance'), 'icon' => 'fa-area-chart'],
            ['route' => 'seo.internal-links.index', 'label' => $ui('sidebar.items.internal_links'), 'icon' => 'fa-link'],
            ['route' => 'seo.redirects.index', 'label' => $ui('sidebar.items.redirects'), 'icon' => 'fa-random'],
            ['route' => 'seo.technical.index', 'label' => $ui('sidebar.items.technical_seo'), 'icon' => 'fa-stethoscope'],
            ['route' => 'translations.index', 'label' => $ui('sidebar.items.translations'), 'icon' => 'fa-language'],
        ],
    ],
    [
        'label' => $ui('sidebar.groups.users_access'), 'icon' => 'fa-lock',
        'items' => [
            ['route' => 'admin.index', 'label' => $ui('sidebar.items.administrators'), 'icon' => 'fa-user-circle-o'],
            ['route' => 'role.index', 'label' => $ui('sidebar.items.roles_permissions'), 'icon' => 'fa-key'],
        ],
    ],
];

foreach ($navGroups as &$group) {
    $group['items'] = array_values(array_filter($group['items'], fn (array $item): bool => $canUse($item['route'])));
}
unset($group);
$navGroups = array_values(array_filter($navGroups, fn (array $group): bool => $group['items'] !== []));
$activeNavRoute = collect($navGroups)
    ->flatMap(fn (array $group) => $group['items'])
    ->filter(function (array $item) use ($routeName): bool {
        $base = str_contains($item['route'], '.') ? str($item['route'])->beforeLast('.')->toString() : $item['route'];

        return $routeName === $item['route'] || str_starts_with($routeName, $base . '.');
    })
    ->sortByDesc(function (array $item): int {
        $base = str_contains($item['route'], '.') ? str($item['route'])->beforeLast('.')->toString() : $item['route'];

        return strlen($base);
    })
    ->pluck('route')
    ->first();
foreach ($navGroups as &$group) {
    $group['active'] = collect($group['items'])->contains(fn (array $item): bool => $item['route'] === $activeNavRoute);
}
unset($group);
$curatedRoutes = collect($navGroups)
    ->flatMap(fn (array $group) => $group['items'])
    ->pluck('route')
    ->push('dashboard.index')
    ->unique()
    ->values()
    ->all();
$legacyMenu = trim(MyMenu::menuUi($curatedRoutes));
$legacyMenuHasCurrent = str_contains($legacyMenu, 'aria-current="page"');
?>

<aside id="left-panel" class="left-panel" aria-label="{{ $ui('sidebar.administration_navigation') }}">
    <div class="igf-sidebar-brand">
        <a class="igf-brand-home" href="{{ route('dashboard.index') }}" aria-label="{{ $ui('sidebar.dashboard_label') }}">
            <span class="igf-brand-mark"><img src="{{ asset('image/logo.png') }}" alt=""></span>
            <span class="igf-brand-copy">
                <strong>{{ $ui('sidebar.brand') }}</strong>
                <small>{{ $ui('sidebar.global_dashboard') }}</small>
            </span>
        </a>
        <button id="sidebarClose" class="igf-sidebar-close" type="button" aria-label="{{ $ui('sidebar.close_navigation') }}">
            <i class="fa fa-times" aria-hidden="true"></i>
        </button>
    </div>

    <nav class="navbar navbar-expand-sm navbar-default" aria-label="{{ $ui('sidebar.content_management') }}">
        <div id="main-menu" class="main-menu collapse navbar-collapse">
            <ul class="nav navbar-nav igf-primary-nav">
                <li class="{{ $routeName === 'dashboard.index' ? 'active' : '' }}"><a href="{{ route('dashboard.index') }}" aria-label="{{ $ui('sidebar.dashboard') }}" title="{{ $ui('sidebar.dashboard') }}" @if($routeName === 'dashboard.index') aria-current="page" @endif><i class="menu-icon fa fa-th-large" aria-hidden="true"></i><span class="igf-nav-label">{{ $ui('sidebar.dashboard') }}</span></a></li>
            </ul>
            @foreach($navGroups as $group)
                <details class="igf-nav-group" @if($group['active']) open @endif>
                    <summary aria-label="{{ $group['label'] }} navigation" title="{{ $group['label'] }}"><i class="fa {{ $group['icon'] }}" aria-hidden="true"></i><span>{{ $group['label'] }}</span></summary>
                    <ul class="nav navbar-nav">
                        @foreach($group['items'] as $item)
                            @php
                                $itemActive = $item['route'] === $activeNavRoute;
                            @endphp
                            <li class="{{ $itemActive ? 'active' : '' }}"><a href="{{ route($item['route']) }}" aria-label="{{ $item['label'] }}" @if($itemActive) aria-current="page" @endif title="{{ $item['label'] }}"><i class="menu-icon fa {{ $item['icon'] }}" aria-hidden="true"></i><span class="igf-nav-label">{{ $item['label'] }}</span></a></li>
                        @endforeach
                    </ul>
                </details>
            @endforeach
            @if($legacyMenu !== '')
                <details class="igf-all-tools" @if($legacyMenuHasCurrent) open @endif>
                    <summary aria-label="{{ $ui('sidebar.advanced_navigation') }}" title="{{ $ui('sidebar.advanced_tools') }}"><i class="fa fa-cogs" aria-hidden="true"></i><span>{{ $ui('sidebar.advanced_tools') }}</span></summary>
                    <ul class="nav navbar-nav">
                        {!! $legacyMenu !!}
                    </ul>
                </details>
            @endif
        </div>
    </nav>

    <div class="igf-sidebar-footer">
        <a class="igf-visit-site" href="{{ route('frontend.home') }}" target="_blank" rel="noopener" aria-label="{{ $ui('sidebar.visit_public_site') }}" title="{{ $ui('sidebar.visit_site') }}">
            <i class="fa fa-external-link" aria-hidden="true"></i><span>{{ $ui('sidebar.visit_site') }}</span>
        </a>
        @if($canSettings)<a href="{{ route('site.settings.index') }}" aria-label="{{ $ui('sidebar.website_customizer') }}" title="{{ $ui('sidebar.website_customizer') }}">
            <i class="fa fa-magic" aria-hidden="true"></i><span>{{ $ui('sidebar.website_customizer') }}</span>
        </a>@endif
        <form method="post" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" aria-label="{{ $ui('sidebar.logout') }}" title="{{ $ui('sidebar.logout') }}"><i class="fa fa-sign-out" aria-hidden="true"></i><span>{{ $ui('sidebar.logout') }}</span></button>
        </form>
    </div>
</aside>
