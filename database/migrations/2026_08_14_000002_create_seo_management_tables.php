<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('seoable_type', 150)->nullable();
            $table->unsignedBigInteger('seoable_id')->nullable();
            $table->string('route_name', 150)->nullable();
            $table->text('route_path')->nullable();
            $table->string('locale', 10)->default('en');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('focus_keyword')->nullable();
            $table->text('canonical_url')->nullable();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->text('og_image')->nullable();
            $table->string('twitter_card', 40)->default('summary_large_image');
            $table->string('twitter_title')->nullable();
            $table->text('twitter_description')->nullable();
            $table->text('twitter_image')->nullable();
            $table->longText('schema_markup')->nullable();
            $table->decimal('sitemap_priority', 2, 1)->default(0.5);
            $table->string('sitemap_change_frequency', 20)->default('monthly');
            $table->boolean('exclude_from_sitemap')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['seoable_type', 'seoable_id']);
            $table->index(['route_name', 'locale']);
        });

        Schema::create('seo_redirects', function (Blueprint $table) {
            $table->id();
            $table->text('from_path');
            $table->text('to_url');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_redirects');
        Schema::dropIfExists('seo_metadata');
    }
};
