@php
    $workflowStatus = $record->workflow_status ?: 'new';
    $badgeClass = match ($workflowStatus) {
        'contacted' => 'badge-info',
        'in_progress' => 'badge-warning',
        'completed' => 'badge-success',
        'spam' => 'badge-danger',
        default => 'badge-secondary',
    };
    $workflowCapability = match ($routeName) {
        'volunteer.workflow' => 'volunteer.edit',
        'sponsorships.workflow' => 'sponsorships.edit',
        'contact-message.workflow' => 'contact-message.edit',
        default => $routeName,
    };
    $canManageWorkflow = app(\App\Http\Middleware\Permission::class)
        ->allows(auth('admin')->user(), $workflowCapability);
@endphp

<div class="mb-2">
    <span class="badge {{ $badgeClass }}">{{ $workflowStatuses[$workflowStatus] ?? 'New' }}</span>
    @if($record->assignedAdmin)
        <small class="text-muted ml-1">Owner: {{ $record->assignedAdmin->name ?: $record->assignedAdmin->email }}</small>
    @endif
</div>

@if($canManageWorkflow)
    <details>
        <summary class="btn btn-sm btn-outline-primary">Manage enquiry</summary>
        <form action="{{ route($routeName, $record) }}" method="post" class="mt-3 p-3 border rounded bg-light" style="min-width:280px">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="workflow-status-{{ $record->id }}">Status</label>
                <select id="workflow-status-{{ $record->id }}" name="workflow_status" class="form-control form-control-sm" required>
                    @foreach($workflowStatuses as $value => $label)
                        <option value="{{ $value }}" @selected($workflowStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="workflow-owner-{{ $record->id }}">Assigned team member</label>
                <select id="workflow-owner-{{ $record->id }}" name="assigned_to" class="form-control form-control-sm">
                    <option value="">Unassigned</option>
                    @foreach($assignees as $assignee)
                        <option value="{{ $assignee->id }}" @selected((string) $record->assigned_to === (string) $assignee->id)>
                            {{ $assignee->name ?: $assignee->email }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="workflow-follow-up-{{ $record->id }}">Follow-up date</label>
                <input id="workflow-follow-up-{{ $record->id }}" name="follow_up_at" type="datetime-local" class="form-control form-control-sm"
                    value="{{ $record->follow_up_at?->format('Y-m-d\TH:i') }}">
            </div>
            <div class="form-group">
                <label for="workflow-notes-{{ $record->id }}">Private team notes</label>
                <textarea id="workflow-notes-{{ $record->id }}" name="internal_notes" rows="3" maxlength="5000" class="form-control form-control-sm"
                    placeholder="Record calls, decisions, and next steps. Visitors cannot see these notes.">{{ $record->internal_notes }}</textarea>
            </div>
            <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-save" aria-hidden="true"></i> Save workflow</button>
        </form>
    </details>
@else
    <details>
        <summary class="btn btn-sm btn-outline-secondary">View workflow</summary>
        <div class="mt-3 p-3 border rounded bg-light text-left" style="min-width:280px">
            <p class="small text-muted mb-3"><strong>Workflow is read only for your role.</strong> Ask a workflow editor to update this enquiry.</p>
            <dl class="mb-0 small">
                <dt>Status</dt>
                <dd>{{ $workflowStatuses[$workflowStatus] ?? 'New' }}</dd>
                <dt>Assigned team member</dt>
                <dd>{{ $record->assignedAdmin?->name ?: ($record->assignedAdmin?->email ?: 'Unassigned') }}</dd>
                <dt>Follow-up date</dt>
                <dd>{{ $record->follow_up_at?->format('d M Y, g:i A') ?: 'Not scheduled' }}</dd>
                <dt>Private team notes</dt>
                <dd class="mb-0" style="white-space:pre-wrap">{{ $record->internal_notes ?: 'No private notes recorded.' }}</dd>
            </dl>
        </div>
    </details>
@endif
