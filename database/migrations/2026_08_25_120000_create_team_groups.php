<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('team_groups')) {
            Schema::create('team_groups', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('slug', 120);
                $table->unsignedInteger('order_by')->default(0);
                $table->tinyInteger('status')->default(1);
                $table->string('language', 10)->default('en');
                $table->integer('created_by')->nullable();
                $table->integer('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['language', 'slug']);
                $table->index(['language', 'status', 'order_by']);
            });
        }

        if (!Schema::hasTable('latest_news')) {
            return;
        }

        if (!Schema::hasColumn('latest_news', 'team_group_id')) {
            Schema::table('latest_news', function (Blueprint $table): void {
                $table->unsignedBigInteger('team_group_id')->nullable()->after('category_id');
                $table->foreign('team_group_id')
                    ->references('id')
                    ->on('team_groups')
                    ->restrictOnDelete();
                $table->index(['type', 'language', 'team_group_id', 'status'], 'latest_news_team_group_public_index');
            });
        }

        $languages = DB::table('latest_news')
            ->where('type', 'our-members')
            ->pluck('language')
            ->filter(fn ($language): bool => is_string($language) && trim($language) !== '')
            ->map(fn (string $language): string => mb_substr(trim($language), 0, 10))
            ->push('en')
            ->unique()
            ->values();

        foreach ($languages as $language) {
            $group = DB::table('team_groups')
                ->where('language', $language)
                ->where('slug', 'board-of-directors')
                ->first();

            if (!$group) {
                $now = now();
                $id = DB::table('team_groups')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Board of directors',
                    'description' => 'The board provides mission stewardship, oversight, and accountability.',
                    'slug' => 'board-of-directors',
                    'order_by' => 100,
                    'status' => 1,
                    'language' => $language,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $id = (int) $group->id;
            }

            DB::table('latest_news')
                ->where('type', 'our-members')
                ->where('language', $language)
                ->whereNull('team_group_id')
                ->update(['team_group_id' => $id]);
        }

        $englishGroupId = DB::table('team_groups')
            ->where('language', 'en')
            ->where('slug', 'board-of-directors')
            ->value('id');

        if ($englishGroupId) {
            DB::table('latest_news')
                ->where('type', 'our-members')
                ->whereNull('language')
                ->whereNull('team_group_id')
                ->update(['team_group_id' => $englishGroupId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('latest_news') && Schema::hasColumn('latest_news', 'team_group_id')) {
            Schema::table('latest_news', function (Blueprint $table): void {
                $table->dropForeign(['team_group_id']);
                $table->dropIndex('latest_news_team_group_public_index');
                $table->dropColumn('team_group_id');
            });
        }

        Schema::dropIfExists('team_groups');
    }
};
