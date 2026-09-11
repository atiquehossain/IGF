<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Category;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\Role;
use App\Models\Tag;
use App\Services\SeoMetadataService;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CanonicalEditorHandoffIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionRegistrySeeder::class);
    }

    public function test_content_hub_shows_authoritative_special_and_category_landing_urls(): void
    {
        $admin = $this->makeAdmin(['page.index', 'page.builder.edit']);
        $home = $this->makePage(['name' => 'Homepage', 'slug' => 'home']);
        $about = $this->makePage(['name' => 'About us', 'slug' => 'about-us']);
        $zakat = $this->makePage(['name' => 'Zakat', 'slug' => 'zakat']);
        $landing = $this->makePage(['name' => 'School landing', 'slug' => 'school-landing']);
        $category = $this->makeCategory([
            'name' => 'Visit our school',
            'slug' => 'visit-our-school',
            'display_mode' => 'landing_page',
            'landing_page_uuid' => $landing->uuid,
        ]);
        $landing->update(['category_id' => $category->id]);

        $response = $this->actingAs($admin, 'admin')->get(route('page.index', ['language' => 'en']))
            ->assertOk();

        $response
            ->assertSee('Live: <a class="hub-live-address" href="'.route('frontend.home').'"', false)
            ->assertSee('>/</a>', false)
            ->assertSee('Live: <a class="hub-live-address" href="'.route('frontend.about').'"', false)
            ->assertSee('>/about-us</a>', false)
            ->assertSee('Live: <a class="hub-live-address" href="'.route('frontend.zakat').'"', false)
            ->assertSee('>/zakat</a>', false)
            ->assertSee('Live: <a class="hub-live-address" href="'.route('frontend.category', ['slug' => $category->slug]).'"', false)
            ->assertSee('>/category/visit-our-school</a>', false)
            ->assertDontSee('/page/home')
            ->assertDontSee('/page/about-us')
            ->assertDontSee('/page/zakat')
            ->assertDontSee('/page/school-landing');
    }

    public function test_translated_special_page_uses_its_real_live_url_and_customizer_handoff(): void
    {
        $admin = $this->makeAdmin(['page.index', 'page.builder.edit', 'site.settings.update']);
        $uuid = (string) Str::uuid();
        $this->makePage(['uuid' => $uuid, 'name' => 'Sponsor a child', 'slug' => 'sponsor-a-child']);
        $this->makePage([
            'uuid' => $uuid,
            'name' => 'শিশু স্পনসর করুন',
            'slug' => 'shishu-sponsor',
            'language' => 'bn',
        ]);
        $liveUrl = app(SeoMetadataService::class)->localizedUrl(route('frontend.sponsor_child'), 'bn');

        $this->actingAs($admin, 'admin')->get(route('page.index', ['language' => 'bn']))
            ->assertOk()
            ->assertSee('href="'.$liveUrl.'"', false)
            ->assertSee('>/sponsor-child?lang=bn</a>', false)
            ->assertSee('Sponsor customizer')
            ->assertDontSee('/page/shishu-sponsor');
    }

    public function test_both_builders_show_the_canonical_live_address_and_guided_create_actions(): void
    {
        $admin = $this->makeAdmin(['page.builder.edit', 'page.create']);
        $page = $this->makePage(['name' => 'Programs landing', 'slug' => 'programs-landing']);
        $category = $this->makeCategory([
            'name' => 'Our programs',
            'slug' => 'our-programs',
            'display_mode' => 'landing_page',
            'landing_page_uuid' => $page->uuid,
        ]);
        $page->update(['category_id' => $category->id]);
        $liveUrl = route('frontend.category', ['slug' => 'our-programs']);

        foreach ([null, 'advanced'] as $mode) {
            $parameters = ['uuid' => $page->uuid, 'locale' => 'en'];
            if ($mode) {
                $parameters['mode'] = $mode;
            }

            $response = $this->actingAs($admin, 'admin')
                ->get(route('page.builder.edit', $parameters))
                ->assertOk()
                ->assertSee('href="'.$liveUrl.'"', false)
                ->assertSee('/category/our-programs')
                ->assertSee('Preview draft')
                ->assertSee('View live');

            $source = $response->getContent();
            $this->assertStringContainsString("label:'Add program'", $source);
            $this->assertStringContainsString("label:'Add project'", $source);
            $this->assertStringContainsString("url.searchParams.set('category_slug'", $source);
            $this->assertStringContainsString("url.searchParams.set('tag_slug'", $source);
            $this->assertStringContainsString('createPage: '.json_encode(route('page.create')), $source);
        }
    }

    public function test_guided_page_creation_safely_preselects_requested_program_or_project_context(): void
    {
        $admin = $this->makeAdmin(['page.create']);
        $category = $this->makeCategory(['name' => 'Our causes', 'slug' => 'our-causes']);
        $blogCategory = Category::query()
            ->where('uuid', '61000000-0000-4000-8000-000000000007')
            ->where('language', 'en')
            ->firstOrFail();
        $tag = Tag::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Current projects',
            'slug' => 'current-projects',
            'status' => true,
        ]);

        $this->actingAs($admin, 'admin')->get(route('page.create', [
            'language' => 'en',
            'kind' => 'program',
            'category_slug' => $category->slug,
        ]))->assertOk()
            ->assertSee('<h1>Create one program draft</h1>', false)
            ->assertSee('value="'.$category->id.'" data-locale-option="en" selected', false);

        $this->actingAs($admin, 'admin')->get(route('page.create', [
            'language' => 'en',
            'kind' => 'project',
            'tag_slug' => $tag->slug,
        ]))->assertOk()
            ->assertSee('<h1>Create one project draft</h1>', false)
            ->assertSee('name="tags[]" value="'.$tag->id.'" checked', false);

        $this->actingAs($admin, 'admin')->get(route('page.create', [
            'language' => 'en',
            'kind' => 'blog',
            'category_slug' => $blogCategory->slug,
        ]))->assertOk()
            ->assertSee('<h1>Create one blog post draft</h1>', false)
            ->assertSee('<h2 id="draft-basics-heading">Blog post basics</h2>', false)
            ->assertSee('value="'.$blogCategory->id.'" data-locale-option="en" selected', false);

        $this->actingAs($admin, 'admin')->get(route('page.create', [
            'language' => 'en',
            'kind' => 'program',
            'category_slug' => 'not-a-real-category',
        ]))->assertOk()
            ->assertDontSee('value="'.$category->id.'" data-locale-option="en" selected', false);
    }

    public function test_category_copy_url_uses_the_slug_as_the_route_parameter(): void
    {
        $admin = $this->makeAdmin(['category.index']);
        $category = $this->makeCategory(['name' => 'Education programs', 'slug' => 'education-programs']);
        $expected = route('frontend.category', ['slug' => $category->slug]);

        $this->actingAs($admin, 'admin')->get(route('category.index'))
            ->assertOk()
            ->assertSee('data-route="'.$expected.'"', false)
            ->assertDontSee('/category/en?education-programs', false);
    }

    private function makePage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Canonical page '.Str::random(5),
            'sub_title' => 'Canonical editor handoff test',
            'slug' => 'canonical-'.Str::lower(Str::random(8)),
            'status' => true,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
        ], $overrides));
    }

    private function makeCategory(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Canonical category '.Str::random(5),
            'slug' => 'category-'.Str::lower(Str::random(8)),
            'display_mode' => 'archive',
            'status' => true,
            'language' => 'en',
        ], $overrides));
    }

    /** @param list<string> $capabilities */
    private function makeAdmin(array $capabilities): Admin
    {
        $role = Role::create([
            'name' => 'Canonical editor '.Str::random(8),
            'permission' => AuthMenu::query()->whereIn('link', $capabilities)->pluck('id')->implode(','),
            'actionPermission' => MenuAction::query()->whereIn('link', $capabilities)->pluck('id')->implode(','),
            'serial' => '[]',
            'status' => true,
        ]);

        return Admin::create([
            'name' => 'Canonical Editor',
            'username' => 'canonical-'.Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(8)).'@example.test',
            'role' => (string) $role->id,
            'status' => true,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
