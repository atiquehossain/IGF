<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'org')) {
                $table->string('org', 150)->nullable();
            }
            if (!Schema::hasColumn('users', 'designation')) {
                $table->string('designation', 150)->nullable();
            }
            if (!Schema::hasColumn('users', 'is_approved')) {
                $table->unsignedTinyInteger('is_approved')->default(0)->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['org', 'designation', 'is_approved'],
                fn (string $column) => Schema::hasColumn('users', $column)
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
