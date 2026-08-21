<?php

namespace Tests\Unit;

use App\Services\SafeMediaReplacementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SafeMediaReplacementServiceTest extends TestCase
{
    public function test_a_false_storage_write_is_a_failure_and_the_attempted_path_is_cleaned(): void
    {
        $disk = Mockery::mock();
        Storage::shouldReceive('disk')->twice()->with('local')->andReturn($disk);
        $disk->shouldReceive('put')
            ->once()
            ->withArgs(fn (string $path, string $bytes): bool =>
                preg_match('#\Auploads/users/7/350X350/[a-f0-9]{48}\.png\z#', $path) === 1
                    && $bytes === $this->onePixelPng())
            ->andReturnFalse();
        $disk->shouldReceive('delete')
            ->once()
            ->withArgs(fn (array $paths): bool => count($paths) === 1
                && preg_match('#\Auploads/users/7/350X350/[a-f0-9]{48}\.png\z#', $paths[0]) === 1)
            ->andReturnTrue();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not be written');

        app(SafeMediaReplacementService::class)->stageUserAvatar(
            7,
            $this->onePixelPng(),
            'image/png',
        );
    }

    public function test_discard_removes_all_paths_recorded_by_the_staged_asset(): void
    {
        Storage::fake('local');
        $service = app(SafeMediaReplacementService::class);
        $asset = $service->stageUserAvatar(9, $this->onePixelPng(), 'image/png');
        Storage::disk('local')->assertExists($asset->paths[0]);

        $service->discardMany([$asset]);

        Storage::disk('local')->assertMissing($asset->paths[0]);
    }

    public function test_a_partial_gallery_write_removes_every_attempted_variant(): void
    {
        $png = $this->onePixelPng();
        $processed = Mockery::mock();
        $processed->shouldReceive('resize')->once()->with(430, 360)->andReturnSelf();
        $processed->shouldReceive('resize')->once()->with(1, 1)->andReturnSelf();
        $processed->shouldReceive('encode')->twice()->with('png', 75)->andReturn($png);
        Image::shouldReceive('make')->twice()->with($png)->andReturn($processed);

        $disk = Mockery::mock();
        Storage::shouldReceive('disk')->twice()->with('public')->andReturn($disk);
        $disk->shouldReceive('put')->twice()->andReturn(true, false);
        $disk->shouldReceive('get')->once()->andReturn($png);
        $disk->shouldReceive('delete')
            ->once()
            ->withArgs(function (array $paths): bool {
                if (count($paths) !== 2) {
                    return false;
                }

                return preg_match('#\Aphotos/1/gallery/31/430X360/[a-f0-9]{48}\.png\z#', $paths[0]) === 1
                    && preg_match('#\Aphotos/1/gallery/31/main/[a-f0-9]{48}\.png\z#', $paths[1]) === 1
                    && basename($paths[0]) === basename($paths[1]);
            })
            ->andReturnTrue();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not be written');

        app(SafeMediaReplacementService::class)->stageGalleryImage(
            UploadedFile::fake()->createWithContent('gallery.png', $png),
            31,
        );
    }

    private function onePixelPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQMcAAAAASUVORK5CYII=',
            true,
        );
    }
}
