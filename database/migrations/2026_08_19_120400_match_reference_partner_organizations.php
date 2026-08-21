<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BLOCK_UUID = '69000000-0000-4000-8000-000000000005';

    private const MARKER = '_migration_20260819_partner_wall';

    public function up(): void
    {
        if (! Schema::hasTable('page_blocks')) {
            return;
        }

        foreach ($this->candidateBlocks()->get() as $block) {
            $content = $this->decode($block->content);
            $settings = $this->decode($block->settings);
            $changes = [];
            $label = (string) $block->label;

            $this->replaceIfUnchanged($changes, $content, 'eyebrow', 'Working together', '');
            $this->replaceIfUnchanged($changes, $content, 'heading', 'Partners and supporters', 'Partner Organizations');
            $this->replaceIfUnchanged(
                $changes,
                $content,
                'body',
                'Institutions and media organizations that have supported, amplified, or collaborated with Ignite’s work.',
                ''
            );

            $headings = array_map(
                static fn (mixed $item): string => is_array($item) ? (string) ($item['heading'] ?? '') : '',
                array_values((array) ($content['items'] ?? []))
            );
            if ($headings === ['United Nations Volunteers', 'VSO', 'The Daily Star', 'Samakal', 'Banik Barta']) {
                $changes['content.items'] = [
                    'before' => $content['items'],
                    'after' => $this->referenceItems(),
                ];
                $content['items'] = $this->referenceItems();
            }

            if ($label === 'Partners and Supporters') {
                $changes['label'] = ['before' => $label, 'after' => 'Partner Organizations'];
                $label = 'Partner Organizations';
            }

            if ($changes === []) {
                continue;
            }

            $settings[self::MARKER] = $changes;
            DB::table('page_blocks')->where('id', $block->id)->update([
                'label' => $label,
                'content' => $this->encode($content),
                'settings' => $this->encode($settings),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('page_blocks')) {
            return;
        }

        foreach ($this->candidateBlocks()->get() as $block) {
            $content = $this->decode($block->content);
            $settings = $this->decode($block->settings);
            $changes = $settings[self::MARKER] ?? null;
            if (! is_array($changes)) {
                continue;
            }

            $label = (string) $block->label;
            foreach ($changes as $path => $change) {
                if (! is_array($change) || ! array_key_exists('before', $change) || ! array_key_exists('after', $change)) {
                    continue;
                }

                if ($path === 'label') {
                    if ($label === $change['after']) {
                        $label = (string) $change['before'];
                    }
                    continue;
                }

                $key = str_replace('content.', '', (string) $path);
                if (($content[$key] ?? null) === $change['after']) {
                    $content[$key] = $change['before'];
                }
            }

            unset($settings[self::MARKER]);
            DB::table('page_blocks')->where('id', $block->id)->update([
                'label' => $label,
                'content' => $this->encode($content),
                'settings' => $this->encode($settings),
                'updated_at' => now(),
            ]);
        }
    }

    private function replaceIfUnchanged(array &$changes, array &$content, string $key, string $before, string $after): void
    {
        if (($content[$key] ?? null) !== $before) {
            return;
        }

        $changes['content.' . $key] = ['before' => $before, 'after' => $after];
        $content[$key] = $after;
    }

    private function candidateBlocks()
    {
        return DB::table('page_blocks')->where(function ($query): void {
            $query->where('uuid', self::BLOCK_UUID);
            if (Schema::hasColumn('page_blocks', 'translation_key')) {
                $query->orWhere('translation_key', self::BLOCK_UUID);
            }
        });
    }

    private function decode(mixed $value): array
    {
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function encode(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function referenceItems(): array
    {
        $base = '/storage/media/ignite-live/partners/';

        return [
            $this->partner('Bangladesh Brand Forum', $base . '01-bangladesh-brand-forum.png'),
            $this->partner('TechHub Bangladesh', $base . '02-techhub-bangladesh.jpeg'),
            $this->partner('Daraz Bangladesh', $base . '03-daraz.png'),
            $this->partner('ICT Division', $base . '04-ict-division.png', 'ICT Division, Government of Bangladesh'),
            $this->partner('It’s Humanity Foundation', $base . '05-its-humanity-foundation.png'),
            $this->partner('Rtv', $base . '06-rtv.jpg'),
            $this->partner('Prothom Alo', $base . '07-prothom-alo.png'),
            $this->partner('ATN News', $base . '08-atn-news.png'),
            $this->partner('The Daily Star', $base . '09-the-daily-star.png'),
            $this->partner('Incepta Pharmaceuticals', $base . '10-incepta.png'),
            $this->partner('JCI Bangladesh', $base . '11-jci-bangladesh.jpeg'),
            $this->partner('JCI Dhaka West', $base . '12-jci-dhaka-west.png'),
            $this->partner('Matribhumi Group', $base . '13-matribhumi-group.jpeg'),
            $this->partner('PriyoShop', $base . '14-priyoshop.png'),
            $this->partner('Technohaven', $base . '15-technohaven.png', 'Technohaven Company Limited'),
            $this->partner('The Business Standard', $base . '16-the-business-standard.png'),
            $this->partner('Maasranga Television', $base . '17-maasranga-tv.png'),
            $this->partner('Loop', $base . '18-loop.png'),
            $this->partner('Rotary Club of Banani Model Town', $base . '19-rotary-club-banani-model-town.jpeg'),
            $this->partner('What’s On Guide', $base . '20-whatson-guide.svg'),
        ];
    }

    private function partner(string $heading, string $image, ?string $alt = null): array
    {
        return [
            'heading' => $heading,
            'body' => '',
            'image' => $image,
            'image_alt' => $alt ?? $heading,
            'url' => '',
        ];
    }
};
