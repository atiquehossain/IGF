<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Donation;
use App\Models\SslCommerzTransaction;
use App\Services\SSLCommerzService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentStateIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_gateway_transaction_cannot_be_created_by_a_callback(): void
    {
        $result = app(SSLCommerzService::class)->updateTransaction([
            'tran_id' => 'UNKNOWN-TRANSACTION',
            'status' => 'VALID',
            'amount' => '500.00',
        ]);

        $this->assertNull($result);
        $this->assertDatabaseMissing('ssl_commerz_transactions', [
            'tran_id' => 'UNKNOWN-TRANSACTION',
        ]);
    }

    public function test_successful_payment_cannot_be_downgraded_by_replayed_failure_callback(): void
    {
        SslCommerzTransaction::create([
            'tran_id' => 'IGNITE-VALID-001',
            'status' => 'PENDING',
            'amount' => 750,
            'currency' => 'BDT',
        ]);

        $service = app(SSLCommerzService::class);
        $service->updateTransaction([
            'tran_id' => 'IGNITE-VALID-001',
            'status' => 'VALID',
            'amount' => '750.00',
            'val_id' => 'validated-once',
            'card_no' => '4111111111111111',
        ]);
        $service->updateTransaction([
            'tran_id' => 'IGNITE-VALID-001',
            'status' => 'FAILED',
            'amount' => '750.00',
        ]);

        $transaction = SslCommerzTransaction::where('tran_id', 'IGNITE-VALID-001')->firstOrFail();

        $this->assertSame('VALID', $transaction->status);
        $this->assertSame('validated-once', $transaction->val_id);
        $this->assertNull($transaction->card_no);
    }

    public function test_card_number_is_not_mass_assignable_and_historical_value_is_scrubbed_on_update(): void
    {
        $transaction = SslCommerzTransaction::create([
            'tran_id' => 'NO-PAN-001',
            'status' => 'PENDING',
            'amount' => 100,
            'currency' => 'BDT',
            'card_no' => '4111111111111111',
        ]);
        $this->assertNull($transaction->card_no);

        DB::table('ssl_commerz_transactions')
            ->where('id', $transaction->id)
            ->update(['card_no' => '4111111111111111']);
        app(SSLCommerzService::class)->updateTransaction([
            'tran_id' => $transaction->tran_id,
            'status' => 'PENDING',
            'amount' => '100.00',
        ]);

        $this->assertNull($transaction->fresh()->card_no);
    }

    public function test_raw_gateway_audit_data_excludes_credentials_card_number_and_donor_pii(): void
    {
        $safe = app(SSLCommerzService::class)->sanitizePaymentData([
            'tran_id' => 'IGNITE-SAFE-001',
            'status' => 'VALID',
            'store_passwd' => 'must-not-survive',
            'card_no' => '4111111111111111',
            'cus_email' => 'donor@example.test',
            'cus_phone' => '+8801700000000',
            'amount' => '250.00',
        ]);

        $this->assertSame([
            'tran_id' => 'IGNITE-SAFE-001',
            'status' => 'VALID',
            'amount' => '250.00',
        ], $safe);
    }

    public function test_gateway_and_donation_status_change_atomically_and_replay_cannot_split_them(): void
    {
        SslCommerzTransaction::create([
            'tran_id' => 'IGNITE-ATOMIC-001',
            'status' => 'PENDING',
            'amount' => 500,
            'currency' => 'BDT',
            'requested_payment_method' => 'bkash',
            'opted_b' => 'bkash',
        ]);
        Donation::create([
            'donor_name' => 'QA Donor',
            'email' => 'qa@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'payment_cause' => 'qa-cause',
            'amount' => 500,
            'transaction_id' => 'IGNITE-ATOMIC-001',
            'requested_payment_method' => 'bkash',
            'payment_status' => 'Pending',
        ]);

        $service = app(SSLCommerzService::class);
        $success = $service->updateDonationPayment([
            'tran_id' => 'IGNITE-ATOMIC-001',
            'status' => 'VALID',
            'amount' => '500.00',
            'card_type' => 'BKASH-BKASH',
            'risk_level' => '0',
        ], 'Success');
        $replayedFailure = $service->updateDonationPayment([
            'tran_id' => 'IGNITE-ATOMIC-001',
            'status' => 'FAILED',
            'amount' => '500.00',
        ], 'Failed');

        $this->assertNotNull($success);
        $this->assertNull($replayedFailure);
        $this->assertDatabaseHas('ssl_commerz_transactions', ['tran_id' => 'IGNITE-ATOMIC-001', 'status' => 'VALID']);
        $this->assertDatabaseHas('donations', ['transaction_id' => 'IGNITE-ATOMIC-001', 'payment_status' => 'Success']);
    }

    public function test_gateway_transition_rolls_back_when_required_donation_record_is_missing(): void
    {
        SslCommerzTransaction::create([
            'tran_id' => 'IGNITE-ORPHAN-001',
            'status' => 'PENDING',
            'amount' => 100,
            'currency' => 'BDT',
        ]);

        $result = app(SSLCommerzService::class)->updateDonationPayment([
            'tran_id' => 'IGNITE-ORPHAN-001',
            'status' => 'VALID',
            'amount' => '100.00',
        ], 'Success');

        $this->assertNull($result);
        $this->assertDatabaseHas('ssl_commerz_transactions', ['tran_id' => 'IGNITE-ORPHAN-001', 'status' => 'PENDING']);
    }

    public function test_initialization_persists_both_local_records_before_contacting_gateway(): void
    {
        config()->set('sslcommerz.store_id', 'sandbox-store');
        config()->set('sslcommerz.store_password', 'sandbox-password');
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/session',
                'sessionkey' => 'safe-session',
            ]),
        ]);

        $result = app(SSLCommerzService::class)->initializePayment(
            customerData: [
                'cus_name' => 'QA Donor',
                'cus_email' => 'qa@example.test',
                'cus_phone' => '+8801700000000',
                'cus_add1' => 'Dhaka',
                'cus_city' => 'Dhaka',
                'cus_country' => 'Bangladesh',
            ],
            amount: 300,
            meta: ['value_a' => 'qa-cause'],
            persistLocalPayment: function (string $tranId) {
                Donation::create([
                    'donor_name' => 'QA Donor',
                    'email' => 'qa@example.test',
                    'phone' => '+8801700000000',
                    'address' => 'Dhaka',
                    'payment_cause' => 'qa-cause',
                    'amount' => 300,
                    'transaction_id' => $tranId,
                    'payment_status' => 'Pending',
                ]);
            }
        );

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('ssl_commerz_transactions', ['tran_id' => $result['tran_id'], 'status' => 'PENDING', 'cus_email' => null]);
        $this->assertDatabaseHas('donations', ['transaction_id' => $result['tran_id'], 'payment_status' => 'Pending']);
        Http::assertSentCount(1);
    }

    public function test_local_persistence_failure_prevents_any_gateway_request_and_rolls_back(): void
    {
        config()->set('sslcommerz.store_id', 'sandbox-store');
        config()->set('sslcommerz.store_password', 'sandbox-password');
        Http::fake();

        $result = app(SSLCommerzService::class)->initializePayment(
            customerData: ['cus_name' => 'QA Donor'],
            amount: 125,
            persistLocalPayment: fn () => throw new \RuntimeException('Simulated local failure')
        );

        $this->assertFalse($result['success']);
        $this->assertDatabaseCount('ssl_commerz_transactions', 0);
        $this->assertDatabaseCount('donations', 0);
        Http::assertNothingSent();
    }

    public function test_review_resolution_requires_verified_gateway_record_and_records_operator_audit(): void
    {
        $admin = Admin::create([
            'name' => 'Payment Reviewer',
            'username' => 'payment-reviewer',
            'email' => 'reviewer@example.test',
            'status' => 1,
            'password' => bcrypt('not-used-here'),
        ]);
        SslCommerzTransaction::create([
            'tran_id' => 'REVIEW-RESOLVE-1',
            'status' => 'VALID',
            'val_id' => 'VERIFIED-VAL-1',
            'amount' => 500,
            'currency' => 'BDT',
            'requested_payment_method' => 'bkash',
        ]);
        Donation::create([
            'donor_name' => 'QA Donor',
            'email' => 'qa@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'amount' => 500,
            'transaction_id' => 'REVIEW-RESOLVE-1',
            'requested_payment_method' => 'bkash',
            'payment_status' => 'Review',
        ]);
        DB::table('donations')->where('transaction_id', 'REVIEW-RESOLVE-1')->update([
            'review_reason' => 'gateway_high_risk',
        ]);

        $resolved = app(SSLCommerzService::class)->resolveReviewedDonation(
            'REVIEW-RESOLVE-1',
            $admin->id,
            'Matched the verified gateway record to the settlement report.'
        );

        $this->assertNotNull($resolved);
        $this->assertSame('Success', $resolved->payment_status);
        $this->assertSame($admin->id, (int) $resolved->review_resolved_by);
        $this->assertNotNull($resolved->review_resolved_at);
        $this->assertSame('gateway_high_risk', $resolved->review_reason);
        $this->assertNull(app(SSLCommerzService::class)->resolveReviewedDonation(
            'REVIEW-RESOLVE-1',
            $admin->id,
            'A second replay must never overwrite the original resolution audit.'
        ));

        SslCommerzTransaction::create([
            'tran_id' => 'UNVERIFIED-REVIEW-1',
            'status' => 'PENDING',
            'amount' => 500,
            'currency' => 'BDT',
        ]);
        Donation::create([
            'donor_name' => 'QA Donor',
            'email' => 'qa@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'amount' => 500,
            'transaction_id' => 'UNVERIFIED-REVIEW-1',
            'payment_status' => 'Review',
        ]);
        $this->assertNull(app(SSLCommerzService::class)->resolveReviewedDonation(
            'UNVERIFIED-REVIEW-1',
            $admin->id,
            'This cannot bypass the missing gateway verification evidence.'
        ));
    }
}
