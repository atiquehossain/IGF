<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mattiverse\Userstamps\Traits\Userstamps;

class Page extends Model
{
    use HasFactory, SoftDeletes;
    use Userstamps;

    protected $fillable = [
        'uuid',
        'category_id',
        'banner_id',
        'name',
        'thumbnail',
        'sub_title',
        'slug',
        'description',
        'inline_css',
        'status',
        'publication_status',
        'visibility',
        'is_comment',
        'name_enabled',
        'sub_title_enabled',
        'is_relationship',
        'is_funding_project',
        'is_zakat_eligible',
        'meta_title',
        'meta_keyword',
        'meta_description',
        'published_at',
        'scheduled_for',
        'last_published_at',
        'publish_by',
        'published_by',
        'order_by',
        'language',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'scheduled_for' => 'datetime',
        'last_published_at' => 'datetime',
        'published_at' => 'date',
        'editor_version' => 'integer',
        'is_funding_project' => 'boolean',
        'is_zakat_eligible' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Page $page) {
            // Preserve the legacy status switch for integrations that have not
            // yet adopted the richer publication workflow.
            if ($page->isDirty('status') && !$page->isDirty('publication_status')) {
                $page->publication_status = $page->status ? 'published' : 'draft';
            }
        });
    }

    public function banner()
    {
        return $this->belongsTo(Banner::class)->where('status', 1);
    }

    public function comment()
    {
        return $this->hasMany(Comment::class)->where('status', 1);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function pageTags()
    {
        return $this->hasMany(PageTagModule::class);
    }

    public function blocks()
    {
        return $this->hasMany(PageBlock::class)->orderBy('sort_order');
    }

    public function visibleBlocks()
    {
        return $this->hasMany(PageBlock::class)
            ->visible()
            ->orderBy('sort_order');
    }

    public function revisions()
    {
        return $this->hasMany(PageRevision::class)->latest('revision');
    }

    public function seo()
    {
        return $this->morphOne(SeoMetadata::class, 'seoable')
            ->where('locale', $this->language ?: app()->getLocale());
    }

    public function scopePubliclyAvailable($query)
    {
        $table = $this->getTable();

        return $query
            ->where($table . '.status', 1)
            ->where($table . '.visibility', '!=', 'private')
            ->where(function ($builder) use ($table) {
                $builder->where($table . '.publication_status', 'published')
                    ->orWhere(function ($scheduled) use ($table) {
                        $scheduled->where($table . '.publication_status', 'scheduled')
                            ->whereNotNull($table . '.scheduled_for')
                            ->where($table . '.scheduled_for', '<=', now());
                    });
            });
    }

    /**
     * Public records that may be surfaced by discovery features. Unlisted
     * pages remain directly addressable but must not appear automatically.
     */
    public function scopePubliclyListed($query)
    {
        return $query
            ->publiclyAvailable()
            ->where($this->qualifyColumn('visibility'), 'public');
    }

    /**
     * Project groups belong to the logical page, not to one language row.
     * Keep the direct relation as a legacy fallback for rows without UUIDs,
     * while letting every translation discover assignments made on a sibling.
     */
    public function scopeAssignedToActiveProjectTag($query, ?int $tagId = null, ?string $tagSlug = null)
    {
        $qualifiedUuid = $query->getModel()->qualifyColumn('uuid');
        $tagFilter = static function ($tags) use ($tagId, $tagSlug): void {
            $tags->where('status', 1);
            if ($tagId !== null) {
                $tags->whereKey($tagId);
            }
            if (filled($tagSlug)) {
                $tags->where('slug', $tagSlug);
            }
        };

        return $query->where(function ($pages) use ($qualifiedUuid, $tagId, $tagSlug, $tagFilter): void {
            $pages->whereHas('pageTags.tag', $tagFilter)
                ->orWhere(function ($logicalPages) use ($qualifiedUuid, $tagId, $tagSlug): void {
                    $logicalPages
                        ->whereNotNull($qualifiedUuid)
                        ->where($qualifiedUuid, '!=', '')
                        ->whereExists(function ($assignments) use ($qualifiedUuid, $tagId, $tagSlug): void {
                            $assignments->selectRaw('1')
                                ->from('pages as logical_tag_pages')
                                ->join(
                                    'page_tag_modules as logical_page_tags',
                                    'logical_page_tags.page_id',
                                    '=',
                                    'logical_tag_pages.id'
                                )
                                ->join('tags as logical_tags', 'logical_tags.id', '=', 'logical_page_tags.tag_id')
                                ->whereNull('logical_tag_pages.deleted_at')
                                ->whereNull('logical_tags.deleted_at')
                                ->where('logical_tags.status', 1)
                                ->whereColumn('logical_tag_pages.uuid', $qualifiedUuid);

                            if ($tagId !== null) {
                                $assignments->where('logical_tags.id', $tagId);
                            }
                            if (filled($tagSlug)) {
                                $assignments->where('logical_tags.slug', $tagSlug);
                            }
                        });
                });
        });
    }
}
