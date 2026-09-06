<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\District;
use App\Models\Division;
use App\Models\Upazila;
use App\Models\Volunteer;
use App\Models\VolunteerCause;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PublicSubmissionFeedbackIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_persistence_failure_returns_form_error_instead_of_false_success(): void
    {
        ContactMessage::creating(static function (): void {
            throw new RuntimeException('Simulated database failure.');
        });

        $this->from('/contact-us')
            ->post(route('frontend.send.sms'), [
                'first_name' => 'Test Visitor',
                'email' => 'visitor@example.test',
                'phone' => '+8801700000000',
                'message' => 'Please contact me.',
            ])
            ->assertRedirect('/contact-us')
            ->assertSessionHasErrors('submission')
            ->assertSessionMissing('message');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_volunteer_persistence_failure_returns_form_error_instead_of_false_success(): void
    {
        $cause = VolunteerCause::create([
            'name' => 'Teaching',
            'description' => 'Support learners.',
            'status' => 1,
        ]);
        $division = Division::create(['name' => 'Failure Test Division', 'status' => true]);
        $district = District::create([
            'name' => 'Failure Test District',
            'division_id' => $division->id,
            'status' => true,
        ]);
        $upazila = Upazila::create([
            'name' => 'Failure Test Upazila',
            'district_id' => $district->id,
            'status' => true,
        ]);
        Volunteer::creating(static function (): void {
            throw new RuntimeException('Simulated database failure.');
        });

        $this->from('/volunteer/register')
            ->post(route('frontend.volunteer_registration.store'), [
                'name' => 'Test Volunteer',
                'institution' => 'Community Group',
                'email' => 'volunteer@example.test',
                'phone' => '+8801800000000',
                'address' => 'Dhaka',
                'cause_id' => $cause->id,
                'sex' => 'male',
                'date_of_birth' => '1995-04-10',
                'division_id' => $division->id,
                'district_id' => $district->id,
                'upazila_id' => $upazila->id,
                'occupation' => 'service',
                'education_level' => 'masters_equivalent',
                'blood_group' => 'O+',
                'emergency_response_training' => false,
                'skill' => 'creative_writing',
                'consent' => true,
            ])
            ->assertRedirect('/volunteer/register')
            ->assertSessionHasErrors('registration')
            ->assertSessionMissing('success');

        $this->assertDatabaseCount('volunteers', 0);
    }
}
