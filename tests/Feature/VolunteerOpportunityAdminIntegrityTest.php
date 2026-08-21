<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\VolunteerCause;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VolunteerOpportunityAdminIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_only_admin_sees_opportunities_without_mutating_controls(): void
    {
        $opportunity = VolunteerCause::create([
            'name' => 'Community tutoring',
            'description' => 'Support weekly learning sessions.',
            'status' => false,
        ]);
        $admin = $this->makeAdmin([]);

        $this->actingAs($admin, 'admin')->get(route('volunteerCause.index'))
            ->assertOk()
            ->assertSee('Read-only access.')
            ->assertSee('Community tutoring')
            ->assertSee('Draft')
            ->assertSee('View only')
            ->assertDontSee('Save draft')
            ->assertDontSee('data-url="'.route('volunteerCause.edit', $opportunity->id).'"', false)
            ->assertDontSee('data-volunteer-publish', false)
            ->assertDontSee('data-volunteer-delete', false);

        $this->actingAs($admin, 'admin')
            ->put(route('volunteerCause.status', $opportunity->id))
            ->assertForbidden();
    }

    public function test_rendered_controls_match_each_volunteer_opportunity_capability(): void
    {
        $opportunity = VolunteerCause::create(['name' => 'Event support', 'status' => false]);

        $creator = $this->makeAdmin(['volunteerCause.create']);
        $this->actingAs($creator, 'admin')->get(route('volunteerCause.index'))
            ->assertOk()
            ->assertSee('action="'.route('volunteerCause.store').'"', false)
            ->assertSee('data-volunteer-create', false)
            ->assertSee('Save draft')
            ->assertDontSee('data-url="'.route('volunteerCause.edit', $opportunity->id).'"', false)
            ->assertDontSee('data-volunteer-publish', false)
            ->assertDontSee('data-volunteer-delete', false);

        $editor = $this->makeAdmin(['volunteerCause.edit']);
        $this->actingAs($editor, 'admin')->get(route('volunteerCause.index'))
            ->assertOk()
            ->assertSee('data-url="'.route('volunteerCause.edit', $opportunity->id).'"', false)
            ->assertDontSee('Add a volunteer opportunity')
            ->assertDontSee('Save draft')
            ->assertDontSee('data-volunteer-publish', false)
            ->assertDontSee('data-volunteer-delete', false);

        $publisher = $this->makeAdmin(['volunteerCause.status']);
        $this->actingAs($publisher, 'admin')->get(route('volunteerCause.index'))
            ->assertOk()
            ->assertSee('action="'.route('volunteerCause.status', $opportunity->id).'"', false)
            ->assertSee('data-volunteer-publish', false)
            ->assertSee('Publish')
            ->assertDontSee('data-url="'.route('volunteerCause.edit', $opportunity->id).'"', false)
            ->assertDontSee('data-volunteer-delete', false);

        $deleter = $this->makeAdmin(['volunteerCause.destroy']);
        $this->actingAs($deleter, 'admin')->get(route('volunteerCause.index'))
            ->assertOk()
            ->assertSee('action="'.route('volunteerCause.destroy', $opportunity->id).'"', false)
            ->assertSee('data-volunteer-delete', false)
            ->assertDontSee('data-url="'.route('volunteerCause.edit', $opportunity->id).'"', false)
            ->assertDontSee('data-volunteer-publish', false);
    }

    public function test_new_opportunity_stays_draft_until_published_and_can_be_hidden_again(): void
    {
        $admin = $this->makeAdmin(['volunteerCause.create', 'volunteerCause.status']);

        $this->actingAs($admin, 'admin')
            ->from(route('volunteerCause.index'))
            ->post(route('volunteerCause.store'), [
                'name' => 'Youth mentoring',
                'description' => 'Mentor young people twice a month.',
            ])
            ->assertRedirect(route('volunteerCause.index'))
            ->assertSessionHas('message', 'Volunteer opportunity saved as a draft. Publish it when it is ready for the public sign-up form.');

        $opportunity = VolunteerCause::where('name', 'Youth mentoring')->firstOrFail();
        $this->assertFalse((bool) $opportunity->status);

        $this->get(route('frontend.volunteer_registration.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('data.causes', 0));

        $this->from(route('volunteerCause.index'))
            ->put(route('volunteerCause.status', $opportunity->id))
            ->assertRedirect(route('volunteerCause.index'))
            ->assertSessionHas('message', 'Volunteer opportunity published. It now appears on the public sign-up form.');
        $this->assertTrue((bool) $opportunity->fresh()->status);

        $this->get(route('frontend.volunteer_registration.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('data.causes', 1)
                ->where('data.causes.0.id', $opportunity->id)
                ->where('data.causes.0.name', 'Youth mentoring'));

        $this->from(route('volunteerCause.index'))
            ->put(route('volunteerCause.status', $opportunity->id))
            ->assertRedirect(route('volunteerCause.index'))
            ->assertSessionHas('message', 'Volunteer opportunity unpublished. It is now hidden from the public sign-up form.');
        $this->assertFalse((bool) $opportunity->fresh()->status);

        $this->get(route('frontend.volunteer_registration.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('data.causes', 0));
    }

    public function test_editor_can_update_copy_and_deleter_can_move_an_opportunity_to_trash(): void
    {
        $opportunity = VolunteerCause::create(['name' => 'Old wording', 'status' => false]);
        $editor = $this->makeAdmin(['volunteerCause.edit']);

        $this->actingAs($editor, 'admin')->getJson(route('volunteerCause.edit', $opportunity->id))
            ->assertOk()
            ->assertJsonPath('data.name', 'Old wording');

        $this->from(route('volunteerCause.index'))
            ->put(route('volunteerCause.update'), [
                'id' => $opportunity->id,
                'name' => 'Updated wording',
                'description' => 'A clear internal description.',
            ])
            ->assertRedirect(route('volunteerCause.index'));
        $this->assertDatabaseHas('volunteer_causes', [
            'id' => $opportunity->id,
            'name' => 'Updated wording',
            'description' => 'A clear internal description.',
            'status' => false,
        ]);

        $deleter = $this->makeAdmin(['volunteerCause.destroy']);
        $this->actingAs($deleter, 'admin')
            ->from(route('volunteerCause.index'))
            ->delete(route('volunteerCause.destroy', $opportunity->id))
            ->assertRedirect(route('volunteerCause.index'));
        $this->assertSoftDeleted('volunteer_causes', ['id' => $opportunity->id]);
    }

    private function makeAdmin(array $actionLinks): Admin
    {
        $suffix = Str::lower(Str::random(10));
        $menu = AuthMenu::query()->updateOrCreate(
            ['link' => 'volunteerCause.index'],
            ['name' => 'Volunteer Opportunities', 'status' => 1]
        );
        $actions = collect($actionLinks)->unique()->map(function (string $link) use ($menu) {
            return MenuAction::query()->updateOrCreate(
                ['link' => $link],
                [
                    'auth_menu_id' => $menu->id,
                    'name' => Str::headline(str_replace('.', ' ', $link)),
                    'type' => match (true) {
                        str_ends_with($link, '.create') => 1,
                        str_ends_with($link, '.edit') => 2,
                        str_ends_with($link, '.status') => 3,
                        str_ends_with($link, '.destroy') => 4,
                        default => 2,
                    },
                    'status' => 1,
                ]
            );
        });
        $role = Role::create([
            'name' => 'Volunteer opportunities '.$suffix,
            'permission' => (string) $menu->id,
            'actionPermission' => $actions->pluck('id')->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Volunteer QA '.$suffix,
            'username' => 'volunteer-'.$suffix,
            'email' => 'volunteer-'.$suffix.'@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
