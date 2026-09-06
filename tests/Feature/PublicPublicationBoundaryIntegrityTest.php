<?php

namespace Tests\Feature;

use App\Helper\MyMenu;
use App\Http\Controllers\Admin\PageBuilderController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Models\Album;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageMenu;
use App\Models\ReusableBlock;
use App\Services\PageBlockContentResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class PublicPublicationBoundaryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_reusable_section_is_not_rendered_or_searched_via_stale_local_content(): void
    {
        $page = $this->page('Reusable publication boundary', 'reusable-publication-boundary');
        $reusable = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared publication section',
            'type' => 'rich_text',
            'locale' => '*',
            'content' => ['body' => '<p>Current shared comet phrase 7319.</p>'],
            'settings' => ['tone' => 'current'],
            'is_enabled' => true,
        ]);
        $block = PageBlock::create([
            'page_id' => $page->id,
            'reusable_block_id' => $reusable->id,
            'uuid' => (string) Str::uuid(),
            'type' => 'rich_text',
            'label' => 'Old local section label',
            'content' => ['body' => '<p>Stale local nebula phrase 4826.</p>'],
            'settings' => ['tone' => 'stale'],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);

        $this->withHeaders($this->inertiaHeaders())
            ->get(route('frontend.page', ['slug' => $page->slug]))
            ->assertOk()
            ->assertJsonCount(1, 'props.data.page.visible_blocks')
            ->assertJsonPath('props.data.page.visible_blocks.0.content.body', '<p>Current shared comet phrase 7319.</p>');

        $reusable->update(['is_enabled' => false]);
        $disabled = $block->fresh('reusableBlock');

        $this->assertSame([], $disabled->resolvedContent());
        $this->assertSame([], $disabled->resolvedSettings());
        $this->assertFalse(PageBlock::visible()->whereKey($block->id)->exists());
        $this->withHeaders($this->inertiaHeaders())
            ->get(route('frontend.page', ['slug' => $page->slug]))
            ->assertOk()
            ->assertJsonCount(0, 'props.data.page.visible_blocks');
        $this->withHeaders($this->inertiaHeaders())
            ->get('/search?search=stale%20local%20nebula')
            ->assertOk()
            ->assertJsonPath('props.properties.total_count', 0);

        $reusable->update(['is_enabled' => true]);
        $this->assertTrue(PageBlock::visible()->whereKey($block->id)->exists());
    }

    public function test_album_publication_boundary_is_shared_by_search_blocks_and_builder_picker(): void
    {
        $publishedAlbum = $this->album('Published boundary album', true);
        $draftAlbum = $this->album('Draft boundary album', false);
        $deletedAlbum = $this->album('Deleted boundary album', true);

        $this->photo('Gallery boundary published photo', $publishedAlbum, 40);
        $this->photo('Gallery boundary draft-album photo', $draftAlbum, 30);
        $this->photo('Gallery boundary deleted-album photo', $deletedAlbum, 20);
        $this->photo('Gallery boundary legacy albumless photo', null, 10);
        $deletedAlbum->delete();

        $resolved = app(PageBlockContentResolver::class)->resolve(new PageBlock([
            'type' => 'gallery',
            'content' => [
                'content_source' => 'gallery',
                'selection_mode' => 'automatic',
                'sort' => 'featured',
                'limit' => 12,
            ],
        ]));
        $expected = [
            'Gallery boundary published photo',
            'Gallery boundary legacy albumless photo',
        ];

        $this->assertSame($expected, collect($resolved['items'])->pluck('heading')->all());

        $search = $this->withHeaders($this->inertiaHeaders())
            ->get('/search?search=gallery%20boundary')
            ->assertOk();
        $this->assertSame($expected, collect($search->json('props.data.pages'))->pluck('name')->all());

        $options = $this->builderContentOptions();
        $this->assertEqualsCanonicalizing(
            $expected,
            collect(data_get($options, 'items.gallery'))->pluck('label')->all()
        );
    }

    public function test_unlisted_pages_stay_directly_accessible_but_out_of_public_discovery(): void
    {
        $category = Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Boundary programs',
            'slug' => 'boundary-programs',
            'language' => 'en',
            'status' => 1,
        ]);
        $public = $this->page('Discovery boundary public page', 'discovery-boundary-public', [
            'category_id' => $category->id,
            'order_by' => 20,
        ]);
        $unlisted = $this->page('Discovery boundary unlisted page', 'discovery-boundary-unlisted', [
            'category_id' => $category->id,
            'visibility' => 'unlisted',
            'order_by' => 30,
        ]);

        $this->withHeaders($this->inertiaHeaders())
            ->get('/search?search=discovery%20boundary')
            ->assertOk()
            ->assertJsonPath('props.properties.total_count', 1)
            ->assertJsonPath('props.data.pages.0.name', $public->name);

        $resolved = app(PageBlockContentResolver::class)->resolve(new PageBlock([
            'type' => 'cards',
            'content' => [
                'content_source' => 'category',
                'category_slug' => $category->slug,
                'selection_mode' => 'automatic',
                'sort' => 'featured',
                'limit' => 12,
            ],
        ]));
        $this->assertSame([$public->name], collect($resolved['items'])->pluck('heading')->all());

        $pickerLabels = collect(data_get($this->builderContentOptions(), 'items.category'))->pluck('label');
        $this->assertTrue($pickerLabels->contains($public->name));
        $this->assertFalse($pickerLabels->contains($unlisted->name));

        $this->get(route('frontend.page', ['slug' => $unlisted->slug]))->assertOk();
    }

    public function test_page_trash_hides_active_navigation_link_and_restore_brings_it_back_without_mutating_menu(): void
    {
        $page = $this->page('Recoverable navigation page', 'recoverable-navigation-page');
        $parent = PageMenu::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Navigation group',
            'type' => 'main',
            'link' => null,
            'slug' => null,
            'language' => 'en',
            'order_by' => 500,
            'status' => 1,
        ]);
        $menu = PageMenu::create([
            'uuid' => (string) Str::uuid(),
            'parent_id' => $parent->id,
            'name' => 'Recoverable navigation link',
            'type' => 'main',
            'link' => 'frontend.page',
            'slug' => $page->slug,
            'language' => 'en',
            'order_by' => 1,
            'status' => 1,
        ]);

        $this->assertTrue($this->publicMenuUuids()->contains($menu->uuid));

        $controller = app(AdminPageController::class);
        $deleteRequest = Request::create('/admin/page/' . $page->uuid, 'DELETE');
        $deleteRequest->Lang = $this->adminLanguage();
        $this->assertSame(200, $controller->destroy($page->uuid, $deleteRequest)->getStatusCode());

        $this->assertSoftDeleted('pages', ['id' => $page->id]);
        $this->assertDatabaseHas('page_menus', [
            'id' => $menu->id,
            'status' => 1,
            'deleted_at' => null,
        ]);
        $this->assertFalse($this->publicMenuUuids()->contains($menu->uuid));

        $restoreRequest = Request::create('/admin/page-trash/' . $page->uuid . '/restore', 'POST');
        $restoreRequest->Lang = $this->adminLanguage();
        $this->assertSame(200, $controller->restore($page->uuid, $restoreRequest)->getStatusCode());

        $this->assertNotSoftDeleted('pages', ['id' => $page->id]);
        $this->assertTrue($this->publicMenuUuids()->contains($menu->uuid));
    }

    private function page(string $name, string $slug, array $overrides = []): Page
    {
        return Page::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'sub_title' => 'Publication boundary fixture',
            'description' => '',
            'slug' => $slug,
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
        ], $overrides));
    }

    private function album(string $name, bool $published): Album
    {
        return Album::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'language' => 'en',
            'status' => $published ? 1 : 0,
        ]);
    }

    private function photo(string $name, ?Album $album, int $priority): Gallery
    {
        return Gallery::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'description' => $name,
            'type' => 'gallery',
            'path' => Str::slug($name) . '.jpg',
            'language' => 'en',
            'album_id' => $album?->id,
            'order_by' => $priority,
            'status' => 1,
        ]);
    }

    private function builderContentOptions(): array
    {
        $method = new ReflectionMethod(PageBuilderController::class, 'blockContentOptions');

        return $method->invoke(app(PageBuilderController::class), 'en');
    }

    private function publicMenuUuids()
    {
        return MyMenu::frontMenus('en')
            ->flatMap(function (PageMenu $root) {
                return collect([$root->uuid])
                    ->concat($root->children->flatMap(fn (PageMenu $child) => [
                        $child->uuid,
                        ...$child->children->pluck('uuid')->all(),
                    ]));
            });
    }

    private function inertiaHeaders(): array
    {
        $manifest = public_path('build/manifest.json');

        return array_filter([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : null,
        ]);
    }

    private function adminLanguage(): object
    {
        return (object) ['Common' => (object) ['Form' => (object) [
            'DeleteSuccessfully' => 'Deleted',
            'NotDelete' => 'Delete failed',
        ]]];
    }
}
