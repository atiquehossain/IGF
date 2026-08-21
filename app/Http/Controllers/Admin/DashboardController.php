<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Permission;
use App\Models\ContactMessage;
use App\Models\Donation;
use App\Models\DonationType;
use App\Models\Page;
use App\Models\PageMenu;
use App\Models\Sponsorship;
use App\Models\SslCommerzTransaction;
use App\Models\Volunteer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $title = $request->Lang->DashboardTitle;
        $successfulDonations = Donation::query()
            ->whereRaw('LOWER(payment_status) = ?', ['success']);

        $donationsToday = (float) (clone $successfulDonations)
            ->whereDate('created_at', today())
            ->sum('amount');
        $donationsYesterday = (float) (clone $successfulDonations)
            ->whereDate('created_at', today()->subDay())
            ->sum('amount');
        $donationChange = $donationsYesterday > 0
            ? (int) round((($donationsToday - $donationsYesterday) / $donationsYesterday) * 100)
            : ($donationsToday > 0 ? 100 : 0);

        $metrics = [
            'donations_today' => $donationsToday,
            'donation_change' => $donationChange,
            'successful_gifts' => (clone $successfulDonations)->count(),
            'successful_this_month' => (clone $successfulDonations)
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'pending_gateways' => SslCommerzTransaction::pending()->count(),
            'volunteers' => Volunteer::query()->where('status', 1)->count(),
            'new_enquiries' => Sponsorship::query()->where('workflow_status', 'new')->count()
                + Volunteer::query()->where('workflow_status', 'new')->count()
                + ContactMessage::query()->where('workflow_status', 'new')->count(),
        ];

        $permission = app(Permission::class);
        $admin = $request->user('admin');
        $canReviewTranslations = Route::has('translations.index') && $permission->allows($admin, 'translations.index');
        $canReviewPages = Route::has('page.index') && $permission->allows($admin, 'page.index');
        $quickActions = collect([
            ['permission' => 'page.index', 'route' => 'page.index', 'label' => 'Edit home or a page', 'help' => 'Change visitor-facing sections and text.', 'icon' => 'fa-file-text-o'],
            ['permission' => 'page.menu.index', 'route' => 'page.menu.index', 'label' => 'Update header or footer', 'help' => 'Change navigation labels and destinations.', 'icon' => 'fa-sitemap'],
            ['permission' => 'notice.board.create', 'route' => 'notice.board.create', 'label' => 'Add event or news', 'help' => 'Publish a new update for visitors.', 'icon' => 'fa-calendar-plus-o'],
            ['permission' => 'gallery.create', 'route' => 'gallery.create', 'label' => 'Add a gallery photo', 'help' => 'Upload and describe a public image.', 'icon' => 'fa-camera'],
            ['permission' => 'donationType.index', 'route' => 'donationType.index', 'label' => 'Manage donation causes', 'help' => 'Choose what supporters can fund.', 'icon' => 'fa-heart-o'],
            ['permission' => 'volunteer.index', 'route' => 'volunteer.index', 'label' => 'Review applications', 'help' => 'Assign and follow up volunteer enquiries.', 'icon' => 'fa-id-card-o'],
            ['permission' => 'contact-message.index', 'route' => 'contact-message.index', 'label' => 'Open contact enquiries', 'help' => 'Respond, assign and record next steps.', 'icon' => 'fa-inbox'],
            ['permission' => 'site.settings.index', 'route' => 'site.settings.index', 'label' => 'Customize the website', 'help' => 'Update brand, colors and global information.', 'icon' => 'fa-magic'],
        ])->filter(fn (array $action): bool => Route::has($action['route']) && $permission->allows($admin, $action['permission']))
            ->values();

        $siteHealth = collect();
        if (!PageMenu::query()->where('type', 'footer')->where('status', 1)->exists()) {
            $siteHealth->push([
                'title' => 'Footer navigation needs attention',
                'detail' => 'Add the links visitors should see at the bottom of every page.',
                'route' => 'page.menu.index',
                'parameters' => ['location' => 'footer'],
                'action' => 'Set up footer',
            ]);
        }
        if (!DonationType::query()->where('status', 1)->exists()) {
            $siteHealth->push([
                'title' => 'No active donation cause',
                'detail' => 'Visitors cannot complete the donation form until at least one cause is active.',
                'route' => 'donationType.index',
                'parameters' => [],
                'action' => 'Manage donation causes',
            ]);
        }
        if (!Page::query()->where('slug', 'home')->where('status', 1)->exists()) {
            $siteHealth->push([
                'title' => 'Homepage is not published',
                'detail' => 'Publish the Home page before sharing the website publicly.',
                'route' => 'page.index',
                'parameters' => ['search' => 'Home'],
                'action' => 'Open pages',
            ]);
        }
        $siteHealth = $siteHealth
            ->filter(fn (array $item): bool => Route::has($item['route']) && $permission->allows($admin, $item['route']))
            ->values();

        $trendStart = now()->startOfMonth()->subMonths(6);
        $donationRows = (clone $successfulDonations)
            ->where('created_at', '>=', $trendStart)
            ->get(['amount', 'created_at']);
        $monthlyRevenue = collect(range(0, 6))->map(function (int $offset) use ($trendStart, $donationRows): array {
            $month = $trendStart->copy()->addMonths($offset);

            return [
                'key' => $month->format('Y-m'),
                'label' => $month->format('M'),
                'amount' => (float) $donationRows
                    ->filter(fn (Donation $donation): bool => $donation->created_at->format('Y-m') === $month->format('Y-m'))
                    ->sum('amount'),
            ];
        });

        $maxRevenue = max(1, (float) $monthlyRevenue->max('amount'));
        $monthlyRevenue = $monthlyRevenue->map(function (array $month) use ($maxRevenue): array {
            $month['height'] = $month['amount'] > 0
                ? max(8, (int) round(($month['amount'] / $maxRevenue) * 100))
                : 0;

            return $month;
        });

        $pageLanguages = Page::query()->get(['slug', 'language']);
        $totalUniquePages = max(1, $pageLanguages->pluck('slug')->filter()->unique()->count());
        $configuredLocales = collect(['en' => 'English', 'bn' => 'Bengali']);
        $pageLanguages->pluck('language')->filter()->unique()->each(function (string $locale) use ($configuredLocales): void {
            if (!$configuredLocales->has($locale)) {
                $configuredLocales->put($locale, strtoupper($locale));
            }
        });
        $localization = $configuredLocales->map(function (string $label, string $locale) use ($pageLanguages, $totalUniquePages): array {
            $translated = $pageLanguages
                ->where('language', $locale)
                ->pluck('slug')
                ->filter()
                ->unique()
                ->count();

            return [
                'locale' => $locale,
                'label' => $label,
                'percent' => min(100, (int) round(($translated / $totalUniquePages) * 100)),
            ];
        })->values();

        $recentActivity = collect()
            ->merge(Page::query()->latest('updated_at')->limit(3)->get()->map(fn (Page $page): array => [
                'type' => 'page',
                'title' => $page->publication_status === 'published' ? 'Page published' : 'Draft updated',
                'detail' => $page->name,
                'at' => $page->updated_at,
                'icon' => 'fa-file-text-o',
            ]))
            ->merge(Donation::query()->whereRaw('LOWER(payment_status) = ?', ['success'])->latest()->limit(3)->get()->map(fn (Donation $donation): array => [
                'type' => 'donation',
                'title' => 'Donation received',
                'detail' => 'BDT ' . number_format((float) $donation->amount, 2) . ' payment confirmed.',
                'at' => $donation->created_at,
                'icon' => 'fa-heart-o',
            ]))
            ->merge(Volunteer::query()->latest()->limit(3)->get()->map(fn (Volunteer $volunteer): array => [
                'type' => 'volunteer',
                'title' => 'Volunteer sign-up',
                'detail' => $volunteer->cause?->name ?: 'New registration received.',
                'at' => $volunteer->created_at,
                'icon' => 'fa-user-plus',
            ]))
            ->sortByDesc('at')
            ->take(3)
            ->values();

        return view('admin.dashboard.index', compact(
            'title',
            'metrics',
            'monthlyRevenue',
            'maxRevenue',
            'localization',
            'recentActivity',
            'quickActions',
            'siteHealth',
            'canReviewTranslations',
            'canReviewPages'
        ));
    }
}
