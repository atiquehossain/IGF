<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PAGE_UUID = '22222222-2222-4222-8222-000000000010';

    private const BLOCK_UUID = '69000000-0000-4000-8000-000000000007';

    public function up(): void
    {
        if (! Schema::hasTable('pages') || ! Schema::hasTable('page_blocks')) {
            return;
        }

        // Query Builder includes soft-deleted rows. A deliberately removed
        // editorial block must not be silently resurrected on a later deploy.
        if (DB::table('page_blocks')->where('uuid', self::BLOCK_UUID)->exists()) {
            return;
        }

        $pageQuery = DB::table('pages')
            ->where('uuid', self::PAGE_UUID)
            ->where('language', 'en');

        if (Schema::hasColumn('pages', 'deleted_at')) {
            $pageQuery->whereNull('deleted_at');
        }

        $pageId = $pageQuery->value('id');
        if (! $pageId) {
            return;
        }

        $content = [
            'variant' => 'about-pillars',
            'eyebrow' => 'What guides us',
            'heading' => 'Purpose that turns care into action',
            'body' => 'Our mission and vision keep every program focused on dignity, opportunity, and accountable service.',
            'items' => [
                [
                    'eyebrow' => 'Our mission',
                    'heading' => 'Turn compassion into practical opportunity',
                    'body' => 'Mobilize volunteers and work alongside communities to expand inclusive education, youth development, women’s empowerment, health, livelihoods, and humanitarian support.',
                    'icon' => 'heart',
                ],
                [
                    'eyebrow' => 'Our vision',
                    'heading' => 'A more equitable and compassionate society',
                    'body' => 'A future where every person can learn, grow, and shape a dignified life, supported by resilient communities and young people prepared to lead.',
                    'icon' => 'map',
                ],
            ],
        ];

        $attributes = [
            'page_id' => $pageId,
            'uuid' => self::BLOCK_UUID,
            'type' => 'cards',
            'label' => 'Mission and Vision',
            'content' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'settings' => json_encode([], JSON_UNESCAPED_SLASHES),
            'sort_order' => 0,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('page_blocks', 'translation_key')) {
            $attributes['translation_key'] = self::BLOCK_UUID;
        }

        DB::table('page_blocks')->insert($attributes);
    }

    public function down(): void
    {
        // Non-destructive by design: the block becomes editor-owned as soon as
        // it is deployed, so rollback must preserve any administrator changes.
    }
};
