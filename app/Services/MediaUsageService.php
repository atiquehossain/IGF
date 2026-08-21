<?php

namespace App\Services;

use App\Models\AnnualReport;
use App\Models\Banner;
use App\Models\Category;
use App\Models\DonationType;
use App\Models\Gallery;
use App\Models\LatestNews;
use App\Models\MediaAsset;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\ReusableBlock;
use App\Models\SeoMetadata;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Model;

class MediaUsageService
{
    public function references(MediaAsset $asset): array
    {
        $rawNeedles = array_values(array_unique(array_filter([
            $asset->url,
            $asset->path,
            '/storage/' . ltrim($asset->path, '/'),
        ])));
        $needles = array_values(array_unique(array_merge(
            $rawNeedles,
            array_map(fn (string $needle) => str_replace('/', '\\/', $needle), $rawNeedles)
        )));

        $references = [];

        foreach ($needles as $needle) {
            $locations = [
                'pages' => [Page::class, ['description', 'inline_css', 'thumbnail']],
                'page_blocks' => [PageBlock::class, ['content', 'settings']],
                'reusable_blocks' => [ReusableBlock::class, ['content', 'settings']],
                'seo' => [SeoMetadata::class, ['og_image', 'twitter_image']],
                'categories' => [Category::class, ['description', 'inline_css', 'image', 'path']],
                'banners' => [Banner::class, ['description', 'image', 'path', 'url']],
                'gallery' => [Gallery::class, ['description', 'image', 'path', 'url']],
                'events' => [NoticeBoard::class, ['description', 'inline_css', 'image_path', 'file_path', 'url']],
                'testimonials' => [Testimonial::class, ['testimonial', 'photo']],
                'team' => [LatestNews::class, ['description', 'image', 'path', 'url']],
                'annual_reports' => [AnnualReport::class, ['description', 'inline_css', 'image_path', 'file_path', 'url']],
                'site_settings' => [SiteSetting::class, ['value']],
                'donation_causes' => [DonationType::class, ['image']],
            ];

            foreach ($locations as $label => [$model, $fields]) {
                $count = $this->referenceCount($model, $fields, $needle);
                $references[$label] = max($references[$label] ?? 0, $count);
            }
        }

        // Donation causes retain the Media Library UUID as the authoritative
        // reference. Check it directly so URL normalization or a future path
        // change can never make an in-use cause image appear deletable.
        $references['donation_causes'] = max(
            $references['donation_causes'] ?? 0,
            DonationType::withTrashed()
                ->where('image_media_uuid', (string) $asset->uuid)
                ->count()
        );

        return array_filter($references);
    }

    public function inUse(MediaAsset $asset): bool
    {
        return array_sum($this->references($asset)) > 0;
    }

    /** @param class-string<Model> $model */
    private function referenceCount(string $model, array $fields, string $needle): int
    {
        $query = $model::withTrashed();

        return $query->where(function ($builder) use ($fields, $needle): void {
            foreach ($fields as $index => $field) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $builder->{$method}($field, 'like', '%' . $needle . '%');
            }
        })->count();
    }
}
