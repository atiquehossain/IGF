<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const WORKSHOP_UUIDS = [
        '68000000-0304-4000-8000-000000000001',
        '68000000-0304-4000-8000-000000000002',
    ];

    private const OUR_WORK_UUIDS = [
        '67000000-0000-4000-8000-000000000003',
        '69000000-0000-4000-8000-000000000003',
    ];

    private const YOUTH_DEVELOPMENT_UUIDS = [
        '68000000-0003-4000-8000-000000000004',
        '69000000-0003-4000-8000-000000000004',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('page_menus')) {
            return;
        }

        DB::transaction(function (): void {
            $scopes = DB::table('page_menus')
                ->where('type', 'main')
                ->whereNotNull('language')
                ->whereNull('deleted_at')
                ->select(['language', 'type'])
                ->distinct()
                ->get();
            $workshopUuid = $this->globallyCompatibleWorkshopUuid();

            foreach ($scopes as $scope) {
                $youthDevelopment = $this->findYouthDevelopment($scope->language, $scope->type);
                if (! $youthDevelopment) {
                    continue;
                }

                $ourWork = $this->findOurWork($scope->language, $scope->type, $youthDevelopment);
                if (! $ourWork) {
                    continue;
                }

                if ((int) $youthDevelopment->parent_id !== (int) $ourWork->id) {
                    if (! $this->canMoveSubtree((int) $youthDevelopment->id, (int) $ourWork->id)) {
                        // Moving an editor-owned subtree must never create a
                        // fourth public navigation level. Preserve it in place.
                        continue;
                    }

                    DB::table('page_menus')->where('id', $youthDevelopment->id)->update([
                        'parent_id' => $ourWork->id,
                        'updated_at' => now(),
                    ]);
                }

                $this->ensureWorkshop(
                    $scope->language,
                    $scope->type,
                    (int) $youthDevelopment->id,
                    $workshopUuid
                );
            }
        });
    }

    public function down(): void
    {
        // Intentionally irreversible: editor-controlled hierarchy, status, and
        // ordering must not be guessed during a rollback.
    }

    private function findYouthDevelopment(string $language, string $type): ?object
    {
        return DB::table('page_menus')
            ->where('language', $language)
            ->where('type', $type)
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                $query->where(function ($route): void {
                    $route->where('link', 'frontend.page')
                        ->where('slug', 'youth-development');
                })->orWhere(function ($legacy): void {
                    $legacy->where('link', 'custom')
                        ->whereIn('slug', ['/page/youth-development', 'page/youth-development']);
                });
            })
            ->get()
            ->sortBy(fn (object $menu): array => [
                in_array($menu->uuid, self::YOUTH_DEVELOPMENT_UUIDS, true) ? 0 : 1,
                (int) $menu->id,
            ])
            ->first();
    }

    private function findOurWork(string $language, string $type, object $youthDevelopment): ?object
    {
        $known = DB::table('page_menus')
            ->where('language', $language)
            ->where('type', $type)
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                $query->whereIn('uuid', self::OUR_WORK_UUIDS)
                    ->orWhere(function ($placeholder): void {
                        $placeholder->where('link', 'custom')
                            ->where(function ($destination): void {
                                $destination->whereIn('slug', ['#', ''])->orWhereNull('slug');
                            })
                            ->whereRaw('LOWER(TRIM(name)) = ?', ['our work']);
                    });
            })
            ->orderBy('id')
            ->first();

        if ($known) {
            return $known;
        }

        if (! $youthDevelopment->parent_id) {
            return null;
        }

        $currentParent = DB::table('page_menus')
            ->where('id', $youthDevelopment->parent_id)
            ->where('language', $language)
            ->where('type', $type)
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->first();

        if (! $currentParent) {
            return null;
        }

        $normalizedName = mb_strtolower(trim((string) $currentParent->name));

        return in_array($currentParent->uuid, self::OUR_WORK_UUIDS, true)
            || in_array($normalizedName, ['our work', 'আমাদের কাজ'], true)
            ? $currentParent
            : null;
    }

    private function ensureWorkshop(string $language, string $type, int $parentId, ?string $workshopUuid): void
    {
        $matches = $this->workshopDestinationRows($language, $type)
            ->whereNull('deleted_at')
            ->get()
            ->sortBy(fn (object $menu): array => [
                (int) $menu->status === 1 ? 0 : 1,
                in_array($menu->uuid, self::WORKSHOP_UUIDS, true) ? 0 : 1,
                $menu->order_by === null ? PHP_INT_MAX : (int) $menu->order_by,
                (int) $menu->id,
            ])
            ->values();

        if ($matches->isNotEmpty()) {
            $canonical = $matches->first(fn (object $menu): bool => (int) $menu->parent_id === $parentId
                || $this->canMoveSubtree((int) $menu->id, $parentId));

            if (! $canonical) {
                // Every matching destination owns descendants that would be
                // pushed beyond level three. Leave every row and status intact.
                return;
            }

            if ((int) $canonical->parent_id !== $parentId) {
                DB::table('page_menus')->where('id', $canonical->id)->update([
                    'parent_id' => $parentId,
                    'updated_at' => now(),
                ]);
            }

            $duplicateIds = $matches
                ->reject(fn (object $menu): bool => (int) $menu->id === (int) $canonical->id)
                ->filter(fn (object $menu): bool => $this->hasNoLiveDescendants((int) $menu->id))
                ->pluck('id');
            if ($duplicateIds->isNotEmpty()) {
                DB::table('page_menus')
                    ->whereIn('id', $duplicateIds)
                    ->where('status', '!=', 0)
                    ->update(['status' => 0, 'updated_at' => now()]);
            }

            return;
        }

        // A matching soft-deleted destination is an editor tombstone. Never
        // resurrect it or silently replace it with a new stable row.
        if ($this->workshopDestinationRows($language, $type)->whereNotNull('deleted_at')->exists()) {
            return;
        }

        if (! $workshopUuid || $this->depthOf((int) $parentId) !== 2) {
            // UUID compatibility is decided globally so every inserted locale
            // shares one logical identity. The target must also be level two.
            return;
        }

        $maximumOrder = DB::table('page_menus')
            ->where('parent_id', $parentId)
            ->whereNull('deleted_at')
            ->max('order_by');

        DB::table('page_menus')->insert([
            'uuid' => $workshopUuid,
            'parent_id' => $parentId,
            'name' => $language === 'bn' ? 'কর্মশালা' : 'Workshop',
            'description' => null,
            'type' => $type,
            'link' => 'frontend.workshops.index',
            'slug' => null,
            'icon' => null,
            'language' => $language,
            'banner_id' => null,
            'order_by' => $maximumOrder === null ? 0 : ((int) $maximumOrder + 1),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function workshopDestinationRows(string $language, string $type)
    {
        return DB::table('page_menus')
            ->where('language', $language)
            ->where('type', $type)
            ->where(function ($query): void {
                $query->where('link', 'frontend.workshops.index')
                    ->orWhere(function ($legacy): void {
                        $legacy->where('link', 'custom')
                            ->whereIn('slug', ['/workshops', 'workshops']);
                    })
                    ->orWhereIn('link', ['/workshops', 'workshops']);
            });
    }

    private function globallyCompatibleWorkshopUuid(): ?string
    {
        foreach (self::WORKSHOP_UUIDS as $candidate) {
            $owners = DB::table('page_menus')->where('uuid', $candidate)->get();
            if ($owners->isEmpty() || $owners->every(fn (object $menu): bool => $menu->type === 'main'
                && $this->isWorkshopDestination($menu))) {
                return $candidate;
            }
        }

        return null;
    }

    private function isWorkshopDestination(object $menu): bool
    {
        $link = trim((string) $menu->link);
        $slug = rtrim(trim((string) $menu->slug), '/');

        return $link === 'frontend.workshops.index'
            || ($link === 'custom' && in_array($slug, ['/workshops', 'workshops'], true))
            || in_array(rtrim($link, '/'), ['/workshops', 'workshops'], true);
    }

    private function canMoveSubtree(int $menuId, int $targetParentId): bool
    {
        $parentDepth = $this->depthOf($targetParentId);
        $subtree = $this->subtreeMetrics($menuId);

        return $parentDepth !== null
            && $subtree !== null
            && ! in_array($targetParentId, $subtree['ids'], true)
            && ($parentDepth + 1 + $subtree['height']) <= 3;
    }

    private function depthOf(int $menuId): ?int
    {
        $depth = 0;
        $visited = [];
        $cursor = $menuId;

        while ($cursor > 0) {
            if (isset($visited[$cursor])) {
                return null;
            }
            $visited[$cursor] = true;

            $menu = DB::table('page_menus')->where('id', $cursor)->whereNull('deleted_at')->first(['parent_id']);
            if (! $menu) {
                return null;
            }

            $depth++;
            $cursor = (int) ($menu->parent_id ?? 0);
        }

        return $depth;
    }

    /** @return array{height: int, ids: list<int>}|null */
    private function subtreeMetrics(int $menuId): ?array
    {
        $visited = [];
        $frontier = [$menuId];
        $height = 0;

        while ($frontier !== []) {
            foreach ($frontier as $id) {
                if (isset($visited[$id])) {
                    return null;
                }
                $visited[$id] = true;
            }

            $children = DB::table('page_menus')
                ->whereIn('parent_id', $frontier)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($children === []) {
                break;
            }

            $height++;
            $frontier = $children;
        }

        return ['height' => $height, 'ids' => array_map('intval', array_keys($visited))];
    }

    private function hasNoLiveDescendants(int $menuId): bool
    {
        return ! DB::table('page_menus')->where('parent_id', $menuId)->whereNull('deleted_at')->exists();
    }
};
