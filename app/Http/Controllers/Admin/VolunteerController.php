<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\UpdatesEnquiryWorkflow;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Volunteer;
use App\Services\AdminPrivateSearch;
use App\Services\AdminAuditService;

class VolunteerController extends Controller
{
    use UpdatesEnquiryWorkflow;

    public function __construct(
        private AdminPrivateSearch $privateSearch,
        private AdminAuditService $audit
    )
    {
    }

    public function index(Request $request)
    {
        $title = "Volunteer Registrations";

        if ($request->query->has('search') || $request->query->has('email')) {
            return redirect()->route('volunteer.index', $request->only([
                'workflow_status', 'from_date', 'to_date',
            ]));
        }

        $search = $this->privateSearch->current($request, 'volunteers');
        $status = trim((string) $request->input('workflow_status', ''));
        $from_date = date('Y-m-d', strtotime($request->from_date ?? '2000-01-01'));
        $to_date = date('Y-m-d', strtotime($request->to_date ?? date('Y-m-d')));

        $volunteers = Volunteer::with(['cause', 'assignedAdmin:id,name,email'])
            ->whereDate('created_at', '>=', $from_date)
            ->whereDate('created_at', '<=', $to_date)
            ->when($search, function ($query) use ($search) {
                $pattern = '%' . $search . '%';
                return $query->where(function ($fields) use ($pattern) {
                    $fields->where('name', 'like', $pattern)
                        ->orWhere('email', 'like', $pattern)
                        ->orWhere('phone', 'like', $pattern)
                        ->orWhere('institution', 'like', $pattern);
                });
            })
            ->when(array_key_exists($status, $this->workflowStatuses()), fn ($query) => $query->where('workflow_status', $status))
            ->orderBy('id', 'desc')
            ->paginate(20);

        return view('admin.volunteer.index', [
            'title' => $title,
            'volunteers' => $volunteers,
            'search' => $search,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'selectedStatus' => $status,
            'workflowStatuses' => $this->workflowStatuses(),
            'assignees' => $this->activeWorkflowAssignees(),
        ]);
    }

    public function exportExcel(Request $request)
    {
        if ($request->query->has('search') || $request->query->has('email')) {
            return redirect()->route('volunteer.index', $request->only([
                'workflow_status', 'from_date', 'to_date',
            ]));
        }

        $search = $this->privateSearch->current($request, 'volunteers');
        $status = trim((string) $request->input('workflow_status', ''));
        $status = array_key_exists($status, $this->workflowStatuses()) ? $status : '';
        $from_date = date('Y-m-d', strtotime($request->from_date ?? '2000-01-01'));
        $to_date = date('Y-m-d', strtotime($request->to_date ?? date('Y-m-d')));

        $volunteers = Volunteer::with('cause')
            ->whereDate('created_at', '>=', $from_date)
            ->whereDate('created_at', '<=', $to_date)
            ->when($search, function ($query) use ($search) {
                $pattern = '%' . $search . '%';
                return $query->where(function ($fields) use ($pattern) {
                    $fields->where('name', 'like', $pattern)
                        ->orWhere('email', 'like', $pattern)
                        ->orWhere('phone', 'like', $pattern)
                        ->orWhere('institution', 'like', $pattern);
                });
            })
            ->when($status !== '', fn ($query) => $query->where('workflow_status', $status));
        $rowCount = (clone $volunteers)->count();

        $this->audit->record(
            $request->user('admin'),
            'volunteer.exported',
            'volunteer-list',
            context: [
                'row_count' => $rowCount,
                'private_search_active' => $search !== '',
                'workflow_status' => $status,
                'from_date' => $from_date,
                'to_date' => $to_date,
            ]
        );

        $fileName = "volunteer-registration_" . date('Y-m-d') . ".xls";

        return response()->streamDownload(function () use ($volunteers, $rowCount): void {
            echo "\xEF\xBB\xBF";
            echo "Name\tInstitution\tEmail\tContact No\tAddress\tCause\tRegistered At\n";
            (clone $volunteers)->orderBy('id')->chunkById(500, function ($records): void {
                foreach ($records as $data) {
                    echo implode("\t", array_map([self::class, 'safeSpreadsheetCell'], [
                        $data->name,
                        $data->institution,
                        $data->email,
                        $data->phone,
                        $data->address,
                        $data->cause?->name,
                        $data->created_at?->format('d-m-Y H:i A'),
                    ])) . "\n";
                }
            });
            if ($rowCount === 0) {
                echo "No records found...\n";
            }
        }, $fileName, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updateWorkflow(Request $request, Volunteer $volunteer)
    {
        return $this->persistWorkflow($request, $volunteer);
    }

    public static function safeSpreadsheetCell(mixed $value): string
    {
        $cell = str_replace(["\t", "\r", "\n"], ' ', (string) $value);

        return preg_match('/^[=+\-@]/', $cell) ? "'" . $cell : $cell;
    }
}
