<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sign_in_page_renders_complete_english_and_bangla_copy(): void
    {
        $this->get(route('admin.showLogin'))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('<title>Admin | Ignite Global Foundation</title>', false)
            ->assertSee('Administrator sign in')
            ->assertSee('Administrator username')
            ->assertSee('Manage website content, media, search visibility, and administrator access.')
            ->assertSee('aria-label="Administrator sign in"', false)
            ->assertSee('aria-required="true"', false);

        $this->withSession(['locale' => 'bn'])
            ->get(route('admin.showLogin'))
            ->assertOk()
            ->assertSee('<html lang="bn">', false)
            ->assertSee('<title>অ্যাডমিন | ইগনাইট গ্লোবাল ফাউন্ডেশন</title>', false)
            ->assertSee('অ্যাডমিন হিসেবে সাইন ইন করুন')
            ->assertSee('অ্যাডমিন ব্যবহারকারীর নাম')
            ->assertSee('ওয়েবসাইটের কনটেন্ট, মিডিয়া, সার্চে দৃশ্যমানতা')
            ->assertSee('aria-label="অ্যাডমিন হিসেবে সাইন ইন"', false)
            ->assertDontSee('Administrator sign in');
    }

    public function test_bangla_validation_copy_is_linked_to_the_invalid_fields(): void
    {
        $this->withSession(['locale' => 'bn'])
            ->from(route('admin.showLogin'))
            ->post(route('admin.login'), [])
            ->assertRedirect(route('admin.showLogin'))
            ->assertSessionHasErrors([
                'username' => 'অ্যাডমিন ব্যবহারকারীর নাম লিখুন।',
                'password' => 'পাসওয়ার্ড লিখুন।',
            ]);

        $this->get(route('admin.showLogin'))
            ->assertOk()
            ->assertSee('সাইন ইন সফল হয়নি। চিহ্নিত ঘরগুলো ঠিক করে আবার চেষ্টা করুন।')
            ->assertSee('aria-describedby="admin-login-errors"', false)
            ->assertSee('aria-invalid="true" aria-describedby="username-error"', false)
            ->assertSee('id="username-error" role="alert"', false)
            ->assertSee('অ্যাডমিন ব্যবহারকারীর নাম লিখুন।')
            ->assertSee('পাসওয়ার্ড লিখুন।');
    }

    public function test_invalid_credentials_use_the_localized_generic_message(): void
    {
        $this->withSession(['locale' => 'bn'])
            ->post(route('admin.login'), [
                'username' => 'missing-admin',
                'password' => 'not-the-password',
            ])->assertRedirect(route('admin.login'))
            ->assertSessionHas('message', 'দেওয়া পরিচয় তথ্য সঠিক নয়।');

        $this->get(route('admin.showLogin'))
            ->assertOk()
            ->assertSee(
                '<div class="alert alert-danger" role="alert">দেওয়া পরিচয় তথ্য সঠিক নয়।</div>',
                false
            );
    }

    public function test_login_title_uses_the_localized_admin_managed_site_name(): void
    {
        SiteSetting::create([
            'group' => 'branding',
            'key' => 'site_name',
            'locale' => 'bn',
            'value' => 'পরীক্ষামূলক ফাউন্ডেশন',
            'type' => 'text',
            'is_public' => true,
        ]);

        $this->withSession(['locale' => 'bn'])
            ->get(route('admin.showLogin'))
            ->assertOk()
            ->assertSee('<title>অ্যাডমিন | পরীক্ষামূলক ফাউন্ডেশন</title>', false);
    }
}
