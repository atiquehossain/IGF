<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\District;
use App\Models\Division;
use App\Models\MediaAsset;
use App\Models\NoticeBoard;
use App\Models\Role;
use App\Services\SafeMediaReplacementService;
use App\Services\StagedMediaAsset;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MeetTheHeroesAdminContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_directory_copy_and_motion_controls_are_nontechnical_and_reference_aligned(): void
    {
        $fields = config('site-settings.groups.meet_the_heroes.fields');

        $this->assertSame('Heroes', $fields['national_eyebrow']['default']);
        $this->assertSame('Meet the Heroes', $fields['national_title']['default']);
        $this->assertSame('', $fields['national_introduction']['default']);
        $this->assertSame('Team', $fields['division_eyebrow']['default']);
        $this->assertSame('Meet the Heroes from {division} Division', $fields['division_title_template']['default']);
        $this->assertSame('About {division}', $fields['division_about_heading']['default']);
        $this->assertSame('', $fields['division_introduction']['default']);
        $this->assertSame('Team', $fields['district_eyebrow']['default']);
        $this->assertSame('Meet the Heroes from {district} District', $fields['district_title_template']['default']);
        $this->assertSame('', $fields['district_introduction']['default']);
        $this->assertSame('About {district}', $fields['district_about_heading']['default']);
        $this->assertStringContainsString('{district}', $fields['district_about_body']['default']);
        $this->assertSame('Activities at {place}', $fields['activities_heading']['default']);
        $this->assertSame('Explore heroes by division', $fields['national_map_heading']['default']);
        $this->assertSame('Explore heroes by district', $fields['division_map_heading']['default']);
        $this->assertStringContainsString('division', $fields['national_map_help']['default']);
        $this->assertStringContainsString('district', $fields['division_map_help']['default']);
        $this->assertTrue($fields['autoplay']['default']);
        $this->assertStringContainsString('5 seconds', $fields['autoplay']['help']);
        $this->assertStringContainsString('reduced motion', $fields['autoplay']['help']);
        $this->assertTrue($fields['animation_enabled']['default']);
        $this->assertStringContainsString('reduced motion', $fields['animation_enabled']['help']);
    }

    public function test_admin_can_manage_division_and_district_copy_and_a_safe_group_image(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->owner(), 'admin');
        Storage::fake('public');

        $division = Division::query()->where('name', 'Dhaka')->firstOrFail();
        $district = District::query()
            ->where('division_id', $division->id)
            ->where('name', 'Dhaka')
            ->firstOrFail();
        $divisionSlug = $division->slug;
        $districtSlug = $district->slug;

        $this->get(route('division.index'))
            ->assertOk()
            ->assertSee('Division introduction (English)')
            ->assertSee('Division introduction (Bangla)')
            ->assertSee('Kept stable automatically');

        $this->put(route('division.update'), [
            'id' => $division->id,
            'name' => 'Dhaka Region',
            'description' => '<b>English regional introduction.</b>',
            'description_bn' => '<script>bad()</script>বাংলা আঞ্চলিক পরিচিতি।',
        ])->assertSessionHasNoErrors();

        $division->refresh();
        $this->assertSame($divisionSlug, $division->slug, 'Editing the label must not break its public URL.');
        $this->assertSame('English regional introduction.', $division->description);
        $this->assertSame('bad()বাংলা আঞ্চলিক পরিচিতি।', $division->description_bn);

        Storage::disk('public')->put('media/dhaka-team.jpg', 'managed image');
        $media = MediaAsset::create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => 'media/dhaka-team.jpg',
            'original_name' => 'Dhaka team.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'bytes' => 13,
            'alt_text' => 'Dhaka team gathering',
        ]);

        $this->get(route('district.index'))
            ->assertOk()
            ->assertSee('District introduction (English)')
            ->assertSee('Choose a group image from Media Library')
            ->assertSee('Image description (Bangla)')
            ->assertSee('Remove the current group image');

        $this->put(route('district.update'), [
            'id' => $district->id,
            'division_id' => $division->id,
            'name' => 'Dhaka District Area',
            'description' => '<p>English district introduction.</p>',
            'description_bn' => 'বাংলা জেলার পরিচিতি।',
            'hero_image_media_uuid' => $media->uuid,
            'hero_image_alt' => 'Volunteers meeting in Dhaka District',
            'hero_image_alt_bn' => 'ঢাকা জেলার স্বেচ্ছাসেবকেরা একসঙ্গে',
        ])->assertSessionHasNoErrors();

        $district->refresh();
        $this->assertSame($districtSlug, $district->slug, 'Editing the label must not break its public URL.');
        $this->assertSame('English district introduction.', $district->description);
        $this->assertSame('বাংলা জেলার পরিচিতি।', $district->description_bn);
        $this->assertSame($media->url, $district->hero_image);
        $this->assertSame('Volunteers meeting in Dhaka District', $district->hero_image_alt);
        $this->assertSame('ঢাকা জেলার স্বেচ্ছাসেবকেরা একসঙ্গে', $district->hero_image_alt_bn);

        $this->get(route('district.edit', $district->id))
            ->assertOk()
            ->assertJsonPath('data.slug', $districtSlug)
            ->assertJsonPath('data.hero_image_media_uuid', $media->uuid)
            ->assertJsonPath('data.hero_image_url', $media->url);

        $this->put(route('district.update'), [
            'id' => $district->id,
            'division_id' => $division->id,
            'name' => 'Dhaka District Area',
            'description' => $district->description,
            'description_bn' => $district->description_bn,
            'remove_hero_image' => 1,
        ])->assertSessionHasNoErrors();

        $district->refresh();
        $this->assertNull($district->hero_image);
        $this->assertNull($district->hero_image_alt);
        $this->assertNull($district->hero_image_alt_bn);
        Storage::disk('public')->assertExists('media/dhaka-team.jpg');
    }

    public function test_district_upload_is_optimized_and_ambiguous_or_unlabelled_replacements_are_rejected(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->owner(), 'admin');
        Storage::fake('public');

        $division = Division::query()->where('name', 'Dhaka')->firstOrFail();
        $stagedName = str_repeat('a', 48) . '.png';
        $stagedPath = 'photos/1/districts/' . $stagedName;
        $media = new class($stagedPath, $stagedName) extends SafeMediaReplacementService
        {
            /** @var array{collection: string, width: int, height: int|null}|null */
            public ?array $request = null;

            public function __construct(private string $path, private string $name)
            {
            }

            public function stageResizedPublicImage(
                UploadedFile $file,
                string $collection,
                int $width,
                ?int $height = null,
            ): StagedMediaAsset {
                $this->request = compact('collection', 'width', 'height');
                Storage::disk('public')->put($this->path, 'verified staged district image');

                return new StagedMediaAsset('public', $this->name, [$this->path]);
            }
        };
        $this->app->instance(SafeMediaReplacementService::class, $media);
        $upload = UploadedFile::fake()->createWithContent('district-team.png', $this->onePixelPng());

        $this->post(route('district.store'), [
            'division_id' => $division->id,
            'name' => 'Admin Demo District',
            'description' => 'A clearly labeled test district.',
            'hero_image_upload' => $upload,
            'hero_image_alt' => 'A group of program volunteers',
        ])->assertSessionHasNoErrors();

        $district = District::query()->where('name', 'Admin Demo District')->firstOrFail();
        $this->assertSame($stagedName, $district->hero_image);
        $this->assertSame(['collection' => 'districts', 'width' => 1400, 'height' => null], $media->request);
        Storage::disk('public')->assertExists('photos/1/districts/' . $district->hero_image);

        $media = MediaAsset::create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => 'media/other.jpg',
            'original_name' => 'Other.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'bytes' => 10,
        ]);

        $this->post(route('district.store'), [
            'division_id' => $division->id,
            'name' => 'Rejected Demo District',
            'hero_image_upload' => UploadedFile::fake()->createWithContent('new.png', $this->onePixelPng()),
            'hero_image_media_uuid' => $media->uuid,
            'hero_image_alt' => '',
        ])->assertSessionHasErrors(['hero_image_upload', 'hero_image_alt']);

        $this->assertFalse(District::query()->where('name', 'Rejected Demo District')->exists());
    }

    public function test_event_admin_assigns_only_a_district_inside_the_selected_division_and_can_clear_scope(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->owner(), 'admin');

        $dhaka = Division::query()->where('name', 'Dhaka')->firstOrFail();
        $dhakaDistrict = District::query()->where('name', 'Dhaka')->firstOrFail();
        $chattogramDistrict = District::query()->where('name', 'Chattogram')->firstOrFail();

        $this->get(route('notice.board.create'))
            ->assertOk()
            ->assertSee('Meet the Heroes activity area (optional)')
            ->assertSee('it is not a person’s home address');

        $this->post(route('notice.board.store'), [
            'title' => 'Scoped activity test',
            'published_at' => '2026-09-08',
            'language' => 'en',
            'content_kind' => 'article',
            'division_id' => $dhaka->id,
            'district_id' => $dhakaDistrict->id,
        ])->assertSessionHasNoErrors();

        $notice = NoticeBoard::query()->where('title', 'Scoped activity test')->firstOrFail();
        $this->assertSame($dhaka->id, $notice->division_id);
        $this->assertSame($dhakaDistrict->id, $notice->district_id);

        $this->put(route('notice.board.update'), [
            'id' => $notice->id,
            'title' => $notice->title,
            'published_at' => '2026-09-08',
            'language' => 'en',
            'content_kind' => 'article',
            'division_id' => $dhaka->id,
            'district_id' => $chattogramDistrict->id,
        ])->assertSessionHasErrors('district_id');

        $this->put(route('notice.board.update'), [
            'id' => $notice->id,
            'title' => $notice->title,
            'published_at' => '2026-09-08',
            'language' => 'en',
            'content_kind' => 'article',
            'division_id' => '',
            'district_id' => '',
        ])->assertSessionHasNoErrors();

        $notice->refresh();
        $this->assertNull($notice->division_id);
        $this->assertNull($notice->district_id);
    }

    public function test_geography_cannot_be_unpublished_while_public_directory_content_depends_on_it(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->owner(), 'admin');

        $division = Division::query()->where('name', 'Dhaka')->firstOrFail();
        $district = District::query()
            ->where('division_id', $division->id)
            ->where('name', 'Dhaka')
            ->firstOrFail();
        $member = \App\Models\LatestNews::create([
            'name' => 'Status guard demo profile',
            'type' => 'our-members',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'language' => 'en',
            'status' => 1,
        ]);

        $this->put(route('district.status', $district->id))
            ->assertStatus(409)
            ->assertJsonPath('dependencies.published_team_members', 1);
        $this->assertTrue((bool) $district->fresh()->status);

        $this->put(route('division.status', $division->id))
            ->assertStatus(409)
            ->assertJsonPath('dependencies.published_team_members', 1);
        $this->assertTrue((bool) $division->fresh()->status);

        $member->update(['status' => 0]);
        District::query()->where('division_id', $division->id)->update(['status' => 0]);
        $this->put(route('division.status', $division->id))->assertOk();
        $this->assertFalse((bool) $division->fresh()->status);
    }

    public function test_profiles_and_activities_cannot_be_published_with_inactive_or_mismatched_geography(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->owner(), 'admin');

        $division = Division::query()->where('name', 'Dhaka')->firstOrFail();
        $district = District::query()
            ->where('division_id', $division->id)
            ->where('name', 'Dhaka')
            ->firstOrFail();
        $member = \App\Models\LatestNews::create([
            'name' => 'Inactive geography profile',
            'type' => 'our-members',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'language' => 'en',
            'status' => 0,
        ]);
        $activity = NoticeBoard::create([
            'title' => 'Inactive geography activity',
            'slug' => 'inactive-geography-activity',
            'content_kind' => 'article',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'language' => 'en',
            'published_at' => now(),
            'status' => 0,
        ]);

        $district->update(['status' => 0]);
        $this->put(route('latest.news.status', $member->id), [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Choose an active district inside an active work division before publishing this team profile.');
        $this->put(route('notice.board.status', $activity->id), [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Choose an active district inside an active activity division before publishing this item.');
        $this->assertFalse((bool) $member->fresh()->status);
        $this->assertFalse((bool) $activity->fresh()->status);

        $district->update(['status' => 1]);
        $division->update(['status' => 0]);
        $this->put(route('latest.news.status', $member->id), [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(409);
        $this->put(route('notice.board.status', $activity->id), [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(409);

        $division->update(['status' => 1]);
        $this->put(route('latest.news.status', $member->id), [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();
        $this->put(route('notice.board.status', $activity->id), [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();
        $this->assertTrue((bool) $member->fresh()->status);
        $this->assertTrue((bool) $activity->fresh()->status);
    }

    private function owner(): Admin
    {
        $role = Role::create([
            'name' => 'Meet the Heroes content owner',
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
            'is_owner' => true,
        ]);

        return Admin::create([
            'name' => 'Heroes QA Owner',
            'username' => 'heroes-qa-owner',
            'email' => 'heroes-qa-owner@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => Hash::make('admin-password'),
            'must_change_password' => false,
        ]);
    }

    private function onePixelPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );
    }
}
