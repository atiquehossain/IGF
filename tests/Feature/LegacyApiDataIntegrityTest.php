<?php

namespace Tests\Feature;

use App\Helper\MyYoutube;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Comment;
use App\Models\District;
use App\Models\Division;
use App\Models\EventCalendar;
use App\Models\Like;
use App\Models\Page;
use App\Models\Role;
use App\Models\Tag;
use App\Models\Upazila;
use App\Models\User;
use App\Models\YouTube;
use App\Models\YouTubeWatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegacyApiDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_geo_api_maps_upazilas_through_their_district_and_omits_inactive_ancestors(): void
    {
        $correctDivision = Division::create(['name' => 'Correct Division', 'status' => 1]);
        $wrongDivision = Division::create(['name' => 'Wrong Division', 'status' => 1]);
        District::create(['name' => 'First District', 'division_id' => $wrongDivision->id, 'status' => 1]);
        $district = District::create(['name' => 'Target District', 'division_id' => $correctDivision->id, 'status' => 1]);
        $upazila = Upazila::create(['name' => 'Target Upazila', 'district_id' => $district->id, 'status' => 1]);

        $inactiveDivision = Division::create(['name' => 'Inactive Division', 'status' => 0]);
        $inactiveDistrict = District::create(['name' => 'Hidden District', 'division_id' => $inactiveDivision->id, 'status' => 1]);
        Upazila::create(['name' => 'Hidden Upazila', 'district_id' => $inactiveDistrict->id, 'status' => 1]);

        // Use the stable endpoint directly because the historic route has no name.
        $this->withoutMiddleware();
        $response = $this->actingAs($this->member())->postJson('/api/v1/user/geo')
            ->assertOk()
            ->assertJsonPath('status', true);

        $returned = collect($response->json('data.upazila'))->firstWhere('UpazillaCode', $upazila->id);
        $this->assertSame($correctDivision->id, $returned['DivisionCode']);
        $this->assertNotContains('Hidden District', collect($response->json('data.district'))->pluck('DistrictName'));
        $this->assertNotContains('Hidden Upazila', collect($response->json('data.upazila'))->pluck('UpazillaName'));
    }

    public function test_events_api_returns_every_overlapping_event_in_the_requested_locale(): void
    {
        EventCalendar::create([
            'title' => 'Spans requested range',
            'start_date' => '2026-08-01 10:00:00',
            'end_date' => '2026-08-30 18:00:00',
            'status' => 1,
            'language' => 'en',
        ]);
        EventCalendar::create([
            'title' => 'Outside range',
            'start_date' => '2026-09-01 10:00:00',
            'end_date' => '2026-09-02 18:00:00',
            'status' => 1,
            'language' => 'en',
        ]);

        $this->getJson('/api/v1/events?start=2026-08-10&end=2026-08-12')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Spans requested range')
            ->assertJsonPath('0.start', '2026-08-01 10:00');

        $this->getJson('/api/v1/events?start=2026-08-12&end=2026-08-10')
            ->assertUnprocessable();
    }

    public function test_api_rejects_unpublished_locale_headers_instead_of_reading_arbitrary_translation_paths(): void
    {
        $this->withHeader('locale', '../../secrets')
            ->getJson('/api/v1/events')
            ->assertStatus(400)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Unsupported locale.');
    }

    public function test_recent_posts_use_portable_queries_and_honor_the_requested_category(): void
    {
        $category = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'News',
            'slug' => 'news',
            'language' => 'en',
            'status' => 1,
        ]);
        Page::create([
            'uuid' => (string) Str::uuid(),
            'category_id' => $category->id,
            'name' => 'Current story',
            'sub_title' => '',
            'slug' => 'current-story',
            'description' => '<p>Safe story</p>',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
            'published_at' => now(),
        ]);

        $this->getJson('/api/v1/recent-post/news?search=Current')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.items.0.name', 'Current story')
            ->assertJsonPath('data.items.0.published_at', now()->format('d-m-Y'));
        $this->getJson('/api/v1/recent-post/missing-category')->assertNotFound();
    }

    public function test_comment_submission_is_validated_moderated_and_idempotent(): void
    {
        $page = $this->commentablePage();
        $payload = ['page_id' => $page->id, 'name' => 'Visitor', 'text' => 'A useful story.'];

        $this->postJson('/api/v1/page-comment', $payload)
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('created', true);
        $this->postJson('/api/v1/page-comment', $payload)
            ->assertOk()
            ->assertJsonPath('created', false);

        $this->assertDatabaseCount('comments', 1);
        $this->assertDatabaseHas('comments', [
            'page_id' => $page->id,
            'text' => 'A useful story.',
            'status' => 0,
            'is_delete' => 0,
        ]);
        $this->assertNotSame('127.0.0.1', Comment::firstOrFail()->ip);

        $page->update(['is_comment' => 0]);
        $this->postJson('/api/v1/page-comment', ['page_id' => $page->id, 'text' => 'No longer allowed'])
            ->assertNotFound();
    }

    public function test_like_api_supports_an_idempotent_desired_state_and_rejects_orphan_ids(): void
    {
        $page = $this->commentablePage();
        $comment = Comment::create([
            'page_id' => $page->id,
            'text' => 'Approved comment',
            'status' => 1,
            'is_delete' => 0,
        ]);

        $payload = ['comment_id' => $comment->id, 'liked' => true];
        $this->postJson('/api/v1/page-like', $payload)->assertOk()->assertJsonPath('liked', true);
        $this->postJson('/api/v1/page-like', $payload)->assertOk()->assertJsonPath('liked', true);
        $this->assertSame(1, Like::where('comment_id', $comment->id)->count());
        $this->assertNotSame('127.0.0.1', Like::firstOrFail()->ip);

        $this->postJson('/api/v1/page-like', ['comment_id' => $comment->id, 'liked' => false])
            ->assertOk()->assertJsonPath('total_like', 0);
        $this->postJson('/api/v1/page-like', ['comment_id' => 999999, 'liked' => true])
            ->assertNotFound();
    }

    public function test_youtube_listing_batches_provider_checks_and_progress_requires_a_bound_watch_token(): void
    {
        config(['services.youtube.api_key' => 'test-key']);
        Http::fake([
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [['id' => 'video_id_01'], ['id' => 'video_id_02']],
            ]),
        ]);
        YouTube::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'First video',
            'video_id' => 'video_id_01',
            'activision_time' => 1,
            'duration_time' => 5,
            'language' => 'en',
            'status' => 1,
        ]);
        YouTube::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Second video',
            'video_id' => 'video_id_02',
            'activision_time' => 1,
            'duration_time' => 5,
            'language' => 'en',
            'status' => 1,
        ]);
        $user = $this->member('video-member@example.test');
        $this->withoutMiddleware();
        $this->actingAs($user);

        $listing = $this->postJson('/api/v1/youtube', [])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('provider_verified', true);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('X-Goog-Api-Key', 'test-key')
            && !str_contains($request->url(), 'test-key')
            && !array_key_exists('key', $request->data()));

        $payload = json_decode(Crypt::decryptString($listing->json('data.0.watch_token')), true, 8, JSON_THROW_ON_ERROR);
        $payload['issued_at'] = now()->subMinutes(2)->timestamp;
        $elapsedToken = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));

        $progress = [
            'video_id' => $listing->json('data.0.video_id'),
            'duration_time' => 2,
            'watch_token' => $elapsedToken,
        ];
        $this->postJson('/api/v1/youtube-meta', $progress)
            ->assertOk()
            ->assertJsonPath('data.watch_verified', true)
            ->assertJsonPath('data.completed', true);
        $this->postJson('/api/v1/youtube-meta', $progress)->assertOk();

        $this->assertSame(1, YouTubeWatch::where('user_id', $user->id)
            ->where('video_id', $progress['video_id'])->count());

        $this->postJson('/api/v1/youtube-meta', [
            'video_id' => 'video_id_02',
            'duration_time' => 100,
        ])->assertOk()
            ->assertJsonPath('data.watch_verified', false)
            ->assertJsonPath('data.completed', false)
            ->assertJsonPath('data.accepted_duration_time', 0);
    }

    public function test_youtube_provider_failures_never_log_the_api_key_or_exception_url(): void
    {
        config(['services.youtube.api_key' => 'youtube-secret-never-log']);
        Log::spy();
        Http::fake(fn () => throw new ConnectionException(
            'Connection failed for https://www.googleapis.com/youtube/v3/videos?key=youtube-secret-never-log'
        ));

        $this->assertNull(MyYoutube::existingVideoIds(['video_id_01']));

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            $encoded = $message . json_encode($context);

            return $message === 'YouTube provider request failed.'
                && ($context['exception_class'] ?? null) === ConnectionException::class
                && !str_contains($encoded, 'youtube-secret-never-log')
                && !str_contains($encoded, 'googleapis.com');
        });
    }

    public function test_watch_integrity_migration_consolidates_more_than_one_batch_of_duplicate_groups(): void
    {
        Schema::table('you_tube_watches', function ($table): void {
            $table->dropUnique('youtube_watches_user_video_unique');
        });

        $videos = [];
        $watches = [];
        for ($index = 1; $index <= 101; $index++) {
            $videoId = 'video_' . str_pad((string) $index, 3, '0', STR_PAD_LEFT);
            $videos[] = [
                'video_id' => $videoId,
                'name' => 'Migration video ' . $index,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $watches[] = [
                'video_id' => $videoId,
                'user_id' => 9001,
                'duration_time' => 1,
                'status' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $watches[] = [
                'video_id' => $videoId,
                'user_id' => 9001,
                'duration_time' => 2,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($videos, 50) as $chunk) {
            DB::table('you_tubes')->insert($chunk);
        }
        foreach (array_chunk($watches, 50) as $chunk) {
            DB::table('you_tube_watches')->insert($chunk);
        }

        $migration = require database_path('migrations/2026_08_21_120000_harden_legacy_api_integrity.php');
        $migration->up();
        $migration->down();

        $this->assertSame(101, DB::table('you_tube_watches')->count());
        $this->assertSame(0, DB::table('you_tube_watches')
            ->select('user_id', 'video_id')
            ->groupBy('user_id', 'video_id')
            ->havingRaw('COUNT(*) > 1')
            ->count());
        $this->assertSame(2.0, (float) DB::table('you_tube_watches')
            ->where('video_id', 'video_101')
            ->value('duration_time'));
        $this->assertTrue(Schema::hasIndex('you_tube_watches', 'youtube_watches_user_video_unique'));
        $this->assertTrue(Schema::hasIndex('districts', 'districts_division_lookup'));
        $this->assertTrue(Schema::hasIndex('upazilas', 'upazilas_district_lookup'));
        $this->assertTrue(Schema::hasIndex('users', 'users_geography_lookup'));
        $this->assertTrue(Schema::hasIndex('comments', 'comments_public_lookup'));
        $this->assertTrue(Schema::hasIndex('likes', 'likes_comment_lookup'));
    }

    public function test_geography_deletions_are_restricted_and_blank_legacy_routes_redirect(): void
    {
        $admin = $this->ownerAdmin();
        $division = Division::create(['name' => 'Referenced Division', 'status' => 1]);
        $district = District::create(['name' => 'Referenced District', 'division_id' => $division->id, 'status' => 1]);
        $upazila = Upazila::create(['name' => 'Referenced Upazila', 'district_id' => $district->id, 'status' => 1]);
        $this->member('dependent-member@example.test', [
            'division_id' => $division->id,
            'district_id' => $district->id,
            'upazila_id' => $upazila->id,
        ]);

        $this->actingAs($admin, 'admin')->deleteJson(route('division.destroy', $division->id))
            ->assertStatus(409)->assertJsonPath('dependencies.districts', 1);
        $this->actingAs($admin, 'admin')->deleteJson(route('district.destroy', $district->id))
            ->assertStatus(409)->assertJsonPath('dependencies.upazilas', 1);
        $this->actingAs($admin, 'admin')->deleteJson(route('upazila.destroy', $upazila->id))
            ->assertStatus(409)->assertJsonPath('dependencies.users', 1);

        foreach (['division.create', 'division.show', 'district.create', 'upazila.create', 'tag.create', 'tag.show'] as $routeName) {
            $parameters = str_ends_with($routeName, '.show') ? ['id' => 999] : [];
            $this->actingAs($admin, 'admin')->get(route($routeName, $parameters))->assertRedirect();
        }
    }

    public function test_inline_status_endpoints_use_the_route_identifier_without_a_request_body(): void
    {
        $admin = $this->ownerAdmin();
        $division = Division::create(['name' => 'Toggle Division', 'status' => 0]);
        $district = District::create(['name' => 'Toggle District', 'division_id' => $division->id, 'status' => 0]);
        $upazila = Upazila::create(['name' => 'Toggle Upazila', 'district_id' => $district->id, 'status' => 0]);
        $tag = Tag::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Toggle Project',
            'slug' => 'toggle-project',
            'status' => 0,
        ]);

        foreach ([
            ['division.status', $division],
            ['district.status', $district],
            ['upazila.status', $upazila],
            ['tag.status', $tag],
        ] as [$routeName, $model]) {
            $this->actingAs($admin, 'admin')
                ->putJson(route($routeName, $model->id))
                ->assertOk()
                ->assertJsonPath('status', true);
            $this->assertTrue((bool) $model->fresh()->status);
        }
    }

    private function commentablePage(): Page
    {
        return Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Commentable story',
            'sub_title' => '',
            'slug' => 'commentable-story-' . Str::lower(Str::random(6)),
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'is_comment' => 1,
            'language' => 'en',
            'published_at' => now(),
        ]);
    }

    private function member(string $email = 'member@example.test', array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Approved Member',
            'email' => $email,
            'password' => Hash::make('member-password'),
            'status' => 1,
            'is_approved' => 1,
        ], $overrides));
    }

    private function ownerAdmin(): Admin
    {
        $role = Role::create([
            'name' => 'Legacy API test owner',
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
            'is_owner' => true,
        ]);

        return Admin::create([
            'name' => 'Legacy API test owner',
            'username' => 'legacy-api-owner',
            'email' => 'legacy-api-owner@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => Hash::make('admin-password'),
            'must_change_password' => false,
        ]);
    }
}
