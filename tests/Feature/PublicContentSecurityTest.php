<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Helper\MyLogs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicContentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_rich_text_is_sanitized_for_web_and_api_consumers(): void
    {
        $category = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Safe programs',
            'slug' => 'safe-programs',
            'language' => 'en',
            'status' => 1,
            'description' => '<p><strong>Useful copy</strong><img src="x" onerror="alert(1)"></p><script>alert(2)</script>',
            'inline_css' => '@import url(https://attacker.test/x.css);.x{background:url(javascript:alert(3))}',
        ]);
        Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Related page',
            'sub_title' => 'Published story',
            'slug' => 'related-page',
            'category_id' => $category->id,
            'language' => 'en',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->subMinute(),
            'description' => '<p onmouseover="alert(4)">Related <b>content</b></p>',
        ]);

        $this->get('/category/safe-programs')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('data.category.description', fn ($html) => str_contains($html, '<strong>Useful copy</strong>')
                && !str_contains($html, 'onerror')
                && !str_contains($html, '<script'))
            ->where('data.category.inline_css', fn ($css) => !str_contains(strtolower($css), '@import')
                && !str_contains(strtolower($css), 'javascript'))
        );

        $json = $this->getJson('/api/v1/category/safe-programs')->assertOk()->json();
        $encoded = json_encode($json, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Useful copy', $encoded);
        $this->assertStringNotContainsString('onerror', $encoded);
        $this->assertStringNotContainsString('onmouseover', $encoded);
        $this->assertStringNotContainsString('<script', $encoded);

        $this->getJson('/api/v1/category/missing')->assertNotFound();
        $this->getJson('/api/v1/page/missing')->assertNotFound();
    }

    public function test_home_api_sanitizes_every_nested_category_and_page_payload(): void
    {
        $category = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Our success story',
            'slug' => 'our-success-story',
            'language' => 'en',
            'status' => 1,
            'description' => '<p>Safe summary<img src="x" onerror="steal()"></p><script>steal()</script>',
        ]);
        Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Nested story',
            'sub_title' => 'Published impact story',
            'slug' => 'nested-story',
            'category_id' => $category->id,
            'language' => 'en',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->subMinute(),
            'description' => '<p onclick="steal()">Nested copy</p>',
        ]);

        $encoded = json_encode($this->getJson('/api/v1/')->assertOk()->json(), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Safe summary', $encoded);
        $this->assertStringContainsString('Nested copy', $encoded);
        $this->assertStringNotContainsString('onerror', $encoded);
        $this->assertStringNotContainsString('onclick', $encoded);
        $this->assertStringNotContainsString('<script', $encoded);
    }

    public function test_write_audit_log_omits_request_body_and_network_identifiers(): void
    {
        $request = Request::create('/api/v1/profile', 'POST', [
            'email' => 'private@example.test',
            'password' => 'Do-Not-Log-This!',
            'address' => 'Private address',
        ], server: [
            'REMOTE_ADDR' => '203.0.113.8',
            'HTTP_USER_AGENT' => 'Sensitive browser fingerprint',
        ]);

        Log::shouldReceive('info')->once()->withArgs(function (string $message, array $context): bool {
            $encoded = json_encode($context, JSON_THROW_ON_ERROR);

            return $message === 'Application write action.'
                && array_keys($context) === ['channel', 'action', 'method', 'route', 'user_id']
                && !str_contains($encoded, 'private@example.test')
                && !str_contains($encoded, 'Do-Not-Log-This')
                && !str_contains($encoded, '203.0.113.8')
                && !str_contains($encoded, 'fingerprint');
        });

        MyLogs::front($request, 'profile update');
    }
}
