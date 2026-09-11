<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BLOG_CATEGORY_UUID = '61000000-0000-4000-8000-000000000007';
    private const BLOG_MENU_UUID = '68000000-0005-4000-8000-000000000004';
    private const BLOG_FOOTER_UUID = '7f010500-0000-4000-8000-000000000105';
    private const STORIES_ROOT_UUIDS = [
        '67000000-0000-4000-8000-000000000005',
        '69000000-0000-4000-8000-000000000005',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->ensureBlogCategories();
            $this->ensureHeaderLinks();
            $this->splitBundledFooterLink();
        });
    }

    public function down(): void
    {
        // Intentionally irreversible. Categories and navigation are editorial
        // state, and rollback must not erase posts or restore labels an editor
        // may have changed after deployment.
    }

    private function ensureBlogCategories(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        $translations = [
            'en' => [
                'name' => 'Blog',
                'description' => 'Insights, field reflections, and practical stories from Ignite Global Foundation.',
                'meta_title' => 'The Ignite Blog | Ignite Global Foundation',
                'meta_description' => 'Read insights, field reflections, and community stories from Ignite Global Foundation.',
            ],
            'bn' => [
                'name' => 'ব্লগ',
                'description' => 'ইগনাইট গ্লোবাল ফাউন্ডেশনের ভাবনা, মাঠের অভিজ্ঞতা ও কমিউনিটির গল্প।',
                'meta_title' => 'ইগনাইট ব্লগ | ইগনাইট গ্লোবাল ফাউন্ডেশন',
                'meta_description' => 'ইগনাইট গ্লোবাল ফাউন্ডেশনের ভাবনা, মাঠের অভিজ্ঞতা ও কমিউনিটির গল্প পড়ুন।',
            ],
        ];

        foreach ($translations as $language => $copy) {
            $deterministic = DB::table('categories')
                ->where('uuid', self::BLOG_CATEGORY_UUID)
                ->where('language', $language)
                ->first();
            if ($deterministic) {
                // A soft-deleted deterministic row is an administrator's
                // tombstone. Never silently restore it during a deployment.
                continue;
            }

            $existingSlug = DB::table('categories')
                ->where('language', $language)
                ->where('slug', 'blog')
                ->first();
            if ($existingSlug) {
                // Adopt an existing active Blog category as the stable
                // translation identity, but respect a deleted category.
                if (($existingSlug->deleted_at ?? null) === null) {
                    DB::table('categories')->where('id', $existingSlug->id)->update([
                        'uuid' => self::BLOG_CATEGORY_UUID,
                        'updated_at' => now(),
                    ]);
                }
                continue;
            }

            DB::table('categories')->insert([
                'name' => $copy['name'],
                'slug' => 'blog',
                'description' => $copy['description'],
                'type' => 'category-pages',
                'display_mode' => 'archive',
                'landing_page_uuid' => null,
                'image' => null,
                'path' => null,
                'banner_id' => null,
                'inline_css' => null,
                'meta_title' => $copy['meta_title'],
                'meta_keyword' => $language === 'bn' ? 'ইগনাইট ব্লগ, কমিউনিটি, বাংলাদেশ' : 'Ignite blog, community, Bangladesh',
                'meta_description' => $copy['meta_description'],
                'name_enabled' => 1,
                'status' => 1,
                'language' => $language,
                'uuid' => self::BLOG_CATEGORY_UUID,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'deleted_by' => null,
            ]);
        }
    }

    private function ensureHeaderLinks(): void
    {
        if (! Schema::hasTable('page_menus')) {
            return;
        }

        foreach (['en', 'bn'] as $language) {
            $root = DB::table('page_menus')
                ->whereIn('uuid', self::STORIES_ROOT_UUIDS)
                ->where('language', $language)
                ->where('type', 'main')
                ->whereNull('parent_id')
                ->whereNull('deleted_at')
                ->first();
            if (! $root) {
                continue;
            }

            $deterministic = DB::table('page_menus')
                ->where('uuid', self::BLOG_MENU_UUID)
                ->where('language', $language)
                ->first();
            if ($deterministic) {
                continue;
            }

            $existingDestination = DB::table('page_menus')
                ->where('language', $language)
                ->where('type', 'main')
                ->whereNull('deleted_at')
                ->where(function ($query): void {
                    $query->where('link', 'frontend.blog')
                        ->orWhere(function ($custom): void {
                            $custom->where('link', 'custom')->where('slug', '/blog');
                        });
                })
                ->exists();
            if ($existingDestination) {
                continue;
            }

            $order = (int) DB::table('page_menus')
                ->where('parent_id', $root->id)
                ->whereNull('deleted_at')
                ->max('order_by') + 1;

            DB::table('page_menus')->insert([
                'uuid' => self::BLOG_MENU_UUID,
                'parent_id' => $root->id,
                'name' => $language === 'bn' ? 'ব্লগ' : 'Blog',
                'description' => null,
                'type' => 'main',
                'link' => 'frontend.blog',
                'slug' => null,
                'icon' => null,
                'language' => $language,
                'banner_id' => null,
                'order_by' => $order,
                'status' => (int) $root->status,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'deleted_by' => null,
            ]);
        }
    }

    private function splitBundledFooterLink(): void
    {
        if (! Schema::hasTable('page_menus')) {
            return;
        }

        foreach (['en', 'bn'] as $language) {
            $combined = DB::table('page_menus')
                ->where('uuid', '7f010300-0000-4000-8000-000000000103')
                ->where('language', $language)
                ->where('type', 'footer')
                ->whereNull('deleted_at')
                ->first();
            if (! $combined) {
                continue;
            }

            $bundledNames = $language === 'bn'
                ? ['সংবাদ ও ব্লগ']
                : ['News & blog', 'News and blog'];
            if (in_array(trim((string) $combined->name), $bundledNames, true)
                && $combined->link === 'custom'
                && in_array(trim((string) $combined->slug), ['/events', '/news'], true)) {
                DB::table('page_menus')->where('id', $combined->id)->update([
                    'name' => $language === 'bn' ? 'সংবাদ' : 'News',
                    'slug' => '/news',
                    'updated_at' => now(),
                ]);
            }

            $deterministic = DB::table('page_menus')
                ->where('uuid', self::BLOG_FOOTER_UUID)
                ->where('language', $language)
                ->first();
            if ($deterministic) {
                continue;
            }

            $existingBlog = DB::table('page_menus')
                ->where('language', $language)
                ->where('type', 'footer')
                ->whereNull('deleted_at')
                ->where(function ($query): void {
                    $query->where('link', 'frontend.blog')
                        ->orWhere(function ($custom): void {
                            $custom->where('link', 'custom')->where('slug', '/blog');
                        });
                })
                ->exists();
            if ($existingBlog) {
                continue;
            }

            $gallery = DB::table('page_menus')
                ->where('uuid', '7f010400-0000-4000-8000-000000000104')
                ->where('language', $language)
                ->where('parent_id', $combined->parent_id)
                ->whereNull('deleted_at')
                ->first();
            $blogOrder = (int) $combined->order_by + 1;
            if ($gallery && (int) $gallery->order_by === $blogOrder) {
                DB::table('page_menus')->where('id', $gallery->id)->update([
                    'order_by' => $blogOrder + 1,
                    'updated_at' => now(),
                ]);
            }

            DB::table('page_menus')->insert([
                'uuid' => self::BLOG_FOOTER_UUID,
                'parent_id' => $combined->parent_id,
                'name' => $language === 'bn' ? 'ব্লগ' : 'Blog',
                'description' => null,
                'type' => 'footer',
                'link' => 'custom',
                'slug' => '/blog',
                'icon' => null,
                'language' => $language,
                'banner_id' => null,
                'order_by' => $blogOrder,
                'status' => (int) $combined->status,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'deleted_by' => null,
            ]);
        }
    }
};
