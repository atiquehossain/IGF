<?php

namespace Tests\Feature;

use App\Http\Middleware\Permission;
use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\Role;
use App\Models\SeoMetadataRevision;
use App\Services\SeoMetadataRevisionService;
use App\Services\SeoMetadataEditorVersionService;
use App\Services\SeoMetadataService;
use App\Services\SeoRouteRegistry;
use App\Support\AdminPermissionRegistry;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoCanonicalSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionRegistrySeeder::class);
    }

    public function test_external_canonical_permission_is_additive_specialist_only_and_visible_in_the_editor(): void
    {
        $page = $this->page('canonical-permission');
        $editor = $this->adminWith(['seo.metadata.edit']);
        $specialist = $this->adminWith(['seo.metadata.edit', 'seo.canonical.external']);

        $this->assertDatabaseHas('menu_actions', [
            'id' => 231, 'link' => 'seo.canonical.external', 'status' => 1,
        ]);
        $this->assertSame(
            ['seo.canonical.external'],
            AdminPermissionRegistry::capabilitiesForRoute('seo.canonical.external')
        );
        $this->assertFalse(app(Permission::class)->allows($editor, 'seo.canonical.external'));
        $this->assertTrue(app(Permission::class)->allows($specialist, 'seo.canonical.external'));

        $this->actingAs($editor, 'admin')->get(route('seo.content.edit', ['page', $page->id]))
            ->assertOk()
            ->assertSee('Crediting another website is locked')
            ->assertDontSee('I intentionally want this page to credit another website');
        $this->actingAs($specialist, 'admin')->get(route('seo.content.edit', ['page', $page->id]))
            ->assertOk()
            ->assertSee('I intentionally want this page to credit another website')
            ->assertSee('name="external_canonical_confirm"', false);
    }

    public function test_normal_editor_can_save_same_origin_but_cannot_forge_external_route_or_content_canonicals(): void
    {
        $editor = $this->adminWith(['seo.metadata.edit']);
        $page = $this->page('canonical-content');
        $sameOrigin = url('/contact-us?canonical=preferred');

        $this->actingAs($editor, 'admin')->put(route('seo.update'), $this->routePayload($sameOrigin))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seo_metadata', [
            'route_name' => 'frontend.contactUs', 'locale' => 'en', 'canonical_url' => $sameOrigin,
        ]);

        $forgedRoute = $this->routePayload('https://outside.example/contact-copy') + [
            'external_canonical_confirm' => '1',
        ];
        $this->from(route('seo.index'))->put(route('seo.update'), $forgedRoute)
            ->assertRedirect(route('seo.index'))->assertSessionHasErrors('seo.canonical_url');
        $this->assertDatabaseMissing('seo_metadata', ['canonical_url' => 'https://outside.example/contact-copy']);

        $forgedContent = $this->editorPayload('https://outside.example/page-copy') + [
            'external_canonical_confirm' => '1',
        ];
        $this->from(route('seo.content.edit', ['page', $page->id]))
            ->put(route('seo.content.update', ['page', $page->id]), $forgedContent)
            ->assertSessionHasErrors('seo.canonical_url');
        $this->assertDatabaseMissing('seo_metadata', [
            'seoable_type' => Page::class, 'seoable_id' => $page->id,
            'canonical_url' => 'https://outside.example/page-copy',
        ]);

        // Same host with another scheme is still another origin.
        $schemeSwap = preg_replace('#^http://#', 'https://', url('/canonical-content'));
        $this->put(route('seo.content.update', ['page', $page->id]), $this->editorPayload($schemeSwap) + [
            'external_canonical_confirm' => '1',
        ])->assertSessionHasErrors('seo.canonical_url');
    }

    public function test_specialist_must_confirm_each_external_canonical_save_for_routes_and_content(): void
    {
        $specialist = $this->adminWith(['seo.metadata.edit', 'seo.canonical.external']);
        $page = $this->page('specialist-canonical');
        $routeCanonical = 'https://partner.example/contact-copy';
        $contentCanonical = 'https://partner.example/program-copy';

        $this->actingAs($specialist, 'admin')->from(route('seo.index'))
            ->put(route('seo.update'), $this->routePayload($routeCanonical))
            ->assertSessionHasErrors('external_canonical_confirm');
        $this->assertDatabaseMissing('seo_metadata', ['canonical_url' => $routeCanonical]);

        $this->put(route('seo.update'), $this->routePayload($routeCanonical) + [
            'external_canonical_confirm' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seo_metadata', [
            'route_name' => 'frontend.contactUs', 'canonical_url' => $routeCanonical,
        ]);

        $this->put(route('seo.content.update', ['page', $page->id]), $this->editorPayload($contentCanonical))
            ->assertSessionHasErrors('external_canonical_confirm');
        $this->put(route('seo.content.update', ['page', $page->id]), $this->editorPayload($contentCanonical) + [
            'external_canonical_confirm' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => Page::class, 'seoable_id' => $page->id, 'canonical_url' => $contentCanonical,
        ]);
    }

    public function test_external_canonical_revision_restore_requires_both_specialist_permission_and_confirmation(): void
    {
        $page = $this->page('canonical-restore');
        $service = app(SeoMetadataService::class);
        $external = 'https://archive.partner.example/canonical-copy';
        $safe = url('/page/canonical-restore');
        $metadata = $service->updateForModel($page, [
            'title' => 'External historical version', 'description' => $this->description(),
            'canonical_url' => $external,
        ], 'en');
        $revision = app(SeoMetadataRevisionService::class)->capture($metadata, 'External canonical version');
        $metadata->forceFill(['canonical_url' => $safe])->save();
        $revisionCount = SeoMetadataRevision::where('seo_metadata_id', $metadata->id)->count();

        $restorer = $this->adminWith(['seo.metadata.view', 'seo.metadata.restore']);
        $this->actingAs($restorer, 'admin')->get(route('seo.content.edit', ['page', $page->id]))
            ->assertOk()->assertSee('This version credits another website')->assertSee($external)
            ->assertSee('requires the External canonical specialist permission')
            ->assertDontSee('I confirm this external canonical restoration');
        $this->from(route('seo.content.edit', ['page', $page->id]))
            ->post(route('seo.revisions.restore', $revision), ['external_canonical_confirm' => '1', 'expected_editor_version' => 0])
            ->assertSessionHasErrors('seo.canonical_url');
        $this->assertSame($safe, $metadata->fresh()->canonical_url);
        $this->assertSame($revisionCount, SeoMetadataRevision::where('seo_metadata_id', $metadata->id)->count());

        $specialist = $this->adminWith([
            'seo.metadata.view', 'seo.metadata.restore', 'seo.canonical.external',
        ]);
        $this->actingAs($specialist, 'admin')->get(route('seo.content.edit', ['page', $page->id]))
            ->assertOk()->assertSee('I confirm this external canonical restoration');
        $this->from(route('seo.content.edit', ['page', $page->id]))
            ->post(route('seo.revisions.restore', $revision), ['expected_editor_version' => 0])
            ->assertSessionHasErrors('external_canonical_confirm');
        $this->assertSame($safe, $metadata->fresh()->canonical_url);

        $this->post(route('seo.revisions.restore', $revision), ['external_canonical_confirm' => '1', 'expected_editor_version' => 0])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($external, $metadata->fresh()->canonical_url);
    }

    private function routePayload(string $canonical): array
    {
        $routeName = 'frontend.contactUs';
        $path = (string) app(SeoRouteRegistry::class)->path($routeName);

        return $this->editorPayload($canonical) + [
            'route_name' => $routeName,
            'expected_seo_version' => app(SeoMetadataEditorVersionService::class)
                ->currentForRoute($routeName, $path, 'en'),
        ];
    }

    private function editorPayload(string $canonical): array
    {
        return [
            'locale' => 'en', 'schema_template' => 'none',
            'expected_editor_version' => 0,
            'seo' => [
                'title' => 'Safe canonical test title', 'description' => $this->description(), 'focus_keyword' => '',
                'canonical_url' => $canonical, 'robots_index' => 1, 'robots_follow' => 1,
                'og_title' => '', 'og_description' => '', 'og_image' => '',
                'twitter_card' => 'summary_large_image', 'twitter_title' => '',
                'twitter_description' => '', 'twitter_image' => '', 'schema_markup' => '',
                'sitemap_priority' => 0.5, 'sitemap_change_frequency' => 'monthly',
                'exclude_from_sitemap' => 0,
            ],
        ];
    }

    private function page(string $slug): Page
    {
        return Page::create([
            'uuid' => (string) Str::uuid(), 'name' => Str::headline($slug), 'slug' => $slug,
            'sub_title' => $this->description(), 'language' => 'en', 'status' => 1,
            'publication_status' => 'published', 'visibility' => 'public', 'published_at' => now()->subDay(),
        ]);
    }

    private function description(): string
    {
        return 'Learn how Ignite works alongside communities to deliver practical education, health and livelihood support throughout Bangladesh.';
    }

    private function adminWith(array $actions): Admin
    {
        $menuIds = AuthMenu::whereIn('link', ['seo.index'])->pluck('id')->implode(',');
        $actionIds = MenuAction::whereIn('link', $actions)->pluck('id')->implode(',');
        $role = Role::create([
            'name' => 'Canonical safety ' . Str::random(8), 'permission' => $menuIds,
            'actionPermission' => $actionIds, 'serial' => '[]', 'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Canonical safety QA', 'username' => 'canonical-' . Str::lower(Str::random(10)),
            'email' => Str::lower(Str::random(10)) . '@example.test', 'role' => (string) $role->id,
            'status' => 1, 'password' => bcrypt('test-password'), 'must_change_password' => false,
        ]);
    }
}
