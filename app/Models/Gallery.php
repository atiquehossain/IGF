<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mattiverse\Userstamps\Traits\Userstamps;

class Gallery extends Model
{
    use HasFactory, SoftDeletes;
    use Userstamps;

    protected $fillable = [
        'name',
        'type',
        'description',
        'image',
        'path',
        'language',
        'url',
        'uuid',
        'order_by',
        'album_id',
        'grid_column',
        'grid_row',
        'status'
    ];

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class, 'album_id');
    }

    /**
     * Photos discoverable on public surfaces. Records created before albums
     * were introduced remain valid, while an assigned album owns the public
     * publication boundary for its photos.
     */
    public function scopePubliclyAvailable(Builder $query): Builder
    {
        $table = $this->getTable();

        return $query
            ->where($table . '.status', 1)
            ->where($table . '.type', 'gallery')
            ->where(function (Builder $photos) use ($table): void {
                $photos->whereNull($table . '.album_id')
                    ->orWhere($table . '.album_id', '')
                    ->orWhereHas('album', fn (Builder $album) => $album->where('status', 1));
            });
    }
}
