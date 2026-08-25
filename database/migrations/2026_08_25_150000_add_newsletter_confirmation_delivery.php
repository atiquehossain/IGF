<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('subscribers', 'confirmation_sent_at')) {
            Schema::table('subscribers', function (Blueprint $table): void {
                $table->timestamp('confirmation_sent_at')->nullable()->after('confirmed_at');
                $table->index('confirmation_sent_at', 'subscribers_confirmation_sent_at_index');
                $table->unique('uuid', 'subscribers_uuid_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscribers', 'confirmation_sent_at')) {
            Schema::table('subscribers', function (Blueprint $table): void {
                $table->dropUnique('subscribers_uuid_unique');
                $table->dropIndex('subscribers_confirmation_sent_at_index');
                $table->dropColumn('confirmation_sent_at');
            });
        }
    }
};
