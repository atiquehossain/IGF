<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mattiverse\Userstamps\Traits\Userstamps;

class Upazila extends Model
{
    use HasFactory;
    use Userstamps;


    protected $fillable = [
        'name',
        'district_id',
        'status'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at', 'updated_at', 'created_by', 'updated_by',
    ];

    
    public function district() {
        return $this->belongsTo(District::class);
    }
}
