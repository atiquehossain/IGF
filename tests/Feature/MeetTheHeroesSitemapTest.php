<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Division;
use App\Models\SeoMetadata;
use App\Models\TranslationLocale;
use App\Services\SeoRouteRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetTheHeroesSitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Division::query()->update(['status' => 0]);
        District::query()->update(['status' => 0]);
    }

    public function test_national_page_is_a_settings_backed_seo_admin_target(): void
    {
        $definition = app(SeoRouteRegistry::class)->definition('frontend.heroes.index');

        $this->assertSame('/meet-the-heroes', $definition['path'] ?? null);
        $this->assertSame('Meet the Heroes', $definition['label'] ?? null);
        $this->assertTrue($definition['settings_backed'] ?? false);
    }

    public function test_localized_sitemaps_include_only_active_heroes_geography(): void
    {
        TranslationLocale::query()->whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);

        $dhaka = Division::query()->where('slug', 'dhaka')->firstOrFail();
        $dhaka->update(['status' => 1]);
        $dhakaDistrict = District::query()
            ->where('division_id', $dhaka->id)
            ->where('slug', 'dhaka')
            ->firstOrFail();
        $dhakaDistrict->update(['status' => 1]);

        $inactiveDivision = Division::query()->where('slug', 'chattogram')->firstOrFail();
        $inactiveDistrict = District::query()
            ->where('division_id', $inactiveDivision->id)
            ->firstOrFail();
        // An active child of an inactive parent must never leak into discovery.
        $inactiveDistrict->update(['status' => 1]);

        $english = $this->get('/sitemap-en.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>' . url('/meet-the-heroes') . '</loc>', $english);
        $this->assertStringContainsString(
            '<loc>' . url('/meet-the-heroes/division/dhaka') . '</loc>',
            $english
        );
        $this->assertStringContainsString(
            '<loc>' . url('/meet-the-heroes/district/dhaka') . '</loc>',
            $english
        );
        $this->assertStringNotContainsString('/meet-the-heroes/division/chattogram', $english);
        $this->assertStringNotContainsString(
            '/meet-the-heroes/district/' . $inactiveDistrict->slug,
            $english
        );
        $this->assertStringContainsString(
            'hreflang="bn" href="' . url('/meet-the-heroes/division/dhaka?lang=bn') . '"',
            $english
        );

        $bangla = $this->get('/sitemap-bn.xml')->assertOk()->getContent();
        $this->assertStringContainsString(
            '<loc>' . url('/meet-the-heroes/division/dhaka?lang=bn') . '</loc>',
            $bangla
        );
        $this->assertStringContainsString(
            '<loc>' . url('/meet-the-heroes/district/dhaka?lang=bn') . '</loc>',
            $bangla
        );
    }

    public function test_national_route_seo_exclusion_removes_only_that_locale_and_its_alternate(): void
    {
        TranslationLocale::query()->whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        SeoMetadata::query()->create([
            'route_name' => 'frontend.heroes.index',
            'route_path' => '/meet-the-heroes',
            'locale' => 'bn',
            'robots_index' => false,
            'robots_follow' => true,
            'exclude_from_sitemap' => true,
        ]);

        $english = $this->get('/sitemap-en.xml')->assertOk()->getContent();
        $bangla = $this->get('/sitemap-bn.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>' . url('/meet-the-heroes') . '</loc>', $english);
        $this->assertStringNotContainsString(
            'hreflang="bn" href="' . url('/meet-the-heroes?lang=bn') . '"',
            $english
        );
        $this->assertStringNotContainsString(
            '<loc>' . url('/meet-the-heroes?lang=bn') . '</loc>',
            $bangla
        );
    }
}
