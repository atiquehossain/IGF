<?php

namespace Tests\Feature;

use App\Models\DonationType;
use App\Models\SiteSetting;
use App\Models\SslCommerzTransaction;
use App\Models\TranslationLocale;
use App\Services\SSLCommerzService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentResultLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bangla_server_language_files_cover_the_english_contract_and_keep_placeholders(): void
    {
        foreach (['validation', 'auth', 'passwords', 'pagination', 'donation'] as $file) {
            $english = $this->flattenLanguageLines(require lang_path("en/{$file}.php"));
            $bangla = $this->flattenLanguageLines(require lang_path("bn/{$file}.php"));

            foreach ($english as $key => $source) {
                $this->assertArrayHasKey($key, $bangla, "Missing Bangla language line: {$file}.{$key}");
                preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*|(?<!\{)\{[A-Za-z_][A-Za-z0-9_]*\}(?!\})/', $source, $matches);

                foreach (array_unique($matches[0] ?? []) as $placeholder) {
                    $this->assertStringContainsString(
                        $placeholder,
                        $bangla[$key],
                        "Bangla language line {$file}.{$key} must retain {$placeholder}"
                    );
                }
            }
        }
    }

    public function test_bangla_checkout_uses_bangla_validation_and_preserves_locale_in_gateway_callbacks(): void
    {
        $this->enableBangla();
        $this->configureGateway();
        $this->withSession(['locale' => 'bn'])
            ->postJson('/donate', [])
            ->assertUnprocessable()
            ->assertHeader('Content-Language', 'bn')
            ->assertJsonPath('errors.donor_name.0', 'পূর্ণ নাম ঘরটি পূরণ করা আবশ্যক।')
            ->assertJsonPath('errors.payment_method.0', 'পেমেন্ট পদ্ধতি ঘরটি পূরণ করা আবশ্যক।');

        $cause = DonationType::create(['name' => 'Education', 'status' => 1]);
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/localized-session',
                'sessionkey' => 'localized-session',
            ]),
        ]);

        $response = $this->withSession(['locale' => 'bn'])
            ->postJson('/donate', $this->validPayload($cause))
            ->assertOk()
            ->assertHeader('Content-Language', 'bn')
            ->assertJsonPath('message', 'পেমেন্ট সফলভাবে শুরু হয়েছে।');

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_ends_with((string) ($data['success_url'] ?? ''), '/donation/payment/success?lang=bn')
                && str_ends_with((string) ($data['fail_url'] ?? ''), '/donation/payment/fail?lang=bn')
                && str_ends_with((string) ($data['cancel_url'] ?? ''), '/donation/payment/cancel?lang=bn');
        });

        $this->assertNotEmpty($response->json('tran_id'));
    }

    public function test_bangla_success_result_uses_admin_editable_copy_and_a_localized_amount_message(): void
    {
        $this->enableBangla();
        SiteSetting::create([
            'group' => 'system_pages',
            'key' => 'success_title',
            'locale' => 'bn',
            'value' => 'আপনার সহায়তার জন্য আন্তরিক ধন্যবাদ।',
            'type' => 'text',
            'is_public' => true,
        ]);
        SiteSetting::create([
            'group' => 'system_pages',
            'key' => 'success_message',
            'locale' => 'bn',
            'value' => 'আপনার {amount} অনুদান আমরা পেয়েছি।',
            'type' => 'textarea',
            'is_public' => true,
        ]);

        $transaction = new SslCommerzTransaction([
            'tran_id' => 'IGF-BN-SUCCESS',
            'amount' => 1250,
            'currency_type' => 'BDT',
            'card_issuer' => 'bKash',
            'cus_name' => 'Bangla Donor',
        ]);
        $transaction->created_at = now();

        $this->mock(SSLCommerzService::class, function ($mock) use ($transaction): void {
            $mock->shouldReceive('validateIpnAndVerify')->once()->andReturn(['tran_id' => $transaction->tran_id]);
            $mock->shouldReceive('updateDonationPayment')->once()->andReturn($transaction);
        });

        $this->post('/donation/payment/success?lang=bn', ['tran_id' => $transaction->tran_id])
            ->assertOk()
            ->assertHeader('Content-Language', 'bn')
            ->assertInertia(fn (Assert $page) => $page
                ->component('payment_success')
                ->where('title', 'আপনার সহায়তার জন্য আন্তরিক ধন্যবাদ।')
                ->where('data.result_state', 'success')
                ->where('data.result_copy.title', 'আপনার সহায়তার জন্য আন্তরিক ধন্যবাদ।')
                ->where('data.message', 'আপনার BDT 1,250.00 অনুদান আমরা পেয়েছি।')
            );
    }

    public function test_bangla_cancel_result_uses_admin_editable_failure_copy(): void
    {
        $this->enableBangla();
        SiteSetting::create([
            'group' => 'system_pages',
            'key' => 'cancelled_message',
            'locale' => 'bn',
            'value' => 'আপনার অনুরোধে পেমেন্টটি বাতিল করা হয়েছে।',
            'type' => 'textarea',
            'is_public' => true,
        ]);

        $this->post('/donation/payment/cancel?lang=bn')
            ->assertOk()
            ->assertHeader('Content-Language', 'bn')
            ->assertInertia(fn (Assert $page) => $page
                ->component('payment_fail')
                ->where('title', 'আপনার অনুদান প্রক্রিয়া করা হয়নি।')
                ->where('data.message', 'আপনার অনুরোধে পেমেন্টটি বাতিল করা হয়েছে।')
                ->where('siteSettings.system_pages.try_again_label', 'আবার চেষ্টা করুন')
            );
    }

    private function enableBangla(): void
    {
        TranslationLocale::query()->where('locale', 'bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
    }

    private function configureGateway(): void
    {
        config()->set('sslcommerz.store_id', 'sandbox-store');
        config()->set('sslcommerz.store_password', 'sandbox-password');
        config()->set('sslcommerz.sandbox', true);
        config()->set('sslcommerz.payment_methods.bkash.enabled', true);
        config()->set('sslcommerz.payment_methods.bkash.gateway_filter', 'bkash');
    }

    private function validPayload(DonationType $cause): array
    {
        return [
            'amount' => '500.00',
            'donor_name' => 'Bangla QA Donor',
            'email' => 'qa@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'payment_cause' => $cause->uuid,
            'payment_method' => 'bkash',
            'frequency' => 'one_time',
            'checkout_key' => app(SSLCommerzService::class)->issueCheckoutKey(),
        ];
    }

    private function flattenLanguageLines(array $lines, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($lines as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flattened += $this->flattenLanguageLines($value, $path);
                continue;
            }

            if (is_string($value)) {
                $flattened[$path] = $value;
            }
        }

        return $flattened;
    }
}
