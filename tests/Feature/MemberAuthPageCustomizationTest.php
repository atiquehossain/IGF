<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberAuthPageCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_auth_page_metadata_uses_localized_customizer_copy_and_branding(): void
    {
        $this->putSetting('branding', 'site_name', 'Community Impact Trust');
        $this->putSetting('member_area', 'title', 'Partner sign in');
        $this->putSetting('member_area', 'introduction', 'Use your approved partner account.');
        $this->putSetting('member_area', 'registration_title', 'Request partner access');
        $this->putSetting('member_area', 'registration_introduction', 'Tell us who you represent.');
        $this->putSetting('member_area', 'two_factor_title', 'Protected partner sign in');
        $this->putSetting('member_area', 'two_factor_introduction', 'Confirm your protected account details.');
        $this->putSetting('member_area', 'verification_code_title', 'Enter the partner security code');
        $this->putSetting('member_area', 'verification_code_body', 'Use the current code from your authenticator.');

        $this->get(route('showLogin'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/login')
                ->where('title', 'Partner sign in')
                ->where('meta_tag.meta_title', 'Partner sign in | Community Impact Trust')
                ->where('meta_tag.meta_description', 'Use your approved partner account.')
                ->where('meta_tag.robots', 'noindex,nofollow,noarchive'));

        $this->get(route('register.form'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/register')
                ->where('title', 'Request partner access')
                ->where('meta_tag.meta_title', 'Request partner access | Community Impact Trust')
                ->where('meta_tag.meta_description', 'Tell us who you represent.')
                ->where('meta_tag.robots', 'noindex,nofollow,noarchive'));

        $this->get(route('login2fa'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/login-2fa')
                ->where('title', 'Protected partner sign in')
                ->where('meta_tag.meta_title', 'Protected partner sign in | Community Impact Trust')
                ->where('meta_tag.meta_description', 'Confirm your protected account details.')
                ->where('meta_tag.robots', 'noindex,nofollow,noarchive'));

        $this->withSession(['data' => [
            'access_token' => str_repeat('a', 64),
            'enrollment_required' => false,
        ]])->get(route('login2fa.verify'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/login-2fa-verify')
                ->where('title', 'Enter the partner security code')
                ->where('meta_tag.meta_title', 'Enter the partner security code | Community Impact Trust')
                ->where('meta_tag.meta_description', 'Use the current code from your authenticator.')
                ->where('meta_tag.robots', 'noindex,nofollow,noarchive'));
    }

    public function test_member_auth_feedback_uses_managed_copy_without_changing_security_decisions(): void
    {
        $this->putSetting('member_area', 'invalid_credentials_message', '<b>Those details do not match.</b>');
        $this->putSetting('member_area', 'two_factor_required_message', 'Continue in the protected sign-in flow.');
        $this->putSetting('member_area', 'login_success_message', 'Welcome to the partner area.');
        $this->putSetting('member_area', 'verification_restart_message', 'Begin a fresh partner verification.');
        $this->putSetting('member_area', 'verification_expired_message', 'That partner verification has expired.');
        $this->putSetting('member_area', 'logout_success_message', 'You have left the partner area.');
        $this->putSetting('member_area', 'password_incorrect_message', 'The existing partner password is not correct.');
        $this->putSetting('member_area', 'password_current_field_error', 'Re-enter the existing partner password.');

        $this->post(route('login'), [
            'phone_no' => '01700000001',
            'password' => 'not-the-password',
        ])->assertSessionHas('message.text', 'Those details do not match.');

        $twoFactorUser = $this->member('01700000002', 'two-factor@example.test', true);
        $this->post(route('login'), [
            'phone_no' => $twoFactorUser->phone_no,
            'password' => 'Strong-Member-Password!',
        ])->assertRedirect(route('login2fa'))
            ->assertSessionHas('message.text', 'Continue in the protected sign-in flow.');
        $this->assertGuest();

        $this->get(route('login2fa.verify'))
            ->assertRedirect(route('login2fa'))
            ->assertSessionHas('message.text', 'Begin a fresh partner verification.');

        $this->from(route('login2fa.verify'))->post(route('login2fa.verify.perform'), [
            'access_token' => str_repeat('f', 64),
            'code' => '123456',
        ])->assertSessionHas('message.text', 'That partner verification has expired.');

        $member = $this->member('01700000003', 'member@example.test');
        $this->post(route('login'), [
            'phone_no' => $member->phone_no,
            'password' => 'Strong-Member-Password!',
        ])->assertRedirect(route('frontend.home'))
            ->assertSessionHas('message.text', 'Welcome to the partner area.');
        $this->assertAuthenticatedAs($member);

        $this->from(route('showLogin'))->post(route('change.password'), [
            'current_password' => 'wrong-password',
            'password' => 'New-Strong-Member-Password!',
            'password_confirmation' => 'New-Strong-Member-Password!',
        ])->assertSessionHasErrors([
            'current_password' => 'Re-enter the existing partner password.',
        ])->assertSessionHas('message.text', 'The existing partner password is not correct.');

        $this->post(route('front.logout'))
            ->assertRedirect(route('frontend.home'))
            ->assertSessionHas('message.text', 'You have left the partner area.');
    }

    public function test_ineligible_member_session_uses_managed_account_copy_and_is_still_revoked(): void
    {
        $this->putSetting('member_area', 'account_unavailable_message', 'Your partner access is awaiting review.');
        $member = $this->member('01700000004', 'inactive@example.test');
        $this->actingAs($member);
        $member->update(['status' => 0]);

        $this->get(route('frontend.home'))
            ->assertRedirect(route('showLogin'))
            ->assertSessionHas('message.text', 'Your partner access is awaiting review.');
        $this->assertGuest();
    }

    private function putSetting(string $group, string $key, string $value): void
    {
        SiteSetting::create([
            'group' => $group,
            'key' => $key,
            'locale' => 'en',
            'value' => $value,
            'type' => 'text',
            'is_public' => true,
        ]);
    }

    private function member(string $phone, string $email, bool $twoFactor = false): User
    {
        return User::create([
            'name' => 'Member Copy Tester',
            'phone_no' => $phone,
            'email' => $email,
            'provider_type' => 'local',
            'status' => 1,
            'is_approved' => 1,
            'password' => Hash::make('Strong-Member-Password!'),
            'google2fa_secret' => $twoFactor
                ? app('pragmarx.google2fa')->generateSecretKey()
                : null,
        ]);
    }
}
