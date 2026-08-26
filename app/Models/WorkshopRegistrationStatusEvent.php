<?php

namespace App\Models;

use App\Models\Concerns\AppendOnlyRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopRegistrationStatusEvent extends Model
{
    use AppendOnlyRecord;

    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_SYSTEM = 'system';
    public const SOURCE_IMPORT = 'import';
    public const SOURCES = [self::SOURCE_ADMIN, self::SOURCE_SYSTEM, self::SOURCE_IMPORT];

    public $timestamps = false;

    protected $fillable = [
        'workshop_registration_id', 'from_status', 'to_status', 'actor_admin_id',
        'actor_name_snapshot', 'source', 'created_at',
    ];

    protected $casts = ['created_at' => 'immutable_datetime'];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(WorkshopRegistration::class, 'workshop_registration_id');
    }

    public function actorAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'actor_admin_id');
    }
}
