<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\DonationCauseSection;
use App\Models\DonationType;
use App\Models\MediaAsset;
use App\Models\MenuAction;
use App\Models\Role;
use App\Services\DonationCauseContentService;
use App\Services\MediaUsageService;
use App\Support\AdminPermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DonationCauseContentCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_donation_cause_editor_persists_ordered_localized_content_without_gateway_controls(): void
    {
        DonationType::query()->forceDelete();
        $admin = $this->makeAdmin();
        $cause = $this->cause();
        $image = $this->asset('cause-story.jpg', 'image/jpeg');

        $this->actingAs($admin, 'admin')
            ->get(route('donationType.content.edit', $cause))
            ->assertOk()
            ->assertSee('Suggested donation amounts')
            ->assertSee('Landing-page story sections')
            ->assertSee('content_editor_ready')
            ->assertSee('English video transcript')
            ->assertSee('cause-story.jpg')
            ->assertDontSee('store_id')
            ->assertDontSee('store_password');

        $response = $this->actingAs($admin, 'admin')->put(
            route('donationType.content.update', $cause),
            $this->editorPayload($cause, [
            'amount_cards' => [
                [
                    'amount' => 2500,
                    'impact' => ['en' => 'Supports one learner.', 'bn' => 'একজন শিক্ষার্থীকে সহায়তা করে।'],
                    'enabled' => '1',
                ],
                [
                    'amount' => 500,
                    'impact' => ['en' => 'Provides learning materials.', 'bn' => ''],
                    'enabled' => '0',
                ],
            ],
            'landing_sections' => [
                [
                    'layout' => 'media-left',
                    'title' => ['en' => 'Why this work matters', 'bn' => 'এই কাজটি কেন গুরুত্বপূর্ণ'],
                    'body' => [
                        'en' => '<script>alert(1)</script><p onclick="alert(2)"><strong>Safe learning</strong> for every child.</p>',
                        'bn' => '<p>প্রতিটি শিশুর জন্য নিরাপদ শিক্ষা।</p>',
                    ],
                    'image_media_uuid' => $image->uuid,
                    'image_alt' => ['en' => 'Children learning together', 'bn' => 'শিশুরা একসাথে শিখছে'],
                    'video_media_uuid' => '',
                    'video_url' => '',
                    'cta_label' => ['en' => 'Meet the team', 'bn' => 'দলের সাথে পরিচিত হন'],
                    'cta_url' => '/about-us',
                    'enabled' => '1',
                ],
                [
                    'layout' => 'highlight',
                    'title' => ['en' => 'Watch the story', 'bn' => ''],
                    'body' => ['en' => '', 'bn' => ''],
                    'image_media_uuid' => '',
                    'image_alt' => ['en' => '', 'bn' => ''],
                    'video_media_uuid' => '',
                    'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
                    'video_title' => ['en' => 'Watch the education story', 'bn' => 'শিক্ষার গল্প দেখুন'],
                    'video_transcript' => ['en' => '', 'bn' => ''],
                    'cta_label' => ['en' => '', 'bn' => ''],
                    'cta_url' => '',
                    'enabled' => '0',
                ],
            ],
            ])
        );

        $response->assertRedirect(route('donationType.content.edit', $cause))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('donation_cause_amounts', 2);
        $this->assertDatabaseCount('donation_cause_sections', 2);

        $amounts = $cause->fresh()->amountCards;
        $this->assertSame([2500, 500], $amounts->pluck('amount')->all());
        $this->assertSame([10, 20], $amounts->pluck('display_order')->all());
        $this->assertSame('একজন শিক্ষার্থীকে সহায়তা করে।', data_get($amounts->first()->impact, 'bn'));
        $this->assertFalse($amounts->last()->enabled);

        $sections = $cause->fresh()->landingSections;
        $this->assertSame([10, 20], $sections->pluck('display_order')->all());
        $this->assertStringNotContainsString('<script', data_get($sections->first()->body, 'en'));
        $this->assertStringNotContainsString('onclick', data_get($sections->first()->body, 'en'));
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $sections->last()->video_url);
        $this->assertSame('Watch the education story', data_get($sections->last()->video_title, 'en'));
        $this->assertTrue(app(MediaUsageService::class)->inUse($image));
        $this->assertSame(2, $cause->fresh()->content_editor_version);
        $this->assertDatabaseHas('admin_audit_events', [
            'actor_admin_id' => $admin->id,
            'action' => 'donation_cause.content_updated',
            'target_id' => (string) $cause->id,
            'outcome' => 'success',
        ]);
    }

    public function test_public_cause_and_direct_pages_expose_only_enabled_sanitized_content_with_locale_fallbacks(): void
    {
        DonationType::query()->forceDelete();
        $direct = $this->cause('Flexible community support', 'direct');
        $direct->amountCards()->createMany([
            ['amount' => 1000, 'impact' => ['en' => 'Meals and practical support.', 'bn' => 'খাবার ও ব্যবহারিক সহায়তা।'], 'display_order' => 20, 'enabled' => true],
            ['amount' => 500, 'impact' => ['en' => 'Learning essentials.', 'bn' => ''], 'display_order' => 10, 'enabled' => true],
            ['amount' => 9000, 'impact' => ['en' => 'Hidden draft.', 'bn' => 'লুকানো'], 'display_order' => 30, 'enabled' => false],
        ]);
        DonationCauseSection::create([
            'donation_type_id' => $direct->id,
            'layout' => 'highlight',
            'title' => ['en' => 'A transparent contribution', 'bn' => 'স্বচ্ছ অবদান'],
            // Simulate old data saved before the current write-time sanitizer.
            'body' => ['en' => '<p>See our <a href="/annual-report">results</a>.</p><script>alert(1)</script>', 'bn' => ''],
            'cta_label' => ['en' => 'Read reports', 'bn' => ''],
            'cta_url' => 'javascript:alert(1)',
            'display_order' => 10,
            'enabled' => true,
        ]);
        DonationCauseSection::create([
            'donation_type_id' => $direct->id,
            'layout' => 'text',
            'title' => ['en' => 'Hidden section', 'bn' => ''],
            'display_order' => 20,
            'enabled' => false,
        ]);
        $unnamedImage = $this->asset('unnamed-story.jpg', 'image/jpeg');
        DonationCauseSection::create([
            'donation_type_id' => $direct->id,
            'layout' => 'media-left',
            'title' => ['en' => '', 'bn' => ''],
            'body' => ['en' => '', 'bn' => ''],
            'image_media_uuid' => $unnamedImage->uuid,
            'image_alt' => ['en' => '', 'bn' => ''],
            'display_order' => 30,
            'enabled' => true,
        ]);

        $this->get(route('frontend.donate.direct'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.pageMode', 'detail')
                ->has('data.donationTypes', 1)
                ->where('data.donationTypes.0.uuid', $direct->uuid)
                ->has('data.donationTypes.0.amount_options', 2)
                ->where('data.donationTypes.0.amount_options.0.amount', 500)
                ->where('data.donationTypes.0.amount_options.0.impact', 'Learning essentials.')
                ->where('data.donationTypes.0.amount_options.1.amount', 1000)
                ->has('data.donationTypes.0.landing_sections', 1)
                ->where('data.donationTypes.0.landing_sections.0.title', 'A transparent contribution')
                ->where('data.donationTypes.0.landing_sections.0.body', '<p>See our <a href="/annual-report">results</a>.</p>')
                ->where('data.donationTypes.0.landing_sections.0.cta', null)
            );

        $payload = app(DonationCauseContentService::class)->publicPayload($direct->fresh(), 'bn');
        $this->assertSame('খাবার ও ব্যবহারিক সহায়তা।', $payload['amount_options'][1]['impact']);
        $this->assertSame('Learning essentials.', $payload['amount_options'][0]['impact']);
        $this->assertSame('স্বচ্ছ অবদান', $payload['landing_sections'][0]['title']);
        $this->assertStringNotContainsString('<script', $payload['landing_sections'][0]['body']);
    }

    public function test_editor_rejects_unsafe_or_ambiguous_content_and_bounded_lists(): void
    {
        DonationType::query()->forceDelete();
        $admin = $this->makeAdmin();
        $cause = $this->cause();
        $image = $this->asset('story.jpg', 'image/jpeg');
        $video = $this->asset('story.mp4', 'video/mp4');

        $tooManyAmounts = collect(range(1, DonationCauseContentService::MAX_AMOUNT_CARDS + 1))
            ->map(fn (int $index): array => [
                'amount' => 100 + $index,
                'impact' => ['en' => 'Impact ' . $index, 'bn' => ''],
                'enabled' => true,
            ])->all();

        $this->actingAs($admin, 'admin')->from(route('donationType.content.edit', $cause))
            ->put(route('donationType.content.update', $cause), $this->editorPayload($cause, [
                'amount_cards' => $tooManyAmounts,
                'landing_sections' => [],
            ]))
            ->assertRedirect(route('donationType.content.edit', $cause))
            ->assertSessionHasErrors('amount_cards');

        $this->actingAs($admin, 'admin')->from(route('donationType.content.edit', $cause))
            ->put(route('donationType.content.update', $cause), $this->editorPayload($cause, [
                'amount_cards' => [
                    ['amount' => 500, 'impact' => ['en' => 'First', 'bn' => ''], 'enabled' => true],
                    ['amount' => 500, 'impact' => ['en' => 'Duplicate', 'bn' => ''], 'enabled' => true],
                ],
                'landing_sections' => [[
                    'layout' => 'media-right',
                    'title' => ['en' => 'Ambiguous media', 'bn' => ''],
                    'body' => ['en' => '', 'bn' => ''],
                    'image_media_uuid' => $image->uuid,
                    'image_alt' => ['en' => '', 'bn' => ''],
                    'video_media_uuid' => $video->uuid,
                    'video_url' => 'http://youtube.com/watch?v=dQw4w9WgXcQ',
                    'video_title' => ['en' => '', 'bn' => ''],
                    'video_transcript' => ['en' => '', 'bn' => ''],
                    'cta_label' => ['en' => 'Unsafe', 'bn' => ''],
                    'cta_url' => 'javascript:alert(1)',
                    'enabled' => true,
                ]],
            ]))
            ->assertSessionHasErrors([
                'amount_cards.1.amount',
                'landing_sections.0.image_media_uuid',
                'landing_sections.0.video_url',
                'landing_sections.0.cta_url',
            ]);

        $this->assertDatabaseCount('donation_cause_amounts', 0);
        $this->assertDatabaseCount('donation_cause_sections', 0);
    }

    public function test_missing_or_unready_editor_payload_never_deletes_existing_rows(): void
    {
        DonationType::query()->forceDelete();
        $admin = $this->makeAdmin();
        $cause = $this->cause();
        $amount = $cause->amountCards()->create([
            'amount' => 750,
            'impact' => ['en' => 'Keeps existing support safe.', 'bn' => ''],
            'display_order' => 10,
            'enabled' => true,
        ]);
        $section = $cause->landingSections()->create([
            'layout' => 'text',
            'title' => ['en' => 'Existing story', 'bn' => ''],
            'body' => ['en' => '<p>This must survive an incomplete form submission.</p>', 'bn' => ''],
            'display_order' => 10,
            'enabled' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('donationType.content.update', $cause), [])
            ->assertSessionHasErrors([
                'content_editor_ready',
                'amount_cards_payload_ready',
                'landing_sections_payload_ready',
            ]);

        $this->actingAs($admin, 'admin')
            ->put(route('donationType.content.update', $cause), [
                'content_editor_ready' => '0',
                'amount_cards_payload_ready' => '0',
                'landing_sections_payload_ready' => '0',
                'content_editor_version' => $cause->content_editor_version,
            ])
            ->assertSessionHasErrors([
                'content_editor_ready',
                'amount_cards_payload_ready',
                'landing_sections_payload_ready',
            ]);

        $this->assertDatabaseHas('donation_cause_amounts', ['id' => $amount->id, 'amount' => 750]);
        $this->assertDatabaseHas('donation_cause_sections', ['id' => $section->id]);
        $this->assertSame(1, $cause->fresh()->content_editor_version);
        $this->assertDatabaseMissing('admin_audit_events', ['action' => 'donation_cause.content_updated']);
    }

    public function test_explicitly_ready_empty_lists_can_clear_optional_content(): void
    {
        DonationType::query()->forceDelete();
        $admin = $this->makeAdmin();
        $cause = $this->cause();
        $cause->amountCards()->create([
            'amount' => 750,
            'impact' => ['en' => 'Temporary amount.', 'bn' => ''],
            'display_order' => 10,
            'enabled' => true,
        ]);
        $cause->landingSections()->create([
            'layout' => 'text',
            'title' => ['en' => 'Temporary story', 'bn' => ''],
            'display_order' => 10,
            'enabled' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('donationType.content.update', $cause), $this->editorPayload($cause))
            ->assertRedirect(route('donationType.content.edit', $cause))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('donation_cause_amounts', 0);
        $this->assertDatabaseCount('donation_cause_sections', 0);
        $this->assertSame(2, $cause->fresh()->content_editor_version);
    }

    public function test_stale_editor_version_cannot_overwrite_or_delete_a_newer_save(): void
    {
        DonationType::query()->forceDelete();
        $admin = $this->makeAdmin();
        $cause = $this->cause();
        $amount = $cause->amountCards()->create([
            'amount' => 500,
            'impact' => ['en' => 'Original impact.', 'bn' => ''],
            'display_order' => 10,
            'enabled' => true,
        ]);
        $staleVersion = (int) $cause->fresh()->content_editor_version;

        $firstPayload = $this->editorPayload($cause, [
            'amount_cards' => [[
                'uuid' => $amount->uuid,
                'amount' => 1000,
                'impact' => ['en' => 'Saved by the first editor.', 'bn' => ''],
                'enabled' => true,
            ]],
        ]);
        $this->actingAs($admin, 'admin')
            ->put(route('donationType.content.update', $cause), $firstPayload)
            ->assertSessionHasNoErrors();

        $stalePayload = $this->editorPayload($cause, [
            'content_editor_version' => $staleVersion,
            'amount_cards' => [[
                'uuid' => $amount->uuid,
                'amount' => 2500,
                'impact' => ['en' => 'Stale editor overwrite.', 'bn' => ''],
                'enabled' => true,
            ]],
        ]);
        $this->actingAs($admin, 'admin')
            ->from(route('donationType.content.edit', $cause))
            ->put(route('donationType.content.update', $cause), $stalePayload)
            ->assertRedirect(route('donationType.content.edit', $cause))
            ->assertSessionHasErrors('content_editor_version');

        $saved = $cause->fresh();
        $this->assertSame(2, $saved->content_editor_version);
        $this->assertSame([1000], $saved->amountCards->pluck('amount')->all());
        $this->assertSame('Saved by the first editor.', data_get($saved->amountCards->first()->impact, 'en'));
        $this->assertDatabaseHas('admin_audit_events', [
            'action' => 'donation_cause.content_conflict',
            'target_id' => (string) $cause->id,
            'outcome' => 'denied',
        ]);
    }

    public function test_media_accessibility_requires_names_and_uploaded_video_transcripts(): void
    {
        DonationType::query()->forceDelete();
        $admin = $this->makeAdmin();
        $cause = $this->cause();
        $image = $this->asset('image-only.jpg', 'image/jpeg');
        $video = $this->asset('accessible-story.mp4', 'video/mp4');

        $this->actingAs($admin, 'admin')
            ->put(route('donationType.content.update', $cause), $this->editorPayload($cause, [
                'landing_sections' => [[
                    'layout' => 'media-left',
                    'title' => ['en' => '', 'bn' => ''],
                    'body' => ['en' => '', 'bn' => ''],
                    'image_media_uuid' => $image->uuid,
                    'image_alt' => ['en' => '', 'bn' => ''],
                    'video_media_uuid' => '',
                    'video_url' => '',
                    'video_title' => ['en' => '', 'bn' => ''],
                    'video_transcript' => ['en' => '', 'bn' => ''],
                    'cta_label' => ['en' => '', 'bn' => ''],
                    'cta_url' => '',
                    'enabled' => true,
                ]],
            ]))
            ->assertSessionHasErrors('landing_sections.0.image_alt.en');

        $this->actingAs($admin, 'admin')
            ->put(route('donationType.content.update', $cause), $this->editorPayload($cause, [
                'landing_sections' => [[
                    'layout' => 'media-right',
                    'title' => ['en' => '', 'bn' => ''],
                    'body' => ['en' => '', 'bn' => ''],
                    'image_media_uuid' => '',
                    'image_alt' => ['en' => '', 'bn' => ''],
                    'video_media_uuid' => $video->uuid,
                    'video_url' => '',
                    'video_title' => ['en' => '', 'bn' => ''],
                    'video_transcript' => ['en' => 'Too short.', 'bn' => ''],
                    'cta_label' => ['en' => '', 'bn' => ''],
                    'cta_url' => '',
                    'enabled' => true,
                ]],
            ]))
            ->assertSessionHasErrors([
                'landing_sections.0.video_title.en',
                'landing_sections.0.video_transcript.en',
            ]);

        $transcript = 'A teacher explains how donations provide books and a safe classroom.';
        $this->actingAs($admin, 'admin')
            ->put(route('donationType.content.update', $cause), $this->editorPayload($cause, [
                'landing_sections' => [[
                    'layout' => 'media-right',
                    'title' => ['en' => '', 'bn' => ''],
                    'body' => ['en' => '', 'bn' => ''],
                    'image_media_uuid' => '',
                    'image_alt' => ['en' => '', 'bn' => ''],
                    'video_media_uuid' => $video->uuid,
                    'video_url' => '',
                    'video_title' => ['en' => 'How education support works', 'bn' => 'শিক্ষা সহায়তা যেভাবে কাজ করে'],
                    'video_transcript' => ['en' => $transcript, 'bn' => 'একজন শিক্ষক ব্যাখ্যা করছেন কীভাবে অনুদান বই ও নিরাপদ শ্রেণিকক্ষ নিশ্চিত করে।'],
                    'cta_label' => ['en' => '', 'bn' => ''],
                    'cta_url' => '',
                    'enabled' => true,
                ]],
            ]))
            ->assertSessionHasNoErrors();

        $payload = app(DonationCauseContentService::class)->publicPayload($cause->fresh(), 'bn');
        $this->assertSame('file', data_get($payload, 'landing_sections.0.video.type'));
        $this->assertSame('শিক্ষা সহায়তা যেভাবে কাজ করে', data_get($payload, 'landing_sections.0.video.title'));
        $this->assertSame(
            'একজন শিক্ষক ব্যাখ্যা করছেন কীভাবে অনুদান বই ও নিরাপদ শ্রেণিকক্ষ নিশ্চিত করে।',
            data_get($payload, 'landing_sections.0.video.transcript')
        );
    }

    public function test_donation_content_catalogues_match_and_the_dynamic_editor_uses_them(): void
    {
        $english = require resource_path('lang/en/donation_content.php');
        $bangla = require resource_path('lang/bn/donation_content.php');

        $this->assertSame(
            $this->catalogKeyPaths($english),
            $this->catalogKeyPaths($bangla),
            'The donation-content catalogues must expose matching English and Bangla key paths.'
        );

        $source = file_get_contents(resource_path('views/admin/donationType/content.blade.php'));
        $this->assertIsString($source);
        $this->assertStringContainsString("@json(__('donation_content.editor'))", $source);
        $this->assertStringContainsString('editorCopy.amount.limit', $source);
        $this->assertStringContainsString('editorCopy.section.limit', $source);
        $this->assertStringNotContainsString('A cause can have up to ${maxAmountCards}', $source);
        $this->assertStringNotContainsString('A cause can have up to ${maxLandingSections}', $source);
    }

    public function test_editor_renders_static_dynamic_and_layout_copy_in_english_and_bangla(): void
    {
        DonationType::query()->forceDelete();
        $admin = $this->makeAdmin();
        $cause = $this->cause();
        $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

        $englishEditor = trans('donation_content.editor', [], 'en');
        $englishResponse = $this->actingAs($admin, 'admin')
            ->withSession(['locale' => 'en'])
            ->get(route('donationType.content.edit', $cause));
        $englishResponse
            ->assertOk()
            ->assertSee(trans('donation_content.view.amounts.heading', [], 'en'))
            ->assertSee(trans('donation_content.view.sections.heading', [], 'en'))
            ->assertSee(trans('donation_content.layout.media_left', [], 'en'));
        $this->assertStringContainsString(
            (string) json_encode($englishEditor, $jsonFlags),
            $englishResponse->getContent()
        );

        $banglaEditor = trans('donation_content.editor', [], 'bn');
        $banglaResponse = $this->actingAs($admin, 'admin')
            ->withSession(['locale' => 'bn'])
            ->get(route('donationType.content.edit', $cause));
        $banglaResponse
            ->assertOk()
            ->assertSee(trans('donation_content.view.amounts.heading', [], 'bn'))
            ->assertSee(trans('donation_content.view.sections.heading', [], 'bn'))
            ->assertDontSee(trans('donation_content.view.amounts.heading', [], 'en'));
        $this->assertStringContainsString(
            (string) json_encode(trans('donation_content.layout.media_left', [], 'bn'), $jsonFlags),
            $banglaResponse->getContent()
        );
        $this->assertStringContainsString(
            (string) json_encode($banglaEditor, $jsonFlags),
            $banglaResponse->getContent()
        );
    }

    public function test_editor_validation_success_and_conflict_feedback_follow_the_admin_locale(): void
    {
        DonationType::query()->forceDelete();
        $admin = $this->makeAdmin();
        $cause = $this->cause();
        $amount = $cause->amountCards()->create([
            'amount' => 750,
            'impact' => ['en' => 'Keeps existing support safe.', 'bn' => 'বিদ্যমান সহায়তা নিরাপদ রাখে।'],
            'display_order' => 10,
            'enabled' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->withSession(['locale' => 'en'])
            ->put(route('donationType.content.update', $cause), [])
            ->assertSessionHasErrors([
                'content_editor_ready' => trans('donation_content.validation.editor_not_ready', [], 'en'),
            ]);
        $this->assertDatabaseHas('donation_cause_amounts', ['id' => $amount->id, 'amount' => 750]);

        $this->actingAs($admin, 'admin')
            ->withSession(['locale' => 'bn'])
            ->put(route('donationType.content.update', $cause), [])
            ->assertSessionHasErrors([
                'content_editor_ready' => trans('donation_content.validation.editor_not_ready', [], 'bn'),
            ]);
        $this->assertDatabaseHas('donation_cause_amounts', ['id' => $amount->id, 'amount' => 750]);

        $savedPayload = $this->editorPayload($cause, [
            'amount_cards' => [[
                'uuid' => $amount->uuid,
                'amount' => 1000,
                'impact' => ['en' => 'Saved by the current editor.', 'bn' => 'বর্তমান সম্পাদক সংরক্ষণ করেছেন।'],
                'enabled' => true,
            ]],
            'landing_sections' => [],
        ]);
        $this->actingAs($admin, 'admin')
            ->withSession(['locale' => 'bn'])
            ->put(route('donationType.content.update', $cause), $savedPayload)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('message', trans('donation_content.controller.saved', [], 'bn'));

        $stalePayload = $this->editorPayload($cause, [
            'content_editor_version' => 1,
            'amount_cards' => [[
                'uuid' => $amount->uuid,
                'amount' => 2500,
                'impact' => ['en' => 'Stale overwrite.', 'bn' => 'পুরোনো লেখা।'],
                'enabled' => true,
            ]],
            'landing_sections' => [],
        ]);
        $this->actingAs($admin, 'admin')
            ->withSession(['locale' => 'bn'])
            ->from(route('donationType.content.edit', $cause))
            ->put(route('donationType.content.update', $cause), $stalePayload)
            ->assertSessionHasErrors([
                'content_editor_version' => trans('donation_content.validation.conflict', [], 'bn'),
            ]);

        $saved = $cause->fresh();
        $this->assertSame(2, $saved->content_editor_version);
        $this->assertSame([1000], $saved->amountCards->pluck('amount')->all());
        $this->assertSame('বর্তমান সম্পাদক সংরক্ষণ করেছেন।', data_get($saved->amountCards->first()->impact, 'bn'));
    }

    public function test_content_routes_reuse_the_existing_donation_cause_edit_capability(): void
    {
        $this->assertSame(['donationType.edit'], AdminPermissionRegistry::capabilitiesForRoute('donationType.content.edit'));
        $this->assertSame(['donationType.edit'], AdminPermissionRegistry::capabilitiesForRoute('donationType.content.update'));
    }

    private function cause(string $name = 'Education support', ?string $purpose = null): DonationType
    {
        return DonationType::create([
            'name' => $name,
            'description' => 'A visitor-ready donation cause description.',
            'purpose_key' => $purpose,
            'destination_type' => $purpose === 'direct' ? 'unrestricted' : 'restricted_fund',
            'destination_name' => $purpose === 'direct' ? null : $name . ' Fund',
            'status' => true,
        ]);
    }

    private function editorPayload(DonationType $cause, array $payload = []): array
    {
        $currentVersion = (int) ($cause->fresh()?->content_editor_version ?? 1);

        return array_merge([
            'content_editor_ready' => '1',
            'amount_cards_payload_ready' => '1',
            'landing_sections_payload_ready' => '1',
            'content_editor_version' => $currentVersion,
        ], $payload);
    }

    private function asset(string $name, string $mime): MediaAsset
    {
        return MediaAsset::create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => 'donation-causes/' . $name,
            'original_name' => $name,
            'mime_type' => $mime,
            'extension' => pathinfo($name, PATHINFO_EXTENSION),
            'bytes' => 1024,
            'uploaded_by' => null,
        ]);
    }

    private function makeAdmin(): Admin
    {
        $menu = AuthMenu::firstOrCreate(['link' => 'donationType.index'], [
            'name' => 'Donation Causes',
            'status' => 1,
        ]);
        $action = MenuAction::firstOrCreate(['link' => 'donationType.edit'], [
            'name' => 'Edit donation causes',
            'auth_menu_id' => $menu->id,
            'status' => 1,
        ]);
        $role = Role::create([
            'name' => 'Donation content editor ' . Str::random(8),
            'permission' => (string) $menu->id,
            'actionPermission' => (string) $action->id,
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Donation Content QA',
            'username' => 'donation-content-' . Str::random(8),
            'email' => 'donation-content-' . Str::random(8) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }

    /** @return list<string> */
    private function catalogKeyPaths(array $copy, string $prefix = ''): array
    {
        $paths = [];

        foreach ($copy as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            $paths[] = $path;

            if (is_array($value)) {
                array_push($paths, ...$this->catalogKeyPaths($value, $path));
            }
        }

        sort($paths);

        return $paths;
    }
}
