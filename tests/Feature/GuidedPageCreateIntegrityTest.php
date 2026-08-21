<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Banner;
use App\Models\Category;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\Role;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuidedPageCreateIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_create_is_a_guided_single_language_draft_form(): void
    {
        $admin = $this->makePageCreator();
        $englishCategory = $this->makeCategory('English programs', 'en');
        $banglaCategory = $this->makeCategory('Bangla programs', 'bn');
        $this->makeCategory('Inactive category', 'en', 0);
        Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Technical service category',
            'slug' => 'technical-service-category',
            'type' => 'category-services',
            'language' => 'en',
            'status' => 1,
        ]);
        $englishBanner = $this->makeBanner('English page banner', 'en');
        $banglaBanner = $this->makeBanner('Bangla page banner', 'bn');
        $this->makeBanner('Inactive banner', 'en', 0);
        $this->makeBanner('Home-only banner', 'en', 1, 'banner-home');
        $activeTag = $this->makeTag('Education project');
        $this->makeTag('Inactive project', 0);

        $response = $this->actingAs($admin, 'admin')->get(route('page.create'));

        $response
            ->assertOk()
            ->assertSee('Create one page draft')
            ->assertSee('Simple Editor')
            ->assertSee('Nothing goes live now.')
            ->assertSee('name="creation_mode" value="guided"', false)
            ->assertSee('name="language"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="sub_title"', false)
            ->assertSee('name="category_id"', false)
            ->assertSee('name="banner_id"', false)
            ->assertSee('name="tags[]"', false)
            ->assertSee($englishCategory->name)
            ->assertSee($banglaCategory->name)
            ->assertSee($englishBanner->name)
            ->assertSee($banglaBanner->name)
            ->assertSee($activeTag->name)
            ->assertDontSee('Inactive category')
            ->assertDontSee('Technical service category')
            ->assertDontSee('Inactive banner')
            ->assertDontSee('Home-only banner')
            ->assertDontSee('Inactive project')
            ->assertDontSee('name="inline_css', false)
            ->assertDontSee('name="order_by', false)
            ->assertDontSee('name="is_relationship', false)
            ->assertDontSee('name="thumbnail', false)
            ->assertDontSee('save_and_update', false);
    }

    public function test_guided_submission_creates_one_revisioned_draft_and_redirects_to_simple_editor(): void
    {
        $admin = $this->makePageCreator();
        $category = $this->makeCategory('Community programs', 'en');
        $banner = $this->makeBanner('Community banner', 'en');
        $tag = $this->makeTag('Community project');
        $existing = $this->makePage('Existing community stories', 'community-stories');

        $response = $this->actingAs($admin, 'admin')->post(route('page.store'), [
            'creation_mode' => 'guided',
            'language' => 'en',
            'name' => 'Community stories',
            'sub_title' => 'News and progress from community-led work',
            'category_id' => $category->id,
            'banner_id' => $banner->id,
            'tags' => [$tag->id],
        ]);

        $page = Page::where('id', '!=', $existing->id)->sole();
        $response
            ->assertRedirect(route('page.builder.edit', ['uuid' => $page->uuid, 'locale' => 'en']))
            ->assertSessionHas('message', 'Draft created. Add the page sections below, then preview it before publishing.');

        $this->assertSame('Community stories', $page->name);
        $this->assertSame('News and progress from community-led work', $page->sub_title);
        $this->assertSame('en', $page->language);
        $this->assertSame($category->id, (int) $page->category_id);
        $this->assertSame($banner->id, (int) $page->banner_id);
        $this->assertSame('draft', $page->publication_status);
        $this->assertFalse((bool) $page->status);
        $this->assertNull($page->published_at);
        $this->assertNull($page->description);
        $this->assertNull($page->inline_css);
        $this->assertNull($page->order_by);
        $this->assertTrue((bool) $page->name_enabled);
        $this->assertTrue((bool) $page->sub_title_enabled);
        $this->assertTrue((bool) $page->is_relationship);
        $this->assertNotSame('community-stories', $page->slug);
        $this->assertStringStartsWith('community-stories-', $page->slug);
        $this->assertSame([$tag->id], $page->pageTags()->pluck('tag_id')->map(fn ($id) => (int) $id)->all());

        $revision = $page->revisions()->sole();
        $this->assertSame('Initial snapshot from guided page creation', $revision->note);
        $this->assertSame($page->slug, $revision->snapshot['page']['slug']);
        $this->assertSame([$tag->id], collect($revision->snapshot['tags'])->pluck('tag_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_guided_submission_rejects_inactive_or_wrong_language_choices_without_writing(): void
    {
        $admin = $this->makePageCreator();
        $banglaCategory = $this->makeCategory('Bangla-only category', 'bn');
        $banglaBanner = $this->makeBanner('Bangla-only banner', 'bn');
        $inactiveTag = $this->makeTag('Inactive guided tag', 0);

        $this->actingAs($admin, 'admin')
            ->from(route('page.create'))
            ->post(route('page.store'), [
                'creation_mode' => 'guided',
                'language' => 'en',
                'name' => 'Invalid choice draft',
                'category_id' => $banglaCategory->id,
                'banner_id' => $banglaBanner->id,
                'tags' => [$inactiveTag->id],
            ])
            ->assertRedirect(route('page.create'))
            ->assertSessionHasErrors(['category_id', 'banner_id', 'tags.0']);

        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('page_revisions', 0);
        $this->assertDatabaseCount('page_tag_modules', 0);
    }

    public function test_legacy_multi_language_payload_processes_each_locales_thumbnail_tags_and_revision(): void
    {
        $admin = $this->makePageCreator();
        $englishTag = $this->makeTag('English legacy project');
        $banglaTag = $this->makeTag('Bangla legacy project');
        $originalStoragePath = app()->storagePath();
        $temporaryStorage = $originalStoragePath . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'testing'
            . DIRECTORY_SEPARATOR . 'guided-page-' . Str::lower(Str::random(10));
        File::ensureDirectoryExists($temporaryStorage);
        app()->useStoragePath($temporaryStorage);

        try {
            $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
            $processedImage = \Mockery::mock();
            $processedImage->shouldReceive('resize')->twice()->with(410, 240)->andReturnSelf();
            $processedImage->shouldReceive('encode')->twice()->with('png', 75)->andReturn($tinyPng);
            \Intervention\Image\Facades\Image::shouldReceive('make')->twice()->andReturn($processedImage);

            $response = $this->actingAs($admin, 'admin')->post(route('page.store'), [
                'language' => ['en' => 'en', 'bn' => 'bn'],
                'name' => ['en' => 'Legacy English page', 'bn' => 'Legacy Bangla page'],
                'sub_title' => ['en' => 'English subtitle', 'bn' => 'Bangla subtitle'],
                'description' => ['en' => '<p>English body</p>', 'bn' => '<p>Bangla body</p>'],
                'inline_css' => ['en' => '', 'bn' => ''],
                'tags' => ['en' => [$englishTag->id], 'bn' => [$banglaTag->id]],
                'thumbnail' => [
                    'en' => UploadedFile::fake()->createWithContent('english.png', $tinyPng),
                    'bn' => UploadedFile::fake()->createWithContent('bangla.png', $tinyPng),
                ],
            ]);

            $response->assertRedirect(route('page.index'));

            $pages = Page::orderBy('language')->get()->keyBy('language');
            $this->assertCount(2, $pages);
            $this->assertSame($pages['en']->uuid, $pages['bn']->uuid);
            $this->assertNotEmpty($pages['en']->thumbnail);
            $this->assertNotEmpty($pages['bn']->thumbnail);
            $this->assertNotSame($pages['en']->thumbnail, $pages['bn']->thumbnail);
            $this->assertSame([$englishTag->id], $pages['en']->pageTags()->pluck('tag_id')->map(fn ($id) => (int) $id)->all());
            $this->assertSame([$banglaTag->id], $pages['bn']->pageTags()->pluck('tag_id')->map(fn ($id) => (int) $id)->all());

            foreach ($pages as $page) {
                $revision = $page->revisions()->sole();
                $this->assertSame('Initial snapshot from legacy page creation', $revision->note);
                $this->assertSame($page->thumbnail, $revision->snapshot['page']['thumbnail']);
                $this->assertCount(1, $revision->snapshot['tags']);
            }
        } finally {
            app()->useStoragePath($originalStoragePath);
            if (File::isDirectory($temporaryStorage)) {
                File::deleteDirectory($temporaryStorage);
            }
        }
    }

    private function makePageCreator(): Admin
    {
        $menu = AuthMenu::where('link', 'page.index')->firstOrFail();
        $actions = MenuAction::whereIn('link', ['page.create', 'page.builder.edit'])->get();
        $this->assertCount(2, $actions);
        $role = Role::create([
            'name' => 'Guided page creator ' . Str::random(5),
            'permission' => (string) $menu->id,
            'actionPermission' => $actions->pluck('id')->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        $identity = Str::lower(Str::random(8));

        return Admin::create([
            'name' => 'Guided Page Creator',
            'username' => 'guided-page-' . $identity,
            'email' => 'guided-page-' . $identity . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);
    }

    private function makeCategory(string $name, string $language, int $status = 1): Category
    {
        return Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'language' => $language,
            'status' => $status,
        ]);
    }

    private function makeBanner(string $name, string $language, int $status = 1, string $type = 'banner-page'): Banner
    {
        return Banner::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'type' => $type,
            'language' => $language,
            'status' => $status,
        ]);
    }

    private function makeTag(string $name, int $status = 1): Tag
    {
        return Tag::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'status' => $status,
        ]);
    }

    private function makePage(string $name, string $slug): Page
    {
        return Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'sub_title' => '',
            'slug' => $slug,
            'description' => null,
            'language' => 'en',
            'status' => 0,
            'publication_status' => 'draft',
            'visibility' => 'public',
        ]);
    }
}
