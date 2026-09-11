<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlogChannelMigrationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const BLOG_CATEGORY_UUID = '61000000-0000-4000-8000-000000000007';

    private const BLOG_MENU_UUID = '68000000-0005-4000-8000-000000000004';

    private const BLOG_FOOTER_UUID = '7f010500-0000-4000-8000-000000000105';

    private const COMBINED_FOOTER_UUID = '7f010300-0000-4000-8000-000000000103';

    private const FOOTER_PARENT_UUID = '7f010000-0000-4000-8000-000000000100';

    private const STORIES_ROOT_UUIDS = [
        '67000000-0000-4000-8000-000000000005',
        '69000000-0000-4000-8000-000000000005',
    ];

    public function test_migration_creates_the_localized_blog_categories_and_navigation_contract(): void
    {
        $this->arrangeLegacyNavigationState();
        $this->migration()->up();

        foreach ($this->localizedLabels() as $locale => $labels) {
            $this->assertDatabaseHas('categories', [
                'uuid' => self::BLOG_CATEGORY_UUID,
                'language' => $locale,
                'name' => $labels['blog'],
                'slug' => 'blog',
                'display_mode' => 'archive',
                'status' => 1,
                'deleted_at' => null,
            ]);

            $root = DB::table('page_menus')
                ->whereIn('uuid', self::STORIES_ROOT_UUIDS)
                ->where('language', $locale)
                ->where('type', 'main')
                ->whereNull('parent_id')
                ->whereNull('deleted_at')
                ->first();

            $this->assertNotNull($root, "The {$locale} Stories navigation root is missing.");
            $this->assertSame($labels['stories'], $root->name);

            $this->assertDatabaseHas('page_menus', [
                'uuid' => self::BLOG_MENU_UUID,
                'language' => $locale,
                'parent_id' => $root->id,
                'name' => $labels['blog'],
                'type' => 'main',
                'link' => 'frontend.blog',
                'slug' => null,
                'status' => 1,
                'deleted_at' => null,
            ]);

            $news = DB::table('page_menus')
                ->where('uuid', self::COMBINED_FOOTER_UUID)
                ->where('language', $locale)
                ->where('type', 'footer')
                ->whereNull('deleted_at')
                ->first();

            $this->assertNotNull($news, "The {$locale} footer News link is missing.");
            $this->assertSame($labels['news'], $news->name);
            $this->assertSame('custom', $news->link);
            $this->assertSame('/news', $news->slug);

            $this->assertDatabaseHas('page_menus', [
                'uuid' => self::BLOG_FOOTER_UUID,
                'language' => $locale,
                'parent_id' => $news->parent_id,
                'name' => $labels['blog'],
                'type' => 'footer',
                'link' => 'custom',
                'slug' => '/blog',
                'status' => 1,
                'deleted_at' => null,
            ]);
        }
    }

    public function test_migration_is_idempotent_and_does_not_restore_a_deleted_navigation_tombstone(): void
    {
        $this->arrangeLegacyNavigationState();
        $migration = $this->migration();

        $migration->up();
        $migration->up();

        foreach (['en', 'bn'] as $locale) {
            $this->assertSame(1, DB::table('categories')
                ->where('uuid', self::BLOG_CATEGORY_UUID)
                ->where('language', $locale)
                ->count());
            $this->assertSame(1, DB::table('page_menus')
                ->where('uuid', self::BLOG_MENU_UUID)
                ->where('language', $locale)
                ->count());
            $this->assertSame(1, DB::table('page_menus')
                ->where('uuid', self::BLOG_FOOTER_UUID)
                ->where('language', $locale)
                ->count());
        }

        $englishMenu = DB::table('page_menus')
            ->where('uuid', self::BLOG_MENU_UUID)
            ->where('language', 'en')
            ->first();
        $this->assertNotNull($englishMenu);

        $deletedAt = now()->subMinute()->startOfSecond();
        DB::table('page_menus')->where('id', $englishMenu->id)->update([
            'deleted_at' => $deletedAt,
            'deleted_by' => 999,
        ]);

        $migration->up();

        $this->assertSoftDeleted('page_menus', [
            'id' => $englishMenu->id,
            'uuid' => self::BLOG_MENU_UUID,
            'language' => 'en',
            'deleted_by' => 999,
        ]);
        $this->assertSame(1, DB::table('page_menus')
            ->where('uuid', self::BLOG_MENU_UUID)
            ->where('language', 'en')
            ->count());
        $this->assertSame(0, DB::table('page_menus')
            ->where('uuid', self::BLOG_MENU_UUID)
            ->where('language', 'en')
            ->whereNull('deleted_at')
            ->count());
    }

    /** @return array<string, array{stories: string, blog: string, news: string}> */
    private function localizedLabels(): array
    {
        return [
            'en' => ['stories' => 'Stories', 'blog' => 'Blog', 'news' => 'News'],
            'bn' => ['stories' => 'গল্প', 'blog' => 'ব্লগ', 'news' => 'সংবাদ'],
        ];
    }

    private function arrangeLegacyNavigationState(): void
    {
        DB::table('categories')->where('uuid', self::BLOG_CATEGORY_UUID)->delete();
        DB::table('page_menus')->whereIn('uuid', [
            self::BLOG_MENU_UUID,
            self::BLOG_FOOTER_UUID,
        ])->delete();

        foreach ($this->localizedLabels() as $locale => $labels) {
            $this->ensureMenu([
                'uuid' => self::STORIES_ROOT_UUIDS[0],
                'language' => $locale,
            ], [
                'name' => $labels['stories'],
                'type' => 'main',
                'link' => 'custom',
                'slug' => '#',
                'parent_id' => null,
                'order_by' => 4,
            ]);

            $footerParentId = $this->ensureMenu([
                'uuid' => self::FOOTER_PARENT_UUID,
                'language' => $locale,
            ], [
                'name' => $locale === 'bn' ? 'অন্বেষণ করুন' : 'Explore',
                'type' => 'footer',
                'link' => 'custom',
                'slug' => '#',
                'parent_id' => null,
                'order_by' => 0,
            ]);

            $this->ensureMenu([
                'uuid' => self::COMBINED_FOOTER_UUID,
                'language' => $locale,
            ], [
                'name' => $locale === 'bn' ? 'সংবাদ ও ব্লগ' : 'News & blog',
                'type' => 'footer',
                'link' => 'custom',
                'slug' => '/events',
                'parent_id' => $footerParentId,
                'order_by' => 2,
            ]);
        }
    }

    /** @param array<string, mixed> $identity @param array<string, mixed> $values */
    private function ensureMenu(array $identity, array $values): int
    {
        $existing = DB::table('page_menus')->where($identity)->first();
        $attributes = array_merge([
            'description' => null,
            'icon' => null,
            'banner_id' => null,
            'status' => 1,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ], $values);

        if ($existing) {
            DB::table('page_menus')->where('id', $existing->id)->update($attributes);

            return (int) $existing->id;
        }

        return (int) DB::table('page_menus')->insertGetId(array_merge($identity, $attributes));
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_09_000000_add_blog_page_channel.php');
    }
}
