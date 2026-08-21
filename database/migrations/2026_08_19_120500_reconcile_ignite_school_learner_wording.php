<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INTRO_UUID = '69400000-0000-4000-8000-000000000001';
    private const STATS_UUID = '69400000-0000-4000-8000-000000000002';

    public function up(): void
    {
        if (!Schema::hasTable('page_blocks')) {
            return;
        }

        $this->updateBlock(self::INTRO_UUID, function (array $content): array {
            $intermediateBody = '<p><strong>Ignite School, Bawnia Campus</strong> began in <strong>2016 with 35 children</strong>. Today it supports nearly <strong>120 learners</strong>, including children with additional needs, through free inclusive education, learning materials, uniforms, nutritious meals, healthcare, creative activities, and practical life skills.</p>';
            if (($content['body'] ?? null) === $intermediateBody) {
                $content['body'] = '<p><strong>Ignite School, Bawnia Campus</strong> began in <strong>2016 with 35 children</strong>. Today it supports <strong>nearly 120 learners</strong>, including children with additional needs, through free inclusive education, learning materials, uniforms, nutritious meals, healthcare, creative activities, and practical life skills.</p>';
            }

            return $content;
        });

        $this->updateBlock(self::STATS_UUID, function (array $content): array {
            if (!is_array($content['items'] ?? null)) {
                return $content;
            }

            foreach ($content['items'] as $index => $item) {
                if ($item === ['value' => '120+', 'label' => 'Current learners', 'icon' => 'child']) {
                    $content['items'][$index] = [
                        'value' => 'Nearly 120',
                        'label' => 'Learners supported',
                        'icon' => 'child',
                    ];
                }
            }

            return $content;
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive: a rollback must not replace later edits.
    }

    private function updateBlock(string $uuid, callable $mutate): void
    {
        foreach (DB::table('page_blocks')->where('uuid', $uuid)->get() as $block) {
            $content = $this->decode($block->content);
            $updated = $mutate($content);
            if ($updated === $content) {
                continue;
            }

            DB::table('page_blocks')->where('id', $block->id)->update([
                'content' => $this->encode($updated),
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
};
