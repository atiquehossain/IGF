<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Division;
use App\Models\LatestNews;
use App\Models\NoticeBoard;
use App\Models\SiteSetting;
use App\Models\TranslationLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MeetTheHeroesPublicJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The canonical Bangladesh location migration supplies all 8/64
        // records. Each test activates only the locations relevant to it.
        Division::query()->update(['status' => 0]);
        District::query()->update(['status' => 0]);
    }

    public function test_national_page_exposes_only_active_geography_and_safe_published_members(): void
    {
        [$dhaka, $dhakaDistrict] = $this->geography('Dhaka');
        $inactive = Division::create([
            'name' => 'Hidden',
            'slug' => 'hidden',
            'status' => 0,
        ]);
        $member = LatestNews::create([
            'name' => 'National hero',
            'type' => 'our-members',
            'description' => 'National Coordinator',
            'biography' => 'Works with teams throughout Bangladesh.',
            'language' => 'en',
            'order_by' => 100,
            'status' => 1,
            'email' => 'private@example.test',
            'url' => 'javascript:alert(1)',
            'social_links' => [
                ['platform' => 'linkedin', 'url' => 'https://www.linkedin.com/in/national-hero'],
                ['platform' => 'unsafe', 'url' => 'javascript:alert(2)'],
            ],
        ]);
        $this->member('Regional hero excluded nationally', $dhaka, $dhakaDistrict);
        $this->member('Unpublished hero', $dhaka, $dhakaDistrict, ['status' => 0]);

        $this->get('/meet-the-heroes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('heroes')
                ->where('scope', 'national')
                ->where('data.presentation.title', 'Meet the Heroes')
                ->where('presentation.title', 'Meet the Heroes')
                ->where('presentation.introduction', '')
                ->where('presentation.autoplay', true)
                ->where('presentation.animation_enabled', true)
                ->where('presentation.map_heading', 'Explore heroes by division')
                ->where('presentation.map_help', 'Choose a division on the map to meet its team and explore local activities.')
                ->has('data.members', 1)
                ->where('data.members.0.id', $member->id)
                ->where('data.members.0.url', '')
                ->missing('data.members.0.email')
                ->has('data.members.0.social_links', 1)
                ->has('data.divisions', 1)
                ->where('data.divisions.0.slug', 'dhaka')
                ->where('data.divisions.0.url', route('frontend.heroes.division', ['division' => 'dhaka']))
                ->has('data.activities', 0)
                ->where('data.current_division', null)
                ->where('data.current_district', null)
            );

        $this->assertNotSame($inactive->id, $dhaka->id);
    }

    public function test_division_page_scopes_members_district_navigation_and_released_activities(): void
    {
        [$dhaka, $dhakaDistrict] = $this->geography('Dhaka');
        [$sylhet, $sylhetDistrict] = $this->geography('Sylhet');
        $directMember = $this->member('Dhaka division hero', $dhaka);
        $districtMember = $this->member('Dhaka district hero', $dhaka, $dhakaDistrict, ['order_by' => 200]);
        $this->member('Sylhet hero', $sylhet, $sylhetDistrict);
        $inactiveDistrict = District::create([
            'division_id' => $dhaka->id,
            'name' => 'Hidden Dhaka District',
            'slug' => 'hidden-dhaka-district',
            'status' => 0,
        ]);
        $this->member('Inactive district hero', $dhaka, $inactiveDistrict);

        $directActivity = $this->activity('Dhaka division activity', $dhaka, null, [
            'published_at' => now()->subDay(),
        ]);
        $districtActivity = $this->activity('Dhaka district activity', $dhaka, $dhakaDistrict);
        $this->activity('Sylhet activity', $sylhet, $sylhetDistrict);
        $this->activity('Inactive district activity', $dhaka, $inactiveDistrict);
        $this->activity('Draft activity', $dhaka, $dhakaDistrict, ['status' => 0]);
        $this->activity('Future activity', $dhaka, $dhakaDistrict, ['published_at' => now()->addDay()]);

        $this->get('/meet-the-heroes/division/dhaka')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('heroes')
                ->where('scope', 'division')
                ->where('data.current_division.id', $dhaka->id)
                ->where('data.current_division.label', 'Dhaka')
                ->has('data.districts', 1)
                ->where('data.districts.0.id', $dhakaDistrict->id)
                ->where(
                    'data.districts.0.url',
                    route('frontend.heroes.district', ['district' => 'dhaka'])
                )
                ->has('data.members', 2)
                ->where('data.members.0.id', $districtMember->id)
                ->where('data.members.1.id', $directMember->id)
                ->has('data.activities', 2)
                ->where('data.activities.0.id', $districtActivity->id)
                ->where('data.activities.0.archive_url', route('frontend.news'))
                ->where('data.activities.1.id', $directActivity->id)
                ->where('presentation.about_heading', 'About Dhaka')
                ->where('presentation.activities_heading', 'Activities at Dhaka')
                ->where('presentation.introduction', '')
                ->where('presentation.map_heading', 'Explore heroes by district')
                ->where('presentation.map_help', 'Choose a district on the map to meet its team and explore local activities.')
            );
    }

    public function test_district_page_exposes_group_image_and_district_only_activity_payload(): void
    {
        [$division, $district] = $this->geography('Rangpur', [
            'description' => 'Rangpur volunteers turn local priorities into community action.',
            'hero_image' => '/storage/media/rangpur-heroes.jpg',
            'hero_image_alt' => 'Rangpur volunteers together',
            'hero_image_alt_bn' => 'রংপুরের স্বেচ্ছাসেবকেরা একসঙ্গে',
        ]);
        $districtMember = $this->member('Rangpur district hero', $division, $district);
        $this->member('Division-only hero', $division);
        $districtActivity = $this->activity('Rangpur event', $division, $district, [
            'content_kind' => 'event',
        ]);
        $this->activity('Rangpur division story', $division);

        $this->get('/meet-the-heroes/district/rangpur')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('heroes')
                ->where('scope', 'district')
                ->where('data.current_district.id', $district->id)
                ->where('data.current_district.hero_image', '/storage/media/rangpur-heroes.jpg')
                ->where('data.current_district.hero_image_alt', 'Rangpur volunteers together')
                ->where('data.current_division.id', $division->id)
                ->has('data.members', 1)
                ->where('data.members.0.id', $districtMember->id)
                ->has('data.activities', 1)
                ->where('data.activities.0.id', $districtActivity->id)
                ->where('data.activities.0.archive_url', route('frontend.events'))
                ->where('presentation.title', 'Meet the Heroes from Rangpur District')
                ->where('presentation.about_heading', 'About Rangpur')
                ->where('presentation.about_body', 'Rangpur volunteers turn local priorities into community action.')
                ->where('presentation.activities_heading', 'Activities at Rangpur')
                ->where('presentation.introduction', '')
            );
    }

    public function test_bangla_route_localizes_geography_and_falls_back_to_managed_english_members(): void
    {
        TranslationLocale::query()->whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        [$division, $district] = $this->geography('Dhaka');
        $member = $this->member('English source hero', $division, $district);

        $this->get('/meet-the-heroes/division/dhaka?lang=bn')
            ->assertOk()
            ->assertHeader('Content-Language', 'bn')
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope', 'division')
                ->where('data.current_division.label', 'ঢাকা')
                ->where('data.districts.0.label', 'ঢাকা')
                ->where('data.districts.0.url', route('frontend.heroes.district', [
                    'district' => 'dhaka',
                    'lang' => 'bn',
                ]))
                ->has('data.members', 1)
                ->where('data.members.0.id', $member->id)
                ->where('presentation.activities_heading', 'ঢাকা-এর কার্যক্রম')
            );
    }

    public function test_bangla_district_page_uses_localized_group_image_alt_text(): void
    {
        TranslationLocale::query()->whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        [, $district] = $this->geography('Rangpur', [
            'hero_image' => '/storage/media/rangpur-heroes.jpg',
            'hero_image_alt' => 'Rangpur volunteers together',
            'hero_image_alt_bn' => 'রংপুরের স্বেচ্ছাসেবকেরা একসঙ্গে',
        ]);

        $this->get('/meet-the-heroes/district/rangpur?lang=bn')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.current_district.id', $district->id)
                ->where('data.current_district.hero_image_alt', 'রংপুরের স্বেচ্ছাসেবকেরা একসঙ্গে')
            );
    }

    public function test_unknown_inactive_or_noncanonical_geography_is_not_public(): void
    {
        [$active, $activeDistrict] = $this->geography('Dhaka');
        [$inactive, $inactiveDistrict] = $this->geography('Hidden');
        $inactive->update(['status' => 0]);

        $this->get('/meet-the-heroes/division/not-real')->assertNotFound();
        $this->get('/meet-the-heroes/division/Dhaka')->assertNotFound();
        $this->get('/meet-the-heroes/division/hidden')->assertNotFound();
        $this->get('/meet-the-heroes/district/not-real')->assertNotFound();
        $this->get('/meet-the-heroes/district/Dhaka')->assertNotFound();
        $this->get('/meet-the-heroes/district/hidden')->assertNotFound();

        $this->assertTrue($active->status == 1 && $activeDistrict->status == 1 && $inactiveDistrict->status == 1);
    }

    public function test_motion_preferences_are_exposed_as_nonlocalized_booleans(): void
    {
        foreach (['autoplay', 'animation_enabled'] as $key) {
            SiteSetting::query()->create([
                'group' => 'meet_the_heroes',
                'key' => $key,
                'locale' => '*',
                'value' => '0',
                'type' => 'boolean',
                'is_public' => true,
            ]);
        }

        $this->get('/meet-the-heroes?lang=en')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('presentation.autoplay', false)
                ->where('presentation.animation_enabled', false)
                ->where('data.presentation.autoplay', false)
                ->where('data.presentation.animation_enabled', false)
            );
    }

    /** @return array{Division, District} */
    private function geography(string $name, array $districtAttributes = []): array
    {
        $slug = str($name)->slug()->toString();
        $division = Division::query()->firstOrNew(['slug' => $slug]);
        $division->fill([
            'name' => $name,
            'description' => $name . ' division narrative.',
            'status' => 1,
        ])->save();
        $district = District::query()->firstOrNew([
            'division_id' => $division->id,
            'name' => $name,
        ]);
        $district->fill(array_merge([
            'division_id' => $division->id,
            'name' => $name,
            'slug' => $slug,
            'description' => $name . ' district narrative.',
            'status' => 1,
        ], $districtAttributes))->save();

        return [$division, $district];
    }

    private function member(
        string $name,
        Division $division,
        ?District $district = null,
        array $attributes = [],
    ): LatestNews {
        return LatestNews::create(array_merge([
            'name' => $name,
            'type' => 'our-members',
            'description' => 'Community Coordinator',
            'biography' => 'Works with local communities.',
            'qualification' => 'Community leadership',
            'division_id' => $division->id,
            'district_id' => $district?->id,
            'language' => 'en',
            'order_by' => 100,
            'status' => 1,
        ], $attributes));
    }

    private function activity(
        string $title,
        Division $division,
        ?District $district = null,
        array $attributes = [],
    ): NoticeBoard {
        return NoticeBoard::create(array_merge([
            'title' => $title,
            'sub_title' => $title . ' summary',
            'description' => '<p>' . $title . ' description.</p>',
            'slug' => str($title)->slug()->toString(),
            'content_kind' => 'article',
            'division_id' => $division->id,
            'district_id' => $district?->id,
            'language' => 'en',
            'published_at' => now(),
            'order_by' => 100,
            'status' => 1,
        ], $attributes));
    }
}
