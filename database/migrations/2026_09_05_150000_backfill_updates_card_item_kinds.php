<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MARKER = '_migration_updates_item_kind_v1';

    public function up(): void
    {
        foreach (['page_blocks', 'reusable_blocks'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->where('type', 'cards')
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($table): void {
                    foreach ($rows as $row) {
                        $content = $this->decode($row->content ?? null);
                        if (($content['variant'] ?? null) !== 'updates' || !is_array($content['items'] ?? null)) {
                            continue;
                        }

                        $items = array_values($content['items']);
                        $total = count($items);
                        $assigned = [];

                        foreach ($items as $index => &$item) {
                            if (!is_array($item)) {
                                continue;
                            }

                            $kind = strtolower(trim((string) ($item['kind'] ?? '')));
                            if (in_array($kind, ['event', 'news'], true)) {
                                continue;
                            }

                            $kind = $this->legacyKind($item, $index, $total);
                            $item['kind'] = $kind;
                            $assigned[(string) $index] = $kind;
                        }
                        unset($item);

                        if ($assigned === []) {
                            continue;
                        }

                        $content['items'] = $items;
                        $settings = $this->decode($row->settings ?? null);
                        $settings[self::MARKER] = $assigned;

                        DB::table($table)->where('id', $row->id)->update([
                            'content' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                            'settings' => json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        foreach (['page_blocks', 'reusable_blocks'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->where('type', 'cards')
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($table): void {
                    foreach ($rows as $row) {
                        $settings = $this->decode($row->settings ?? null);
                        $assigned = $settings[self::MARKER] ?? null;
                        if (!is_array($assigned)) {
                            continue;
                        }

                        $content = $this->decode($row->content ?? null);
                        $items = is_array($content['items'] ?? null)
                            ? array_values($content['items'])
                            : [];

                        foreach ($assigned as $index => $kind) {
                            $index = (int) $index;
                            if (!isset($items[$index]) || !is_array($items[$index])) {
                                continue;
                            }

                            if (($items[$index]['kind'] ?? null) === $kind) {
                                unset($items[$index]['kind']);
                            }
                        }

                        $content['items'] = $items;
                        unset($settings[self::MARKER]);

                        DB::table($table)->where('id', $row->id)->update([
                            'content' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                            'settings' => json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ]);
                    }
                });
        }
    }

    private function legacyKind(array $item, int $index, int $total): string
    {
        $contentKind = strtolower(trim((string) ($item['content_kind'] ?? '')));
        if ($contentKind === 'event' || filled($item['event_start_at'] ?? null)) {
            return 'event';
        }
        if (in_array($contentKind, ['article', 'news'], true)) {
            return 'news';
        }

        // The former updates template rendered the first and second halves in
        // separate columns. Preserve that original order without interpreting
        // editable or translated headings.
        return $index < (int) ceil(max(1, $total) / 2) ? 'event' : 'news';
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
