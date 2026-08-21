<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DonationType;
use App\Models\Donation;
use App\Models\Gallery;
use App\Models\LatestNews;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageMenu;
use App\Models\PageTagModule;
use App\Models\SiteSetting;
use App\Models\Tag;
use App\Models\VolunteerCause;
use App\Services\MediaUsageService;
use App\Services\PageBlockContentResolver;
use App\Services\TranslationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DynamicAdminContentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_and_award_cards_resolve_from_managed_pages(): void
    {
        $current = Tag::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Current Projects',
            'slug' => 'current-project',
            'status' => 1,
        ]);
        $project = $this->page('Project Ankur', 'project-ankur', ['order_by' => 20]);
        PageTagModule::create([
            'uuid' => (string) Str::uuid(),
            'page_id' => $project->id,
            'tag_id' => $current->id,
        ]);
        $projectBlock = $this->block([
            'variant' => 'projects',
            'content_source' => 'projects',
            'tag_slug' => 'current-project',
            'selection_mode' => 'automatic',
            'sort' => 'featured',
            'limit' => 3,
            'item_link_label' => 'Read project',
            'items' => [['heading' => 'Stale snapshot']],
        ]);

        $projectContent = app(PageBlockContentResolver::class)->resolve($projectBlock);
        $this->assertSame(['Project Ankur'], collect($projectContent['items'])->pluck('heading')->all());
        $this->assertSame('Read project', $projectContent['items'][0]['link_label']);
        $this->assertSame('/page/project-ankur', $projectContent['items'][0]['url']);

        $awards = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Awards & Recognition',
            'slug' => 'awards-&-recognition',
            'language' => 'en',
            'status' => 1,
        ]);
        $award = $this->page('The Community Award', 'community-award', [
            'category_id' => $awards->id,
            'order_by' => 10,
        ]);
        $awardBlock = $this->block([
            'variant' => 'awards',
            'content_source' => 'category',
            'category_slug' => 'awards-&-recognition',
            'selection_mode' => 'manual',
            'selected_items' => [$award->uuid],
            'sort' => 'title',
            'limit' => 3,
        ]);

        $awardContent = app(PageBlockContentResolver::class)->resolve($awardBlock);
        $this->assertSame('The Community Award', $awardContent['items'][0]['heading']);
    }

    public function test_manual_source_with_no_selected_items_stays_intentionally_empty(): void
    {
        $category = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Programs',
            'slug' => 'programs',
            'language' => 'en',
            'status' => 1,
        ]);
        $this->page('Should not leak into the block', 'not-selected', ['category_id' => $category->id]);
        $block = $this->block([
            'content_source' => 'category',
            'category_slug' => 'programs',
            'selection_mode' => 'manual',
            'selected_items' => [],
            'empty_state' => 'Choose items before publishing.',
        ]);

        $content = app(PageBlockContentResolver::class)->resolve($block);

        $this->assertSame([], $content['items']);
        $this->assertSame('Choose items before publishing.', $content['empty_state']);
    }

    public function test_gallery_block_uses_managed_media_and_accessible_description_not_redirect_as_image(): void
    {
        $photo = Gallery::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Community workshop',
            'description' => 'Participants working together around a table.',
            'type' => 'gallery',
            'path' => 'workshop photo.jpg',
            'url' => 'https://example.test/story',
            'language' => 'en',
            'status' => 1,
        ]);
        $page = $this->page('Gallery block page', 'gallery-block-page');
        $block = PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'type' => 'gallery',
            'label' => 'Managed gallery',
            'content' => ['content_source' => 'gallery', 'limit' => 3],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);

        $item = app(PageBlockContentResolver::class)->resolve($block)['items'][0];

        $this->assertSame('/storage/photos/1/gallery/' . $photo->id . '/430X360/workshop%20photo.jpg', $item['image']);
        $this->assertSame('Participants working together around a table.', $item['image_alt']);
        $this->assertSame('https://example.test/story', $item['url']);
    }

    public function test_zakat_route_uses_the_admin_selected_semantic_purpose(): void
    {
        DonationType::create([
            'uuid' => '84ae0875-0656-494a-b3a2-9c9477397465',
            'name' => 'Legacy regular cause',
            'status' => 1,
        ]);
        $selected = DonationType::create([
            'name' => 'Eligible Zakat programs',
            'purpose_key' => 'zakat',
            'status' => 1,
        ]);

        $this->get(route('frontend.donate.cause', ['type' => 'zakat']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.selectedUUID', $selected->uuid));
    }

    public function test_zakat_destination_cannot_be_unpublished_or_deleted(): void
    {
        $selected = DonationType::create([
            'name' => 'Eligible Zakat programs',
            'purpose_key' => 'zakat',
            'status' => 1,
        ]);
        $controller = app(\App\Http\Controllers\Admin\DonationTypeController::class);
        $statusRequest = \Illuminate\Http\Request::create('/admin/donation-type/' . $selected->id, 'PUT', ['id' => $selected->id]);
        $statusRequest->headers->set('X-Requested-With', 'XMLHttpRequest');

        $this->assertSame(422, $controller->status($statusRequest)->getStatusCode());
        $this->assertSame(422, $controller->destroy($selected->id, \Illuminate\Http\Request::create('/', 'DELETE'))->getStatusCode());
        $this->assertDatabaseHas('donation_types', ['id' => $selected->id, 'purpose_key' => 'zakat', 'status' => 1, 'deleted_at' => null]);
    }

    public function test_historical_donation_keeps_its_soft_deleted_cause_label(): void
    {
        $cause = DonationType::create(['name' => 'Emergency response', 'status' => 1]);
        $donation = Donation::create([
            'donor_name' => 'Audit Donor',
            'email' => 'donor@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'payment_cause' => $cause->uuid,
            'amount' => 1000,
            'payment_status' => 'Completed',
        ]);

        $cause->delete();

        $this->assertSame('Emergency response', $donation->fresh()->donationType->name);
    }

    public function test_footer_backfill_is_editable_and_does_not_replace_existing_records(): void
    {
        $this->assertSame(4, PageMenu::where('type', 'footer')->whereNull('parent_id')->count());
        $this->assertSame(17, PageMenu::where('type', 'footer')->whereNotNull('parent_id')->count());

        $explore = PageMenu::where('type', 'footer')->whereNull('parent_id')->where('name', 'Explore')->firstOrFail();
        $explore->update(['name' => 'Discover']);

        $migration = require database_path('migrations/2026_08_19_090200_seed_editable_footer_navigation.php');
        $migration->up();

        $this->assertSame(21, PageMenu::where('type', 'footer')->count());
        $this->assertDatabaseHas('page_menus', ['id' => $explore->id, 'name' => 'Discover']);
    }

    public function test_footer_backfill_pairs_corresponding_locales_with_the_same_uuid(): void
    {
        PageMenu::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'মূল নেভিগেশন',
            'type' => 'main',
            'link' => 'frontend.home',
            'language' => 'bn',
            'order_by' => 1,
            'status' => 1,
        ]);

        $migration = require database_path('migrations/2026_08_19_090200_seed_editable_footer_navigation.php');
        $migration->up();

        $english = PageMenu::where('type', 'footer')->where('language', 'en')->where('name', 'Explore')->firstOrFail();
        $bangla = PageMenu::where('type', 'footer')->where('language', 'bn')->where('name', 'Explore')->firstOrFail();
        $this->assertSame($english->uuid, $bangla->uuid);
    }

    public function test_media_usage_includes_legacy_content_and_site_settings(): void
    {
        $asset = MediaAsset::create([
            'uuid' => (string) Str::uuid(),
            'path' => 'media/managed-photo.webp',
            'original_name' => 'managed-photo.webp',
            'mime_type' => 'image/webp',
            'extension' => 'webp',
            'bytes' => 500,
        ]);
        Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Managed category',
            'slug' => 'managed-category',
            'language' => 'en',
            'image' => $asset->url,
            'status' => 1,
        ]);
        SiteSetting::create([
            'group' => 'branding',
            'key' => 'managed_test_logo',
            'locale' => '*',
            'value' => $asset->url,
            'type' => 'text',
            'is_public' => true,
        ]);

        $references = app(MediaUsageService::class)->references($asset);
        $this->assertSame(1, $references['categories']);
        $this->assertSame(1, $references['site_settings']);
        $this->assertTrue(app(MediaUsageService::class)->inUse($asset));
    }

    public function test_translation_center_covers_team_donation_and_volunteer_content(): void
    {
        $member = LatestNews::create([
            'name' => 'Amina Rahman',
            'type' => 'our-members',
            'description' => 'Program Director',
            'language' => 'en',
            'status' => 1,
        ]);
        $donation = DonationType::create(['name' => 'Education', 'description' => 'Support learning.', 'status' => 1]);
        $volunteer = VolunteerCause::create(['name' => 'Teaching', 'description' => 'Help learners.', 'status' => 1]);
        $service = app(TranslationCenterService::class);
        $rows = $service->rows('en', 'bn');

        $translations = collect([
            ['model' => 'team_member', 'source_id' => $member->id, 'field' => 'name', 'value' => 'আমিনা রহমান'],
            ['model' => 'donation_cause', 'source_id' => $donation->id, 'field' => 'name', 'value' => 'শিক্ষা'],
            ['model' => 'volunteer_opportunity', 'source_id' => $volunteer->id, 'field' => 'name', 'value' => 'শিক্ষাদান'],
        ])->map(function (array $wanted) use ($rows): array {
            $row = $rows->first(fn (array $candidate) =>
                ($candidate['identity']['type'] ?? null) === 'content_overlay'
                && ($candidate['identity']['model'] ?? null) === $wanted['model']
                && ($candidate['identity']['source_id'] ?? null) === $wanted['source_id']
                && ($candidate['identity']['field'] ?? null) === $wanted['field']
            );
            $this->assertNotNull($row);

            return [
                'key' => $row['key'],
                'precondition' => $row['precondition'],
                'value' => $wanted['value'],
            ];
        })->all();

        $this->assertSame(3, $service->save('en', 'bn', $translations, null));
        $this->assertSame('আমিনা রহমান', $service->localizedContentValue('team_member', (string) $member->id, 'name', $member->name, 'bn'));
        $this->assertSame('শিক্ষা', $service->localizedContentValue('donation_cause', $donation->uuid, 'name', $donation->name, 'bn'));
        $this->assertSame('শিক্ষাদান', $service->localizedContentValue('volunteer_opportunity', (string) $volunteer->id, 'name', $volunteer->name, 'bn'));
    }

    private function page(string $name, string $slug, array $overrides = []): Page
    {
        return Page::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'sub_title' => $name . ' summary',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
        ], $overrides));
    }

    private function block(array $content): PageBlock
    {
        $page = $this->page('Home ' . Str::random(5), 'home-' . Str::lower(Str::random(8)));

        return PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'type' => 'cards',
            'label' => 'Managed cards',
            'content' => $content,
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
    }
}
