<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION_IDS = [47, 48];

    public function up(): void
    {
        $now = now();
        DB::table('auth_menus')->upsert([
            [
                'id' => 47,
                'parent_id' => null,
                'name' => 'Donations',
                'link' => 'donations.index',
                'icon' => 'fa-heart-o',
                'order_by' => 50,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 48,
                'parent_id' => null,
                'name' => 'Donors',
                'link' => 'user.index',
                'icon' => 'fa-users',
                'order_by' => 51,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['id'], ['name', 'link', 'icon', 'order_by', 'status', 'updated_at']);

        DB::table('roles')->where('name', 'Super Admin')->get()->each(function ($role): void {
            DB::table('roles')->where('id', $role->id)->update([
                'permission' => $this->appendIds($role->permission, self::PERMISSION_IDS),
                'updated_at' => now(),
            ]);
        });

        $legalCopy = [
            'privacy-policy' => '<h2>Information we collect</h2><p>Ignite Global Foundation collects the information you choose to provide when you contact us, donate, register to volunteer, subscribe for updates, or request sponsorship information.</p><h2>How information is used</h2><p>We use this information to respond to you, administer the service you requested, maintain required financial and safeguarding records, and improve our programs. Card details are handled by the payment provider and are not stored on this website.</p><h2>Your choices</h2><p>You may ask to access, correct, or delete eligible personal information by contacting info@ignite.org.bd. We restrict access to authorized personnel and retain information only for operational, legal, and safeguarding needs.</p>',
            'terms-conditions' => '<h2>Using this website</h2><p>You may use this website for lawful, personal purposes. Do not attempt to disrupt the service, access another person\'s account, upload harmful material, or misuse Ignite Global Foundation content.</p><h2>Information and donations</h2><p>Program information is provided for general awareness and may change as community needs evolve. Donations are processed by an independent payment provider. Contact info@ignite.org.bd promptly if you believe a transaction was made in error.</p><h2>Content</h2><p>Unless otherwise stated, website text, photographs, and visual materials belong to Ignite Global Foundation or are used with permission. Please request written permission before reproducing them.</p>',
            'safeguarding' => '<h2>Our commitment</h2><p>Ignite Global Foundation is committed to protecting children, adults at risk, community members, volunteers, and staff from abuse, exploitation, harassment, and neglect.</p><h2>How we work</h2><p>People representing Ignite are expected to follow safeguarding standards, treat every person with dignity, minimize risk, and report concerns promptly. Concerns are handled confidentially and shared only with people responsible for responding safely.</p><h2>Report a concern</h2><p>To raise a safeguarding concern, contact info@ignite.org.bd or +880 1972016221. If someone is in immediate danger, contact the appropriate local emergency or protection service first. Retaliation against a person who reports a concern in good faith is not accepted.</p>',
        ];
        foreach ($legalCopy as $slug => $description) {
            DB::table('pages')
                ->where('slug', $slug)
                ->where('language', 'en')
                ->where('description', 'like', '%Replace this local demonstration text before production release.%')
                ->update(['description' => $description, 'updated_at' => $now]);
        }

        foreach ([
            'education' => 'Ignite works with families, teachers, and local leaders to improve access to inclusive learning, practical materials, and safer learning environments.',
            'healthcare' => 'Community health initiatives bring practical information, preventive support, and connections to essential care closer to underserved families.',
            'clean-water' => 'Local partners help plan, maintain, and monitor safe-water and sanitation facilities so that improvements remain useful over time.',
            'livelihoods' => 'Skills development, market connections, and small-enterprise support help people build more reliable and resilient sources of income.',
        ] as $slug => $copy) {
            DB::table('pages')
                ->where('slug', $slug)
                ->where('language', 'en')
                ->where('description', '<p>This local demonstration page is ready to be customized in the page builder.</p>')
                ->update(['description' => '<p>' . $copy . '</p>', 'updated_at' => $now]);
        }

        if (Schema::hasTable('page_blocks')) {
            DB::table('page_blocks')
                ->whereIn('uuid', [
                    '44444444-4444-4444-8444-000000000006',
                    '44444444-4444-4444-8444-000000000007',
                ])
                ->get()
                ->each(function ($block) use ($now): void {
                    $content = json_decode((string) $block->content, true);
                    if (!is_array($content)) {
                        return;
                    }

                    if ($block->uuid === '44444444-4444-4444-8444-000000000006') {
                        foreach ($content['items'] ?? [] as &$item) {
                            if (($item['url'] ?? null) === '/page/community-health-outreach') {
                                $item['url'] = '/category/our-causes';
                            }
                        }
                        unset($item);
                    }
                    if ($block->uuid === '44444444-4444-4444-8444-000000000007'
                        && str_contains((string) ($content['body'] ?? ''), 'ready for a client-approved')) {
                        $content['body'] = '<p>Across Bangladesh, local leaders are combining practical knowledge, shared responsibility, and community networks to improve access to education, health, and reliable livelihoods.</p>';
                    }

                    DB::table('page_blocks')->where('id', $block->id)->update([
                        'content' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'updated_at' => $now,
                    ]);
                });
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'Super Admin')->get()->each(function ($role): void {
            DB::table('roles')->where('id', $role->id)->update([
                'permission' => $this->removeIds($role->permission, self::PERMISSION_IDS),
                'updated_at' => now(),
            ]);
        });
        DB::table('auth_menus')->whereIn('id', self::PERMISSION_IDS)->delete();
    }

    private function appendIds(?string $value, array $ids): string
    {
        return collect(explode(',', (string) $value))
            ->merge($ids)
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->implode(',');
    }

    private function removeIds(?string $value, array $ids): string
    {
        $remove = array_map('strval', $ids);

        return collect(explode(',', (string) $value))
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '' && !in_array($id, $remove, true))
            ->implode(',');
    }
};
