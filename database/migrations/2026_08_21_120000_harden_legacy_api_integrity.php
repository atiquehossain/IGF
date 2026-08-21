<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('you_tube_watches')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('you_tubes')
                    ->whereColumn('you_tubes.video_id', 'you_tube_watches.video_id');
            })
            ->delete();

        // The original TEXT column cannot participate in a portable unique
        // index (notably on MySQL). YouTube IDs are already capped at 30
        // characters by the canonical videos table and request validation.
        Schema::table('you_tube_watches', function (Blueprint $table): void {
            $table->string('video_id', 30)->change();
        });

        // Old clients inserted one row per progress report. Consolidate those
        // rows before enforcing the idempotent user/video contract.
        do {
            // Always read from the beginning: deleting duplicate groups makes
            // offset-based chunking skip every second page of results.
            $groups = DB::table('you_tube_watches')
                ->select('user_id', 'video_id')
                ->whereNotNull('user_id')
                ->whereNotNull('video_id')
                ->groupBy('user_id', 'video_id')
                ->havingRaw('COUNT(*) > 1')
                ->orderBy('user_id')
                ->orderBy('video_id')
                ->limit(100)
                ->get();

            foreach ($groups as $group) {
                $rows = DB::table('you_tube_watches')
                    ->where('user_id', $group->user_id)
                    ->where('video_id', $group->video_id)
                    ->orderBy('id')
                    ->get();
                $keep = $rows->first();

                DB::table('you_tube_watches')->where('id', $keep->id)->update([
                    'duration_time' => $rows->max('duration_time'),
                    'status' => $rows->max('status'),
                    'updated_at' => $rows->max('updated_at') ?: now(),
                ]);
                DB::table('you_tube_watches')
                    ->where('user_id', $group->user_id)
                    ->where('video_id', $group->video_id)
                    ->where('id', '!=', $keep->id)
                    ->delete();
            }
        } while ($groups->isNotEmpty());

        if (!Schema::hasIndex('you_tube_watches', 'youtube_watches_user_video_unique')) {
            Schema::table('you_tube_watches', function (Blueprint $table): void {
                $table->unique(['user_id', 'video_id'], 'youtube_watches_user_video_unique');
            });
        }

        if (!Schema::hasIndex('districts', 'districts_division_lookup')) {
            Schema::table('districts', function (Blueprint $table): void {
                $table->index('division_id', 'districts_division_lookup');
            });
        }
        if (!Schema::hasIndex('upazilas', 'upazilas_district_lookup')) {
            Schema::table('upazilas', function (Blueprint $table): void {
                $table->index('district_id', 'upazilas_district_lookup');
            });
        }
        if (!Schema::hasIndex('users', 'users_geography_lookup')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->index(['division_id', 'district_id', 'upazila_id'], 'users_geography_lookup');
            });
        }
        if (!Schema::hasIndex('comments', 'comments_public_lookup')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->index(['page_id', 'status', 'is_delete'], 'comments_public_lookup');
            });
        }
        if (!Schema::hasIndex('likes', 'likes_comment_lookup')) {
            Schema::table('likes', function (Blueprint $table): void {
                $table->index(['comment_id', 'status'], 'likes_comment_lookup');
            });
        }
    }

    public function down(): void
    {
        // Intentionally one-way. Removing the uniqueness contract during a
        // rollback would immediately allow duplicate progress rows and would
        // discard the integrity guarantee this migration established. Restore
        // a verified pre-migration backup when a true schema rollback is
        // required.
    }
};
