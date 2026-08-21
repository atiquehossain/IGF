<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\ContactMessage;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\Sponsorship;
use App\Models\Volunteer;
use App\Models\VolunteerCause;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnquiryWorkflowIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_assign_and_progress_each_public_enquiry_type(): void
    {
        $this->seed();
        $role = $this->makeOwnerRole();
        $owner = $this->makeAdmin('Owner', $role);
        $assignee = $this->makeAdmin('Programme lead', $role);
        $this->actingAs($owner, 'admin');

        $sponsorship = Sponsorship::create([
            'name' => 'Sponsor One',
            'email' => 'sponsor@example.test',
            'number_of_children' => 1,
            'contribution_interval' => 'monthly',
            'sponsorship_amount' => 1500,
            'transaction_id' => 'SPONSOR-1',
        ]);
        $volunteer = Volunteer::create(['name' => 'Volunteer One', 'email' => 'volunteer@example.test']);
        $message = ContactMessage::create(['first_name' => 'Visitor', 'email' => 'visitor@example.test', 'message' => 'Please call me.']);

        $payload = [
            'workflow_status' => 'contacted',
            'assigned_to' => $assignee->id,
            'internal_notes' => 'Called and agreed the next step.',
            'follow_up_at' => '2026-08-22 10:30:00',
        ];

        $this->put(route('sponsorships.workflow', $sponsorship), $payload)->assertRedirect();
        $this->put(route('volunteer.workflow', $volunteer), $payload)->assertRedirect();
        $this->put(route('contact-message.workflow', $message), $payload)->assertRedirect();

        foreach ([$sponsorship, $volunteer, $message] as $record) {
            $record->refresh();
            $this->assertSame('contacted', $record->workflow_status);
            $this->assertSame($assignee->id, $record->assigned_to);
            $this->assertSame('Called and agreed the next step.', $record->internal_notes);
            $this->assertNull($record->resolved_at);
        }

        $this->put(route('contact-message.workflow', $message), array_merge($payload, ['workflow_status' => 'completed']))
            ->assertRedirect();
        $this->assertNotNull($message->fresh()->resolved_at);
    }

    public function test_volunteer_filters_and_export_use_the_same_search_and_status(): void
    {
        $this->seed();
        $role = $this->makeOwnerRole();
        $owner = $this->makeAdmin('Owner', $role);
        $this->actingAs($owner, 'admin');
        $cause = VolunteerCause::create(['name' => 'Education', 'status' => 1]);

        Volunteer::create([
            'name' => 'Matching Applicant',
            'email' => 'match@example.test',
            'cause_id' => $cause->id,
            'workflow_status' => 'in_progress',
        ]);
        Volunteer::create([
            'name' => 'Other Applicant',
            'email' => 'other@example.test',
            'cause_id' => $cause->id,
            'workflow_status' => 'new',
        ]);

        $filters = [
            'workflow_status' => 'in_progress',
            'from_date' => '2000-01-01',
            'to_date' => '2030-01-01',
        ];

        $this->post(route('volunteer.search'), ['search' => 'match@example.test'])
            ->assertRedirect(route('volunteer.index'));
        $this->get(route('volunteer.index', $filters))
            ->assertOk()
            ->assertSee('Matching Applicant')
            ->assertDontSee('Other Applicant');

        $export = $this->get(route('volunteer.export.excel', $filters));
        $export->assertOk();
        $content = $export->streamedContent();
        $this->assertStringContainsString('Matching Applicant', $content);
        $this->assertStringNotContainsString('Other Applicant', $content);
    }

    private function makeOwnerRole(): Role
    {
        return Role::create([
            'name' => 'Workflow test owner',
            'permission' => AuthMenu::query()->where('status', 1)->pluck('id')->implode(','),
            'actionPermission' => MenuAction::query()->where('status', 1)->pluck('id')->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);
    }

    private function makeAdmin(string $name, Role $role): Admin
    {
        return Admin::create([
            'name' => $name,
            'username' => str($name)->slug() . '-' . uniqid(),
            'email' => str($name)->slug() . '-' . uniqid() . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);
    }
}
