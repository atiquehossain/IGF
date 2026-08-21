<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BLOCK_UUID = '69400000-0000-4000-8000-000000000002';
    private const MARKER = '_migration_20260819_school_metric';

    public function up(): void
    {
        if (!Schema::hasTable('page_blocks')) {
            return;
        }

        foreach ($this->candidateBlocks()->get() as $block) {
            $content = $this->decode($block->content);
            $settings = $this->decode($block->settings);
            $isSourceBlock = (string) $block->uuid === self::BLOCK_UUID;

            foreach ((array) ($content['items'] ?? []) as $index => $item) {
                if (($item['value'] ?? null) !== '100+'
                    || ($isSourceBlock && ($item['label'] ?? null) !== 'Graduates')) {
                    continue;
                }

                if ($isSourceBlock) {
                    // Keep any editor-owned presentation keys while correcting only
                    // the unsupported value and its English label/icon.
                    $replacement = array_merge($item, [
                        'value' => '35',
                        'label' => 'Children at launch',
                        'icon' => 'child',
                    ]);
                    $content['items'][$index] = $replacement;
                    $settings[self::MARKER] = [
                        'action' => 'replace',
                        'index' => $index,
                        'previous' => $item,
                        'replacement' => $replacement,
                    ];
                } else {
                    // A translated label cannot safely be replaced with English copy.
                    // Remove only the known unsupported numeric claim; editors can add
                    // reviewed localized wording through the normal translation flow.
                    array_splice($content['items'], $index, 1);
                    $settings[self::MARKER] = [
                        'action' => 'remove',
                        'index' => $index,
                        'previous' => $item,
                        'expected_items_after' => array_values($content['items']),
                    ];
                }

                DB::table('page_blocks')->where('id', $block->id)->update([
                    'content' => $this->encode($content),
                    'settings' => $this->encode($settings),
                    'updated_at' => now(),
                ]);
                break;
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('page_blocks')) {
            return;
        }

        foreach ($this->candidateBlocks()->get() as $block) {
            $content = $this->decode($block->content);
            $settings = $this->decode($block->settings);
            $marker = $settings[self::MARKER] ?? null;
            if (!is_array($marker)) {
                continue;
            }

            $index = $marker['index'] ?? null;
            if (is_int($index) && is_array($marker['previous'] ?? null)) {
                if (($marker['action'] ?? 'replace') === 'remove'
                    && array_values((array) ($content['items'] ?? [])) === ($marker['expected_items_after'] ?? null)) {
                    array_splice($content['items'], $index, 0, [$marker['previous']]);
                } elseif (($marker['action'] ?? 'replace') === 'replace'
                    && ($content['items'][$index] ?? null) === ($marker['replacement'] ?? null)) {
                    $content['items'][$index] = $marker['previous'];
                }
            }

            unset($settings[self::MARKER]);
            DB::table('page_blocks')->where('id', $block->id)->update([
                'content' => $this->encode($content),
                'settings' => $this->encode($settings),
                'updated_at' => now(),
            ]);
        }
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

    private function candidateBlocks()
    {
        return DB::table('page_blocks')->where(function ($query): void {
            $query->where('uuid', self::BLOCK_UUID)
                ->orWhere('translation_key', self::BLOCK_UUID);
        });
    }
};
