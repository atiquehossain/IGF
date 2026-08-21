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
