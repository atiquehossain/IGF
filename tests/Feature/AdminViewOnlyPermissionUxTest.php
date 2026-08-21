<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\ContactMessage;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\Role;
use App\Models\Sponsorship;
use App\Models\Subscriber;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminViewOnlyPermissionUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_translation_viewer_gets_guidance_and_no_mutation_controls(): void
    {
        $this->makeEnglishPage();
        $viewer = $this->makeAdmin('Translation viewer', $this->makeRole(
            'Translation viewer role',
            ['translations.index']
        ));

        $response = $this->actingAs($viewer, 'admin')->get(route('translations.index', [
            'search' => 'Our permission test page',
        ]));

        $response
            ->assertOk()
            ->assertSee('Read-only access.')
            ->assertSee('Our permission test page')
            ->assertSee('readonly', false)
            ->assertSee('<div id="translation-form" data-read-only="true">', false)
            ->assertDontSee('<form id="translation-form"', false)
            ->assertDontSee(route('translations.toggle'), false)
            ->assertDontSee('Save translations')
            ->assertDontSee('Copy English into this cell');
    }

    public function test_translation_edit_and_visibility_capabilities_are_independent(): void
    {
        $this->makeEnglishPage();

        $editor = $this->makeAdmin('Translation editor', $this->makeRole(
            'Translation editor role',
            ['translations.index'],
            ['translations.edit']
        ));

        $this->actingAs($editor, 'admin')
            ->get(route('translations.index', ['search' => 'Our permission test page']))
            ->assertOk()
            ->assertSee('Language visibility is read only.')
            ->assertSee('<form id="translation-form" method="POST"', false)
            ->assertSee('Save translations')
            ->assertDontSee(route('translations.toggle'), false);

        $publisher = $this->makeAdmin('Translation publisher', $this->makeRole(
            'Translation publisher role',
            ['translations.index'],
            ['translations.status']
        ));

        $this->actingAs($publisher, 'admin')
            ->get(route('translations.index', ['search' => 'Our permission test page']))
            ->assertOk()
            ->assertSee('Translation wording is read only.')
            ->assertSee(route('translations.toggle'), false)
            ->assertSee('readonly', false)
            ->assertSee('<div id="translation-form" data-read-only="true">', false)
            ->assertDontSee('<form id="translation-form"', false)
            ->assertDontSee('Save translations');
    }

    public function test_enquiry_viewer_sees_workflow_details_without_update_forms(): void
    {
        $viewer = $this->makeAdmin('Enquiry viewer', $this->makeRole(
            'Enquiry viewer role',
            ['volunteer.index', 'sponsorships.index', 'contact-message.index']
        ));
        [$volunteer, $sponsorship, $contact] = $this->makeEnquiries($viewer);

        $journeys = [
            ['volunteer.index', 'volunteer.workflow', $volunteer, 'Volunteer permission record', 'Volunteer notes stay visible.'],
            ['sponsorships.index', 'sponsorships.workflow', $sponsorship, 'Sponsor permission record', 'Sponsor notes stay visible.'],
            ['contact-message.index', 'contact-message.workflow', $contact, 'Contact permission record', 'Contact notes stay visible.'],
        ];

        foreach ($journeys as [$indexRoute, $workflowRoute, $record, $visibleText, $notes]) {
            $this->actingAs($viewer, 'admin')
                ->get(route($indexRoute))
                ->assertOk()
                ->assertSee($visibleText)
                ->assertSee($notes)
                ->assertSee('Workflow is read only for your role.')
                ->assertSee('View workflow')
                ->assertDontSee('Manage enquiry')
                ->assertDontSee('Save workflow')
                ->assertDontSee(route($workflowRoute, $record), false);
        }
    }

    public function test_enquiry_editors_keep_the_workflow_forms_for_each_enquiry_type(): void
    {
        $editor = $this->makeAdmin('Enquiry editor', $this->makeRole(
            'Enquiry editor role',
            ['volunteer.index', 'sponsorships.index', 'contact-message.index'],
            ['volunteer.edit', 'sponsorships.edit', 'contact-message.edit']
        ));
        [$volunteer, $sponsorship, $contact] = $this->makeEnquiries($editor);

        foreach ([
            ['volunteer.index', 'volunteer.workflow', $volunteer],
            ['sponsorships.index', 'sponsorships.workflow', $sponsorship],
            ['contact-message.index', 'contact-message.workflow', $contact],
        ] as [$indexRoute, $workflowRoute, $record]) {
            $this->actingAs($editor, 'admin')
                ->get(route($indexRoute))
                ->assertOk()
                ->assertSee('Manage enquiry')
                ->assertSee('Save workflow')
                ->assertSee(route($workflowRoute, $record), false)
                ->assertDontSee('Workflow is read only for your role.');
        }
    }

    public function test_subscriber_composer_is_only_rendered_with_email_capability(): void
    {
        $subscriber = Subscriber::create([
            'uuid' => (string) Str::uuid(),
            'email' => 'permission-subscriber@example.test',
        ]);

        $viewer = $this->makeAdmin('Subscriber viewer', $this->makeRole(
            'Subscriber viewer role',
            ['subscriber.index']
        ));

        $this->actingAs($viewer, 'admin')
            ->get(route('subscriber.index'))
            ->assertOk()
            ->assertSee('permission-subscriber@example.test')
            ->assertSee('Read-only email list.')
            ->assertDontSee('send-email-btn', false)
            ->assertDontSee('class="btn btn-sm btn-danger trash"', false)
            ->assertDontSee('Send Email to Subscriber')
            ->assertDontSee(route('subscriber.sendEmail'), false);

        $sender = $this->makeAdmin('Subscriber sender', $this->makeRole(
            'Subscriber sender role',
            ['subscriber.index'],
            ['subscriber.sendEmail']
        ));

        $this->actingAs($sender, 'admin')
            ->get(route('subscriber.index'))
            ->assertOk()
            ->assertSee('send-email-btn', false)
            ->assertSee('Send Email to Subscriber')
            ->assertSee(route('subscriber.sendEmail'), false)
            ->assertDontSee(route('subscriber.destroy', $subscriber->id), false)
            ->assertDontSee('Read-only email list.');

        $deleter = $this->makeAdmin('Subscriber deleter', $this->makeRole(
            'Subscriber deleter role',
            ['subscriber.index'],
            ['subscriber.destroy']
        ));

        $this->actingAs($deleter, 'admin')
            ->get(route('subscriber.index'))
            ->assertOk()
            ->assertSee('class="btn btn-sm btn-danger trash"', false)
            ->assertSee(route('subscriber.destroy', Subscriber::firstOrFail()->id), false)
            ->assertDontSee('send-email-btn', false)
            ->assertDontSee('Send Email to Subscriber')
            ->assertDontSee('Read-only email list.');
    }

    public function test_member_application_search_and_review_controls_follow_the_edit_capability(): void
    {
        $applicant = User::create([
            'name' => 'Pending Community Member',
            'phone_no' => '01711111111',
            'email' => 'pending-member@example.test',
            'org' => 'Community Network',
            'designation' => 'Coordinator',
            'password' => bcrypt('test-password'),
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 0,
        ]);
        User::create([
            'name' => 'Different Applicant',
            'phone_no' => '01822222222',
            'email' => 'different@example.test',
            'org' => 'Another Group',
            'designation' => 'Volunteer',
            'password' => bcrypt('test-password'),
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 0,
        ]);
        $viewer = $this->makeAdmin('Application viewer', $this->makeRole(
            'Application viewer role',
            ['user-approval.index']
        ));

        $this->actingAs($viewer, 'admin')
            ->post(route('user-approval.search'), ['search' => '01711111111'])
            ->assertRedirect(route('user-approval.index'));
        $this->get(route('user-approval.index'))
            ->assertOk()
            ->assertSee('form action="'.route('user-approval.search').'" method="post" role="search"', false)
            ->assertSee($applicant->name)
            ->assertDontSee('Different Applicant')
            ->assertSee('Read-only access.')
            ->assertSee('View only')
            ->assertDontSee(route('user-approval.update.approve', $applicant->id), false)
            ->assertDontSee(route('user-approval.update.reject', $applicant->id), false);

        $reviewer = $this->makeAdmin('Application reviewer', $this->makeRole(
            'Application reviewer role',
            ['user-approval.index'],
            ['user-approval.edit']
        ));
        $this->actingAs($reviewer, 'admin')
            ->post(route('user-approval.search'), ['search' => 'pending-member@example.test'])
            ->assertRedirect(route('user-approval.index'));
        $this->get(route('user-approval.index'))
            ->assertOk()
            ->assertSee(route('user-approval.update.approve', $applicant->id), false)
            ->assertSee(route('user-approval.update.reject', $applicant->id), false)
            ->assertDontSee('Read-only access.');

        $this->actingAs($reviewer, 'admin')
            ->put(route('user-approval.update.approve', $applicant->id))
            ->assertRedirect()
            ->assertSessionHas('message', 'Member application approved successfully.');
        $this->assertSame(1, $applicant->fresh()->is_approved);

        $this->actingAs($reviewer, 'admin')
            ->put(route('user-approval.update.reject', $applicant->id))
            ->assertRedirect()
            ->assertSessionHas('message', 'Member application rejected.');
        $this->assertSame(2, $applicant->fresh()->is_approved);

        $this->actingAs($viewer, 'admin')
            ->get(route('user-approval.show', 999999))
            ->assertNotFound();
    }

    private function makeRole(string $name, array $menuLinks, array $actionLinks = []): Role
    {
        $menuIds = AuthMenu::query()
            ->whereIn('link', $menuLinks)
            ->where('status', 1)
            ->pluck('id');
        $actionIds = MenuAction::query()
            ->whereIn('link', $actionLinks)
            ->where('status', 1)
            ->pluck('id');

        $this->assertCount(count($menuLinks), $menuIds, 'Every requested view capability must exist.');
        $this->assertCount(count($actionLinks), $actionIds, 'Every requested action capability must exist.');

        return Role::create([
            'name' => $name,
            'permission' => $menuIds->implode(','),
            'actionPermission' => $actionIds->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);
    }

    private function makeAdmin(string $name, Role $role): Admin
    {
        $slug = Str::slug($name) . '-' . Str::lower(Str::random(6));

        return Admin::create([
            'name' => $name,
            'username' => $slug,
            'email' => $slug . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);
    }

    private function makeEnglishPage(): Page
    {
        return Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Our permission test page',
            'sub_title' => 'Permission-safe translations',
            'slug' => 'permission-test-page',
            'description' => '<p>Translation permissions must match visible controls.</p>',
            'language' => 'en',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now(),
        ]);
    }

    private function makeEnquiries(Admin $owner): array
    {
        $workflow = [
            'workflow_status' => 'in_progress',
            'assigned_to' => $owner->id,
            'follow_up_at' => now()->addDay(),
        ];

        $volunteer = Volunteer::create(array_merge($workflow, [
            'name' => 'Volunteer permission record',
            'email' => 'workflow-volunteer@example.test',
            'internal_notes' => 'Volunteer notes stay visible.',
        ]));
        $sponsorship = Sponsorship::create(array_merge($workflow, [
            'name' => 'Sponsor permission record',
            'email' => 'workflow-sponsor@example.test',
            'number_of_children' => 1,
            'contribution_interval' => 'monthly',
            'sponsorship_amount' => 1500,
            'transaction_id' => 'PERMISSION-SPONSOR',
            'internal_notes' => 'Sponsor notes stay visible.',
        ]));
        $contact = ContactMessage::create(array_merge($workflow, [
            'first_name' => 'Contact permission record',
            'email' => 'workflow-contact@example.test',
            'message' => 'Please contact me about your work.',
            'internal_notes' => 'Contact notes stay visible.',
        ]));

        return [$volunteer, $sponsorship, $contact];
    }
}
