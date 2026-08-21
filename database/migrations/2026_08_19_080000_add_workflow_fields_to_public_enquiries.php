<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['sponsorships', 'volunteers', 'contact_messages'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            $missing = array_values(array_filter(
                ['workflow_status', 'assigned_to', 'internal_notes', 'follow_up_at', 'resolved_at'],
                fn (string $column): bool => !Schema::hasColumn($tableName, $column)
            ));

            if ($missing === []) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($missing): void {
                if (in_array('workflow_status', $missing, true)) {
                    $table->string('workflow_status', 24)->default('new')->index();
                }
                if (in_array('assigned_to', $missing, true)) {
                    $table->unsignedBigInteger('assigned_to')->nullable()->index();
                }
                if (in_array('internal_notes', $missing, true)) {
                    $table->text('internal_notes')->nullable();
                }
                if (in_array('follow_up_at', $missing, true)) {
                    $table->timestamp('follow_up_at')->nullable()->index();
                }
                if (in_array('resolved_at', $missing, true)) {
                    $table->timestamp('resolved_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            $columns = array_values(array_filter(
                ['workflow_status', 'assigned_to', 'internal_notes', 'follow_up_at', 'resolved_at'],
                fn (string $column): bool => Schema::hasColumn($tableName, $column)
            ));

            if ($columns !== []) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }
    }
};
