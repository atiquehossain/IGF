<?php

namespace Tests\Unit;

use App\Support\AdminUi;
use Tests\TestCase;

class AdminUiTranslationTest extends TestCase
{
    public function test_admin_copy_uses_bangla_and_supports_plain_text_replacements(): void
    {
        app()->setLocale('bn');

        $label = AdminUi::text('shell.account_menu', [
            'name' => '<strong>সম্পাদক</strong><img src=x onerror=alert(1)>',
        ]);

        $this->assertSame('সম্পাদক-এর অ্যাকাউন্ট মেনু খুলুন', $label);
        $this->assertStringNotContainsString('<', $label);
        $this->assertStringNotContainsString('onerror', $label);
        $this->assertSame('পরিবর্তন সংরক্ষণ', AdminUi::text('builder.save_changes'));
    }

    public function test_unsupported_locale_falls_back_to_english_with_nested_locale_config(): void
    {
        config()->set('localization.editor_locales', [
            'en' => ['name' => 'English'],
            'bn' => ['name' => 'Bangla'],
        ]);

        $this->assertSame('Save changes', AdminUi::text('builder.save_changes', [], 'fr'));
        $this->assertSame('New page', AdminUi::text('shell.new_page', [], 'fr'));
    }

    public function test_javascript_dictionary_is_recursively_plain_text_and_has_english_fallback(): void
    {
        $dictionary = AdminUi::section('navigation', 'bn');

        $this->assertSame('নেভিগেশন', $dictionary['title']);
        $this->assertSame('কিছু সমস্যা হয়েছে। আবার চেষ্টা করুন।', $dictionary['messages']['generic_error']);
        $this->assertSame('Header', AdminUi::text('missing.header', [], 'bn'));
        $this->assertSame([], AdminUi::section('../unsafe', 'bn'));
    }

    public function test_transactional_email_editor_copy_is_available_in_english_and_bangla(): void
    {
        $this->assertSame(
            'Email templates',
            AdminUi::text('email_templates.title', [], 'en')
        );
        $this->assertSame(
            'ইমেইল টেমপ্লেট',
            AdminUi::text('email_templates.title', [], 'bn')
        );
        $this->assertSame(
            'নতুন স্বেচ্ছাসেবক নিবন্ধন — টিম বার্তা',
            AdminUi::text(
                'email_templates.templates.volunteer_admin_notification.label',
                [],
                'bn'
            )
        );
        $this->assertSame(
            'প্রতিটি বার্তায় {{confirmation_url}} প্লেসহোল্ডারটি রাখতে হবে।',
            AdminUi::text(
                'email_templates.validation.required_placeholder',
                ['placeholder' => 'confirmation_url'],
                'bn'
            )
        );
    }
}
