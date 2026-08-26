<?php

namespace Tests\Unit;

use App\Services\ContentSanitizer;
use App\Services\PublicCardImageService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicCardImageServiceTest extends TestCase
{
    private PublicCardImageService $images;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://ignite.test']);
        $this->images = new PublicCardImageService(new ContentSanitizer());
    }

    public function test_it_skips_external_images_and_returns_the_first_managed_public_image(): void
    {
        $image = $this->images->firstManagedImage(
            '<img src="https://tracker.test/pixel.jpg" alt="Tracker">' .
            '<figure><img src="/storage/media/2026/08/poster.jpg" alt="  AI   workshop poster  "></figure>'
        );

        $this->assertSame([
            'url' => '/storage/media/2026/08/poster.jpg',
            'alt' => 'AI workshop poster',
        ], $image);
    }

    public function test_it_normalizes_same_origin_https_images_and_preserves_decorative_alt_text(): void
    {
        $image = $this->images->firstManagedImage(
            '<img src="https://ignite.test/storage/photos/events/session.PNG" alt="">'
        );

        $this->assertSame([
            'url' => '/storage/photos/events/session.PNG',
            'alt' => '',
        ], $image);
    }

    #[DataProvider('rejectedImageProvider')]
    public function test_it_rejects_unmanaged_or_unsafe_image_urls(string $url): void
    {
        $this->assertNull($this->images->firstManagedImage('<img src="' . e($url) . '">'));
    }

    /** @return array<string,array{string}> */
    public static function rejectedImageProvider(): array
    {
        return [
            'external HTTPS' => ['https://example.test/storage/media/poster.jpg'],
            'plain HTTP' => ['http://ignite.test/storage/media/poster.jpg'],
            'protocol relative external' => ['//example.test/storage/media/poster.jpg'],
            'data URL' => ['data:image/png;base64,AAAA'],
            'javascript URL' => ['javascript:alert(1)'],
            'ordinary relative path' => ['storage/media/poster.jpg'],
            'unexpected public folder' => ['/storage/private/poster.jpg'],
            'query token' => ['/storage/media/poster.jpg?token=secret'],
            'SVG' => ['/storage/media/poster.svg'],
            'encoded traversal' => ['/storage/media/%2e%2e/poster.jpg'],
        ];
    }

    public function test_it_returns_null_when_the_description_has_no_eligible_image(): void
    {
        $this->assertNull($this->images->firstManagedImage('<p>No poster was added.</p>'));
    }
}
