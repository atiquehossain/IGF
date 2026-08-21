<?php

namespace Tests\Feature;

use App\Models\SslCommerzTransaction;
use App\Services\SSLCommerzService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentResultPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_result_exposes_only_display_safe_transaction_fields_and_is_not_cached(): void
    {
        $transaction = new SslCommerzTransaction([
            'tran_id' => 'IGF-SAFE-REFERENCE',
            'amount' => 2500,
            'currency_type' => 'BDT',
            'card_issuer' => 'Test Bank',
            'cus_name' => 'Community Donor',
            'cus_email' => 'private@example.test',
            'cus_phone' => '+8801700000000',
            'raw_response' => '{"secret":"must-not-leak"}',
        ]);
        $transaction->created_at = now();

        $this->mock(SSLCommerzService::class, function ($mock) use ($transaction) {
            $mock->shouldReceive('validateIpnAndVerify')->once()->andReturn(['tran_id' => 'IGF-SAFE-REFERENCE']);
            $mock->shouldReceive('updateDonationPayment')->once()->with(['tran_id' => 'IGF-SAFE-REFERENCE'], 'Success')->andReturn($transaction);
        });

        $response = $this->post('/donation/payment/success', ['tran_id' => 'IGF-SAFE-REFERENCE']);

        $response->assertOk()
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
            ->assertInertia(fn (Assert $page) => $page
                ->component('payment_success')
                ->where('meta_tag.robots', 'noindex,nofollow,noarchive')
                ->where('data.transaction.reference', 'IGF-SAFE-REFERENCE')
                ->where('data.transaction.donor_name', 'Community Donor')
                ->missing('data.transaction.raw_response')
                ->missing('data.transaction.cus_email')
                ->missing('data.transaction.cus_phone')
            );

        $this->assertStringNotContainsString('must-not-leak', $response->getContent());
        $this->assertStringNotContainsString('private@example.test', $response->getContent());
    }
}
