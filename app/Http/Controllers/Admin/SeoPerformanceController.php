<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SeoPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SeoPerformanceController extends Controller
{
    public function __construct(private SeoPerformanceService $performance)
    {
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'days' => ['nullable', 'integer', Rule::in([7, 28, 90])],
        ]);
        $days = (int) ($data['days'] ?? 28);

        return view('admin.seo.performance', [
            'title' => 'Search Performance',
            'report' => $this->performance->report($days),
            'days' => $days,
            'configuration' => [
                'enabled' => (bool) config('seo-performance.enabled'),
                'search_console_site' => $this->safePropertyLabel((string) config('seo-performance.search_console.site_url')),
                'analytics_property' => trim((string) config('seo-performance.analytics.property_id')),
            ],
        ]);
    }

    public function refresh(Request $request)
    {
        $data = $request->validate([
            'days' => ['required', 'integer', Rule::in([7, 28, 90])],
        ]);
        $days = (int) $data['days'];
        $report = $this->performance->report($days, true);
        $connected = collect([$report['search_console'], $report['analytics']])
            ->contains(fn (array $source): bool => $source['status'] === 'connected');

        return redirect()->route('seo.performance.index', ['days' => $days])->with([
            'message' => $connected
                ? 'Search performance data was refreshed.'
                : 'No performance source could be refreshed. Review the connection guidance below.',
            'alert-type' => $connected ? 'success' : 'warning',
        ]);
    }

    private function safePropertyLabel(string $value): string
    {
        $value = trim($value);
        if (str_starts_with($value, 'sc-domain:')) {
            return mb_substr($value, 0, 255);
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? mb_substr($value, 0, 255) : '';
    }
}
