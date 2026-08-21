<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'albums',
        'annual_reports',
        'banners',
        'categories',
        'donation_types',
        'event_calendars',
        'galleries',
        'latest_news',
        'notice_boards',
        'splash_screens',
        'tags',
        'testimonials',
        'volunteer_causes',
        'you_tubes',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->softDeletes();
                $table->unsignedBigInteger('deleted_by')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('deleted_by');
                $table->dropSoftDeletes();
            });
        }
    }
};
