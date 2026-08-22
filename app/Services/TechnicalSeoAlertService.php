<?php

namespace App\Services;

use App\Models\SeoAuditAlert;
use App\Models\SeoAuditIgnoreRule;
use App\Models\SeoAuditRun;
use App\Notifications\TechnicalSeoAuditNotification;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class TechnicalSeoAlertService
{
    public function recordFor(SeoAuditRun $run): ?SeoAuditAlert
    {
        $inAppEnabled = (bool) config('technical-seo.alerts.in_app_enabled', true);
        $emailEnabled = (bool) config('technical-seo.alerts.email_enabled', false);
        if (!$inAppEnabled && !$emailEnabled) {
            return null;
        }

        $payload = $this->payload($run);
        if ($payload === null) {
            return null;
        }

        $alert = SeoAuditAlert::query()->firstOrCreate(
            ['run_id' => $run->id, 'alert_type' => $payload['alert_type']],
            $payload + ['email_status' => $emailEnabled ? 'pending' : 'disabled']
        );

        if ($emailEnabled && $alert->email_status !== 'sent') {
            $this->sendEmail($alert);
        }

        return $alert->fresh();
    }

    /** @return array<string,mixed>|null */
    private function payload(SeoAuditRun $run): ?array
    {
        if ($run->status === 'failed') {
            return [
                'alert_type' => 'scan_failed',
                'severity' => 'high',
                'title' => 'Technical SEO scan failed',
                'message' => 'The technical SEO scan stopped safely. No website content was changed; review the application log and retry the scan.',
                'context' => ['run_id' => $run->id],
            ];
        }

        if (!$run->isCompletedSnapshot()) {
            return null;
        }

        $comparison = $run->comparisonWithPrevious();
        $newFingerprints = (array) $comparison['new_fingerprints'];
        if ($newFingerprints === []) {
            return null;
        }

        $ignored = SeoAuditIgnoreRule::query()
            ->whereIn('fingerprint', $newFingerprints)
            ->pluck('fingerprint')
            ->flip();
        $newHighCount = $run->issues()
            ->where('severity', 'high')
            ->whereIn('fingerprint', $newFingerprints)
            ->get(['fingerprint'])
            ->reject(fn ($issue): bool => $ignored->has($issue->fingerprint))
            ->count();
        if ($newHighCount === 0) {
            return null;
        }

        return [
            'alert_type' => 'new_high_findings',
            'severity' => 'high',
            'title' => $newHighCount . ' new high-priority SEO ' . str('finding')->plural($newHighCount),
            'message' => $newHighCount . ' new high-priority technical SEO ' . str('finding')->plural($newHighCount)
                . ' need review after scan #' . $run->id . '.',
            'context' => [
                'run_id' => $run->id,
                'previous_run_id' => $comparison['previous_run_id'],
                'new_high_count' => $newHighCount,
            ],
        ];
    }

    private function sendEmail(SeoAuditAlert $alert): void
    {
        $recipients = collect((array) config('technical-seo.alerts.email_recipients', []))
            ->map(fn (mixed $address): string => trim((string) $address))
            ->filter(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->take(10)
            ->values()
            ->all();

        if ($recipients === []) {
            $alert->update([
                'email_status' => 'skipped',
                'email_attempted_at' => now(),
                'email_failure' => 'No valid alert recipient is configured.',
            ]);
            return;
        }

        try {
            Notification::route('mail', $recipients)->notify(
                new TechnicalSeoAuditNotification($alert->title, $alert->message)
            );
            $alert->update([
                'email_status' => 'sent',
                'email_attempted_at' => now(),
                'email_failure' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $alert->update([
                'email_status' => 'failed',
                'email_attempted_at' => now(),
                'email_failure' => 'Delivery failed safely. Check the application mail configuration and log.',
            ]);
        }
    }
}
