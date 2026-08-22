<?php

namespace Tests\Unit;

use App\Services\SeoContentAnalysisService;
use App\Services\SeoHealthService;
use Tests\TestCase;

class SeoContentAnalysisServiceTest extends TestCase
{
    public function test_well_structured_document_reports_clear_bilingual_safe_signals(): void
    {
        $analysis = app(SeoContentAnalysisService::class)->analyzeDocument([
            'h1' => ['Community learning in Bangladesh'],
            'h2' => ['How community learning helps'],
            'text' => [
                'Community learning helps children build confidence and practical skills. Families and teachers shape each local program together. Results are reviewed with the community.',
            ],
            'paragraphs' => [
                'Community learning helps children build confidence and practical skills.',
                'Families and teachers shape each local program together.',
                'Results are reviewed with the community.',
            ],
            'blocks' => [[
                'heading' => 'Community learning results',
                'image' => '/storage/media/learning.jpg',
                'image_alt' => 'Children learning together',
                'primary_url' => '/page/education',
                'items' => [['url' => 'https://partner.example.org/report']],
            ]],
        ], 'en', 'community learning', 'https://ignite.test/page/learning');

        $this->assertTrue($analysis['available']);
        $this->assertSame(1, $analysis['h1_count']);
        $this->assertSame(1, $analysis['h2_count']);
        $this->assertSame(1, $analysis['images_with_alt']);
        $this->assertSame(1, $analysis['internal_link_count']);
        $this->assertSame(1, $analysis['external_link_count']);
        $this->assertTrue($analysis['focus_in_headings']);
        $this->assertTrue($analysis['focus_in_body']);
        $this->assertSame([], $analysis['issues']);
    }

    public function test_analysis_flags_structure_readability_alt_link_and_focus_actions_without_density_scoring(): void
    {
        $longSentence = implode(' ', array_fill(0, 52, 'community'));
        $analysis = app(SeoContentAnalysisService::class)->analyzeDocument([
            'h1' => ['First main heading', 'Second main heading'],
            'h2' => [],
            'text' => ["{$longSentence}. {$longSentence}. {$longSentence}."],
            'paragraphs' => ["{$longSentence}. {$longSentence}. {$longSentence}."],
            'blocks' => [[
                'image' => '/storage/media/story.jpg',
                'image_alt' => '',
                'items' => [
                    ['url' => 'https://one.example.org'],
                    ['url' => 'https://two.example.org'],
                    ['url' => 'https://three.example.org'],
                ],
            ]],
        ], 'en', 'child education', 'https://ignite.test/page/story');

        $keys = collect($analysis['issues'])->pluck('key');
        $this->assertTrue($keys->contains('content_multiple_h1'));
        $this->assertTrue($keys->contains('content_missing_h2'));
        $this->assertTrue($keys->contains('content_long_sentences'));
        $this->assertTrue($keys->contains('content_long_paragraphs'));
        $this->assertTrue($keys->contains('content_missing_image_alt'));
        $this->assertTrue($keys->contains('content_missing_internal_link'));
        $this->assertTrue($keys->contains('focus_missing_headings'));
        $this->assertTrue($keys->contains('focus_missing_body'));
        $this->assertFalse($keys->contains(fn (string $key): bool => str_contains($key, 'density')));
        $this->assertSame('Review long passages', $analysis['readability']);
    }

    public function test_average_sentence_warning_never_conflicts_with_the_readability_label(): void
    {
        $sentence = implode(' ', array_fill(0, 28, 'community'));
        $analysis = app(SeoContentAnalysisService::class)->analyzeDocument([
            'h1' => ['Community support'],
            'h2' => ['How support works'],
            'text' => ["{$sentence}. {$sentence}. {$sentence}."],
            'paragraphs' => ["{$sentence}.", "{$sentence}.", "{$sentence}."],
        ], 'en');

        $this->assertContains('content_long_sentences', collect($analysis['issues'])->pluck('key')->all());
        $this->assertSame(0, $analysis['long_sentence_count']);
        $this->assertSame('Review long passages', $analysis['readability']);
    }

    public function test_bangla_sentence_guidance_uses_a_language_appropriate_threshold(): void
    {
        $sentence = implode(' ', array_fill(0, 24, 'শিক্ষা'));
        $document = [
            'h1' => ['কমিউনিটি শিক্ষা'],
            'h2' => ['আমাদের কার্যক্রম'],
            'text' => ["{$sentence}। {$sentence}। {$sentence}।"],
            'paragraphs' => ["{$sentence}।", "{$sentence}।", "{$sentence}।"],
        ];

        $bangla = app(SeoContentAnalysisService::class)->analyzeDocument($document, 'bn');
        $englishThreshold = app(SeoContentAnalysisService::class)->analyzeDocument($document, 'en');

        $this->assertContains('content_long_sentences', collect($bangla['issues'])->pluck('key')->all());
        $this->assertNotContains('content_long_sentences', collect($englishThreshold['issues'])->pluck('key')->all());
        $this->assertSame('Bangla', $bangla['locale_label']);
    }

    public function test_content_actions_are_part_of_setup_completeness(): void
    {
        $health = app(SeoHealthService::class)->evaluate([
            'title' => 'Community learning programs',
            'description' => 'Learn how community-led education programs help children, families and teachers build practical skills and stronger local learning systems together.',
            'image' => 'https://ignite.test/share.jpg',
            'indexable' => true,
            'content_analysis' => [
                'issues' => [[
                    'key' => 'content_missing_image_alt',
                    'label' => 'One content image needs useful alternative text.',
                    'tone' => 'danger',
                    'level' => 'required',
                ]],
            ],
        ]);

        $this->assertSame('Needs attention', $health['status']);
        $this->assertLessThan(100, $health['score']);
        $this->assertContains('content_missing_image_alt', collect($health['issues'])->pluck('key')->all());
    }
}
