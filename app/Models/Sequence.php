<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    protected $fillable = [
        'name', 'sequence_no',
    ];
    protected $hidden = [
        'created_at', 'updated_at',
    ];

}
