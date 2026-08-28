<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mattiverse\Userstamps\Traits\Userstamps;

class PageMenu extends Model
{
    use HasFactory, SoftDeletes;
    use Userstamps;

    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'type',
        'link',
        'slug',
        'icon',
        'language',
        'banner_id',
        'order_by',
        'status',
        'uuid',
        'deleted_by',
    ];

    public function parent() {
        return $this->belongsTo($this, 'parent_id', 'id');
    }

    public function child() {
        $children = $this->hasMany($this, 'parent_id', 'id')
                ->select('id', 'uuid', 'name', 'description', 'link')
                ->selectRaw("IFNULL(parent_id, '') as parent_id")
                ->selectRaw("IFNULL(slug, '') as slug")
                ->selectRaw("IFNULL(icon, '') as icon")
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('page_menus as navigation_parent')
                        ->whereColumn('navigation_parent.id', 'page_menus.parent_id')
                        ->whereColumn('navigation_parent.language', 'page_menus.language')
                        ->whereColumn('navigation_parent.type', 'page_menus.type')
                        ->whereNull('navigation_parent.deleted_at');
                })
                ->where('status', 1)
                ->orderBy('order_by', 'ASC');
        return $children;
    }

    public function children() {
        return $this->child()->with('children');
    }

    public function banner() {
        return $this->belongsTo(Banner::class) ->where('status', 1);
    }

    public function page() {
        return $this->belongsTo(Page::class ,'slug', 'slug')->publiclyAvailable();
    }
}
