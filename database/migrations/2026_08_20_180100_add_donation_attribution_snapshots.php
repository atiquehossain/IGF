<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'cause_uuid_snapshot' => fn (Blueprint $table) => $table->uuid('cause_uuid_snapshot')->nullable()->after('payment_cause'),
            'cause_slug_snapshot' => fn (Blueprint $table) => $table->string('cause_slug_snapshot')->nullable()->after('cause_uuid_snapshot'),
            'cause_name_snapshot' => fn (Blueprint $table) => $table->string('cause_name_snapshot')->nullable()->after('cause_slug_snapshot'),
            'purpose_key_snapshot' => fn (Blueprint $table) => $table->string('purpose_key_snapshot', 30)->nullable()->after('cause_name_snapshot'),
            'destination_type_snapshot' => fn (Blueprint $table) => $table->string('destination_type_snapshot', 30)->nullable()->after('purpose_key_snapshot'),
            'destination_uuid_snapshot' => fn (Blueprint $table) => $table->uuid('destination_uuid_snapshot')->nullable()->after('destination_type_snapshot'),
            'destination_name_snapshot' => fn (Blueprint $table) => $table->string('destination_name_snapshot')->nullable()->after('destination_uuid_snapshot'),
            'project_uuid_snapshot' => fn (Blueprint $table) => $table->uuid('project_uuid_snapshot')->nullable()->after('destination_name_snapshot'),
            'project_name_snapshot' => fn (Blueprint $table) => $table->string('project_name_snapshot')->nullable()->after('project_uuid_snapshot'),
        ];
        foreach ($columns as $name => $definition) {
            if (!Schema::hasColumn('donations', $name)) {
                Schema::table('donations', $definition);
            }
        }

        foreach ([
            'donations_cause_snapshot_index' => 'cause_uuid_snapshot',
            'donations_cause_slug_snapshot_index' => 'cause_slug_snapshot',
            'donations_destination_snapshot_index' => 'destination_uuid_snapshot',
            'donations_project_snapshot_index' => 'project_uuid_snapshot',
        ] as $index => $column) {
            if (!Schema::hasIndex('donations', $index)) {
                Schema::table('donations', fn (Blueprint $table) => $table->index($column, $index));
            }
        }

        DB::table('donations')->orderBy('id')->chunkById(200, function ($donations): void {
            foreach ($donations as $donation) {
                $cause = $donation->payment_cause
                    ? DB::table('donation_types')->where('uuid', $donation->payment_cause)->first()
                    : null;

                $isUnresolvedLegacy = !$cause;
                $destinationType = $isUnresolvedLegacy
                    ? 'legacy_unspecified'
                    : (string) ($cause->destination_type ?? 'restricted_fund');
                $destinationUuid = match ($destinationType) {
                    'category' => $cause->destination_category_uuid ?? null,
                    'page' => $cause->destination_page_uuid ?? null,
                    default => null,
                };
                $destinationName = trim((string) ($cause->destination_name ?? ''));

                if ($destinationName === '' && $destinationType === 'category' && $destinationUuid) {
                    $destinationName = (string) (DB::table('categories')
                        ->where('uuid', $destinationUuid)
                        ->orderByRaw("CASE WHEN language = 'en' THEN 0 ELSE 1 END")
                        ->value('name') ?? 'Program');
                }
                if ($destinationName === '' && $destinationType === 'page' && $destinationUuid) {
                    $destinationName = (string) (DB::table('pages')
                        ->where('uuid', $destinationUuid)
                        ->orderByRaw("CASE WHEN language = 'en' THEN 0 ELSE 1 END")
                        ->value('name') ?? 'Project');
                }
                if ($destinationName === '') {
                    $destinationName = match ($destinationType) {
                        'unrestricted' => 'Where it is needed most',
                        'legacy_unspecified' => 'Unresolved legacy designation — allocation blocked',
                        default => trim((string) ($cause->name ?? 'Restricted fund')),
                    };
                }

                $facts = [
                    'cause_uuid_snapshot' => $cause->uuid ?? null,
                    'cause_slug_snapshot' => $cause->slug ?? 'unresolved-legacy-gift',
                    'cause_name_snapshot' => $cause->name ?? 'Unresolved legacy gift',
                    'purpose_key_snapshot' => $cause->purpose_key ?? null,
                    'destination_type_snapshot' => $destinationType,
                    'destination_uuid_snapshot' => $destinationUuid,
                    'destination_name_snapshot' => $destinationName,
                    'project_uuid_snapshot' => $destinationType === 'page' ? $destinationUuid : null,
                    'project_name_snapshot' => $destinationType === 'page' ? $destinationName : null,
                ];
                $updates = [];
                foreach ($facts as $column => $value) {
                    // Attribution is immutable once populated, including when
                    // the original cause row no longer exists. A partial rerun
                    // may fill blanks but must never replace known history.
                    if ($donation->{$column} === null) {
                        $updates[$column] = $value;
                    }
                }
                if ($updates !== []) {
                    DB::table('donations')->where('id', $donation->id)->update($updates);
                }
            }
        });
    }

    public function down(): void
    {
        $columns = [
            'cause_uuid_snapshot',
            'cause_slug_snapshot',
            'cause_name_snapshot',
            'purpose_key_snapshot',
            'destination_type_snapshot',
            'destination_uuid_snapshot',
            'destination_name_snapshot',
            'project_uuid_snapshot',
            'project_name_snapshot',
        ];
        $present = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn('donations', $column)
        ));
        if ($present !== [] && DB::table('donations')->where(function ($query) use ($present): void {
            foreach ($present as $index => $column) {
                $method = $index === 0 ? 'whereNotNull' : 'orWhereNotNull';
                $query->{$method}($column);
            }
        })->exists()) {
            throw new RuntimeException(
                'Rollback refused: donation attribution snapshots contain financial history. Preserve the columns or reconcile and archive the data explicitly.'
            );
        }

        foreach ([
            'donations_cause_snapshot_index',
            'donations_cause_slug_snapshot_index',
            'donations_destination_snapshot_index',
            'donations_project_snapshot_index',
        ] as $index) {
            if (Schema::hasIndex('donations', $index)) {
                Schema::table('donations', fn (Blueprint $table) => $table->dropIndex($index));
            }
        }
        if ($present !== []) {
            Schema::table('donations', fn (Blueprint $table) => $table->dropColumn($present));
        }
    }
};
