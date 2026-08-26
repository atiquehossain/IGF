<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminPrivateSearch;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PrivateListingSearchController extends Controller
{
    private const TARGETS = [
        'donations' => 'donations.index',
        'contact-messages' => 'contact-message.index',
        'sponsorships' => 'sponsorships.index',
        'volunteers' => 'volunteer.index',
        'member-approvals' => 'user-approval.index',
        'users' => 'user.index',
        'youtube-report' => 'report.youtubeMeta',
        'subscribers' => 'subscriber.index',
        'comments' => 'comment.index',
        'admins' => 'admin.index',
        'recruitment-applications' => 'recruitment.applications.index',
        'workshop-registrations' => 'workshop.registrations.index',
    ];

    public function __construct(
        private AdminPrivateSearch $searches,
        private AdminAuditService $audit
    )
    {
    }

    public function store(Request $request, string $scope): RedirectResponse
    {
        $route = self::TARGETS[$scope] ?? null;
        abort_unless($route, 404);

        $validated = $request->validate([
            'search' => ['required', 'string', 'max:100'],
        ]);
        if ($this->searches->store($request, $scope, $validated['search']) === '') {
            return back()->withErrors(['search' => 'Enter a name, contact detail, reference or message to search.']);
        }
        $this->audit->record(
            $request->user('admin'),
            'private_search.started',
            'private-listing-search',
            context: ['scope' => $scope, 'expires_in_minutes' => 10]
        );

        return redirect()->route($route);
    }

    public function clear(Request $request, string $scope): RedirectResponse
    {
        $route = self::TARGETS[$scope] ?? null;
        abort_unless($route, 404);
        $this->searches->forget($request, $scope);
        $this->audit->record(
            $request->user('admin'),
            'private_search.cleared',
            'private-listing-search',
            context: ['scope' => $scope]
        );

        return redirect()->route($route);
    }
}
