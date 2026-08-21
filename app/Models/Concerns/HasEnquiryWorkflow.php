<?php

namespace App\Models\Concerns;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasEnquiryWorkflow
{
    public const WORKFLOW_STATUSES = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'spam' => 'Spam',
    ];

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }

    public function workflowStatusLabel(): string
    {
        return self::WORKFLOW_STATUSES[$this->workflow_status] ?? self::WORKFLOW_STATUSES['new'];
    }
}
