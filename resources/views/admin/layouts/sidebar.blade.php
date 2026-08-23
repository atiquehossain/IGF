<?php

use App\Helper\MyMenu;
use App\Http\Middleware\Permission;

$routeName = (string) Route::currentRouteName();
$admin = Auth::guard('admin')->user();
$permissions = app(Permission::class);
$canSettings = $permissions->allows($admin, 'site.settings.index');
$canUse = fn (string $route): bool => Route::has($route) && $permissions->allows($admin, $route);
$navGroups = [
    [
        'label' => 'Website', 'icon' => 'fa-globe',
        'items' => [
            ['route' => 'page.index', 'label' => 'Content Hub', 'icon' => 'fa-file-text-o'],
            ['route' => 'page.menu.index', 'label' => 'Header & Footer', 'icon' => 'fa-sitemap'],
            ['route' => 'site.settings.index', 'label' => 'Brand & Appearance', 'icon' => 'fa-magic'],
            ['route' => 'media.index', 'label' => 'Media Library', 'icon' => 'fa-picture-o'],
        ],
    ],
    [
        'label' => 'Content', 'icon' => 'fa-pencil-square-o',
        'items' => [
            ['route' => 'category.index', 'label' => 'Programs', 'icon' => 'fa-compass'],
            ['route' => 'tag.index', 'label' => 'Projects', 'icon' => 'fa-briefcase'],
            ['route' => 'notice.board.index', 'label' => 'Events & News', 'icon' => 'fa-calendar'],
            ['route' => 'latest.news.index', 'label' => 'Team Members', 'icon' => 'fa-users'],
            ['route' => 'testimonial.index', 'label' => 'Community Stories', 'icon' => 'fa-quote-left'],
            ['route' => 'gallery.index', 'label' => 'Photo Gallery', 'icon' => 'fa-camera'],
            ['route' => 'annual.report.index', 'label' => 'Reports', 'icon' => 'fa-file-pdf-o'],
        ],
    ],
    [
        'label' => 'Get Involved', 'icon' => 'fa-heart-o',
        'items' => [
            ['route' => 'donationType.index', 'label' => 'Donation Causes', 'icon' => 'fa-heart'],
            ['route' => 'donations.index', 'label' => 'Donation Records', 'icon' => 'fa-money'],
            ['route' => 'sponsorships.index', 'label' => 'Sponsorship Enquiries', 'icon' => 'fa-child'],
            ['route' => 'volunteerCause.index', 'label' => 'Volunteer Opportunities', 'icon' => 'fa-hand-paper-o'],
            ['route' => 'volunteer.index', 'label' => 'Volunteer Applications', 'icon' => 'fa-id-card-o'],
            ['route' => 'subscriber.index', 'label' => 'Subscribers', 'icon' => 'fa-envelope-o'],
            ['route' => 'contact-message.index', 'label' => 'Contact Enquiries', 'icon' => 'fa-inbox'],
            ['route' => 'chat.index', 'label' => 'Chat Inbox', 'icon' => 'fa-comments-o'],
            ['route' => 'chat.faq.index', 'label' => 'Chat Answers', 'icon' => 'fa-commenting-o'],
        ],
    ],
    [
        'label' => 'Search & Languages', 'icon' => 'fa-search',
        'items' => [
            ['route' => 'seo.index', 'label' => 'Search & Sharing', 'icon' => 'fa-line-chart'],
            ['route' => 'seo.performance.index', 'label' => 'Search Performance', 'icon' => 'fa-area-chart'],
            ['route' => 'seo.internal-links.index', 'label' => 'Internal Links', 'icon' => 'fa-link'],
            ['route' => 'seo.redirects.index', 'label' => 'Redirects', 'icon' => 'fa-random'],
            ['route' => 'seo.technical.index', 'label' => 'Technical SEO & 404s', 'icon' => 'fa-stethoscope'],
            ['route' => 'translations.index', 'label' => 'Translations', 'icon' => 'fa-language'],
        ],
    ],
    [
        'label' => 'Users & Access', 'icon' => 'fa-lock',
        'items' => [
            ['route' => 'admin.index', 'label' => 'Administrators', 'icon' => 'fa-user-circle-o'],
            ['route' => 'role.index', 'label' => 'Roles & Permissions', 'icon' => 'fa-key'],
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

<aside id="left-panel" class="left-panel" aria-label="Administration navigation">
    <div class="igf-sidebar-brand">
        <a class="igf-brand-home" href="{{ route('dashboard.index') }}" aria-label="Ignite Admin dashboard">
            <span class="igf-brand-mark"><img src="{{ asset('image/logo.png') }}" alt=""></span>
            <span class="igf-brand-copy">
                <strong>Ignite Admin</strong>
                <small>Global Dashboard</small>
            </span>
        </a>
        <button id="sidebarClose" class="igf-sidebar-close" type="button" aria-label="Close navigation">
            <i class="fa fa-times" aria-hidden="true"></i>
        </button>
    </div>

    <nav class="navbar navbar-expand-sm navbar-default" aria-label="Content management">
        <div id="main-menu" class="main-menu collapse navbar-collapse">
            <ul class="nav navbar-nav igf-primary-nav">
                <li class="{{ $routeName === 'dashboard.index' ? 'active' : '' }}"><a href="{{ route('dashboard.index') }}" aria-label="Dashboard" title="Dashboard" @if($routeName === 'dashboard.index') aria-current="page" @endif><i class="menu-icon fa fa-th-large" aria-hidden="true"></i><span class="igf-nav-label">Dashboard</span></a></li>
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
                    <summary aria-label="Advanced and legacy tools navigation" title="Advanced &amp; Legacy Tools"><i class="fa fa-cogs" aria-hidden="true"></i><span>Advanced & Legacy Tools</span></summary>
                    <ul class="nav navbar-nav">
                        {!! $legacyMenu !!}
                    </ul>
                </details>
            @endif
        </div>
    </nav>

    <div class="igf-sidebar-footer">
        <a class="igf-visit-site" href="{{ route('frontend.home') }}" target="_blank" rel="noopener" aria-label="Visit public website" title="Visit Site">
            <i class="fa fa-external-link" aria-hidden="true"></i><span>Visit Site</span>
        </a>
        @if($canSettings)<a href="{{ route('site.settings.index') }}" aria-label="Website Customizer" title="Website Customizer">
            <i class="fa fa-magic" aria-hidden="true"></i><span>Website Customizer</span>
        </a>@endif
        <form method="post" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" aria-label="Log out" title="Log Out"><i class="fa fa-sign-out" aria-hidden="true"></i><span>Log Out</span></button>
        </form>
    </div>
</aside>
