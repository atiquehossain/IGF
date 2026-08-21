<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const SUGGESTED_CAUSES = [
        [
            'uuid' => '6e3c1d7a-8b01-4f01-8a01-000000000001',
            'name' => 'Sadaqah',
            'slug' => 'sadaqah',
            'description' => null,
            'destination_name' => 'Sadaqah Fund',
        ],
        [
            'uuid' => '6e3c1d7a-8b01-4f01-8a01-000000000002',
            'name' => 'Food Support',
            'slug' => 'food-support',
            'description' => null,
            'destination_name' => 'Food Support Fund',
        ],
        [
            'uuid' => '6e3c1d7a-8b01-4f01-8a01-000000000003',
            'name' => 'Emergency Relief',
            'slug' => 'emergency-relief',
            'description' => null,
            'destination_name' => 'Emergency Relief Fund',
        ],
        [
            'uuid' => '6e3c1d7a-8b01-4f01-8a01-000000000004',
            'name' => 'Orphan Support',
            'slug' => 'orphan-support',
            'description' => null,
            'destination_name' => 'Orphan Support Fund',
        ],
    ];

    public function up(): void
    {
        $causeColumns = [
            'slug' => fn (Blueprint $table) => $table->string('slug')->nullable()->after('uuid'),
            'destination_type' => fn (Blueprint $table) => $table->string('destination_type', 30)->nullable()->after('purpose_key'),
            'destination_name' => fn (Blueprint $table) => $table->string('destination_name')->nullable()->after('destination_type'),
            'destination_category_uuid' => fn (Blueprint $table) => $table->uuid('destination_category_uuid')->nullable()->after('destination_name'),
            'destination_page_uuid' => fn (Blueprint $table) => $table->uuid('destination_page_uuid')->nullable()->after('destination_category_uuid'),
            'image' => fn (Blueprint $table) => $table->string('image', 2048)->nullable()->after('description'),
            'image_media_uuid' => fn (Blueprint $table) => $table->uuid('image_media_uuid')->nullable()->after('image'),
        ];
        foreach ($causeColumns as $name => $definition) {
            if (!Schema::hasColumn('donation_types', $name)) {
                Schema::table('donation_types', $definition);
            }
        }
        foreach ([
            'donation_types_destination_type_index' => 'destination_type',
            'donation_types_destination_category_index' => 'destination_category_uuid',
            'donation_types_destination_page_index' => 'destination_page_uuid',
            'donation_types_image_media_index' => 'image_media_uuid',
        ] as $index => $column) {
            if (!Schema::hasIndex('donation_types', $index)) {
                Schema::table('donation_types', fn (Blueprint $table) => $table->index($column, $index));
            }
        }

        if (!Schema::hasColumn('pages', 'is_zakat_eligible')) {
            Schema::table('pages', fn (Blueprint $table) => $table->boolean('is_zakat_eligible')->default(false));
        }
        if (!Schema::hasIndex('pages', 'pages_zakat_eligible_index')) {
            Schema::table('pages', fn (Blueprint $table) => $table->index('is_zakat_eligible', 'pages_zakat_eligible_index'));
        }

        $usedSlugs = [];
        DB::table('donation_types')->orderBy('id')->get()->each(function (object $cause) use (&$usedSlugs): void {
            $existingSlug = trim((string) ($cause->slug ?? ''));
            $base = $existingSlug !== ''
                ? $existingSlug
                : (Str::slug((string) $cause->name) ?: 'donation-cause');
            $slug = $base;
            $suffix = 2;
            while (isset($usedSlugs[$slug])) {
                $slug = $base . '-' . $suffix++;
            }
            $usedSlugs[$slug] = true;

            $normalizedName = mb_strtolower(trim((string) $cause->name));
            $isGeneral = in_array($normalizedName, [
                'where it is needed most',
                'general',
                'general donation',
                'general fund',
                'unrestricted',
            ], true);
            $isZakat = (string) ($cause->purpose_key ?? '') === 'zakat'
                || $normalizedName === 'zakat';

            $destinationType = trim((string) ($cause->destination_type ?? ''));
            if (!in_array($destinationType, ['unrestricted', 'restricted_fund', 'category', 'page'], true)) {
                $destinationType = $isGeneral ? 'unrestricted' : 'restricted_fund';
            }
            $destinationName = $cause->destination_name ?? null;
            if ($destinationType === 'unrestricted') {
                $destinationName = null;
            } elseif ($destinationType === 'restricted_fund' && trim((string) $destinationName) === '') {
                $destinationName = $isZakat ? 'Zakat Fund' : trim((string) $cause->name);
            }

            DB::table('donation_types')->where('id', $cause->id)->update([
                'slug' => $slug,
                'destination_type' => $destinationType,
                'destination_name' => $destinationName,
            ]);
        });

        foreach (self::SUGGESTED_CAUSES as $suggestion) {
            $exists = DB::table('donation_types')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($suggestion['name'])])
                ->exists();
            if ($exists) {
                continue;
            }

            $slug = $suggestion['slug'];
            $suffix = 2;
            while (isset($usedSlugs[$slug]) || DB::table('donation_types')->where('slug', $slug)->exists()) {
                $slug = $suggestion['slug'] . '-' . $suffix++;
            }
            $usedSlugs[$slug] = true;

            $uuid = DB::table('donation_types')->where('uuid', $suggestion['uuid'])->exists()
                ? (string) Str::uuid()
                : $suggestion['uuid'];

            DB::table('donation_types')->insert([
                'uuid' => $uuid,
                'slug' => $slug,
                'purpose_key' => null,
                'destination_type' => 'restricted_fund',
                'destination_name' => $suggestion['destination_name'],
                'name' => $suggestion['name'],
                'description' => $suggestion['description'],
                'status' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!Schema::hasIndex('donation_types', 'donation_types_slug_unique')) {
            Schema::table('donation_types', fn (Blueprint $table) => $table->unique('slug', 'donation_types_slug_unique'));
        }
    }

    public function down(): void
    {
        $zakatControlsInUse = Schema::hasColumn('pages', 'is_zakat_eligible')
            && DB::table('pages')->where('is_zakat_eligible', true)->exists();
        $suggestions = collect(self::SUGGESTED_CAUSES)->keyBy('uuid');
        $causeControlsInUse = Schema::hasColumn('donation_types', 'destination_type')
            && DB::table('donation_types')->get()->contains(function (object $cause) use ($suggestions): bool {
                $suggestion = $suggestions->get((string) $cause->uuid);
                if (!$suggestion) {
                    return true;
                }

                return (string) $cause->name !== $suggestion['name']
                    || (string) ($cause->slug ?? '') !== $suggestion['slug']
                    || trim((string) ($cause->description ?? '')) !== ''
                    || (bool) ($cause->status ?? false)
                    || trim((string) ($cause->purpose_key ?? '')) !== ''
                    || (string) ($cause->destination_type ?? '') !== 'restricted_fund'
                    || (string) ($cause->destination_name ?? '') !== $suggestion['destination_name']
                    || trim((string) ($cause->destination_category_uuid ?? '')) !== ''
                    || trim((string) ($cause->destination_page_uuid ?? '')) !== ''
                    || trim((string) ($cause->image ?? '')) !== ''
                    || trim((string) ($cause->image_media_uuid ?? '')) !== '';
            });
        if ($zakatControlsInUse || $causeControlsInUse) {
            throw new RuntimeException(
                'Rollback refused: guided donation destinations, permanent slugs, managed images, or Zakat eligibility contain live financial configuration.'
            );
        }

        if (Schema::hasIndex('pages', 'pages_zakat_eligible_index')) {
            Schema::table('pages', fn (Blueprint $table) => $table->dropIndex('pages_zakat_eligible_index'));
        }
        if (Schema::hasColumn('pages', 'is_zakat_eligible')) {
            Schema::table('pages', fn (Blueprint $table) => $table->dropColumn('is_zakat_eligible'));
        }

        foreach ([
            'donation_types_slug_unique' => true,
            'donation_types_destination_type_index' => false,
            'donation_types_destination_category_index' => false,
            'donation_types_destination_page_index' => false,
            'donation_types_image_media_index' => false,
        ] as $index => $unique) {
            if (Schema::hasIndex('donation_types', $index)) {
                Schema::table('donation_types', fn (Blueprint $table) => $unique
                    ? $table->dropUnique($index)
                    : $table->dropIndex($index));
            }
        }
        $columns = array_values(array_filter([
            'slug',
            'destination_type',
            'destination_name',
            'destination_category_uuid',
            'destination_page_uuid',
            'image',
            'image_media_uuid',
        ], fn (string $column): bool => Schema::hasColumn('donation_types', $column)));
        if ($columns !== []) {
            Schema::table('donation_types', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
