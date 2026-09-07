<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NEWS_ROOT_UUIDS = [
        '67000000-0000-4000-8000-000000000005',
        '69000000-0000-4000-8000-000000000005',
    ];

    private const EVENT_MENU_UUIDS = [
        '68000000-0005-4000-8000-000000000002',
        '69000000-0005-4000-8000-000000000002',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->upgradeNavigation();
            $this->upgradeManagedArchiveDestinations('page_blocks');
            $this->upgradeManagedArchiveDestinations('reusable_blocks');
            $this->upgradeSharedNewsDestination();
        });
    }

    public function down(): void
    {
        // Intentionally irreversible. Navigation labels and page-builder links
        // are editorial state and a rollback must not overwrite later edits.
    }

    private function upgradeNavigation(): void
    {
        if (! Schema::hasTable('page_menus')) {
            return;
        }

        $roots = DB::table('page_menus')
            ->whereIn('uuid', self::NEWS_ROOT_UUIDS)
            ->where('type', 'main')
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->get();

        foreach ($roots as $root) {
            $eventUuid = str_starts_with((string) $root->uuid, '69000000-')
                ? '69000000-0005-4000-8000-000000000002'
                : '68000000-0005-4000-8000-000000000002';
            $newsUuid = str_replace('000000000002', '000000000003', $eventUuid);
            $event = DB::table('page_menus')
                ->where('uuid', $eventUuid)
                ->where('language', $root->language)
                ->where('type', $root->type)
                ->first();

            // The old deterministic child is the installation marker. If an
            // administrator deleted it, do not recreate either half of that
            // deliberately removed navigation destination.
            if (! $event || $event->deleted_at !== null) {
                continue;
            }

            if ($this->isBundledCombinedDestination($event)) {
                $updates = [
                    'link' => 'frontend.events',
                    'slug' => null,
                    'updated_at' => now(),
                ];
                if ($this->isBundledCombinedLabel((string) $event->name)) {
                    $updates['name'] = $this->eventLabel((string) $root->language);
                }
                DB::table('page_menus')->where('id', $event->id)->update($updates);
            }

            $existingNews = DB::table('page_menus')
                ->where('language', $root->language)
                ->where('type', $root->type)
                ->where(function ($query) use ($newsUuid, $root): void {
                    $query->where('uuid', $newsUuid)
                        ->orWhere(function ($destination) use ($root): void {
                            $destination->where('parent_id', $root->id)
                                ->where('link', 'frontend.news');
                        });
                })
                ->get();
            $activeNews = $existingNews->first(fn (object $menu): bool => $menu->deleted_at === null);
            if ($activeNews) {
                // A deleted deterministic row is an administrator tombstone;
                // never silently restore it during a deployment.
                if ($activeNews->uuid === $newsUuid
                    && $activeNews->link === 'frontend.news') {
                    DB::table('page_menus')->where('id', $activeNews->id)->update([
                        'parent_id' => $root->id,
                        'slug' => null,
                        'updated_at' => now(),
                    ]);
                }
                continue;
            }
            if ($existingNews->contains(fn (object $menu): bool => $menu->uuid === $newsUuid)) {
                continue;
            }

            DB::table('page_menus')->insert([
                'uuid' => $newsUuid,
                'parent_id' => $root->id,
                'name' => $this->newsLabel((string) $root->language),
                'description' => null,
                'type' => $root->type,
                'link' => 'frontend.news',
                'slug' => null,
                'icon' => null,
                'language' => $root->language,
                'banner_id' => null,
                'order_by' => (int) $event->order_by + 1,
                'status' => (int) $event->status,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function isBundledCombinedDestination(object $menu): bool
    {
        $slug = trim((string) $menu->slug);

        return $menu->link === 'frontend.events'
            || ($menu->link === 'custom'
                && in_array($slug, ['/events', '/events?kind=event'], true));
    }

    private function isBundledCombinedLabel(string $label): bool
    {
        return in_array(mb_strtolower(trim($label)), [
            'events & news',
            'events and news',
            'ইভেন্ট ও সংবাদ',
        ], true);
    }

    private function eventLabel(string $locale): string
    {
        return $locale === 'bn' ? 'ইভেন্ট' : 'Events';
    }

    private function newsLabel(string $locale): string
    {
        return $locale === 'bn' ? 'সংবাদ' : 'News';
    }

    private function upgradeManagedArchiveDestinations(string $table): void
    {
        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'type')
            || ! Schema::hasColumn($table, 'content')) {
            return;
        }

        DB::table($table)
            ->where('type', 'events_news')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $content = json_decode((string) $row->content, true);
                    if (! is_array($content)) {
                        continue;
                    }

                    $changed = false;
                    if (($content['events_view_all_url'] ?? null) === '/events?kind=event') {
                        $content['events_view_all_url'] = '/events';
                        $changed = true;
                    }
                    if (($content['news_view_all_url'] ?? null) === '/events?kind=article') {
                        $content['news_view_all_url'] = '/news';
                        $changed = true;
                    }
                    if (! $changed) {
                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update([
                        'content' => json_encode(
                            $content,
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                        ),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function upgradeSharedNewsDestination(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        DB::table('site_settings')
            ->where('group', 'shared_blocks')
            ->where('key', 'updates_news_url')
            ->where('value', '/events')
            ->whereNull('deleted_at')
            ->update(['value' => '/news', 'updated_at' => now()]);
    }
};
