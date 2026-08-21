<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notice_boards', function (Blueprint $table): void {
            $table->string('content_kind', 20)->default('article')->after('notice_type');
            $table->dateTimeTz('event_start_at')->nullable()->after('published_at');
            $table->dateTimeTz('event_end_at')->nullable()->after('event_start_at');
            $table->string('event_status', 30)->nullable()->after('event_end_at');
            $table->string('event_attendance_mode', 20)->nullable()->after('event_status');
            $table->index(['content_kind', 'event_start_at'], 'notice_boards_event_schedule_index');
        });
    }

    public function down(): void
    {
        Schema::table('notice_boards', function (Blueprint $table): void {
            $table->dropIndex('notice_boards_event_schedule_index');
            $table->dropColumn([
                'content_kind',
                'event_start_at',
                'event_end_at',
                'event_status',
                'event_attendance_mode',
            ]);
        });
    }
};
