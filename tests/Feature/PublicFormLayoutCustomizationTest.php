<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\SiteSetting;
use App\Models\Sponsorship;
use App\Models\Volunteer;
use App\Models\VolunteerCause;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicFormLayoutCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_phone_visibility_and_requirement_follow_the_safe_admin_layout(): void
    {
        $this->storeLayout('contact_page', [
            ['key' => 'phone', 'enabled' => true, 'required' => true],
            ['key' => 'first_name', 'enabled' => true, 'required' => true],
            ['key' => 'email', 'enabled' => true, 'required' => true],
            ['key' => 'message', 'enabled' => true, 'required' => true],
        ]);

        $payload = [
            'first_name' => 'Client Visitor',
            'email' => 'visitor@example.test',
            'message' => 'Please contact me.',
        ];

        $this->post(route('frontend.send.sms'), $payload)
            ->assertSessionHasErrors('phone');
        $this->assertSame(0, ContactMessage::query()->count());

        $this->storeLayout('contact_page', [
            ['key' => 'message', 'enabled' => true, 'required' => true],
            ['key' => 'phone', 'enabled' => false, 'required' => true],
            ['key' => 'email', 'enabled' => true, 'required' => true],
            ['key' => 'first_name', 'enabled' => true, 'required' => true],
        ]);

        $this->post(route('frontend.send.sms'), $payload)->assertSessionHasNoErrors();

        $message = ContactMessage::query()->firstOrFail();
        $this->assertNull($message->phone);
        $this->assertSame('Please contact me.', $message->message);
    }

    public function test_sponsor_optional_fields_can_be_hidden_without_weakening_core_fields(): void
    {
        Mail::fake();
        $this->storeLayout('sponsor_page', [
            ['key' => 'email', 'enabled' => false, 'required' => false],
            ['key' => 'phone', 'enabled' => false, 'required' => true],
            ['key' => 'address', 'enabled' => false, 'required' => true],
            ['key' => 'name', 'enabled' => false, 'required' => false],
            ['key' => 'number_of_children', 'enabled' => false, 'required' => false],
            ['key' => 'contribution_interval', 'enabled' => false, 'required' => false],
        ]);

        $this->postJson(route('frontend.sponsorship.store'), [
            'name' => 'Community Sponsor',
            'email' => 'sponsor@example.test',
            'phone' => '+8801700000000',
            'address' => 'Should be discarded',
            'number_of_children' => 2,
            'contribution_interval' => 'monthly',
            'sponsorshipAmount' => 1,
        ])->assertOk()->assertJson(['status' => true]);

        $sponsorship = Sponsorship::query()->firstOrFail();
        $this->assertNull($sponsorship->phone);
        $this->assertNull($sponsorship->address);
        $this->assertSame('Community Sponsor', $sponsorship->name);
        $this->assertSame('sponsor@example.test', $sponsorship->email);
    }

    public function test_volunteer_profile_fields_can_be_hidden_but_identity_and_cause_stay_required(): void
    {
        Mail::fake();
        $cause = VolunteerCause::query()->create([
            'name' => 'Education',
            'description' => 'Support learning programs.',
            'status' => 1,
        ]);
        $this->storeLayout('volunteer_page', [
            ['key' => 'cause_id', 'enabled' => false, 'required' => false],
            ['key' => 'address', 'enabled' => false, 'required' => true],
            ['key' => 'phone', 'enabled' => false, 'required' => true],
            ['key' => 'institution', 'enabled' => false, 'required' => true],
            ['key' => 'email', 'enabled' => false, 'required' => false],
            ['key' => 'name', 'enabled' => false, 'required' => false],
        ]);

        $this->post(route('frontend.volunteer_registration.store'), [
            'name' => 'Volunteer One',
            'email' => 'volunteer@example.test',
            'institution' => 'Should be discarded',
            'phone' => '+8801700000000',
            'address' => 'Should be discarded',
            'cause_id' => $cause->id,
        ])->assertSessionHasNoErrors();

        $volunteer = Volunteer::query()->firstOrFail();
        $this->assertNull($volunteer->institution);
        $this->assertNull($volunteer->phone);
        $this->assertNull($volunteer->address);
        $this->assertSame($cause->id, $volunteer->cause_id);

        $this->post(route('frontend.volunteer_registration.store'), [
            'name' => 'Volunteer Two',
            'email' => 'volunteer-two@example.test',
        ])->assertSessionHasErrors('cause_id');
    }

    private function storeLayout(string $group, array $layout): void
    {
        SiteSetting::query()->updateOrCreate(
            ['group' => $group, 'key' => 'form_fields', 'locale' => '*'],
            [
                'value' => json_encode($layout, JSON_THROW_ON_ERROR),
                'type' => 'json',
                'is_public' => true,
            ],
        );
    }
}
