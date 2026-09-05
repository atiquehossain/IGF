<?php

namespace Tests\Feature;

use App\Mail\ConfirmNewsletterSubscription;
use App\Mail\TransactionalEmail;
use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\TransactionalEmailTemplate;
use App\Models\VolunteerCause;
use App\Services\SiteSettingService;
use App\Services\TransactionalEmailContentSanitizer;
use App\Services\TransactionalEmailDesignService;
use App\Services\TransactionalEmailTemplateService;
use App\Support\TransactionalEmailTemplateCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransactionalEmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config([
            'mail.from.address' => 'sender@example.test',
            'mail.from.name' => 'Ignite Mailer',
            'transactional-mail.admin_to' => 'operations@example.test',
            'transactional-mail.contact_address' => 'contact@example.test',
            'transactional-mail.admin_locale' => 'en',
        ]);
    }

    public function test_defaults_are_multipart_localized_and_schema_cannot_store_delivery_headers(): void
    {
        $service = app(TransactionalEmailTemplateService::class);
        $english = $service->render(
            TransactionalEmailTemplateCatalog::NEWSLETTER_CONFIRMATION,
            'en',
            ['confirmation_url' => 'https://example.test/confirm?a=1&signature=safe', 'expiry_hours' => '24']
        );
        SiteSetting::query()->create([
            'group' => 'branding',
            'key' => 'site_name',
            'locale' => 'bn',
            'value' => 'ইগনাইট পরীক্ষামূলক নাম',
            'type' => 'text',
            'is_public' => true,
        ]);
        $bangla = $service->render(
            TransactionalEmailTemplateCatalog::NEWSLETTER_CONFIRMATION,
            'bn',
            ['confirmation_url' => 'https://example.test/confirm-bn', 'expiry_hours' => '24']
        );

        $this->assertStringContainsString('Confirm your', $english->subject);
        $this->assertStringContainsString('https://example.test/confirm?a=1&amp;signature=safe', $english->htmlBody);
        $this->assertStringContainsString('https://example.test/confirm?a=1&signature=safe', $english->textBody);
        $this->assertStringContainsString('নিশ্চিত করুন', $bangla->subject);
        $this->assertStringContainsString('ইগনাইট পরীক্ষামূলক নাম', $bangla->subject);
        $this->assertStringContainsString('সাবস্ক্রিপশন নিশ্চিত করুন', $bangla->htmlBody);

        $mailable = new TransactionalEmail($english);
        $this->assertSame('emails.transactional', $mailable->content()->view);
        $this->assertSame('emails.transactional-text', $mailable->content()->text);
        $this->assertNull($mailable->envelope()->from);
        $this->assertSame([], $mailable->envelope()->to);
        $this->assertSame([], $mailable->envelope()->cc);
        $this->assertSame([], $mailable->envelope()->bcc);
        $this->assertSame([], $mailable->envelope()->replyTo);
        $this->assertSame([], $mailable->attachments());

        $columns = Schema::getColumnListing('transactional_email_templates');
        foreach (['to', 'from', 'reply_to', 'cc', 'bcc', 'headers', 'attachments', 'mailer', 'credentials'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns);
        }
        $this->assertSame('operations@example.test', config('transactional-mail.admin_to'));
        $this->assertSame('sender@example.test', config('mail.from.address'));
    }

    public function test_email_shell_appearance_is_admin_managed_but_css_and_delivery_controls_stay_allowlisted(): void
    {
        foreach ([
            ['key' => 'presentation', 'locale' => '*', 'value' => 'high_contrast', 'type' => 'text'],
            ['key' => 'content_width', 'locale' => '*', 'value' => 'wide', 'type' => 'text'],
            ['key' => 'corner_style', 'locale' => '*', 'value' => 'soft', 'type' => 'text'],
            ['key' => 'show_brand_name', 'locale' => '*', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'brand_heading', 'locale' => 'bn', 'value' => '<img src=x onerror=alert(1)>পরীক্ষামূলক ব্র্যান্ড', 'type' => 'text'],
            ['key' => 'footer_text', 'locale' => 'bn', 'value' => "নিরাপদ ফুটার<script>alert(1)</script>\nদ্বিতীয় লাইন", 'type' => 'text'],
        ] as $setting) {
            SiteSetting::query()->create($setting + [
                'group' => 'email_design',
                'is_public' => false,
            ]);
        }

        $design = app(TransactionalEmailDesignService::class)->forLocale('bn');
        $this->assertSame('#e9edf0', $design['background_color']);
        $this->assertSame('#202124', $design['button_color']);
        $this->assertSame('720px', $design['content_width']);
        $this->assertSame('20px', $design['corner_radius']);
        $this->assertSame('পরীক্ষামূলক ব্র্যান্ড', $design['brand_heading']);
        $this->assertSame("নিরাপদ ফুটারalert(1)\nদ্বিতীয় লাইন", $design['footer_text']);

        $renderedTemplate = app(TransactionalEmailTemplateService::class)->render(
            TransactionalEmailTemplateCatalog::NEWSLETTER_CONFIRMATION,
            'bn',
            ['confirmation_url' => 'https://example.test/confirm', 'expiry_hours' => '24']
        );
        $html = (new TransactionalEmail($renderedTemplate))->render();
        $this->assertStringContainsString('background:#e9edf0', $html);
        $this->assertStringContainsString('max-width:720px', $html);
        $this->assertStringContainsString('border-radius:20px', $html);
        $this->assertStringContainsString('পরীক্ষামূলক ব্র্যান্ড', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);

        SiteSetting::query()->where('group', 'email_design')->where('key', 'presentation')
            ->update(['value' => 'url(javascript:alert(1))']);
        SiteSetting::query()->where('group', 'email_design')->where('key', 'content_width')
            ->update(['value' => '9999px;position:fixed']);
        SiteSetting::query()->where('group', 'email_design')->where('key', 'corner_style')
            ->update(['value' => 'expression(alert(1))']);
        $fallback = app(TransactionalEmailDesignService::class)->forLocale('bn');
        $this->assertSame('brand_warm', $fallback['presentation']);
        $this->assertSame('640px', $fallback['content_width']);
        $this->assertSame('12px', $fallback['corner_radius']);

        $this->assertArrayNotHasKey('email_design', app(SiteSettingService::class)->values('bn', true));
        $fieldKeys = array_keys(config('site-settings.groups.email_design.fields'));
        foreach (['to', 'from', 'reply_to', 'cc', 'bcc', 'headers', 'attachments', 'mailer', 'credentials', 'host', 'port'] as $forbidden) {
            $this->assertNotContains($forbidden, $fieldKeys);
        }
    }

    public function test_every_guided_default_compiles_under_the_existing_storage_contract(): void
    {
        $service = app(TransactionalEmailTemplateService::class);

        foreach (array_keys(TransactionalEmailTemplateCatalog::definitions()) as $templateKey) {
            foreach (TransactionalEmailTemplateCatalog::LOCALES as $locale) {
                $fields = TransactionalEmailTemplateCatalog::structuredDefaults($templateKey, $locale);
                $safe = $service->sanitizeStructuredForStorage($templateKey, $locale, $fields);

                $this->assertSame($fields['subject'], $safe['subject'], "{$templateKey}:{$locale}:subject");
                $this->assertStringContainsString('<h1>', $safe['html_body'], "{$templateKey}:{$locale}:html");
                $this->assertStringContainsString('<!--igf-email-structured:', $safe['html_body'], "{$templateKey}:{$locale}:marker");
                $this->assertStringContainsString($fields['heading'], $safe['text_body'], "{$templateKey}:{$locale}:text");
                $this->assertSame(
                    TransactionalEmailTemplateCatalog::usesButton($templateKey),
                    array_key_exists('button_url', $fields),
                    "{$templateKey}:{$locale}:button"
                );
            }
        }
    }

    public function test_invalid_database_content_falls_back_and_unknown_template_identities_are_rejected(): void
    {
        $defaults = TransactionalEmailTemplateCatalog::defaults(
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'en'
        );
        TransactionalEmailTemplate::query()->create([
            'template_key' => TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'locale' => 'en',
            'subject' => "Injected\r\nBcc: attacker@example.test",
            'html_body' => $defaults['html_body'],
            'text_body' => $defaults['text_body'],
        ]);

        $rendered = app(TransactionalEmailTemplateService::class)->render(
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'en',
            [
                'sponsor_name' => 'Safe Sponsor',
                'response_hours' => '72',
                'request_reference' => 'REQUEST-SAFE',
            ]
        );
        $this->assertSame('Thank you for your sponsorship request', $rendered->subject);
        $this->assertStringNotContainsString('Bcc:', $rendered->subject);

        $this->expectException(\InvalidArgumentException::class);
        TransactionalEmailTemplate::query()->create([
            'template_key' => 'arbitrary_mail',
            'locale' => 'en',
            'subject' => 'Not permitted',
            'html_body' => '<p>Not permitted</p>',
            'text_body' => 'Not permitted',
        ]);
    }

    public function test_admin_template_pages_are_visible_without_mutation_permissions(): void
    {
        $viewer = $this->adminWith('Email template viewer', ['transactional-mail.index']);
        $this->actingAs($viewer, 'admin')
            ->get(route('transactional-mail.index'))
            ->assertOk()
            ->assertSee('Email templates')
            ->assertSee('Read-only access');
        $this->withSession(['locale' => 'bn'])
            ->get(route('transactional-mail.index'))
            ->assertOk()
            ->assertSee('ইমেইল টেমপ্লেট')
            ->assertSee('শুধু দেখার অনুমতি')
            ->assertSee('নিউজলেটার নিশ্চিতকরণ')
            ->assertSee('aria-label="সিস্টেম থেকে পাঠানো ইমেইল টেমপ্লেট"', false);
        $this->get(route('transactional-mail.show', [
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'bn',
        ]))->assertOk()
            ->assertSee('বাংলা সংস্করণ')
            ->assertSee('অনুমোদিত প্লেসহোল্ডার')
            ->assertSee('বিষয়')
            ->assertSee('শিরোনাম')
            ->assertSee('ভূমিকা')
            ->assertSee('মূল বার্তা')
            ->assertSee('বাটনের লেখা')
            ->assertSee('সমাপ্তি')
            ->assertSee('তাৎক্ষণিক প্রিভিউ')
            ->assertSee('aria-labelledby="mail-editor-title"', false)
            ->assertSee('aria-describedby="mail-subject-help"', false)
            ->assertSee('data-preview-status', false)
            ->assertDontSee('name="html_body"', false)
            ->assertDontSee('name="text_body"', false);
        $this->put(route('transactional-mail.update', [
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'bn',
        ]), $this->safeSponsorPayload())->assertForbidden();
    }

    public function test_bangla_admin_validation_and_flash_messages_are_localized(): void
    {
        $manager = $this->adminWith(
            'Localized email template manager',
            ['transactional-mail.index'],
            ['transactional-mail.edit', 'transactional-mail.destroy']
        );
        $this->actingAs($manager, 'admin')->withSession(['locale' => 'bn']);
        $route = route('transactional-mail.update', [
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'bn',
        ]);
        $invalid = $this->safeSponsorPayload();
        $invalid['subject'] = "নিরাপদ বিষয়\r\nBcc: attacker@example.test";

        $this->from($route)->put($route, $invalid)
            ->assertRedirect($route)
            ->assertSessionHasErrors([
                'subject' => 'বিষয়টি এক লাইনে হতে হবে এবং এতে ইমেইল হেডার রাখা যাবে না।',
            ]);

        $this->put($route, $this->safeSponsorPayload())
            ->assertRedirect($route)
            ->assertSessionHas(
                'message',
                'এই ভাষার ইমেইল টেমপ্লেটটি নিরাপদভাবে সংরক্ষণ করা হয়েছে।'
            );
        $this->delete(route('transactional-mail.destroy', [
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'bn',
        ]))->assertRedirect($route)
            ->assertSessionHas(
                'message',
                'কোডে রাখা ডিফল্ট টেমপ্লেটটি ফিরিয়ে আনা হয়েছে।'
            );
    }

    public function test_guided_fields_generate_safe_multipart_copy_and_round_trip_without_raw_html_editing(): void
    {
        $editor = $this->adminWith(
            'Guided email copy editor',
            ['transactional-mail.index'],
            ['transactional-mail.edit']
        );
        $this->actingAs($editor, 'admin');
        $route = route('transactional-mail.update', [
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'en',
        ]);
        $payload = [
            'subject' => 'Thank you {{sponsor_name}}',
            'heading' => 'Welcome, {{sponsor_name}}',
            'introduction' => 'We received request {{request_reference}}.',
            'body' => "A team member will reply soon.\nNo technical formatting is needed.",
            'button_label' => 'Visit {{site_name}}',
            'button_url' => 'https://example.test/help?from=email&safe=1',
            'closing' => "Warm regards,\nThe {{site_name}} team",
        ];

        $this->put($route, $payload)->assertRedirect(route('transactional-mail.show', [
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'en',
        ]));

        $stored = TransactionalEmailTemplate::query()->sole();
        $this->assertStringContainsString('<h1>Welcome, {{sponsor_name}}</h1>', $stored->html_body);
        $this->assertStringContainsString('href="https://example.test/help?from=email&amp;safe=1"', $stored->html_body);
        $this->assertStringContainsString('<br>', $stored->html_body);
        $this->assertStringContainsString('Visit {{site_name}}: https://example.test/help?from=email&safe=1', $stored->text_body);
        $this->assertStringContainsString('<!--igf-email-structured:', $stored->html_body);

        $editorContent = app(TransactionalEmailTemplateService::class)->structuredEditorContent(
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'en'
        );
        foreach ($payload as $field => $expected) {
            $this->assertSame($expected, $editorContent[$field], $field);
        }
        $this->assertFalse($editorContent['is_legacy']);

        $rendered = app(TransactionalEmailTemplateService::class)->render(
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'en',
            [
                'sponsor_name' => 'A & B',
                'request_reference' => 'SP-100',
                'response_hours' => '72',
            ]
        );
        $this->assertStringContainsString('<h1>Welcome, A &amp; B</h1>', $rendered->htmlBody);
        $this->assertStringContainsString('href="https://example.test/help?from=email&amp;safe=1"', $rendered->htmlBody);
        $this->assertStringContainsString("A team member will reply soon.\nNo technical formatting is needed.", $rendered->textBody);
        $this->assertStringNotContainsString('igf-email-structured', $rendered->htmlBody);
    }

    public function test_guided_editor_rejects_unsafe_button_destinations_and_hides_button_fields_when_not_applicable(): void
    {
        $editor = $this->adminWith(
            'Safe guided email editor',
            ['transactional-mail.index'],
            ['transactional-mail.edit']
        );
        $this->actingAs($editor, 'admin');
        $route = route('transactional-mail.update', [
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'en',
        ]);

        $unsafe = $this->safeSponsorPayload();
        $unsafe['button_url'] = 'javascript:alert(1)';
        $this->from($route)->put($route, $unsafe)
            ->assertRedirect($route)
            ->assertSessionHasErrors('button_url');

        foreach ([
            'https:attacker.example/path',
            'https://trusted.example@attacker.example/path',
        ] as $deceptiveUrl) {
            $unsafe = $this->safeSponsorPayload();
            $unsafe['button_url'] = $deceptiveUrl;
            $this->from($route)->put($route, $unsafe)
                ->assertRedirect($route)
                ->assertSessionHasErrors('button_url');
        }

        $wrongPlaceholder = $this->safeSponsorPayload();
        $wrongPlaceholder['button_url'] = '{{sponsor_name}}';
        $this->from($route)->put($route, $wrongPlaceholder)
            ->assertRedirect($route)
            ->assertSessionHasErrors('button_url');

        $embeddedPlaceholder = $this->safeSponsorPayload();
        $embeddedPlaceholder['button_url'] = 'https://example.test/{{site_name}}';
        $this->from($route)->put($route, $embeddedPlaceholder)
            ->assertRedirect($route)
            ->assertSessionHasErrors('button_url');
        $this->assertDatabaseCount('transactional_email_templates', 0);

        $this->get(route('transactional-mail.show', [
            TransactionalEmailTemplateCatalog::SPONSORSHIP_ADMIN_NOTIFICATION,
            'en',
        ]))->assertOk()
            ->assertSee('name="heading"', false)
            ->assertSee('name="introduction"', false)
            ->assertSee('name="body"', false)
            ->assertSee('name="closing"', false)
            ->assertDontSee('name="button_label"', false)
            ->assertDontSee('name="button_url"', false)
            ->assertDontSee('name="html_body"', false)
            ->assertDontSee('name="text_body"', false);
    }

    public function test_existing_unmarked_templates_keep_delivering_and_are_mapped_for_review_before_conversion(): void
    {
        $legacy = [
            'subject' => 'Legacy request {{request_reference}}',
            'html_body' => '<h1>Hello {{sponsor_name}}</h1><p>Request {{request_reference}} was received.</p><p>We will reply soon.</p><p>Regards from {{site_name}}</p><p><a href="{{site_url}}">Open website</a></p>',
            'text_body' => "Hello {{sponsor_name}}\n\nRequest {{request_reference}} was received.\n\nWe will reply soon.\n\nRegards from {{site_name}}\n\nOpen website: {{site_url}}",
        ];
        TransactionalEmailTemplate::query()->create($legacy + [
            'template_key' => TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'locale' => 'en',
        ]);
        $service = app(TransactionalEmailTemplateService::class);
        $before = $service->render(
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'en',
            ['sponsor_name' => 'Legacy Sponsor', 'request_reference' => 'OLD-1', 'response_hours' => '72']
        );
        $this->assertStringContainsString('We will reply soon.', $before->htmlBody);
        $this->assertStringNotContainsString('igf-email-structured', $before->htmlBody);

        $mapped = $service->structuredEditorContent(
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'en'
        );
        $this->assertTrue($mapped['is_legacy']);
        $this->assertSame('Hello {{sponsor_name}}', $mapped['heading']);
        $this->assertSame('Request {{request_reference}} was received.', $mapped['introduction']);
        $this->assertStringContainsString('We will reply soon.', $mapped['body']);
        $this->assertSame('Open website', $mapped['button_label']);
        $this->assertSame('{{site_url}}', $mapped['button_url']);

        $viewer = $this->adminWith('Legacy email reviewer', ['transactional-mail.index']);
        $this->actingAs($viewer, 'admin')->get(route('transactional-mail.show', [
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'en',
        ]))->assertOk()
            ->assertSee('Existing customized template')
            ->assertSee('currently delivered template remains unchanged');

        $this->assertSame($legacy['html_body'], TransactionalEmailTemplate::query()->sole()->html_body);

        $editor = $this->adminWith(
            'Legacy integration email editor',
            ['transactional-mail.index'],
            ['transactional-mail.edit']
        );
        $this->actingAs($editor, 'admin')->put(route('transactional-mail.update', [
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'en',
        ]), $legacy)->assertRedirect();
        $this->assertSame($legacy['html_body'], TransactionalEmailTemplate::query()->sole()->html_body);
        $this->assertStringNotContainsString(
            'igf-email-structured',
            TransactionalEmailTemplate::query()->sole()->html_body
        );
    }

    public function test_admin_editor_is_allowlisted_sanitized_and_separately_permissioned(): void
    {
        $editor = $this->adminWith(
            'Email template editor',
            ['transactional-mail.index'],
            ['transactional-mail.edit']
        );
        $this->actingAs($editor, 'admin');
        $route = route('transactional-mail.update', [
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'bn',
        ]);

        $injected = $this->safeSponsorPayload();
        $injected['subject'] = "Safe subject\r\nBcc: attacker@example.test";
        $this->from($route)->put($route, $injected)
            ->assertRedirect($route)
            ->assertSessionHasErrors('subject');
        $this->assertDatabaseCount('transactional_email_templates', 0);

        $unknown = $this->safeSponsorPayload();
        $unknown['body'] .= "\n{{arbitrary_header}}";
        $this->from($route)->put($route, $unknown)
            ->assertRedirect($route)
            ->assertSessionHasErrors('body');
        $this->assertDatabaseCount('transactional_email_templates', 0);

        $safe = $this->safeSponsorPayload();
        $safe['heading'] = '<img src=x onerror=steal()>স্বাগতম {{sponsor_name}}<script>alert(1)</script>';
        $safe['body'] = '<b>সাধারণ নিরাপদ লেখা</b>';
        $this->put($route, $safe)->assertRedirect(route('transactional-mail.show', [
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'bn',
        ]));

        $stored = TransactionalEmailTemplate::query()->sole();
        $this->assertStringNotContainsString('<script', $stored->html_body);
        $this->assertStringNotContainsString('onclick', $stored->html_body);
        $this->assertStringNotContainsString('<img', $stored->html_body);
        $this->assertStringNotContainsString('<b>', $stored->text_body);
        $this->assertStringContainsString('<!--igf-email-structured:', $stored->html_body);
        $this->assertDatabaseHas('admin_audit_events', [
            'action' => 'transactional_email_template.saved',
            'actor_admin_id' => $editor->id,
        ]);

        $rendered = app(TransactionalEmailTemplateService::class)->render(
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'bn',
            [
                'sponsor_name' => '<img src=x onerror=steal()>A & B',
                'response_hours' => '72',
                'request_reference' => 'REQUEST-SAFE',
            ]
        );
        $this->assertStringContainsString('A &amp; B', $rendered->htmlBody);
        $this->assertStringNotContainsString('<img', $rendered->htmlBody);
        $this->assertStringNotContainsString('igf-email-structured', $rendered->htmlBody);
        $this->delete(route('transactional-mail.destroy', [
            TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
            'bn',
        ]))->assertForbidden();
        $this->assertDatabaseCount('transactional_email_templates', 1);

        $manager = $this->adminWith(
            'Email template manager',
            ['transactional-mail.index'],
            ['transactional-mail.edit', 'transactional-mail.destroy']
        );
        $this->actingAs($manager, 'admin')
            ->delete(route('transactional-mail.destroy', [
                TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION,
                'bn',
            ]))->assertRedirect();
        $this->assertDatabaseCount('transactional_email_templates', 0);
        $this->assertDatabaseHas('admin_audit_events', [
            'action' => 'transactional_email_template.default_restored',
            'actor_admin_id' => $manager->id,
        ]);

        $this->get('/admin/email-templates/not-allowlisted/en')->assertNotFound();
        $this->get('/admin/email-templates/newsletter_confirmation/fr')->assertNotFound();
    }

    public function test_raw_compatibility_path_drops_conditional_comments_and_forged_editor_metadata(): void
    {
        $editor = $this->adminWith(
            'Legacy email security reviewer',
            ['transactional-mail.index'],
            ['transactional-mail.edit']
        );
        $this->actingAs($editor, 'admin');
        $templateKey = TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION;
        $route = route('transactional-mail.update', [$templateKey, 'en']);
        $payload = TransactionalEmailTemplateCatalog::defaults($templateKey, 'en');
        $forgedFields = TransactionalEmailTemplateCatalog::structuredDefaults($templateKey, 'en');
        $forgedFields['heading'] = 'Benign copy shown to the next reviewer';
        $markerJson = json_encode(
            ['version' => 1, 'fields' => $forgedFields],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $this->assertIsString($markerJson);
        $forgedMarker = '<!--igf-email-structured:'
            .rtrim(strtr(base64_encode($markerJson), '+/', '-_'), '=')
            .'-->';
        $conditionalMarkup = '<!--[if mso]><img src="https://attacker.example/pixel" '
            .'onerror="alert(1)"><![endif]-->';
        $payload['html_body'] = $conditionalMarkup.$payload['html_body'].$forgedMarker;

        // Raw fields are a compatibility contract for pre-existing legacy
        // overrides, not an alternate way to bypass the guided editor.
        $this->from($route)->put($route, $payload)
            ->assertRedirect($route)
            ->assertSessionHasErrors('html_body');
        $this->assertDatabaseCount('transactional_email_templates', 0);

        $defaults = TransactionalEmailTemplateCatalog::defaults($templateKey, 'en');
        TransactionalEmailTemplate::query()->create($defaults + [
            'template_key' => $templateKey,
            'locale' => 'en',
        ]);

        // This is the legacy raw-field compatibility branch. Browser users
        // never receive these fields, but deployed integrations may still do.
        $this->put($route, $payload)->assertRedirect(route('transactional-mail.show', [
            $templateKey,
            'en',
        ]));

        $stored = TransactionalEmailTemplate::query()->sole();
        $this->assertStringNotContainsString('[if mso]', $stored->html_body);
        $this->assertStringNotContainsString('attacker.example', $stored->html_body);
        $this->assertStringNotContainsString('igf-email-structured', $stored->html_body);
        $this->assertTrue(
            app(TransactionalEmailTemplateService::class)
                ->structuredEditorContent($templateKey, 'en')['is_legacy']
        );

        // Once converted by the guided editor, the raw branch can no longer
        // overwrite the generated rich/plain pair.
        $this->put($route, [
            'subject' => 'Thank you {{sponsor_name}}',
            'heading' => 'Thank you, {{sponsor_name}}',
            'introduction' => 'We received request {{request_reference}}.',
            'body' => 'Our team will contact you soon.',
            'button_label' => 'Visit {{site_name}}',
            'button_url' => '{{site_url}}',
            'closing' => 'Warm regards from {{site_name}}.',
        ])->assertRedirect();
        $guidedHash = hash('sha256', TransactionalEmailTemplate::query()->sole()->html_body);
        $this->from($route)->put($route, $payload)
            ->assertRedirect($route)
            ->assertSessionHasErrors('html_body');
        $this->assertSame(
            $guidedHash,
            hash('sha256', TransactionalEmailTemplate::query()->sole()->html_body)
        );

        // Even a directly inserted forged marker is treated as legacy unless
        // its fields faithfully recompile to the delivered HTML and text.
        TransactionalEmailTemplate::query()->sole()->forceFill([
            'subject' => $defaults['subject'],
            'html_body' => $defaults['html_body'].$forgedMarker,
            'text_body' => $defaults['text_body'],
        ])->save();
        $review = app(TransactionalEmailTemplateService::class)
            ->structuredEditorContent($templateKey, 'en');
        $this->assertTrue($review['is_legacy']);
        $this->assertNotSame('Benign copy shown to the next reviewer', $review['heading']);

        $rendered = app(TransactionalEmailTemplateService::class)->render(
            $templateKey,
            'en',
            [
                'sponsor_name' => 'Safe Sponsor',
                'response_hours' => '72',
                'request_reference' => 'SP-SAFE',
            ]
        );
        $this->assertStringNotContainsString('[if mso]', $rendered->htmlBody);
        $this->assertStringNotContainsString('attacker.example', $rendered->htmlBody);

        $sanitizer = app(TransactionalEmailContentSanitizer::class);
        $this->assertSame('<p>Visible safe copy</p>', $sanitizer->rich(
            '<!--ordinary comment--><p>Visible safe copy</p>'
        ));
        $safeLinks = $sanitizer->rich(
            '<p><a href="https://example.test/help">Safe</a> '
            .'<a href="https:attacker.example/phish">Malformed</a> '
            .'<a href="https://trusted.example@attacker.example/phish">Deceptive</a> '
            .'<a href="mailto:attacker@example.test?bcc=victim@example.test">Mail</a></p>'
        );
        $this->assertStringContainsString('href="https://example.test/help"', $safeLinks);
        $this->assertStringNotContainsString('href="https:attacker.example', $safeLinks);
        $this->assertStringNotContainsString('trusted.example@attacker.example', $safeLinks);
        $this->assertStringNotContainsString('mailto:', $safeLinks);
    }

    public function test_newsletter_sponsorship_and_volunteer_flows_use_the_selected_safe_templates(): void
    {
        Mail::fake();
        $this->withSession(['locale' => 'bn'])->post(route('frontend.subscribe'), [
            'email' => 'reader@example.test',
            'consent' => true,
        ])->assertRedirect();
        Mail::assertSent(ConfirmNewsletterSubscription::class, function (ConfirmNewsletterSubscription $mail): bool {
            $message = $mail->renderedTemplate();

            return $mail->hasTo('reader@example.test')
                && $message->templateKey === TransactionalEmailTemplateCatalog::NEWSLETTER_CONFIRMATION
                && $message->locale === 'bn'
                && str_contains($message->subject, 'নিশ্চিত করুন');
        });

        Mail::fake();
        config(['transactional-mail.admin_locale' => 'bn']);
        $this->withSession(['locale' => 'bn'])->postJson(route('frontend.sponsorship.store'), [
            'name' => '<b>Safe Sponsor</b>',
            'email' => 'sponsor@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'number_of_children' => 1,
            'contribution_interval' => 'monthly',
            'sponsorshipAmount' => 1500,
        ])->assertOk();
        Mail::assertSent(TransactionalEmail::class, function (TransactionalEmail $mail): bool {
            $message = $mail->renderedTemplate();

            return $mail->hasTo('sponsor@example.test')
                && $message->templateKey === TransactionalEmailTemplateCatalog::SPONSORSHIP_CONFIRMATION
                && $message->locale === 'bn'
                && !str_contains($message->htmlBody, '<b>Safe Sponsor</b>');
        });
        Mail::assertSent(TransactionalEmail::class, function (TransactionalEmail $mail): bool {
            $message = $mail->renderedTemplate();

            return $mail->hasTo('operations@example.test')
                && $message->templateKey === TransactionalEmailTemplateCatalog::SPONSORSHIP_ADMIN_NOTIFICATION
                && $message->locale === 'bn';
        });

        Mail::fake();
        $cause = VolunteerCause::query()->create(['name' => 'Community teaching', 'status' => 1]);
        $this->from(route('frontend.volunteer_registration.index'))->post(
            route('frontend.volunteer_registration.store'),
            [
                'name' => 'Volunteer Person',
                'institution' => 'Example University',
                'email' => 'volunteer@example.test',
                'phone' => '+8801800000000',
                'address' => 'Dhaka',
                'cause_id' => $cause->id,
            ]
        )->assertRedirect(route('frontend.volunteer_registration.index'));
        Mail::assertSent(TransactionalEmail::class, function (TransactionalEmail $mail): bool {
            $message = $mail->renderedTemplate();

            return $mail->hasTo('operations@example.test')
                && !$mail->hasTo('volunteer@example.test')
                && $message->templateKey === TransactionalEmailTemplateCatalog::VOLUNTEER_ADMIN_NOTIFICATION
                && str_contains($message->textBody, 'Community teaching');
        });
    }

    /** @return array<string, string> */
    private function safeSponsorPayload(): array
    {
        return [
            'subject' => 'ধন্যবাদ {{sponsor_name}}',
            'heading' => 'ধন্যবাদ {{sponsor_name}}',
            'introduction' => 'আপনার অনুরোধের রেফারেন্স {{request_reference}}।',
            'body' => 'আমাদের দল শিগগিরই আপনার সঙ্গে যোগাযোগ করবে।',
            'button_label' => 'ওয়েবসাইট দেখুন',
            'button_url' => '{{site_url}}',
            'closing' => 'শুভেচ্ছান্তে, {{site_name}} দল',
        ];
    }

    /** @param list<string> $menus @param list<string> $actions */
    private function adminWith(string $label, array $menus, array $actions = []): Admin
    {
        $menuIds = AuthMenu::query()->whereIn('link', $menus)->where('status', 1)->pluck('id');
        $actionIds = MenuAction::query()->whereIn('link', $actions)->where('status', 1)->pluck('id');
        $this->assertCount(count($menus), $menuIds);
        $this->assertCount(count($actions), $actionIds);
        $suffix = (string) (Role::query()->max('id') + 1);
        $role = Role::query()->create([
            'name' => $label,
            'permission' => $menuIds->implode(','),
            'actionPermission' => $actionIds->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::query()->create([
            'name' => $label,
            'username' => 'email-template-admin-'.$suffix,
            'email' => 'email-template-admin-'.$suffix.'@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
