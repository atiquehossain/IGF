<?php

namespace Tests\Feature;

use App\Http\Middleware\Permission;
use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\Role;
use App\Models\SeoMetadata;
use App\Models\SeoMetadataRevision;
use App\Models\SeoRedirect;
use App\Services\SeoMetadataRevisionService;
use App\Services\SeoMetadataEditorVersionService;
use App\Services\SeoMetadataService;
use App\Services\SeoRedirectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SeoSecurityFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_untrusted_host_is_rejected_before_it_can_poison_public_seo_urls(): void
    {
        $this->get('http://attacker.example/robots.txt')
            ->assertStatus(400);
        $this->get('http://attacker.localhost/robots.txt')
            ->assertStatus(400);
        $this->get('http://localhost:6553/robots.txt')
            ->assertStatus(400);

        $this->get(rtrim((string) config('app.url'), '/') . '/robots.txt')
            ->assertOk()
            ->assertDontSee('attacker.example', false);
    }

    public function test_page_editors_cannot_administer_seo_and_metadata_and_redirect_permissions_are_split(): void
    {
        $pageMenu = AuthMenu::create(['name' => 'Pages', 'link' => 'page.index', 'status' => 1]);
        $pageEdit = MenuAction::create([
            'auth_menu_id' => $pageMenu->id,
            'name' => 'Edit pages',
            'link' => 'page.edit',
            'status' => 1,
        ]);
        $metadata = MenuAction::where('link', 'seo.metadata.edit')->firstOrFail();
        $redirectCreate = MenuAction::where('link', 'seo.redirects.create')->firstOrFail();
        $redirectDestroy = MenuAction::where('link', 'seo.redirects.destroy')->firstOrFail();

        [$pageEditor, $pageRole] = $this->makeAdmin('Page editor', (string) $pageEdit->id);
        $permission = app(Permission::class);
        $this->assertFalse($permission->allows($pageEditor, 'seo.index'));
        $this->assertFalse($permission->allows($pageEditor, 'seo.redirects.store'));

        $pageRole->update(['actionPermission' => (string) $metadata->id]);
        $this->assertTrue($permission->allows($pageEditor, 'seo.index'));
        $this->assertTrue($permission->allows($pageEditor, 'seo.content.update'));
        $this->assertFalse($permission->allows($pageEditor, 'seo.redirects.store'));

        $pageRole->update(['actionPermission' => $redirectCreate->id . ',' . $redirectDestroy->id]);
        $this->assertFalse($permission->allows($pageEditor, 'seo.index'));
        $this->assertTrue($permission->allows($pageEditor, 'seo.redirects.index'));
        $this->assertTrue($permission->allows($pageEditor, 'seo.redirects.store'));
        $this->assertTrue($permission->allows($pageEditor, 'seo.redirects.destroy'));
    }

    public function test_redirect_policy_rejects_external_critical_self_and_unsafe_status_rules(): void
    {
        $service = app(SeoRedirectService::class);

        $this->assertValidationFails(fn () => $service->create([
            'from_path' => '/old',
            'to_url' => 'https://attacker.example/phish',
            'status_code' => 301,
            'is_active' => true,
        ]), 'to_url');

        $this->assertValidationFails(fn () => $service->create([
            'from_path' => '/login',
            'to_url' => '/welcome',
            'status_code' => 301,
            'is_active' => true,
        ]), 'from_path');

        foreach (['/sitemap-index.xml', '/sitemap-bn.xml'] as $protectedSitemap) {
            $this->assertValidationFails(fn () => $service->create([
                'from_path' => $protectedSitemap,
                'to_url' => '/welcome',
                'status_code' => 301,
                'is_active' => true,
            ]), 'from_path');
        }

        $this->assertValidationFails(fn () => $service->create([
            'from_path' => '/old-api-link',
            'to_url' => '/api/private',
            'status_code' => 301,
            'is_active' => true,
        ]), 'to_url');

        foreach (['/robots.txt', '/sitemap.xml', '/sitemap-index.xml', '/admin'] as $protectedTarget) {
            $this->assertValidationFails(fn () => $service->create([
                'from_path' => '/old-' . md5($protectedTarget),
                'to_url' => $protectedTarget,
                'status_code' => 301,
                'is_active' => true,
            ]), 'to_url');
        }

        $this->assertValidationFails(fn () => $service->create([
            'from_path' => '/same',
            'to_url' => '/same?tracking=1',
            'status_code' => 301,
            'is_active' => true,
        ]), 'to_url');

        $this->assertValidationFails(fn () => $service->create([
            'from_path' => '/temporary',
            'to_url' => '/destination',
            'status_code' => 305,
            'is_active' => true,
        ]), 'status_code');

        $this->assertValidationFails(fn () => $service->create([
            'from_path' => '/coerced-status',
            'to_url' => '/destination',
            'status_code' => '301-not-really',
            'is_active' => true,
        ]), 'status_code');

        $this->assertValidationFails(fn () => $service->create([
            'from_path' => '/coerced-active',
            'to_url' => '/destination',
            'status_code' => 301,
            'is_active' => 'false',
        ]), 'is_active');

        $this->assertValidationFails(fn () => $service->create([
            'from_path' => '/oversized-target',
            'to_url' => '/' . str_repeat('x', 2048),
            'status_code' => 301,
            'is_active' => true,
        ]), 'to_url');

        config()->set('seo.redirects.allow_external', true);
        config()->set('seo.redirects.allowed_external_hosts', ['partner.example']);
        $external = $service->create([
            'from_path' => '/approved-partner',
            'to_url' => 'https://partner.example/destination',
            'status_code' => 302,
            'is_active' => true,
        ]);
        $this->assertSame('https://partner.example/destination', $external->to_url);
        $this->assertValidationFails(fn () => $service->create([
            'from_path' => '/approved-host-unsafe-port',
            'to_url' => 'https://partner.example:8443/destination',
            'status_code' => 302,
            'is_active' => true,
        ]), 'to_url');

        $sameOrigin = $service->create([
            'from_path' => '/same-origin',
            'to_url' => config('app.url') . '/page/destination?source=old',
            'status_code' => 308,
            'is_active' => true,
        ]);
        $this->assertSame('/page/destination?source=old', $sameOrigin->to_url);
    }

    public function test_redirect_graph_rejects_chains_cycles_and_normalized_duplicates(): void
    {
        $service = app(SeoRedirectService::class);
        $this->assertDatabaseHas('seo_redirect_locks', ['id' => 1]);
        $service->create([
            'from_path' => '/old-a/',
            'to_url' => '/new-b',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->assertValidationFails(fn () => $service->create([
            'from_path' => '/new-b',
            'to_url' => '/new-c',
            'status_code' => 301,
            'is_active' => true,
        ]), 'from_path');

        $this->assertValidationFails(fn () => $service->create([
            'from_path' => '/new-c',
            'to_url' => '/old-a',
            'status_code' => 301,
            'is_active' => true,
        ]), 'to_url');

        $this->assertValidationFails(fn () => $service->create([
            'from_path' => '/old-a',
            'to_url' => '/elsewhere',
            'status_code' => 302,
            'is_active' => false,
        ]), 'from_path');

        $inactive = $service->create([
            'from_path' => '/new-b',
            'to_url' => '/new-c',
            'status_code' => 302,
            'is_active' => false,
        ]);
        $this->assertValidationFails(fn () => $service->setActive($inactive, true), 'from_path');
    }

    public function test_redirect_lifecycle_is_audited_and_restore_is_inactive_until_reenabled(): void
    {
        [$admin] = $this->makeAdmin('SEO owner', '');
        $service = app(SeoRedirectService::class);
        $redirect = $service->create([
            'from_path' => '/legacy-story',
            'to_url' => '/page/current-story',
            'status_code' => 301,
            'is_active' => true,
        ], $admin->id);

        $this->assertSame($admin->id, $redirect->created_by);
        $this->assertSame('/legacy-story', $redirect->normalized_from_path);
        $this->assertSame(hash('sha256', '/legacy-story'), $redirect->from_path_hash);

        $redirect = $service->setActive($redirect, false, $admin->id);
        $this->assertFalse($redirect->is_active);

        $redirect = $service->update($redirect, [
            'to_url' => '/page/revised-story',
            'status_code' => 308,
        ], $admin->id);
        $this->assertSame('/page/revised-story', $redirect->to_url);
        $this->assertSame(308, $redirect->status_code);

        $service->delete($redirect, $admin->id);
        $deleted = SeoRedirect::withTrashed()->findOrFail($redirect->id);
        $this->assertTrue($deleted->trashed());
        $this->assertFalse($deleted->is_active);
        $this->assertSame($admin->id, $deleted->deleted_by);

        $restored = $service->restore($deleted, $admin->id);
        $this->assertFalse($restored->trashed());
        $this->assertFalse($restored->is_active);
        $this->assertSame($admin->id, $restored->restored_by);
        $this->assertNotNull($restored->restored_at);

        $reenabled = $service->setActive($restored, true, $admin->id);
        $this->assertTrue($reenabled->is_active);
    }

    public function test_redirect_mass_assignment_cannot_forge_hits_or_audit_identity(): void
    {
        [$admin] = $this->makeAdmin('Forged owner', '');
        $redirect = SeoRedirect::create([
            'from_path' => '/legacy-mass-assignment',
            'to_url' => '/page/current',
            'status_code' => 301,
            'is_active' => true,
            'hits' => 999,
            'last_hit_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'deleted_by' => $admin->id,
            'restored_by' => $admin->id,
            'restored_at' => now(),
        ]);
        $redirect->refresh();

        $this->assertSame(0, $redirect->hits);
        $this->assertNull($redirect->last_hit_at);
        $this->assertNull($redirect->created_by);
        $this->assertNull($redirect->updated_by);
        $this->assertNull($redirect->deleted_by);
        $this->assertNull($redirect->restored_by);
        $this->assertNull($redirect->restored_at);
    }

    public function test_invalid_historical_redirect_fails_open_to_the_real_route(): void
    {
        $source = '/unsafe-history';
        DB::table('seo_redirects')->insert([
            'from_path' => $source,
            'normalized_from_path' => $source,
            'from_path_hash' => hash('sha256', $source),
            'to_url' => 'https://attacker.example/phish',
            'status_code' => 301,
            'is_active' => true,
            'hits' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get($source)
            ->assertNotFound()
            ->assertHeaderMissing('Location');
    }

    public function test_metadata_service_ignores_nested_ownership_locale_and_audit_injection(): void
    {
        [$admin] = $this->makeAdmin('Metadata owner', '');
        $this->actingAs($admin, 'admin');
        $page = $this->makePage('owned-page');
        $other = $this->makePage('other-page');
        $service = app(SeoMetadataService::class);

        $metadata = $service->updateForModel($page, [
            'title' => 'Safe title',
            'robots_index' => true,
            'robots_follow' => true,
            'twitter_card' => 'summary',
            'sitemap_priority' => 0.5,
            'sitemap_change_frequency' => 'monthly',
            'exclude_from_sitemap' => false,
            'seoable_type' => Role::class,
            'seoable_id' => $other->id,
            'route_name' => 'admin.login',
            'route_path' => '/admin/login',
            'locale' => 'bn',
            'created_by' => 999999,
            'updated_by' => 999999,
        ], 'en');

        $this->assertSame(Page::class, $metadata->seoable_type);
        $this->assertSame($page->id, $metadata->seoable_id);
        $this->assertNull($metadata->route_name);
        $this->assertNull($metadata->route_path);
        $this->assertSame('en', $metadata->locale);
        $this->assertSame($admin->id, $metadata->created_by);
        $this->assertSame($admin->id, $metadata->updated_by);

        $routeMetadata = $service->updateForRoute('frontend.contactUs', '/contact-us', 'en', [
            'title' => 'Contact safely',
            'robots_index' => true,
            'robots_follow' => true,
            'twitter_card' => 'summary',
            'sitemap_priority' => 0.5,
            'sitemap_change_frequency' => 'monthly',
            'exclude_from_sitemap' => false,
            'seoable_type' => Page::class,
            'seoable_id' => $page->id,
            'route_name' => 'admin.login',
            'route_path' => '/admin/login',
            'locale' => 'bn',
        ]);
        $this->assertNull($routeMetadata->seoable_type);
        $this->assertNull($routeMetadata->seoable_id);
        $this->assertSame('frontend.contactUs', $routeMetadata->route_name);
        $this->assertSame('/contact-us', $routeMetadata->route_path);
        $this->assertSame('en', $routeMetadata->locale);
    }

    public function test_revision_restore_is_undoable_and_cannot_restore_identity_or_audit_fields(): void
    {
        [$admin] = $this->makeAdmin('Revision owner', '');
        $this->actingAs($admin, 'admin');
        $metadataService = app(SeoMetadataService::class);
        $revisions = app(SeoMetadataRevisionService::class);
        $metadata = $metadataService->updateForRoute('frontend.contactUs', '/contact-us', 'en', [
            'title' => 'Original safe title',
        ]);
        $revision = $revisions->capture($metadata, 'Original version');
        $metadataService->updateForRoute('frontend.contactUs', '/contact-us', 'en', [
            'title' => 'Current title before restore',
        ]);
        $metadata->forceFill([
            'review_status' => 'approved',
            'review_note' => 'Approved for this exact payload.',
            'review_content_hash' => hash('sha256', 'current-approved-payload'),
            'review_requested_by' => $admin->id,
            'review_requested_at' => now()->subMinute(),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        // Simulate a legacy snapshot containing fields that editors never own.
        $revision->forceFill(['snapshot' => array_merge($revision->snapshot, [
            'seoable_type' => Role::class,
            'seoable_id' => 999999,
            'route_name' => 'admin.login',
            'route_path' => '/admin/login',
            'locale' => 'bn',
            'created_by' => 999999,
            'updated_by' => 999999,
        ])])->save();
        $immutableSnapshot = $revision->fresh()->snapshot;
        $historyBefore = SeoMetadataRevision::count();

        $restored = $revisions->restore($revision);

        $this->assertSame('Original safe title', $restored->title);
        $this->assertNull($restored->seoable_type);
        $this->assertNull($restored->seoable_id);
        $this->assertSame('frontend.contactUs', $restored->route_name);
        $this->assertSame('/contact-us', $restored->route_path);
        $this->assertSame('en', $restored->locale);
        $this->assertSame($admin->id, $restored->created_by);
        $this->assertSame($admin->id, $restored->updated_by);
        $this->assertSame('draft', $restored->review_status);
        $this->assertNull($restored->review_note);
        $this->assertNull($restored->review_content_hash);
        $this->assertNull($restored->review_requested_by);
        $this->assertNull($restored->review_requested_at);
        $this->assertNull($restored->reviewed_by);
        $this->assertNull($restored->reviewed_at);
        $this->assertSame($immutableSnapshot, $revision->fresh()->snapshot);
        $this->assertSame($historyBefore + 1, SeoMetadataRevision::count());
        $undo = SeoMetadataRevision::latest('id')->firstOrFail();
        $this->assertSame('Before restoring an earlier SEO version', $undo->reason);
        $this->assertSame('Current title before restore', $undo->snapshot['title']);
    }

    public function test_revision_restore_requires_live_matching_identity_but_can_restore_soft_deleted_metadata(): void
    {
        [$admin] = $this->makeAdmin('Restore owner', '');
        $this->actingAs($admin, 'admin');
        $metadataService = app(SeoMetadataService::class);
        $revisions = app(SeoMetadataRevisionService::class);

        $softDeleted = $metadataService->updateForRoute('frontend.about', '/about-us', 'en', [
            'title' => 'Restorable version',
        ]);
        $softRevision = $revisions->capture($softDeleted);
        $softDeleted->delete();
        $restored = $revisions->restore($softRevision);
        $this->assertFalse($restored->trashed());
        $this->assertSame('Restorable version', $restored->title);

        $mismatched = $metadataService->updateForRoute('frontend.contactUs', '/contact-us', 'en', [
            'title' => 'Do not overwrite',
        ]);
        $mismatchRevision = $revisions->capture($mismatched);
        DB::table('seo_metadata')->where('id', $mismatched->id)->update(['locale' => 'bn']);
        $historyBeforeMismatch = SeoMetadataRevision::count();
        $this->assertHttpConflict(fn () => $revisions->restore($mismatchRevision));
        $this->assertSame($historyBeforeMismatch, SeoMetadataRevision::count());
        $this->assertSame('Do not overwrite', $mismatched->fresh()->title);

        $gone = $metadataService->updateForRoute('frontend.gallery', '/gallery', 'en', [
            'title' => 'Physically removed',
        ]);
        $goneRevision = $revisions->capture($gone);
        $gone->forceDelete();
        $historyBeforeGone = SeoMetadataRevision::count();
        $this->assertHttpConflict(fn () => $revisions->restore($goneRevision));
        $this->assertSame($historyBeforeGone, SeoMetadataRevision::count());
        $this->assertDatabaseMissing('seo_metadata', ['id' => $gone->id]);
    }

    public function test_metadata_editor_can_restore_a_revision_through_the_uuid_endpoint(): void
    {
        $metadataPermission = MenuAction::where('link', 'seo.metadata.edit')->firstOrFail();
        [$admin] = $this->makeAdmin('Revision endpoint editor', (string) $metadataPermission->id);
        $this->actingAs($admin, 'admin')->withSession([
            Admin::SESSION_AUTH_VERSION => $admin->auth_version,
        ]);

        $metadataService = app(SeoMetadataService::class);
        $revisions = app(SeoMetadataRevisionService::class);
        $metadata = $metadataService->updateForRoute('frontend.contactUs', '/contact-us', 'en', [
            'title' => 'Endpoint restore point',
        ]);
        $revision = $revisions->capture($metadata, 'Endpoint restore point');
        $metadataService->updateForRoute('frontend.contactUs', '/contact-us', 'en', [
            'title' => 'Current endpoint title',
        ]);
        $historyBefore = SeoMetadataRevision::count();

        $this->post(route('seo.revisions.restore', $revision), [
            'expected_seo_version' => app(SeoMetadataEditorVersionService::class)
                ->currentForRoute('frontend.contactUs', '/contact-us', 'en'),
        ])
            ->assertRedirect(route('seo.index', [
                'route' => 'frontend.contactUs',
                'locale' => 'en',
            ]) . '#seo-editor')
            ->assertSessionHas('alert-type', 'success');

        $metadata->refresh();
        $this->assertSame('Endpoint restore point', $metadata->title);
        $this->assertSame($admin->id, $metadata->updated_by);
        $this->assertSame($historyBefore + 1, SeoMetadataRevision::count());
        $this->assertSame(
            'Current endpoint title',
            SeoMetadataRevision::latest('id')->firstOrFail()->snapshot['title']
        );
    }

    public function test_revision_restore_endpoint_accepts_curated_content_and_rejects_a_missing_owner(): void
    {
        $metadataPermission = MenuAction::where('link', 'seo.metadata.edit')->firstOrFail();
        [$admin] = $this->makeAdmin('Content revision editor', (string) $metadataPermission->id);
        $this->actingAs($admin, 'admin')->withSession([
            Admin::SESSION_AUTH_VERSION => $admin->auth_version,
        ]);

        $metadataService = app(SeoMetadataService::class);
        $revisions = app(SeoMetadataRevisionService::class);
        $page = $this->makePage('revision-content-page');
        $metadata = $metadataService->updateForModel($page, [
            'title' => 'Content restore point',
        ], 'en');
        $revision = $revisions->capture($metadata);
        $metadataService->updateForModel($page, [
            'title' => 'Content current title',
        ], 'en');

        $this->post(route('seo.revisions.restore', $revision), ['expected_editor_version' => 0])
            ->assertRedirect(route('seo.content.edit', [
                'type' => 'page',
                'id' => $page->id,
                'locale' => 'en',
            ]));
        $this->assertSame('Content restore point', $metadata->fresh()->title);

        $missingPage = $this->makePage('missing-revision-content-page');
        $missingMetadata = $metadataService->updateForModel($missingPage, [
            'title' => 'Missing owner restore point',
        ], 'en');
        $missingRevision = $revisions->capture($missingMetadata);
        $missingMetadata->forceFill(['title' => 'Missing owner current title'])->save();
        $missingPage->forceDelete();
        $historyBeforeMissingOwner = SeoMetadataRevision::count();

        $this->post(route('seo.revisions.restore', $missingRevision))->assertStatus(409);
        $this->assertSame('Missing owner current title', $missingMetadata->fresh()->title);
        $this->assertSame($historyBeforeMissingOwner, SeoMetadataRevision::count());
    }

    public function test_revision_restore_endpoint_requires_metadata_permission(): void
    {
        $metadataService = app(SeoMetadataService::class);
        $revisions = app(SeoMetadataRevisionService::class);
        $metadata = $metadataService->updateForRoute('frontend.contactUs', '/contact-us', 'en', [
            'title' => 'Permission restore point',
        ]);
        $revision = $revisions->capture($metadata);
        $metadataService->updateForRoute('frontend.contactUs', '/contact-us', 'en', [
            'title' => 'Permission protected current title',
        ]);
        $historyBefore = SeoMetadataRevision::count();

        $pageMenu = AuthMenu::create(['name' => 'Revision pages', 'link' => 'page.index', 'status' => 1]);
        $pageEdit = MenuAction::create([
            'auth_menu_id' => $pageMenu->id,
            'name' => 'Edit revision pages',
            'link' => 'page.edit',
            'status' => 1,
        ]);
        [$pageEditor] = $this->makeAdmin('Revision page editor', (string) $pageEdit->id);
        $this->actingAs($pageEditor, 'admin')->withSession([
            Admin::SESSION_AUTH_VERSION => $pageEditor->auth_version,
        ])->post(route('seo.revisions.restore', $revision))->assertForbidden();

        $redirectPermission = MenuAction::where('link', 'seo.redirects.create')->firstOrFail();
        [$redirectEditor] = $this->makeAdmin('Revision redirect editor', (string) $redirectPermission->id);
        $this->actingAs($redirectEditor, 'admin')->withSession([
            Admin::SESSION_AUTH_VERSION => $redirectEditor->auth_version,
        ])->post(route('seo.revisions.restore', $revision))->assertForbidden();

        $this->assertSame('Permission protected current title', $metadata->fresh()->title);
        $this->assertSame($historyBefore, SeoMetadataRevision::count());
    }

    public function test_revision_restore_endpoint_uses_uuid_binding_and_rejects_uncurated_targets_without_writes(): void
    {
        $metadataPermission = MenuAction::where('link', 'seo.metadata.edit')->firstOrFail();
        [$admin] = $this->makeAdmin('Revision object guard', (string) $metadataPermission->id);
        $this->actingAs($admin, 'admin')->withSession([
            Admin::SESSION_AUTH_VERSION => $admin->auth_version,
        ]);

        $metadataService = app(SeoMetadataService::class);
        $revisions = app(SeoMetadataRevisionService::class);

        $valid = $metadataService->updateForRoute('frontend.gallery', '/gallery', 'en', [
            'title' => 'UUID restore point',
        ]);
        $validRevision = $revisions->capture($valid);
        $metadataService->updateForRoute('frontend.gallery', '/gallery', 'en', [
            'title' => 'UUID protected current title',
        ]);
        $historyBeforeNumericBinding = SeoMetadataRevision::count();
        $this->post(url("/admin/seo/revisions/{$validRevision->id}/restore"))->assertNotFound();
        $this->assertSame('UUID protected current title', $valid->fresh()->title);
        $this->assertSame($historyBeforeNumericBinding, SeoMetadataRevision::count());

        $uncuratedRoute = $metadataService->updateForRoute('admin.login', '/admin/login', 'en', [
            'title' => 'Uncurated route restore point',
        ]);
        $uncuratedRevision = $revisions->capture($uncuratedRoute);
        $metadataService->updateForRoute('admin.login', '/admin/login', 'en', [
            'title' => 'Uncurated route current title',
        ]);
        $historyBeforeUncuratedRoute = SeoMetadataRevision::count();
        $this->post(route('seo.revisions.restore', $uncuratedRevision))->assertNotFound();
        $this->assertSame('Uncurated route current title', $uncuratedRoute->fresh()->title);
        $this->assertSame($historyBeforeUncuratedRoute, SeoMetadataRevision::count());

        $unsupportedOwner = $metadataService->updateForModel(
            Role::query()->firstOrFail(),
            ['title' => 'Unsupported owner restore point'],
            'en'
        );
        $unsupportedOwnerRevision = $revisions->capture($unsupportedOwner);
        $unsupportedOwner->forceFill(['title' => 'Unsupported owner current title'])->save();
        $historyBeforeUnsupportedOwner = SeoMetadataRevision::count();
        $this->post(route('seo.revisions.restore', $unsupportedOwnerRevision))->assertNotFound();
        $this->assertSame('Unsupported owner current title', $unsupportedOwner->fresh()->title);
        $this->assertSame($historyBeforeUnsupportedOwner, SeoMetadataRevision::count());

        $unsupportedLocale = $metadataService->updateForRoute('frontend.about', '/about-us', 'zz', [
            'title' => 'Unsupported locale restore point',
        ]);
        $unsupportedLocaleRevision = $revisions->capture($unsupportedLocale);
        $unsupportedLocale->forceFill(['title' => 'Unsupported locale current title'])->save();
        $historyBeforeUnsupportedLocale = SeoMetadataRevision::count();
        $this->post(route('seo.revisions.restore', $unsupportedLocaleRevision))->assertStatus(409);
        $this->assertSame('Unsupported locale current title', $unsupportedLocale->fresh()->title);
        $this->assertSame($historyBeforeUnsupportedLocale, SeoMetadataRevision::count());
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

    private function assertHttpConflict(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected an HTTP 409 conflict.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    /** @return array{0: Admin, 1: Role} */
    private function makeAdmin(string $name, string $actionPermission): array
    {
        $role = Role::create([
            'name' => $name . ' role',
            'permission' => '',
            'actionPermission' => $actionPermission,
            'serial' => '[]',
            'status' => 1,
        ]);
        $admin = Admin::create([
            'name' => $name,
            'username' => Str::slug($name),
            'email' => Str::slug($name) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
            'auth_version' => 0,
        ]);

        return [$admin, $role];
    }

    private function makePage(string $slug): Page
    {
        return Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'sub_title' => 'A public page.',
            'slug' => $slug,
            'status' => 1,
            'language' => 'en',
        ]);
    }
}
