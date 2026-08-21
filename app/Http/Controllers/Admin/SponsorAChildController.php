<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\UpdatesEnquiryWorkflow;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Sponsorship;
use App\Services\AdminPrivateSearch;

class SponsorAChildController extends Controller
{
    use UpdatesEnquiryWorkflow;

    public function __construct(private AdminPrivateSearch $privateSearch)
    {
    }

    // Show Sponsorship Records
    public function index(Request $request)
    {
        if ($request->query->has('search')) {
            return redirect()->route('sponsorships.index', $request->only('workflow_status'));
        }

        $search = $this->privateSearch->current($request, 'sponsorships');
        $status = trim((string) $request->input('workflow_status', ''));

        $sponsorships = Sponsorship::query()
            ->with('assignedAdmin:id,name,email')
            ->when($search !== '', function ($query) use ($search) {
                $pattern = '%' . $search . '%';
                $query->where(function ($fields) use ($pattern) {
                    $fields->where('name', 'like', $pattern)
                        ->orWhere('email', 'like', $pattern)
                        ->orWhere('phone', 'like', $pattern)
                        ->orWhere('transaction_id', 'like', $pattern);
                });
            })
            ->when(array_key_exists($status, $this->workflowStatuses()), fn ($query) => $query->where('workflow_status', $status))
            ->orderBy('id', 'desc')
            ->paginate(20);

        return view('admin.sponsorships.index', [
            'title' => 'Sponsorship Records',
            'sponsorships' => $sponsorships,
            'search' => $search,
            'selectedStatus' => $status,
            'workflowStatuses' => $this->workflowStatuses(),
            'assignees' => $this->activeWorkflowAssignees(),
        ]);
    }

    public function updateWorkflow(Request $request, Sponsorship $sponsorship)
    {
        return $this->persistWorkflow($request, $sponsorship);
    }
}
