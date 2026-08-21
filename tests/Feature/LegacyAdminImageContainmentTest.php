<?php

namespace Tests\Feature;

use App\Helper\IgfFile;
use App\Models\Admin;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegacyAdminImageContainmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->owner(), 'admin');
    }

    public function test_every_legacy_admin_image_route_rejects_windows_and_posix_traversal(): void
    {
        $windowsTraversal = '..%5C..%5C..%5C..%5C..%5C..%5Ccomposer.json';
        foreach ([
            '/admin/banner/image/',
            '/admin/category/image/',
            '/admin/publication/image/',
            '/admin/member/image/',
            '/admin/page/thumbnail/',
            '/admin/testimonial/photo/',
            '/admin/gallery/image/',
        ] as $prefix) {
            $this->get($prefix . $windowsTraversal)
                ->assertNotFound()
                ->assertDontSee('laravel/framework', false);
        }

        $this->get('/admin/banner/image/..%2F..%2F..%2F..%2F..%2Fcomposer.json')
            ->assertNotFound()
            ->assertDontSee('laravel/framework', false);
    }

    public function test_contained_raster_is_served_but_non_image_content_falls_back_safely(): void
    {
        $directory = storage_path('app/public/photos/1/banner');
        File::ensureDirectoryExists($directory);
        $name = Str::lower(Str::random(20)) . '.png';
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQMcAAAAASUVORK5CYII=', true);
        File::put($path, $png);

        try {
            $this->get(route('banner.image', $name))
                ->assertOk()
                ->assertHeader('Content-Type', 'image/png')
                ->assertHeader('X-Content-Type-Options', 'nosniff');

            File::put($path, '<?php echo "not an image";');
            $response = $this->get(route('banner.image', $name))->assertOk();
            $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));
            $response->assertDontSee('<?php', false);
        } finally {
            File::delete($path);
        }
    }

    public function test_legacy_removal_helpers_cannot_escape_or_delete_the_storage_root(): void
    {
        File::ensureDirectoryExists(storage_path('app/public/photos/1'));
        $sentinel = storage_path('app/igf-file-sentinel-' . Str::lower(Str::random(12)) . '.txt');
        File::put($sentinel, 'preserve');

        try {
            IgfFile::remove('/banner/../../../../' . basename($sentinel));
            IgfFile::remove($sentinel, true);
            IgfFile::removeFolder('/', false);

            $this->assertFileExists($sentinel);
            $this->assertDirectoryExists(storage_path('app/public/photos/1'));
        } finally {
            File::delete($sentinel);
        }
    }

    private function owner(): Admin
    {
        $role = Role::query()->where('is_owner', true)->firstOrFail();
        $role->forceFill(['status' => 1, 'security_rank' => 0])->save();

        return Admin::query()->create([
            'name' => 'Image route owner',
            'username' => 'image-route-owner',
            'email' => 'image-route-owner@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => Hash::make('Strong-Test-Password!23'),
            'must_change_password' => false,
        ]);
    }
}
