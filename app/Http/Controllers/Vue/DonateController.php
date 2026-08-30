<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\DonationType;
use App\Services\DonationPaymentMethodService;
use App\Services\DonationDestinationService;
use App\Services\PublicStructuredDataService;
use App\Services\SSLCommerzService;
use App\Services\SeoMetadataService;
use App\Services\SiteSettingService;
use App\Services\TranslationCenterService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DonateController extends Controller
{
    public function __construct(
        protected SSLCommerzService $sslCommerz,
        protected TranslationCenterService $translations,
        protected DonationPaymentMethodService $paymentMethods,
        protected DonationDestinationService $destinations,
        protected SiteSettingService $siteSettings,
        protected PublicStructuredDataService $structuredData,
        protected SeoMetadataService $seo,
    ) {}

    /**
     * GET /donate
     */
    public function index(Request $request)
    {
        if ($request->filled('cause')) {
            $locale = app()->getLocale();
            $cause = $this->destinations->resolveActiveCause((string) $request->query('cause'), $locale);
            if ($cause) {
                $query = $request->query();
                unset($query['cause']);
                $url = $cause->purpose_key === 'direct'
                    ? route('frontend.donate.direct')
                    : route('frontend.donate.cause', ['cause' => $cause->slug]);
                if ($query !== []) {
                    $url .= '?' . http_build_query($query);
                }

                return redirect()->to($url);
            }
        }

        return $this->renderPage($request);
    }

    /**
     * GET /make-a-donation
     *
     * Render the admin-designated direct donation cause without exposing the
     * cause catalog. The protected role keeps this stable URL independent of
     * an individual cause slug while the visible content and destination stay
     * editable in the donation-cause manager.
     */
    public function direct(Request $request)
    {
        $cause = $this->destinations
            ->activeCauses(app()->getLocale())
            ->firstWhere('purpose_key', 'direct');

        abort_unless(
            $cause,
            503,
            'Direct donations are temporarily unavailable. Please contact Ignite Global Foundation for assistance.'
        );

        return $this->renderPage($request, (string) $cause->slug);
    }

    /**
     * GET /donate/{cause}
     */
    public function cause(Request $request, string $cause)
    {
        $selectedCause = $this->destinations->resolveActiveCause($cause, app()->getLocale());
        if ($selectedCause?->purpose_key === 'direct') {
            $url = route('frontend.donate.direct');
            if ($request->query() !== []) {
                $url .= '?' . http_build_query($request->query());
            }

            return redirect()->to($url, 301);
        }

        return $this->renderPage($request, $cause);
    }

    /**
     * Render either the public cause catalog or one private, no-store checkout.
     */
    private function renderPage(Request $request, ?string $causeToken = null)
    {
        $locale = app()->getLocale();
        $donationCopy = (array) data_get(
            $this->siteSettings->values($locale, true),
            'donation_page',
            []
        );
        $causes = $this->destinations->activeCauses($locale);
        $pageMode = $causeToken === null ? 'catalog' : 'detail';
        $donationGroups = collect();
        if ($pageMode === 'catalog') {
            $causeGroups = $causes
                ->pluck('causeGroup')
                ->filter(fn ($group): bool => $group !== null && (bool) $group->status)
                ->unique('id')
                ->sort(function ($left, $right): int {
                    $orderComparison = (int) $left->display_order <=> (int) $right->display_order;

                    return $orderComparison !== 0 ? $orderComparison : (int) $left->id <=> (int) $right->id;
                })
                ->values();
            $groupFallbacks = $causeGroups->mapWithKeys(fn ($group): array => [
                (string) $group->uuid => [
                    'name' => (string) $group->name,
                    'description' => (string) ($group->description ?? ''),
                ],
            ])->all();
            $localizedGroups = $this->translations->localizedContentValues(
                'donation_cause_group',
                $groupFallbacks,
                $locale
            );
            $donationGroups = $causeGroups->map(function ($group) use ($localizedGroups): array {
                $localized = $localizedGroups[(string) $group->uuid] ?? [];

                return [
                    'uuid' => (string) $group->uuid,
                    'slug' => (string) $group->slug,
                    'name' => (string) ($localized['name'] ?? $group->name),
                    'description' => (string) ($localized['description'] ?? $group->description ?? ''),
                ];
            })->values();
        }
        $localizedFallbacks = $causes->mapWithKeys(function (DonationType $cause): array {
            return [
                (string) $cause->uuid => [
                    'name' => (string) $cause->name,
                    'description' => (string) $cause->description,
                    'destination_name' => (string) $cause->destination_name,
                ],
            ];
        })->all();
        $localizedByUuid = $this->translations->localizedContentValues(
            'donation_cause',
            $localizedFallbacks,
            $locale
        );
        $directCauseUuid = (string) ($causes->firstWhere('purpose_key', 'direct')?->uuid ?? '');
        $donationTypes = $this->destinations
            ->publicOptions($causes, $locale, $localizedByUuid)
            ->map(function (array $option) use ($directCauseUuid): array {
                $option['url'] = $directCauseUuid !== '' && hash_equals($directCauseUuid, (string) $option['uuid'])
                    ? route('frontend.donate.direct')
                    : route('frontend.donate.cause', ['cause' => $option['slug']]);

                return $option;
            })
            ->values();

        $selectionWarning = null;
        $selectedCause = null;
        if ($causeToken !== null) {
            if ($causeToken === 'zakat') {
                $selectedCause = $causes->firstWhere('purpose_key', 'zakat');
                if (!$selectedCause) {
                    $selectionWarning = (string) ($donationCopy['selection_zakat_unavailable_warning']
                        ?? 'Zakat giving is temporarily unavailable while its restricted destination is reviewed.');
                    $donationTypes = collect();
                }
            } else {
                $selectedCause = $this->destinations->resolveActiveCause($causeToken, $locale);
                abort_unless($selectedCause, 404);
            }

            if ($selectedCause) {
                $donationTypes = $donationTypes
                    ->where('uuid', (string) $selectedCause->uuid)
                    ->values();
            }
        }

        $selectedProject = null;
        $selectedDestination = null;
        if ($selectedCause) {
            try {
                $selection = $this->destinations->resolveCheckoutSelection(
                    $selectedCause,
                    $request->query('project'),
                    $locale
                );
                $selectedProject = $selection['project'];
                $selectedDestination = [
                    'type' => $selection['destination_type'],
                    'uuid' => $selection['destination_uuid'],
                    'name' => $selection['destination_name'],
                    'project_uuid' => $selectedProject?->uuid,
                    'project_name' => $selectedProject?->name,
                ];
            } catch (ValidationException $exception) {
                $selectionWarning = (string) ($donationCopy['selection_unavailable_project_warning']
                    ?? 'The requested project is not compatible with this donation cause.');
            }
        } elseif ($request->filled('project') && !$selectionWarning) {
            $selectionWarning = (string) ($donationCopy['selection_choose_cause_first_warning']
                ?? 'Choose a donation cause before selecting a project.');
        }

        $selectedUUID = $selectedCause?->uuid;
        $selectedCauseOption = $selectedUUID
            ? $donationTypes->firstWhere('uuid', (string) $selectedUUID)
            : null;
        $causeName = trim((string) data_get($selectedCauseOption, 'name', ''));
        $causeDescription = trim((string) data_get($selectedCauseOption, 'description', ''));

        $metaTag = [
            'meta_keyword'     => $causeName !== ''
                ? $causeName . ', donate Bangladesh, Ignite Global Foundation'
                : 'donate Bangladesh, community development, Ignite Global Foundation',
            'meta_title'       => $causeName !== ''
                ? 'Donate to ' . $causeName . ' | Ignite Global Foundation'
                : 'Donate Securely | Ignite Global Foundation',
            'meta_description' => $causeDescription !== ''
                ? mb_substr(strip_tags($causeDescription), 0, 160)
                : 'Support community-led education, healthcare, livelihoods, clean water, and urgent relief in Bangladesh through a secure donation.',
            'meta_image'       => (string) data_get($selectedCauseOption, 'image', ''),
        ];
        $routeSeo = (array) $request->attributes->get('route_seo', []);
        $contentSeo = $selectedCause
            ? $this->seo->metaForModel($selectedCause, $metaTag, $request->url(), $locale)
            : [];
        if ($selectedCause?->purpose_key === 'direct' && $request->routeIs('frontend.donate.direct')) {
            $contentSeo['canonical_url'] = (string) $this->seo->localizedUrl(
                route('frontend.donate.direct'),
                $locale
            );
        }
        if (empty($contentSeo['schema_markup']) && empty($routeSeo['schema_markup'])) {
            $pageUrl = (string) $this->seo->localizedUrl($request->url(), $locale);
            $breadcrumbs = [
                ['name' => 'Home', 'url' => (string) $this->seo->localizedUrl(url('/'), $locale)],
                ['name' => 'Donate', 'url' => (string) $this->seo->localizedUrl(url('/donate'), $locale)],
            ];
            if ($causeName !== '') {
                $breadcrumbs[] = ['name' => $causeName, 'url' => $pageUrl];
            }

            $contentSeo['schema_markup'] = $this->structuredData->donation(
                (string) ($contentSeo['meta_title'] ?? $routeSeo['meta_title'] ?? $metaTag['meta_title']),
                (string) ($contentSeo['meta_description'] ?? $routeSeo['meta_description'] ?? $metaTag['meta_description']),
                $pageUrl,
                $breadcrumbs
            );
        }

        $response = Inertia::render('donate', [
            'status' => true,
            'title'  => $causeName !== '' ? 'Donate to ' . $causeName : 'Donate securely',
            'meta_tag' => $metaTag,
            'contentSeo' => $contentSeo,
            'data' => [
                'pageMode' => $pageMode,
                'catalogUrl' => route('frontend.donate.index'),
                'donationTypes' => $donationTypes,
                'donationGroups' => $donationGroups,
                'selectedUUID'  => $selectedUUID,
                'selectedCauseSlug' => $selectedCause?->slug,
                'selectedProjectUUID' => $selectedProject?->uuid,
                'selectedDestination' => $selectedDestination,
                'selection_warning' => $selectionWarning,
                'paymentMethods' => $pageMode === 'detail'
                    ? $this->paymentMethods->publicOptions($locale)
                    : [],
                'donationFrequencies' => $pageMode === 'detail' ? [
                    ['key' => 'one_time', 'available' => true],
                    ['key' => 'daily', 'available' => false],
                    ['key' => 'weekly', 'available' => false],
                    ['key' => 'monthly', 'available' => false],
                ] : [],
                'checkout_key' => $pageMode === 'detail'
                    ? $this->sslCommerz->issueCheckoutKey()
                    : null,
            ],
        ])->toResponse($request);

        return $pageMode === 'detail'
            ? $response->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                'Pragma' => 'no-cache',
            ])
            : $response;
    }

    /**
     * GET /donate/checkout-key
     *
     * A no-store way for the browser to begin a materially different attempt
     * without manufacturing idempotency identifiers on the client.
     */
    public function checkoutKey()
    {
        return response()->json([
            'status' => true,
            'checkout_key' => $this->sslCommerz->issueCheckoutKey(),
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * POST /donate
     */
    public function donate(Request $request)
    {
        $validated = $request->validate([
            'amount'        => ['required', 'numeric', 'regex:/^(?:0|[1-9]\d{0,5})(?:\.\d{1,2})?$/', 'min:10', 'max:500000'],
            'donor_name'    => 'required|string|max:50',
            'email'         => 'required|email|max:50',
            'phone'         => 'required|string|max:20',
            'address'       => 'required|string|max:50',
            'payment_cause' => ['required', 'string', 'max:255'],
            'project_uuid' => ['nullable', 'uuid'],
            'payment_method' => ['required', 'string', Rule::in($this->paymentMethods->publicKeys())],
            'frequency' => ['nullable', 'string', Rule::in(['one_time'])],
            'checkout_key' => [
                'required',
                'string',
                'max:110',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!$this->sslCommerz->isValidCheckoutKey(is_string($value) ? $value : null)) {
                        $fail('The checkout session is invalid. Refresh the payment form and try again.');
                    }
                },
            ],
        ]);

        $paymentMethod = $this->paymentMethods->resolveAvailable(
            (string) $validated['payment_method'],
            app()->getLocale()
        );

        if (!$paymentMethod) {
            $publicOption = collect($this->paymentMethods->publicOptions(app()->getLocale()))
                ->firstWhere('key', $validated['payment_method']);

            throw ValidationException::withMessages([
                'payment_method' => (string) ($publicOption['unavailable_reason'] ?? 'This payment method is unavailable.'),
            ]);
        }

        $cause = $this->destinations->resolveActiveCause(
            (string) $validated['payment_cause'],
            app()->getLocale()
        );
        if (!$cause) {
            throw ValidationException::withMessages([
                'payment_cause' => 'This donation cause is no longer available. Choose another cause and try again.',
            ]);
        }
        $selection = $this->destinations->resolveCheckoutSelection(
            $cause,
            $validated['project_uuid'] ?? null,
            app()->getLocale()
        );
        $project = $selection['project'];

        try {
            $payment = $this->sslCommerz
                ->setUrls([
                    'success_url' => route('frontend.donation.payment.success'),
                    'fail_url'    => route('frontend.donation.payment.fail'),
                    'cancel_url'  => route('frontend.donation.payment.cancel'),
                    'ipn_url'     => route('frontend.donation.payment.ipn'),
                ])
                ->initializePayment(
                    customerData: [
                        'cus_name'         => $validated['donor_name'],
                        'cus_email'        => $validated['email'],
                        'cus_phone'        => $validated['phone'],
                        'cus_add1'         => $validated['address'],
                        'cus_city'         => 'Dhaka',
                        'cus_country'      => 'Bangladesh',
                        'product_name'     => 'Donation for ' . ($project?->name ?: $cause->name),
                        'product_category' => 'Donation',
                        'product_profile'  => 'general',
                    ],
                    amount: (float) $validated['amount'],
                    currency: 'BDT',
                    meta: [
                        'value_a' => $cause->uuid,
                        'value_b' => $paymentMethod['key'],
                        'value_c' => $project?->uuid,
                        'value_d' => $cause->slug,
                    ],
                    persistLocalPayment: function (string $tranId) use ($validated, $cause, $paymentMethod, $selection, $project) {
                        Donation::create([
                            'donor_name' => $validated['donor_name'],
                            'email' => $validated['email'],
                            'phone' => $validated['phone'],
                            'address' => $validated['address'],
                            'payment_cause' => $cause->uuid,
                            'cause_uuid_snapshot' => $cause->uuid,
                            'cause_slug_snapshot' => $cause->slug,
                            'cause_name_snapshot' => $cause->name,
                            'purpose_key_snapshot' => $cause->purpose_key,
                            'destination_type_snapshot' => $selection['destination_type'],
                            'destination_uuid_snapshot' => $selection['destination_uuid'],
                            'destination_name_snapshot' => $selection['destination_name'],
                            'project_uuid_snapshot' => $project?->uuid,
                            'project_name_snapshot' => $project?->name,
                            'requested_payment_method' => $paymentMethod['key'],
                            'donation_frequency' => $validated['frequency'] ?? 'one_time',
                            'amount' => $validated['amount'],
                            'transaction_id' => $tranId,
                            'payment_status' => 'Pending',
                        ]);
                    },
                    paymentMethod: $paymentMethod['key'],
                    gatewayFilter: $paymentMethod['gateway_filter'],
                    checkoutKey: $validated['checkout_key']
                );

            if (!($payment['success'] ?? false)) {
                return response()->json(array_filter([
                    'status'  => false,
                    'code' => $payment['code'] ?? 'INITIALIZATION_FAILED',
                    'message' => $payment['message'] ?? 'Payment initialization failed.',
                    'tran_id' => $payment['tran_id'] ?? null,
                    'replacement_checkout_key' => $payment['replacement_checkout_key'] ?? null,
                ], fn ($value) => $value !== null), (int) ($payment['http_status'] ?? 422));
            }

            return response()->json([
                'status'      => true,
                'payment_url' => $payment['payment_url'],
                'tran_id'     => $payment['tran_id'],
                'reused'      => (bool) ($payment['reused'] ?? false),
                'message'     => 'Payment initialized successfully.',
            ]);
        } catch (Exception $e) {
            Log::error('Donation init failed', [
                'exception_class' => $e::class,
            ]);

            return response()->json([
                'status'  => false,
                'code' => 'INTERNAL_ERROR',
                'message' => 'Something went wrong during payment initialization.',
                'replacement_checkout_key' => $this->sslCommerz->issueCheckoutKey(),
            ], 500);
        }
    }

    /**
     * POST /donate/payment/success
     *
     * Browser redirect after payment.
     * Never trust this callback alone; validate with gateway API.
     */
    public function success(Request $request)
    {
        $data = $request->all();

        $validated = $this->sslCommerz->validateIpnAndVerify($data);

        if (!$validated) {
            Log::warning('Donation success callback validation failed', [
                'callback' => $this->sslCommerz->callbackSummary($data),
            ]);

            return $this->renderPaymentPage(
                view: 'payment_fail',
                success: false,
                errorMessage: 'Payment verification failed. Please contact support.'
            );
        }

        $transaction = $this->sslCommerz->updateDonationPayment($validated, 'Success');

        if (!$transaction) {
            return $this->renderPaymentPage(
                view: 'payment_fail',
                success: false,
                errorMessage: 'Payment was verified but could not be saved.'
            );
        }

        $requiresReview = Donation::query()
            ->where('transaction_id', $transaction->tran_id)
            ->where('payment_status', 'Review')
            ->exists();

        return $this->renderPaymentPage(
            view: 'payment_success',
            success: !$requiresReview,
            transaction: $transaction,
            titleOverride: $requiresReview ? 'Payment under review' : null,
            resultState: $requiresReview ? 'review' : 'success'
        );
    }

    /**
     * POST /donate/payment/fail
     */
    public function fail(Request $request)
    {
        $data = $request->all();
        $tranId = $data['tran_id'] ?? null;

        $validated = $tranId ? $this->sslCommerz->validateIpnAndVerify($data) : null;
        if ($validated && strtoupper((string) ($validated['status'] ?? '')) === 'FAILED') {
            $transaction = $this->sslCommerz->updateDonationPayment(
                $validated,
                'Failed'
            );
        }

        return $this->renderPaymentPage(
            view: 'payment_fail',
            success: false,
            transaction: $transaction ?? null,
            errorMessage: 'Your payment could not be processed. Please try again.'
        );
    }

    /**
     * POST /donate/payment/cancel
     */
    public function cancel(Request $request)
    {
        $data = $request->all();
        $tranId = $data['tran_id'] ?? null;

        $validated = $tranId ? $this->sslCommerz->validateIpnAndVerify($data) : null;
        if ($validated && strtoupper((string) ($validated['status'] ?? '')) === 'CANCELLED') {
            $transaction = $this->sslCommerz->updateDonationPayment(
                $validated,
                'Cancelled'
            );
        }

        return $this->renderPaymentPage(
            view: 'payment_fail',
            success: false,
            transaction: $transaction ?? null,
            errorMessage: 'You cancelled the payment.'
        );
    }

    /**
     * POST /donate/payment/ipn
     *
     * Server-to-server notification from SSLCommerz.
     * Must be excluded from CSRF.
     */
    public function ipn(Request $request)
    {
        $data = $request->all();

        $validated = $this->sslCommerz->validateIpnAndVerify($data);

        if (!$validated) {
            Log::warning('Donation IPN validation failed', [
                'callback' => $this->sslCommerz->callbackSummary($data),
            ]);

            return response()->json([
                'status' => 'VERIFICATION_FAILED',
            ], 400);
        }

        $gatewayStatus = strtoupper((string) ($validated['status'] ?? 'UNKNOWN'));

        $donationStatus = match ($gatewayStatus) {
            'VALID', 'VALIDATED' => 'Success',
            'FAILED'             => 'Failed',
            'CANCELLED'          => 'Cancelled',
            default              => 'Unknown',
        };

        $transaction = $this->sslCommerz->updateDonationPayment($validated, $donationStatus);

        if (!$transaction) {
            return response()->json([
                'status' => 'UPDATE_FAILED',
            ], 500);
        }

        return response()->json([
            'status' => 'IPN_RECEIVED',
        ], 200);
    }

    private function renderPaymentPage(
        string $view,
        bool $success,
        ?string $errorMessage = null,
        mixed $transaction = null,
        ?string $titleOverride = null,
        ?string $messageOverride = null,
        ?string $resultState = null
    ) {
        $title = $titleOverride ?: ($success ? 'Donation Successful' : 'Donation Failed');

        $message = $messageOverride ?: ($success
            ? 'Thank you! Your donation of BDT ' . number_format((float) ($transaction->amount ?? 0), 2) . ' has been received.'
            : ($errorMessage ?? "We're sorry - your donation payment could not be processed."));

        $displayTransaction = $transaction ? [
            'donor_name' => (string) ($transaction->cus_name ?? ''),
            'amount' => (float) ($transaction->amount ?? 0),
            'currency' => (string) ($transaction->currency_type ?: $transaction->currency ?: 'BDT'),
            'payment_method' => (string) ($transaction->card_issuer ?: $transaction->card_type ?: 'Online payment'),
            'reference' => (string) ($transaction->tran_id ?? ''),
            'created_at' => optional($transaction->created_at)->toIso8601String(),
        ] : null;

        $resultCopy = null;
        if ($view === 'payment_success') {
            $settings = $this->siteSettings->values(app()->getLocale(), true)['system_pages'] ?? [];
            $resultState = $resultState === 'review' ? 'review' : 'success';
            $resultCopy = $resultState === 'review'
                ? [
                    'eyebrow' => (string) ($settings['review_eyebrow'] ?? 'Verification complete · Review required'),
                    'title' => (string) ($settings['review_title'] ?? 'Payment under review'),
                    'note' => (string) ($settings['review_note'] ?? 'Keep the reference for your records. Our team must finish its safety review before this donation is marked successful.'),
                    'message' => (string) ($messageOverride ?: ($settings['review_message'] ?? $message)),
                ]
                : [
                    'eyebrow' => (string) ($settings['success_eyebrow'] ?? 'Payment confirmed'),
                    'title' => (string) ($settings['success_title'] ?? $title),
                    'note' => (string) ($settings['success_note'] ?? 'Keep the reference for your records. This page is private and is not indexed.'),
                    'message' => $message,
                ];
            $title = $resultCopy['title'];
            $message = $resultCopy['message'];
        }

        return Inertia::render($view, [
            'status' => $success,
            'title'  => $title,
            'meta_tag' => [
                'meta_title'       => $title . ' | Ignite Global Foundation',
                'meta_description' => $message,
                'robots' => 'noindex,nofollow,noarchive',
            ],
            'data' => [
                'message'      => $message,
                'result_state' => $resultState,
                'result_copy' => $resultCopy,
                'transaction'  => $displayTransaction,
                'redirect_url' => route('frontend.donate.index'),
            ],
        ])->toResponse(request())->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }
}
