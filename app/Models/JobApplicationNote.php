<?php

namespace App\Models;

use App\Models\Concerns\AppendOnlyRecord;
use App\Models\Concerns\HasGeneratedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplicationNote extends Model
{
    use AppendOnlyRecord, HasGeneratedUuid;

    protected $fillable = ['job_application_id', 'author_admin_id', 'author_name_snapshot', 'body'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function authorAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'author_admin_id');
    }
}
