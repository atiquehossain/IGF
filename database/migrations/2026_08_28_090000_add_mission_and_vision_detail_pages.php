<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ABOUT_PAGE_UUID = '22222222-2222-4222-8222-000000000010';

    private const ABOUT_BLOCK_UUID = '69000000-0000-4000-8000-000000000007';

    /**
     * @var array<string, array<string, mixed>>
     */
    private const DETAIL_PAGES = [
        'our-mission' => [
            'uuid' => '6a000000-0000-4000-8000-000000000001',
            'name' => 'Our mission',
            'sub_title' => 'Turning compassion into practical opportunity alongside communities.',
            'description' => '<h2>Turn compassion into practical opportunity</h2><p>Ignite Global Foundation mobilizes volunteers and works alongside communities to expand inclusive education, youth development, women’s empowerment, healthcare, livelihoods, safe water, food security, and humanitarian support.</p><h2>How we work</h2><p>We listen first, respect community leadership, and connect responsible support with practical action. Programs are designed around dignity, accountability, and outcomes that communities can sustain.</p>',
            'order_by' => 110,
        ],
        'our-vision' => [
            'uuid' => '6a000000-0000-4000-8000-000000000002',
            'name' => 'Our vision',
            'sub_title' => 'A more equitable and compassionate society where every person can thrive.',
            'description' => '<h2>A more equitable and compassionate society</h2><p>Ignite envisions a future where every person can learn, grow, and shape a dignified life.</p><h2>What that future looks like</h2><p>Children can access inclusive education, families can strengthen their health and livelihoods, and young people can lead practical change. Resilient communities have the confidence, opportunity, and support to shape their own futures.</p>',
            'order_by' => 111,
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('pages') || ! Schema::hasTable('page_blocks')) {
            return;
        }

        $aboutPage = DB::table('pages')
            ->where('uuid', self::ABOUT_PAGE_UUID)
            ->where('language', 'en')
            ->when(Schema::hasColumn('pages', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->first();

        if (! $aboutPage) {
            return;
        }

        $availableDestinations = [];
        foreach (self::DETAIL_PAGES as $slug => $attributes) {
            $availableDestinations[$slug] = $this->ensureDetailPage($slug, $attributes, $aboutPage?->category_id);
        }

        $blockQuery = DB::table('page_blocks')->where('uuid', self::ABOUT_BLOCK_UUID);
        if (Schema::hasColumn('page_blocks', 'deleted_at')) {
            $blockQuery->whereNull('deleted_at');
        }

        $block = $blockQuery->first();
        if (! $block) {
            return;
        }

        $content = is_string($block->content)
            ? json_decode($block->content, true)
            : (array) $block->content;

        if (! is_array($content) || ! isset($content['items']) || ! is_array($content['items'])) {
            return;
        }

        $changed = false;
        foreach ($content['items'] as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $eyebrow = strtolower(trim((string) ($item['eyebrow'] ?? '')));
            $slug = match ($eyebrow) {
                'our mission' => 'our-mission',
                'our vision' => 'our-vision',
                default => null,
            };

            if (! $slug || ! ($availableDestinations[$slug] ?? false)) {
                continue;
            }

            // Once these keys exist they are editor-owned, including a blank
            // value chosen deliberately to remove the public action.
            if (! array_key_exists('url', $item)) {
                $content['items'][$index]['url'] = '/page/' . $slug;
                $changed = true;
            }
            if (! array_key_exists('link_label', $item)) {
                $content['items'][$index]['link_label'] = 'Read more';
                $changed = true;
            }
        }

        if ($changed) {
            DB::table('page_blocks')->where('id', $block->id)->update([
                'content' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Create a missing detail page without restoring or overwriting an
     * administrator-owned page. Returns true only when its route is public.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function ensureDetailPage(string $slug, array $attributes, mixed $categoryId): bool
    {
        $existing = DB::table('pages')
            ->where('language', 'en')
            ->where(function ($query) use ($slug, $attributes): void {
                $query->where('slug', $slug)->orWhere('uuid', $attributes['uuid']);
            })
            ->first();

        if (! $existing) {
            $timestamp = now();
            DB::table('pages')->insert([
                'uuid' => $attributes['uuid'],
                'category_id' => $categoryId,
                'name' => $attributes['name'],
                'sub_title' => $attributes['sub_title'],
                'slug' => $slug,
                'description' => $attributes['description'],
                'status' => 1,
                'publication_status' => 'published',
                'visibility' => 'public',
                'name_enabled' => 1,
                'sub_title_enabled' => 1,
                'is_comment' => 0,
                'is_relationship' => 0,
                'meta_title' => $attributes['name'] . ' | Ignite Global Foundation',
                'meta_keyword' => $attributes['name'] . ', Ignite Global Foundation',
                'meta_description' => $attributes['sub_title'],
                'order_by' => $attributes['order_by'],
                'published_at' => $timestamp,
                'last_published_at' => $timestamp,
                'language' => 'en',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            return true;
        }

        if (Schema::hasColumn('pages', 'deleted_at') && $existing->deleted_at !== null) {
            return false;
        }

        return (bool) $existing->status
            && ($existing->publication_status ?? 'published') === 'published'
            && ($existing->visibility ?? 'public') !== 'private'
            && $existing->slug === $slug;
    }

    public function down(): void
    {
        // Non-destructive by design. These pages and links become editor-owned
        // immediately, so rollback must preserve administrator changes.
    }
};
