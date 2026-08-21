<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_metadata_revisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('seo_metadata_id')->nullable()->index();
            $table->string('seoable_type', 150)->nullable();
            $table->unsignedBigInteger('seoable_id')->nullable();
            $table->string('route_name', 150)->nullable();
            $table->string('locale', 10)->default('en');
            $table->json('snapshot');
            $table->string('reason', 255)->default('Before SEO update');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['seoable_type', 'seoable_id', 'locale'], 'seo_revision_owner_locale_index');
            $table->index(['route_name', 'locale'], 'seo_revision_route_locale_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_metadata_revisions');
    }
};
