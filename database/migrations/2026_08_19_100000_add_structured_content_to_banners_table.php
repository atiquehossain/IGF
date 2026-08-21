<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('eyebrow')->nullable()->after('name');
            $table->string('headline')->nullable()->after('eyebrow');
            $table->string('subheadline')->nullable()->after('headline');
            $table->string('image_alt')->nullable()->after('path');
            $table->string('cta_label')->nullable()->after('description');
            $table->text('cta_url')->nullable()->after('cta_label');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn([
                'eyebrow',
                'headline',
                'subheadline',
                'image_alt',
                'cta_label',
                'cta_url',
            ]);
        });
    }
};
