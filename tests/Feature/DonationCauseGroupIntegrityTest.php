<?php

namespace Tests\Feature;

use App\Http\Middleware\SetRouteSeo;
use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\DonationCauseGroup;
use App\Models\DonationType;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\TranslationLocale;
use App\Models\TranslationString;
use App\Services\TranslationCenterService;
use App\Support\AdminPermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DonationCauseGroupIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_migration_can_roll_back_and_be_reapplied(): void
    {
        $migration = require database_path('migrations/2026_08_28_100000_create_donation_cause_groups.php');

        try {
            $migration->down();

            $this->assertFalse(Schema::hasTable('donation_cause_groups'));
            $this->assertFalse(Schema::hasColumn('donation_types', 'donation_cause_group_id'));
        } finally {
            $migration->up();
        }

        $this->assertTrue(Schema::hasTable('donation_cause_groups'));
        $this->assertTrue(Schema::hasColumn('donation_types', 'donation_cause_group_id'));
    }

    public function test_group_migration_is_idempotent_and_backfills_only_unassigned_known_causes(): void
    {
        DonationType::withTrashed()->forceDelete();

        $customGroup = DonationCauseGroup::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Administrator choice',
            'slug' => 'administrator-choice',
            'display_order' => 5,
            'status' => true,
        ]);
        $education = $this->cause('Education', 'education');
        $zakat = $this->cause('Zakat campaign', 'zakat', $customGroup);
        $custom = $this->cause('Custom local campaign', 'custom-local-campaign');

        $general = DonationCauseGroup::where('slug', 'general-giving')->firstOrFail();
        $general->update([
            'name' => 'Locally edited general giving',
            'display_order' => 91,
            'status' => false,
        ]);

        $migration = require database_path('migrations/2026_08_28_100000_create_donation_cause_groups.php');
        $migration->up();
        $migration->up();

        $educationGroup = DonationCauseGroup::where('slug', 'education-children')->firstOrFail();
        $this->assertSame($educationGroup->id, $education->fresh()->donation_cause_group_id);
        $this->assertSame($customGroup->id, $zakat->fresh()->donation_cause_group_id);
        $this->assertNull($custom->fresh()->donation_cause_group_id);
        $this->assertSame(1, DonationCauseGroup::where('slug', 'education-children')->count());
        $this->assertSame('Locally edited general giving', $general->fresh()->name);
        $this->assertSame(91, $general->fresh()->display_order);
        $this->assertFalse((bool) $general->fresh()->status);
    }

    public function test_catalog_exposes_ordered_localized_populated_groups_without_hiding_causes(): void
    {
        DonationType::withTrashed()->forceDelete();
        DonationCauseGroup::query()->delete();

        $second = $this->group('Education & Children', 'education-children', 20);
        $first = $this->group('Community & Relief', 'community-relief', 10);
        $hidden = $this->group('Hidden campaigns', 'hidden-campaigns', 5, false);
        $this->group('Empty visible group', 'empty-visible-group', 1);

        $community = $this->cause('Community support', 'community-support', $first, 10);
        $education = $this->cause('Education support', 'education-support', $second, 20);
        $hiddenCause = $this->cause('Hidden-group cause', 'hidden-group-cause', $hidden, 30);
        $ungrouped = $this->cause('Ungrouped cause', 'ungrouped-cause', null, 40);

        TranslationString::create([
            'key' => 'content.donation_cause_group.' . $first->uuid . '.name',
            'locale' => 'bn',
            'value' => 'কমিউনিটি ও ত্রাণ',
            'source_hash' => hash('sha256', 'en|' . $first->name),
            'status' => 'translated',
        ]);
        TranslationLocale::whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        TranslationString::create([
            'key' => 'content.donation_cause_group.' . $first->uuid . '.description',
            'locale' => 'bn',
            'value' => 'জরুরি সহায়তা ও কমিউনিটি উদ্যোগ।',
            'source_hash' => hash('sha256', 'en|' . $first->description),
            'status' => 'translated',
        ]);

        $this->get(route('frontend.donate.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('data.donationTypes', 4)
                ->where('data.donationTypes.0.uuid', $community->uuid)
                ->where('data.donationTypes.0.group_uuid', $first->uuid)
                ->where('data.donationTypes.1.uuid', $education->uuid)
                ->where('data.donationTypes.1.group_uuid', $second->uuid)
                ->where('data.donationTypes.2.uuid', $hiddenCause->uuid)
                ->where('data.donationTypes.2.group_uuid', null)
                ->where('data.donationTypes.3.uuid', $ungrouped->uuid)
                ->where('data.donationTypes.3.group_uuid', null)
                ->has('data.donationGroups', 2)
                ->where('data.donationGroups.0.uuid', $first->uuid)
                ->where('data.donationGroups.0.slug', 'community-relief')
                ->where('data.donationGroups.0.name', 'Community & Relief')
                ->where('data.donationGroups.0.description', 'Browse Community & Relief causes.')
                ->where('data.donationGroups.1.uuid', $second->uuid)
            );

        $this->withoutMiddleware(SetRouteSeo::class)
            ->get(route('frontend.donate.index') . '?lang=bn')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.donationGroups.0.uuid', $first->uuid)
                ->where('data.donationGroups.0.name', 'কমিউনিটি ও ত্রাণ')
                ->where('data.donationGroups.0.description', 'জরুরি সহায়তা ও কমিউনিটি উদ্যোগ।')
            );

        $this->get(route('frontend.donate.cause', ['cause' => $hiddenCause->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('data.donationTypes', 1)
                ->has('data.donationGroups', 0)
                ->where('data.donationTypes.0.uuid', $hiddenCause->uuid)
            );
    }

    public function test_authorized_admin_can_manage_groups_and_assign_causes(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('donationType.index'))
            ->assertOk()
            ->assertSee('Donation cause groups')
            ->assertSee('Hiding a tab never hides its causes');

        $this->actingAs($admin, 'admin')->post(route('donationType.group.store'), [
            'group_name' => '<strong>Seasonal Campaigns</strong>',
            'group_description' => '<p>Time-bound giving opportunities.</p>',
            'group_display_order' => 75,
        ])->assertSessionHasNoErrors();

        $group = DonationCauseGroup::where('slug', 'seasonal-campaigns')->firstOrFail();
        $this->assertSame('Seasonal Campaigns', $group->name);
        $this->assertSame('Time-bound giving opportunities.', $group->description);
        $this->assertSame(75, $group->display_order);

        $causePayload = [
            'name' => 'Winter support campaign',
            'description' => 'Provide visitor-ready winter support to communities.',
            'purpose_key' => '',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Winter Support Fund',
            'destination_category_uuid' => '',
            'destination_page_uuid' => '',
            'image_media_uuid' => '',
            'display_order' => 75,
            'icon_key' => 'emergency',
            'donation_cause_group_id' => $group->id,
        ];
        $this->actingAs($admin, 'admin')
            ->post(route('donationType.store'), $causePayload)
            ->assertSessionHasNoErrors();

        $cause = DonationType::where('name', 'Winter support campaign')->firstOrFail();
        $this->assertSame($group->id, $cause->donation_cause_group_id);
        $this->actingAs($admin, 'admin')
            ->get(route('donationType.edit', $cause->id))
            ->assertOk()
            ->assertJsonPath('data.donation_cause_group_id', $group->id);

        $this->actingAs($admin, 'admin')->put(route('donationType.group.update', $group), [
            'group_name' => 'Seasonal & Winter Campaigns',
            'group_description' => 'Seasonal giving and winter response.',
            'group_display_order' => 85,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Seasonal & Winter Campaigns', $group->fresh()->name);
        $this->assertSame('seasonal-campaigns', $group->fresh()->slug);
        $this->assertSame(85, $group->fresh()->display_order);

        $this->actingAs($admin, 'admin')
            ->put(route('donationType.group.status', $group))
            ->assertSessionHasNoErrors();
        $this->assertFalse((bool) $group->fresh()->status);

        $cause->delete();
        $this->actingAs($admin, 'admin')
            ->delete(route('donationType.group.destroy', $group))
            ->assertSessionHasErrorsIn('donationCauseGroup', ['donation_cause_group']);
        $this->assertDatabaseHas('donation_cause_groups', ['id' => $group->id]);

        $cause->forceDelete();
        $this->actingAs($admin, 'admin')
            ->delete(route('donationType.group.destroy', $group))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('donation_cause_groups', ['id' => $group->id]);
    }

    public function test_groups_are_translatable_and_routes_reuse_donation_cause_permissions(): void
    {
        $group = $this->group('Community Programs', 'community-programs', 200);
        $row = app(TranslationCenterService::class)
            ->rows('en', 'bn')
            ->first(fn (array $candidate): bool => ($candidate['identity']['model'] ?? null) === 'donation_cause_group'
                && ($candidate['identity']['source_id'] ?? null) === $group->id
                && ($candidate['identity']['field'] ?? null) === 'name');

        $this->assertNotNull($row);
        $this->assertSame(['donationType.create'], AdminPermissionRegistry::capabilitiesForRoute('donationType.group.store'));
        $this->assertSame(['donationType.edit'], AdminPermissionRegistry::capabilitiesForRoute('donationType.group.update'));
        $this->assertSame(['donationType.status'], AdminPermissionRegistry::capabilitiesForRoute('donationType.group.status'));
        $this->assertSame(['donationType.destroy'], AdminPermissionRegistry::capabilitiesForRoute('donationType.group.destroy'));
    }

    private function group(
        string $name,
        string $slug,
        int $displayOrder,
        bool $status = true
    ): DonationCauseGroup {
        return DonationCauseGroup::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'description' => 'Browse ' . $name . ' causes.',
            'slug' => $slug,
            'display_order' => $displayOrder,
            'status' => $status,
        ]);
    }

    private function cause(
        string $name,
        string $slug,
        ?DonationCauseGroup $group = null,
        int $displayOrder = 10
    ): DonationType {
        return DonationType::create([
            'name' => $name,
            'slug' => $slug,
            'description' => 'Visitor-ready wording for ' . $name . '.',
            'destination_type' => 'restricted_fund',
            'destination_name' => $name . ' Fund',
            'display_order' => $displayOrder,
            'donation_cause_group_id' => $group?->id,
            'status' => true,
        ]);
    }

    private function makeAdmin(): Admin
    {
        $menu = AuthMenu::firstOrCreate([
            'link' => 'donationType.index',
        ], [
            'name' => 'Donation Causes',
            'status' => 1,
        ]);
        $actions = collect([
            ['name' => 'Create donation causes', 'link' => 'donationType.create'],
            ['name' => 'Edit donation causes', 'link' => 'donationType.edit'],
            ['name' => 'Publish donation causes', 'link' => 'donationType.status'],
            ['name' => 'Delete donation causes', 'link' => 'donationType.destroy'],
        ])->map(fn (array $attributes) => MenuAction::firstOrCreate([
            'link' => $attributes['link'],
        ], $attributes + [
            'auth_menu_id' => $menu->id,
            'status' => 1,
        ]));
        $role = Role::create([
            'name' => 'Donation cause group editor ' . Str::random(8),
            'permission' => (string) $menu->id,
            'actionPermission' => $actions->pluck('id')->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Donation Group QA',
            'username' => 'donation-group-qa-' . Str::random(8),
            'email' => 'donation-group-qa-' . Str::random(8) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
