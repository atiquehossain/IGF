<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\AdminAuditService;
use App\Services\AdminPrivateSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PageCommentSearchController extends Controller
{
    public function __construct(
        private AdminPrivateSearch $searches,
        private AdminAuditService $audit
    ) {
    }

    public function store(Request $request, string $id): RedirectResponse
    {
        $page = $this->page($id);
        $validated = $request->validate(['search' => ['required', 'string', 'max:100']]);
        $scope = 'page-comments:' . $page->uuid;
        if ($this->searches->store($request, $scope, $validated['search']) === '') {
            return back()->withErrors(['search' => 'Enter comment text to search.']);
        }
        $this->audit->record($request->user('admin'), 'private_search.started', $page, context: [
            'scope' => 'page-comments',
            'expires_in_minutes' => 10,
        ]);

        return redirect()->route('page.view', ['id' => $page->uuid]);
    }

    public function clear(Request $request, string $id): RedirectResponse
    {
        $page = $this->page($id);
        $this->searches->forget($request, 'page-comments:' . $page->uuid);
        $this->audit->record($request->user('admin'), 'private_search.cleared', $page, context: [
            'scope' => 'page-comments',
        ]);

        return redirect()->route('page.view', ['id' => $page->uuid]);
    }

    private function page(string $uuid): Page
    {
        return Page::query()
            ->where('uuid', $uuid)
            ->where('language', app()->getLocale())
            ->firstOrFail();
    }
}
