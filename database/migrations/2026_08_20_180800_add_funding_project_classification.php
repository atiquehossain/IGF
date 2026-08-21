<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pages', 'is_funding_project')) {
            Schema::table('pages', fn (Blueprint $table) => $table
                ->boolean('is_funding_project')
                ->default(false)
                ->after('is_zakat_eligible'));
        }
        if (!Schema::hasIndex('pages', 'pages_funding_project_index')) {
            Schema::table('pages', fn (Blueprint $table) => $table
                ->index('is_funding_project', 'pages_funding_project_index'));
        }

        $taggedPageIds = DB::table('page_tag_modules as page_tags')
            ->join('tags', 'tags.id', '=', 'page_tags.tag_id')
            ->whereIn('tags.slug', ['current-project', 'completed-project'])
            ->pluck('page_tags.page_id');
        $fixedDestinationUuids = DB::table('donation_types')
            ->where('destination_type', 'page')
            ->whereNotNull('destination_page_uuid')
            ->pluck('destination_page_uuid');
        $fundingCategoryUuids = DB::table('categories')
            ->whereIn('slug', ['our-causes', 'programs', 'projects'])
            ->whereNotNull('uuid')
            ->pluck('uuid')
            ->unique();
        $fundingCategoryIds = $fundingCategoryUuids->isEmpty()
            ? collect()
            : DB::table('categories')
                ->whereIn('uuid', $fundingCategoryUuids)
                ->pluck('id');

        // Existing cause/program/project pages are already public fundraising
        // destinations in this installation. Classify their complete logical
        // identity across locales, alongside the stronger explicit signals.
        $logicalUuids = DB::table('pages')
            ->where(function ($query) use ($taggedPageIds, $fixedDestinationUuids, $fundingCategoryIds): void {
                $query->where('is_zakat_eligible', true);
                if ($taggedPageIds->isNotEmpty()) {
                    $query->orWhereIn('id', $taggedPageIds);
                }
                if ($fixedDestinationUuids->isNotEmpty()) {
                    $query->orWhereIn('uuid', $fixedDestinationUuids);
                }
                if ($fundingCategoryIds->isNotEmpty()) {
                    $query->orWhereIn('category_id', $fundingCategoryIds);
                }
            })
            ->pluck('uuid')
            ->filter()
            ->unique();

        if ($logicalUuids->isNotEmpty()) {
            DB::table('pages')->whereIn('uuid', $logicalUuids)->update(['is_funding_project' => true]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('pages', 'is_funding_project')) {
            return;
        }
        if (DB::table('pages')->where('is_funding_project', true)->exists()) {
            throw new RuntimeException(
                'Rollback refused: fundable project classifications are active financial controls.'
            );
        }
        if (Schema::hasIndex('pages', 'pages_funding_project_index')) {
            Schema::table('pages', fn (Blueprint $table) => $table->dropIndex('pages_funding_project_index'));
        }
        Schema::table('pages', fn (Blueprint $table) => $table->dropColumn('is_funding_project'));
    }
};
