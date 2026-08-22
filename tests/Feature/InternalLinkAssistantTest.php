<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\PageTagModule;
use App\Models\Role;
use App\Models\SeoMetadata;
use App\Models\Tag;
use App\Services\InternalLinkAssistantService;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalLinkAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionRegistrySeeder::class);
    }

    public function test_it_returns_three_to_five_transparent_language_matched_source_suggestions(): void
    {
        $category = $this->category('Climate resilience', 'en');
        $target = $this->page(
            'Coastal climate adaptation',
            'coastal-climate-adaptation',
            'en',
            $category,
            'Community-led coastal resilience protects families from flooding.'
        );
        SeoMetadata::create([
            'seoable_type' => Page::class,
            'seoable_id' => $target->id,
            'locale' => 'en',
            'title' => 'Coastal climate adaptation',
            'description' => 'Practical coastal climate adaptation for communities.',
            'focus_keyword' => 'coastal resilience',
        ]);

        $tag = Tag::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Climate action',
            'slug' => 'climate-action',
            'status' => 1,
        ]);
        $this->tag($target, $tag);

        $sourceA = $this->page('Disaster preparedness', 'disaster-preparedness', 'en', $category, 'Coastal resilience and flood preparation support local families.');
        $sourceB = $this->page('Community programs', 'community-programs', 'en', $category, 'Our climate adaptation work connects communities with practical support.');
        $sourceC = $this->page('Flood response', 'flood-response', 'en', null, 'Learn how coastal communities build flood resilience together.');
        $sourceD = $this->page('Environmental projects', 'environmental-projects', 'en', null, 'Climate action projects protect coastal livelihoods.');
        $this->tag($sourceA, $tag);
        $this->tag($sourceD, $tag);

        $bangla = $this->page('উপকূলীয় জলবায়ু সহায়তা', 'bangla-coastal-support', 'bn', null, 'Coastal resilience climate adaptation');

        $analysis = app(InternalLinkAssistantService::class)->recommendations('en');
        $recommendation = collect($analysis['targets'])->firstWhere('id', $target->id);

        $this->assertNotNull($recommendation);
        $this->assertSame('orphan', $recommendation['status']);
        $this->assertCount(4, $recommendation['suggestions']);
        $this->assertContains('coastal resilience', $recommendation['suggestions'][0]['anchor_phrases']);
        $this->assertContains('Coastal climate adaptation', $recommendation['suggestions'][0]['anchor_phrases']);
        $this->assertNotEmpty($recommendation['suggestions'][0]['reasons']);
        $this->assertTrue(collect($recommendation['suggestions'])->every(
            fn (array $suggestion): bool => $suggestion['source_locale'] === 'en'
        ));
        $this->assertNotContains($bangla->id, collect($recommendation['suggestions'])->pluck('source_id')->all());
        $this->assertContains($sourceB->id, collect($recommendation['suggestions'])->pluck('source_id')->all());
        $this->assertContains($sourceC->id, collect($recommendation['suggestions'])->pluck('source_id')->all());
    }

    public function test_existing_internal_links_are_counted_and_the_linking_page_is_not_suggested_again(): void
    {
        $category = $this->category('Education', 'en');
        $target = $this->page('Girls education', 'girls-education', 'en', $category, 'Education opportunities for girls and families.');
        $linkedSource = $this->page(
            'Community schools',
            'community-schools',
            'en',
            $category,
            '<p>Explore our <a href="/page/girls-education?campaign=school">girls education work</a>.</p>'
        );
        $this->page('Learning support', 'learning-support', 'en', $category, 'Education support and learning opportunities for girls.');

        $analysis = app(InternalLinkAssistantService::class)->recommendations('en');
        $recommendation = collect($analysis['targets'])->firstWhere('id', $target->id);

        $this->assertNotNull($recommendation);
        $this->assertSame('weak', $recommendation['status']);
        $this->assertSame(1, $recommendation['inbound_count']);
        $this->assertNotContains($linkedSource->id, collect($recommendation['suggestions'])->pluck('source_id')->all());
    }

    public function test_the_workflow_is_read_only_permission_aware_and_explains_disconnected_languages(): void
    {
        $englishCategory = $this->category('Livelihoods', 'en');
        $target = $this->page('Income resilience', 'income-resilience', 'en', $englishCategory, 'Income resilience support for families.');
        $source = $this->page('Skills training', 'skills-training', 'en', $englishCategory, 'Skills and income resilience for families.');
        $bangla = $this->page('একটি বাংলা পাতা', 'one-bangla-page', 'bn', null, 'বাংলা বিষয়বস্তু');

        $viewer = $this->adminWithCapabilities(['seo.metadata.view'], 'link-viewer');
        $response = $this->actingAs($viewer, 'admin')
            ->get(route('seo.internal-links.index', ['locale' => 'en']));

        $response->assertOk()
            ->assertSee('Contextual link assistant')
            ->assertSee('nothing is inserted or published automatically')
            ->assertSee('Pages with no contextual page links')
            ->assertSee('This editorial assistant counts links inside managed page content only.')
            ->assertSee('use Technical link checks for site-wide orphan status.')
            ->assertSee('No contextual links found in managed page content.')
            ->assertDontSee('Orphan page')
            ->assertSee('Ask a Content Hub editor to add this link.')
            ->assertSee(route('seo.content.edit', ['type' => 'page', 'id' => $target->id, 'locale' => 'en']), false)
            ->assertDontSee(route('page.builder.edit', ['uuid' => $source->uuid, 'locale' => 'en']), false);

        $contentEditor = $this->adminWithCapabilities(['seo.metadata.view', 'page.builder.edit'], 'link-content-editor');
        $this->actingAs($contentEditor, 'admin')
            ->get(route('seo.internal-links.index', ['locale' => 'en']))
            ->assertOk()
            ->assertSee(route('page.builder.edit', ['uuid' => $source->uuid, 'locale' => 'en']), false)
            ->assertSee('Edit source content');

        $banglaUrl = collect(app(InternalLinkAssistantService::class)->recommendations('bn')['targets'])
            ->firstWhere('id', $bangla->id)['public_url'];
        $this->actingAs($viewer, 'admin')
            ->get(route('seo.internal-links.index', ['locale' => 'bn']))
            ->assertOk()
            ->assertSee('BN is not enabled on the public site.')
            ->assertSee('Publish-ready pages checked')
            ->assertSee('Translated address not live')
            ->assertDontSee($banglaUrl, false)
            ->assertDontSee('View target')
            ->assertSee('No relevant source page found in BN')
            ->assertSee('This is a disconnected topic');

        $unprivileged = $this->adminWithCapabilities([], 'no-seo');
        $this->actingAs($unprivileged, 'admin')
            ->get(route('seo.internal-links.index'))
            ->assertForbidden();

        $this->actingAs($viewer, 'admin')
            ->post('/admin/seo/internal-links')
            ->assertStatus(405);
    }

    private function category(string $name, string $locale): Category
    {
        return Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name) ?: (string) Str::uuid(),
            'description' => $name . ' programs',
            'language' => $locale,
            'status' => 1,
        ]);
    }

    private function page(string $name, string $slug, string $locale, ?Category $category, string $description): Page
    {
        return Page::create([
            'uuid' => (string) Str::uuid(),
            'category_id' => $category?->id,
            'name' => $name,
            'sub_title' => strip_tags($description),
            'description' => $description,
            'slug' => $slug,
            'language' => $locale,
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
        ]);
    }

    private function tag(Page $page, Tag $tag): void
    {
        PageTagModule::create([
            'uuid' => (string) Str::uuid(),
            'page_id' => $page->id,
            'tag_id' => $tag->id,
        ]);
    }

    /** @param array<int, string> $capabilities */
    private function adminWithCapabilities(array $capabilities, string $username): Admin
    {
        $actionIds = MenuAction::query()->whereIn('link', $capabilities)->pluck('id');
        $this->assertCount(count($capabilities), $actionIds);
        $role = Role::create([
            'name' => Str::headline($username),
            'permission' => '',
            'actionPermission' => $actionIds->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => Str::headline($username),
            'username' => $username,
            'email' => $username . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
