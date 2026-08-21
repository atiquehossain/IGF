<?php

namespace App\Models;

use App\Models\Concerns\HasEnquiryWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mattiverse\Userstamps\Traits\Userstamps;

class ContactMessage extends Model
{
    use HasEnquiryWorkflow;
    use HasFactory;
    use Userstamps;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'message',
        'ip',
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
  
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at', 'created_by', 'updated_at', 'updated_by',
    ];
}
