<?php

namespace App\Http\Controllers\Vue;

use App\Helper\StaticUtil;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Page;
use App\Models\Sponsorship;
use App\Services\SeoMetadataService;
use App\Services\SiteSettingService;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use Exception;
use Throwable;

class SponsorController extends Controller
{
    public function __construct(private SeoMetadataService $seo)
    {
    }

    /**
     * Show the sponsor page.
     */
    public function index()
    {
        $page = Page::with('banner')
            ->publiclyAvailable()
            ->where('language', app()->getLocale())
            ->where('slug', 'sponsor-a-child')
            ->first();

        $fallbackMeta = [
            'meta_keyword' => 'sponsor a child, education support, child sponsorship Bangladesh',
            'meta_title' => 'Sponsor a Child | Ignite Global Foundation',
            'meta_description' => 'Support a child with dependable access to education, learning materials, nutrition, and essential care through Ignite Global Foundation.',
            'canonical_url' => url()->current(),
        ];
        $metaTag = $page
            ? $this->seo->metaForPage($page)
            : $fallbackMeta;
        $metaTag['meta_title'] = $metaTag['meta_title'] ?: $fallbackMeta['meta_title'];
        $metaTag['meta_description'] = $metaTag['meta_description'] ?: $fallbackMeta['meta_description'];
        $metaTag['canonical_url'] = $metaTag['canonical_url'] ?: $fallbackMeta['canonical_url'];
        if ($page?->visibility === 'unlisted') {
            $metaTag['robots'] = 'noindex,nofollow';
        }
        $contentSeo = $page ? $metaTag : [];
        StaticUtil::ssr($metaTag);

        return Inertia::render('sponsor_child')->with([
            'status' => true,
            'title' => 'Sponsor a Child',
            'meta_tag' => $metaTag,
            'contentSeo' => $contentSeo,
            'data' => [
                'banner' => $page?->banner,
                'sponsor_child' => $page,
            ],
        ]);
    }

    /**
     * Initialize the sponsorship payment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'number_of_children' => 'required|integer|min:1|max:100',
            'contribution_interval' => ['required', Rule::in(['monthly', 'quarterly', 'semi_annually', 'annually'])],
            'sponsorshipAmount' => 'required|numeric|min:1|max:10000000',
        ]);

        $intervalMultipliers = [
            'monthly' => 1,
            'quarterly' => 3,
            'semi_annually' => 6,
            'annually' => 12,
        ];
        $settings = app(SiteSettingService::class)->values(app()->getLocale(), true);
        $monthlyAmount = max(1, (int) data_get($settings, 'sponsor_page.monthly_amount', 1500));
        $sponsorshipAmount = $validated['number_of_children']
            * $monthlyAmount
            * $intervalMultipliers[$validated['contribution_interval']];
        if ($sponsorshipAmount > 10000000) {
            throw ValidationException::withMessages([
                'number_of_children' => data_get($settings, 'sponsor_page.maximum_total_message', 'This sponsorship total exceeds the supported request limit. Please contact us for a larger partnership.'),
            ]);
        }

        try {
            $sponsorship = Sponsorship::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'number_of_children' => $validated['number_of_children'],
                'contribution_interval' => $validated['contribution_interval'],
                'sponsorship_amount' => $sponsorshipAmount,
                'transaction_id' => 'REQUEST-' . Str::upper(Str::random(20)),
                'payment_status' => 'Request',
            ]);

            $this->sendSponsorConfirmationEmail($sponsorship->toArray());
            $this->sendAdminNotificationEmail($sponsorship->toArray());

            return response()->json([
                'status' => true,
                'message' => data_get($settings, 'sponsor_page.success_message', 'Sponsorship request submitted.'),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => data_get($settings, 'sponsor_page.error_message', 'We could not send your request. Please try again.'),
            ], 500);
        }
    }

    /**
     * Send notification email to admin about new sponsorship.
     */
    public function sendAdminNotificationEmail(array $data): void
    {
        try {
            $toEmail = Config::get('mail.from.address');
            $subject = 'Sponsor A Child - New Sponsorship Request';

            $message = <<<EOT
                A new sponsorship request has been received.

                Details:
                ------------------------------------------------------------
                Name: {$data['name']}
                Email: {$data['email']}
                Phone: {$data['phone']}
                Address: {$data['address']}

                Number of Children: {$data['number_of_children']}
                Contribution Interval: {$data['contribution_interval']}
                Sponsorship Amount: {$data['sponsorship_amount']}
                ------------------------------------------------------------

                This notification was sent automatically by the Sponsor A Child system.
                EOT;

            Mail::raw($message, function ($mail) use ($toEmail, $subject) {
                $mail->to($toEmail)->subject($subject);
            });

            Log::info('Sponsorship admin notification sent.', [
                'sponsorship_id' => $data['id'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error('Sponsorship admin notification failed.', [
                'sponsorship_id' => $data['id'] ?? null,
                'exception' => $e::class,
                'code' => $e->getCode(),
            ]);
        }
    }

    /**
     * Send confirmation email to sponsor.
     */
    public function sendSponsorConfirmationEmail(array $data): void
    {
        try {
            $configEmail = Config::get('mail.from.address');
            $toEmail = $data['email'];
            $subject = 'Thank You for Your Sponsorship Request';
            $siteUrl = $this->publicSiteUrl();

            $message = <<<EOT
                Dear {$data['name']},

                We’re thrilled to have you as part of the Ignite Global Foundation family! Your support is
                helping children from marginalized communities in Bangladesh access hope, opportunities, and a brighter future.
                Thank you for taking this meaningful step-your generosity truly makes a difference. One of our team members will
                connect with you within the next 72 hours.

                Warm regards,
                The Ignite Global Foundation Team
                Email: {$configEmail}
                Website: {$siteUrl}
                EOT;

            Mail::raw($message, function ($mail) use ($toEmail, $subject) {
                $mail->to($toEmail)->subject($subject);
            });

            Log::info('Sponsorship confirmation sent.', [
                'sponsorship_id' => $data['id'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error('Sponsorship confirmation failed.', [
                'sponsorship_id' => $data['id'] ?? null,
                'exception' => $e::class,
                'code' => $e->getCode(),
            ]);
        }
    }

    private function publicSiteUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
