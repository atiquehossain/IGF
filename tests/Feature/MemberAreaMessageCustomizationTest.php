<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\TranslationLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberAreaMessageCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_nontechnical_editor_copy_is_used_for_member_login_feedback(): void
    {
        $this->setting('invalid_credentials_message', 'Please check your secure sign-in details.');
        $this->setting('login_success_message', 'Welcome back to the member community.');

        $this->from(route('showLogin'))->post(route('login'), [
            'phone_no' => '01099999999',
            'password' => 'not-the-password',
        ])->assertSessionHas('message', [
            'type' => 'error',
            'text' => 'Please check your secure sign-in details.',
        ]);

        $member = User::query()->create([
            'name' => 'Approved Member',
            'phone_no' => '01700000071',
            'email' => 'member-copy@example.test',
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Strong-Member-Password!'),
        ]);

        $this->post(route('login'), [
            'phone_no' => $member->phone_no,
            'password' => 'Strong-Member-Password!',
        ])->assertRedirect(route('frontend.home'))
            ->assertSessionHas('message.text', 'Welcome back to the member community.');
    }

    public function test_bangla_member_security_feedback_has_a_managed_localized_default(): void
    {
        TranslationLocale::query()->whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);

        $response = $this->withSession(['locale' => 'bn'])
            ->from('/login?lang=bn')
            ->post(route('login'), [
                'phone_no' => '01099999998',
                'password' => 'not-the-password',
            ]);

        $response->assertSessionHas('message.text', 'সাইন-ইনের তথ্য সঠিক নয় অথবা এই অ্যাকাউন্টটি বর্তমানে ব্যবহারযোগ্য নয়।');
    }

    private function setting(string $key, string $value): void
    {
        SiteSetting::query()->create([
            'group' => 'member_area',
            'key' => $key,
            'locale' => 'en',
            'value' => $value,
            'type' => 'text',
            'is_public' => true,
        ]);
    }
}
