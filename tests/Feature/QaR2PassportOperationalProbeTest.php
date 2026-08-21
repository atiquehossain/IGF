<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class QaR2PassportOperationalProbeTest extends TestCase
{
    use RefreshDatabase;

    private string $keyDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keyDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ignite-passport-r2-' . Str::uuid();
        mkdir($this->keyDirectory, 0700, true);
        $keyOptions = ['private_key_bits' => 2048];
        $configCandidates = array_filter([
            getenv('OPENSSL_CONF') ?: null,
            PHP_OS_FAMILY === 'Windows' ? 'C:\\xampp\\apache\\conf\\openssl.cnf' : null,
            PHP_OS_FAMILY === 'Windows' ? 'C:\\xampp\\php\\extras\\ssl\\openssl.cnf' : null,
        ]);
        foreach ($configCandidates as $config) {
            if (is_file($config)) {
                $keyOptions['config'] = $config;
                break;
            }
        }
        $key = openssl_pkey_new($keyOptions);
        $this->assertNotFalse($key, 'Unable to generate an ephemeral Passport QA key.');
        $this->assertTrue(
            openssl_pkey_export($key, $privateKey, null, $keyOptions),
            'Unable to export the ephemeral Passport QA key.'
        );
        $details = openssl_pkey_get_details($key);
        $this->assertNotFalse($details, 'Unable to inspect the ephemeral Passport QA key.');
        $publicKey = $details['key'];
        file_put_contents($this->keyDirectory . DIRECTORY_SEPARATOR . 'oauth-private.key', $privateKey);
        file_put_contents($this->keyDirectory . DIRECTORY_SEPARATOR . 'oauth-public.key', $publicKey);
        Passport::loadKeysFrom($this->keyDirectory);
        app(ClientRepository::class)->createPersonalAccessGrantClient('R2 numeric client', 'users');
    }

    protected function tearDown(): void
    {
        foreach (['oauth-private.key', 'oauth-public.key'] as $file) {
            $path = $this->keyDirectory . DIRECTORY_SEPARATOR . $file;
            if (is_file($path)) unlink($path);
        }
        if (is_dir($this->keyDirectory)) rmdir($this->keyDirectory);
        parent::tearDown();
    }

    public function test_numeric_client_schema_issues_a_token_through_api_login(): void
    {
        $user = User::create([
            'name' => 'Passport QA User',
            'phone_no' => '+8801700000000',
            'email' => 'passport-r2@example.test',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Valid-Passport-Password!'),
        ]);
        $client = Passport::client()->newQuery()->firstOrFail();
        $this->assertIsInt($client->id);

        $this->postJson('/api/v1/auth/login', [
            'phone_no' => $user->phone_no,
            'password' => 'Valid-Passport-Password!',
        ])->assertOk()->assertJson(['status' => true])->assertJsonStructure(['token']);
    }

    public function test_successful_api_otp_does_not_double_encrypt_an_existing_secret(): void
    {
        $secret = app('pragmarx.google2fa')->generateSecretKey();
        $user = User::create([
            'name' => 'Encrypted Two Factor API User',
            'phone_no' => '+8801700000001',
            'email' => 'encrypted-two-factor-api@example.test',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Valid-Passport-Password!'),
            'google2fa_secret' => $secret,
        ]);
        $encryptedFingerprint = hash('sha256', (string) DB::table('users')
            ->where('id', $user->id)
            ->value('google2fa_secret'));

        $challenge = $this->postJson('/api/v1/auth/login-2fa', [
            'phone_no' => $user->phone_no,
            'password' => 'Valid-Passport-Password!',
        ])->assertOk()
            ->assertJson(['status' => true, 'enrollment_required' => false]);

        $this->postJson('/api/v1/auth/verify2fa', [
            'access_token' => $challenge->json('access_token'),
            'code' => app('pragmarx.google2fa')->getCurrentOtp($secret),
        ])->assertOk()
            ->assertJson(['status' => true])
            ->assertJsonStructure(['token']);

        $this->assertSame(
            $encryptedFingerprint,
            hash('sha256', (string) DB::table('users')
                ->where('id', $user->id)
                ->value('google2fa_secret'))
        );
    }

    public function test_api_social_signup_binds_provider_but_waits_for_approval_before_issuing_a_token(): void
    {
        $providerUser = new class {
            public function getId(): string { return 'google-provider-123'; }
            public function getEmail(): string { return 'social-api@example.test'; }
            public function getName(): string { return 'Social API Member'; }
            public function getAvatar(): ?string { return null; }
        };
        $driver = new class ($providerUser) {
            public function __construct(private object $providerUser) {}
            public function stateless(): self { return $this; }
            public function userFromToken(string $token): object { return $this->providerUser; }
        };
        Socialite::shouldReceive('with')->once()->with('google')->andReturn($driver);

        $this->postJson('/api/v1/auth/social', [
            'provider' => 'google',
            'access_token' => 'opaque-provider-token',
        ])->assertStatus(202)->assertJson([
            'status' => false,
            'approval_status' => 'pending',
        ])->assertJsonMissingPath('token');

        $user = User::query()->where('social_id', 'google-provider-123')->firstOrFail();
        $this->assertSame('google', $user->provider_type);
        $this->assertTrue((bool) $user->status);
        $this->assertSame(0, (int) $user->is_approved);
        $this->assertNotNull($user->password);
    }
}
