<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\AnnualReport;
use App\Models\AuthMenu;
use App\Models\Banner;
use App\Models\Category;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ContactMessage;
use App\Models\Gallery;
use App\Models\LatestNews;
use App\Models\MenuAction;
use App\Models\NoticeBoard;
use App\Models\Role;
use App\Models\Sponsorship;
use App\Models\Subscriber;
use App\Models\Testimonial;
use App\Models\Volunteer;
use App\Services\ContentFileQuarantine;
use App\Services\PrivacyRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPrivacyWorkflowIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_listing_searches_use_short_lived_session_state_not_get_urls(): void
    {
        $admin = $this->adminWithMenus([
            'donations.index', 'contact-message.index', 'sponsorships.index', 'volunteer.index',
            'user-approval.index', 'user.index', 'report.youtubeMeta', 'subscriber.index', 'comment.index',
            'admin.index',
        ]);
        $this->actingAs($admin, 'admin');

        $journeys = [
            ['donations.search', 'donations.index', 'donations'],
            ['contact-message.search', 'contact-message.index', 'contact-messages'],
            ['sponsorships.search', 'sponsorships.index', 'sponsorships'],
            ['volunteer.search', 'volunteer.index', 'volunteers'],
            ['user-approval.search', 'user-approval.index', 'member-approvals'],
            ['user.search', 'user.index', 'users'],
            ['report.youtubeMeta.search', 'report.youtubeMeta', 'youtube-report'],
            ['subscriber.filter', 'subscriber.index', 'subscribers'],
            ['comment.search', 'comment.index', 'comments'],
            ['admin.search', 'admin.index', 'admins'],
        ];

        foreach ($journeys as [$storeRoute, $indexRoute, $scope]) {
            $privateValue = "private-{$scope}@example.test";
            $this->post(route($storeRoute), ['search' => $privateValue])
                ->assertRedirect(route($indexRoute))
                ->assertSessionDoesntHaveErrors();

            $response = $this->get(route($indexRoute));
            $response->assertOk()->assertSee($privateValue);
            $this->assertStringNotContainsString($privateValue, (string) $response->headers->get('Location'));

            $audit = AdminAuditEvent::query()->latest('id')->firstOrFail();
            $this->assertSame('private_search.started', $audit->action);
            $this->assertSame($scope, $audit->context['scope']);
            $this->assertStringNotContainsString($privateValue, json_encode($audit->toArray(), JSON_THROW_ON_ERROR));
        }

        $this->travel(11)->minutes();
        $this->get(route('subscriber.index'))
            ->assertOk()
            ->assertDontSee('private-subscribers@example.test');
        $this->travelBack();

        $this->get(route('contact-message.index', [
            'search' => 'legacy-private@example.test',
            'workflow_status' => 'completed',
        ]))->assertRedirect(route('contact-message.index', ['workflow_status' => 'completed']));
    }

    public function test_subscriber_export_streams_formula_safe_cells_and_records_only_safe_audit_context(): void
    {
        $viewer = $this->adminWithMenus(['subscriber.index', 'volunteer.index']);
        $this->actingAs($viewer, 'admin')
            ->get(route('subscriber.index'))
            ->assertOk()
            ->assertDontSee('Export as Excel');
        $this->get(route('volunteer.index'))
            ->assertOk()
            ->assertDontSee('Export filtered list');
        $this->get(route('subscriber-excel-download.index'))->assertForbidden();
        $this->get(route('volunteer.export.excel'))->assertForbidden();

        $admin = $this->adminWithMenus(
            ['subscriber.index', 'volunteer.index'],
            ['subscriber.export', 'volunteer.export']
        );
        Subscriber::create([
            'uuid' => (string) Str::uuid(),
            'email' => '+formula@example.test',
            'confirmed_at' => now(),
        ]);
        Subscriber::create([
            'uuid' => (string) Str::uuid(),
            'email' => 'excluded@example.test',
            'confirmed_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('subscriber.filter'), ['search' => 'formula@example.test'])
            ->assertRedirect(route('subscriber.index'));
        $response = $this->get(route('subscriber-excel-download.index'));
        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $content = $response->streamedContent();
        $this->assertStringContainsString("'+formula@example.test", $content);
        $this->assertStringNotContainsString('excluded@example.test', $content);

        $audit = AdminAuditEvent::query()->where('action', 'subscriber.exported')->firstOrFail();
        $this->assertSame(1, $audit->context['row_count']);
        $this->assertStringNotContainsString('+formula@example.test', json_encode($audit->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_content_purge_quarantine_restores_interrupted_media_and_audits_completed_trash_actions(): void
    {
        Storage::fake('public');
        $category = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Recoverable category',
            'slug' => 'recoverable-category',
            'language' => 'en',
            'image' => 'recoverable.jpg',
            'status' => 1,
        ]);
        Storage::disk('public')->put('photos/1/category/recoverable.jpg', 'recoverable image');

        $quarantine = app(ContentFileQuarantine::class);
        $batch = $quarantine->stage($category);
        Storage::disk('public')->assertMissing('photos/1/category/recoverable.jpg');
        $quarantine->rollback($batch);
        Storage::disk('public')->assertExists('photos/1/category/recoverable.jpg');

        $category->delete();
        $admin = $this->adminWithMenus(['content.trash.index'], [
            'content.trash.edit', 'content.trash.destroy',
        ]);
        $this->actingAs($admin, 'admin')
            ->post(route('content.trash.restore', ['category', $category->id]))
            ->assertOk();
        $this->assertNotNull($category->fresh());
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'content_trash.restored']);

        $category->delete();
        $this->deleteJson(route('content.trash.force-destroy', ['category', $category->id]))
            ->assertOk();
        $this->assertNull(Category::withTrashed()->find($category->id));
        Storage::disk('public')->assertMissing('photos/1/category/recoverable.jpg');
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'content_trash.purged']);
    }

    public function test_every_content_trash_media_mapping_quarantines_and_restores_all_known_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $cases = [
            [new AnnualReport(['image_path' => 'annual.pdf']), [['local', 'annual-reports/annual.pdf']]],
            [new Banner(['image' => 'banner.jpg']), [['public', 'photos/1/banner/banner.jpg']]],
            [new Category(['image' => 'category.jpg']), [['public', 'photos/1/category/category.jpg']]],
            [new Gallery(), [['public', 'photos/1/gallery/104/main/gallery.jpg']]],
            [new LatestNews(['image' => 'member.jpg']), [['public', 'photos/1/our_members/member.jpg']]],
            [new NoticeBoard(['image_path' => 'notice.jpg', 'file_path' => 'notice.pdf']), [
                ['public', 'photos/1/notice_board/notice.jpg'],
                ['local', 'notice-attachments/notice.pdf'],
            ]],
            [new Testimonial(['photo' => 'person.jpg']), [['public', 'photos/1/testimonial/person.jpg']]],
        ];

        foreach ($cases as $offset => [$model, $entries]) {
            $model->setAttribute($model->getKeyName(), 101 + $offset);
            foreach ($entries as [$disk, $path]) {
                Storage::disk($disk)->put($path, 'recoverable asset');
            }

            $batch = app(ContentFileQuarantine::class)->stage($model);
            $this->assertNotNull($batch);
            foreach ($entries as [$disk, $path]) {
                Storage::disk($disk)->assertMissing($path);
            }

            app(ContentFileQuarantine::class)->rollback($batch);
            foreach ($entries as [$disk, $path]) {
                Storage::disk($disk)->assertExists($path);
            }
        }
    }

    public function test_content_purge_quarantine_never_moves_media_still_referenced_by_another_row(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $bannerName = 'shared-purge-banner.jpg';
        $this->assertSharedMediaIsNotQuarantined(
            Banner::create(['name' => 'Purged banner', 'image' => $bannerName]),
            Banner::create(['name' => 'Surviving banner', 'path' => $bannerName]),
            [['public', 'photos/1/banner/' . $bannerName]],
        );

        $categoryName = 'shared-purge-category.jpg';
        $this->assertSharedMediaIsNotQuarantined(
            Category::create(['uuid' => (string) Str::uuid(), 'name' => 'Purged category', 'slug' => 'purged-category', 'image' => $categoryName]),
            Category::create(['uuid' => (string) Str::uuid(), 'name' => 'Surviving category', 'slug' => 'surviving-category', 'path' => $categoryName]),
            [['public', 'photos/1/category/' . $categoryName]],
        );

        $memberName = 'shared-purge-member.jpg';
        $this->assertSharedMediaIsNotQuarantined(
            LatestNews::create(['name' => 'Purged member', 'type' => 'our-members', 'image' => $memberName]),
            LatestNews::create(['name' => 'Surviving member', 'type' => 'our-members', 'path' => $memberName]),
            [['public', 'photos/1/our_members/' . $memberName]],
        );

        $testimonialName = 'shared-purge-testimonial.jpg';
        $this->assertSharedMediaIsNotQuarantined(
            Testimonial::create(['uuid' => (string) Str::uuid(), 'name' => 'Purged testimonial', 'photo' => $testimonialName]),
            Testimonial::create(['uuid' => (string) Str::uuid(), 'name' => 'Surviving testimonial', 'photo' => $testimonialName]),
            [['public', 'photos/1/testimonial/' . $testimonialName]],
        );

        $noticeImage = 'shared-purge-notice.jpg';
        $noticeAttachment = 'shared-purge-notice.pdf';
        $this->assertSharedMediaIsNotQuarantined(
            NoticeBoard::create(['title' => 'Purged notice', 'image_path' => $noticeImage, 'file_path' => $noticeAttachment]),
            NoticeBoard::create([
                'title' => 'Surviving notice',
                'image_path' => '/storage/photos/1/notice_board/' . $noticeImage,
                'file_path' => $noticeAttachment,
            ]),
            [
                ['public', 'photos/1/notice_board/' . $noticeImage],
                ['local', 'notice-attachments/' . $noticeAttachment],
            ],
        );

        $reportName = 'shared-purge-report.pdf';
        $this->assertSharedMediaIsNotQuarantined(
            AnnualReport::create(['title' => 'Purged report', 'image_path' => $reportName]),
            AnnualReport::create(['title' => 'Surviving report', 'file_path' => $reportName]),
            [['local', 'annual-reports/' . $reportName]],
        );

        $galleryName = 'shared-purge-gallery.jpg';
        $purgedGallery = Gallery::create(['uuid' => (string) Str::uuid(), 'name' => 'Purged gallery', 'image' => $galleryName]);
        $survivingGallery = Gallery::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Surviving gallery',
            'path' => "/storage/photos/1/gallery/{$purgedGallery->id}/main/{$galleryName}",
        ]);
        $this->assertSharedMediaIsNotQuarantined(
            $purgedGallery,
            $survivingGallery,
            [['public', "photos/1/gallery/{$purgedGallery->id}/main/{$galleryName}"]],
        );
    }

    public function test_retention_is_disabled_when_unset_and_requires_execute_after_preview(): void
    {
        config()->set('privacy.retention.contact_enquiries.days', null);
        $record = ContactMessage::create([
            'first_name' => 'Private',
            'last_name' => 'Person',
            'email' => 'retention@example.test',
            'phone' => '01700000000',
            'message' => 'Private enquiry details',
            'workflow_status' => 'completed',
            'resolved_at' => now()->subDays(90),
        ]);

        $disabled = app(PrivacyRetentionService::class)->run(true);
        $this->assertFalse($disabled['contact_enquiries']['enabled']);
        $this->assertSame('retention@example.test', $record->fresh()->email);

        config()->set('privacy.retention.contact_enquiries.days', 30);
        $preview = app(PrivacyRetentionService::class)->run(false);
        $this->assertSame(1, $preview['contact_enquiries']['eligible']);
        $this->assertSame(0, $preview['contact_enquiries']['processed']);
        $this->assertSame('retention@example.test', $record->fresh()->email);

        $executed = app(PrivacyRetentionService::class)->run(true);
        $this->assertSame(1, $executed['contact_enquiries']['processed']);
        $record->refresh();
        $this->assertNull($record->email);
        $this->assertNull($record->phone);
        $this->assertSame('[Removed by retention policy]', $record->message);
        $this->assertNotNull($record->anonymized_at);
        $audit = AdminAuditEvent::query()->where('action', 'privacy_retention.applied')
            ->where('context->policy', 'contact_enquiries')->firstOrFail();
        $this->assertSame(1, $audit->context['processed_count']);
        $this->assertStringNotContainsString('retention@example.test', json_encode($audit->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_retention_rechecks_a_reopened_record_under_lock_before_anonymizing(): void
    {
        foreach (array_keys(config('privacy.retention')) as $policy) {
            config()->set("privacy.retention.{$policy}.days", null);
        }
        config()->set('privacy.retention.contact_enquiries.days', 30);
        $record = ContactMessage::create([
            'first_name' => 'Still active',
            'email' => 'reopened@example.test',
            'message' => 'Do not erase after reopen',
            'workflow_status' => 'completed',
            'resolved_at' => now()->subDays(60),
        ]);
        $reopened = false;
        $event = 'eloquent.retrieved: ' . ContactMessage::class;
        Event::listen($event, function (ContactMessage $candidate) use ($record, &$reopened): void {
            if ($reopened || $candidate->isNot($record)) {
                return;
            }
            $reopened = true;
            DB::table('contact_messages')->where('id', $record->id)->update([
                'workflow_status' => 'in_progress',
                'resolved_at' => null,
            ]);
        });

        try {
            $results = app(PrivacyRetentionService::class)->run(true);
        } finally {
            Event::forget($event);
        }

        $this->assertTrue($reopened);
        $this->assertSame(0, $results['contact_enquiries']['processed']);
        $record->refresh();
        $this->assertSame('in_progress', $record->workflow_status);
        $this->assertSame('reopened@example.test', $record->email);
        $this->assertSame('Do not erase after reopen', $record->message);
    }

    public function test_retention_mutations_roll_back_if_the_atomic_audit_cannot_be_written(): void
    {
        foreach (array_keys(config('privacy.retention')) as $policy) {
            config()->set("privacy.retention.{$policy}.days", null);
        }
        config()->set('privacy.retention.contact_enquiries.days', 30);
        $record = ContactMessage::create([
            'first_name' => 'Rollback',
            'email' => 'rollback@example.test',
            'message' => 'Must survive an audit failure',
            'workflow_status' => 'completed',
            'resolved_at' => now()->subDays(60),
        ]);
        $event = 'eloquent.creating: ' . AdminAuditEvent::class;
        Event::listen($event, fn () => throw new \RuntimeException('Simulated audit failure'));

        try {
            app(PrivacyRetentionService::class)->run(true);
            $this->fail('The simulated audit failure should escape the retention run.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated audit failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $record->refresh();
        $this->assertSame('rollback@example.test', $record->email);
        $this->assertSame('Must survive an audit failure', $record->message);
        $this->assertNull($record->anonymized_at);
    }

    public function test_privacy_marker_migration_drops_indexes_before_columns_and_reapplies_cleanly(): void
    {
        $migration = require database_path('migrations/2026_08_21_121000_add_privacy_retention_markers.php');

        try {
            $migration->down();
            foreach (['contact_messages', 'sponsorships', 'volunteers', 'chat_conversations'] as $table) {
                $this->assertFalse(Schema::hasColumn($table, 'anonymized_at'));
            }
        } finally {
            $migration->up();
        }

        foreach (['contact_messages', 'sponsorships', 'volunteers', 'chat_conversations'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'anonymized_at'));
        }
    }

    public function test_each_explicit_retention_policy_minimizes_pii_without_removing_operational_facts(): void
    {
        foreach (array_keys(config('privacy.retention')) as $policy) {
            config()->set("privacy.retention.{$policy}.days", 30);
        }
        $old = now()->subDays(60);
        $sponsorship = Sponsorship::create([
            'name' => 'Private Sponsor', 'email' => 'sponsor-retention@example.test',
            'phone' => '01700000001', 'address' => 'Private address',
            'number_of_children' => 2, 'contribution_interval' => 'monthly',
            'sponsorship_amount' => 2500, 'transaction_id' => 'KEEP-REFERENCE-1',
            'payment_status' => 'Success', 'workflow_status' => 'completed', 'resolved_at' => $old,
        ]);
        $volunteer = Volunteer::create([
            'name' => 'Private Volunteer', 'email' => 'volunteer-retention@example.test',
            'phone' => '01700000002', 'workflow_status' => 'spam', 'resolved_at' => $old,
        ]);
        $conversation = ChatConversation::create([
            'visitor_token_hash' => hash('sha256', 'old-token'), 'guest_name' => 'Private Guest',
            'guest_email' => 'chat-retention@example.test', 'locale' => 'en',
            'status' => 'closed', 'closed_at' => $old,
        ]);
        $message = ChatMessage::create([
            'chat_conversation_id' => $conversation->id, 'sender_type' => 'visitor',
            'body' => 'A private chat message',
        ]);
        $subscriber = Subscriber::create([
            'uuid' => (string) Str::uuid(), 'email' => 'old-subscriber@example.test',
        ]);
        Subscriber::query()->whereKey($subscriber->id)->update(['created_at' => $old, 'updated_at' => $old]);

        $results = app(PrivacyRetentionService::class)->run(true);
        $this->assertSame(1, $results['sponsorship_enquiries']['processed']);
        $this->assertSame(1, $results['volunteer_applications']['processed']);
        $this->assertSame(1, $results['closed_chat']['processed']);
        $this->assertSame(1, $results['subscribers']['processed']);

        $sponsorship->refresh();
        $this->assertSame('Anonymized supporter', $sponsorship->name);
        $this->assertNull($sponsorship->phone);
        $this->assertEquals(2500, $sponsorship->sponsorship_amount);
        $this->assertSame('KEEP-REFERENCE-1', $sponsorship->transaction_id);
        $this->assertSame('Anonymized volunteer', $volunteer->fresh()->name);
        $this->assertNull($conversation->fresh()->guest_email);
        $this->assertSame('[Removed by approved retention policy]', $message->fresh()->body);
        $this->assertNull(Subscriber::find($subscriber->id));
    }

    private function adminWithMenus(array $menuLinks, array $actionLinks = []): Admin
    {
        $menus = AuthMenu::query()->whereIn('link', $menuLinks)->where('status', 1)->pluck('id');
        $actions = MenuAction::query()->whereIn('link', $actionLinks)->where('status', 1)->pluck('id');
        $this->assertCount(count($menuLinks), $menus);
        $this->assertCount(count($actionLinks), $actions);

        $role = Role::create([
            'name' => 'Privacy workflow tester ' . Str::random(8),
            'permission' => $menus->implode(','),
            'actionPermission' => $actions->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Privacy Workflow Tester',
            'username' => 'privacy-' . Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);
    }

    /** @param list<array{string, string}> $files */
    private function assertSharedMediaIsNotQuarantined(Model $purged, Model $survivor, array $files): void
    {
        $this->assertTrue($survivor->exists);
        $purged->delete();
        foreach ($files as [$disk, $path]) {
            Storage::disk($disk)->put($path, 'shared recoverable asset');
        }

        $batch = app(ContentFileQuarantine::class)->stage($purged);

        $this->assertNull($batch);
        foreach ($files as [$disk, $path]) {
            Storage::disk($disk)->assertExists($path);
        }
    }
}
