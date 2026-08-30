<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\DonationType;
use App\Models\MenuAction;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DirectDonationPurposeRoleIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_operational_cause_can_receive_and_replace_the_direct_page_role(): void
    {
        $this->assertSame(
            'Use for the direct Make a Donation page',
            DonationType::PURPOSE_OPTIONS['direct']
        );
        $this->assertContains('direct', DonationType::PROTECTED_PURPOSE_KEYS);

        $first = $this->readyCause('General community support');
        $replacement = $this->readyCause('Priority community support');
        $editor = $this->adminWith(['donationType.edit']);

        $this->actingAs($editor, 'admin')
            ->put(route('donationType.update'), $this->updatePayload($first, 'direct'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('direct', $first->fresh()->purpose_key);

        $this->put(route('donationType.update'), $this->updatePayload($replacement, 'direct'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($first->fresh()->purpose_key);
        $this->assertSame('direct', $replacement->fresh()->purpose_key);
        $this->assertSame(1, DonationType::query()->where('purpose_key', 'direct')->count());

        $this->put(route('donationType.update'), $this->updatePayload($replacement, ''))
            ->assertRedirect()
            ->assertSessionHasErrors('purpose_key');

        $this->assertSame('direct', $replacement->fresh()->purpose_key);
    }

    public function test_direct_role_cannot_be_claimed_during_creation_or_by_an_unpublished_cause(): void
    {
        $admin = $this->adminWith(['donationType.create', 'donationType.edit']);

        $this->actingAs($admin, 'admin')
            ->post(route('donationType.store'), [
                'name' => 'Direct role during creation',
                'description' => 'Visitor-ready direct donation wording.',
                'purpose_key' => 'direct',
                'destination_type' => 'unrestricted',
                'destination_name' => '',
                'destination_category_uuid' => '',
                'destination_page_uuid' => '',
                'image_media_uuid' => '',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('purpose_key');

        $this->assertDatabaseMissing('donation_types', [
            'name' => 'Direct role during creation',
        ]);

        $draft = DonationType::create([
            'name' => 'Unpublished direct candidate',
            'description' => 'Visitor-ready direct donation wording.',
            'destination_type' => 'unrestricted',
            'status' => 0,
        ]);

        $this->put(route('donationType.update'), $this->updatePayload($draft, 'direct'))
            ->assertRedirect()
            ->assertSessionHasErrors('purpose_key');

        $this->assertNull($draft->fresh()->purpose_key);
        $this->assertFalse((bool) $draft->fresh()->status);
    }

    public function test_direct_role_allows_an_unrestricted_destination_without_weakening_zakat_rules(): void
    {
        $direct = $this->readyCause('Flexible direct support');
        $zakat = $this->readyCause('Unsafe unrestricted Zakat candidate');
        $editor = $this->adminWith(['donationType.edit']);

        $this->actingAs($editor, 'admin')
            ->put(route('donationType.update'), $this->updatePayload($direct, 'direct'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('direct', $direct->fresh()->purpose_key);

        $this->put(route('donationType.update'), $this->updatePayload($zakat, 'zakat'))
            ->assertRedirect()
            ->assertSessionHasErrors('destination_type');
        $this->assertNull($zakat->fresh()->purpose_key);
    }

    public function test_direct_and_zakat_page_causes_cannot_be_unpublished_or_deleted_in_place(): void
    {
        $direct = $this->readyCause('Protected direct destination', 'direct');
        $zakat = DonationType::create([
            'name' => 'Protected Zakat destination',
            'description' => 'Visitor-ready Zakat donation wording.',
            'purpose_key' => 'zakat',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Zakat Fund',
            'status' => 1,
        ]);
        $admin = $this->adminWith(['donationType.status', 'donationType.destroy']);

        $this->actingAs($admin, 'admin');
        foreach ([$direct, $zakat] as $cause) {
            $this->withHeader('X-Requested-With', 'XMLHttpRequest')
                ->putJson(route('donationType.status', $cause->id), ['id' => $cause->id])
                ->assertUnprocessable()
                ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'cannot be unpublished'));

            $this->deleteJson(route('donationType.destroy', $cause->id))
                ->assertUnprocessable()
                ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'cannot be deleted'));

            $this->assertDatabaseHas('donation_types', [
                'id' => $cause->id,
                'purpose_key' => $cause->purpose_key,
                'status' => 1,
                'deleted_at' => null,
            ]);
        }
    }

    private function readyCause(string $name, ?string $purpose = null): DonationType
    {
        return DonationType::create([
            'name' => $name,
            'description' => 'Visitor-ready direct donation wording.',
            'purpose_key' => $purpose,
            'destination_type' => 'unrestricted',
            'status' => 1,
        ]);
    }

    /** @return array<string, mixed> */
    private function updatePayload(DonationType $cause, string $purpose): array
    {
        return [
            'id' => $cause->id,
            'name' => $cause->name,
            'description' => $cause->description,
            'purpose_key' => $purpose,
            'destination_type' => $cause->destination_type,
            'destination_name' => $cause->destination_name ?: '',
            'destination_category_uuid' => $cause->destination_category_uuid ?: '',
            'destination_page_uuid' => $cause->destination_page_uuid ?: '',
            'image_media_uuid' => $cause->image_media_uuid ?: '',
            'display_order' => $cause->display_order,
            'icon_key' => $cause->icon_key ?: '',
            'donation_cause_group_id' => $cause->donation_cause_group_id ?: '',
        ];
    }

    /** @param list<string> $actionLinks */
    private function adminWith(array $actionLinks): Admin
    {
        $menu = AuthMenu::query()->where('link', 'donationType.index')->firstOrFail();
        $actions = MenuAction::query()
            ->whereIn('link', $actionLinks)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();
        $role = Role::create([
            'name' => 'Direct donation role owner ' . Str::random(8),
            'permission' => (string) $menu->id,
            'actionPermission' => implode(',', $actions),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Direct donation administrator',
            'username' => 'direct-donation-' . Str::random(10),
            'email' => 'direct-donation-' . Str::random(10) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);
    }
}
