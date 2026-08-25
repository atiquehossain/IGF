<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MemberCredentialVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberAuthenticationEnumerationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Known-Member-Password!';

    public function test_web_password_login_has_one_failure_shape_for_absent_pending_inactive_and_wrong_password_accounts(): void
    {
        [$pending, $inactive, $active] = $this->memberFixtures();
        $attempts = [
            ['phone_no' => '01099999999', 'password' => 'Wrong-Member-Password!'],
            ['phone_no' => $pending->phone_no, 'password' => self::PASSWORD],
            ['phone_no' => $inactive->phone_no, 'password' => self::PASSWORD],
            ['phone_no' => $active->phone_no, 'password' => 'Wrong-Member-Password!'],
        ];

        $observed = [];
        foreach ($attempts as $attempt) {
            $response = $this->from(route('showLogin'))->post(route('login'), $attempt);
            $observed[] = [
                $response->getStatusCode(),
                $response->headers->get('Location'),
                $response->getSession()->get('message'),
            ];
            $this->assertGuest();
        }

        $this->assertCount(1, array_unique(array_map(
            static fn (array $shape): string => serialize($shape),
            $observed
        )));
        $this->assertSame([
            'type' => 'error',
            'text' => MemberCredentialVerifier::FAILURE_MESSAGE,
        ], $observed[0][2]);
    }

    public function test_web_two_factor_login_has_one_failure_shape_for_absent_pending_inactive_and_wrong_password_accounts(): void
    {
        [$pending, $inactive, $active] = $this->memberFixtures();
        $attempts = [
            ['email' => 'missing-2fa@example.test', 'password' => 'Wrong-Member-Password!'],
            ['email' => $pending->email, 'password' => self::PASSWORD],
            ['email' => $inactive->email, 'password' => self::PASSWORD],
            ['email' => $active->email, 'password' => 'Wrong-Member-Password!'],
        ];

        $observed = [];
        foreach ($attempts as $attempt) {
            $response = $this->from(route('login2fa'))->post(route('login2fa.perform'), $attempt);
            $observed[] = [
                $response->getStatusCode(),
                $response->headers->get('Location'),
                $response->getSession()->get('message'),
            ];
            $this->assertGuest();
        }

        $this->assertCount(1, array_unique(array_map(
            static fn (array $shape): string => serialize($shape),
            $observed
        )));
        $this->assertSame(MemberCredentialVerifier::FAILURE_MESSAGE, $observed[0][2]['text']);
    }

    public function test_api_password_and_two_factor_endpoints_do_not_disclose_account_state(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        [$pending, $inactive, $active] = $this->memberFixtures();
        $attempts = [
            ['phone_no' => '01099999999', 'password' => 'Wrong-Member-Password!'],
            ['phone_no' => $pending->phone_no, 'password' => self::PASSWORD],
            ['phone_no' => $inactive->phone_no, 'password' => self::PASSWORD],
            ['phone_no' => $active->phone_no, 'password' => 'Wrong-Member-Password!'],
        ];

        foreach (['/api/v1/auth/login', '/api/v1/auth/login-2fa'] as $endpoint) {
            $observed = [];
            foreach ($attempts as $attempt) {
                $response = $this->postJson($endpoint, $attempt)->assertOk();
                $observed[] = $response->json();
            }

            $this->assertCount(1, array_unique(array_map('serialize', $observed)));
            $this->assertSame([
                'status' => false,
                'message' => MemberCredentialVerifier::FAILURE_MESSAGE,
            ], $observed[0]);
        }
    }

    public function test_missing_identity_still_performs_a_fixed_bcrypt_hash_check(): void
    {
        Hash::shouldReceive('check')
            ->once()
            ->with(
                'Wrong-Member-Password!',
                \Mockery::on(static fn (string $hash): bool => password_get_info($hash)['algoName'] === 'bcrypt')
            )
            ->andReturnFalse();

        $this->assertFalse(
            app(MemberCredentialVerifier::class)->passes(null, 'Wrong-Member-Password!')
        );
    }

    /** @return array{User, User, User} */
    private function memberFixtures(): array
    {
        $pending = $this->createMember('01000000101', 'pending-enumeration@example.test', 1, 0);
        $inactive = $this->createMember('01000000102', 'inactive-enumeration@example.test', 0, 1);
        $active = $this->createMember('01000000103', 'active-enumeration@example.test', 1, 1);

        return [$pending, $inactive, $active];
    }

    private function createMember(string $phone, string $email, int $status, int $approval): User
    {
        return User::create([
            'name' => 'Enumeration Test Member',
            'phone_no' => $phone,
            'email' => $email,
            'provider_type' => 'local',
            'status' => $status,
            'is_approved' => $approval,
            'password' => Hash::make(self::PASSWORD),
        ]);
    }
}
