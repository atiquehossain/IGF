<?php

namespace App\Models;

use App\Models\Concerns\HasEnquiryWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mattiverse\Userstamps\Traits\Userstamps;

class Volunteer extends Model
{
    use HasEnquiryWorkflow;
    use HasFactory;
    use Userstamps;

    protected $fillable = [
        'name',
        'institution',
        'email',
        'phone',
        'address',
        'cause_id',
        'status',
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

    public function cause()
    {
        return $this->belongsTo(VolunteerCause::class, 'cause_id', 'id');
    }
}
