<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class DonationAllocation extends Model
{
    protected $fillable = [
        'uuid',
        'request_token',
        'donation_id',
        'page_uuid',
        'page_name_snapshot',
        'category_uuid_snapshot',
        'category_name_snapshot',
        'amount',
        'note',
        'allocated_by',
        'allocated_by_name_snapshot',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (DonationAllocation $allocation): void {
            if (empty($allocation->uuid)) {
                $allocation->uuid = (string) Str::uuid();
            }
        });
        static::updating(fn () => throw new LogicException('Donation allocations are append-only audit records.'));
        static::deleting(fn () => throw new LogicException('Donation allocations cannot be deleted.'));
    }

    public function donation()
    {
        return $this->belongsTo(Donation::class);
    }

    public function allocator()
    {
        return $this->belongsTo(Admin::class, 'allocated_by');
    }
}
