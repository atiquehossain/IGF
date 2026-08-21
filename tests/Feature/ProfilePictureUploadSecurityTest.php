<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Division;
use App\Models\Upazila;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePictureUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    public function test_profile_picture_rejects_streams_remote_urls_oversize_and_non_images(): void
    {
        Storage::fake('local');
        $user = $this->activeUser();
        $this->actingAs($user);

        foreach ([
            'https://127.0.0.1/internal-metadata',
            'file:///' . str_replace('\\', '/', base_path('.env')),
            'php://filter/convert.base64-encode/resource=' . base_path('.env'),
            'data:image/png;base64,' . base64_encode('not an image'),
        ] as $hostileValue) {
            $this->postJson(route('api.pictureUpload'), ['file' => $hostileValue])
                ->assertUnprocessable()
                ->assertJson(['status' => false]);
        }

        $oversize = 'data:image/png;base64,' . base64_encode(str_repeat('A', (2 * 1024 * 1024) + 1));
        $this->postJson(route('api.pictureUpload'), ['file' => $oversize])
            ->assertUnprocessable()
            ->assertJson(['status' => false]);

        $this->assertNull($user->fresh()->avatar);
        $this->assertSame([], Storage::disk('local')->allFiles('uploads/users'));
    }

    public function test_valid_image_is_normalized_to_canonical_private_layout_and_retrievable(): void
    {
        Storage::fake('local');
        $user = $this->activeUser();
        $this->actingAs($user);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQMcAAAAASUVORK5CYII=', true);
        $file = UploadedFile::fake()->createWithContent('profile.png', $png . '<?php echo "polyglot";');

        $response = $this->post(route('api.pictureUpload'), ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['status' => true]);

        $stored = (string) $user->fresh()->avatar;
        $this->assertMatchesRegularExpression('#^' . $user->id . '/350X350/[a-f0-9]{48}\.png$#', $stored);
        Storage::disk('local')->assertExists('uploads/users/' . $stored);
        $this->assertStringNotContainsString('<?php', Storage::disk('local')->get('uploads/users/' . $stored));
        $avatarUrl = $response->json('data.avatar');
        $this->assertSame(route('api.avatar', [
            'id' => $user->id,
            'size' => '350X350',
            'img' => basename($stored),
        ]), $avatarUrl);

        $avatarResponse = $this->get($avatarUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $cacheControl = (string) $avatarResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=86400', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
    }

    public function test_avatar_reader_rejects_traversal_unknown_files_and_wrong_owners(): void
    {
        Storage::fake('local');
        $user = $this->activeUser();
        $name = str_repeat('a', 48) . '.jpg';
        $path = $user->id . '/350X350/' . $name;
        $user->update(['avatar' => $path]);
        Storage::disk('local')->put('uploads/users/' . $path, 'not relevant');

        $this->get('/api/v1/profile/avatar/' . $user->id . '/350X350/%2e%2e%2f%2e%2e%2f.env')->assertNotFound();
        $this->get('/api/v1/profile/avatar/' . $user->id . '/350X350/' . str_repeat('b', 48) . '.jpg')->assertNotFound();
        $this->get('/api/v1/profile/avatar/999/350X350/' . $name)->assertNotFound();
        $this->get('/api/v1/profile/avatar/' . $user->id . '/other/' . $name)->assertNotFound();
    }

    public function test_profile_update_validates_email_and_consistent_geography(): void
    {
        $user = $this->activeUser();
        $this->actingAs($user);
        $division = Division::create(['name' => 'Dhaka', 'status' => 1]);
        $otherDivision = Division::create(['name' => 'Other', 'status' => 1]);
        $district = District::create(['name' => 'Dhaka', 'division_id' => $division->id, 'status' => 1]);
        $upazila = Upazila::create(['name' => 'Mirpur', 'district_id' => $district->id, 'status' => 1]);

        $base = [
            'name' => 'Profile Owner',
            'email' => 'updated@example.test',
            'dob' => '1995-05-05',
            'study_type' => 'University',
            'institute_name' => 'Community Institute',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'upazila_id' => $upazila->id,
        ];
        $this->postJson('/api/v1/user/profile', array_merge($base, [
            'email' => 'not-an-email',
            'division_id' => $otherDivision->id,
        ]))->assertUnprocessable();

        $this->postJson('/api/v1/user/profile', $base)->assertOk()->assertJson(['status' => true]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'updated@example.test', 'division_id' => $division->id]);
    }

    public function test_upload_route_is_throttled(): void
    {
        $this->assertContains('throttle:6,1', app('router')->getRoutes()->getByName('api.pictureUpload')->gatherMiddleware());
    }

    private function activeUser(): User
    {
        return User::create([
            'name' => 'Profile Owner',
            'phone_no' => '01900000000',
            'email' => 'profile-owner@example.test',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Strong-Profile-Password!'),
        ]);
    }
}
