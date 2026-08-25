<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories') || ! Schema::hasTable('pages')) {
            return;
        }

        $categoryQuery = DB::table('categories')
            ->where('slug', 'awards-&-recognition')
            ->where('language', 'en');

        if (Schema::hasColumn('categories', 'deleted_at')) {
            $categoryQuery->whereNull('deleted_at');
        }

        $category = $categoryQuery->first();
        if (! $category) {
            return;
        }

        DB::transaction(function () use ($category): void {
            foreach ([
                'the-diana-award' => [30, 50],
                'youth-development-award' => [20, 40],
                'best-volunteer-award' => [10, 30],
            ] as $slug => [$oldOrder, $newOrder]) {
                DB::table('pages')
                    ->where('slug', $slug)
                    ->where('language', 'en')
                    ->where('order_by', $oldOrder)
                    ->update(['order_by' => $newOrder, 'updated_at' => now()]);
            }

            foreach ($this->awards((int) $category->id) as $award) {
                $exists = DB::table('pages')
                    ->where('slug', $award['slug'])
                    ->where('language', 'en')
                    ->exists();

                if (! $exists) {
                    DB::table('pages')->insert($award);
                }
            }
        });
    }

    public function down(): void
    {
        // Preserve awards that administrators may have edited after deployment.
    }

    private function awards(int $categoryId): array
    {
        $now = now();

        return [
            [
                'uuid' => '62000000-0000-4000-8000-000000000017',
                'category_id' => $categoryId,
                'name' => 'VSO National Volunteer Award',
                'sub_title' => 'In 2023, VSO Bangladesh gave Ignite Global Foundation the National Volunteer Award for its volunteer work.',
                'thumbnail' => '/storage/media/ignite-live/350-x-200-265bad01dc2c.jpg',
                'slug' => 'vso-national-volunteer-award',
                'description' => '<h2>VSO National Volunteer Award</h2><p>In 2023, VSO Bangladesh gave Ignite Global Foundation the National Volunteer Award in recognition of its volunteer-led community service. The award celebrates the dedication, compassion, and practical impact of IGF volunteers.</p>',
                'status' => 1,
                'publication_status' => 'published',
                'visibility' => 'public',
                'name_enabled' => 1,
                'sub_title_enabled' => 1,
                'is_relationship' => 1,
                'meta_title' => 'VSO National Volunteer Award | Ignite Global Foundation',
                'meta_keyword' => 'VSO National Volunteer Award, Ignite Global Foundation',
                'meta_description' => 'VSO Bangladesh recognized Ignite Global Foundation with the 2023 National Volunteer Award for volunteer-led community service.',
                'order_by' => 20,
                'published_at' => $now,
                'last_published_at' => $now,
                'language' => 'en',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'uuid' => '62000000-0000-4000-8000-000000000018',
                'category_id' => $categoryId,
                'name' => 'The Hero Award',
                'sub_title' => 'The Arvi Foundation recognized Ignite Global Foundation for its response to the COVID-19 crisis.',
                'thumbnail' => '/storage/media/ignite-live/350-x-200-images-509234b7769c.jpg',
                'slug' => 'the-hero-award',
                'description' => '<h2>The Hero Award</h2><p>The Arvi Foundation honoured Ignite Global Foundation with The Hero Award in 2021 for its response during COVID-19. Ignite supported marginalized communities with nutritious food for almost 5,000 families and additional financial assistance.</p>',
                'status' => 1,
                'publication_status' => 'published',
                'visibility' => 'public',
                'name_enabled' => 1,
                'sub_title_enabled' => 1,
                'is_relationship' => 1,
                'meta_title' => 'The Hero Award | Ignite Global Foundation',
                'meta_keyword' => 'The Hero Award, Ignite Global Foundation',
                'meta_description' => 'The Arvi Foundation recognized Ignite Global Foundation for its community response during the COVID-19 crisis.',
                'order_by' => 10,
                'published_at' => $now,
                'last_published_at' => $now,
                'language' => 'en',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
    }
};
