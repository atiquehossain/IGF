<?php

namespace Tests\Unit;

use App\Services\ContentSanitizer;
use PHPUnit\Framework\TestCase;

class ContentSanitizerTest extends TestCase
{
    private ContentSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new ContentSanitizer();
    }

    public function test_html_keeps_editorial_markup_and_removes_active_content(): void
    {
        $safe = $this->sanitizer->sanitizeHtml(
            '<section class="story" onclick="steal()"><h2>Hope</h2><script>alert(1)</script>' .
            '<a href="javascript:alert(2)" target="_blank">Read</a><img src="/impact.jpg" onerror="steal()"></section>'
        );

        $this->assertStringContainsString('<section class="story"><h2>Hope</h2>', $safe);
        $this->assertStringContainsString('rel="noopener noreferrer"', $safe);
        $this->assertStringContainsString('src="/impact.jpg"', $safe);
        $this->assertStringNotContainsString('script', $safe);
        $this->assertStringNotContainsString('onclick', $safe);
        $this->assertStringNotContainsString('onerror', $safe);
        $this->assertStringNotContainsString('javascript:', $safe);
    }

    public function test_block_urls_and_rich_text_are_sanitized_recursively(): void
    {
        $safe = $this->sanitizer->sanitizeBlockContent([
            'body' => '<p>Safe <strong>story</strong><iframe src="https://evil.test"></iframe></p>',
            'primary_url' => 'java script:alert(1)',
            'report_url' => 'javascript:alert(2)',
            'view_all_url' => 'javascript:alert(3)',
            'campaign_url' => '/donate?campaign=safe-water',
            'items' => [[
                'heading' => 'Community',
                'url' => 'https://ignite.test/story',
                'image' => 'data:image/svg+xml,<svg onload=alert(1)>',
            ]],
        ]);

        $this->assertSame('<p>Safe <strong>story</strong></p>', $safe['body']);
        $this->assertSame('', $safe['primary_url']);
        $this->assertSame('', $safe['report_url']);
        $this->assertSame('', $safe['view_all_url']);
        $this->assertSame('/donate?campaign=safe-water', $safe['campaign_url']);
        $this->assertSame('https://ignite.test/story', $safe['items'][0]['url']);
        $this->assertSame('', $safe['items'][0]['image']);
    }

    public function test_url_policy_rejects_active_schemes_and_backslash_network_paths(): void
    {
        foreach ([
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            '\\\\attacker.test/share',
            '/%5c%5cattacker.test/share',
        ] as $unsafe) {
            $this->assertSame('', $this->sanitizer->sanitizeUrl($unsafe), $unsafe);
        }

        $this->assertSame('/about-us', $this->sanitizer->sanitizeUrl('/about-us'));
        $this->assertSame('https://example.org/path', $this->sanitizer->sanitizeUrl('https://example.org/path'));
        $this->assertSame('https://example.org/path', $this->sanitizer->sanitizeUrl('//example.org/path'));
    }

    public function test_css_blocks_remote_loading_and_active_legacy_constructs(): void
    {
        $safe = $this->sanitizer->sanitizeCss(
            '@import "https://evil.test/x.css";' .
            '@\\69mport "https://evil.test/escaped.css";' .
            '.hero{background:url(https://evil.test/pixel);width:expression(alert(1));color:#fff}' .
            '.set{background-image:image-set("https://evil.test/one" 1x)}' .
            '.escaped{mask:u\\72l(https://evil.test/two);background:im\\61ge-set("https://evil.test/three" 1x)}'
        );

        $this->assertStringNotContainsString('@import', $safe);
        $this->assertStringNotContainsString('url(', $safe);
        $this->assertStringNotContainsString('image-set', $safe);
        $this->assertStringNotContainsString('expression(', $safe);
        $this->assertStringNotContainsString('evil.test', $safe);
        $this->assertStringContainsString('color:#fff', $safe);
    }

    public function test_css_keeps_safe_editorial_layout_and_animation_rules(): void
    {
        $safe = $this->sanitizer->sanitizeCss(
            ':root{--brand:#f97316}.layout{display:grid;gap:clamp(1rem,2vw,2rem);' .
            'background:linear-gradient(135deg,#fff,#f5f5f5);transform:translateY(0)}' .
            '@media (min-width:40rem){.layout{grid-template-columns:repeat(2,minmax(0,1fr))}}' .
            '@keyframes reveal{from{opacity:0}to{opacity:1}}'
        );

        $this->assertStringContainsString('--brand:#f97316', $safe);
        $this->assertStringContainsString('display:grid', $safe);
        $this->assertStringContainsString('linear-gradient', $safe);
        $this->assertStringContainsString('@media', $safe);
        $this->assertStringContainsString('@keyframes reveal', $safe);
        $this->assertStringContainsString('opacity:1', $safe);
    }
}
