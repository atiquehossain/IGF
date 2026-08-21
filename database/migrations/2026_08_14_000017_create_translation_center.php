<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_locales', function (Blueprint $table) {
            $table->string('locale', 10)->primary();
            $table->string('name', 80);
            $table->string('native_name', 80);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('enabled_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('translation_strings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 255);
            $table->string('locale', 10);
            $table->longText('value')->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->string('status', 30)->default('draft');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['key', 'locale']);
            $table->index(['locale', 'status']);
        });

        Schema::table('page_blocks', function (Blueprint $table) {
            $table->uuid('translation_key')->nullable()->after('uuid');
            $table->index(['page_id', 'translation_key']);
        });

        DB::table('page_blocks')->whereNull('translation_key')->update([
            'translation_key' => DB::raw('uuid'),
        ]);

        $now = now();
        DB::table('translation_locales')->insert([
            [
                'locale' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'is_default' => true,
                'is_enabled' => true,
                'enabled_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'locale' => 'bn',
                'name' => 'Bangla',
                'native_name' => 'বাংলা',
                'is_default' => false,
                'is_enabled' => false,
                'enabled_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('page_blocks', function (Blueprint $table) {
            $table->dropIndex(['page_id', 'translation_key']);
            $table->dropColumn('translation_key');
        });

        Schema::dropIfExists('translation_strings');
        Schema::dropIfExists('translation_locales');
    }
};
