<?php

namespace App\Http\Controllers\Vue;

use App\Helper\StaticUtil;
use App\Http\Controllers\Controller;
use App\Mail\TransactionalEmail;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Page;
use App\Models\Sponsorship;
use App\Services\PublicSystemPageMetaService;
use App\Services\PublicFormFieldLayoutService;
use App\Services\SeoMetadataService;
use App\Services\SiteSettingService;
use App\Services\TransactionalEmailTemplateService;
use App\Support\TransactionalEmailTemplateCatalog;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use Exception;
use RuntimeException;
use Throwable;

class SponsorController extends Controller
{
    public function __construct(
        private SeoMetadataService $seo,
        private TransactionalEmailTemplateService $emailTemplates,
        private PublicSystemPageMetaService $systemMeta,
        private SiteSettingService $siteSettings,
        private PublicFormFieldLayoutService $formLayouts,
    ) {
    }

    /**
     * Show the sponsor page.
     */
    public function index(Request $request)
    {
        $page = Page::with('banner')
            ->publiclyAvailable()
            ->where('language', app()->getLocale())
            ->where('slug', 'sponsor-a-child')
            ->first();

        $pageMeta = $this->systemMeta->resolve(
            $request,
            'sponsor_page.eyebrow',
            'sponsor_page.introduction',
            [
                'title' => 'Sponsor a Child',
                'meta_title' => 'Sponsor a Child',
                'description' => 'Support a child with dependable access to education, learning materials, nutrition, and essential care through Ignite Global Foundation.',
            ],
        );
        $fallbackMeta = array_merge(['canonical_url' => url()->current()], $pageMeta['meta_tag']);
        $metaTag = $page
            ? $this->seo->metaForModel(
                $page,
                $this->systemMeta->forPage(
                    $page,
                    $request,
                    (string) $pageMeta['meta_tag']['meta_description'],
                ),
                url()->current(),
                (string) $page->language,
            )
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
            'title' => $page?->name ?: $pageMeta['title'],
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
        $settings = $this->siteSettings->values(app()->getLocale(), true);
        $layout = (array) data_get($settings, 'sponsor_page.form_fields', []);
        $phone = $this->formLayouts->state($layout, 'phone');
        $address = $this->formLayouts->state($layout, 'address');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => $phone['enabled']
                ? [$phone['required'] ? 'required' : 'nullable', 'string', 'max:20']
                : ['exclude'],
            'address' => $address['enabled']
                ? [$address['required'] ? 'required' : 'nullable', 'string', 'max:255']
                : ['exclude'],
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
                'phone' => $phone['enabled'] ? ($validated['phone'] ?? null) : null,
                'address' => $address['enabled'] ? ($validated['address'] ?? null) : null,
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
            $toEmail = $this->adminNotificationAddress();
            $rendered = $this->emailTemplates->render(
                TransactionalEmailTemplateCatalog::SPONSORSHIP_ADMIN_NOTIFICATION,
                (string) config('transactional-mail.admin_locale', 'en'),
                [
                    'sponsor_name' => $data['name'] ?? '',
                    'sponsor_email' => $data['email'] ?? '',
                    'sponsor_phone' => $data['phone'] ?? '',
                    'sponsor_address' => $data['address'] ?? '',
                    'children_count' => $data['number_of_children'] ?? '',
                    'contribution_interval' => $data['contribution_interval'] ?? '',
                    'sponsorship_amount' => number_format((float) ($data['sponsorship_amount'] ?? 0), 2).' BDT',
                    'request_reference' => $data['transaction_id'] ?? ('SPONSOR-'.($data['id'] ?? 'NEW')),
                ]
            );

            Mail::to($toEmail)->send(new TransactionalEmail($rendered));

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
            $toEmail = trim((string) ($data['email'] ?? ''));
            if (filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('The sponsorship recipient address is invalid.');
            }
            $rendered = $this->emailTemplates->render(
                TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
                app()->getLocale(),
                [
                    'sponsor_name' => $data['name'] ?? '',
                    'response_hours' => '72',
                    'request_reference' => $data['transaction_id'] ?? ('SPONSOR-'.($data['id'] ?? 'NEW')),
                ]
            );

            Mail::to($toEmail)->send(new TransactionalEmail($rendered));

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

    private function adminNotificationAddress(): string
    {
        $address = trim((string) config('transactional-mail.admin_to'));
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('The transactional admin recipient is not configured.');
        }

        return $address;
    }
}
