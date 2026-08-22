<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Permission;
use App\Models\SeoAuditAlert;
use App\Models\SeoAuditIgnoreRule;
use App\Models\SeoAuditIssue;
use App\Models\SeoAuditRun;
use App\Models\SeoNotFoundHit;
use App\Services\SeoManagedDestinationService;
use App\Services\SeoRedirectService;
use App\Services\TechnicalSeoAuditService;
use App\Services\TechnicalSeoEditorLinkService;
use App\Services\TechnicalSeoPathNormalizer;
use App\Services\TechnicalSeoUrlPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class TechnicalSeoController extends Controller
{
    private const ISSUE_TYPES = [
        'broken_link', 'broken_image', 'http_4xx', 'http_5xx', 'redirect_in_link',
        'redirect_page', 'orphan_page', 'duplicate_canonical', 'canonical_conflict',
        'missing_h1', 'multiple_h1', 'hreflang_mismatch', 'schema_mismatch',
        'head_mismatch', 'response_too_large',
    ];

    public function __construct(
        private TechnicalSeoAuditService $audits,
        private SeoManagedDestinationService $destinations,
        private SeoRedirectService $redirects,
        private TechnicalSeoUrlPolicy $urls,
        private TechnicalSeoPathNormalizer $privacyPaths,
        private TechnicalSeoEditorLinkService $editorLinks,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'issue_type' => ['nullable', Rule::in(self::ISSUE_TYPES)],
            'severity' => ['nullable', Rule::in(['high', 'medium', 'low'])],
            'visibility' => ['nullable', Rule::in(['open', 'ignored', 'all'])],
            'search' => ['nullable', 'string', 'max:100'],
            'not_found' => ['nullable', Rule::in(['open', 'resolved', 'all'])],
        ]);
        $latestRun = SeoAuditRun::query()->latest('id')->first();
        $ignoredRules = SeoAuditIgnoreRule::query()->get()->keyBy('fingerprint');
        $ignoredFingerprints = $ignoredRules->keys()->all();
        $issueQuery = SeoAuditIssue::query()->when($latestRun, fn ($query) => $query->where('run_id', $latestRun->id));
        if (!$latestRun) {
            $issueQuery->whereRaw('1 = 0');
        }
        $issueQuery
            ->when($filters['issue_type'] ?? null, fn ($query, $value) => $query->where('issue_type', $value))
            ->when($filters['severity'] ?? null, fn ($query, $value) => $query->where('severity', $value))
            ->when($filters['search'] ?? null, function ($query, $value): void {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
                $query->where(function ($nested) use ($escaped): void {
                    $nested->where('source_path', 'like', '%' . $escaped . '%')
                        ->orWhere('target_path', 'like', '%' . $escaped . '%')
                        ->orWhere('message', 'like', '%' . $escaped . '%');
                });
            });
        $visibility = $filters['visibility'] ?? 'open';
        if ($visibility === 'open') {
            $issueQuery->whereNotExists(fn ($ignored) => $ignored->selectRaw('1')
                ->from('seo_audit_ignore_rules')->whereColumn('seo_audit_ignore_rules.fingerprint', 'seo_audit_issues.fingerprint'));
        } elseif ($visibility === 'ignored') {
            $issueQuery->whereExists(fn ($ignored) => $ignored->selectRaw('1')
                ->from('seo_audit_ignore_rules')->whereColumn('seo_audit_ignore_rules.fingerprint', 'seo_audit_issues.fingerprint'));
        }

        $notFoundQuery = $this->visibleNotFoundQuery()
            ->when(($filters['not_found'] ?? 'open') === 'open', fn ($query) => $query->whereNull('resolved_at'))
            ->when(($filters['not_found'] ?? 'open') === 'resolved', fn ($query) => $query->whereNotNull('resolved_at'))
            ->orderByDesc('hits')->orderByDesc('last_seen_at');

        $issues = $issueQuery->orderByRaw("CASE severity WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderBy('issue_type')->paginate(25, ['*'], 'issues_page')->withQueryString();
        $notFoundHits = $notFoundQuery->paginate(15, ['*'], 'not_found_page')->withQueryString();
        $liveNotFoundHits = $notFoundHits->getCollection()->mapWithKeys(fn (SeoNotFoundHit $hit) => [
            $hit->id => $this->destinations->isManaged($hit->path, (string) $hit->locale),
        ]);
        $suggestions = $notFoundHits->getCollection()->mapWithKeys(fn (SeoNotFoundHit $hit) => [
            $hit->id => $liveNotFoundHits->get($hit->id, false)
                ? []
                : $this->destinations->suggestions($hit),
        ]);

        $latestCounts = $latestRun
            ? SeoAuditIssue::query()->where('run_id', $latestRun->id)
                ->select('severity', DB::raw('COUNT(*) AS aggregate'))->groupBy('severity')->pluck('aggregate', 'severity')
            : collect();

        $permission = app(Permission::class);
        $admin = $request->user('admin');
        $latestComparison = $latestRun?->comparisonWithPrevious() ?? [
            'has_baseline' => false,
            'previous_run_id' => null,
            'new' => 0,
            'recurring' => 0,
            'resolved' => 0,
            'new_high' => 0,
            'new_fingerprints' => [],
            'recurring_fingerprints' => [],
            'resolved_fingerprints' => [],
        ];
        $newFingerprints = array_fill_keys($latestComparison['new_fingerprints'], true);
        $recurringFingerprints = array_fill_keys($latestComparison['recurring_fingerprints'], true);
        $issueStates = $issues->getCollection()->mapWithKeys(fn (SeoAuditIssue $issue): array => [
            $issue->id => isset($newFingerprints[$issue->fingerprint])
                ? 'new'
                : (isset($recurringFingerprints[$issue->fingerprint]) ? 'recurring' : null),
        ]);
        $issueActions = $issues->getCollection()->mapWithKeys(function (SeoAuditIssue $issue) use ($permission, $admin): array {
            $actions = collect($this->editorLinks->actionsFor($issue))
                ->filter(fn (array $action): bool => empty($action['permission'])
                    || $permission->allows($admin, (string) $action['permission']))
                ->values()
                ->all();

            return [$issue->id => $actions];
        });
        $inAppAlertsEnabled = (bool) config('technical-seo.alerts.in_app_enabled', true);

        return view('admin.seo.technical', [
            'title' => 'Technical SEO & 404 Center',
            'latestRun' => $latestRun,
            'latestCounts' => $latestCounts,
            'issues' => $issues,
            'issueStates' => $issueStates,
            'issueActions' => $issueActions,
            'ignoredFingerprints' => array_fill_keys($ignoredFingerprints, true),
            'ignoredRules' => $ignoredRules,
            'notFoundHits' => $notFoundHits,
            'suggestions' => $suggestions,
            'liveNotFoundHits' => $liveNotFoundHits,
            'filters' => $filters + ['visibility' => 'open', 'not_found' => 'open'],
            'open404Count' => $this->visibleNotFoundQuery()->whereNull('resolved_at')->count(),
            'ignoredCount' => count($ignoredFingerprints),
            'latestComparison' => $latestComparison,
            'runHistory' => SeoAuditRun::query()->latest('id')->limit(12)->get(),
            'auditAlerts' => $inAppAlertsEnabled
                ? SeoAuditAlert::query()->with('run')->latest('id')->limit(6)->get()
                : collect(),
            'scheduleEnabled' => (bool) config('technical-seo.schedule_enabled'),
            'inAppAlertsEnabled' => $inAppAlertsEnabled,
            'emailAlertsEnabled' => (bool) config('technical-seo.alerts.email_enabled', false),
            'canViewMetadata' => $permission->allows($admin, 'seo.index'),
            'canScan' => $permission->allows($admin, 'seo.technical.scan'),
            'canIgnore' => $permission->allows($admin, 'seo.technical.ignore'),
            'canRedirect' => $permission->allows($admin, 'seo.technical.redirect'),
        ]);
    }

    private function visibleNotFoundQuery(): Builder
    {
        $query = SeoNotFoundHit::query();
        foreach ($this->privacyPaths->noisePrefixes() as $prefix) {
            $query->where(function (Builder $visible) use ($prefix): void {
                $visible->where('path', '!=', $prefix)
                    // SUBSTR keeps SQL wildcard characters such as the
                    // leading underscore in /_debugbar literal on both the
                    // SQLite test database and production MySQL.
                    ->whereRaw('SUBSTR(path, 1, ?) != ?', [mb_strlen($prefix . '/'), $prefix . '/']);
            });
        }

        return $query;
    }

    public function scan(Request $request)
    {
        $run = $this->audits->run('admin', $request->user('admin')?->id);
        $message = $run->status === 'failed'
            ? 'The scan stopped safely. No public website data was changed.'
            : "Technical scan finished: {$run->urls_checked} URLs checked and {$run->issues_found} findings saved.";

        return redirect()->route('seo.technical.index')->with([
            'message' => $message,
            'alert-type' => $run->status === 'failed' ? 'error' : 'success',
        ]);
    }

    public function ignore(Request $request, SeoAuditIssue $issue)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        SeoAuditIgnoreRule::query()->updateOrCreate(
            ['fingerprint' => $issue->fingerprint],
            [
                'issue_type' => $issue->issue_type,
                'source_path' => $issue->source_path,
                'target_path' => $issue->target_path,
                'reason' => $data['reason'] ?? null,
                'created_by' => $request->user('admin')?->id,
            ]
        );

        return back()->with(['message' => 'This exact finding is now ignored in future scan snapshots.', 'alert-type' => 'success']);
    }

    public function unignore(SeoAuditIgnoreRule $rule)
    {
        $rule->delete();

        return back()->with(['message' => 'The ignore rule was removed.', 'alert-type' => 'success']);
    }

    public function createRedirect(Request $request, SeoNotFoundHit $hit)
    {
        $data = $request->validate([
            'destination' => ['required', 'string', 'max:1024'],
            'status_code' => ['required', Rule::in(SeoRedirectService::SAFE_STATUS_CODES)],
        ]);
        abort_if($this->privacyPaths->containsRedaction($hit->path), 422,
            'Redacted paths cannot become redirects. Create a redirect manually from a verified non-sensitive source.');
        abort_if($this->destinations->isManaged($hit->path, (string) $hit->locale), 409,
            'This address is live managed content now. Dismiss the stale 404 record instead of creating a redirect.');
        $destination = $this->urls->internalPath($data['destination'], '/');
        abort_unless($destination !== null
            && $destination === $data['destination']
            && $this->destinations->isManaged($destination, (string) $hit->locale), 422,
            'Choose one of the managed website destinations shown for this 404.');
        $fallbackLocale = (string) config('app.fallback_locale', 'en');
        $target = $destination;
        if ($hit->locale !== $fallbackLocale) {
            $queryKey = (string) config('seo.locale_query_parameter', 'lang');
            $target .= '?' . http_build_query([$queryKey => $hit->locale], '', '&', PHP_QUERY_RFC3986);
        }

        $redirect = $this->redirects->create([
            'from_path' => $hit->path,
            'to_url' => $target,
            'status_code' => (int) $data['status_code'],
            'is_active' => true,
            'locale' => $hit->locale,
        ], $request->user('admin')?->id);
        $hit->update(['resolved_at' => now(), 'redirect_id' => $redirect->id]);

        return back()->with(['message' => 'The safe redirect is active and this 404 is resolved.', 'alert-type' => 'success']);
    }

    public function dismissNotFound(SeoNotFoundHit $hit)
    {
        $hit->update(['resolved_at' => now()]);

        return back()->with(['message' => 'The missing address was dismissed. It will reopen automatically if a visitor reaches it again.', 'alert-type' => 'success']);
    }
}
