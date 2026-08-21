<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mattiverse\Userstamps\Traits\Userstamps;

class ViewSearchData extends Model
{
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'view_search_data';

    use HasFactory;
    use Userstamps;

    protected $fillable = [
        'id',
        'name',
        'sub_title',
        'description',
        'language',
        'search',
        'skill_id',
        'skill_name',
        'class_id',
        'class_name',
        'subject_id',
        'subject_name',
        'package_id',
        'package_name',
        'audio_music_id',
        'audio_music_name',
        'video_content_id',
        'video_content_name',
        'you_tube_id',
        'you_tube_name',
        'view_type',
        'order_by',
    ];

}
