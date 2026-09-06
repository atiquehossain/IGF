<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\UpdatesEnquiryWorkflow;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Volunteer;
use App\Services\AdminPrivateSearch;
use App\Services\AdminAuditService;
use App\Support\VolunteerApplicationOptions;
use Illuminate\Database\Eloquent\Builder;

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

        $volunteers = $this->applySearch(
            Volunteer::with([
                'cause',
                'division:id,name',
                'district:id,name',
                'upazila:id,name',
                'assignedAdmin:id,name,email',
            ])
                ->whereDate('created_at', '>=', $from_date)
                ->whereDate('created_at', '<=', $to_date),
            $search
        )
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

        $volunteers = $this->applySearch(
            Volunteer::with([
                'cause:id,name',
                'division:id,name',
                'district:id,name',
                'upazila:id,name',
            ])
                ->whereDate('created_at', '>=', $from_date)
                ->whereDate('created_at', '<=', $to_date),
            $search
        )
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
            echo "Name\tSex\tDate of Birth\tInstitution\tEmail\tContact No\tAddress\tDivision\tDistrict\tUpazila\tOccupation\tEducation Level\tBlood Group\tEmergency Response Training\tSkill\tCause\tConsent Version\tConsent Language\tConsent Text SHA-256\tConsent Wording\tConsented At\tRegistered At\n";
            (clone $volunteers)->orderBy('id')->chunkById(500, function ($records): void {
                foreach ($records as $data) {
                    echo implode("\t", array_map([self::class, 'safeSpreadsheetCell'], [
                        $data->name,
                        self::optionLabel('sex', $data->sex),
                        $data->date_of_birth?->format('d-m-Y'),
                        $data->institution,
                        $data->email,
                        $data->phone,
                        $data->address,
                        $data->division?->name,
                        $data->district?->name,
                        $data->upazila?->name,
                        self::optionWithOther('occupations', $data->occupation, $data->occupation_other),
                        self::optionLabel('education_levels', $data->education_level),
                        self::optionLabel('blood_groups', $data->blood_group),
                        self::emergencyTrainingLabel($data->emergency_response_training),
                        self::optionWithOther('skills', $data->skill, $data->skill_other),
                        $data->cause?->name,
                        $data->consent_version,
                        $data->consent_locale,
                        $data->consent_text_hash,
                        $data->consent_text_snapshot,
                        $data->consented_at?->format('d-m-Y H:i A'),
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

    private function applySearch(Builder $query, string $search): Builder
    {
        if ($search === '') {
            return $query;
        }

        $pattern = '%' . $search . '%';

        return $query->where(function (Builder $fields) use ($pattern): void {
            $fields->where('name', 'like', $pattern)
                ->orWhere('email', 'like', $pattern)
                ->orWhere('phone', 'like', $pattern)
                ->orWhere('institution', 'like', $pattern)
                ->orWhere('address', 'like', $pattern)
                ->orWhere('occupation', 'like', $pattern)
                ->orWhere('occupation_other', 'like', $pattern)
                ->orWhere('education_level', 'like', $pattern)
                ->orWhere('blood_group', 'like', $pattern)
                ->orWhere('skill', 'like', $pattern)
                ->orWhere('skill_other', 'like', $pattern)
                ->orWhereHas('division', fn (Builder $relation) => $relation->where('name', 'like', $pattern))
                ->orWhereHas('district', fn (Builder $relation) => $relation->where('name', 'like', $pattern))
                ->orWhereHas('upazila', fn (Builder $relation) => $relation->where('name', 'like', $pattern));
        });
    }

    private static function optionLabel(string $group, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Not provided';
        }

        return VolunteerApplicationOptions::label($group, $value) ?? (string) $value;
    }

    private static function optionWithOther(string $group, mixed $value, ?string $other): string
    {
        $label = self::optionLabel($group, $value);

        return $value === 'other' && filled($other) ? $label . ' — ' . $other : $label;
    }

    private static function emergencyTrainingLabel(?bool $value): string
    {
        if ($value === null) {
            return 'Not answered';
        }

        return $value ? 'Yes' : 'No';
    }

    public static function safeSpreadsheetCell(mixed $value): string
    {
        $cell = str_replace(["\t", "\r", "\n"], ' ', (string) $value);

        return preg_match('/^[=+\-@]/', $cell) ? "'" . $cell : $cell;
    }
}
