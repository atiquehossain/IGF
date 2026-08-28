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
        foreach (['career_page', 'workshop_page'] as $group) {
            $schema = config("site-settings.groups.{$group}");

            $this->assertIsArray($schema);
            $this->assertNotEmpty($schema['label']);
            $this->assertNotEmpty($schema['description']);

            foreach ([
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
            ] as $key) {
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

        $this->storePublicCopy('workshop_page', 'title', 'Learning workshops');
        $this->storePublicCopy('workshop_page', 'introduction', 'An editor-managed workshop introduction.');
        $this->storePublicCopy('workshop_page', 'listing_title', 'Sessions accepting registrations');
        $this->storePublicCopy('workshop_page', 'empty_title', 'New sessions are being planned');
        $this->storePublicCopy('workshop_page', 'search_description', 'Editor-managed workshops search description.');
        $this->storePublicCopy('workshop_page', 'card_link_label', 'Read session and register');

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
