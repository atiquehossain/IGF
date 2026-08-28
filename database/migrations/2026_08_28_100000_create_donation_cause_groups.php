<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GROUPS = [
        [
            'uuid' => '73000000-0000-4000-8000-000000000001',
            'name' => 'General Giving',
            'description' => 'Flexible support for the foundation’s most active community priorities.',
            'slug' => 'general-giving',
            'display_order' => 10,
            'cause_slugs' => ['where-it-is-needed-most'],
        ],
        [
            'uuid' => '73000000-0000-4000-8000-000000000002',
            'name' => 'Education & Children',
            'description' => 'Learning, school, youth, and child-wellbeing initiatives.',
            'slug' => 'education-children',
            'display_order' => 20,
            'cause_slugs' => [
                'education',
                'orphan-support',
                'school-stationery',
                'school-uniforms',
                'school-meals',
                'adopt-a-school',
                'youth-development',
                'street-children-education',
            ],
        ],
        [
            'uuid' => '73000000-0000-4000-8000-000000000003',
            'name' => 'Faith & Seasonal Giving',
            'description' => 'Zakat, Sadaqah, Ramadan, and other seasonal giving opportunities.',
            'slug' => 'faith-seasonal-giving',
            'display_order' => 30,
            'cause_slugs' => ['zakat', 'sadaqah', 'ramadan-iftar', 'qurbani'],
        ],
        [
            'uuid' => '73000000-0000-4000-8000-000000000004',
            'name' => 'Community & Relief',
            'description' => 'Essential support, resilience, clean water, and emergency response.',
            'slug' => 'community-relief',
            'display_order' => 40,
            'cause_slugs' => [
                'food-support',
                'emergency-relief',
                'pure-water-and-sanitation',
                'women-empowerment',
            ],
        ],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('donation_cause_groups')) {
            Schema::create('donation_cause_groups', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('slug', 120)->unique();
                $table->unsignedInteger('display_order')->default(0);
                $table->boolean('status')->default(true);
                $table->integer('created_by')->nullable();
                $table->integer('updated_by')->nullable();
                $table->timestamps();

                $table->index(['status', 'display_order'], 'donation_cause_groups_public_index');
            });
        }

        if (!Schema::hasTable('donation_types')) {
            return;
        }

        if (!Schema::hasColumn('donation_types', 'donation_cause_group_id')) {
            Schema::table('donation_types', function (Blueprint $table): void {
                $table->foreignId('donation_cause_group_id')
                    ->nullable()
                    ->constrained('donation_cause_groups')
                    ->restrictOnDelete();
                $table->index(
                    ['donation_cause_group_id', 'status'],
                    'donation_types_group_catalog_index'
                );
            });
        }

        foreach (self::GROUPS as $definition) {
            $group = DB::table('donation_cause_groups')
                ->where('uuid', $definition['uuid'])
                ->orWhere('slug', $definition['slug'])
                ->first();

            if (!$group) {
                $now = now();
                $groupId = DB::table('donation_cause_groups')->insertGetId([
                    'uuid' => $definition['uuid'],
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'slug' => $definition['slug'],
                    'display_order' => $definition['display_order'],
                    'status' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $groupId = (int) $group->id;
            }

            DB::table('donation_types')
                ->whereNull('donation_cause_group_id')
                ->whereIn('slug', $definition['cause_slugs'])
                ->update(['donation_cause_group_id' => $groupId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('donation_types')
            && Schema::hasColumn('donation_types', 'donation_cause_group_id')) {
            if (Schema::hasIndex('donation_types', 'donation_types_group_catalog_index')) {
                Schema::table('donation_types', fn (Blueprint $table) => $table
                    ->dropIndex('donation_types_group_catalog_index'));
            }
            Schema::table('donation_types', fn (Blueprint $table) => $table
                ->dropConstrainedForeignId('donation_cause_group_id'));
        }

        Schema::dropIfExists('donation_cause_groups');
    }
};
