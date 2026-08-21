<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mattiverse\Userstamps\Traits\Userstamps;

class Division extends Model
{
    use HasFactory;
    use Userstamps;

    protected $fillable = [
        'name',
        'status'
    ];
}
