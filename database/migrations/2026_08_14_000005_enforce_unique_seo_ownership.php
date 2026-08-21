<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateRoutes = DB::table('seo_metadata')
            ->select('route_name', 'locale')
            ->whereNotNull('route_name')
            ->groupBy('route_name', 'locale')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        $duplicateModels = DB::table('seo_metadata')
            ->select('seoable_type', 'seoable_id', 'locale')
            ->whereNotNull('seoable_type')
            ->whereNotNull('seoable_id')
            ->groupBy('seoable_type', 'seoable_id', 'locale')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateRoutes || $duplicateModels) {
            throw new \RuntimeException('Duplicate SEO ownership records must be reconciled before unique constraints can be installed.');
        }

        Schema::table('seo_metadata', function (Blueprint $table) {
            $table->unique(['route_name', 'locale'], 'seo_metadata_route_locale_unique');
            $table->unique(['seoable_type', 'seoable_id', 'locale'], 'seo_metadata_owner_locale_unique');
        });
    }

    public function down(): void
    {
        Schema::table('seo_metadata', function (Blueprint $table) {
            $table->dropUnique('seo_metadata_route_locale_unique');
            $table->dropUnique('seo_metadata_owner_locale_unique');
        });
    }
};
