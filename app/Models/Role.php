<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'parent_id',
        'security_rank',
        'is_owner',
        'permission',
        'actionPermission',
        'serial',
        'order_by',
        'status',
    ];

    protected $casts = [
        'security_rank' => 'integer',
        'is_owner' => 'boolean',
        'status' => 'boolean',
    ];

    public function admins()
    {
        return $this->hasMany(Admin::class, 'role');
    }

    public function parent() {
        return $this->belongsTo($this, 'parent_id', 'id');
    }

    public function children() {
        return $this->hasMany($this, 'parent_id', 'id')
        ->with('children')
        ->select('id', 'name', 'permission', 'actionPermission', 'serial')
        ->where('status', 1)
        ->orderBy('order_by', 'ASC');
    }
}
