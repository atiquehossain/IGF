<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Album;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\LatestNews;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\Role;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\SafeMediaReplacementService;
use App\Services\StagedMediaAsset;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SafeMediaReplacementIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_flat_image_cleanup_preserves_every_other_active_or_recoverable_reference(): void
    {
        Storage::fake('public');

        $bannerName = 'shared-banner.jpg';
        $this->assertSharedFlatImageLifecycle(
            'banner',
            $bannerName,
            Banner::create(['name' => 'Replaced banner', 'image' => $bannerName, 'path' => $bannerName]),
            Banner::create(['name' => 'Surviving banner', 'path' => $bannerName]),
            ['image' => 'new-banner.jpg', 'path' => 'new-banner.jpg'],
            ['path' => null],
        );

        $categoryName = 'shared-category.jpg';
        $replacedCategory = Category::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Replaced category', 'slug' => 'replaced-category',
            'image' => $categoryName, 'path' => $categoryName,
        ]);
        $recoverableCategory = Category::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Recoverable category', 'slug' => 'recoverable-category',
            'image' => $categoryName,
        ]);
        $recoverableCategory->delete();
        $this->assertSharedFlatImageLifecycle(
            'category',
            $categoryName,
            $replacedCategory,
            $recoverableCategory,
            ['image' => 'new-category.jpg', 'path' => 'new-category.jpg'],
            ['image' => null],
        );

        $pageName = 'shared-page.jpg';
        $this->assertSharedFlatImageLifecycle(
            'page',
            $pageName,
            Page::create(['uuid' => (string) Str::uuid(), 'name' => 'Replaced page', 'sub_title' => '', 'slug' => 'replaced-page', 'thumbnail' => $pageName]),
            Page::create(['uuid' => (string) Str::uuid(), 'name' => 'Surviving page', 'sub_title' => '', 'slug' => 'surviving-page', 'thumbnail' => $pageName]),
            ['thumbnail' => 'new-page.jpg'],
            ['thumbnail' => null],
        );

        $memberName = 'shared-member.jpg';
        $this->assertSharedFlatImageLifecycle(
            'our_members',
            $memberName,
            LatestNews::create(['name' => 'Replaced member', 'type' => 'our-members', 'image' => $memberName, 'path' => $memberName]),
            LatestNews::create(['name' => 'Surviving member', 'type' => 'our-members', 'path' => $memberName]),
            ['image' => 'new-member.jpg', 'path' => 'new-member.jpg'],
            ['path' => null],
        );

        $noticeName = 'shared-notice.jpg';
        $this->assertSharedFlatImageLifecycle(
            'notice_board',
            $noticeName,
            NoticeBoard::create(['title' => 'Replaced notice', 'image_path' => $noticeName]),
            NoticeBoard::create(['title' => 'Surviving notice', 'image_path' => '/storage/photos/1/notice_board/' . $noticeName]),
            ['image_path' => 'new-notice.jpg'],
            ['image_path' => null],
        );

        $testimonialName = 'shared-testimonial.jpg';
        $this->assertSharedFlatImageLifecycle(
            'testimonial',
            $testimonialName,
            Testimonial::create(['uuid' => (string) Str::uuid(), 'name' => 'Replaced testimonial', 'photo' => $testimonialName]),
            Testimonial::create(['uuid' => (string) Str::uuid(), 'name' => 'Surviving testimonial', 'photo' => $testimonialName]),
            ['photo' => 'new-testimonial.jpg'],
            ['photo' => null],
        );
    }

    public function test_gallery_cleanup_preserves_an_explicit_shared_record_path_without_confusing_record_scoped_basenames(): void
    {
        Storage::fake('public');
        $name = 'shared-gallery.jpg';
        $first = Gallery::create(['uuid' => (string) Str::uuid(), 'name' => 'First', 'image' => $name, 'path' => $name]);
        $second = Gallery::create(['uuid' => (string) Str::uuid(), 'name' => 'Second', 'image' => $name]);
        $second->update(['path' => "/storage/photos/1/gallery/{$first->id}/main/{$name}"]);
        $paths = [
            "photos/1/gallery/{$first->id}/430X360/{$name}",
            "photos/1/gallery/{$first->id}/main/{$name}",
        ];
        foreach ($paths as $path) {
            Storage::disk('public')->put($path, 'shared gallery image');
        }

        $first->update(['image' => 'new-gallery.jpg', 'path' => 'new-gallery.jpg']);
        app(SafeMediaReplacementService::class)->deleteLegacyGalleryImages($first->id, $name);
        foreach ($paths as $path) {
            Storage::disk('public')->assertExists($path);
        }

        // A bare basename on another gallery row resolves inside that row's
        // own ID directory; only its explicit pointer shared the first path.
        $second->update(['path' => null]);
        app(SafeMediaReplacementService::class)->deleteLegacyGalleryImages($first->id, $name);
        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_publication_create_stages_a_verified_image_and_discards_it_if_the_atomic_insert_fails(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->owner(), 'admin');
        Storage::fake('public');

        $media = new class extends SafeMediaReplacementService
        {
            /** @var list<string> */
            public array $stagedPaths = [];

            public function stageResizedPublicImage(
                UploadedFile $file,
                string $collection,
                int $width,
                ?int $height = null,
            ): StagedMediaAsset {
                if ($file->getClientOriginalName() === 'decode-failure.png') {
                    throw new RuntimeException('Injected image decode failure.');
                }

                $name = str_repeat($this->stagedPaths === [] ? 'd' : 'e', 48) . '.png';
                $path = 'photos/1/notice_board/' . $name;
                Storage::disk('public')->put($path, 'verified staged image');
                $this->stagedPaths[] = $path;

                return new StagedMediaAsset('public', $name, [$path]);
            }
        };
        $this->app->instance(SafeMediaReplacementService::class, $media);

        $this->post(route('notice.board.store'), [
            'title' => 'Verified publication image',
            'published_at' => now()->format('Y-m-d'),
            'language' => 'en',
            'image_path' => UploadedFile::fake()->createWithContent('publication.png', $this->onePixelPng()),
        ])->assertRedirect(route('notice.board.index'))
            ->assertSessionHas('alert-type', 'success');

        $publication = NoticeBoard::query()->where('title', 'Verified publication image')->firstOrFail();
        $this->assertSame(str_repeat('d', 48) . '.png', $publication->image_path);
        $storedPath = 'photos/1/notice_board/' . $publication->image_path;
        Storage::disk('public')->assertExists($storedPath);

        NoticeBoard::creating(function (NoticeBoard $record): void {
            if ($record->title === 'Publication insert rollback') {
                throw new RuntimeException('Injected publication insert failure.');
            }
        });

        $this->from(route('notice.board.index'))->post(route('notice.board.store'), [
            'title' => 'Publication insert rollback',
            'published_at' => now()->format('Y-m-d'),
            'language' => 'en',
            'image_path' => UploadedFile::fake()->createWithContent('rollback.png', $this->onePixelPng()),
        ])->assertRedirect(route('notice.board.index'))
            ->assertSessionHas('alert-type', 'error');

        $this->assertDatabaseMissing('notice_boards', ['title' => 'Publication insert rollback']);
        $this->assertSame([$storedPath], Storage::disk('public')->allFiles('photos/1/notice_board'));

        $this->from(route('notice.board.index'))->post(route('notice.board.store'), [
            'title' => 'Publication decode rollback',
            'published_at' => now()->format('Y-m-d'),
            'language' => 'en',
            'image_path' => UploadedFile::fake()->createWithContent('decode-failure.png', $this->onePixelPng()),
        ])->assertRedirect(route('notice.board.index'))
            ->assertSessionHas('alert-type', 'error');

        $this->assertDatabaseMissing('notice_boards', ['title' => 'Publication decode rollback']);
        $this->assertSame([$storedPath], Storage::disk('public')->allFiles('photos/1/notice_board'));
        $this->assertCount(2, $media->stagedPaths);
    }

    public function test_banner_and_gallery_updates_ignore_forged_translation_row_ids(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->owner(), 'admin');
        $firstBanner = Banner::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'First banner',
            'type' => 'banner-home',
            'language' => 'en',
            'status' => 1,
        ]);
        $otherBanner = Banner::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other banner',
            'type' => 'banner-home',
            'language' => 'en',
            'status' => 1,
        ]);

        $this->from(route('banner.index'))->put(route('banner.update'), [
            'uuid' => $firstBanner->uuid,
            'language' => ['en'],
            'id' => ['en' => $otherBanner->id],
            'name' => ['en' => 'Updated first banner'],
            'headline' => ['en' => 'Updated first banner'],
            'type' => ['en' => 'banner-home'],
        ])->assertRedirect(route('banner.index'))
            ->assertSessionHas('alert-type', 'success');

        $this->assertSame('Updated first banner', $firstBanner->fresh()->name);
        $this->assertSame('Other banner', $otherBanner->fresh()->name);
        $this->assertSame($otherBanner->uuid, $otherBanner->fresh()->uuid);

        $album = Album::create(['name' => 'Safe album', 'status' => 1]);
        $firstGallery = Gallery::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'First gallery image',
            'type' => 'gallery',
            'album_id' => $album->id,
            'language' => 'en',
            'status' => 1,
        ]);
        $otherGallery = Gallery::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other gallery image',
            'type' => 'gallery',
            'album_id' => $album->id,
            'language' => 'en',
            'status' => 1,
        ]);

        $this->from(route('gallery.index'))->put(route('gallery.update'), [
            'uuid' => $firstGallery->uuid,
            'language' => ['en'],
            'id' => ['en' => (string) $otherGallery->id],
            'name' => ['en' => 'Updated first gallery image'],
            'album_id' => ['en' => $album->id],
        ])->assertRedirect(route('gallery.index'))
            ->assertSessionHas('alert-type', 'success');

        $this->assertSame('Updated first gallery image', $firstGallery->fresh()->name);
        $this->assertSame('Other gallery image', $otherGallery->fresh()->name);
        $this->assertSame($otherGallery->uuid, $otherGallery->fresh()->uuid);
    }

    public function test_successful_member_replacement_keeps_the_old_file_until_the_database_update_finishes(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->owner(), 'admin');
        Storage::fake('public');

        $oldName = 'committed-old-member.png';
        $stagedName = str_repeat('c', 48) . '.png';
        $stagedPath = 'photos/1/our_members/' . $stagedName;
        $oldPath = 'photos/1/our_members/' . $oldName;
        Storage::disk('public')->put($oldPath, 'old-image');
        $member = LatestNews::create([
            'name' => 'Commit ordering member',
            'description' => 'Original designation',
            'image' => $oldName,
            'path' => $oldName,
            'type' => 'our-members',
            'language' => 'en',
            'status' => 1,
        ]);

        $media = new class($stagedPath, $stagedName) extends SafeMediaReplacementService
        {
            public function __construct(private string $path, private string $name)
            {
            }

            public function stageResizedPublicImage(
                UploadedFile $file,
                string $collection,
                int $width,
                ?int $height = null,
            ): StagedMediaAsset {
                Storage::disk('public')->put($this->path, 'verified-new-image');

                return new StagedMediaAsset('public', $this->name, [$this->path]);
            }
        };
        $this->app->instance(SafeMediaReplacementService::class, $media);
        $oldExistedDuringUpdate = false;
        LatestNews::updated(function (LatestNews $record) use ($member, $oldPath, &$oldExistedDuringUpdate): void {
            if ($record->is($member) && $record->isDirty('image')) {
                $oldExistedDuringUpdate = Storage::disk('public')->exists($oldPath);
            }
        });

        $this->from(route('latest.news.index'))->put(route('latest.news.update'), [
            'id' => $member->id,
            'name' => 'Commit ordering member',
            'designation' => 'Updated designation',
            'image' => UploadedFile::fake()->createWithContent('replacement.png', $this->onePixelPng()),
        ])->assertRedirect(route('latest.news.index'))
            ->assertSessionHas('alert-type', 'success');

        $this->assertTrue($oldExistedDuringUpdate);
        $this->assertSame($stagedName, $member->fresh()->image);
        Storage::disk('public')->assertExists($stagedPath);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_database_failure_rolls_back_member_fields_and_discards_the_staged_replacement(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->owner(), 'admin');
        Storage::fake('public');

        $oldName = 'old-member.png';
        $stagedName = str_repeat('a', 48) . '.png';
        $stagedPath = 'photos/1/our_members/' . $stagedName;
        Storage::disk('public')->put('photos/1/our_members/' . $oldName, 'old-image');
        $member = LatestNews::create([
            'name' => 'Original member',
            'description' => 'Original designation',
            'image' => $oldName,
            'path' => $oldName,
            'type' => 'our-members',
            'language' => 'en',
            'status' => 1,
        ]);

        $media = new class($stagedPath, $stagedName) extends SafeMediaReplacementService
        {
            public function __construct(private string $path, private string $name)
            {
            }

            public function stageResizedPublicImage(
                UploadedFile $file,
                string $collection,
                int $width,
                ?int $height = null,
            ): StagedMediaAsset {
                Storage::disk('public')->put($this->path, 'verified-new-image');

                return new StagedMediaAsset('public', $this->name, [$this->path]);
            }
        };
        $this->app->instance(SafeMediaReplacementService::class, $media);
        LatestNews::updating(function (LatestNews $record) use ($member): void {
            if ($record->is($member)) {
                throw new RuntimeException('Injected database failure after staging.');
            }
        });

        $png = $this->onePixelPng();
        $this->from(route('latest.news.index'))->put(route('latest.news.update'), [
            'id' => $member->id,
            'name' => 'Changed member',
            'designation' => 'Changed designation',
            'image' => UploadedFile::fake()->createWithContent('replacement.png', $png),
        ])->assertRedirect(route('latest.news.index'))
            ->assertSessionHas('alert-type', 'error');

        $member->refresh();
        $this->assertSame('Original member', $member->name);
        $this->assertSame('Original designation', $member->description);
        $this->assertSame($oldName, $member->image);
        Storage::disk('public')->assertExists('photos/1/our_members/' . $oldName);
        Storage::disk('public')->assertMissing($stagedPath);
    }

    public function test_database_failure_discards_a_staged_avatar_and_preserves_the_previous_avatar(): void
    {
        $this->withoutMiddleware();
        Storage::fake('local');
        $user = User::create([
            'name' => 'Profile owner',
            'phone_no' => '01900000000',
            'email' => 'safe-replacement@example.test',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Strong-Profile-Password!'),
        ]);
        $oldAvatar = $user->id . '/350X350/' . str_repeat('b', 48) . '.png';
        $user->update(['avatar' => $oldAvatar]);
        Storage::disk('local')->put('uploads/users/' . $oldAvatar, $this->onePixelPng());
        User::updating(function (User $record) use ($user): void {
            if ($record->is($user) && $record->isDirty('avatar')) {
                throw new RuntimeException('Injected avatar database failure after staging.');
            }
        });

        $this->actingAs($user)->post(route('api.pictureUpload'), [
            'file' => UploadedFile::fake()->createWithContent('replacement.png', $this->onePixelPng()),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJson(['status' => false]);

        $this->assertSame($oldAvatar, $user->fresh()->avatar);
        Storage::disk('local')->assertExists('uploads/users/' . $oldAvatar);
        $this->assertSame(
            ['uploads/users/' . $oldAvatar],
            Storage::disk('local')->allFiles('uploads/users'),
        );
    }

    private function owner(): Admin
    {
        $role = Role::query()->where('is_owner', true)->firstOrFail();
        $role->forceFill(['status' => 1, 'security_rank' => 0])->save();

        return Admin::query()->create([
            'name' => 'Media replacement owner',
            'username' => 'media-replacement-owner',
            'email' => 'media-replacement-owner@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => Hash::make('Strong-Test-Password!23'),
            'must_change_password' => false,
        ]);
    }

    private function assertSharedFlatImageLifecycle(
        string $collection,
        string $name,
        object $replaced,
        object $survivor,
        array $replacement,
        array $release,
    ): void {
        $path = "photos/1/{$collection}/{$name}";
        Storage::disk('public')->put($path, 'shared legacy image');
        $replaced->update($replacement);

        app(SafeMediaReplacementService::class)->deleteLegacyFlatImages($collection, [$name, $name]);
        Storage::disk('public')->assertExists($path);

        $survivor->update($release);
        app(SafeMediaReplacementService::class)->deleteLegacyFlatImages($collection, $name);
        Storage::disk('public')->assertMissing($path);
    }

    private function onePixelPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQMcAAAAASUVORK5CYII=',
            true,
        );
    }
}
