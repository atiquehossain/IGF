<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\DonationType;
use App\Services\DonationPaymentMethodService;
use App\Services\DonationCauseContentService;
use App\Services\DonationDestinationService;
use App\Services\LocalizationManager;
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
        protected DonationCauseContentService $causeContent,
        protected LocalizationManager $localization,
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
            __('donation.direct_unavailable')
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
                    ->map(fn (array $option): array => array_merge(
                        $option,
                        $this->causeContent->publicPayload($selectedCause, $locale)
                    ))
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
        $pageTitle = $causeName !== ''
            ? $this->donationTemplate($donationCopy, 'detail_page_title_template', 'Donate to {cause}', $causeName)
            : $this->donationCopy($donationCopy, 'fallback_page_title', 'Donate securely');
        $metaTitle = $causeName !== ''
            ? $this->donationTemplate($donationCopy, 'detail_meta_title_template', 'Donate to {cause} | Ignite Global Foundation', $causeName)
            : $this->donationCopy($donationCopy, 'fallback_meta_title', 'Donate Securely | Ignite Global Foundation');
        $metaKeywords = $causeName !== ''
            ? $this->donationTemplate($donationCopy, 'detail_meta_keywords_template', '{cause}, donate Bangladesh, Ignite Global Foundation', $causeName)
            : $this->donationCopy($donationCopy, 'fallback_meta_keywords', 'donate Bangladesh, community development, Ignite Global Foundation');
        $metaDescription = $causeDescription !== ''
            ? mb_substr(strip_tags($causeDescription), 0, 160)
            : ($causeName !== ''
                ? $this->donationTemplate(
                    $donationCopy,
                    'detail_meta_description_template',
                    'Support {cause} through a secure donation to Ignite Global Foundation.',
                    $causeName
                )
                : $this->donationCopy(
                    $donationCopy,
                    'fallback_meta_description',
                    'Support community-led education, healthcare, livelihoods, clean water, and urgent relief in Bangladesh through a secure donation.'
                ));

        $metaTag = [
            'meta_keyword'     => $metaKeywords,
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDescription,
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
                [
                    'name' => $this->donationCopy($donationCopy, 'home_breadcrumb_label', 'Home'),
                    'url' => (string) $this->seo->localizedUrl(url('/'), $locale),
                ],
                [
                    'name' => $this->donationCopy($donationCopy, 'donate_breadcrumb_label', 'Donate'),
                    'url' => (string) $this->seo->localizedUrl(url('/donate'), $locale),
                ],
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
            'title'  => $pageTitle,
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
                        $fail(__('donation.validation.checkout_session'));
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
                'payment_method' => (string) ($publicOption['unavailable_reason'] ?? __('donation.validation.payment_method_unavailable')),
            ]);
        }

        $cause = $this->destinations->resolveActiveCause(
            (string) $validated['payment_cause'],
            app()->getLocale()
        );
        if (!$cause) {
            throw ValidationException::withMessages([
                'payment_cause' => __('donation.validation.cause_unavailable'),
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
                    'success_url' => $this->localizedPaymentCallbackUrl('frontend.donation.payment.success'),
                    'fail_url'    => $this->localizedPaymentCallbackUrl('frontend.donation.payment.fail'),
                    'cancel_url'  => $this->localizedPaymentCallbackUrl('frontend.donation.payment.cancel'),
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
                    'message' => $payment['message'] ?? __('donation.initialization.failed'),
                    'tran_id' => $payment['tran_id'] ?? null,
                    'replacement_checkout_key' => $payment['replacement_checkout_key'] ?? null,
                ], fn ($value) => $value !== null), (int) ($payment['http_status'] ?? 422));
            }

            return response()->json([
                'status'      => true,
                'payment_url' => $payment['payment_url'],
                'tran_id'     => $payment['tran_id'],
                'reused'      => (bool) ($payment['reused'] ?? false),
                'message'     => __('donation.initialization.success'),
            ]);
        } catch (Exception $e) {
            Log::error('Donation init failed', [
                'exception_class' => $e::class,
            ]);

            return response()->json([
                'status'  => false,
                'code' => 'INTERNAL_ERROR',
                'message' => __('donation.initialization.error'),
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
        $this->applyPaymentResultLocale($request);
        $data = $request->all();

        $validated = $this->sslCommerz->validateIpnAndVerify($data);

        if (!$validated) {
            Log::warning('Donation success callback validation failed', [
                'callback' => $this->sslCommerz->callbackSummary($data),
            ]);

            return $this->renderPaymentPage(
                view: 'payment_fail',
                success: false,
                errorMessage: $this->paymentResultMessage(
                    'verification_failure_message',
                    'donation.result.verification_failed'
                )
            );
        }

        $transaction = $this->sslCommerz->updateDonationPayment($validated, 'Success');

        if (!$transaction) {
            return $this->renderPaymentPage(
                view: 'payment_fail',
                success: false,
                errorMessage: $this->paymentResultMessage(
                    'save_failure_message',
                    'donation.result.save_failed'
                )
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
            resultState: $requiresReview ? 'review' : 'success'
        );
    }

    /**
     * POST /donate/payment/fail
     */
    public function fail(Request $request)
    {
        $this->applyPaymentResultLocale($request);
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
            errorMessage: $this->paymentResultMessage('failure_message', 'donation.result.payment_failed')
        );
    }

    /**
     * POST /donate/payment/cancel
     */
    public function cancel(Request $request)
    {
        $this->applyPaymentResultLocale($request);
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
            errorMessage: $this->paymentResultMessage('cancelled_message', 'donation.result.payment_cancelled')
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
        $settings = $this->paymentResultSettings();
        $title = $titleOverride ?: ($success
            ? (string) ($settings['success_title'] ?? __('donation.result.success_title'))
            : (string) ($settings['failure_title'] ?? __('donation.result.failure_title')));

        $currency = strtoupper((string) (data_get($transaction, 'currency_type') ?: data_get($transaction, 'currency') ?: 'BDT'));
        $formattedAmount = $this->localizedPaymentAmount(
            (float) data_get($transaction, 'amount', 0),
            $currency
        );

        $message = $messageOverride ?: ($success
            ? $this->paymentResultMessage(
                'success_message',
                'donation.result.success_message',
                ['amount' => $formattedAmount],
                $settings
            )
            : ($errorMessage ?? $this->paymentResultMessage(
                'generic_failure_message',
                'donation.result.default_failure',
                settings: $settings
            )));

        $displayTransaction = $transaction ? [
            'donor_name' => (string) ($transaction->cus_name ?? ''),
            'amount' => (float) ($transaction->amount ?? 0),
            'currency' => $currency,
            'payment_method' => (string) ($transaction->card_issuer ?: $transaction->card_type ?: __('donation.result.online_payment')),
            'reference' => (string) ($transaction->tran_id ?? ''),
            'created_at' => optional($transaction->created_at)->toIso8601String(),
        ] : null;

        $resultCopy = null;
        if ($view === 'payment_success') {
            $resultState = $resultState === 'review' ? 'review' : 'success';
            $resultCopy = $resultState === 'review'
                ? [
                    'eyebrow' => (string) ($settings['review_eyebrow'] ?? __('donation.result.review_eyebrow')),
                    'title' => (string) ($settings['review_title'] ?? __('donation.result.review_title')),
                    'note' => (string) ($settings['review_note'] ?? __('donation.result.review_note')),
                    'message' => (string) ($messageOverride ?: ($settings['review_message'] ?? __('donation.result.review_message'))),
                ]
                : [
                    'eyebrow' => (string) ($settings['success_eyebrow'] ?? __('donation.result.success_eyebrow')),
                    'title' => (string) ($settings['success_title'] ?? $title),
                    'note' => (string) ($settings['success_note'] ?? __('donation.result.success_note')),
                    'message' => $message,
                ];
            $title = $resultCopy['title'];
            $message = $resultCopy['message'];
        }

        $siteName = trim((string) data_get(
            $this->siteSettings->values(app()->getLocale(), true),
            'branding.site_name',
            config('app.name')
        )) ?: (string) config('app.name');

        return Inertia::render($view, [
            'status' => $success,
            'title'  => $title,
            'meta_tag' => [
                'meta_title'       => $title . ' | ' . $siteName,
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

    private function localizedPaymentCallbackUrl(string $routeName): string
    {
        $parameter = (string) config('seo.locale_query_parameter', 'lang');
        $query = http_build_query([$parameter => app()->getLocale()], '', '&', PHP_QUERY_RFC3986);

        return route($routeName) . '?' . $query;
    }

    private function applyPaymentResultLocale(Request $request): void
    {
        $parameter = (string) config('seo.locale_query_parameter', 'lang');
        $locale = strtolower(trim((string) $request->query($parameter, '')));

        if ($locale === '' || !in_array($locale, $this->localization->publicLocales(), true)) {
            return;
        }

        app()->setLocale($locale);
        $request->session()->put('locale', $locale);
    }

    private function paymentResultSettings(): array
    {
        return (array) data_get(
            $this->siteSettings->values(app()->getLocale(), true),
            'system_pages',
            []
        );
    }

    private function donationCopy(array $settings, string $key, string $fallback): string
    {
        $value = trim(strip_tags((string) ($settings[$key] ?? '')));

        return $value !== '' ? $value : $fallback;
    }

    private function donationTemplate(
        array $settings,
        string $key,
        string $fallback,
        string $causeName
    ): string {
        $template = $this->donationCopy($settings, $key, $fallback);
        if (!str_contains($template, '{cause}')) {
            $template = $fallback;
        }

        return str_replace('{cause}', $causeName, $template);
    }

    private function localizedPaymentAmount(float $amount, string $currency): string
    {
        $number = number_format($amount, 2, '.', ',');
        if (app()->getLocale() !== 'bn') {
            return $currency . ' ' . $number;
        }

        $localizedNumber = strtr($number, [
            '0' => '০',
            '1' => '১',
            '2' => '২',
            '3' => '৩',
            '4' => '৪',
            '5' => '৫',
            '6' => '৬',
            '7' => '৭',
            '8' => '৮',
            '9' => '৯',
        ]);

        return ($currency === 'BDT' ? '৳' : $currency . ' ') . $localizedNumber;
    }

    private function paymentResultMessage(
        string $settingKey,
        string $translationKey,
        array $replacements = [],
        ?array $settings = null
    ): string {
        $settings ??= $this->paymentResultSettings();
        $message = trim((string) ($settings[$settingKey] ?? ''));
        if ($message === '') {
            $message = __($translationKey, $replacements);
        }

        foreach ($replacements as $key => $value) {
            $message = str_replace([':' . $key, '{' . $key . '}'], (string) $value, $message);
        }

        return $message;
    }
}
