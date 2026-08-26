<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationImportRow extends Model
{
    public const STATE_PENDING = 'pending';
    public const STATE_VALID = 'valid';
    public const STATE_INVALID = 'invalid';
    public const STATE_DUPLICATE = 'duplicate';
    public const STATE_IMPORTED = 'imported';
    public const STATE_FAILED = 'failed';
    public const STATES = [
        self::STATE_PENDING,
        self::STATE_VALID,
        self::STATE_INVALID,
        self::STATE_DUPLICATE,
        self::STATE_IMPORTED,
        self::STATE_FAILED,
    ];

    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_SKIP = 'skip';
    public const ACTIONS = [self::ACTION_CREATE, self::ACTION_UPDATE, self::ACTION_SKIP];

    protected $fillable = [
        'application_import_batch_id', 'row_number', 'state', 'action',
        'raw_data', 'normalized_data', 'validation_errors', 'imported_target_uuid',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'raw_data' => 'array',
        'normalized_data' => 'array',
        'validation_errors' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ApplicationImportBatch::class, 'application_import_batch_id');
    }
}
