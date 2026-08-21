<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pages', 'is_funding_project')) {
            return;
        }

        $fundingCategoryUuids = DB::table('categories')
            ->whereIn('slug', ['our-causes', 'programs', 'projects'])
            ->whereNotNull('uuid')
            ->pluck('uuid')
            ->unique();

        if ($fundingCategoryUuids->isEmpty()) {
            return;
        }

        // Category translations share a logical UUID but can have different
        // row IDs (and, in some installations, localized slugs). Resolve every
        // locale row before collecting the logical Page identities.
        $fundingCategoryIds = DB::table('categories')
            ->whereIn('uuid', $fundingCategoryUuids)
            ->pluck('id');
        $fundingPageUuids = DB::table('pages')
            ->whereIn('category_id', $fundingCategoryIds)
            ->whereNotNull('uuid')
            ->pluck('uuid')
            ->unique();

        if ($fundingPageUuids->isEmpty()) {
            return;
        }

        DB::table('pages')
            ->whereIn('uuid', $fundingPageUuids)
            ->where('is_funding_project', false)
            ->update(['is_funding_project' => true]);
    }

    public function down(): void
    {
        // Intentionally forward-only. Once a page has become an available
        // financial destination, a rollback must not silently revoke it. An
        // authorized administrator can review and clear the control explicitly.
    }
};
