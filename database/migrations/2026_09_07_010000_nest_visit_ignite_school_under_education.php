<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const EDUCATION_UUIDS = [
        '68000000-0003-4000-8000-000000000002',
        '69000000-0003-4000-8000-000000000002',
    ];

    private const OUR_WORK_UUIDS = [
        '67000000-0000-4000-8000-000000000003',
        '69000000-0000-4000-8000-000000000003',
    ];

    private const VISIT_SCHOOL_UUIDS = [
        '68000000-0003-4000-8000-000000000003',
        '69000000-0003-4000-8000-000000000003',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('page_menus')) {
            return;
        }

        DB::transaction(function (): void {
            $visitMenus = DB::table('page_menus')
                ->where('type', 'main')
                ->whereNull('deleted_at')
                ->where(function ($query): void {
                    $query->whereIn('uuid', self::VISIT_SCHOOL_UUIDS)
                        ->orWhere(function ($destination): void {
                            $destination->where('link', 'frontend.category')
                                ->where('slug', 'visit-ignite-school');
                        });
                })
                ->get();

            foreach ($visitMenus as $visitMenu) {
                if (! $this->isVisitSchoolDestination($visitMenu)) {
                    continue;
                }

                $educationMenu = $this->findEducationMenu($visitMenu);

                if (! $educationMenu || (int) $visitMenu->parent_id === (int) $educationMenu->id) {
                    continue;
                }

                if (! $this->canMoveSubtree((int) $visitMenu->id, (int) $educationMenu->id)) {
                    // Never make a fourth public-navigation level or create a cycle.
                    continue;
                }

                DB::table('page_menus')->where('id', $visitMenu->id)->update([
                    'parent_id' => $educationMenu->id,
                    'order_by' => 0,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    private function findEducationMenu(object $visitMenu): ?object
    {
        return DB::table('page_menus')
            ->where('language', $visitMenu->language)
            ->where('type', $visitMenu->type)
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                $query->whereIn('uuid', self::EDUCATION_UUIDS)
                    ->orWhere(function ($destination): void {
                        $destination->where('link', 'frontend.page')
                            ->where('slug', 'education');
                    });
            })
            ->get()
            ->filter(fn (object $menu): bool => $this->isEducationDestination($menu)
                && $this->isOurWorkChild($menu))
            ->sortBy(fn (object $menu): array => [
                in_array($menu->uuid, self::EDUCATION_UUIDS, true) ? 0 : 1,
                (int) $menu->status === 1 ? 0 : 1,
                (int) $menu->id,
            ])
            ->first();
    }

    private function isEducationDestination(object $menu): bool
    {
        return $menu->link === 'frontend.page'
            && ltrim((string) $menu->slug, '/') === 'education';
    }

    private function isVisitSchoolDestination(object $menu): bool
    {
        return $menu->link === 'frontend.category'
            && ltrim((string) $menu->slug, '/') === 'visit-ignite-school';
    }

    private function isOurWorkChild(object $menu): bool
    {
        if (! $menu->parent_id) {
            return false;
        }

        $parent = DB::table('page_menus')
            ->where('id', $menu->parent_id)
            ->where('language', $menu->language)
            ->where('type', $menu->type)
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->first();
        if (! $parent) {
            return false;
        }

        $normalizedName = mb_strtolower(trim((string) $parent->name));

        return in_array($parent->uuid, self::OUR_WORK_UUIDS, true)
            || ($parent->link === 'custom'
                && in_array(trim((string) $parent->slug), ['', '#'], true)
                && in_array($normalizedName, ['our work', 'আমাদের কাজ'], true));
    }

    public function down(): void
    {
        // Intentionally irreversible: a rollback must not overwrite menu
        // hierarchy or ordering subsequently chosen by an administrator.
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

            $menu = DB::table('page_menus')
                ->where('id', $cursor)
                ->whereNull('deleted_at')
                ->first(['parent_id']);
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
};
