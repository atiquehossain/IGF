<?php

use App\Support\AdminPermissionSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_setting_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('locale', 10);
            $table->longText('snapshot');
            $table->string('reason', 255)->default('Before website settings changed');
            $table->unsignedBigInteger('changed_by')->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['locale', 'created_at'], 'site_setting_revision_locale_created_index');
        });

        app(AdminPermissionSynchronizer::class)->synchronize();
    }

    public function down(): void
    {
        Schema::dropIfExists('site_setting_revisions');

        // Authorization data remains additive so rolling this migration back
        // cannot leave deployed role CSVs pointing at a deleted capability.
    }
};
