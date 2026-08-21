<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mattiverse\Userstamps\Traits\Userstamps;

class Comment extends Model
{
    use HasFactory;
    use Userstamps;

    protected $fillable = [
        'name',
        'text',
        'page_id',
        'user_id',
        'ip',
        'status',
        'is_delete',
        'updated_at'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at', 'created_by', 'updated_by',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class)->where('status', 1);
    }
}
