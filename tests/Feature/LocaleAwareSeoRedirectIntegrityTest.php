<?php

namespace Tests\Feature;

use App\Models\SeoRedirect;
use App\Models\TranslationLocale;
use App\Services\SeoRedirectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LocaleAwareSeoRedirectIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_language_rule_wins_and_global_rule_remains_a_safe_fallback(): void
    {
        $service = app(SeoRedirectService::class);
        $global = $service->create($this->payload('/former-program', '/programs'));
        $english = $service->create($this->payload('/former-program', '/programs/english', 'en'));
        $bangla = $service->create($this->payload('/former-program', '/programs/bangla?lang=bn', 'bn'));

        $this->assertSame($english->id, $service->resolveActiveForPath('/former-program', 'en')?->id);
        $this->assertSame($bangla->id, $service->resolveActiveForPath('/former-program', 'bn')?->id);
        $this->assertSame($global->id, $service->resolveActiveForPath('/former-program')?->id);
        $this->assertSame(3, SeoRedirect::where('from_path_hash', $global->from_path_hash)->count());
        $this->assertSame(3, SeoRedirect::whereNotNull('source_scope_hash')->distinct()->count('source_scope_hash'));

        TranslationLocale::whereKey('bn')->update(['is_enabled' => true, 'enabled_at' => now()]);
        $this->get('/former-program')->assertRedirect('/programs/english');
        $this->get('/former-program?lang=bn')->assertRedirect('/programs/bangla?lang=bn');
    }

    public function test_scope_uniqueness_validation_and_graph_checks_are_language_aware(): void
    {
        $service = app(SeoRedirectService::class);
        $service->create($this->payload('/old-en', '/current-en', 'en'));
        $service->create($this->payload('/current-en', '/current-bn', 'bn'));

        $this->assertValidationFails(
            fn () => $service->create($this->payload('/old-en', '/duplicate-en', 'en')),
            'from_path'
        );
        $this->assertValidationFails(
            fn () => $service->create($this->payload('/current-en', '/next-en', 'en')),
            'from_path'
        );
        $this->assertValidationFails(
            fn () => $service->create($this->payload('/unsupported-locale', '/destination', 'zz')),
            'locale'
        );

        $bangla = $service->create($this->payload('/old-en', '/duplicate-bn', 'bn'));
        $this->assertSame('bn', $bangla->locale);
        $this->assertNotSame(
            SeoRedirect::where('from_path', '/old-en')->where('locale', 'en')->value('source_scope_hash'),
            $bangla->source_scope_hash
        );
    }

    /** @return array<string, mixed> */
    private function payload(string $from, string $to, ?string $locale = null): array
    {
        return [
            'from_path' => $from,
            'to_url' => $to,
            'status_code' => 301,
            'is_active' => true,
            'locale' => $locale,
        ];
    }

    private function assertValidationFails(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail("Expected validation to fail for {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
