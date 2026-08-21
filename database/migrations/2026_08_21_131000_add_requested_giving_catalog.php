<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    private const CATALOG = [
        [
            'uuid' => '84ae0875-0656-494a-b3a2-9c9477397465',
            'slug' => 'zakat',
            'name' => 'Donate Your Zakat',
            'legacy_names' => ['Zakat'],
            'description' => 'Direct eligible Zakat to approved programs in line with the foundation’s Zakat policy.',
            'purpose_key' => 'zakat',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Zakat Fund',
            'destination_page_uuid' => null,
        ],
        [
            'uuid' => '6e3c1d7a-8b01-4f01-8a01-000000000001',
            'slug' => 'sadaqah',
            'name' => 'Donate Your Sadaqah',
            'legacy_names' => ['Sadaqah'],
            'description' => 'Give voluntary charity toward approved community needs.',
            'purpose_key' => null,
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Sadaqah Fund',
            'destination_page_uuid' => null,
        ],
        [
            'uuid' => '6e3c1d7a-8b01-4f01-8a01-000000000002',
            'slug' => 'food-support',
            'name' => 'Food Support',
            'legacy_names' => [],
            'description' => 'Provide essential food packages to families facing hardship or emergencies.',
            'purpose_key' => null,
            'destination_type' => 'page',
            'destination_name' => null,
            'destination_page_uuid' => null,
            'destination_page_slug' => 'project-onno',
            'fallback_destination_name' => 'Food Support Fund',
            'legacy_destination_names' => ['Food Support Fund'],
        ],
        [
            'uuid' => '6e3c1d7a-8b01-4f01-8a01-000000000004',
            'slug' => 'orphan-support',
            'name' => 'Orphan Shelter & Support',
            'legacy_names' => ['Orphan Support'],
            'description' => 'Support safe shelter, education, nutrition and wellbeing for children without parental care.',
            'purpose_key' => null,
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Orphan Shelter & Support Fund',
            'destination_page_uuid' => null,
            'legacy_destination_names' => ['Orphan Support Fund'],
        ],
        [
            'uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000001',
            'slug' => 'school-stationery',
            'name' => 'School Stationery',
            'legacy_names' => [],
            'description' => 'Provide notebooks, pens and essential learning materials.',
            'purpose_key' => null,
            'destination_type' => 'page',
            'destination_name' => null,
            'destination_page_uuid' => null,
            'destination_page_slug' => 'education',
            'fallback_destination_name' => 'School Stationery Fund',
            'legacy_destination_names' => ['School Stationery Fund'],
        ],
        [
            'uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000002',
            'slug' => 'school-uniforms',
            'name' => 'School Uniforms',
            'legacy_names' => [],
            'description' => 'Provide a complete uniform so a learner can attend school with confidence.',
            'purpose_key' => null,
            'destination_type' => 'page',
            'destination_name' => null,
            'destination_page_uuid' => null,
            'destination_page_slug' => 'education',
            'fallback_destination_name' => 'School Uniforms Fund',
            'legacy_destination_names' => ['School Uniforms Fund'],
        ],
        [
            'uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000003',
            'slug' => 'school-meals',
            'name' => 'School Meals',
            'legacy_names' => [],
            'description' => 'Provide nutritious school-day meals that help children learn and thrive.',
            'purpose_key' => null,
            'destination_type' => 'page',
            'destination_name' => null,
            'destination_page_uuid' => null,
            'destination_page_slug' => 'education',
            'fallback_destination_name' => 'School Meals Fund',
            'legacy_destination_names' => ['School Meals Fund'],
        ],
        [
            'uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000004',
            'slug' => 'adopt-a-school',
            'name' => 'Adopt a School',
            'legacy_names' => [],
            'description' => 'Strengthen a school with learning materials, essential facilities and classroom support.',
            'purpose_key' => null,
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Adopt a School Fund',
            'destination_page_uuid' => null,
        ],
        [
            'uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000005',
            'slug' => 'ramadan-iftar',
            'name' => 'Ramadan Iftar',
            'legacy_names' => [],
            'description' => 'Provide Iftar meals to families and communities during Ramadan.',
            'purpose_key' => null,
            'destination_type' => 'page',
            'destination_name' => null,
            'destination_page_uuid' => null,
            'destination_page_slug' => 'project-onno',
            'fallback_destination_name' => 'Ramadan Iftar Fund',
            'legacy_destination_names' => ['Ramadan Iftar Fund'],
        ],
        [
            'uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000006',
            'slug' => 'qurbani',
            'name' => 'Qurbani',
            'legacy_names' => [],
            'description' => 'Support Qurbani and meat distribution for eligible families.',
            'purpose_key' => null,
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Qurbani Fund',
            'destination_page_uuid' => null,
        ],
        [
            'uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000007',
            'slug' => 'pure-water-and-sanitation',
            'name' => 'Pure Water & Sanitation',
            'legacy_names' => [],
            'description' => 'Support safe water, sanitation facilities and hygiene education.',
            'purpose_key' => null,
            'destination_type' => 'page',
            'destination_name' => null,
            'destination_page_uuid' => null,
            'destination_page_slug' => 'clean-water',
            'fallback_destination_name' => 'Pure Water & Sanitation Fund',
            'legacy_destination_names' => ['Pure Water & Sanitation Fund'],
        ],
        [
            'uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000008',
            'slug' => 'women-empowerment',
            'name' => 'Women Empowerment',
            'legacy_names' => [],
            'description' => 'Help women build skills, livelihoods and long-term financial resilience.',
            'purpose_key' => null,
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Women Empowerment Fund',
            'destination_page_uuid' => null,
        ],
        [
            'uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000009',
            'slug' => 'youth-development',
            'name' => 'Youth Development',
            'legacy_names' => [],
            'description' => 'Equip young people with education, leadership and employability skills.',
            'purpose_key' => null,
            'destination_type' => 'page',
            'destination_name' => null,
            'destination_page_uuid' => null,
            'destination_page_slug' => 'youth-development',
            'fallback_destination_name' => 'Youth Development Fund',
            'legacy_destination_names' => ['Youth Development Fund'],
        ],
        [
            'uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000010',
            'slug' => 'street-children-education',
            'name' => 'Street Children Education',
            'legacy_names' => [],
            'description' => 'Provide safe, accessible learning and support for street-connected children.',
            'purpose_key' => null,
            'destination_type' => 'page',
            'destination_name' => null,
            'destination_page_uuid' => null,
            'destination_page_slug' => 'education',
            'fallback_destination_name' => 'Street Children Education Fund',
            'legacy_destination_names' => ['Street Children Education Fund'],
        ],
    ];

    public function up(): void
    {
        foreach (self::CATALOG as $item) {
            $this->addOrRefreshCatalogItem($item);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive. These slugs are permanent public
        // identifiers and may be present in payment history or shared links.
    }

    private function addOrRefreshCatalogItem(array $item): void
    {
        $item = $this->resolveDestination($item);
        $cause = $item['purpose_key'] === 'zakat'
            ? DB::table('donation_types')->where('purpose_key', 'zakat')->first()
            : null;
        $cause ??= DB::table('donation_types')->where('slug', $item['slug'])->first()
            ?? DB::table('donation_types')->where('uuid', $item['uuid'])->first();

        if (!$cause) {
            // The Zakat purpose is a protected, organization-approved record.
            // Reuse it when present; do not create a financial purpose merely
            // because a fresh installation has no editorial content yet.
            if ($item['purpose_key'] === 'zakat') {
                return;
            }

            DB::table('donation_types')->insert([
                'uuid' => $item['uuid'],
                'slug' => $item['slug'],
                'purpose_key' => $item['purpose_key'],
                'destination_type' => $item['destination_type'],
                'destination_name' => $item['destination_name'],
                'destination_category_uuid' => null,
                'destination_page_uuid' => $item['destination_page_uuid'],
                'name' => $item['name'],
                'description' => $item['description'],
                'status' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        // A deleted or administrator-renamed cause is an editorial decision.
        // Do not restore it or replace it with a duplicate during a rerun.
        if (($cause->deleted_at ?? null) !== null) {
            return;
        }

        $updates = [];
        if (in_array((string) $cause->name, $item['legacy_names'], true)) {
            $updates['name'] = $item['name'];
        }
        if (trim((string) ($cause->description ?? '')) === '') {
            $updates['description'] = $item['description'];
        }

        $legacyDestinationNames = $item['legacy_destination_names'] ?? [];
        $hasLegacyDestination = $legacyDestinationNames !== []
            && !(bool) ($cause->status ?? false)
            && (string) ($cause->uuid ?? '') === $item['uuid']
            && (string) ($cause->destination_type ?? '') === 'restricted_fund'
            && trim((string) ($cause->destination_page_uuid ?? '')) === ''
            && in_array((string) ($cause->destination_name ?? ''), $legacyDestinationNames, true);
        if ($hasLegacyDestination && $item['destination_type'] !== 'restricted_fund') {
            $updates['destination_type'] = $item['destination_type'];
            $updates['destination_name'] = $item['destination_name'];
            $updates['destination_category_uuid'] = null;
            $updates['destination_page_uuid'] = $item['destination_page_uuid'];
        } elseif ($hasLegacyDestination
            && $item['destination_type'] === 'restricted_fund'
            && $legacyDestinationNames !== []) {
            $updates['destination_name'] = $item['destination_name'];
        }

        if ($updates !== []) {
            $updates['updated_at'] = now();
            DB::table('donation_types')->where('id', $cause->id)->update($updates);
        }
    }

    private function resolveDestination(array $item): array
    {
        $slug = trim((string) ($item['destination_page_slug'] ?? ''));
        if ($item['destination_type'] !== 'page' || $slug === '' || !Schema::hasTable('pages')) {
            return $item;
        }

        $pageUuid = DB::table('pages')
            ->where('slug', $slug)
            ->where('language', config('app.fallback_locale', 'en'))
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->where('visibility', 'public')
            ->where('is_funding_project', 1)
            ->where(function ($query): void {
                $query->where('publication_status', 'published')
                    ->orWhere(function ($scheduled): void {
                        $scheduled->where('publication_status', 'scheduled')
                            ->whereNotNull('scheduled_for')
                            ->where('scheduled_for', '<=', now());
                    });
            })
            ->orderBy('id')
            ->value('uuid');

        if ($pageUuid) {
            $item['destination_page_uuid'] = (string) $pageUuid;

            return $item;
        }

        // A missing, private, draft, completed, or unapproved page must not
        // become a broken financial destination. Keep a named draft fund for
        // an administrator to review and map later.
        $item['destination_type'] = 'restricted_fund';
        $item['destination_name'] = $item['fallback_destination_name'] ?? ($item['name'] . ' Fund');
        $item['destination_page_uuid'] = null;

        return $item;
    }
};
