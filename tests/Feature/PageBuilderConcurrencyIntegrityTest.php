<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageRevision;
use App\Models\ReusableBlock;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PageBuilderConcurrencyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const CONFLICT_MESSAGE = 'This page changed in another editor. Your unsaved work is still here; reload the page, review the latest version, and apply your changes again.';

    private const SHARED_CONFLICT_MESSAGE = 'A reusable section changed in another editor. Your unsaved work is still here; reload the page, review the latest shared version, and apply your changes again.';

    public function test_editor_get_never_pairs_an_old_page_snapshot_with_a_newer_generation(): void
    {
        $admin = $this->authorizedAdmin();
        $page = $this->page();
        $block = $this->block($page, 'Snapshot A section');
        $injectedConcurrentWrite = false;

        // Query listeners run after PDO has returned the selected rows but
        // before Eloquent hydrates them. This deterministically lands version
        // B in the exact window that formerly sat between loading display
        // state A and issuing a separate MAX(editor_version) token query.
        DB::listen(function ($query) use ($page, &$injectedConcurrentWrite): void {
            if ($injectedConcurrentWrite) {
                return;
            }

            $sql = strtolower(ltrim((string) $query->sql));
            $readsLogicalPage = str_starts_with($sql, 'select')
                && preg_match('/\bfrom\s+[`"]?pages[`"]?\b/', $sql) === 1
                && in_array($page->uuid, $query->bindings, true);
            if (!$readsLogicalPage) {
                return;
            }

            $injectedConcurrentWrite = true;
            DB::table('pages')->where('id', $page->id)->update([
                'name' => 'Concurrent version B title',
                'editor_version' => 5,
            ]);
        });

        $response = $this->actingAs($admin, 'admin')->get(route('page.builder.edit', [
            'uuid' => $page->uuid,
            'locale' => 'en',
        ]));

        $response->assertOk()
            ->assertSee('id="simple-page-name" maxlength="255" value="Concurrency test page"', false)
            ->assertSee('let editorVersion = 0;', false);
        $this->assertTrue($injectedConcurrentWrite);
        $this->assertSame('Concurrent version B title', $page->fresh()->name);
        $this->assertSame(5, (int) $page->fresh()->editor_version);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block->uuid]),
            [
                'locale' => 'en',
                'expected_version' => 0,
                'content' => ['heading' => 'Stale snapshot overwrite'],
            ]
        )->assertStatus(409)->assertJsonPath('message', self::CONFLICT_MESSAGE);
        $this->assertSame('Snapshot A section', $block->fresh()->content['heading']);
    }

    public function test_editor_get_never_pairs_old_shared_content_with_a_newer_revision_restore_token(): void
    {
        $admin = $this->authorizedAdmin(true);
        $page = $this->page();
        $reusable = $this->reusableBlock('Shared snapshot A');
        $this->sharedBlock($page, $reusable);
        $restorePoint = app(\App\Services\PageRevisionService::class)
            ->capture($page, 'Shared snapshot A restore point');
        $injectedConcurrentWrite = false;

        // Land shared version B after PDO has returned the relation rows for
        // display. A second token read would see B and wrongly authorize a
        // restore of snapshot A; one locked result must supply both values.
        DB::listen(function ($query) use ($reusable, &$injectedConcurrentWrite): void {
            if ($injectedConcurrentWrite) {
                return;
            }

            $sql = strtolower(ltrim((string) $query->sql));
            $readsReusableRows = str_starts_with($sql, 'select')
                && preg_match('/\bselect\s+\*\s+from\s+[`"]?reusable_blocks[`"]?\b/', $sql) === 1;
            if (!$readsReusableRows) {
                return;
            }

            $injectedConcurrentWrite = true;
            DB::table('reusable_blocks')->where('id', $reusable->id)->update([
                'content' => json_encode(['heading' => 'Concurrent shared snapshot B'], JSON_THROW_ON_ERROR),
                'editor_version' => 5,
            ]);
        });

        $response = $this->actingAs($admin, 'admin')->get(route('page.builder.edit', [
            'uuid' => $page->uuid,
            'locale' => 'en',
            'mode' => 'advanced',
        ]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertSame(1, preg_match(
            '/const revisionReusableVersions = (.+?);\R/',
            $html,
            $versionMatch
        ));
        $this->assertSame(1, preg_match(
            '/const state = \{ blocks: (.+?), selected: /',
            $html,
            $blockMatch
        ));
        $revisionVersions = json_decode($versionMatch[1], true, 512, JSON_THROW_ON_ERROR);
        $displayedBlocks = json_decode($blockMatch[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue($injectedConcurrentWrite);
        $this->assertSame('Shared snapshot A', data_get($displayedBlocks, '0.content.heading'));
        $this->assertSame(0, $revisionVersions[$restorePoint->uuid][$reusable->uuid]);
        $this->assertSame('Concurrent shared snapshot B', $reusable->fresh()->content['heading']);
        $this->assertSame(5, (int) $reusable->fresh()->editor_version);

        $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.revision.restore', [$page->uuid, $restorePoint->uuid]),
            [
                'locale' => 'en',
                'expected_version' => 0,
                'expected_reusable_versions' => $revisionVersions[$restorePoint->uuid],
            ]
        )->assertStatus(409)->assertJsonPath('message', self::SHARED_CONFLICT_MESSAGE);
        $this->assertSame('Concurrent shared snapshot B', $reusable->fresh()->content['heading']);
        $this->assertSame(5, (int) $reusable->fresh()->editor_version);
    }

    public function test_editor_version_is_required_and_a_stale_write_cannot_change_content_or_create_a_revision(): void
    {
        $admin = $this->authorizedAdmin();
        $page = $this->page();
        $block = $this->block($page, 'Original copy');
        $route = route('page.builder.block.update', [$page->uuid, $block->uuid]);

        $this->actingAs($admin, 'admin')->putJson($route, [
            'locale' => 'en',
            'content' => ['heading' => 'Missing version must fail'],
        ])->assertUnprocessable()->assertJsonValidationErrors('expected_version');

        $this->assertSame(0, (int) $page->fresh()->editor_version);
        $this->assertSame('Original copy', $block->fresh()->content['heading']);
        $this->assertDatabaseCount('page_revisions', 0);

        $first = $this->actingAs($admin, 'admin')->putJson($route, [
            'locale' => 'en',
            'expected_version' => 0,
            'content' => ['heading' => 'First editor won'],
        ])->assertOk()->assertJsonPath('editor_version', 1);

        $this->assertSame(1, (int) $first->json('editor_version'));
        $this->assertSame(1, (int) $page->fresh()->editor_version);
        $this->assertSame('First editor won', $block->fresh()->content['heading']);
        $this->assertSame([1], $this->revisionNumbers($page));
        $this->assertSame('Original copy', data_get($page->revisions()->firstOrFail()->snapshot, 'blocks.0.content.heading'));

        $this->actingAs($admin, 'admin')->putJson($route, [
            'locale' => 'en',
            'expected_version' => 0,
            'content' => ['heading' => 'Stale editor overwrite'],
        ])->assertStatus(409)->assertJsonPath('message', self::CONFLICT_MESSAGE);

        $this->assertSame(1, (int) $page->fresh()->editor_version);
        $this->assertSame('First editor won', $block->fresh()->content['heading']);
        $this->assertSame([1], $this->revisionNumbers($page));

        $this->actingAs($admin, 'admin')->putJson($route, [
            'locale' => 'en',
            'expected_version' => 1,
            'content' => ['heading' => 'Current editor saved'],
        ])->assertOk()->assertJsonPath('editor_version', 2);

        $this->assertSame(2, (int) $page->fresh()->editor_version);
        $this->assertSame('Current editor saved', $block->fresh()->content['heading']);
        $this->assertSame([1, 2], $this->revisionNumbers($page));
    }

    public function test_logical_page_locale_identity_is_indexed_and_cannot_be_duplicated(): void
    {
        $page = $this->page();
        $this->assertTrue(Schema::hasIndex('pages', 'pages_uuid_language_unique'));

        $this->expectException(QueryException::class);
        Page::create([
            'uuid' => $page->uuid,
            'name' => 'Duplicate logical locale',
            'sub_title' => 'Must be rejected',
            'slug' => 'duplicate-' . Str::lower(Str::random(8)),
            'status' => 0,
            'language' => $page->language,
        ]);
    }

    public function test_legacy_page_editor_carries_the_logical_version_and_cannot_overwrite_a_newer_builder_save(): void
    {
        $admin = $this->authorizedAdmin(false, false, true);
        $page = $this->page();
        $block = $this->block($page, 'Original builder copy');

        $this->actingAs($admin, 'admin')
            ->get(route('page.edit', $page->uuid))
            ->assertOk()
            ->assertSee('name="expected_version" type="hidden" value="0"', false);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$page->uuid, $block->uuid]),
            [
                'locale' => 'en',
                'expected_version' => 0,
                'content' => ['heading' => 'Builder saved first'],
            ]
        )->assertOk()->assertJsonPath('editor_version', 1);

        $stalePayload = [
            'uuid' => $page->uuid,
            'expected_version' => 0,
            'language' => ['en'],
            'name' => ['en' => 'Stale legacy overwrite'],
            'sub_title' => ['en' => 'Stale subtitle'],
            'description' => ['en' => '<p>Stale description</p>'],
            'published_at' => ['en' => now()->toDateString()],
        ];

        $this->actingAs($admin, 'admin')
            ->put(route('page.update'), $stalePayload)
            ->assertStatus(409);

        $this->assertSame('Concurrency test page', $page->fresh()->name);
        $this->assertSame(1, (int) $page->fresh()->editor_version);
        $this->assertSame('Builder saved first', $block->fresh()->content['heading']);

        $this->actingAs($admin, 'admin')
            ->put(route('page.update'), array_replace($stalePayload, [
                'expected_version' => 1,
                'name' => ['en' => 'Current legacy save'],
            ]))
            ->assertRedirect(route('page.index'));

        $this->assertSame('Current legacy save', $page->fresh()->name);
        $this->assertSame(2, (int) $page->fresh()->editor_version);
    }

    public function test_restore_advances_the_version_keeps_revision_numbers_sequential_and_invalidates_pre_restore_editors(): void
    {
        $admin = $this->authorizedAdmin(true);
        $page = $this->page();
        $block = $this->block($page, 'Original copy');
        $route = route('page.builder.block.update', [$page->uuid, $block->uuid]);

        $this->actingAs($admin, 'admin')->putJson($route, [
            'locale' => 'en',
            'expected_version' => 0,
            'content' => ['heading' => 'First change'],
        ])->assertOk()->assertJsonPath('editor_version', 1);

        $this->actingAs($admin, 'admin')->putJson($route, [
            'locale' => 'en',
            'expected_version' => 1,
            'content' => ['heading' => 'Second change'],
        ])->assertOk()->assertJsonPath('editor_version', 2);

        $restorePoint = PageRevision::query()
            ->where('page_id', $page->id)
            ->where('revision', 1)
            ->firstOrFail();

        $this->assertSame('Original copy', data_get($restorePoint->snapshot, 'blocks.0.content.heading'));
        $this->assertSame([1, 2], $this->revisionNumbers($page));

        $this->actingAs($admin, 'admin')->postJson(
            route('page.builder.revision.restore', [$page->uuid, $restorePoint->uuid]),
            ['locale' => 'en', 'expected_version' => 2]
        )->assertOk()
            ->assertJsonPath('message', 'Revision restored.')
            ->assertJsonPath('editor_version', 3);

        $restoredBlock = PageBlock::query()->where('page_id', $page->id)->where('uuid', $block->uuid)->firstOrFail();
        $backup = PageRevision::query()->where('page_id', $page->id)->where('revision', 3)->firstOrFail();
        $this->assertSame(3, (int) $page->fresh()->editor_version);
        $this->assertSame('Original copy', $restoredBlock->content['heading']);
        $this->assertSame('Second change', data_get($backup->snapshot, 'blocks.0.content.heading'));
        $this->assertSame('Automatic backup before restoring revision 1', $backup->note);
        $this->assertSame([1, 2, 3], $this->revisionNumbers($page));

        $restoredRoute = route('page.builder.block.update', [$page->uuid, $restoredBlock->uuid]);
        $this->actingAs($admin, 'admin')->putJson($restoredRoute, [
            'locale' => 'en',
            'expected_version' => 2,
            'content' => ['heading' => 'Pre-restore editor overwrite'],
        ])->assertStatus(409)->assertJsonPath('message', self::CONFLICT_MESSAGE);

        $this->assertSame(3, (int) $page->fresh()->editor_version);
        $this->assertSame('Original copy', $restoredBlock->fresh()->content['heading']);
        $this->assertSame([1, 2, 3], $this->revisionNumbers($page));

        $this->actingAs($admin, 'admin')->putJson($restoredRoute, [
            'locale' => 'en',
            'expected_version' => 3,
            'content' => ['heading' => 'Post-restore current edit'],
        ])->assertOk()->assertJsonPath('editor_version', 4);

        $this->assertSame(4, (int) $page->fresh()->editor_version);
        $this->assertSame('Post-restore current edit', $restoredBlock->fresh()->content['heading']);
        $this->assertSame([1, 2, 3, 4], $this->revisionNumbers($page));
    }

    public function test_shared_section_uses_its_own_version_across_different_pages(): void
    {
        $admin = $this->authorizedAdmin(true);
        $reusable = $this->reusableBlock('Original shared copy');
        $pageA = $this->page();
        $pageB = $this->page();
        $blockA = $this->sharedBlock($pageA, $reusable);
        $blockB = $this->sharedBlock($pageB, $reusable);
        $blockB->update(['label' => 'Obsolete copied label']);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$pageA->uuid, $blockA->uuid]),
            [
                'locale' => 'en',
                'expected_version' => 0,
                'content' => ['heading' => 'Missing shared token'],
            ]
        )->assertStatus(409)->assertJsonPath('message', self::SHARED_CONFLICT_MESSAGE);

        $this->assertSame('Original shared copy', $reusable->fresh()->content['heading']);
        $this->assertSame(0, $pageA->revisions()->count());

        $first = $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$pageA->uuid, $blockA->uuid]),
            [
                'locale' => 'en',
                'expected_version' => 0,
                'expected_reusable_version' => 0,
                'content' => ['heading' => 'Page A saved first'],
            ]
        )->assertOk()
            ->assertJsonPath('block.reusable_version', 1)
            ->assertJsonPath('editor_version', 1);

        $this->assertSame(1, (int) $first->json('block.reusable_version'));
        $this->assertSame(1, (int) $reusable->fresh()->editor_version);
        $this->assertSame('Page A saved first', $reusable->fresh()->content['heading']);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$pageB->uuid, $blockB->uuid]),
            [
                'locale' => 'en',
                'expected_version' => 0,
                'expected_reusable_version' => 0,
                'content' => ['heading' => 'Stale Page B overwrite'],
            ]
        )->assertStatus(409)->assertJsonPath('message', self::SHARED_CONFLICT_MESSAGE);

        $this->assertSame(0, (int) $pageB->fresh()->editor_version);
        $this->assertSame(0, $pageB->revisions()->count());
        $this->assertSame(1, (int) $reusable->fresh()->editor_version);
        $this->assertSame('Page A saved first', $reusable->fresh()->content['heading']);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$pageB->uuid, $blockB->uuid]),
            [
                'locale' => 'en',
                'expected_version' => 0,
                'expected_reusable_version' => 1,
                'content' => ['heading' => 'Page B reviewed and saved'],
            ]
        )->assertOk()
            ->assertJsonPath('block.label', 'Shared concurrency section')
            ->assertJsonPath('block.reusable_version', 2)
            ->assertJsonPath('editor_version', 1);

        $this->assertSame(2, (int) $reusable->fresh()->editor_version);
        $this->assertSame('Shared concurrency section', $reusable->fresh()->name);
        $this->assertSame('Page B reviewed and saved', $reusable->fresh()->content['heading']);
    }

    public function test_revision_restore_checks_and_advances_shared_version_across_pages(): void
    {
        $admin = $this->authorizedAdmin(true);
        $reusable = $this->reusableBlock('Revision shared copy');
        $pageA = $this->page();
        $pageB = $this->page();
        $blockA = $this->sharedBlock($pageA, $reusable);
        $blockB = $this->sharedBlock($pageB, $reusable);
        $restorePoint = app(\App\Services\PageRevisionService::class)
            ->capture($pageA, 'Shared restore point');

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$pageB->uuid, $blockB->uuid]),
            [
                'locale' => 'en',
                'expected_version' => 0,
                'expected_reusable_version' => 0,
                'content' => ['heading' => 'Page B newer shared copy'],
            ]
        )->assertOk()->assertJsonPath('block.reusable_version', 1);

        $restoreRoute = route('page.builder.revision.restore', [$pageA->uuid, $restorePoint->uuid]);
        $this->actingAs($admin, 'admin')->postJson($restoreRoute, [
            'locale' => 'en',
            'expected_version' => 0,
            'expected_reusable_versions' => [$reusable->uuid => 0],
        ])->assertStatus(409)->assertJsonPath('message', self::SHARED_CONFLICT_MESSAGE);

        $this->assertSame(0, (int) $pageA->fresh()->editor_version);
        $this->assertSame([1], $this->revisionNumbers($pageA));
        $this->assertSame('Page B newer shared copy', $reusable->fresh()->content['heading']);

        $this->actingAs($admin, 'admin')->postJson($restoreRoute, [
            'locale' => 'en',
            'expected_version' => 0,
            'expected_reusable_versions' => [$reusable->uuid => 1],
        ])->assertOk()
            ->assertJsonPath('editor_version', 1);

        $this->assertSame(2, (int) $reusable->fresh()->editor_version);
        $this->assertSame('Revision shared copy', $reusable->fresh()->content['heading']);

        $restoredBlockA = PageBlock::query()
            ->where('page_id', $pageA->id)
            ->where('uuid', $blockA->uuid)
            ->firstOrFail();
        $this->assertSame($reusable->id, $restoredBlockA->reusable_block_id);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$pageB->uuid, $blockB->uuid]),
            [
                'locale' => 'en',
                'expected_version' => 1,
                'expected_reusable_version' => 1,
                'content' => ['heading' => 'Pre-restore Page B overwrite'],
            ]
        )->assertStatus(409)->assertJsonPath('message', self::SHARED_CONFLICT_MESSAGE);

        $this->assertSame('Revision shared copy', $reusable->fresh()->content['heading']);
        $this->assertSame(2, (int) $reusable->fresh()->editor_version);
    }

    public function test_simple_editor_bulk_save_enforces_the_shared_version(): void
    {
        $admin = $this->authorizedAdmin(true);
        $reusable = $this->reusableBlock('Simple editor original');
        $pageA = $this->page();
        $pageB = $this->page();
        $blockA = $this->sharedBlock($pageA, $reusable);
        $blockB = $this->sharedBlock($pageB, $reusable);

        $this->actingAs($admin, 'admin')->putJson(route('page.builder.simple.save', $pageA->uuid), [
            'locale' => 'en',
            'expected_version' => 0,
            'blocks' => [[
                'uuid' => $blockA->uuid,
                'label' => $reusable->name,
                'content' => ['heading' => 'Simple Page A saved'],
                'is_enabled' => true,
                'expected_reusable_version' => 0,
            ]],
        ])->assertOk()
            ->assertJsonPath('blocks.0.reusable_version', 1)
            ->assertJsonPath('editor_version', 1);

        $this->actingAs($admin, 'admin')->putJson(route('page.builder.simple.save', $pageB->uuid), [
            'locale' => 'en',
            'expected_version' => 0,
            'blocks' => [[
                'uuid' => $blockB->uuid,
                'label' => $reusable->name,
                'content' => ['heading' => 'Simple stale overwrite'],
                'is_enabled' => true,
                'expected_reusable_version' => 0,
            ]],
        ])->assertStatus(409)->assertJsonPath('message', self::SHARED_CONFLICT_MESSAGE);

        $this->assertSame('Simple Page A saved', $reusable->fresh()->content['heading']);
        $this->assertSame(1, (int) $reusable->fresh()->editor_version);
        $this->assertSame(0, (int) $pageB->fresh()->editor_version);
        $this->assertSame(0, $pageB->revisions()->count());
    }

    public function test_library_update_invalidates_every_page_and_rejects_a_stale_library_writer(): void
    {
        $admin = $this->authorizedAdmin(true);
        $reusable = $this->reusableBlock('Original library copy');
        $pageA = $this->page();
        $pageB = $this->page();
        $blockA = $this->sharedBlock($pageA, $reusable);
        $this->sharedBlock($pageB, $reusable);
        $route = route('reusable-blocks.update', $reusable);
        $payload = [
            'expected_version' => 0,
            'name' => 'Updated library name',
            'locale' => 'en',
            'content' => ['heading' => 'Updated library copy'],
            'settings' => [],
            'is_enabled' => true,
        ];

        $this->actingAs($admin, 'admin')->putJson($route, $payload)
            ->assertOk()
            ->assertJsonPath('block.editor_version', 1);

        $this->assertSame(1, (int) $pageA->fresh()->editor_version);
        $this->assertSame(1, (int) $pageB->fresh()->editor_version);
        $this->assertSame('Updated library copy', $reusable->fresh()->content['heading']);

        $this->actingAs($admin, 'admin')->putJson($route, $payload + [
            'content' => ['heading' => 'Stale library overwrite'],
        ])->assertStatus(409)->assertJsonPath('message', self::SHARED_CONFLICT_MESSAGE);

        $this->assertSame(1, (int) $pageA->fresh()->editor_version);
        $this->assertSame(1, (int) $pageB->fresh()->editor_version);
        $this->assertSame(1, (int) $reusable->fresh()->editor_version);
        $this->assertSame('Updated library copy', $reusable->fresh()->content['heading']);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$pageA->uuid, $blockA->uuid]),
            [
                'locale' => 'en',
                'expected_version' => 0,
                'expected_reusable_version' => 0,
                'content' => ['heading' => 'Stale page overwrite'],
            ]
        )->assertStatus(409)->assertJsonPath('message', self::CONFLICT_MESSAGE);
    }

    public function test_library_delete_detaches_consistent_copies_and_invalidates_open_page_editors(): void
    {
        $admin = $this->authorizedAdmin(true, true);
        $reusable = $this->reusableBlock('Copy retained after detach');
        $pageA = $this->page();
        $pageB = $this->page();
        $blockA = $this->sharedBlock($pageA, $reusable);
        $blockB = $this->sharedBlock($pageB, $reusable);
        $blockB->update(['label' => 'Stale copied name']);

        $this->actingAs($admin, 'admin')->deleteJson(
            route('reusable-blocks.destroy', $reusable),
            ['expected_version' => 0]
        )->assertOk();

        $trashed = ReusableBlock::onlyTrashed()->whereKey($reusable->id)->firstOrFail();
        $this->assertSame(1, (int) $trashed->editor_version);
        $this->assertSame(1, (int) $pageA->fresh()->editor_version);
        $this->assertSame(1, (int) $pageB->fresh()->editor_version);
        foreach ([$blockA->fresh(), $blockB->fresh()] as $detached) {
            $this->assertNull($detached->reusable_block_id);
            $this->assertSame('Shared concurrency section', $detached->label);
            $this->assertSame('Copy retained after detach', $detached->content['heading']);
        }

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$pageA->uuid, $blockA->uuid]),
            [
                'locale' => 'en',
                'expected_version' => 0,
                'content' => ['heading' => 'Stale page after library delete'],
            ]
        )->assertStatus(409)->assertJsonPath('message', self::CONFLICT_MESSAGE);

        $this->actingAs($admin, 'admin')->postJson(
            route('reusable-blocks.restore', $reusable->uuid),
            ['expected_version' => 1]
        )->assertOk()->assertJsonPath('block.editor_version', 2);

        $this->assertSame(2, (int) $reusable->fresh()->editor_version);
    }

    public function test_every_library_mutation_preserves_page_reusable_instance_lock_order(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/ReusableBlockController.php'));

        foreach ([
            'update' => ['public function destroy(', '$this->pageVersions->advanceMany'],
            'destroy' => ['public function restore(', '$this->pageVersions->advanceMany'],
            'restore' => ['public function forceDestroy(', '$this->pageVersions->advanceMany'],
            'forceDestroy' => ['private function affectedPageUuids(', '$this->pageVersions->lockForMutation'],
        ] as $method => [$nextMethodMarker, $pageLockCall]) {
            $start = strpos($source, "public function {$method}(");
            $end = strpos($source, $nextMethodMarker, $start + 1);
            $this->assertNotFalse($start);
            $this->assertNotFalse($end);
            $body = substr($source, $start, $end - $start);

            $pageLock = strpos($body, $pageLockCall);
            $reusableLock = strpos($body, '->lockForUpdate()');
            $instanceLock = strpos($body, '$this->lockInstances');
            $affectedPageRevalidation = strpos($body, '$this->assertAffectedPagesUnchanged');

            $this->assertNotFalse($pageLock, "{$method} must lock affected logical Pages first.");
            $this->assertNotFalse($reusableLock, "{$method} must lock and revalidate the reusable row.");
            $this->assertNotFalse($instanceLock, "{$method} must lock PageBlock instances.");
            $this->assertNotFalse($affectedPageRevalidation, "{$method} must reject a newly attached Page.");
            $this->assertTrue(
                $pageLock < $reusableLock
                    && $reusableLock < $instanceLock
                    && $instanceLock < $affectedPageRevalidation,
                "{$method} must lock Page -> ReusableBlock -> PageBlock and then revalidate affected Pages."
            );
        }
    }

    public function test_inverse_shared_targets_use_the_same_complete_globally_sorted_reusable_union(): void
    {
        $admin = $this->authorizedAdmin(true);
        $reusableOne = $this->reusableBlock('Reusable one original');
        $reusableTwo = $this->reusableBlock('Reusable two original');
        $pageA = $this->page();
        $pageB = $this->page();

        // Deliberately create the placements in inverse target order. Each
        // logical Page must still pre-lock the identical R1,R2 union by global
        // primary-key order before touching its own PageBlock rows.
        $pageATargetTwo = $this->sharedBlock($pageA, $reusableTwo);
        $pageATargetOne = $this->sharedBlock($pageA, $reusableOne);
        $pageBTargetOne = $this->sharedBlock($pageB, $reusableOne);
        $pageBTargetTwo = $this->sharedBlock($pageB, $reusableTwo);
        $expectedUnion = collect([$reusableOne->id, $reusableTwo->id])
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
        $observedUnions = [];

        DB::listen(function ($query) use (&$observedUnions): void {
            $sql = strtolower((string) $query->sql);
            if (!str_starts_with(ltrim($sql), 'select')
                || preg_match('/\bfrom\s+[`"]?reusable_blocks[`"]?\b/', $sql) !== 1
                || !str_contains($sql, ' in (')
                || !str_contains($sql, 'order by')
            ) {
                return;
            }

            $observedUnions[] = collect($query->bindings)
                ->filter(fn ($binding): bool => is_numeric($binding))
                ->map(fn ($binding): int => (int) $binding)
                ->values()
                ->all();
        });

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$pageA->uuid, $pageATargetOne->uuid]),
            [
                'locale' => 'en',
                'expected_version' => 0,
                'expected_reusable_version' => 0,
                'content' => ['heading' => 'Page A changed R1'],
            ]
        )->assertOk()
            ->assertJsonPath('editor_version', 1)
            ->assertJsonPath('block.reusable_version', 1);

        $this->actingAs($admin, 'admin')->putJson(
            route('page.builder.block.update', [$pageB->uuid, $pageBTargetTwo->uuid]),
            [
                'locale' => 'en',
                'expected_version' => 0,
                'expected_reusable_version' => 0,
                'content' => ['heading' => 'Page B changed R2'],
            ]
        )->assertOk()
            ->assertJsonPath('editor_version', 1)
            ->assertJsonPath('block.reusable_version', 1);

        $matchingUnions = collect($observedUnions)
            ->filter(fn (array $ids): bool => $ids === $expectedUnion)
            ->values();
        $this->assertGreaterThanOrEqual(
            2,
            $matchingUnions->count(),
            'Both inverse-target writes must lock the complete reusable union in the same order.'
        );
        $this->assertSame('Page A changed R1', $reusableOne->fresh()->content['heading']);
        $this->assertSame('Page B changed R2', $reusableTwo->fresh()->content['heading']);
        $this->assertSame(1, (int) $pageA->fresh()->editor_version);
        $this->assertSame(1, (int) $pageB->fresh()->editor_version);
        $this->assertSame(1, (int) $reusableOne->fresh()->editor_version);
        $this->assertSame(1, (int) $reusableTwo->fresh()->editor_version);

        $snapshotA = $pageA->revisions()->firstOrFail()->snapshot;
        $snapshotB = $pageB->revisions()->firstOrFail()->snapshot;
        $this->assertEqualsCanonicalizing(
            [$reusableOne->uuid, $reusableTwo->uuid],
            collect(data_get($snapshotA, 'reusable_blocks', []))->pluck('uuid')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$reusableOne->uuid, $reusableTwo->uuid],
            collect(data_get($snapshotB, 'reusable_blocks', []))->pluck('uuid')->all()
        );

        // Keep the inverse fixtures live so the regression also proves neither
        // non-target placement was detached or rewritten locally.
        $this->assertSame($reusableTwo->id, $pageATargetTwo->fresh()->reusable_block_id);
        $this->assertSame($reusableOne->id, $pageBTargetOne->fresh()->reusable_block_id);
    }

    public function test_capture_reuses_a_prelocked_logical_page_union_without_expanding_it_later(): void
    {
        $page = $this->page();
        $translation = Page::create([
            'uuid' => $page->uuid,
            'name' => 'Concurrency test page Bangla',
            'sub_title' => 'Shared logical identity',
            'slug' => 'concurrency-bn-' . Str::lower(Str::random(8)),
            'status' => 1,
            'language' => 'bn',
            'publication_status' => 'published',
            'visibility' => 'public',
        ]);
        $reusableOne = $this->reusableBlock('English reusable');
        $reusableTwo = $this->reusableBlock('Bangla reusable');
        $this->sharedBlock($page, $reusableOne);
        $this->sharedBlock($translation, $reusableTwo);
        $service = app(\App\Services\PageRevisionService::class);
        $reusableQueriesDuringCapture = 0;
        $isCapturing = false;

        DB::listen(function ($query) use (&$isCapturing, &$reusableQueriesDuringCapture): void {
            if (!$isCapturing) {
                return;
            }

            if (preg_match('/\bfrom\s+[`"]?reusable_blocks[`"]?\b/i', (string) $query->sql) === 1) {
                $reusableQueriesDuringCapture++;
            }
        });

        DB::transaction(function () use (
            $service,
            $page,
            $reusableOne,
            $reusableTwo,
            &$isCapturing
        ): void {
            $locked = $service->lockReusableBlocksForPage($page);
            $this->assertSame(
                collect([$reusableOne->id, $reusableTwo->id])->sort()->values()->all(),
                $locked->keys()->values()->all()
            );

            $isCapturing = true;
            try {
                $service->capture($page, 'Prelocked union capture', $locked);
            } finally {
                $isCapturing = false;
            }
        });

        $this->assertSame(
            0,
            $reusableQueriesDuringCapture,
            'Capture must consume the already-locked collection and never acquire a later reusable lock.'
        );
        $this->assertEqualsCanonicalizing(
            [$reusableOne->uuid],
            collect(data_get($page->revisions()->firstOrFail()->snapshot, 'reusable_blocks', []))
                ->pluck('uuid')
                ->all()
        );

        try {
            DB::transaction(fn () => $service->capture(
                $page,
                'Incomplete collection must fail closed',
                collect()
            ));
            $this->fail('Capture accepted an incomplete caller-supplied reusable set.');
        } catch (\LogicException $exception) {
            $this->assertSame(
                'The locked reusable-section set is incomplete for this logical Page.',
                $exception->getMessage()
            );
        }
        $this->assertSame(1, $page->revisions()->count());
    }

    public function test_every_page_builder_mutation_route_preserves_the_canonical_lock_contract(): void
    {
        $controller = \App\Http\Controllers\Admin\PageBuilderController::class;
        $routes = [
            'page.builder.simple.save' => 'saveSimple',
            'page.builder.update' => 'updatePage',
            'page.builder.block.store' => 'storeBlock',
            'page.builder.block.update' => 'updateBlock',
            'page.builder.block.duplicate' => 'duplicateBlock',
            'page.builder.block.promote' => 'promoteBlock',
            'page.builder.reusable.attach' => 'attachReusableBlock',
            'page.builder.block.detach' => 'detachReusableBlock',
            'page.builder.block.reorder' => 'reorder',
            'page.builder.block.destroy' => 'destroyBlock',
            'page.builder.revision.restore' => 'restoreRevision',
        ];

        foreach ($routes as $routeName => $method) {
            $route = app('router')->getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "{$routeName} must remain registered.");
            $this->assertSame($method, $route->getActionMethod());

            $body = $this->methodSource($controller, $method);
            $pageLock = strpos($body, '$this->lockPageForMutation');
            $this->assertNotFalse($pageLock, "{$method} must acquire the logical Page lock.");

            if ($method === 'restoreRevision') {
                $restore = strpos($body, '$this->revisions->restore');
                $this->assertNotFalse($restore, 'Revision restore must delegate to the canonical restore service.');
                $this->assertTrue($pageLock < $restore);
                continue;
            }

            $reusableUnion = strpos($body, '$this->revisions->lockReusableBlocksForPage');
            $capture = strpos($body, '$this->revisions->capture');
            $firstPageBlockAccess = strpos($body, '$page->blocks()', $pageLock + 1);
            $this->assertNotFalse($reusableUnion, "{$method} must lock the complete reusable union.");
            $this->assertNotFalse($capture, "{$method} must capture a rollback revision.");
            $this->assertSame(
                1,
                substr_count($body, '$this->revisions->lockReusableBlocksForPage'),
                "{$method} must acquire its reusable union once, never expand it later."
            );
            $this->assertTrue($pageLock < $reusableUnion, "{$method} must lock Page before ReusableBlock.");
            if ($firstPageBlockAccess !== false) {
                $this->assertTrue(
                    $reusableUnion < $firstPageBlockAccess,
                    "{$method} must lock the complete reusable union before accessing PageBlock rows."
                );
            }
            $this->assertTrue($reusableUnion < $capture, "{$method} must capture from the prelocked union.");
            $this->assertStringContainsString(
                '$lockedReusableBlocks',
                substr($body, $capture, 500),
                "{$method} must pass the existing reusable collection into capture."
            );
        }

        $captureBody = $this->methodSource(\App\Services\PageRevisionService::class, 'capture');
        $this->assertStringContainsString('$lockedReusableBlocks ??=', $captureBody);
        $this->assertTrue(
            strpos($captureBody, '$this->lockLogicalPageRows')
                < strpos($captureBody, 'PageBlock::withTrashed()')
        );
        $this->assertTrue(
            strpos($captureBody, '$lockedReusableBlocks ??=')
                < strpos($captureBody, 'PageBlock::withTrashed()')
        );
        $this->assertStringContainsString('The locked reusable-section set is incomplete', $captureBody);

        $restoreBody = $this->methodSource(\App\Services\PageRevisionService::class, 'restore');
        $snapshotTargets = strpos($restoreBody, '$snapshotReusableIds');
        $reusableUnion = strpos($restoreBody, '$this->lockReusableBlocksForPage');
        $capture = strpos($restoreBody, '$this->capture');
        $pageBlockMutation = strpos($restoreBody, 'PageBlock::withTrashed()');
        $this->assertNotFalse($snapshotTargets);
        $this->assertNotFalse($reusableUnion);
        $this->assertNotFalse($capture);
        $this->assertNotFalse($pageBlockMutation);
        $this->assertTrue(
            $snapshotTargets < $reusableUnion
                && $reusableUnion < $capture
                && $capture < $pageBlockMutation,
            'Restore must include snapshot targets in the global union, reuse it for capture, then mutate PageBlock.'
        );
        $this->assertStringContainsString('$snapshotReusableIds', substr($restoreBody, $reusableUnion, 250));

        $attachBody = $this->methodSource($controller, 'attachReusableBlock');
        $this->assertStringContainsString('[$candidate->id]', $attachBody);
        $this->assertTrue(
            strpos($attachBody, '[$candidate->id]')
                < strpos($attachBody, '$this->revisions->capture')
        );

        $pageUnionBody = $this->methodSource(
            \App\Services\PageRevisionService::class,
            'lockReusableBlocksForPage'
        );
        $this->assertTrue(
            strpos($pageUnionBody, '$this->lockLogicalPageRows')
                < strpos($pageUnionBody, '$this->lockReusableBlocksForPageIds')
        );
        $this->assertStringContainsString('$logicalPages->pluck', $pageUnionBody);
        $this->assertStringContainsString('$additionalIds', $pageUnionBody);

        $pageIdsUnionBody = $this->methodSource(
            \App\Services\PageRevisionService::class,
            'lockReusableBlocksForPageIds'
        );
        $this->assertStringContainsString('PageBlock::withTrashed()', $pageIdsUnionBody);
        $this->assertStringContainsString("->whereIn('page_id', \$pageIds)", $pageIdsUnionBody);
        $this->assertStringContainsString('$referencedIds->merge(collect($additionalIds))', $pageIdsUnionBody);

        $unionBody = $this->methodSource(\App\Services\PageRevisionService::class, 'lockReusableBlocks');
        $this->assertTrue(strpos($unionBody, '->unique()') < strpos($unionBody, '->sort()'));
        $this->assertTrue(strpos($unionBody, '->sort()') < strpos($unionBody, "->orderBy('id')"));
        $this->assertTrue(strpos($unionBody, "->orderBy('id')") < strpos($unionBody, '->lockForUpdate()'));
    }

    /** @return list<int> */
    private function revisionNumbers(Page $page): array
    {
        return PageRevision::query()
            ->where('page_id', $page->id)
            ->orderBy('revision')
            ->pluck('revision')
            ->map(fn ($revision): int => (int) $revision)
            ->all();
    }

    private function methodSource(string $class, string $method): string
    {
        $reflection = new \ReflectionMethod($class, $method);
        $lines = file($reflection->getFileName());
        $this->assertIsArray($lines);

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));
    }

    private function page(): Page
    {
        return Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Concurrency test page',
            'sub_title' => 'Optimistic locking test',
            'slug' => 'concurrency-' . Str::lower(Str::random(8)),
            'status' => 1,
            'language' => 'en',
            'publication_status' => 'published',
            'visibility' => 'public',
        ]);
    }

    private function block(Page $page, string $heading): PageBlock
    {
        return PageBlock::create([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'type' => 'rich_text',
            'label' => 'Concurrent section',
            'content' => ['heading' => $heading],
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
    }

    private function reusableBlock(string $heading): ReusableBlock
    {
        return ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared concurrency section',
            'type' => 'rich_text',
            'locale' => 'en',
            'content' => ['heading' => $heading],
            'settings' => [],
            'is_enabled' => true,
        ]);
    }

    private function sharedBlock(Page $page, ReusableBlock $reusable): PageBlock
    {
        return PageBlock::create([
            'page_id' => $page->id,
            'reusable_block_id' => $reusable->id,
            'uuid' => (string) Str::uuid(),
            'type' => $reusable->type,
            'label' => $reusable->name,
            'content' => $reusable->content,
            'settings' => $reusable->settings,
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
    }

    private function authorizedAdmin(
        bool $canRestore = false,
        bool $canDestroyReusable = false,
        bool $canUseLegacyEditor = false
    ): Admin
    {
        $links = ['page.builder.create', 'page.builder.edit', 'page.builder.destroy'];
        if ($canRestore) {
            $links = array_merge($links, ['page.status', 'seo.metadata.edit', 'reusable-blocks.edit']);
        }
        if ($canDestroyReusable) {
            $links[] = 'reusable-blocks.destroy';
        }
        if ($canUseLegacyEditor) {
            $links[] = 'page.edit';
        }

        $actions = MenuAction::query()->whereIn('link', $links)->get();
        $this->assertSame(count($links), $actions->count(), 'A required page-builder permission is not registered.');
        $role = Role::create([
            'name' => 'Concurrency editor',
            'permission' => '',
            'actionPermission' => $actions->pluck('id')->implode(','),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Concurrency editor',
            'username' => 'concurrency-editor-' . Str::lower(Str::random(5)),
            'email' => Str::lower(Str::random(8)) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
