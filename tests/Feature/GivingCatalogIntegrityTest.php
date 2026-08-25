<?php

namespace Tests\Feature;

use App\Models\DonationType;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GivingCatalogIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED = [
        'zakat' => ['Donate Your Zakat', 'restricted_fund', null],
        'sadaqah' => ['Donate Your Sadaqah', 'restricted_fund', null],
        'food-support' => ['Food Support', 'page', 'project-onno'],
        'orphan-support' => ['Orphan Shelter & Support', 'restricted_fund', null],
        'school-stationery' => ['School Stationery', 'page', 'education'],
        'school-uniforms' => ['School Uniforms', 'page', 'education'],
        'school-meals' => ['School Meals', 'page', 'education'],
        'adopt-a-school' => ['Adopt a School', 'restricted_fund', null],
        'ramadan-iftar' => ['Ramadan Iftar', 'page', 'project-onno'],
        'qurbani' => ['Qurbani', 'restricted_fund', null],
        'pure-water-and-sanitation' => ['Pure Water & Sanitation', 'page', 'clean-water'],
        'women-empowerment' => ['Women Empowerment', 'restricted_fund', null],
        'youth-development' => ['Youth Development', 'page', 'youth-development'],
        'street-children-education' => ['Street Children Education', 'page', 'education'],
    ];

    public function test_requested_catalog_reuses_stable_causes_and_adds_safe_drafts(): void
    {
        $this->createLegacyZakatAndRefreshCatalog();
        $this->rollbackCompleteCatalog();
        $pageUuids = $this->createFundingPages();
        $migration = require database_path('migrations/2026_08_21_131000_add_requested_giving_catalog.php');
        $migration->up();

        foreach (self::EXPECTED as $slug => [$name, $destinationType, $pageSlug]) {
            $cause = DonationType::withTrashed()->where('slug', $slug)->sole();

            $this->assertSame($name, $cause->name);
            $this->assertSame($destinationType, $cause->destination_type);
            $this->assertSame($pageSlug ? $pageUuids[$pageSlug] : null, $cause->destination_page_uuid);
            $this->assertNotSame('', trim((string) $cause->description));
            $this->assertFalse((bool) $cause->status);
            $this->assertNull($cause->deleted_at);
        }

        $this->assertSame(1, DonationType::where('purpose_key', 'zakat')->count());
        $this->assertSame('84ae0875-0656-494a-b3a2-9c9477397465', DonationType::where('slug', 'zakat')->value('uuid'));
        $this->assertSame('6e3c1d7a-8b01-4f01-8a01-000000000001', DonationType::where('slug', 'sadaqah')->value('uuid'));
        $this->assertSame(0, DonationType::withTrashed()->where('slug', 'sponsor-a-child')->count());
        $this->assertSame(1500, config('site-settings.groups.sponsor_page.fields.monthly_amount.default'));
    }

    public function test_catalog_migration_is_idempotent_and_preserves_admin_decisions(): void
    {
        $custom = DonationType::where('slug', 'school-stationery')->sole();
        $custom->update([
            'name' => 'Learning Materials',
            'description' => 'Administrator-approved custom wording.',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Custom Materials Fund',
            'destination_page_uuid' => null,
            'status' => 1,
        ]);
        DonationType::where('slug', 'qurbani')->sole()->delete();
        $before = DonationType::withTrashed()->count();

        $migration = require database_path('migrations/2026_08_21_131000_add_requested_giving_catalog.php');
        $migration->up();
        $migration->up();

        $custom->refresh();
        $this->assertSame('Learning Materials', $custom->name);
        $this->assertSame('Administrator-approved custom wording.', $custom->description);
        $this->assertSame('restricted_fund', $custom->destination_type);
        $this->assertSame('Custom Materials Fund', $custom->destination_name);
        $this->assertTrue((bool) $custom->status);
        $this->assertSame($before, DonationType::withTrashed()->count());
        $this->assertSame(1, DonationType::withTrashed()->where('slug', 'qurbani')->count());
        $this->assertNotNull(DonationType::withTrashed()->where('slug', 'qurbani')->sole()->deleted_at);
    }

    public function test_page_mappings_require_a_public_fallback_locale_fundable_destination(): void
    {
        $this->rollbackCompleteCatalog();

        $this->fundingPage('education', ['language' => 'bn']);
        $this->fundingPage('project-onno', ['visibility' => 'unlisted']);
        $deletedWater = $this->fundingPage('clean-water');
        $deletedWater->delete();
        $validYouth = $this->fundingPage('youth-development');

        $migration = require database_path('migrations/2026_08_21_131000_add_requested_giving_catalog.php');
        $migration->up();

        foreach (['school-stationery', 'food-support', 'pure-water-and-sanitation'] as $slug) {
            $cause = DonationType::where('slug', $slug)->sole();
            $this->assertSame('restricted_fund', $cause->destination_type);
            $this->assertNull($cause->destination_page_uuid);
        }
        $youth = DonationType::where('slug', 'youth-development')->sole();
        $this->assertSame('page', $youth->destination_type);
        $this->assertSame($validYouth->uuid, $youth->destination_page_uuid);
    }

    public function test_complete_catalog_publishes_all_reviewed_cards_in_stable_order(): void
    {
        $this->rollbackCompleteCatalog();

        foreach ([
            [
                'uuid' => '55555555-5555-4555-8555-000000000001',
                'slug' => 'where-it-is-needed-most',
                'name' => 'Where it is needed most',
                'description' => 'Flexible support for active community priorities.',
                'destination_type' => 'unrestricted',
                'status' => 1,
            ],
            [
                'uuid' => '55555555-5555-4555-8555-000000000002',
                'slug' => 'education',
                'name' => 'Education',
                'description' => 'Learning access, materials, and school support.',
                'destination_type' => 'restricted_fund',
                'destination_name' => 'Education Fund',
                'status' => 1,
            ],
            [
                'uuid' => '84ae0875-0656-494a-b3a2-9c9477397465',
                'slug' => 'zakat',
                'name' => 'Donate Your Zakat',
                'description' => 'Eligible Zakat-supported programs.',
                'purpose_key' => 'zakat',
                'destination_type' => 'restricted_fund',
                'destination_name' => 'Zakat Fund',
                'status' => 1,
            ],
        ] as $cause) {
            DonationType::query()->updateOrCreate(['slug' => $cause['slug']], $cause);
        }

        DonationType::query()->where('slug', 'emergency-relief')->update([
            'description' => null,
            'status' => 0,
        ]);
        $unrelated = DonationType::create([
            'name' => 'Future pilot fund',
            'description' => 'Not part of the approved catalog.',
            'status' => 0,
        ]);

        $migration = require database_path('migrations/2026_08_25_130000_complete_donation_card_catalog.php');
        $migration->up();

        $expected = [
            'where-it-is-needed-most',
            'education',
            'zakat',
            'sadaqah',
            'food-support',
            'emergency-relief',
            'orphan-support',
            'school-stationery',
            'school-uniforms',
            'school-meals',
            'adopt-a-school',
            'ramadan-iftar',
            'qurbani',
            'pure-water-and-sanitation',
            'women-empowerment',
            'youth-development',
            'street-children-education',
        ];
        $cards = DonationType::query()
            ->whereIn('slug', $expected)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        $this->assertSame($expected, $cards->pluck('slug')->all());
        $this->assertTrue($cards->every(fn (DonationType $cause): bool => (bool) $cause->status));
        $this->assertTrue($cards->every(fn (DonationType $cause): bool => filled($cause->icon_key)));
        $this->assertSame(range(10, 170, 10), $cards->pluck('display_order')->all());
        $this->assertSame(
            'Provide urgent essentials to communities affected by disasters and emergencies.',
            $cards->firstWhere('slug', 'emergency-relief')->description
        );
        $this->assertFalse((bool) $unrelated->fresh()->status);

        $publicCards = app(\App\Services\DonationDestinationService::class)
            ->activeCauses()
            ->whereIn('slug', $expected)
            ->pluck('slug')
            ->values()
            ->all();
        $this->assertSame($expected, $publicCards);

        $emergency = DonationType::where('slug', 'emergency-relief')->sole();
        $migration->down();

        $this->assertTrue((bool) DonationType::where('slug', 'sadaqah')->sole()->status);
        $this->assertSame(
            'Provide urgent essentials to communities affected by disasters and emergencies.',
            $emergency->fresh()->description
        );
    }

    public function test_stale_page_classification_is_repaired_before_catalog_completion(): void
    {
        $this->rollbackCompleteCatalog();
        $pageUuids = $this->createFundingPages();

        $requestedCatalog = require database_path('migrations/2026_08_21_131000_add_requested_giving_catalog.php');
        $requestedCatalog->up();

        Page::query()
            ->whereIn('uuid', array_values($pageUuids))
            ->update(['is_funding_project' => false]);

        $completeCatalog = require database_path('migrations/2026_08_25_130000_complete_donation_card_catalog.php');
        $completeCatalog->up();

        $stalePublicCauses = app(\App\Services\DonationDestinationService::class)->activeCauses();
        $this->assertLessThan(17, $stalePublicCauses->count());
        $this->assertFalse($stalePublicCauses->contains('slug', 'street-children-education'));

        $fundingClassification = require database_path('migrations/2026_08_20_180800_add_funding_project_classification.php');
        $fundingClassification->up();
        $completeCatalog->up();

        $publicCauses = app(\App\Services\DonationDestinationService::class)->activeCauses();
        $this->assertCount(count(self::EXPECTED), $publicCauses);
        $this->assertTrue($publicCauses->contains('slug', 'street-children-education'));
        $this->assertSame(
            4,
            Page::query()->whereIn('uuid', array_values($pageUuids))->where('is_funding_project', true)->count()
        );
    }

    private function rollbackCompleteCatalog(): void
    {
        DonationType::query()->whereIn('slug', [
            'sadaqah',
            'food-support',
            'emergency-relief',
            'orphan-support',
            'school-stationery',
            'school-uniforms',
            'school-meals',
            'adopt-a-school',
            'ramadan-iftar',
            'qurbani',
            'pure-water-and-sanitation',
            'women-empowerment',
            'youth-development',
            'street-children-education',
        ])->update(['status' => 0]);
        DonationType::query()
            ->where('slug', 'emergency-relief')
            ->update(['description' => null]);

        $migration = require database_path('migrations/2026_08_25_130000_complete_donation_card_catalog.php');
        $migration->down();
    }

    private function createLegacyZakatAndRefreshCatalog(): void
    {
        DonationType::create([
            'uuid' => '84ae0875-0656-494a-b3a2-9c9477397465',
            'slug' => 'zakat',
            'name' => 'Zakat',
            'description' => 'Eligible Zakat-supported programs.',
            'purpose_key' => 'zakat',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Zakat Fund',
            'status' => 0,
        ]);

        $migration = require database_path('migrations/2026_08_21_131000_add_requested_giving_catalog.php');
        $migration->up();
    }

    /** @return array<string, string> */
    private function createFundingPages(): array
    {
        $uuids = [];
        foreach (['education', 'project-onno', 'clean-water', 'youth-development'] as $slug) {
            $page = $this->fundingPage($slug);
            $uuids[$slug] = $page->uuid;
        }

        return $uuids;
    }

    private function fundingPage(string $slug, array $overrides = []): Page
    {
        return Page::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'sub_title' => 'Approved funding destination.',
            'slug' => $slug,
            'language' => 'en',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'is_funding_project' => true,
        ], $overrides));
    }
}
