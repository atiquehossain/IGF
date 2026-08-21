<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AnnualReport;
use App\Models\AuthMenu;
use App\Models\Category;
use App\Models\MenuAction;
use App\Models\NoticeBoard;
use App\Models\Role;
use App\Models\SeoMetadata;
use App\Models\Tag;
use App\Services\SeoMetadataEditorVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EditorialGovernanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_public_localization_is_not_exposed_as_a_broken_language_switch(): void
    {
        $this->assertFalse(config('localization.public_switcher_enabled'));
        $this->assertSame(['en'], config('localization.public_locales'));
        $this->get('/language/bn')->assertNotFound();
        $this->assertStringContainsString(
            'inertiaPage.props.publicLocaleSwitcherEnabled',
            file_get_contents(resource_path('js/layouts/AppHeader.vue'))
        );
    }

    public function test_editorial_content_moves_to_shared_trash_and_restores_with_media_and_seo(): void
    {
        Storage::fake('local');
        $admin = $this->authorizedAdmin();
        $report = AnnualReport::create([
            'title' => 'Annual impact report',
            'slug' => 'annual-impact-report',
            'image_path' => 'report.pdf',
            'status' => 1,
        ]);
        Storage::disk('local')->put('annual-reports/report.pdf', '%PDF-1.7 test');
        SeoMetadata::create([
            'seoable_type' => AnnualReport::class,
            'seoable_id' => $report->id,
            'locale' => 'en',
            'title' => 'Annual impact report SEO',
        ]);

        $report->delete();

        $this->assertSoftDeleted($report);
        Storage::disk('local')->assertExists('annual-reports/report.pdf');
        $this->asAdmin($admin)->get(route('content.trash.index'))
            ->assertOk()
            ->assertSee('Annual impact report');

        $this->asAdmin($admin)
            ->postJson(route('content.trash.restore', ['annual-report', $report->id]))
            ->assertOk();
        $this->assertDatabaseHas('annual_reports', ['id' => $report->id, 'deleted_at' => null]);
        Storage::disk('local')->assertExists('annual-reports/report.pdf');

        $report->fresh()->delete();
        $this->asAdmin($admin)
            ->deleteJson(route('content.trash.force-destroy', ['annual-report', $report->id]))
            ->assertOk();
        $this->assertDatabaseMissing('annual_reports', ['id' => $report->id]);
        $this->assertDatabaseMissing('seo_metadata', ['seoable_type' => AnnualReport::class, 'seoable_id' => $report->id]);
        Storage::disk('local')->assertMissing('annual-reports/report.pdf');
    }

    public function test_dynamic_content_has_complete_admin_seo_and_public_head_and_sitemap_controls(): void
    {
        $admin = $this->authorizedAdmin();
        $category = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Climate resilience',
            'slug' => 'climate-resilience',
            'description' => 'Community climate programs.',
            'language' => 'en',
            'status' => 1,
        ]);
        $event = NoticeBoard::create([
            'title' => 'Community gathering',
            'slug' => 'community-gathering',
            'description' => 'A community event.',
            'language' => 'en',
            'status' => 1,
            'published_at' => now(),
        ]);
        $project = Tag::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Safe water',
            'slug' => 'safe-water',
            'status' => 1,
        ]);

        $payload = [
            'locale' => 'en',
            'expected_seo_version' => app(SeoMetadataEditorVersionService::class)
                ->currentForModel($category, 'en'),
            'seo' => [
                'title' => 'Climate action led by communities',
                'description' => 'A custom search description for climate programs.',
                'focus_keyword' => 'community climate action',
                'canonical_url' => url('/category/climate-resilience'),
                'robots_index' => 1,
                'robots_follow' => 1,
                'og_title' => 'Community climate action',
                'og_description' => 'Custom social description.',
                'og_image' => 'https://images.example.test/climate.jpg',
                'twitter_card' => 'summary_large_image',
                'twitter_title' => 'Community climate action',
                'twitter_description' => 'Custom X description.',
                'twitter_image' => 'https://images.example.test/climate.jpg',
                'schema_markup' => json_encode(['@context' => 'https://schema.org', '@type' => 'CollectionPage'], JSON_THROW_ON_ERROR),
                'sitemap_priority' => 0.8,
                'sitemap_change_frequency' => 'weekly',
                'exclude_from_sitemap' => 0,
            ],
        ];

        $this->asAdmin($admin)->get(route('seo.content.edit', ['category', $category->id]))
            ->assertOk()
            ->assertSee('Open Graph title')
            ->assertSee('Schema markup');
        $this->asAdmin($admin)->put(route('seo.content.update', ['category', $category->id]), $payload)
            ->assertRedirect(route('seo.content.edit', ['category', $category->id]));

        $this->get(route('frontend.category', ['slug' => $category->slug]))
            ->assertOk()
            ->assertSee('<title inertia>Climate action led by communities</title>', false)
            ->assertSee('rel="canonical" href="' . url('/category/climate-resilience') . '"', false)
            ->assertSee('CollectionPage', false);

        SeoMetadata::create([
            'seoable_type' => NoticeBoard::class,
            'seoable_id' => $event->id,
            'locale' => 'en',
            'robots_index' => false,
            'exclude_from_sitemap' => true,
        ]);
        SeoMetadata::create([
            'seoable_type' => Tag::class,
            'seoable_id' => $project->id,
            'locale' => 'en',
            'canonical_url' => url('/projects/safe-water'),
            'sitemap_priority' => 0.9,
        ]);

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString(url('/category/climate-resilience'), $sitemap);
        $this->assertStringNotContainsString(url('/event/community-gathering'), $sitemap);
        $this->assertStringContainsString(url('/projects/safe-water'), $sitemap);
    }

    private function authorizedAdmin(): Admin
    {
        $role = Role::create(['name' => 'Editorial owner', 'permission' => '', 'actionPermission' => '', 'serial' => '[]', 'status' => 1]);
        $trashMenu = AuthMenu::where('link', 'content.trash.index')->firstOrFail();
        $seoMetadata = MenuAction::where('link', 'seo.metadata.edit')->firstOrFail();
        $trashActions = MenuAction::whereIn('link', ['content.trash.edit', 'content.trash.destroy'])->get();
        $role->update([
            'permission' => (string) $trashMenu->id,
            'actionPermission' => $trashActions->pluck('id')->push($seoMetadata->id)->implode(','),
        ]);

        return Admin::create([
            'name' => 'Editorial QA',
            'username' => 'editorial-qa',
            'email' => 'editorial-qa@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }

    private function asAdmin(Admin $admin): self
    {
        $this->actingAs($admin, 'admin');
        session()->put(Admin::SESSION_AUTH_VERSION, $admin->auth_version);

        return $this;
    }
}
