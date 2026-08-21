<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mattiverse\Userstamps\Traits\Userstamps;

class District extends Model {

    use HasFactory;
    use Userstamps;

    protected $fillable = [
        'name',
        'division_id',
        'status'
    ];

    public function division() {
        return $this->belongsTo(Division::class);
    }

}
