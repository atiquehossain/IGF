<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reusable_blocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('type', 80);
            $table->string('locale', 10)->default('*');
            $table->json('content')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['locale', 'is_enabled']);
        });

        Schema::table('page_blocks', function (Blueprint $table) {
            $table->foreignId('reusable_block_id')
                ->nullable()
                ->after('page_id')
                ->constrained('reusable_blocks')
                ->nullOnDelete();
            $table->index('reusable_block_id');
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('disk', 40)->default('public');
            $table->string('path', 2048);
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->string('locale', 10)->default('*');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['mime_type', 'created_at']);
            $table->index(['locale', 'created_at']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->string('publication_status', 30)->default('draft')->after('status');
            $table->string('visibility', 20)->default('public')->after('publication_status');
            $table->timestamp('scheduled_for')->nullable()->after('published_at');
            $table->timestamp('last_published_at')->nullable()->after('scheduled_for');
            $table->unsignedBigInteger('published_by')->nullable()->after('publish_by');
            $table->index(['publication_status', 'scheduled_for']);
        });

        DB::table('pages')->where('status', 1)->update([
            'publication_status' => 'published',
            'last_published_at' => DB::raw('updated_at'),
        ]);

        Schema::table('page_menus', function (Blueprint $table) {
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['language', 'type', 'parent_id', 'order_by'], 'page_menus_navigation_index');
        });
    }

    public function down(): void
    {
        Schema::table('page_menus', function (Blueprint $table) {
            $table->dropIndex('page_menus_navigation_index');
            $table->dropColumn('deleted_by');
            $table->dropSoftDeletes();
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex(['publication_status', 'scheduled_for']);
            $table->dropColumn([
                'publication_status',
                'visibility',
                'scheduled_for',
                'last_published_at',
                'published_by',
            ]);
        });

        Schema::dropIfExists('media_assets');

        Schema::table('page_blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reusable_block_id');
        });

        Schema::dropIfExists('reusable_blocks');
    }
};
