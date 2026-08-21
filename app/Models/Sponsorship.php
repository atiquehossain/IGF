<?php

namespace App\Models;

use App\Models\Concerns\HasEnquiryWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sponsorship extends Model
{
    use HasEnquiryWorkflow;
    use HasFactory;

    // allow mass assignment for these fields
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'number_of_children',
        'contribution_interval',
        'sponsorship_amount',
        'transaction_id',
        'payment_status',
        'workflow_status',
        'assigned_to',
        'internal_notes',
        'follow_up_at',
        'resolved_at',
    ];

    protected $casts = [
        'follow_up_at' => 'datetime',
        'resolved_at' => 'datetime',
        'anonymized_at' => 'datetime',
    ];
}
