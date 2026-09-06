<?php

namespace Tests\Unit;

use App\Support\SiteSettingsPayload;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteSettingsPayloadTest extends TestCase
{
    private array $schema = [
        'branding' => [
            'fields' => [
                'name' => ['type' => 'text'],
                'enabled' => ['type' => 'boolean'],
            ],
        ],
        'contact' => [
            'fields' => [
                'faqs' => ['type' => 'faq_list'],
            ],
        ],
    ];

    public function test_it_decodes_only_a_complete_configured_object_shape(): void
    {
        $decoded = (new SiteSettingsPayload())->decode(json_encode([
            'branding' => ['name' => 'Ignite', 'enabled' => '0'],
            'contact' => ['faqs' => [['question' => 'Q', 'answer' => 'A', 'is_active' => '1']]],
        ], JSON_THROW_ON_ERROR), $this->schema);

        $this->assertSame('Ignite', $decoded['branding']['name']);
        $this->assertSame('0', $decoded['branding']['enabled']);
        $this->assertSame('Q', $decoded['contact']['faqs'][0]['question']);
    }

    #[DataProvider('invalidPayloads')]
    public function test_it_rejects_malformed_incomplete_or_unconfigured_payloads(string $payload): void
    {
        try {
            (new SiteSettingsPayload())->decode($payload, $this->schema);
            $this->fail('An unsafe settings payload was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('settings_payload', $exception->errors());
        }
    }

    public static function invalidPayloads(): array
    {
        return [
            'malformed JSON' => ['{"branding":'],
            'array root' => ['[]'],
            'missing group' => ['{"branding":{"name":"Ignite","enabled":"1"}}'],
            'unknown key' => ['{"branding":{"name":"Ignite","enabled":"1","secret":"x"},"contact":{"faqs":[]}}'],
        ];
    }

    public function test_it_rejects_a_payload_over_the_explicit_byte_limit(): void
    {
        try {
            (new SiteSettingsPayload())->decode(str_repeat('x', SiteSettingsPayload::MAX_BYTES + 1), $this->schema);
            $this->fail('An oversized settings payload was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('unusually large', $exception->errors()['settings_payload'][0]);
        }
    }
}
