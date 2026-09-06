<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Role;
use App\Models\MenuAction;
use App\Services\PageRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegacyPageSafetyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_builder_section_requires_confirmation_and_preserves_legacy_article_as_rich_text(): void
    {
        $admin = $this->adminWith(['page.builder.create', 'page.builder.edit']);
        $page = $this->page([
            'description' => '<h2>Community history</h2><p>Existing article copy.</p><script>alert(1)</script>',
        ]);

        $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.store', $page->uuid),
            $this->withVersion($page, ['locale' => 'en', 'type' => 'cards'])
        )->assertUnprocessable()
            ->assertJsonValidationErrors('convert_legacy_content');

        $this->assertSame(0, $page->blocks()->count());
        $this->assertSame(0, $page->revisions()->count());
        $this->assertStringContainsString('Existing article copy.', (string) $page->fresh()->description);

        $response = $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.block.store', $page->uuid),
            $this->withVersion($page, [
                'locale' => 'en',
                'type' => 'cards',
                'convert_legacy_content' => true,
            ])
        )->assertCreated()
            ->assertJsonPath('converted_legacy_block.type', 'rich_text')
            ->assertJsonPath('converted_legacy_block.label', 'Existing page content')
            ->assertJsonPath('block.type', 'cards');

        $blocks = $page->fresh()->blocks()->orderBy('sort_order')->get();
        $this->assertCount(2, $blocks);
        $this->assertSame('rich_text', $blocks[0]->type);
        $this->assertTrue((bool) $blocks[0]->is_enabled);
        $this->assertSame($page->uuid, $blocks[0]->translation_key);
        $this->assertStringContainsString('<h2>Community history</h2>', $blocks[0]->content['body']);
        $this->assertStringContainsString('Existing article copy.', $blocks[0]->content['body']);
        $this->assertStringNotContainsString('<script', $blocks[0]->content['body']);
        $this->assertSame('cards', $blocks[1]->type);
        $this->assertSame('', (string) $page->fresh()->description);

        $revision = $page->revisions()->sole();
        $this->assertStringContainsString('Existing article copy.', data_get($revision->snapshot, 'page.description'));
        $this->assertSame([], data_get($revision->snapshot, 'blocks'));
        $this->assertStringContainsString('safely converted', (string) $response->json('message'));
    }

    public function test_enabling_an_existing_draft_section_cannot_hide_legacy_content_without_conversion(): void
    {
        $admin = $this->adminWith(['page.builder.edit']);
        $page = $this->page(['description' => '<p>Do not lose this article.</p>']);
        $draft = PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'type' => 'rich_text',
            'label' => 'Draft section',
            'content' => ['heading' => 'Draft', 'body' => '<p>New copy.</p>'],
            'settings' => [],
            'sort_order' => 0,
            'is_enabled' => false,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
        $payload = [
            'locale' => 'en',
            'label' => $draft->label,
            'content' => $draft->content,
            'settings' => [],
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ];

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $draft->uuid]),
            $this->withVersion($page, $payload)
        )->assertUnprocessable()
            ->assertJsonValidationErrors('convert_legacy_content');

        $this->assertFalse((bool) $draft->fresh()->is_enabled);
        $this->assertStringContainsString('Do not lose this article.', (string) $page->fresh()->description);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $draft->uuid]),
            $this->withVersion($page, $payload + ['convert_legacy_content' => true])
        )->assertOk()
            ->assertJsonPath('converted_legacy_block.type', 'rich_text');

        $this->assertTrue((bool) $draft->fresh()->is_enabled);
        $this->assertSame(2, $page->blocks()->where('is_enabled', true)->count());
        $orderedBlocks = $page->blocks()->orderBy('sort_order')->get();
        $this->assertSame('Existing page content', $orderedBlocks[0]->label);
        $this->assertSame(0, $orderedBlocks[0]->sort_order);
        $this->assertSame('Draft section', $orderedBlocks[1]->label);
        $this->assertSame(1, $orderedBlocks[1]->sort_order);
        $converted = $page->blocks()->where('label', 'Existing page content')->firstOrFail();
        $this->assertStringContainsString(
            'Do not lose this article.',
            (string) $converted->content['body']
        );
    }

    public function test_simple_and_advanced_editors_explain_conversion_and_lock_required_page_status(): void
    {
        $admin = $this->adminWith(['page.builder.create', 'page.builder.edit', 'page.status']);
        $home = $this->page([
            'name' => 'Homepage',
            'slug' => 'home',
            'description' => '<p>Legacy homepage copy.</p>',
        ]);

        foreach ([null, 'advanced'] as $mode) {
            $response = $this->actingAs($admin, 'admin')->get(route('page.builder.edit', [
                'uuid' => $home->uuid,
                'locale' => 'en',
                'mode' => $mode,
            ]));

            $response->assertOk()
                ->assertSee('Your existing article is protected.')
                ->assertSee('Published — required website page')
                ->assertSee('Nothing will be discarded.')
                ->assertSee('convert_legacy_content', false);
        }
    }

    public function test_home_about_and_zakat_logical_pages_cannot_be_unpublished_or_trashed_but_content_remains_editable(): void
    {
        $admin = $this->adminWith([
            'page.builder.edit',
            'page.status',
            'page.destroy',
            'page.trash.destroy',
        ]);
        $pages = collect(['home', 'about-us', 'zakat'])->map(function (string $slug): Page {
            $source = $this->page(['name' => Str::headline($slug), 'slug' => $slug]);
            $this->page([
                'uuid' => $source->uuid,
                'name' => 'বাংলা ' . $source->name,
                'slug' => $slug,
                'language' => 'bn',
            ]);

            return $source;
        });

        foreach ($pages as $page) {
            $this->actingAs($admin, 'admin')
                ->withHeader('X-Requested-With', 'XMLHttpRequest')
                ->putJson(route('page.status', $page->uuid))
                ->assertUnprocessable()
                ->assertJsonPath('code', 'required_system_page');

            $this->actingAs($admin, 'admin')
                ->deleteJson(route('page.destroy', $page->uuid))
                ->assertUnprocessable()
                ->assertJsonPath('code', 'required_system_page');

            $this->assertSame(2, Page::where('uuid', $page->uuid)->count());
            $this->assertSame(2, Page::where('uuid', $page->uuid)->where('status', true)->count());
        }

        $this->actingAs($admin, 'admin')->deleteJson(route('page.bulk.destroy'), [
            'page_ids' => $pages->pluck('id')->all(),
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'required_system_page')
            ->assertJsonCount(3, 'protected_pages');

        $zakat = $pages->last();
        Page::where('uuid', $zakat->uuid)->delete();
        $this->actingAs($admin, 'admin')
            ->deleteJson(route('page.trash.force-destroy', $zakat->uuid))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'required_system_page');
        $this->assertSame(2, Page::onlyTrashed()->where('uuid', $zakat->uuid)->count());

        $home = $pages->first();
        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.simple.save', $home->uuid),
            $this->withVersion($home, [
                'locale' => 'en',
                'page' => ['name' => 'Updated homepage', 'publication_status' => 'draft'],
            ])
        )->assertUnprocessable()
            ->assertJsonValidationErrors('publication_status');

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.simple.save', $home->uuid),
            $this->withVersion($home, [
                'locale' => 'en',
                'page' => ['name' => 'Updated homepage', 'publication_status' => 'published'],
            ])
        )->assertOk();

        $this->assertSame('Updated homepage', $home->fresh()->name);
        $this->assertTrue((bool) $home->fresh()->status);
        $this->assertSame('published', $home->fresh()->publication_status);
    }

    public function test_required_page_revision_that_would_take_the_route_offline_is_rejected(): void
    {
        $admin = $this->adminWith([
            'page.builder.edit',
            'page.status',
            'seo.metadata.edit',
            'reusable-blocks.edit',
        ]);
        $this->actingAs($admin, 'admin');
        $home = $this->page([
            'slug' => 'home',
            'status' => false,
            // Older data can contain a contradictory publication label. The
            // active route flag must still prevent this revision being used.
            'publication_status' => 'published',
        ]);
        $revision = app(PageRevisionService::class)->capture($home, 'Offline historical version');
        $home->update(['status' => true, 'publication_status' => 'published']);

        $this->postJson(
            route('page.builder.revision.restore', [$home->uuid, $revision->uuid]),
            $this->withVersion($home, [
                'locale' => 'en',
                'expected_reusable_versions' => [],
            ])
        )->assertUnprocessable()
            ->assertJsonValidationErrors('revision');

        $this->assertTrue((bool) $home->fresh()->status);
        $this->assertSame('published', $home->fresh()->publication_status);
    }

    private function page(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Legacy safety page',
            'sub_title' => '',
            'slug' => 'legacy-safety-' . Str::lower(Str::random(8)),
            'status' => true,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
        ], $overrides));
    }

    private function withVersion(Page $page, array $payload): array
    {
        return ['expected_version' => (int) $page->fresh()->editor_version] + $payload;
    }

    private function adminWith(array $links): Admin
    {
        $role = Role::create([
            'name' => 'Legacy safety editor ' . Str::random(5),
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $actions = MenuAction::whereIn('link', $links)->get();
        $this->assertSame(count($links), $actions->count(), 'A required admin action is missing.');
        $role->update(['actionPermission' => $actions->pluck('id')->implode(',')]);

        return Admin::create([
            'name' => 'Legacy Safety Editor',
            'username' => 'legacy-safety-' . Str::lower(Str::random(6)),
            'email' => 'legacy-safety-' . Str::lower(Str::random(8)) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
