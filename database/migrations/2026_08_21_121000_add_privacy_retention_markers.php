<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['contact_messages', 'sponsorships', 'volunteers', 'chat_conversations'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'anonymized_at')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestamp('anonymized_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'anonymized_at')) {
                continue;
            }
            $indexName = $tableName . '_anonymized_at_index';
            $hasIndex = collect(Schema::getIndexes($tableName))
                ->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexName);
            if ($hasIndex) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($indexName));
            }
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('anonymized_at'));
        }
    }
};
