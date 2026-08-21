<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuAction extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'auth_menu_id',
        'name',
        'type',
        'link',
        'icon',
        'order_by',
        'status',
    ];

    public function authMenu()
    {
        return $this->belongsTo(AuthMenu::class, 'auth_menu_id');
    }
}
