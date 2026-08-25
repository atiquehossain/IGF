<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\DonationType;
use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\SslCommerzTransaction;
use App\Services\DonationPaymentMethodService;
use App\Services\SSLCommerzService;
use App\Support\AdminPermissionSynchronizer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DonationPaymentMethodBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_exposes_all_safe_options_without_gateway_identifiers(): void
    {
        $cause = DonationType::create(['name' => 'Education', 'status' => 1]);
        $this->configureGateway();
        config()->set('sslcommerz.payment_methods.nagad.enabled', false);
        config()->set('sslcommerz.payment_methods.nagad.gateway_filter', null);

        $response = $this->get(route('frontend.donate.cause', ['cause' => $cause->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.checkout_key', fn (string $key) => app(SSLCommerzService::class)->isValidCheckoutKey($key))
                ->has('data.paymentMethods', 3)
                ->where('data.paymentMethods.0.key', 'bkash')
                ->where('data.paymentMethods.0.logos', [
                    ['src' => '/image/payment-methods/bkash-reference.svg'],
                ])
                ->where('data.paymentMethods.0.available', true)
                ->where('data.paymentMethods.1.key', 'nagad')
                ->where('data.paymentMethods.1.logos', [
                    ['src' => '/image/payment-methods/nagad.png'],
                ])
                ->where('data.paymentMethods.1.available', false)
                ->where('data.paymentMethods.2.key', 'card')
                ->where('data.paymentMethods.2.logos', [
                    ['src' => '/image/payment-methods/visa-reference.svg'],
                    ['src' => '/image/payment-methods/amex.png'],
                ])
                ->where('data.paymentMethods.2.available', true)
                ->where('data.paymentMethods.2.networks', ['Visa', 'American Express'])
                ->missing('data.paymentMethods.0.gateway_filter')
                ->missing('data.paymentMethods.1.gateway_filter')
                ->missing('data.paymentMethods.2.gateway_filter')
            );

        $response->assertDontSee('sandbox-password', false);
    }

    public function test_checkout_rejects_recurring_frequencies_until_a_recurring_provider_is_connected(): void
    {
        $cause = DonationType::create(['name' => 'Education', 'status' => 1]);
        $this->configureGateway();

        $this->postJson('/donate', array_merge($this->validPayload($cause, 'bkash'), [
            'frequency' => 'monthly',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('frequency');

        $this->assertDatabaseCount('donations', 0);
        Http::assertNothingSent();
    }

    public function test_provider_identity_and_supported_networks_ignore_legacy_editable_brand_settings(): void
    {
        $this->configureGateway();
        foreach ([
            'bkash_label' => 'Nagad',
            'nagad_label' => 'bKash',
            'card_label' => 'Cash only',
            'card_networks_label' => 'Mastercard',
        ] as $key => $value) {
            SiteSetting::create([
                'group' => 'donation_page',
                'key' => $key,
                'locale' => 'en',
                'value' => $value,
                'type' => 'text',
                'is_public' => true,
            ]);
        }

        $options = collect(app(DonationPaymentMethodService::class)->publicOptions('en'))->keyBy('key');

        $this->assertSame('bKash', $options['bkash']['label']);
        $this->assertNull($options['bkash']['networks']);
        $this->assertSame('Nagad', $options['nagad']['label']);
        $this->assertNull($options['nagad']['networks']);
        $this->assertSame('Card', $options['card']['label']);
        $this->assertSame(['Visa', 'American Express'], $options['card']['networks']);
        $this->assertSame([['src' => '/image/payment-methods/bkash-reference.svg']], $options['bkash']['logos']);
    }

    public function test_shared_readiness_requires_credentials_and_keeps_protected_configuration_private(): void
    {
        $this->configureGateway();
        $methods = app(DonationPaymentMethodService::class);

        $ready = $methods->operationalReadiness('bkash');
        $this->assertTrue($ready['ready']);
        $this->assertSame('Ready', $ready['status']);
        $this->assertArrayNotHasKey('gateway_filter', $ready);
        $this->assertArrayNotHasKey('store_id', $ready);
        $this->assertArrayNotHasKey('store_password', $ready);

        config()->set('sslcommerz.store_id', '   ');
        config()->set('sslcommerz.store_password', '');

        $notReady = $methods->operationalReadiness('bkash');
        $this->assertFalse($notReady['ready']);
        $this->assertSame('Not ready', $notReady['status']);
        $this->assertStringContainsString('credentials have not been configured', $notReady['message']);

        $options = collect($methods->publicOptions('en'))->keyBy('key');
        $this->assertFalse($options['bkash']['available']);
        $this->assertFalse($options['card']['available']);
        $this->assertNull($methods->resolveAvailable('bkash', 'en'));
    }

    public function test_browser_can_request_a_fresh_server_authenticated_checkout_key(): void
    {
        $first = $this->getJson(route('frontend.donate.checkout-key'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
            ->assertJsonPath('status', true)
            ->json('checkout_key');
        $second = $this->getJson(route('frontend.donate.checkout-key'))
            ->assertOk()
            ->json('checkout_key');

        $this->assertIsString($first);
        $this->assertIsString($second);
        $this->assertNotSame($first, $second);
        $this->assertTrue(app(SSLCommerzService::class)->isValidCheckoutKey($first));
        $this->assertTrue(app(SSLCommerzService::class)->isValidCheckoutKey($second));
        $this->assertFalse(app(SSLCommerzService::class)->isValidCheckoutKey(
            substr($first, 0, -1) . ($first[-1] === 'a' ? 'b' : 'a')
        ));
    }

    public function test_same_checkout_key_and_canonical_payload_reuses_one_hosted_session(): void
    {
        $cause = DonationType::create(['name' => 'Education', 'status' => 1]);
        $this->configureGateway();
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/idempotent-session',
                'sessionkey' => 'must-not-be-persisted',
            ]),
        ]);

        $payload = $this->validPayload($cause, 'bkash');
        $first = $this->postJson('/donate', $payload)
            ->assertOk()
            ->assertJsonPath('reused', false);
        $payload['amount'] = 500;
        $second = $this->postJson('/donate', $payload)
            ->assertOk()
            ->assertJsonPath('reused', true);

        $this->assertSame($first->json('tran_id'), $second->json('tran_id'));
        $this->assertSame($first->json('payment_url'), $second->json('payment_url'));
        $this->assertDatabaseCount('donations', 1);
        $this->assertDatabaseCount('ssl_commerz_transactions', 1);
        Http::assertSentCount(1);

        $storedUrl = DB::table('ssl_commerz_transactions')->value('gateway_redirect_url');
        $this->assertIsString($storedUrl);
        $this->assertNotSame($first->json('payment_url'), $storedUrl);
        $this->assertFalse(Schema::hasColumn('ssl_commerz_transactions', 'session_key'));
    }

    public function test_same_checkout_key_with_changed_payload_conflicts_without_second_gateway_call(): void
    {
        $cause = DonationType::create(['name' => 'Education', 'status' => 1]);
        $this->configureGateway();
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/original-session',
            ]),
        ]);

        $payload = $this->validPayload($cause, 'bkash');
        $first = $this->postJson('/donate', $payload)->assertOk();
        $payload['amount'] = '600.00';
        $conflict = $this->postJson('/donate', $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');

        $this->assertSame($first->json('tran_id'), $conflict->json('tran_id'));
        $this->assertTrue(app(SSLCommerzService::class)->isValidCheckoutKey(
            $conflict->json('replacement_checkout_key')
        ));
        $this->assertDatabaseCount('donations', 1);
        $this->assertDatabaseCount('ssl_commerz_transactions', 1);
        Http::assertSentCount(1);
    }

    public function test_database_unique_constraint_and_race_recovery_select_the_original_attempt(): void
    {
        $checkoutKey = app(SSLCommerzService::class)->issueCheckoutKey();
        $original = SslCommerzTransaction::forceCreate([
            'tran_id' => 'UNIQUE-ORIGINAL-1',
            'status' => 'PENDING',
            'checkout_key' => $checkoutKey,
            'request_fingerprint' => str_repeat('a', 64),
            'initialization_status' => 'INITIALIZING',
            'amount' => 100,
            'currency' => 'BDT',
        ]);

        $duplicateException = null;
        try {
            SslCommerzTransaction::forceCreate([
                'tran_id' => 'UNIQUE-DUPLICATE-1',
                'status' => 'PENDING',
                'checkout_key' => $checkoutKey,
                'request_fingerprint' => str_repeat('a', 64),
                'initialization_status' => 'INITIALIZING',
                'amount' => 100,
                'currency' => 'BDT',
            ]);
        } catch (QueryException $exception) {
            $duplicateException = $exception;
        }

        $this->assertInstanceOf(QueryException::class, $duplicateException);
        $raceAwareService = new class(app(DonationPaymentMethodService::class)) extends SSLCommerzService {
            public function recover(QueryException $exception, string $checkoutKey): ?SslCommerzTransaction
            {
                return $this->recoverCheckoutKeyRace($exception, $checkoutKey);
            }
        };
        $recovered = $raceAwareService->recover($duplicateException, $checkoutKey);

        $this->assertNotNull($recovered);
        $this->assertSame($original->id, $recovered->id);
        $this->assertSame('UNIQUE-ORIGINAL-1', $recovered->tran_id);
        $this->assertDatabaseCount('ssl_commerz_transactions', 1);
    }

    public function test_recorded_failed_attempt_is_never_sent_to_gateway_again(): void
    {
        $cause = DonationType::create(['name' => 'Education', 'status' => 1]);
        $this->configureGateway();
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response(['status' => 'FAILED', 'failedreason' => 'Unavailable']),
        ]);

        $failedPayload = $this->validPayload($cause, 'bkash');
        $first = $this->postJson('/donate', $failedPayload)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INITIALIZATION_FAILED');
        $this->postJson('/donate', $failedPayload)
            ->assertConflict()
            ->assertJsonPath('tran_id', $first->json('tran_id'))
            ->assertJsonPath('code', 'CHECKOUT_TERMINAL')
            ->assertJsonMissingPath('payment_url');
        Http::assertSentCount(1);
    }

    public function test_success_failed_and_cancelled_terminal_attempts_never_reuse_the_old_hosted_url(): void
    {
        $cause = DonationType::create(['name' => 'Education', 'status' => 1]);
        $this->configureGateway();
        $sequence = 0;
        Http::fake(function () use (&$sequence) {
            $sequence++;

            return Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/terminal-' . $sequence,
            ]);
        });

        foreach (['VALID', 'VALIDATED', 'FAILED', 'CANCELLED'] as $terminalStatus) {
            $payload = $this->validPayload($cause, 'bkash');
            $first = $this->postJson('/donate', $payload)->assertOk();
            SslCommerzTransaction::where('tran_id', $first->json('tran_id'))->update([
                'status' => $terminalStatus,
            ]);

            $closed = $this->postJson('/donate', $payload)
                ->assertConflict()
                ->assertJsonPath('code', 'CHECKOUT_TERMINAL')
                ->assertJsonPath('tran_id', $first->json('tran_id'))
                ->assertJsonMissingPath('payment_url');
            $this->assertTrue(app(SSLCommerzService::class)->isValidCheckoutKey(
                $closed->json('replacement_checkout_key')
            ));
        }

        Http::assertSentCount(4);
        $this->assertDatabaseCount('donations', 4);
        $this->assertDatabaseCount('ssl_commerz_transactions', 4);
    }

    public function test_uncertain_in_progress_attempt_requires_same_key_retry_without_a_second_gateway_call(): void
    {
        $cause = DonationType::create(['name' => 'Education', 'status' => 1]);
        $this->configureGateway();
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/interrupted-session',
            ]),
        ]);

        $payload = $this->validPayload($cause, 'card');
        $first = $this->postJson('/donate', $payload)->assertOk();
        $transaction = SslCommerzTransaction::where('tran_id', $first->json('tran_id'))->firstOrFail();
        $transaction->initialization_status = 'INITIALIZING';
        $transaction->gateway_redirect_url = null;
        $transaction->save();

        $this->postJson('/donate', $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'INITIALIZATION_IN_PROGRESS')
            ->assertJsonMissingPath('replacement_checkout_key');
        Http::assertSentCount(1);
    }

    public function test_response_lost_after_gateway_acceptance_is_uncertain_and_never_creates_a_second_attempt(): void
    {
        $cause = DonationType::create(['name' => 'Education', 'status' => 1]);
        $this->configureGateway();
        $gatewayCalls = 0;
        Http::fake(function () use (&$gatewayCalls): never {
            $gatewayCalls++;
            throw new ConnectionException('Simulated response lost after gateway acceptance.');
        });

        $payload = $this->validPayload($cause, 'bkash');
        $first = $this->postJson('/donate', $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'INITIALIZATION_UNCERTAIN')
            ->assertJsonMissingPath('payment_url')
            ->assertJsonMissingPath('replacement_checkout_key');
        $second = $this->postJson('/donate', $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'INITIALIZATION_UNCERTAIN')
            ->assertJsonPath('tran_id', $first->json('tran_id'))
            ->assertJsonMissingPath('payment_url')
            ->assertJsonMissingPath('replacement_checkout_key');

        $this->assertSame($first->json('tran_id'), $second->json('tran_id'));
        // Laravel records only completed fake responses, so count the throwing
        // transport callback directly to prove the retry did not leave process.
        $this->assertSame(1, $gatewayCalls);
        $this->assertDatabaseCount('donations', 1);
        $this->assertDatabaseCount('ssl_commerz_transactions', 1);
        $this->assertDatabaseHas('donations', [
            'transaction_id' => $first->json('tran_id'),
            'payment_status' => 'Pending',
            'donation_frequency' => 'one_time',
        ]);
        $this->assertDatabaseHas('ssl_commerz_transactions', [
            'tran_id' => $first->json('tran_id'),
            'status' => 'PENDING',
            'initialization_status' => 'UNCERTAIN',
        ]);
    }

    public function test_ambiguous_5xx_and_malformed_responses_are_uncertain_and_not_retried_at_gateway(): void
    {
        $cause = DonationType::create(['name' => 'Education', 'status' => 1]);
        $this->configureGateway();
        $responseNumber = 0;
        Http::fake(function () use (&$responseNumber) {
            $responseNumber++;

            return $responseNumber === 1
                ? Http::response(['status' => 'FAILED', 'failedreason' => 'Ambiguous upstream failure'], 503)
                : Http::response('not-json', 200, ['Content-Type' => 'text/plain']);
        });

        foreach (['bkash', 'card'] as $method) {
            $payload = $this->validPayload($cause, $method);
            $first = $this->postJson('/donate', $payload)
                ->assertConflict()
                ->assertJsonPath('code', 'INITIALIZATION_UNCERTAIN')
                ->assertJsonMissingPath('payment_url')
                ->assertJsonMissingPath('replacement_checkout_key');
            $this->postJson('/donate', $payload)
                ->assertConflict()
                ->assertJsonPath('code', 'INITIALIZATION_UNCERTAIN')
                ->assertJsonPath('tran_id', $first->json('tran_id'))
                ->assertJsonMissingPath('payment_url')
                ->assertJsonMissingPath('replacement_checkout_key');

            $this->assertDatabaseHas('donations', [
                'transaction_id' => $first->json('tran_id'),
                'payment_status' => 'Pending',
            ]);
            $this->assertDatabaseHas('ssl_commerz_transactions', [
                'tran_id' => $first->json('tran_id'),
                'status' => 'PENDING',
                'initialization_status' => 'UNCERTAIN',
            ]);
        }

        Http::assertSentCount(2);
        $this->assertDatabaseCount('donations', 2);
        $this->assertDatabaseCount('ssl_commerz_transactions', 2);
    }

    public function test_unknown_disabled_and_malformed_configured_methods_fail_before_writes_or_http(): void
    {
        $cause = DonationType::create(['name' => 'Education', 'status' => 1]);
        $this->configureGateway();
        config()->set('sslcommerz.payment_methods.nagad.enabled', true);
        config()->set('sslcommerz.payment_methods.nagad.gateway_filter', 'NAGAD;unsafe');
        SiteSetting::create([
            'group' => 'donation_page',
            'key' => 'enable_card',
            'locale' => '*',
            'value' => '0',
            'type' => 'boolean',
            'is_public' => true,
        ]);
        Http::fake();

        $missingMethod = $this->validPayload($cause, 'bkash');
        unset($missingMethod['payment_method']);
        $this->postJson('/donate', $missingMethod)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');

        foreach (['unknown', 'card', 'nagad'] as $method) {
            $this->postJson('/donate', $this->validPayload($cause, $method))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('payment_method');
        }

        $this->assertDatabaseCount('donations', 0);
        $this->assertDatabaseCount('ssl_commerz_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_each_available_method_uses_only_its_server_mapping_and_persists_tracking(): void
    {
        $cause = DonationType::create(['name' => 'Education', 'status' => 1]);
        $this->configureGateway();
        config()->set('sslcommerz.payment_methods.nagad.enabled', true);
        config()->set('sslcommerz.payment_methods.nagad.gateway_filter', 'merchant_nagad');

        $sequence = 0;
        Http::fake(function () use (&$sequence) {
            $sequence++;

            return Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/session-' . $sequence,
                'sessionkey' => 'safe-session-' . $sequence,
            ]);
        });

        $expectedFilters = [
            'bkash' => 'bkash',
            'nagad' => 'merchant_nagad',
            'card' => 'visacard,amexcard',
        ];

        foreach ($expectedFilters as $method => $filter) {
            $response = $this->postJson('/donate', $this->validPayload($cause, $method))
                ->assertOk()
                ->assertJsonPath('status', true);
            $transactionId = (string) $response->json('tran_id');

            $this->assertDatabaseHas('donations', [
                'transaction_id' => $transactionId,
                'requested_payment_method' => $method,
                'payment_status' => 'Pending',
            ]);
            $this->assertDatabaseHas('ssl_commerz_transactions', [
                'tran_id' => $transactionId,
                'requested_payment_method' => $method,
                'opted_b' => $method,
                'initialization_status' => 'INITIALIZED',
            ]);
        }

        $sentFilters = Http::recorded()
            ->map(fn (array $pair) => $pair[0]->data()['multi_card_name'] ?? null)
            ->values()
            ->all();
        $this->assertSame(array_values($expectedFilters), $sentFilters);
    }

    public function test_amount_requires_canonical_decimal_inside_approved_range(): void
    {
        $cause = DonationType::create(['name' => 'Education', 'status' => 1]);
        $this->configureGateway();
        Http::fake();

        foreach (['1e3', '10.001', '9.99', '500000.01', '1,000', '-10', '010'] as $amount) {
            $payload = $this->validPayload($cause, 'bkash');
            $payload['amount'] = $amount;
            $this->postJson('/donate', $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('amount');
        }

        $this->assertDatabaseCount('donations', 0);
        $this->assertDatabaseCount('ssl_commerz_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_service_rejects_internal_filter_mismatch_and_protects_gateway_fields(): void
    {
        $this->configureGateway();
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/protected',
                'sessionkey' => 'protected-session',
            ]),
        ]);

        $rejected = app(SSLCommerzService::class)->initializePayment(
            customerData: ['cus_name' => 'Safe Donor'],
            amount: 100,
            paymentMethod: 'bkash',
            gatewayFilter: 'visacard'
        );
        $this->assertFalse($rejected['success']);
        Http::assertNothingSent();
        $this->assertDatabaseCount('ssl_commerz_transactions', 0);

        $accepted = app(SSLCommerzService::class)->initializePayment(
            customerData: [
                'cus_name' => 'Safe Donor',
                'cus_email' => 'donor@example.test',
                'cus_phone' => '+8801700000000',
                'cus_add1' => 'Dhaka',
                'store_id' => 'attacker-store',
                'store_passwd' => 'attacker-secret',
                'total_amount' => '999999',
                'currency' => 'USD',
                'tran_id' => 'ATTACKER-ID',
                'success_url' => 'https://attacker.example/success',
                'value_a' => 'attacker-cause',
                'value_b' => 'attacker-method',
                'multi_card_name' => 'nagad',
            ],
            amount: 100,
            currency: 'BDT',
            meta: ['value_a' => 'real-cause', 'value_b' => 'bkash'],
            paymentMethod: 'bkash',
            gatewayFilter: 'bkash'
        );

        $this->assertTrue($accepted['success']);
        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $data['store_id'] === 'sandbox-store'
                && $data['store_passwd'] === 'sandbox-password'
                && $data['total_amount'] === '100.00'
                && $data['currency'] === 'BDT'
                && $data['tran_id'] !== 'ATTACKER-ID'
                && $data['success_url'] !== 'https://attacker.example/success'
                && $data['value_a'] === 'real-cause'
                && $data['value_b'] === 'bkash'
                && $data['multi_card_name'] === 'bkash';
        });
    }

    public function test_untrusted_gateway_redirect_is_never_returned_to_browser(): void
    {
        $cause = DonationType::create(['name' => 'Education', 'status' => 1]);
        $this->configureGateway();
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com.evil.example/pay',
                'sessionkey' => 'unsafe-session',
            ]),
        ]);

        $this->postJson('/donate', $this->validPayload($cause, 'bkash'))
            ->assertConflict()
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 'INITIALIZATION_UNCERTAIN')
            ->assertJsonMissingPath('payment_url')
            ->assertJsonMissingPath('replacement_checkout_key');

        $this->assertDatabaseHas('donations', ['payment_status' => 'Pending']);
        $this->assertDatabaseHas('ssl_commerz_transactions', [
            'status' => 'PENDING',
            'initialization_status' => 'UNCERTAIN',
        ]);
    }

    public function test_admin_donation_list_flags_uncertain_gateway_attempts_for_reconciliation(): void
    {
        $menu = AuthMenu::where('link', 'donations.index')->firstOrFail();
        $viewerRole = Role::create([
            'name' => 'Donation reconciliation viewer',
            'permission' => (string) $menu->id,
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $viewer = $this->adminForRole('Reconciliation Viewer', $viewerRole);
        $transaction = SslCommerzTransaction::forceCreate([
            'tran_id' => 'UNCERTAIN-ADMIN-1',
            'status' => 'PENDING',
            'amount' => 100,
            'currency' => 'BDT',
            'initialization_status' => 'UNCERTAIN',
            'initialization_error' => 'The gateway response was not received. Reconciliation is required.',
        ]);
        Donation::create([
            'donor_name' => 'QA Donor',
            'email' => 'qa@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'amount' => 100,
            'transaction_id' => $transaction->tran_id,
            'payment_status' => 'Pending',
        ]);

        $this->asAdmin($viewer)
            ->get(route('donations.index'))
            ->assertOk()
            ->assertSee('Gateway state')
            ->assertSee('Reconciliation required')
            ->assertSee('The gateway response was not received. Reconciliation is required.');
    }

    public function test_high_risk_mismatch_and_unknown_actual_method_are_held_for_review(): void
    {
        foreach ([
            ['suffix' => 'RISK', 'requested' => 'bkash', 'card_type' => 'BKASH-BKASH', 'risk_level' => '1'],
            ['suffix' => 'MISMATCH', 'requested' => 'bkash', 'card_type' => 'VISA-City Bank', 'risk_level' => '0'],
            ['suffix' => 'UNKNOWN', 'requested' => 'card', 'card_type' => 'Unrecognized channel', 'risk_level' => '0'],
            ['suffix' => 'RISK-MISSING', 'requested' => 'bkash', 'card_type' => 'BKASH-BKASH', 'risk_level' => null],
            ['suffix' => 'RISK-MALFORMED', 'requested' => 'bkash', 'card_type' => 'BKASH-BKASH', 'risk_level' => 'safe'],
        ] as $case) {
            $tranId = 'IGF-' . $case['suffix'];
            SslCommerzTransaction::create([
                'tran_id' => $tranId,
                'status' => 'PENDING',
                'amount' => 100,
                'currency' => 'BDT',
                'requested_payment_method' => $case['requested'],
            ]);
            Donation::create([
                'donor_name' => 'QA Donor',
                'email' => 'qa@example.test',
                'phone' => '+8801700000000',
                'address' => 'Dhaka',
                'amount' => 100,
                'transaction_id' => $tranId,
                'requested_payment_method' => $case['requested'],
                'payment_status' => 'Pending',
            ]);

            $gatewayData = [
                'tran_id' => $tranId,
                'status' => 'VALID',
                'amount' => '100.00',
                'card_type' => $case['card_type'],
            ];
            if ($case['risk_level'] !== null) {
                $gatewayData['risk_level'] = $case['risk_level'];
            }
            $result = app(SSLCommerzService::class)->updateDonationPayment($gatewayData, 'Success');

            $this->assertNotNull($result);
            $this->assertDatabaseHas('donations', [
                'transaction_id' => $tranId,
                'payment_status' => 'Review',
            ]);
        }
    }

    public function test_validation_requires_verified_currency_and_narrow_method_evidence(): void
    {
        $this->configureGateway();
        $tranId = 'CURRENCY-REQUIRED-1';
        SslCommerzTransaction::create([
            'tran_id' => $tranId,
            'status' => 'PENDING',
            'amount' => 100,
            'currency' => 'BDT',
            'requested_payment_method' => 'bkash',
            'opted_b' => 'bkash',
        ]);
        $callback = $this->signedCallback($tranId, 'VAL-CURRENCY');
        Http::fake([
            'sandbox.sslcommerz.com/validator/*' => Http::response([
                'status' => 'VALID',
                'tran_id' => $tranId,
                'amount' => '100.00',
                'value_b' => 'bkash',
                'card_type' => 'Unrecognized channel',
                'card_issuer' => 'bKash Visa Marketing Name',
            ]),
        ]);

        $this->assertNull(app(SSLCommerzService::class)->validateIpnAndVerify($callback));

        Donation::create([
            'donor_name' => 'QA Donor',
            'email' => 'qa@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'amount' => 100,
            'transaction_id' => $tranId,
            'requested_payment_method' => 'bkash',
            'payment_status' => 'Pending',
        ]);
        $updated = app(SSLCommerzService::class)->updateDonationPayment([
            'tran_id' => $tranId,
            'status' => 'VALID',
            'amount' => '100.00',
            'risk_level' => '0',
            'card_type' => 'Unrecognized channel',
            'card_issuer' => 'bKash Visa Marketing Name',
        ], 'Success');
        $this->assertNotNull($updated);
        $this->assertDatabaseHas('donations', [
            'transaction_id' => $tranId,
            'payment_status' => 'Review',
            'review_reason' => 'verified_method_unknown',
        ]);
    }

    public function test_signed_failed_and_cancelled_ipn_statuses_are_reconciled_without_validation_api(): void
    {
        $this->configureGateway();

        foreach (['FAILED' => 'Failed', 'CANCELLED' => 'Cancelled'] as $gatewayStatus => $donationStatus) {
            $tranId = 'TERMINAL-' . $gatewayStatus;
            SslCommerzTransaction::create([
                'tran_id' => $tranId,
                'status' => 'PENDING',
                'amount' => 100,
                'currency' => 'BDT',
                'requested_payment_method' => 'bkash',
                'opted_a' => 'cause-1',
                'opted_b' => 'bkash',
            ]);
            Donation::create([
                'donor_name' => 'QA Donor',
                'email' => 'qa@example.test',
                'phone' => '+8801700000000',
                'address' => 'Dhaka',
                'amount' => 100,
                'transaction_id' => $tranId,
                'requested_payment_method' => 'bkash',
                'payment_status' => 'Pending',
            ]);

            $callback = $this->signedTerminalCallback($tranId, $gatewayStatus);
            $this->postJson(route('frontend.donation.payment.ipn'), $callback)
                ->assertOk()
                ->assertJsonPath('status', 'IPN_RECEIVED');
            $this->assertDatabaseHas('ssl_commerz_transactions', [
                'tran_id' => $tranId,
                'status' => $gatewayStatus,
            ]);
            $this->assertDatabaseHas('donations', [
                'transaction_id' => $tranId,
                'payment_status' => $donationStatus,
            ]);
        }

        Http::assertNothingSent();
    }

    public function test_empty_gateway_credentials_reject_forged_callback_without_mutation_or_secret_logging(): void
    {
        config()->set('sslcommerz.store_id', '');
        config()->set('sslcommerz.store_password', '');
        app()->forgetInstance(SSLCommerzService::class);
        $tranId = 'EMPTY-CREDENTIAL-CALLBACK-1';
        SslCommerzTransaction::create([
            'tran_id' => $tranId,
            'status' => 'PENDING',
            'amount' => 100,
            'currency' => 'BDT',
            'requested_payment_method' => 'bkash',
        ]);
        Donation::create([
            'donor_name' => 'QA Donor',
            'email' => 'qa@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'amount' => 100,
            'transaction_id' => $tranId,
            'requested_payment_method' => 'bkash',
            'payment_status' => 'Pending',
        ]);

        $callback = [
            'tran_id' => $tranId,
            'status' => 'FAILED',
            'amount' => '100.00',
            'currency' => 'BDT',
            'verify_key' => 'status,tran_id,amount,currency',
            'store_passwd' => 'must-never-be-logged',
        ];
        $hashData = collect($callback)->only(['status', 'tran_id', 'amount', 'currency'])->all();
        $hashData['store_passwd'] = md5('');
        ksort($hashData);
        $callback['verify_sign'] = md5(collect($hashData)
            ->map(fn ($value, $key) => $key . '=' . $value)
            ->implode('&'));

        Log::spy();
        $this->postJson(route('frontend.donation.payment.ipn'), $callback)
            ->assertBadRequest()
            ->assertJsonPath('status', 'VERIFICATION_FAILED');

        $this->assertDatabaseHas('ssl_commerz_transactions', [
            'tran_id' => $tranId,
            'status' => 'PENDING',
        ]);
        $this->assertDatabaseHas('donations', [
            'transaction_id' => $tranId,
            'payment_status' => 'Pending',
        ]);
        Log::shouldHaveReceived('critical')
            ->once()
            ->with('SSLCommerz callback validation unavailable because credentials are missing.');
        Log::shouldHaveReceived('warning')
            ->with('Donation IPN validation failed', \Mockery::on(
                fn (array $context): bool => !str_contains(json_encode($context), 'must-never-be-logged')
                    && !array_key_exists('verify_sign', $context['callback'] ?? [])
                    && !array_key_exists('store_passwd', $context['callback'] ?? [])
            ));
    }

    public function test_validation_rejects_altered_cause_or_payment_method_metadata(): void
    {
        $this->configureGateway();

        foreach ([
            ['value_a' => 'wrong-cause', 'value_b' => 'bkash'],
            ['value_a' => 'real-cause', 'value_b' => 'card'],
        ] as $index => $metadata) {
            $tranId = 'META-CHECK-' . $index;
            SslCommerzTransaction::create([
                'tran_id' => $tranId,
                'status' => 'PENDING',
                'amount' => 100,
                'currency' => 'BDT',
                'requested_payment_method' => 'bkash',
                'opted_a' => 'real-cause',
                'opted_b' => 'bkash',
            ]);
            $callback = $this->signedCallback($tranId, 'VAL-' . $index);
            Http::fake([
                'sandbox.sslcommerz.com/validator/*' => Http::response(array_merge([
                    'status' => 'VALID',
                    'tran_id' => $tranId,
                    'amount' => '100.00',
                    'currency_type' => 'BDT',
                ], $metadata)),
            ]);

            $this->assertNull(app(SSLCommerzService::class)->validateIpnAndVerify($callback));
            $this->assertDatabaseHas('ssl_commerz_transactions', [
                'tran_id' => $tranId,
                'status' => 'PENDING',
            ]);
        }
    }

    public function test_reviewed_payment_result_does_not_claim_final_success(): void
    {
        $transaction = SslCommerzTransaction::create([
            'tran_id' => 'REVIEW-RESULT-1',
            'status' => 'VALID',
            'amount' => 100,
            'currency' => 'BDT',
            'requested_payment_method' => 'bkash',
            'card_type' => 'BKASH-BKASH',
        ]);
        Donation::create([
            'donor_name' => 'QA Donor',
            'email' => 'qa@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'amount' => 100,
            'transaction_id' => $transaction->tran_id,
            'requested_payment_method' => 'bkash',
            'payment_status' => 'Review',
        ]);

        $this->mock(SSLCommerzService::class, function ($mock) use ($transaction): void {
            $mock->shouldReceive('validateIpnAndVerify')->once()->andReturn(['tran_id' => $transaction->tran_id]);
            $mock->shouldReceive('updateDonationPayment')->once()->andReturn($transaction);
        });

        $this->post('/donation/payment/success', ['tran_id' => $transaction->tran_id])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', false)
                ->where('title', 'Payment under review')
                ->where('data.result_state', 'review')
                ->where('data.result_copy.title', 'Payment under review')
                ->where('data.result_copy.eyebrow', fn (string $copy) => str_contains(strtolower($copy), 'review'))
                ->where('data.result_copy.note', fn (string $copy) => str_contains(strtolower($copy), 'not a final'))
                ->where('data.message', fn (string $message) => str_contains(strtolower($message), 'review')
                    && !str_contains($message, 'has been received'))
            );
    }

    public function test_only_dedicated_payment_reviewer_permission_can_resolve_a_review(): void
    {
        $menu = AuthMenu::where('link', 'donations.index')->firstOrFail();
        $action = MenuAction::where('link', 'donations.review.resolve')->firstOrFail();
        $viewerRole = Role::create([
            'name' => 'Donation viewer',
            'permission' => (string) $menu->id,
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $reviewerRole = Role::create([
            'name' => 'Donation payment reviewer',
            'permission' => (string) $menu->id,
            'actionPermission' => (string) $action->id,
            'serial' => '[]',
            'status' => 1,
        ]);
        $viewer = $this->adminForRole('Donation Viewer', $viewerRole);
        $reviewer = $this->adminForRole('Payment Reviewer', $reviewerRole);

        SslCommerzTransaction::create([
            'tran_id' => 'ADMIN-REVIEW-1',
            'status' => 'VALID',
            'val_id' => 'VALIDATION-ADMIN-1',
            'amount' => 100,
            'currency' => 'BDT',
        ]);
        $donation = Donation::create([
            'donor_name' => 'QA Donor',
            'email' => 'qa@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'amount' => 100,
            'transaction_id' => 'ADMIN-REVIEW-1',
            'payment_status' => 'Review',
        ]);

        $this->asAdmin($viewer)
            ->put(route('donations.review.resolve', $donation), [
                'resolution_note' => 'A viewer must never resolve this payment.',
            ])
            ->assertForbidden();
        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'payment_status' => 'Review',
            'review_resolved_at' => null,
        ]);

        $this->asAdmin($reviewer)
            ->put(route('donations.review.resolve', $donation), [
                'resolution_note' => 'Matched the gateway validation ID to the settlement report.',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'payment_status' => 'Success',
            'review_resolved_by' => $reviewer->id,
            'review_resolution_note' => 'Matched the gateway validation ID to the settlement report.',
        ]);
    }

    public function test_deploy_permission_sync_registers_review_action_and_grants_owner_role(): void
    {
        $owner = Role::create([
            'name' => 'Super Admin',
            'is_owner' => true,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);

        app(AdminPermissionSynchronizer::class)->synchronize();

        $action = MenuAction::where('link', 'donations.review.resolve')->firstOrFail();
        $ownerActionIds = array_filter(explode(',', (string) $owner->fresh()->actionPermission));
        $this->assertContains((string) $action->id, $ownerActionIds);
    }

    private function configureGateway(): void
    {
        config()->set('sslcommerz.store_id', 'sandbox-store');
        config()->set('sslcommerz.store_password', 'sandbox-password');
        config()->set('sslcommerz.sandbox', true);
        config()->set('sslcommerz.payment_methods.bkash.enabled', true);
        config()->set('sslcommerz.payment_methods.bkash.gateway_filter', 'bkash');
        config()->set('sslcommerz.payment_methods.card.enabled', true);
        config()->set('sslcommerz.payment_methods.card.gateway_filter', 'visacard,amexcard');
    }

    private function validPayload(DonationType $cause, string $method): array
    {
        return [
            'amount' => '500.00',
            'donor_name' => 'QA Donor',
            'email' => 'qa@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'payment_cause' => $cause->uuid,
            'payment_method' => $method,
            'frequency' => 'one_time',
            'checkout_key' => app(SSLCommerzService::class)->issueCheckoutKey(),
        ];
    }

    private function signedCallback(string $tranId, string $valId): array
    {
        $callback = [
            'tran_id' => $tranId,
            'val_id' => $valId,
            'status' => 'VALID',
            'verify_key' => 'status,tran_id,val_id',
        ];
        $hashData = [
            'status' => 'VALID',
            'tran_id' => $tranId,
            'val_id' => $valId,
            'store_passwd' => md5('sandbox-password'),
        ];
        ksort($hashData);
        $callback['verify_sign'] = md5(collect($hashData)
            ->map(fn ($value, $key) => $key . '=' . $value)
            ->implode('&'));

        return $callback;
    }

    private function signedTerminalCallback(string $tranId, string $status): array
    {
        $callback = [
            'tran_id' => $tranId,
            'status' => $status,
            'amount' => '100.00',
            'currency' => 'BDT',
            'value_a' => 'cause-1',
            'value_b' => 'bkash',
            'verify_key' => 'status,tran_id,amount,currency,value_a,value_b',
        ];
        $hashData = array_merge(
            collect($callback)->only(['status', 'tran_id', 'amount', 'currency', 'value_a', 'value_b'])->all(),
            ['store_passwd' => md5('sandbox-password')]
        );
        ksort($hashData);
        $callback['verify_sign'] = md5(collect($hashData)
            ->map(fn ($value, $key) => $key . '=' . $value)
            ->implode('&'));

        return $callback;
    }

    private function adminForRole(string $name, Role $role): Admin
    {
        return Admin::create([
            'name' => $name,
            'username' => str($name)->slug() . '-' . uniqid(),
            'email' => str($name)->slug() . '-' . uniqid() . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);
    }

    private function asAdmin(Admin $admin): self
    {
        $this->actingAs($admin, 'admin');
        session()->put(Admin::SESSION_AUTH_VERSION, $admin->auth_version);

        return $this;
    }
}
