<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OpportunityPageCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_career_and_workshop_shells_are_public_localized_customizer_groups(): void
    {
        $sharedFields = [
            'eyebrow',
            'title',
            'introduction',
            'search_description',
            'listing_title',
            'listing_introduction',
            'empty_title',
            'empty_message',
            'pagination_label',
            'back_label',
            'card_link_label',
            'form_eyebrow',
            'form_title',
            'form_introduction',
            'applicant_name_label',
            'applicant_name_placeholder',
            'email_label',
            'email_placeholder',
            'phone_label',
            'phone_placeholder',
            'select_placeholder',
            'yes_label',
            'no_label',
            'submit_label',
            'submitting_label',
            'privacy_message',
            'form_unavailable',
            'closed_title',
            'closed_message',
            'upcoming_title',
            'upcoming_message',
            'submission_eyebrow',
            'submission_title',
            'submission_message',
            'submission_updated_message',
            'submission_reference_label',
        ];
        $detailFields = [
            'career_page' => [
                'location_label',
                'employment_type_label',
                'work_arrangement_label',
                'vacancies_label',
                'opens_label',
                'deadline_label',
                'requirements_title',
                'responsibilities_title',
                'cv_label',
                'cv_help',
            ],
            'workshop_page' => [
                'date_label',
                'registration_opens_label',
                'registration_deadline_label',
                'venue_label',
                'format_label',
                'availability_label',
                'venue_details_title',
                'registration_instructions_title',
            ],
        ];

        foreach (['career_page', 'workshop_page'] as $group) {
            $schema = config("site-settings.groups.{$group}");

            $this->assertIsArray($schema);
            $this->assertNotEmpty($schema['label']);
            $this->assertNotEmpty($schema['description']);

            foreach (array_merge($sharedFields, $detailFields[$group]) as $key) {
                $field = $schema['fields'][$key] ?? null;
                $this->assertIsArray($field, "{$group}.{$key} must be editable.");
                $this->assertTrue($field['localized'], "{$group}.{$key} must be localized.");
                $this->assertTrue($field['public'], "{$group}.{$key} must be public.");
                $this->assertArrayHasKey('default', $field);
                $this->assertArrayHasKey('bn', $field['localized_defaults']);
            }
        }

        $english = app(SiteSettingService::class)->values('en', true);
        $bangla = app(SiteSettingService::class)->values('bn', true);

        $this->assertSame('Careers', $english['career_page']['title']);
        $this->assertSame('Workshops', $english['workshop_page']['title']);
        $this->assertSame('কর্মজীবন', $bangla['career_page']['title']);
        $this->assertSame('কর্মশালা', $bangla['workshop_page']['title']);
        $this->assertSame('Requirements', $english['career_page']['requirements_title']);
        $this->assertSame('Thank you', $english['workshop_page']['submission_title']);
        $this->assertSame('আপনার ফোন নম্বর লিখুন', $bangla['career_page']['phone_placeholder']);
        $this->assertSame('উপলভ্যতা', $bangla['workshop_page']['availability_label']);
    }

    public function test_public_opportunity_pages_use_editor_managed_shell_copy_and_search_descriptions(): void
    {
        $this->storePublicCopy('career_page', 'title', 'Work with our communities');
        $this->storePublicCopy('career_page', 'introduction', 'A careers introduction written by an editor.');
        $this->storePublicCopy('career_page', 'listing_title', 'Roles accepting applications');
        $this->storePublicCopy('career_page', 'listing_introduction', 'Choose a role that matches your experience.');
        $this->storePublicCopy('career_page', 'empty_title', 'New roles are being prepared');
        $this->storePublicCopy('career_page', 'empty_message', 'Please return next week.');
        $this->storePublicCopy('career_page', 'search_description', 'Editor-managed careers search description.');
        $this->storePublicCopy('career_page', 'card_link_label', 'Read role and apply');
        $this->storePublicCopy('career_page', 'requirements_title', 'What you will bring');
        $this->storePublicCopy('career_page', 'email_placeholder', 'you@community.org');
        $this->storePublicCopy('career_page', 'closed_message', 'Applications will reopen after review.');
        $this->storePublicCopy('career_page', 'submission_title', 'Application safely received');

        $this->storePublicCopy('workshop_page', 'title', 'Learning workshops');
        $this->storePublicCopy('workshop_page', 'introduction', 'An editor-managed workshop introduction.');
        $this->storePublicCopy('workshop_page', 'listing_title', 'Sessions accepting registrations');
        $this->storePublicCopy('workshop_page', 'empty_title', 'New sessions are being planned');
        $this->storePublicCopy('workshop_page', 'search_description', 'Editor-managed workshops search description.');
        $this->storePublicCopy('workshop_page', 'card_link_label', 'Read session and register');
        $this->storePublicCopy('workshop_page', 'venue_details_title', 'How to find the venue');
        $this->storePublicCopy('workshop_page', 'phone_placeholder', 'Enter a reachable number');
        $this->storePublicCopy('workshop_page', 'upcoming_message', 'Registration opens next month.');
        $this->storePublicCopy('workshop_page', 'submission_message', 'Your place request is with our workshop team.');

        $this->get(route('frontend.jobs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('careers')
                ->where('title', 'Work with our communities')
                ->where('meta_tag.meta_description', 'Editor-managed careers search description.')
                ->where('data.copy.introduction', 'A careers introduction written by an editor.')
                ->where('data.copy.listing_title', 'Roles accepting applications')
                ->where('data.copy.listing_introduction', 'Choose a role that matches your experience.')
                ->where('data.copy.empty_title', 'New roles are being prepared')
                ->where('data.copy.empty_message', 'Please return next week.')
                ->where('data.copy.card.link_label', 'Read role and apply')
                ->where('data.copy.requirements_title', 'What you will bring')
                ->where('data.copy.email_placeholder', 'you@community.org')
                ->where('data.copy.closed_message', 'Applications will reopen after review.')
                ->where('data.copy.submission.title', 'Application safely received')
            );

        $this->get(route('frontend.workshops.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workshops')
                ->where('title', 'Learning workshops')
                ->where('meta_tag.meta_description', 'Editor-managed workshops search description.')
                ->where('data.copy.introduction', 'An editor-managed workshop introduction.')
                ->where('data.copy.listing_title', 'Sessions accepting registrations')
                ->where('data.copy.empty_title', 'New sessions are being planned')
                ->where('data.copy.card.link_label', 'Read session and register')
                ->where('data.copy.venue_details_title', 'How to find the venue')
                ->where('data.copy.phone_placeholder', 'Enter a reachable number')
                ->where('data.copy.upcoming_message', 'Registration opens next month.')
                ->where('data.copy.submission.message', 'Your place request is with our workshop team.')
            );
    }

    private function storePublicCopy(string $group, string $key, string $value, string $locale = 'en'): void
    {
        SiteSetting::create([
            'group' => $group,
            'key' => $key,
            'locale' => $locale,
            'value' => $value,
            'type' => 'text',
            'is_public' => true,
        ]);
    }
}
