<?php

namespace Tests\Unit;

use App\Services\AdminMediaUrlResolver;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMediaUrlResolverTest extends TestCase
{
    public function test_it_recovers_a_modern_media_path_from_a_malformed_legacy_prefix(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/ignite-live/page.jpg', 'image');

        $url = app(AdminMediaUrlResolver::class)->image(
            '/storage/photos/1/page//storage/media/ignite-live/page.jpg',
            'page',
        );

        $this->assertSame('/storage/media/ignite-live/page.jpg', $url);
    }

    public function test_it_resolves_legacy_and_imported_gallery_basenames_without_broken_placeholders(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('photos/1/gallery/42/main/legacy.webp', 'image');
        Storage::disk('public')->put('media/ignite-live/imported.jpg', 'image');
        $resolver = app(AdminMediaUrlResolver::class);

        $this->assertSame(
            '/storage/photos/1/gallery/42/main/legacy.webp',
            $resolver->image('legacy.webp', 'gallery', 42, 'main'),
        );
        $this->assertSame(
            '/storage/media/ignite-live/imported.jpg',
            $resolver->image('imported.jpg', 'gallery', 42, '430X360'),
        );
    }

    public function test_it_falls_back_for_missing_unsafe_or_non_image_values(): void
    {
        Storage::fake('public');
        $resolver = app(AdminMediaUrlResolver::class);
        $fallback = asset('image/no-image.png');

        $this->assertSame($fallback, $resolver->image('/storage/media/missing.jpg'));
        $this->assertSame($fallback, $resolver->image('../secret.jpg', 'page'));
        $this->assertSame($fallback, $resolver->image('javascript:alert(1)'));
        $this->assertSame($fallback, $resolver->image('/storage/media/file.pdf'));
    }

    public function test_it_only_accepts_credential_free_http_remote_urls(): void
    {
        Storage::fake('public');
        $resolver = app(AdminMediaUrlResolver::class);

        $this->assertSame('https://cdn.example.test/photo.jpg', $resolver->image('https://cdn.example.test/photo.jpg'));
        $this->assertSame(
            asset('image/no-image.png'),
            $resolver->image('https://user@cdn.example.test/photo.jpg'),
        );
    }
}
