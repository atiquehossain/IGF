<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mattiverse\Userstamps\Traits\Userstamps;
use Illuminate\Support\Str;
use LogicException;

class Donation extends Model
{
    use HasFactory;
    use Userstamps;

    public const ATTRIBUTION_FIELDS = [
        'payment_cause',
        'cause_uuid_snapshot',
        'cause_slug_snapshot',
        'cause_name_snapshot',
        'purpose_key_snapshot',
        'destination_type_snapshot',
        'destination_uuid_snapshot',
        'destination_name_snapshot',
        'project_uuid_snapshot',
        'project_name_snapshot',
    ];

    protected $fillable = [
        'uuid',
        'donor_name',
        'description',
        'status',
        'email',
        'phone',
        'address',
        'payment_cause',
        'cause_uuid_snapshot',
        'cause_slug_snapshot',
        'cause_name_snapshot',
        'purpose_key_snapshot',
        'destination_type_snapshot',
        'destination_uuid_snapshot',
        'destination_name_snapshot',
        'project_uuid_snapshot',
        'project_name_snapshot',
        'requested_payment_method',
        'amount',
        'transaction_id',
        'payment_status',
    ];

    public function donationType()
    {
        // Historical donation reports must retain the cause label even when an
        // editor retires (soft-deletes) that cause later.
        return $this->belongsTo(DonationType::class, 'payment_cause', 'uuid')->withTrashed();
    }

    public function gatewayTransaction()
    {
        return $this->hasOne(SslCommerzTransaction::class, 'tran_id', 'transaction_id');
    }

    public function allocations()
    {
        return $this->hasMany(DonationAllocation::class)->oldest('id');
    }

    public function reviewResolver()
    {
        return $this->belongsTo(Admin::class, 'review_resolved_by');
    }

    protected $casts = [
        'review_resolved_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        static::updating(function (Donation $model): void {
            foreach (self::ATTRIBUTION_FIELDS as $field) {
                if ($model->isDirty($field)) {
                    throw new LogicException('Donation attribution is immutable after the payment attempt is created.');
                }
            }
        });
    }
}
