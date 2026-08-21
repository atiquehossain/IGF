<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthMenu extends Model {

    use HasFactory;

    protected $fillable = [
        'name',
        'parent_id',
        'link',
        'icon',
        'order_by',
        'status',
    ];

    public function parent() {
        return $this->belongsTo($this, 'parent_id', 'id');
    }

    public function children() {
        return $this->hasMany($this, 'parent_id', 'id')
        ->with('children')
        ->where('status', 1)
        ->orderBy('order_by', 'ASC');
    }

    public function menuAction() {
        return $this->hasMany(MenuAction::class, 'auth_menu_id', 'id')->where('status', 1)->orderBy('order_by', 'ASC');
    }

}
