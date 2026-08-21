<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('latest_news')) {
            return;
        }

        $missing = array_values(array_filter(
            ['biography', 'qualification', 'social_links'],
            fn (string $column): bool => !Schema::hasColumn('latest_news', $column)
        ));

        if ($missing === []) {
            return;
        }

        Schema::table('latest_news', function (Blueprint $table) use ($missing): void {
            if (in_array('biography', $missing, true)) {
                $table->text('biography')->nullable()->after('description');
            }
            if (in_array('qualification', $missing, true)) {
                $table->string('qualification')->nullable()->after('biography');
            }
            if (in_array('social_links', $missing, true)) {
                $table->json('social_links')->nullable()->after('url');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('latest_news')) {
            return;
        }

        $columns = array_values(array_filter(
            ['biography', 'qualification', 'social_links'],
            fn (string $column): bool => Schema::hasColumn('latest_news', $column)
        ));

        if ($columns !== []) {
            Schema::table('latest_news', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
