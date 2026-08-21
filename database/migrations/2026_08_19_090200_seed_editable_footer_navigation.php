<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('page_menus')) {
            return;
        }

        $locales = DB::table('page_menus')
            ->whereNotNull('language')
            ->where('language', '!=', '')
            ->distinct()
            ->pluck('language');

        if ($locales->isEmpty()) {
            $locales = collect(['en']);
        }

        foreach ($locales as $locale) {
            $this->seedLocaleWhenEmpty((string) $locale);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('page_menus')) {
            DB::table('page_menus')
                ->where('type', 'footer')
                ->whereIn('uuid', $this->managedUuids())
                ->delete();
        }
    }

    private function seedLocaleWhenEmpty(string $locale): void
    {
        // Include disabled and soft-deleted records in this check. Their
        // presence represents an editorial choice and must never be overwritten.
        if (DB::table('page_menus')->where('type', 'footer')->where('language', $locale)->exists()) {
            return;
        }

        $columns = [
            ['Explore', [
                ['About us', '/about-us'],
                ['Our impact', '/category/our-causes'],
                ['News & blog', '/events'],
                ['Gallery', '/gallery'],
            ]],
            ['Programs', [
                ['Education', '/category/our-causes'],
                ['Healthcare', '/category/our-causes'],
                ['Clean water', '/category/our-causes'],
                ['Livelihoods', '/category/our-causes'],
            ]],
            ['Donor support', [
                ['Ways to give', '/donate'],
                ['Sponsor a child', '/sponsor-child'],
                ['Annual reports', '/annual-report'],
                ['Volunteer', '/volunteer/register'],
            ]],
            ['Legal & contact', [
                ['Contact us', '/contact-us'],
                ['Privacy policy', '/page/privacy-policy'],
                ['Refund policy', '/page/refund-policy'],
                ['Terms of service', '/page/terms-conditions'],
                ['Safeguarding', '/page/safeguarding'],
            ]],
        ];

        DB::transaction(function () use ($columns, $locale): void {
            foreach ($columns as $columnIndex => [$name, $links]) {
                $parentId = DB::table('page_menus')->insertGetId([
                    'uuid' => $this->managedUuid($columnIndex + 1, 0),
                    'name' => $name,
                    'description' => 'Footer column: ' . $name,
                    'parent_id' => null,
                    'type' => 'footer',
                    'link' => 'custom',
                    'slug' => '#',
                    'language' => $locale,
                    'order_by' => $columnIndex,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($links as $linkIndex => [$label, $url]) {
                    DB::table('page_menus')->insert([
                        'uuid' => $this->managedUuid($columnIndex + 1, $linkIndex + 1),
                        'name' => $label,
                        'description' => null,
                        'parent_id' => $parentId,
                        'type' => 'footer',
                        'link' => 'custom',
                        'slug' => $url,
                        'language' => $locale,
                        'order_by' => $linkIndex,
                        'status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    private function managedUuid(int $column, int $item): string
    {
        // Translated PageMenu rows are paired by UUID, so corresponding footer
        // items must use the same UUID in every locale.
        return sprintf('7f%02d%02d00-0000-4000-8000-%012d', $column, $item, ($column * 100) + $item);
    }

    private function managedUuids(): array
    {
        $uuids = [];
        foreach ([1 => 4, 2 => 4, 3 => 4, 4 => 5] as $column => $itemCount) {
            $uuids[] = $this->managedUuid($column, 0);
            for ($item = 1; $item <= $itemCount; $item++) {
                $uuids[] = $this->managedUuid($column, $item);
            }
        }

        return $uuids;
    }
};
