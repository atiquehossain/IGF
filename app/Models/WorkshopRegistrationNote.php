<?php

namespace App\Models;

use App\Models\Concerns\AppendOnlyRecord;
use App\Models\Concerns\HasGeneratedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopRegistrationNote extends Model
{
    use AppendOnlyRecord, HasGeneratedUuid;

    protected $fillable = [
        'workshop_registration_id', 'author_admin_id', 'author_name_snapshot', 'body',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(WorkshopRegistration::class, 'workshop_registration_id');
    }

    public function authorAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'author_admin_id');
    }
}
