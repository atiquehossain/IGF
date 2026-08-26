<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecruitmentWorkshopInventoryAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_opportunity_routes_are_anonymous_and_have_no_applicant_account_surface(): void
    {
        $expected = [
            'frontend.jobs.index' => ['GET', 'careers'],
            'frontend.jobs.show' => ['GET', 'careers/{job}'],
            'frontend.jobs.apply' => ['POST', 'careers/{job}/apply'],
            'frontend.workshops.index' => ['GET', 'workshops'],
            'frontend.workshops.show' => ['GET', 'workshops/{workshop}'],
            'frontend.workshops.register' => ['POST', 'workshops/{workshop}/register'],
        ];
        $actual = [];
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (!str_starts_with($name, 'frontend.jobs.') && !str_starts_with($name, 'frontend.workshops.')) {
                continue;
            }
            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $actual[$name] = [$methods[0] ?? '', $route->uri()];
            $middleware = $route->gatherMiddleware();
            $this->assertNotContains('auth', $middleware);
            $this->assertNotContains('auth:web', $middleware);
            $this->assertNotContains('auth:admin', $middleware);
        }
        $this->assertSame($expected, $actual);

        foreach (array_keys($actual) as $name) {
            $this->assertDoesNotMatchRegularExpression('/login|account|dashboard|profile|magic|password|email/i', $name);
        }
        foreach ([
            \App\Models\Applicant::class,
            'App\\Models\\ApplicantAccount',
            'App\\Models\\ApplicantUser',
            'App\\Http\\Controllers\\ApplicantAuthController',
        ] as $class) {
            $this->assertFalse(class_exists($class), "Out-of-scope applicant identity class exists: {$class}");
        }
        foreach (['applicants', 'applicant_accounts', 'applicant_users', 'applicant_sessions', 'applicant_password_resets'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Out-of-scope applicant account table exists: {$table}");
        }
    }

    public function test_workshop_schema_and_routes_exclude_payment_tracking_qr_certificates_and_feedback(): void
    {
        foreach ([
            'price', 'fee', 'currency', 'payment_status', 'payment_reference', 'payment_url',
            'attendance_status', 'attended_at', 'checked_in_at', 'qr_code', 'qr_token',
            'certificate_id', 'certificate_url', 'feedback', 'feedback_score',
        ] as $column) {
            $this->assertFalse(Schema::hasColumn('workshops', $column), "Out-of-scope workshops.{$column} exists.");
            $this->assertFalse(Schema::hasColumn('workshop_registrations', $column), "Out-of-scope workshop_registrations.{$column} exists.");
        }
        foreach ([
            'workshop_payments', 'workshop_attendance', 'workshop_checkins', 'workshop_qr_codes',
            'workshop_certificates', 'workshop_feedback', 'workshop_feedback_responses',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Out-of-scope workshop table exists: {$table}");
        }

        $workshopRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains((string) $route->getName(), 'workshop'))
            ->map(fn ($route): string => strtolower((string) $route->getName() . ' ' . $route->uri()))
            ->implode("\n");
        $this->assertDoesNotMatchRegularExpression('/payment|checkout|attendance|check-?in|qr|certificate|feedback/', $workshopRoutes);
    }

    public function test_no_automatic_retention_or_email_job_is_configured_for_applications_and_registrations(): void
    {
        $retention = (array) config('privacy.retention', []);
        foreach (['job_applications', 'recruitment_applications', 'workshop_registrations'] as $key) {
            $this->assertArrayNotHasKey($key, $retention);
        }

        $events = collect(app(Schedule::class)->events())
            ->map(fn ($event): string => strtolower((string) $event->command . ' ' . ($event->description ?? '')))
            ->implode("\n");
        $this->assertDoesNotMatchRegularExpression(
            '/(job[_:-]?application|recruitment[_:-]?application|workshop[_:-]?registration).*(delete|purge|retain|email|mail)|'.
            '(delete|purge|retain|email|mail).*(job[_:-]?application|recruitment[_:-]?application|workshop[_:-]?registration)/',
            $events,
        );
    }
}
