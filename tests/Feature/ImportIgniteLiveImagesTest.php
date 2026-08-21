<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MediaAsset;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImportIgniteLiveImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_imports_first_party_images_deduplicates_content_and_writes_manifest(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Http::fake(function (Request $request) use ($png) {
            return Http::response($png, 200, ['Content-Type' => 'image/png']);
        });
        $inventory = $this->inventory([
            ['url' => 'https://ignite.org.bd/image/first.png', 'pages' => ['https://ignite.org.bd/']],
            ['url' => 'https://ignite.org.bd/image/duplicate.png', 'pages' => ['https://ignite.org.bd/about-us']],
        ]);

        $this->artisan('igf:import-live-images', ['inventory' => $inventory])->assertSuccessful();

        $this->assertDatabaseCount('media_assets', 1);
        $asset = MediaAsset::firstOrFail();
        Storage::disk('public')->assertExists($asset->path);
        Storage::disk('local')->assertExists('imports/ignite-live/source-inventory.json');
        Storage::disk('local')->assertExists('imports/ignite-live/manifest.json');
        $manifest = json_decode(Storage::disk('local')->get('imports/ignite-live/manifest.json'), true);
        $this->assertSame(2, $manifest['source_image_count']);
        $this->assertSame(1, $manifest['stored_image_count']);
        $this->assertCount(2, $manifest['images']);
        $this->assertSame($manifest['images'][0]['local_path'], $manifest['images'][1]['local_path']);
    }

    public function test_command_rejects_external_hosts_and_non_images(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Http::fake([
            'https://ignite.org.bd/not-image.jpg' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'text/html']),
        ]);
        $inventory = $this->inventory([
            ['url' => 'https://example.com/external.png', 'pages' => []],
            ['url' => 'https://ignite.org.bd/not-image.jpg', 'pages' => []],
        ]);

        $this->artisan('igf:import-live-images', ['inventory' => $inventory])->assertFailed();

        $this->assertDatabaseCount('media_assets', 0);
        Storage::disk('public')->assertDirectoryEmpty('media/ignite-live');
        $manifest = json_decode(Storage::disk('local')->get('imports/ignite-live/manifest.json'), true);
        $this->assertSame(2, $manifest['failed_image_count']);
    }

    public function test_media_library_uses_compact_admin_pagination_instead_of_unstyled_svg_arrows(): void
    {
        $menu = AuthMenu::query()->where('link', 'media.index')->firstOrFail();
        $role = Role::create([
            'name' => 'Media editor',
            'permission' => (string) $menu->id,
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $admin = Admin::create([
            'name' => 'Media QA',
            'username' => 'media-qa',
            'email' => 'media-qa@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
        foreach (range(1, 31) as $index) {
            MediaAsset::create([
                'uuid' => (string) Str::uuid(),
                'disk' => 'public',
                'path' => "media/ignite-live/image-{$index}.jpg",
                'original_name' => "image-{$index}.jpg",
                'mime_type' => 'image/jpeg',
                'extension' => 'jpg',
                'bytes' => 1024,
                'caption' => 'Imported from https://ignite.org.bd/',
            ]);
        }

        $this->actingAs($admin, 'admin')
            ->get(route('media.index', ['type' => 'image', 'search' => 'ignite.org.bd']))
            ->assertOk()
            ->assertSee('class="igf-library__pagination"', false)
            ->assertSee('class="pagination"', false)
            ->assertSee('page=2', false)
            ->assertDontSee('class="w-5 h-5"', false)
            ->assertDontSee('<svg', false);
    }

    private function inventory(array $assets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ignite-images-');
        file_put_contents($path, json_encode([
            'source' => 'https://ignite.org.bd/?=',
            'pages' => [['url' => 'https://ignite.org/']],
            'assets' => $assets,
        ], JSON_THROW_ON_ERROR));

        return $path;
    }
}
