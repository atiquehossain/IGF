<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mattiverse\Userstamps\Traits\Userstamps;

class PageTagModule extends Model
{
  use HasFactory;
  use Userstamps;

  protected $fillable = [
    'uuid',
    'page_id',
    'tag_id',
  ];

  public function page()
  {
    return $this->belongsTo(Page::class);
  }

  public function tag()
  {
    return $this->belongsTo(Tag::class);
  }
}
