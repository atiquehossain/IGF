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
use App\Support\AdminUi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $title = $request->Lang->DashboardTitle;
        $permission = app(Permission::class);
        $admin = $request->user('admin');
        $canReviewTranslations = Route::has('translations.index') && $permission->allows($admin, 'translations.index');
        $canReviewPages = Route::has('page.index') && $permission->allows($admin, 'page.index');
        $canReviewDonations = Route::has('donations.index') && $permission->allows($admin, 'donations.index');
        $canReviewSponsorships = Route::has('sponsorships.index') && $permission->allows($admin, 'sponsorships.index');
        $canReviewVolunteers = Route::has('volunteer.index') && $permission->allows($admin, 'volunteer.index');
        $canReviewContacts = Route::has('contact-message.index') && $permission->allows($admin, 'contact-message.index');
        $canManageNavigation = Route::has('page.menu.index') && $permission->allows($admin, 'page.menu.index');
        $canManageDonationCauses = Route::has('donationType.index') && $permission->allows($admin, 'donationType.index');
        $canReviewLocalization = $canReviewPages || $canReviewTranslations;
        $canReviewActivity = $canReviewPages || $canReviewDonations || $canReviewVolunteers;
        $canReviewAnyEnquiry = $canReviewSponsorships || $canReviewVolunteers || $canReviewContacts;

        // Build sensitive dashboard data only after its capability has been
        // established. Hiding a card in Blade is not sufficient: restricted
        // administrators must never receive unauthorized values in the view payload.
        $metrics = [];
        $monthlyRevenue = collect();
        $maxRevenue = 1.0;
        if ($canReviewDonations) {
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

            $metrics += [
                'donations_today' => $donationsToday,
                'donation_change' => $donationChange,
                'successful_gifts' => (clone $successfulDonations)->count(),
                'successful_this_month' => (clone $successfulDonations)
                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'pending_gateways' => SslCommerzTransaction::pending()->count(),
            ];

            $trendStart = now()->startOfMonth()->subMonths(6);
            $donationRows = (clone $successfulDonations)
                ->where('created_at', '>=', $trendStart)
                ->get(['amount', 'created_at']);
            $monthlyRevenue = collect(range(0, 6))->map(function (int $offset) use ($trendStart, $donationRows): array {
                $month = $trendStart->copy()->addMonths($offset);

                return [
                    'key' => $month->format('Y-m'),
                    'label' => $month->locale(app()->getLocale())->translatedFormat('M'),
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
        }

        if ($canReviewVolunteers) {
            $metrics['volunteers'] = Volunteer::query()->where('status', 1)->count();
        }

        $enquiryCounts = [];
        if ($canReviewSponsorships) {
            $enquiryCounts['sponsorships'] = Sponsorship::query()->where('workflow_status', 'new')->count();
        }
        if ($canReviewVolunteers) {
            $enquiryCounts['volunteers'] = Volunteer::query()->where('workflow_status', 'new')->count();
        }
        if ($canReviewContacts) {
            $enquiryCounts['contacts'] = ContactMessage::query()->where('workflow_status', 'new')->count();
        }
        $newEnquiries = array_sum($enquiryCounts);

        $enquiryActions = collect([
            ['permission' => 'sponsorships.index', 'route' => 'sponsorships.index', 'label' => AdminUi::text('dashboard.enquiries.sponsorships'), 'count' => $enquiryCounts['sponsorships'] ?? null],
            ['permission' => 'volunteer.index', 'route' => 'volunteer.index', 'label' => AdminUi::text('dashboard.enquiries.volunteers'), 'count' => $enquiryCounts['volunteers'] ?? null],
            ['permission' => 'contact-message.index', 'route' => 'contact-message.index', 'label' => AdminUi::text('dashboard.enquiries.contacts'), 'count' => $enquiryCounts['contacts'] ?? null],
        ])->filter(fn (array $action): bool => $action['count'] !== null
            && $action['count'] > 0
            && Route::has($action['route'])
            && $permission->allows($admin, $action['permission']))
            ->values();
        $quickActions = collect([
            ['permission' => 'page.index', 'route' => 'page.index', 'label' => AdminUi::text('dashboard.quick.edit_home'), 'help' => AdminUi::text('dashboard.quick.edit_home_help'), 'icon' => 'fa-file-text-o'],
            ['permission' => 'page.menu.index', 'route' => 'page.menu.index', 'label' => AdminUi::text('dashboard.quick.header_footer'), 'help' => AdminUi::text('dashboard.quick.header_footer_help'), 'icon' => 'fa-sitemap'],
            ['permission' => 'notice.board.create', 'route' => 'notice.board.create', 'label' => AdminUi::text('dashboard.quick.add_story'), 'help' => AdminUi::text('dashboard.quick.add_story_help'), 'icon' => 'fa-calendar-plus-o'],
            ['permission' => 'gallery.create', 'route' => 'gallery.create', 'label' => AdminUi::text('dashboard.quick.add_photo'), 'help' => AdminUi::text('dashboard.quick.add_photo_help'), 'icon' => 'fa-camera'],
            ['permission' => 'donationType.index', 'route' => 'donationType.index', 'label' => AdminUi::text('dashboard.quick.donation_causes'), 'help' => AdminUi::text('dashboard.quick.donation_causes_help'), 'icon' => 'fa-heart-o'],
            ['permission' => 'volunteer.index', 'route' => 'volunteer.index', 'label' => AdminUi::text('dashboard.quick.applications'), 'help' => AdminUi::text('dashboard.quick.applications_help'), 'icon' => 'fa-id-card-o'],
            ['permission' => 'contact-message.index', 'route' => 'contact-message.index', 'label' => AdminUi::text('dashboard.quick.contacts'), 'help' => AdminUi::text('dashboard.quick.contacts_help'), 'icon' => 'fa-inbox'],
            ['permission' => 'site.settings.index', 'route' => 'site.settings.index', 'label' => AdminUi::text('dashboard.quick.customizer'), 'help' => AdminUi::text('dashboard.quick.customizer_help'), 'icon' => 'fa-magic'],
        ])->filter(fn (array $action): bool => Route::has($action['route']) && $permission->allows($admin, $action['permission']))
            ->values();

        $siteHealth = collect();
        if ($canManageNavigation && !PageMenu::query()->where('type', 'footer')->where('status', 1)->exists()) {
            $siteHealth->push([
                'title' => AdminUi::text('dashboard.health.footer_title'),
                'detail' => AdminUi::text('dashboard.health.footer_detail'),
                'route' => 'page.menu.index',
                'parameters' => ['location' => 'footer'],
                'action' => AdminUi::text('dashboard.health.footer_action'),
            ]);
        }
        if ($canManageDonationCauses && !DonationType::query()->where('status', 1)->exists()) {
            $siteHealth->push([
                'title' => AdminUi::text('dashboard.health.cause_title'),
                'detail' => AdminUi::text('dashboard.health.cause_detail'),
                'route' => 'donationType.index',
                'parameters' => [],
                'action' => AdminUi::text('dashboard.health.cause_action'),
            ]);
        }
        if ($canReviewPages && !Page::query()->where('slug', 'home')->where('status', 1)->exists()) {
            $siteHealth->push([
                'title' => AdminUi::text('dashboard.health.home_title'),
                'detail' => AdminUi::text('dashboard.health.home_detail'),
                'route' => 'page.index',
                'parameters' => ['search' => 'Home'],
                'action' => AdminUi::text('dashboard.health.home_action'),
            ]);
        }
        $siteHealth = $siteHealth
            ->filter(fn (array $item): bool => Route::has($item['route']) && $permission->allows($admin, $item['route']))
            ->values();

        $localization = collect();
        if ($canReviewLocalization) {
            $pageLanguages = Page::query()->get(['slug', 'language']);
            $totalUniquePages = max(1, $pageLanguages->pluck('slug')->filter()->unique()->count());
            $configuredLocales = collect([
                'en' => AdminUi::text('dashboard.languages.en'),
                'bn' => AdminUi::text('dashboard.languages.bn'),
            ]);
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
        }

        $recentActivity = collect();
        if ($canReviewPages) {
            $recentActivity = $recentActivity->merge(Page::query()->latest('updated_at')->limit(3)->get()->map(fn (Page $page): array => [
                'type' => 'page',
                'title' => $page->publication_status === 'published'
                    ? AdminUi::text('dashboard.activity.page_published')
                    : AdminUi::text('dashboard.activity.draft_updated'),
                'detail' => $page->name,
                'at' => $page->updated_at,
                'icon' => 'fa-file-text-o',
            ]));
        }
        if ($canReviewDonations) {
            $recentActivity = $recentActivity->merge(Donation::query()->whereRaw('LOWER(payment_status) = ?', ['success'])->latest()->limit(3)->get()->map(fn (Donation $donation): array => [
                'type' => 'donation',
                'title' => AdminUi::text('dashboard.activity.donation_received'),
                'detail' => AdminUi::text('dashboard.activity.payment_detail', ['amount' => number_format((float) $donation->amount, 2)]),
                'at' => $donation->created_at,
                'icon' => 'fa-heart-o',
            ]));
        }
        if ($canReviewVolunteers) {
            $recentActivity = $recentActivity->merge(Volunteer::query()->latest()->limit(3)->get()->map(fn (Volunteer $volunteer): array => [
                'type' => 'volunteer',
                'title' => AdminUi::text('dashboard.activity.volunteer_signup'),
                'detail' => $volunteer->cause?->name ?: AdminUi::text('dashboard.activity.registration_received'),
                'at' => $volunteer->created_at,
                'icon' => 'fa-user-plus',
            ]));
        }
        $recentActivity = $recentActivity->sortByDesc('at')
            ->take(3)
            ->values();

        $hasSideDashboardCards = $canReviewLocalization || $canReviewActivity;
        $hasDashboardInsights = $quickActions->isNotEmpty()
            || $siteHealth->isNotEmpty()
            || $canReviewDonations
            || $canReviewVolunteers
            || $canReviewLocalization
            || $canReviewActivity
            || $canReviewAnyEnquiry;

        return view('admin.dashboard.index', compact(
            'title',
            'metrics',
            'monthlyRevenue',
            'maxRevenue',
            'localization',
            'recentActivity',
            'quickActions',
            'siteHealth',
            'enquiryActions',
            'newEnquiries',
            'canReviewTranslations',
            'canReviewPages',
            'canReviewDonations',
            'canReviewVolunteers',
            'canReviewLocalization',
            'canReviewActivity',
            'canReviewAnyEnquiry',
            'hasSideDashboardCards',
            'hasDashboardInsights'
        ));
    }
}
