<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'uuid',
        'revision',
        'snapshot',
        'note',
        'created_by',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}
