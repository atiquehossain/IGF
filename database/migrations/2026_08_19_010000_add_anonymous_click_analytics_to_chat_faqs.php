<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_faqs', function (Blueprint $table): void {
            $table->unsignedBigInteger('click_count')->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('chat_faqs', function (Blueprint $table): void {
            $table->dropColumn('click_count');
        });
    }
};
