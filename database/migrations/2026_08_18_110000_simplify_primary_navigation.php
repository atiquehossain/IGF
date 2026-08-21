<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Navigation is editorial content, not schema. Existing installations
        // already have a menu for this migration to upgrade; fresh installs
        // receive their initial menu from the content seeder instead.
        if (! DB::table('page_menus')->exists()) {
            return;
        }

        $this->installNavigation();
    }

    public function down(): void
    {
        DB::table('page_menus')
            ->where('language', 'en')
            ->where('type', 'main')
            ->where('uuid', 'like', '69000000-%')
            ->update(['status' => 0, 'updated_at' => now()]);

        DB::table('page_menus')
            ->where('language', 'en')
            ->where('type', 'main')
            ->whereIn('uuid', array_merge(
                array_map(fn ($index) => '67000000-0000-4000-8000-'.str_pad((string) $index, 12, '0', STR_PAD_LEFT), range(1, 6)),
                DB::table('page_menus')->where('uuid', 'like', '68000000-%')->pluck('uuid')->all()
            ))
            ->update(['status' => 1, 'updated_at' => now()]);
    }

    private function installNavigation(): void
    {
        $roots = [
            ['Home', 'frontend.home', null, []],
            ['About Us', 'custom', '#', [
                ['Who We Are', 'frontend.about', null],
                ["Founder's Letter", 'frontend.page', "founder's-letter"],
                ['Awards & Recognition', 'frontend.category', 'awards-&-recognition'],
                ['Photo Gallery', 'frontend.gallery', null],
                ['Annual Reports', 'frontend.annual_report.index', null],
                ['Contact Us', 'frontend.contactUs', null],
            ]],
            ['Our Work', 'custom', '#', [
                ['Program Overview', 'frontend.category', 'our-causes'],
                ['Inclusive Education', 'frontend.page', 'education'],
                ['Visit Ignite School', 'frontend.category', 'visit-ignite-school'],
                ['Youth Development', 'frontend.page', 'youth-development'],
                ['Disaster Resilience', 'frontend.page', 'disaster-response-and-resilience'],
                ['Current Projects', 'frontend.project', 'current-project'],
                ['Completed Projects', 'frontend.project', 'completed-project'],
            ]],
            ['Get Involved', 'custom', '#', [
                ['Volunteer', 'frontend.volunteer_registration.index', null],
                ['Careers', 'frontend.category', 'career'],
                ['Sponsor a Child', 'frontend.sponsor_child', null],
            ]],
            ['News & Stories', 'custom', '#', [
                ['Stories', 'frontend.category', 'stories'],
                ['Events & News', 'frontend.events', null],
            ]],
            ['Donate', 'custom', '#', [
                ['Make a Donation', 'frontend.donate.index', null],
                ['Give Zakat', 'frontend.zakat', null],
            ]],
        ];

        DB::transaction(function () use ($roots): void {
            DB::table('page_menus')
                ->where('language', 'en')
                ->where('type', 'main')
                ->update(['status' => 0, 'updated_at' => now()]);

            foreach ($roots as $rootIndex => [$name, $link, $slug, $children]) {
                $rootUuid = '69000000-0000-4000-8000-'.str_pad((string) ($rootIndex + 1), 12, '0', STR_PAD_LEFT);
                $rootId = $this->upsertMenu($rootUuid, $name, $link, $slug, null, $rootIndex);

                foreach ($children as $childIndex => [$childName, $childLink, $childSlug]) {
                    $childUuid = '69000000-'.str_pad((string) ($rootIndex + 1), 4, '0', STR_PAD_LEFT).'-4000-8000-'.str_pad((string) ($childIndex + 1), 12, '0', STR_PAD_LEFT);
                    $this->upsertMenu($childUuid, $childName, $childLink, $childSlug, $rootId, $childIndex);
                }
            }
        });
    }

    private function upsertMenu(string $uuid, string $name, string $link, ?string $slug, ?int $parentId, int $order): int
    {
        $attributes = [
            'name' => $name,
            'type' => 'main',
            'link' => $link,
            'slug' => $slug,
            'parent_id' => $parentId,
            'language' => 'en',
            'order_by' => $order,
            'status' => 1,
            'deleted_at' => null,
            'updated_at' => now(),
        ];

        $existing = DB::table('page_menus')->where('uuid', $uuid)->first();
        if ($existing) {
            DB::table('page_menus')->where('id', $existing->id)->update($attributes);
            return (int) $existing->id;
        }

        return (int) DB::table('page_menus')->insertGetId($attributes + [
            'uuid' => $uuid,
            'created_at' => now(),
        ]);
    }
};
