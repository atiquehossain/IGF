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
        'sex',
        'date_of_birth',
        'division_id',
        'district_id',
        'upazila_id',
        'occupation',
        'occupation_other',
        'education_level',
        'blood_group',
        'emergency_response_training',
        'skill',
        'skill_other',
        'consent_version',
        'consent_locale',
        'consent_text_hash',
        'consent_text_snapshot',
        'consented_at',
        'status',
        'workflow_status',
        'assigned_to',
        'internal_notes',
        'follow_up_at',
        'resolved_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'emergency_response_training' => 'boolean',
        'consented_at' => 'datetime',
        'follow_up_at' => 'datetime',
        'resolved_at' => 'datetime',
        'anonymized_at' => 'datetime',
    ];

    public function cause()
    {
        return $this->belongsTo(VolunteerCause::class, 'cause_id', 'id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function upazila()
    {
        return $this->belongsTo(Upazila::class);
    }
}
