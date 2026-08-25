<?php

namespace Tests\Feature;

use App\Mail\ConfirmNewsletterSubscription;
use App\Mail\SubscriberNotification;
use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use App\Models\Subscriber;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class NewsletterSubscriptionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_MESSAGE = 'If this address can receive updates, a confirmation link has been sent.';

    public function test_subscription_requires_explicit_consent_and_uses_an_opaque_signed_double_opt_in_link(): void
    {
        Mail::fake();

        $confirmationRoute = app('router')->getRoutes()->getByName('frontend.subscribe.confirm');
        $this->assertContains('signed', $confirmationRoute->gatherMiddleware());
        $this->assertContains('throttle:newsletter-confirm', $confirmationRoute->gatherMiddleware());

        $this->from(route('frontend.home'))->post(route('frontend.subscribe'), [
            'email' => 'Visitor@Example.Test',
        ])->assertRedirect(route('frontend.home'))
            ->assertSessionHasErrors('consent');

        $this->assertDatabaseCount('subscribers', 0);
        Mail::assertNothingSent();

        $response = $this->from(route('frontend.home'))->post(route('frontend.subscribe'), [
            'email' => ' Visitor@Example.Test ',
            'consent' => true,
        ])->assertRedirect(route('frontend.home'))
            ->assertSessionHas('message.text', self::GENERIC_MESSAGE);

        $subscriber = Subscriber::query()->sole();
        $this->assertSame('visitor@example.test', $subscriber->email);
        $this->assertNull($subscriber->confirmed_at);
        $this->assertNotNull($subscriber->confirmation_sent_at);
        $this->assertTrue(Str::isUuid($subscriber->uuid));

        $confirmationUrl = null;
        Mail::assertSent(ConfirmNewsletterSubscription::class, function (
            ConfirmNewsletterSubscription $mail
        ) use (&$confirmationUrl, $subscriber): bool {
            $confirmationUrl = $mail->confirmationUrl();

            return $mail->hasTo($subscriber->email);
        });

        $this->assertIsString($confirmationUrl);
        $this->assertStringNotContainsString($subscriber->email, $confirmationUrl);
        $this->assertStringNotContainsString(urlencode($subscriber->email), $confirmationUrl);

        $this->get($confirmationUrl)
            ->assertRedirect(route('frontend.home'))
            ->assertSessionHas('message.text', 'Your email subscription is confirmed.');

        $this->assertNotNull($subscriber->fresh()->confirmed_at);

        $this->from(route('frontend.home'))->post(route('frontend.subscribe'), [
            'email' => 'visitor@example.test',
            'consent' => true,
        ])->assertRedirect(route('frontend.home'))
            ->assertSessionHas('message.text', self::GENERIC_MESSAGE);

        Mail::assertSentCount(1);
    }

    public function test_unconfirmed_resubmissions_are_generic_rate_limited_by_cooldown_and_invalid_links_do_not_confirm(): void
    {
        Mail::fake();

        $payload = [
            'email' => 'unconfirmed@example.test',
            'consent' => 'on',
        ];
        $this->from(route('frontend.home'))->post(route('frontend.subscribe'), $payload)
            ->assertSessionHas('message.text', self::GENERIC_MESSAGE);
        $this->from(route('frontend.home'))->post(route('frontend.subscribe'), $payload)
            ->assertSessionHas('message.text', self::GENERIC_MESSAGE);

        $subscriber = Subscriber::query()->sole();
        $confirmationUrl = null;
        Mail::assertSent(ConfirmNewsletterSubscription::class, function (
            ConfirmNewsletterSubscription $mail
        ) use (&$confirmationUrl): bool {
            $confirmationUrl = $mail->confirmationUrl();

            return true;
        });
        Mail::assertSentCount(1);

        $this->get($confirmationUrl . '&tampered=1')->assertForbidden();
        $this->assertNull($subscriber->fresh()->confirmed_at);

        $this->travel(
            (int) config('privacy.newsletter.confirmation_ttl_minutes', 1440) + 1
        )->minutes();
        $this->get($confirmationUrl)->assertForbidden();
        $this->assertNull($subscriber->fresh()->confirmed_at);
    }

    public function test_confirmation_delivery_migration_rolls_back_and_reapplies_cleanly(): void
    {
        $migration = require database_path(
            'migrations/2026_08_25_150000_add_newsletter_confirmation_delivery.php'
        );

        try {
            $migration->down();
            $this->assertFalse(Schema::hasColumn('subscribers', 'confirmation_sent_at'));
        } finally {
            $migration->up();
        }

        $this->assertTrue(Schema::hasColumn('subscribers', 'confirmation_sent_at'));
    }

    public function test_admin_listing_export_and_send_use_only_existing_confirmed_subscriber_identities(): void
    {
        Mail::fake();
        $this->seed(DatabaseSeeder::class);

        $confirmed = Subscriber::create([
            'uuid' => (string) Str::uuid(),
            'email' => 'confirmed-subscriber@example.test',
            'confirmed_at' => now(),
        ]);
        $unconfirmed = Subscriber::create([
            'uuid' => (string) Str::uuid(),
            'email' => 'unconfirmed-subscriber@example.test',
        ]);
        $admin = $this->subscriberAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('subscriber.index'))
            ->assertOk()
            ->assertSee($confirmed->email)
            ->assertDontSee($unconfirmed->email)
            ->assertSee(route('subscriber.sendEmail', ['subscriber' => $confirmed->uuid]), false);

        $export = $this->get(route('subscriber-excel-download.index'))->assertOk();
        $content = $export->streamedContent();
        $this->assertStringContainsString($confirmed->email, $content);
        $this->assertStringNotContainsString($unconfirmed->email, $content);

        $this->post(route('subscriber.sendEmail', ['subscriber' => $unconfirmed->uuid]), [
            'subject' => 'Should not send',
            'message' => 'Unconfirmed recipients are excluded.',
        ])->assertNotFound();
        Mail::assertNothingSent();

        $this->post('/admin/subscriber/send-email', [
            'email' => 'arbitrary-recipient@example.test',
            'subject' => 'Legacy arbitrary send',
            'message' => 'This endpoint no longer exists.',
        ])->assertStatus(405);
        Mail::assertNothingSent();

        $captured = null;
        $this->post(route('subscriber.sendEmail', ['subscriber' => $confirmed->uuid]), [
            'email' => 'arbitrary-recipient@example.test',
            'subject' => 'Confirmed newsletter',
            'message' => '<script>alert(1)</script>\nCommunity update',
        ])->assertOk()
            ->assertJson(['message' => 'Email sent successfully.']);

        Mail::assertSent(SubscriberNotification::class, function (
            SubscriberNotification $mail
        ) use (&$captured, $confirmed): bool {
            $captured = $mail;

            return $mail->hasTo($confirmed->email)
                && !$mail->hasTo('arbitrary-recipient@example.test');
        });

        $rendered = $captured->render();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $rendered);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $rendered);

        $audit = AdminAuditEvent::query()
            ->where('action', 'subscriber.email_sent')
            ->sole();
        $this->assertSame((string) $confirmed->id, $audit->target_id);
        $encodedAudit = json_encode($audit->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($confirmed->email, $encodedAudit);
        $this->assertStringNotContainsString('arbitrary-recipient@example.test', $encodedAudit);
        $this->assertStringNotContainsString('Community update', $encodedAudit);
    }

    private function subscriberAdmin(): Admin
    {
        $menu = AuthMenu::query()
            ->where('link', 'subscriber.index')
            ->where('status', 1)
            ->firstOrFail();
        $actions = MenuAction::query()
            ->whereIn('link', ['subscriber.sendEmail', 'subscriber.export'])
            ->where('status', 1)
            ->pluck('id');
        $this->assertCount(2, $actions);

        $role = Role::create([
            'name' => 'Confirmed Subscriber Security Tester',
            'permission' => (string) $menu->id,
            'actionPermission' => $actions->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Subscriber Security Tester',
            'username' => 'subscriber-security-tester',
            'email' => 'subscriber-security-admin@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
