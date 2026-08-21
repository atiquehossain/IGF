<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Donation;
use App\Models\SslCommerzTransaction;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class SSLCommerzService
{
  private const INITIALIZING = 'INITIALIZING';
  private const INITIALIZED = 'INITIALIZED';
  private const INITIALIZATION_FAILED = 'FAILED';
  private const INITIALIZATION_UNCERTAIN = 'UNCERTAIN';

  protected string $storeId;
  protected string $storePassword;
  protected string $baseUrl;
  protected bool $isSandbox;

  protected string $successUrl;
  protected string $failUrl;
  protected string $cancelUrl;
  protected string $ipnUrl;

  public function __construct(protected DonationPaymentMethodService $paymentMethods)
  {
    $this->storeId       = (string) config('sslcommerz.store_id');
    $this->storePassword = (string) config('sslcommerz.store_password');
    $this->isSandbox     = (bool) config('sslcommerz.sandbox', true);

    $this->baseUrl = $this->isSandbox
      ? 'https://sandbox.sslcommerz.com'
      : 'https://securepay.sslcommerz.com';

    // The project exposes one supported payment flow: donations.
    $this->successUrl = route('frontend.donation.payment.success');
    $this->failUrl    = route('frontend.donation.payment.fail');
    $this->cancelUrl  = route('frontend.donation.payment.cancel');
    $this->ipnUrl     = route('frontend.donation.payment.ipn');
  }

  /**
   * Set redirect and IPN URLs dynamically.
   */
  public function setUrls(array $urls): static
  {
    $this->successUrl = $urls['success_url'] ?? $this->successUrl;
    $this->failUrl    = $urls['fail_url'] ?? $this->failUrl;
    $this->cancelUrl  = $urls['cancel_url'] ?? $this->cancelUrl;
    $this->ipnUrl     = $urls['ipn_url'] ?? $this->ipnUrl;

    return $this;
  }

  /**
   * Initialize a payment session.
   *
   * Required customer keys usually include:
   * cus_name, cus_email, cus_phone, cus_add1, cus_city, cus_country
   */
  public function initializePayment(
    array $customerData,
    float $amount,
    string $currency = 'BDT',
    array $meta = [],
    ?callable $persistLocalPayment = null,
    ?string $paymentMethod = null,
    ?string $gatewayFilter = null,
    ?string $checkoutKey = null
  ): array {
    if ($paymentMethod !== null) {
      $resolvedMethod = $this->paymentMethods->resolveAvailable($paymentMethod);
      if (!$resolvedMethod) {
        return [
          'success' => false,
          'message' => 'The selected payment method is unavailable.',
        ];
      }

      $trustedFilter = (string) $resolvedMethod['gateway_filter'];
      if ($gatewayFilter !== null && !hash_equals($trustedFilter, $gatewayFilter)) {
        Log::warning('Rejected mismatched SSLCommerz gateway filter', [
          'payment_method' => $paymentMethod,
        ]);

        return [
          'success' => false,
          'message' => 'The selected payment method is invalid.',
        ];
      }

      $gatewayFilter = $trustedFilter;
      $meta['value_b'] = $paymentMethod;
      unset($meta['opt_b']);
    } elseif ($gatewayFilter !== null) {
      return [
        'success' => false,
        'message' => 'A gateway filter requires a valid payment method.',
      ];
    }

    if ($this->storeId === '' || $this->storePassword === '') {
      Log::critical('SSLCommerz credentials are not configured.');

      return [
        'success' => false,
        'message' => 'The payment gateway is temporarily unavailable.',
      ];
    }

    $checkoutKey ??= $this->issueCheckoutKey();
    if (!$this->isValidCheckoutKey($checkoutKey)) {
      return [
        'success' => false,
        'code' => 'INVALID_CHECKOUT_KEY',
        'http_status' => 422,
        'message' => 'Please refresh the checkout and try again.',
        'replacement_checkout_key' => $this->issueCheckoutKey(),
      ];
    }

    $allowedCustomerKeys = [
      'cus_name', 'cus_email', 'cus_phone', 'cus_add1', 'cus_add2',
      'cus_city', 'cus_state', 'cus_postcode', 'cus_country', 'cus_fax',
      'product_name', 'product_category', 'product_profile',
    ];
    $safeCustomerData = array_intersect_key($customerData, array_flip($allowedCustomerKeys));

    $requestFingerprint = $this->canonicalRequestFingerprint(
      customerData: $safeCustomerData,
      amount: $amount,
      currency: $currency,
      meta: $meta,
      paymentMethod: $paymentMethod
    );

    $tranId = $this->generateTransactionId();

    $payload = array_merge([
      'store_id'          => $this->storeId,
      'store_passwd'      => $this->storePassword,

      'total_amount'      => number_format($amount, 2, '.', ''),
      'currency'          => strtoupper($currency),
      'tran_id'           => $tranId,

      'success_url'       => $this->successUrl,
      'fail_url'          => $this->failUrl,
      'cancel_url'        => $this->cancelUrl,
      'ipn_url'           => $this->ipnUrl,

      'shipping_method'   => 'NO',
      'num_of_item'       => 1,
      'product_name'      => 'Online Payment',
      'product_category'  => 'General',
      'product_profile'   => 'general',

      'value_a'           => $meta['value_a'] ?? $meta['opt_a'] ?? null,
      'value_b'           => $meta['value_b'] ?? $meta['opt_b'] ?? null,
      'value_c'           => $meta['value_c'] ?? $meta['opt_c'] ?? null,
      'value_d'           => $meta['value_d'] ?? $meta['opt_d'] ?? null,
    ], $safeCustomerData);

    // Gateway filtering is always server-owned. Even an internal caller cannot
    // smuggle a visitor-supplied multi_card_name through customer data.
    unset($payload['multi_card_name']);
    if ($gatewayFilter !== null && trim($gatewayFilter) !== '') {
      $payload['multi_card_name'] = trim($gatewayFilter);
    }

    try {
      $reservation = $this->reservePaymentAttempt(
        checkoutKey: $checkoutKey,
        requestFingerprint: $requestFingerprint,
        tranId: $tranId,
        amount: $amount,
        currency: $currency,
        customerData: $customerData,
        meta: $meta,
        payload: $payload,
        paymentMethod: $paymentMethod,
        persistLocalPayment: $persistLocalPayment
      );
    } catch (Exception $e) {
      Log::error('SSLCommerz local payment preparation failed', [
        'exception_class' => $e::class,
        'tran_id' => $tranId,
      ]);

      return [
        'success' => false,
        'code' => 'PREPARATION_FAILED',
        'http_status' => 422,
        'message' => 'Payment preparation failed before contacting the gateway.',
        'replacement_checkout_key' => $this->issueCheckoutKey(),
      ];
    }

    if (!($reservation['created'] ?? false)) {
      return $this->existingAttemptResult(
        transaction: $reservation['transaction'],
        requestFingerprint: $requestFingerprint
      );
    }

    try {
      $response = Http::asForm()
        ->timeout(30)
        ->post("{$this->baseUrl}/gwprocess/v4/api.php", $payload);
      $result = $response->json();
    } catch (Exception $e) {
      Log::warning('SSLCommerz initialization outcome is uncertain', [
        'exception_class' => $e::class,
        'tran_id' => $tranId,
      ]);

      return $this->markInitializationUncertain(
        $tranId,
        'The gateway response was not received. Reconciliation is required before another attempt.'
      );
    }

    if (
      $response->successful() &&
      is_array($result) &&
      ($result['status'] ?? null) === 'SUCCESS' &&
      !empty($result['GatewayPageURL'])
    ) {
      $paymentUrl = (string) $result['GatewayPageURL'];
      if (!$this->isAllowedGatewayRedirectUrl($paymentUrl)) {
        Log::error('SSLCommerz returned an untrusted payment redirect', [
          'tran_id' => $tranId,
        ]);

        return $this->markInitializationUncertain(
          $tranId,
          'The gateway returned an unusable redirect. Reconciliation is required.'
        );
      }

      try {
        $stored = DB::transaction(function () use ($tranId, $paymentUrl): bool {
          $transaction = SslCommerzTransaction::where('tran_id', $tranId)
            ->lockForUpdate()
            ->first();

          if (!$transaction || $transaction->initialization_status !== self::INITIALIZING) {
            return false;
          }

          $transaction->gateway_redirect_url = $paymentUrl;
          $transaction->initialization_status = self::INITIALIZED;
          $transaction->initialization_error = null;
          $transaction->initialization_completed_at = now();

          return $transaction->save();
        });
      } catch (Exception $e) {
        Log::critical('SSLCommerz session was created but could not be recorded', [
          'exception_class' => $e::class,
          'tran_id' => $tranId,
        ]);

        return $this->markInitializationUncertain(
          $tranId,
          'The hosted session could not be recorded. Reconciliation is required.'
        );
      }

      if (!$stored) {
        Log::critical('SSLCommerz session was created but could not be recorded', [
          'tran_id' => $tranId,
        ]);

        return $this->markInitializationUncertain(
          $tranId,
          'The hosted session could not be recorded. Reconciliation is required.'
        );
      }

      return [
        'success'     => true,
        'payment_url' => $paymentUrl,
        'tran_id'     => $tranId,
        'reused'      => false,
      ];
    }

    if ($response->successful()
      && is_array($result)
      && ($result['status'] ?? null) === 'FAILED') {
      $failureMessage = is_string($result['failedreason'] ?? null)
        ? mb_substr(trim($result['failedreason']), 0, 200)
        : 'The payment gateway rejected the initialization request.';
      $this->markInitializationFailed($tranId, $failureMessage);
      Log::warning('SSLCommerz explicitly rejected payment initialization', [
        'response' => $this->sanitizePaymentData($result),
        'tran_id'  => $tranId,
      ]);

      return [
        'success' => false,
        'code' => 'INITIALIZATION_FAILED',
        'http_status' => 422,
        'message' => $failureMessage ?: 'The payment gateway rejected this attempt.',
        'tran_id' => $tranId,
        'replacement_checkout_key' => $this->issueCheckoutKey(),
      ];
    }

    Log::warning('SSLCommerz returned an ambiguous initialization response', [
      'http_status' => $response->status(),
      'response' => $this->sanitizePaymentData(is_array($result) ? $result : []),
      'tran_id' => $tranId,
    ]);

    return $this->markInitializationUncertain(
      $tranId,
      'The gateway response was ambiguous. Reconciliation is required before another attempt.'
    );
  }

  /**
   * Issue an opaque, server-authenticated token for one logical checkout.
   */
  public function issueCheckoutKey(): string
  {
    $uuid = (string) Str::uuid();

    return $uuid . '.' . hash_hmac('sha256', $uuid, $this->checkoutSigningKey());
  }

  public function isValidCheckoutKey(?string $checkoutKey): bool
  {
    if (!is_string($checkoutKey)
      || !preg_match('/^([0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})\.([0-9a-f]{64})$/i', $checkoutKey, $matches)) {
      return false;
    }

    $expected = hash_hmac('sha256', strtolower($matches[1]), $this->checkoutSigningKey());

    return hash_equals($expected, strtolower($matches[2]));
  }

  private function checkoutSigningKey(): string
  {
    return 'ignite-donation-checkout|' . (string) config('app.key');
  }

  private function canonicalRequestFingerprint(
    array $customerData,
    float $amount,
    string $currency,
    array $meta,
    ?string $paymentMethod
  ): string {
    $customer = [];
    foreach ($customerData as $key => $value) {
      $normalized = trim((string) $value);
      $customer[$key] = $key === 'cus_email' ? mb_strtolower($normalized) : $normalized;
    }
    ksort($customer);

    $metadata = [];
    foreach (['a', 'b', 'c', 'd'] as $slot) {
      $metadata['value_' . $slot] = trim((string) ($meta['value_' . $slot] ?? $meta['opt_' . $slot] ?? ''));
    }

    $canonical = json_encode([
      'amount' => number_format($amount, 2, '.', ''),
      'currency' => strtoupper(trim($currency)),
      'payment_method' => (string) $paymentMethod,
      'customer' => $customer,
      'metadata' => $metadata,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    return hash_hmac('sha256', $canonical, $this->checkoutSigningKey());
  }

  private function reservePaymentAttempt(
    string $checkoutKey,
    string $requestFingerprint,
    string $tranId,
    float $amount,
    string $currency,
    array $customerData,
    array $meta,
    array $payload,
    ?string $paymentMethod,
    ?callable $persistLocalPayment
  ): array {
    try {
      return DB::transaction(function () use (
        $checkoutKey,
        $requestFingerprint,
        $tranId,
        $amount,
        $currency,
        $customerData,
        $meta,
        $payload,
        $paymentMethod,
        $persistLocalPayment
      ): array {
        $existing = SslCommerzTransaction::where('checkout_key', $checkoutKey)
          ->lockForUpdate()
          ->first();

        if ($existing) {
          return ['created' => false, 'transaction' => $existing];
        }

        $transaction = $this->createPendingTransaction(
          tranId: $tranId,
          amount: $amount,
          currency: strtoupper($currency),
          customerData: $customerData,
          meta: $meta,
          fullPayload: $payload,
          paymentMethod: $paymentMethod,
          checkoutKey: $checkoutKey,
          requestFingerprint: $requestFingerprint
        );

        if ($persistLocalPayment) {
          $persistLocalPayment($tranId);
        }

        return ['created' => true, 'transaction' => $transaction];
      }, 3);
    } catch (QueryException $e) {
      $existing = $this->recoverCheckoutKeyRace($e, $checkoutKey);
      if (!$existing) {
        throw $e;
      }

      return ['created' => false, 'transaction' => $existing];
    }
  }

  private function existingAttemptResult(
    SslCommerzTransaction $transaction,
    string $requestFingerprint
  ): array {
    if (!is_string($transaction->request_fingerprint)
      || !hash_equals($transaction->request_fingerprint, $requestFingerprint)) {
      return [
        'success' => false,
        'code' => 'IDEMPOTENCY_CONFLICT',
        'http_status' => 409,
        'message' => 'This checkout key is already attached to different donation details.',
        'tran_id' => $transaction->tran_id,
        'replacement_checkout_key' => $this->issueCheckoutKey(),
      ];
    }

    if (strtoupper((string) $transaction->status) !== 'PENDING') {
      return [
        'success' => false,
        'code' => 'CHECKOUT_TERMINAL',
        'http_status' => 409,
        'message' => 'This checkout attempt is closed. Start a new payment attempt.',
        'tran_id' => $transaction->tran_id,
        'replacement_checkout_key' => $this->issueCheckoutKey(),
      ];
    }

    if ($transaction->initialization_status === self::INITIALIZED
      && is_string($transaction->gateway_redirect_url)
      && $this->isAllowedGatewayRedirectUrl($transaction->gateway_redirect_url)) {
      return [
        'success' => true,
        'payment_url' => $transaction->gateway_redirect_url,
        'tran_id' => $transaction->tran_id,
        'reused' => true,
      ];
    }

    if ($transaction->initialization_status === self::INITIALIZATION_FAILED) {
      return [
        'success' => false,
        'code' => 'INITIALIZATION_FAILED',
        'http_status' => 422,
        'message' => $transaction->initialization_error ?: 'Payment initialization failed.',
        'tran_id' => $transaction->tran_id,
        'replacement_checkout_key' => $this->issueCheckoutKey(),
      ];
    }

    if ($transaction->initialization_status === self::INITIALIZATION_UNCERTAIN) {
      return [
        'success' => false,
        'code' => 'INITIALIZATION_UNCERTAIN',
        'http_status' => 409,
        'message' => $transaction->initialization_error
          ?: 'The gateway outcome is uncertain. Reconciliation is required before another attempt.',
        'tran_id' => $transaction->tran_id,
      ];
    }

    return [
      'success' => false,
      'code' => 'INITIALIZATION_IN_PROGRESS',
      'http_status' => 409,
      'message' => 'Payment initialization is in progress. Retry with the same checkout key.',
      'tran_id' => $transaction->tran_id,
    ];
  }

  protected function recoverCheckoutKeyRace(
    QueryException $exception,
    string $checkoutKey
  ): ?SslCommerzTransaction {
    if (!$this->isCheckoutKeyUniqueViolation($exception)) {
      return null;
    }

    return SslCommerzTransaction::where('checkout_key', $checkoutKey)->first();
  }

  private function isCheckoutKeyUniqueViolation(QueryException $exception): bool
  {
    $message = strtolower($exception->getMessage());

    return in_array((string) $exception->getCode(), ['23000', '23505'], true)
      && (str_contains($message, 'checkout_key') || str_contains($message, 'ssl_transactions_checkout_key_unique'));
  }

  /**
   * Validate IPN signature only.
   */
  public function validateIpn(array $ipnData): bool
  {
    if ($this->storeId === '' || $this->storePassword === '') {
      Log::critical('SSLCommerz callback validation unavailable because credentials are missing.');
      return false;
    }

    if (empty($ipnData['verify_sign']) || empty($ipnData['verify_key'])) {
      Log::warning('SSLCommerz IPN missing verify_sign/verify_key', [
        'callback' => $this->callbackSummary($ipnData),
      ]);
      return false;
    }

    $verifyKeys = array_map('trim', explode(',', (string) $ipnData['verify_key']));
    $hashData = [];

    foreach ($verifyKeys as $key) {
      if ($key === '') {
        continue;
      }

      $hashData[$key] = $ipnData[$key] ?? '';
    }

    $hashData['store_passwd'] = md5($this->storePassword);

    ksort($hashData);

    $pairs = [];
    foreach ($hashData as $key => $value) {
      $pairs[] = $key . '=' . $value;
    }

    $computedHash = md5(implode('&', $pairs));

    if (!hash_equals((string) $ipnData['verify_sign'], $computedHash)) {
      Log::warning('SSLCommerz IPN hash mismatch', [
        'callback' => $this->callbackSummary($ipnData),
      ]);
      return false;
    }

    return true;
  }

  /**
   * Verify with SSLCommerz validation API using val_id.
   */
  public function verifyTransactionByValId(string $valId): ?array
  {
    $url = "{$this->baseUrl}/validator/api/validationserverAPI.php";

    try {
      $response = Http::timeout(30)->get($url, [
        'val_id'       => $valId,
        'store_id'     => $this->storeId,
        'store_passwd' => $this->storePassword,
        'v'            => 1,
        'format'       => 'json',
      ]);

      if ($response->successful()) {
        return $response->json();
      }

      Log::error('SSLCommerz verifyTransactionByValId failed', [
        'status' => $response->status(),
        'val_id' => $valId,
      ]);
    } catch (Exception $e) {
      Log::error('SSLCommerz verifyTransactionByValId exception', [
        'exception_class' => $e::class,
        'val_id' => $valId,
      ]);
    }

    return null;
  }

  /**
   * Full validation flow for IPN/success callback.
   *
   * Returns validation response if valid, otherwise null.
   */
  public function validateIpnAndVerify(array $callbackData): ?array
  {
    if (!$this->validateIpn($callbackData)) {
      return null;
    }

    $tranId = $callbackData['tran_id'] ?? null;
    $callbackStatus = strtoupper(trim((string) ($callbackData['status'] ?? '')));

    if (!$tranId) {
      Log::warning('SSLCommerz callback missing tran_id', [
        'callback' => $this->callbackSummary($callbackData),
      ]);
      return null;
    }

    $local = SslCommerzTransaction::where('tran_id', $tranId)->first();

    if (!$local) {
      Log::warning('SSLCommerz callback rejected for unknown transaction', [
        'tran_id' => $tranId,
      ]);
      return null;
    }

    if (in_array($callbackStatus, ['FAILED', 'CANCELLED'], true)) {
      return $this->immutableGatewayFactsMatch($callbackData, $local, true)
        ? array_merge($callbackData, ['status' => $callbackStatus])
        : null;
    }

    if (!$this->isSuccessful($callbackStatus)) {
      Log::warning('SSLCommerz callback has unsupported status', [
        'callback' => $this->callbackSummary($callbackData),
      ]);
      return null;
    }

    $valId = $callbackData['val_id'] ?? null;
    if (!$valId) {
      Log::warning('SSLCommerz successful callback missing val_id', [
        'callback' => $this->callbackSummary($callbackData),
      ]);
      return null;
    }

    $validation = $this->verifyTransactionByValId((string) $valId);

    if (!$validation || !$this->isValidStatus((string) ($validation['status'] ?? ''))) {
      Log::warning('SSLCommerz validation API invalid response', [
        'callback' => $this->callbackSummary($callbackData),
        'validation_status' => $validation['status'] ?? null,
      ]);
      return null;
    }

    if (($validation['tran_id'] ?? null) !== $tranId) {
      Log::warning('SSLCommerz tran_id mismatch', [
        'callback_tran_id'   => $tranId,
        'validation_tran_id' => $validation['tran_id'] ?? null,
      ]);
      return null;
    }

    return $this->immutableGatewayFactsMatch($validation, $local, true)
      ? $validation
      : null;
  }

  private function immutableGatewayFactsMatch(
    array $gatewayData,
    SslCommerzTransaction $local,
    bool $requireAmountAndCurrency
  ): bool {
    $tranId = (string) $local->tran_id;
    if (!hash_equals($tranId, (string) ($gatewayData['tran_id'] ?? ''))) {
      Log::warning('SSLCommerz immutable transaction reference mismatch', ['tran_id' => $tranId]);
      return false;
    }

    $requestedMethod = (string) ($local->requested_payment_method ?? '');
    $storedMethodMetadata = (string) ($local->opted_b ?? '');
    if ($requestedMethod !== '' && $storedMethodMetadata !== ''
      && !hash_equals($requestedMethod, $storedMethodMetadata)) {
      Log::warning('SSLCommerz local payment-method metadata is inconsistent', ['tran_id' => $tranId]);
      return false;
    }

    foreach (['a', 'b', 'c', 'd'] as $slot) {
      $responseKey = 'value_' . $slot;
      $localKey = 'opted_' . $slot;
      $expected = (string) ($local->{$localKey} ?? '');
      $received = array_key_exists($responseKey, $gatewayData)
        ? (string) ($gatewayData[$responseKey] ?? '')
        : '';

      // Compare every slot symmetrically. In particular, a callback cannot
      // inject a project UUID into value_c when the Pending reservation had
      // no donor-selected project.
      if (!hash_equals($expected, $received)) {
        Log::warning('SSLCommerz pass-through metadata mismatch', [
          'tran_id' => $tranId,
          'slot' => $slot,
        ]);
        return false;
      }
    }

    if ($requestedMethod !== '' && array_key_exists('value_b', $gatewayData)
      && !hash_equals($requestedMethod, (string) $gatewayData['value_b'])) {
      Log::warning('SSLCommerz requested payment method mismatch', ['tran_id' => $tranId]);
      return false;
    }

    $amountPresent = array_key_exists('amount', $gatewayData) || array_key_exists('total_amount', $gatewayData);
    $validatedAmount = (float) ($gatewayData['amount'] ?? $gatewayData['total_amount'] ?? 0);
    if (($requireAmountAndCurrency && !$amountPresent)
      || round($validatedAmount, 2) !== round((float) $local->amount, 2)) {
      Log::warning('SSLCommerz amount missing or mismatched', ['tran_id' => $tranId]);
      return false;
    }

    $validatedCurrency = strtoupper(trim((string) ($gatewayData['currency_type'] ?? $gatewayData['currency'] ?? '')));
    $localCurrency = strtoupper(trim((string) $local->currency));
    if (($requireAmountAndCurrency && ($validatedCurrency === '' || $localCurrency === ''))
      || !hash_equals($localCurrency, $validatedCurrency)) {
      Log::warning('SSLCommerz currency missing or mismatched', ['tran_id' => $tranId]);
      return false;
    }

    return true;
  }

  /**
   * Optional helper for success callback validation too.
   */
  public function validateSuccessAndVerify(array $callbackData): ?array
  {
    // Some success callbacks contain the same signed payload.
    return $this->validateIpnAndVerify($callbackData);
  }

  /**
   * Create initial pending transaction.
   */
  protected function createPendingTransaction(
    string $tranId,
    float $amount,
    string $currency,
    array $customerData,
    array $meta,
    array $fullPayload,
    ?string $paymentMethod = null,
    ?string $checkoutKey = null,
    ?string $requestFingerprint = null
  ): SslCommerzTransaction {
    return SslCommerzTransaction::forceCreate([
      'tran_id'      => $tranId,
      'status'       => 'PENDING',
      'requested_payment_method' => $paymentMethod,
      'checkout_key' => $checkoutKey,
      'request_fingerprint' => $requestFingerprint,
      'initialization_status' => self::INITIALIZING,
      'amount'       => $amount,
      'currency'     => strtoupper($currency),
      'cus_name'     => null,
      'cus_email'    => null,
      'cus_phone'    => null,
      'opted_a'      => $meta['value_a'] ?? $meta['opt_a'] ?? null,
      'opted_b'      => $meta['value_b'] ?? $meta['opt_b'] ?? null,
      'opted_c'      => $meta['value_c'] ?? $meta['opt_c'] ?? null,
      'opted_d'      => $meta['value_d'] ?? $meta['opt_d'] ?? null,
      'raw_response' => json_encode($this->sanitizePaymentData($fullPayload)),
    ]);
  }

  /**
   * Update transaction with validated callback data.
   */
  public function updateTransaction(array $data): ?SslCommerzTransaction
  {
    $tranId = $data['tran_id'] ?? null;

    if (!$tranId) {
      Log::error('SSLCommerz updateTransaction without tran_id');
      return null;
    }

    try {
      return DB::transaction(fn () => $this->applyTransactionUpdate($tranId, $data));
    } catch (Exception $e) {
      Log::error('SSLCommerz updateTransaction failed', [
        'exception_class' => $e::class,
        'tran_id' => $tranId,
      ]);
      return null;
    }
  }

  /**
   * Atomically update the gateway record and its required donation record.
   */
  public function updateDonationPayment(array $data, string $donationStatus): ?SslCommerzTransaction
  {
    $tranId = (string) ($data['tran_id'] ?? '');
    if ($tranId === '') {
      return null;
    }

    try {
      return DB::transaction(function () use ($tranId, $data, $donationStatus) {
        $transaction = $this->applyTransactionUpdate($tranId, $data);
        if (!$transaction) {
          throw new \RuntimeException('Gateway transition was rejected.');
        }

        $donation = Donation::where('transaction_id', $tranId)->lockForUpdate()->first();
        if (!$donation) {
          throw new \RuntimeException('Matching donation was not found.');
        }

        $current = strtolower((string) $donation->payment_status);
        $incoming = strtolower($donationStatus);

        if ($incoming === 'success') {
          $requestedMethod = (string) ($donation->requested_payment_method ?: $transaction->requested_payment_method);
          $actualMethod = $this->verifiedPaymentMethodKey($data);
          $riskLevel = array_key_exists('risk_level', $data) ? (string) $data['risk_level'] : null;
          $reviewReasons = [];

          if ($riskLevel !== '0') {
            $reviewReasons[] = $riskLevel === '1' ? 'gateway_high_risk' : 'gateway_risk_unverified';
          }
          if ($requestedMethod === '') {
            $reviewReasons[] = 'requested_method_missing';
          } elseif ($actualMethod === null) {
            $reviewReasons[] = 'verified_method_unknown';
          } elseif (!hash_equals($requestedMethod, $actualMethod)) {
            $reviewReasons[] = 'payment_method_mismatch';
          }

          if ($reviewReasons !== []) {
            $incoming = 'review';
            Log::warning('Verified donation requires manual review', [
              'tran_id' => $tranId,
              'requested_payment_method' => $requestedMethod ?: null,
              'verified_payment_method' => $actualMethod,
              'risk_level' => $riskLevel,
              'review_reasons' => $reviewReasons,
            ]);
          }
        }

        $isSuccessfulOutcome = in_array($incoming, ['success', 'review'], true);
        $allowed = $incoming === $current
          || ($isSuccessfulOutcome && in_array($current, ['pending', 'failed', 'cancelled'], true))
          || (!$isSuccessfulOutcome && $current === 'pending' && in_array($incoming, ['failed', 'cancelled'], true));

        if (!$allowed) {
          throw new \RuntimeException('Donation transition was rejected.');
        }

        $donation->payment_status = ucfirst($incoming);
        if ($incoming === 'review') {
          $donation->review_reason = implode(',', $reviewReasons ?? ['manual_review_required']);
        }
        $donation->save();

        return $transaction;
      });
    } catch (Exception $e) {
      Log::warning('Atomic donation payment update rejected', [
        'tran_id' => $tranId,
        'exception_class' => $e::class,
      ]);

      return null;
    }
  }

  /**
   * Resolve a verified Review donation to Success with an immutable operator
   * identity, timestamp and explanation. It cannot bypass gateway verification.
   */
  public function resolveReviewedDonation(
    string $tranId,
    int $resolvedBy,
    string $resolutionNote
  ): ?Donation {
    $resolutionNote = trim($resolutionNote);
    if ($resolvedBy < 1
      || !Admin::whereKey($resolvedBy)->where('status', 1)->exists()
      || mb_strlen($resolutionNote) < 10
      || mb_strlen($resolutionNote) > 1000) {
      return null;
    }

    try {
      return DB::transaction(function () use ($tranId, $resolvedBy, $resolutionNote): Donation {
        $transaction = SslCommerzTransaction::where('tran_id', $tranId)
          ->lockForUpdate()
          ->firstOrFail();
        $donation = Donation::where('transaction_id', $tranId)
          ->lockForUpdate()
          ->firstOrFail();

        if (!$this->isSuccessful((string) $transaction->status)
          || (!$transaction->val_id && !$transaction->bank_tran_id)
          || strtolower((string) $donation->payment_status) !== 'review'
          || $donation->review_resolved_at !== null) {
          throw new \RuntimeException('The reviewed payment is not eligible for resolution.');
        }

        $donation->payment_status = 'Success';
        $donation->review_resolved_at = now();
        $donation->review_resolved_by = $resolvedBy;
        $donation->review_resolution_note = $resolutionNote;
        $donation->save();

        Log::notice('Reviewed donation resolved to success', [
          'tran_id' => $tranId,
          'resolved_by' => $resolvedBy,
          'review_reason' => $donation->review_reason,
        ]);

        return $donation->fresh();
      });
    } catch (Exception $e) {
      Log::warning('Reviewed donation resolution rejected', [
        'tran_id' => $tranId,
        'resolved_by' => $resolvedBy,
        'exception_class' => $e::class,
      ]);

      return null;
    }
  }

  private function applyTransactionUpdate(string $tranId, array $data): ?SslCommerzTransaction
  {
    $transaction = SslCommerzTransaction::where('tran_id', $tranId)
      ->lockForUpdate()
      ->first();

    if (!$transaction) {
      Log::warning('SSLCommerz update rejected for unknown transaction', ['tran_id' => $tranId]);
      return null;
    }

    $currentStatus = strtoupper((string) $transaction->status);
    $incomingStatus = strtoupper((string) ($data['status'] ?? 'UNKNOWN'));
    $successfulStatuses = ['VALID', 'VALIDATED'];
    $allowed = in_array($incomingStatus, $successfulStatuses, true)
      || ($currentStatus === 'PENDING' && in_array($incomingStatus, ['FAILED', 'CANCELLED'], true))
      || $incomingStatus === $currentStatus;

    if (!$allowed || (in_array($currentStatus, $successfulStatuses, true) && !in_array($incomingStatus, $successfulStatuses, true))) {
      Log::warning('SSLCommerz status transition rejected', [
        'tran_id' => $tranId,
        'from' => $currentStatus,
        'to' => $incomingStatus,
      ]);
      return null;
    }

    $transaction->fill([
      'status'                   => $incomingStatus,
      'val_id'                   => $data['val_id'] ?? $transaction->val_id,
      // The authorized amount and pass-through metadata are immutable local
      // facts. Callback values are verified separately and must not rewrite
      // the original request.
      'amount'                   => $transaction->amount,
      'store_amount'             => $data['store_amount'] ?? $transaction->store_amount,
      'bank_tran_id'             => $data['bank_tran_id'] ?? $transaction->bank_tran_id,
      'card_type'                => $data['card_type'] ?? $transaction->card_type,
      'card_issuer'              => $data['card_issuer'] ?? $transaction->card_issuer,
      'card_brand'               => $data['card_brand'] ?? $transaction->card_brand,
      'card_issuer_country'      => $data['card_issuer_country'] ?? $transaction->card_issuer_country,
      'card_issuer_country_code' => $data['card_issuer_country_code'] ?? $transaction->card_issuer_country_code,
      'currency_type'            => $data['currency_type'] ?? $data['currency'] ?? $transaction->currency_type,
      'currency_amount'          => $data['currency_amount'] ?? $transaction->currency_amount,
      'currency_rate'            => $data['currency_rate'] ?? $transaction->currency_rate,
      'base_fair'                => $data['base_fair'] ?? $transaction->base_fair,
      'opted_a'                  => $transaction->opted_a,
      'opted_b'                  => $transaction->opted_b,
      'opted_c'                  => $transaction->opted_c,
      'opted_d'                  => $transaction->opted_d,
      'risk_title'               => $data['risk_title'] ?? $transaction->risk_title,
      'risk_level'               => $data['risk_level'] ?? $transaction->risk_level,
      'raw_response'             => json_encode($this->sanitizePaymentData($data)),
    ]);

    // Never retain a PAN, including historical values or mass-assigned input.
    $transaction->card_no = null;
    $transaction->save();

    return $transaction->fresh();
  }

  private function markInitializationFailed(string $tranId, string $message = 'Payment initialization failed.'): void
  {
    DB::transaction(function () use ($tranId, $message) {
      $transaction = SslCommerzTransaction::where('tran_id', $tranId)
        ->lockForUpdate()
        ->first();
      if ($transaction && $transaction->status === 'PENDING') {
        $transaction->status = 'FAILED';
        $transaction->initialization_status = self::INITIALIZATION_FAILED;
        $transaction->initialization_error = mb_substr(trim($message), 0, 255);
        $transaction->initialization_completed_at = now();
        $transaction->save();
      }
      Donation::where('transaction_id', $tranId)
        ->where('payment_status', 'Pending')
        ->update(['payment_status' => 'Failed']);
    });
  }

  private function markInitializationUncertain(string $tranId, string $message): array
  {
    try {
      DB::transaction(function () use ($tranId, $message): void {
        $transaction = SslCommerzTransaction::where('tran_id', $tranId)
          ->lockForUpdate()
          ->first();
        if (!$transaction || $transaction->status !== 'PENDING') {
          return;
        }

        $transaction->initialization_status = self::INITIALIZATION_UNCERTAIN;
        $transaction->initialization_error = mb_substr(trim($message), 0, 255);
        $transaction->initialization_completed_at = null;
        $transaction->save();
      });
    } catch (Exception $e) {
      Log::critical('Unable to record uncertain SSLCommerz initialization', [
        'exception_class' => $e::class,
        'tran_id' => $tranId,
      ]);
    }

    return [
      'success' => false,
      'code' => 'INITIALIZATION_UNCERTAIN',
      'http_status' => 409,
      'message' => $message,
      'tran_id' => $tranId,
    ];
  }

  private function isAllowedGatewayRedirectUrl(string $url): bool
  {
    $parts = parse_url($url);
    if (!is_array($parts)) {
      return false;
    }

    $expectedHost = $this->isSandbox
      ? 'sandbox.sslcommerz.com'
      : 'securepay.sslcommerz.com';

    return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
      && strtolower((string) ($parts['host'] ?? '')) === $expectedHost
      && !isset($parts['user'])
      && !isset($parts['pass'])
      && (!isset($parts['port']) || (int) $parts['port'] === 443);
  }

  private function verifiedPaymentMethodKey(array $data): ?string
  {
    $details = strtolower(trim(implode(' ', array_filter([
      $data['card_type'] ?? null,
      $data['card_brand'] ?? null,
    ]))));

    if (preg_match('/(?:^|[^a-z0-9])bkash(?:[^a-z0-9]|$)/', $details)) {
      return 'bkash';
    }

    if (preg_match('/(?:^|[^a-z0-9])nagad(?:[^a-z0-9]|$)/', $details)) {
      return 'nagad';
    }

    if (preg_match('/(?:^|[^a-z0-9])(?:visa|amex|american express)(?:[^a-z0-9]|$)/', $details)) {
      return 'card';
    }

    return null;
  }

  /**
   * Refund request.
   */
  public function refund(string $bankTranId, float $amount, string $reason = 'Customer request'): ?array
  {
    $url = "{$this->baseUrl}/validator/api/merchantTransIDvalidationAPI.php";

    try {
      $response = Http::timeout(30)->get($url, [
        'bank_tran_id'   => $bankTranId,
        'store_id'       => $this->storeId,
        'store_passwd'   => $this->storePassword,
        'refund_amount'  => number_format($amount, 2, '.', ''),
        'refund_remarks' => $reason,
        'format'         => 'json',
      ]);

      if ($response->successful()) {
        return $response->json();
      }

      Log::error('SSLCommerz refund failed', [
        'status'       => $response->status(),
        'bank_tran_id' => $bankTranId,
      ]);
    } catch (Exception $e) {
      Log::error('SSLCommerz refund exception', [
        'exception_class' => $e::class,
        'bank_tran_id' => $bankTranId,
      ]);
    }

    return null;
  }

  /**
   * Generate unique transaction ID.
   */
  protected function generateTransactionId(): string
  {
    $prefix = 'TRX';
    $date   = now()->format('Ymd');
    $tries  = 0;

    do {
      $random = strtoupper(Str::random(6));
      $micro  = str_pad((string) (now()->micro % 10000), 4, '0', STR_PAD_LEFT);
      $tranId = $prefix . $date . $random . $micro;
      $exists = SslCommerzTransaction::where('tran_id', $tranId)->exists();
      $tries++;
    } while ($exists && $tries < 5);

    return $tranId;
  }

  public function isSuccessful(string $status): bool
  {
    return in_array(strtoupper($status), ['VALID', 'VALIDATED'], true);
  }

  public function isValidStatus(string $status): bool
  {
    return in_array(strtoupper($status), ['VALID', 'VALIDATED'], true);
  }

  /**
   * Return only identifiers that are safe and useful in operational logs.
   */
  public function callbackSummary(array $data): array
  {
    return array_filter([
      'tran_id' => $data['tran_id'] ?? null,
      'val_id' => $data['val_id'] ?? null,
      'status' => $data['status'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
  }

  /**
   * Keep raw gateway audit data free of credentials, full card data and donor PII.
   */
  public function sanitizePaymentData(array $data): array
  {
    $allowed = [
      'tran_id', 'val_id', 'bank_tran_id', 'status', 'amount', 'total_amount',
      'store_amount', 'currency', 'currency_type', 'currency_amount',
      'currency_rate', 'base_fair', 'card_type', 'card_brand',
      'card_issuer_country_code', 'risk_level', 'risk_title',
      'value_a', 'value_b', 'value_c', 'value_d', 'opt_a', 'opt_b', 'opt_c', 'opt_d',
    ];

    return array_intersect_key($data, array_flip($allowed));
  }
}
