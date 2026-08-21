<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait UpdatesEnquiryWorkflow
{
    protected function activeWorkflowAssignees()
    {
        return Admin::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    protected function workflowStatuses(): array
    {
        return [
            'new' => 'New',
            'contacted' => 'Contacted',
            'in_progress' => 'In progress',
            'completed' => 'Completed',
            'spam' => 'Spam',
        ];
    }

    protected function persistWorkflow(Request $request, Model $enquiry): RedirectResponse
    {
        $data = $request->validate([
            'workflow_status' => ['required', Rule::in(array_keys($this->workflowStatuses()))],
            'assigned_to' => [
                'nullable',
                Rule::exists('admins', 'id')->where(fn ($query) => $query->where('status', 1)),
            ],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'follow_up_at' => ['nullable', 'date'],
        ]);

        $data['resolved_at'] = in_array($data['workflow_status'], ['completed', 'spam'], true)
            ? ($enquiry->resolved_at ?: now())
            : null;

        $enquiry->update($data);

        return back()->with('success', 'Enquiry workflow updated.');
    }
}
