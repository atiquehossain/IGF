<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Page;
use App\Models\Role;
use App\Models\SeoAuditAlert;
use App\Models\SeoAuditIgnoreRule;
use App\Models\SeoAuditIssue;
use App\Models\SeoAuditRun;
use App\Models\SeoNotFoundHit;
use App\Notifications\TechnicalSeoAuditNotification;
use App\Services\SeoNotFoundRecorder;
use App\Services\TechnicalSeoAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TechnicalSeoMonitoringUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_snapshots_report_new_recurring_and_resolved_findings(): void
    {
        $previous = $this->auditRun();
        $this->issue($previous, 'gone', 'high', '/gone');
        $this->issue($previous, 'recurring', 'medium', '/recurring');

        $latest = $this->auditRun();
        $this->issue($latest, 'recurring', 'medium', '/recurring');
        $this->issue($latest, 'new-high', 'high', '/new');

        $comparison = $latest->comparisonWithPrevious();

        $this->assertTrue($comparison['has_baseline']);
        $this->assertSame($previous->id, $comparison['previous_run_id']);
        $this->assertSame(1, $comparison['new']);
        $this->assertSame(1, $comparison['recurring']);
        $this->assertSame(1, $comparison['resolved']);
        $this->assertSame(1, $comparison['new_high']);
        $this->assertSame([hash('sha256', 'new-high')], $comparison['new_fingerprints']);
    }

    public function test_new_high_priority_and_failure_alerts_are_persistent_and_email_is_opt_in(): void
    {
        Notification::fake();
        config()->set('technical-seo.alerts.in_app_enabled', true);
        config()->set('technical-seo.alerts.email_enabled', true);
        config()->set('technical-seo.alerts.email_recipients', ['seo@example.test']);

        $previous = $this->auditRun();
        $latest = $this->auditRun();
        $this->issue($latest, 'new-high', 'high', '/new-high');

        $alert = app(TechnicalSeoAlertService::class)->recordFor($latest);

        $this->assertNotNull($alert);
        $this->assertSame('new_high_findings', $alert->alert_type);
        $this->assertSame('sent', $alert->email_status);
        $this->assertSame($previous->id, $alert->context['previous_run_id']);
        Notification::assertSentOnDemandTimes(TechnicalSeoAuditNotification::class, 1);

        // Calling the alert hook twice is idempotent and never duplicates mail.
        app(TechnicalSeoAlertService::class)->recordFor($latest);
        $this->assertDatabaseCount('seo_audit_alerts', 1);
        Notification::assertSentOnDemandTimes(TechnicalSeoAuditNotification::class, 1);

        config()->set('technical-seo.alerts.email_enabled', false);
        $failed = SeoAuditRun::query()->create([
            'status' => 'failed',
            'trigger' => 'command',
            'started_at' => now(),
            'completed_at' => now(),
            'failure_message' => 'Safe failure',
        ]);
        $failureAlert = app(TechnicalSeoAlertService::class)->recordFor($failed);
        $this->assertSame('scan_failed', $failureAlert?->alert_type);
        $this->assertSame('disabled', $failureAlert?->email_status);
    }

    public function test_missing_email_recipient_never_breaks_the_in_app_alert(): void
    {
        Notification::fake();
        config()->set('technical-seo.alerts.in_app_enabled', true);
        config()->set('technical-seo.alerts.email_enabled', true);
        config()->set('technical-seo.alerts.email_recipients', ['not-an-email']);

        $latest = $this->auditRun();
        $this->issue($latest, 'new-high', 'high', '/new-high');
        $alert = app(TechnicalSeoAlertService::class)->recordFor($latest);

        $this->assertSame('skipped', $alert?->email_status);
        $this->assertSame('No valid alert recipient is configured.', $alert?->email_failure);
        Notification::assertNothingSent();
    }

    public function test_an_exact_ignore_rule_suppresses_new_high_priority_alert_noise(): void
    {
        config()->set('technical-seo.alerts.in_app_enabled', true);
        config()->set('technical-seo.alerts.email_enabled', false);
        $latest = $this->auditRun();
        $issue = $this->issue($latest, 'known-safe-high', 'high', '/known-safe-high');
        SeoAuditIgnoreRule::query()->create([
            'fingerprint' => $issue->fingerprint,
            'issue_type' => $issue->issue_type,
            'source_path' => $issue->source_path,
            'reason' => 'Reviewed exception',
        ]);

        $this->assertNull(app(TechnicalSeoAlertService::class)->recordFor($latest));
        $this->assertDatabaseCount('seo_audit_alerts', 0);
    }

    public function test_admin_center_exposes_monitoring_history_comparison_actions_and_hides_framework_404_noise(): void
    {
        config()->set('technical-seo.schedule_enabled', true);
        config()->set('technical-seo.alerts.in_app_enabled', true);
        [$admin] = $this->ownerAdmin();
        $page = Page::query()->create([
            'name' => 'Fix me',
            'sub_title' => '',
            'slug' => 'fix-me',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
        ]);

        $previous = $this->auditRun();
        $this->issue($previous, 'resolved', 'medium', '/resolved');
        $latest = $this->auditRun();
        $this->issue($latest, 'new-page-problem', 'high', '/page/fix-me');
        SeoAuditAlert::query()->create([
            'run_id' => $latest->id,
            'alert_type' => 'new_high_findings',
            'severity' => 'high',
            'title' => '1 new high-priority SEO finding',
            'message' => 'Review this finding.',
        ]);
        $this->notFound('/_debugbar/open-handler', 9);
        $this->notFound('/xdebugbar/real-visitor-path', 3);
        $this->notFound('/real-missing-page', 2);

        $response = $this->actingAs($admin, 'admin')->get(route('seo.technical.index'));

        $response->assertOk()
            ->assertViewHas('open404Count', 2)
            ->assertSee('Weekly schedule enabled')
            ->assertSee('New since previous scan')
            ->assertSee('Resolved since previous scan')
            ->assertSee('Monitoring history')
            ->assertSee('Needs review')
            ->assertSee('Edit page content')
            ->assertSee(route('page.edit', $page->id), false)
            ->assertSee(route('seo.content.edit', ['type' => 'page', 'id' => $page->id, 'locale' => 'en']), false)
            ->assertSee('/real-missing-page')
            ->assertSee('/xdebugbar/real-visitor-path')
            ->assertDontSee('/_debugbar/open-handler');

        $clean = $this->auditRun();

        $this->actingAs($admin, 'admin')->get(route('seo.technical.index'))
            ->assertOk()
            ->assertSee('Resolved by clean scan #' . $clean->id)
            ->assertDontSee('Needs review');
    }

    public function test_framework_utility_paths_are_discarded_before_404_storage(): void
    {
        $request = Request::create('/_debugbar/not-a-public-page?token=secret', 'GET');
        app(SeoNotFoundRecorder::class)->record($request);

        $this->assertDatabaseCount('seo_not_found_hits', 0);
    }

    private function auditRun(): SeoAuditRun
    {
        return SeoAuditRun::query()->create([
            'status' => 'completed',
            'trigger' => 'command',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    private function issue(SeoAuditRun $run, string $identity, string $severity, string $path): SeoAuditIssue
    {
        return SeoAuditIssue::query()->create([
            'run_id' => $run->id,
            'fingerprint' => hash('sha256', $identity),
            'issue_type' => 'broken_link',
            'severity' => $severity,
            'source_path' => $path,
            'message' => 'A test finding.',
        ]);
    }

    private function notFound(string $path, int $hits): SeoNotFoundHit
    {
        return SeoNotFoundHit::query()->create([
            'scope_hash' => hash('sha256', 'en|' . $path),
            'path_hash' => hash('sha256', $path),
            'path' => $path,
            'locale' => 'en',
            'hits' => $hits,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    /** @return array{0:Admin,1:Role} */
    private function ownerAdmin(): array
    {
        $role = Role::query()->create([
            'name' => 'Technical SEO owner',
            'is_owner' => true,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $admin = Admin::query()->create([
            'name' => 'Technical SEO owner',
            'username' => 'technical-seo-owner',
            'email' => 'technical-seo-owner@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);

        return [$admin, $role];
    }
}
