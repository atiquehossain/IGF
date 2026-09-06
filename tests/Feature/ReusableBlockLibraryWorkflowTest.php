<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\ReusableBlock;
use App\Models\Role;
use App\Support\PageBuilderElementManifest;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReusableBlockLibraryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionRegistrySeeder::class);
    }

    public function test_library_offers_open_preview_and_permission_aware_edit_actions(): void
    {
        $block = $this->reusableBlock('Shared impact introduction');
        $owner = $this->owner();

        $this->actingAs($owner, 'admin')->get(route('reusable-blocks.index'))
            ->assertOk()
            ->assertSee('href="'.route('reusable-blocks.show', $block).'"', false)
            ->assertSee('href="'.route('reusable-blocks.preview', ['reusableBlock' => $block, 'locale' => 'en']).'"', false)
            ->assertSee('href="'.route('reusable-blocks.edit', $block).'"', false)
            ->assertSee('Open')
            ->assertSee('Preview')
            ->assertSee('Edit');

        $viewer = $this->viewer();
        $this->actingAs($viewer, 'admin')->get(route('reusable-blocks.index'))
            ->assertOk()
            ->assertSee('href="'.route('reusable-blocks.show', $block).'"', false)
            ->assertSee('href="'.route('reusable-blocks.preview', ['reusableBlock' => $block, 'locale' => 'en']).'"', false)
            ->assertDontSee('href="'.route('reusable-blocks.edit', $block).'"', false);
        $this->actingAs($viewer, 'admin')->get(route('reusable-blocks.edit', $block))->assertForbidden();
    }

    public function test_editor_names_every_affected_page_and_requires_explicit_impact_acknowledgement(): void
    {
        $owner = $this->owner();
        $block = $this->reusableBlock('Shared impact introduction');
        $pageA = $this->page('Community health', 'community-health');
        $pageB = $this->page('Inclusive education', 'inclusive-education');
        $this->attach($pageA, $block);
        $this->attach($pageB, $block);

        $this->actingAs($owner, 'admin')->get(route('reusable-blocks.edit', $block))
            ->assertOk()
            ->assertSee('Saving updates 2 pages')
            ->assertSee($pageA->name)
            ->assertSee($pageB->name)
            ->assertSee(route('page.builder.edit', ['uuid' => $pageA->uuid, 'locale' => 'en']), false)
            ->assertSee(route('page.builder.preview', ['uuid' => $pageB->uuid, 'locale' => 'en']), false)
            ->assertSee('I reviewed the affected pages')
            ->assertSee('Desktop preview')
            ->assertSee('Tablet preview')
            ->assertSee('Mobile preview')
            ->assertSee('No code or JSON is required');

        $payload = $this->libraryPayload($block, [$pageA, $pageB]);
        $this->actingAs($owner, 'admin')->putJson(route('reusable-blocks.update', $block), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('impact_acknowledged');

        $payload['impact_acknowledged'] = true;
        $this->actingAs($owner, 'admin')->putJson(route('reusable-blocks.update', $block), $payload)
            ->assertOk()
            ->assertJsonPath('connected_page_count', 2)
            ->assertJsonPath('block.content.heading', 'Updated shared heading')
            ->assertJsonPath('block.editor_version', 1);

        $this->assertSame(1, (int) $pageA->fresh()->editor_version);
        $this->assertSame(1, (int) $pageB->fresh()->editor_version);
    }

    public function test_unattached_library_section_remains_editable_without_an_impact_confirmation(): void
    {
        $owner = $this->owner();
        $block = $this->reusableBlock('Unattached introduction');

        $this->actingAs($owner, 'admin')->get(route('reusable-blocks.edit', $block))
            ->assertOk()
            ->assertSee('Not used on a page yet')
            ->assertSee('Save library section')
            ->assertDontSee('name="impact_acknowledged"', false);

        $this->actingAs($owner, 'admin')->putJson(route('reusable-blocks.update', $block), [
            'expected_version' => 0,
            'name' => 'Unattached introduction updated',
            'locale' => 'en',
            'content_json' => json_encode([
                'heading' => 'Safe unattached update',
                'body' => '<p>Useful copy</p><script>alert(1)</script>',
            ]),
            'settings_json' => '[]',
            'is_enabled' => true,
            'library_editor' => true,
            'expected_page_uuids_json' => '[]',
            'expected_connected_page_ids_json' => '[]',
        ])->assertOk()
            ->assertJsonPath('connected_page_count', 0)
            ->assertJsonPath('block.content.heading', 'Safe unattached update');

        $this->assertSame('Unattached introduction updated', $block->fresh()->name);
        $this->assertStringNotContainsString('<script', $block->fresh()->content['body']);
    }

    public function test_editor_rejects_a_save_when_the_affected_page_list_changed(): void
    {
        $owner = $this->owner();
        $block = $this->reusableBlock('Changing usage section');
        $first = $this->page('First connected page', 'first-connected-page');
        $second = $this->page('Second connected page', 'second-connected-page');
        $this->attach($first, $block);
        $payload = $this->libraryPayload($block, [$first]);
        $payload['impact_acknowledged'] = true;

        $this->attach($second, $block);

        $this->actingAs($owner, 'admin')->putJson(route('reusable-blocks.update', $block), $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', 'The pages using this section changed while it was open. Reload the editor and review the updated page list before saving.');

        $this->assertSame('Shared impact introduction', $block->fresh()->content['heading']);
        $this->assertSame(0, (int) $block->fresh()->editor_version);
    }

    public function test_preview_uses_the_public_page_block_renderer_even_for_a_disabled_section(): void
    {
        $owner = $this->owner();
        $block = $this->reusableBlock('Disabled preview section');
        $block->update(['is_enabled' => false]);

        $this->actingAs($owner, 'admin')
            ->get(route('reusable-blocks.preview', ['reusableBlock' => $block, 'locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reusable-block-preview')
                ->where('libraryStatus', 'disabled')
                ->where('previewLocale', 'en')
                ->where('block.type', 'rich_text')
                ->where('block.content.heading', 'Shared impact introduction')
                ->where('block.is_enabled', true));
    }

    public function test_library_editor_uses_named_managed_choices_instead_of_raw_identifiers(): void
    {
        $owner = $this->owner();
        $block = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared ways to give',
            'type' => 'ways_to_give',
            'locale' => 'en',
            'content' => [
                'heading' => 'Choose how to help',
                'layout' => 'card_grid',
                'selection_mode' => 'manual',
                'selected_items' => ['sponsor'],
            ],
            'settings' => [],
            'is_enabled' => true,
        ]);

        $this->actingAs($owner, 'admin')->get(route('reusable-blocks.edit', $block))
            ->assertOk()
            ->assertSee('Sponsor a Child')
            ->assertSee('data-e2e="reusable-managed-items"', false)
            ->assertSee('Internal record IDs are never required.');
    }

    public function test_legacy_and_layout_reusable_editors_show_virtual_design_defaults_without_changing_saved_content(): void
    {
        config()->set('page-builder.section_presentation_default', 'contrast');
        config()->set('page-builder.design_defaults', [
            'section_spacing' => 'spacious',
            'content_alignment' => 'center',
            'column_count' => 'auto',
        ]);

        $owner = $this->owner();
        $richText = $this->reusableBlock('Legacy rich text design');
        $cards = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Legacy card grid design',
            'type' => 'cards',
            'locale' => 'en',
            'content' => [
                'heading' => 'Shared cards',
                'items' => [],
            ],
            'settings' => [],
            'is_enabled' => true,
        ]);
        $layout = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Legacy visual layout design',
            'type' => 'layout',
            'locale' => 'en',
            'content' => [
                'schema_version' => 2,
                'rows' => [[
                    'id' => (string) Str::uuid(),
                    'layout' => 'full',
                    'width' => 'standard',
                    'background' => 'default',
                    'spacing' => 'standard',
                    'columns' => [[
                        'id' => (string) Str::uuid(),
                        'elements' => [],
                    ]],
                ]],
            ],
            'settings' => [],
            'is_enabled' => true,
        ]);
        $originalContent = collect([$richText, $cards, $layout])
            ->mapWithKeys(fn (ReusableBlock $block): array => [$block->uuid => $block->content])
            ->all();
        $expectedDefaults = [
            'section_presentation' => 'contrast',
            'section_spacing' => 'spacious',
            'content_alignment' => 'center',
            'column_count' => 'auto',
        ];
        $expectedColumnTypes = (array) config('page-builder.column_count_block_types', []);

        foreach ([$richText, $cards, $layout] as $block) {
            $response = $this->actingAs($owner, 'admin')->get(route('reusable-blocks.edit', $block))
                ->assertOk();

            $this->assertSame($expectedDefaults, $response->viewData('designDefaults'));
            $this->assertSame($expectedColumnTypes, $response->viewData('columnCountBlockTypes'));
            $this->assertSame($originalContent[$block->uuid], $block->fresh()->content);
        }

        $source = file_get_contents(resource_path('views/admin/reusable-blocks/editor.blade.php'));
        $this->assertIsString($source);
        $this->assertStringContainsString('const designDefaults = @json($designDefaults);', $source);
        $this->assertStringContainsString('const columnCountBlockTypes = new Set((@json($columnCountBlockTypes) || []).map(String));', $source);
        $this->assertStringContainsString("if (key === 'column_count' && !columnCountBlockTypes.has(blockType)) return false;", $source);
        $this->assertStringContainsString("const value = Object.prototype.hasOwnProperty.call(content,key) ? content[key] : designDefaults[key];", $source);
        $this->assertStringContainsString("const contentDesignFields = rootName === 'content' ? renderGlobalDesignFields() : '';", $source);
        $this->assertStringContainsString('const globalFields = renderGlobalDesignFields();', $source);
        $this->assertStringContainsString('data-e2e="reusable-global-design"', $source);

        $rendererStart = strpos($source, 'function renderGlobalDesignFields()');
        $rendererEnd = strpos($source, 'function renderLayoutEditor()', $rendererStart ?: 0);
        $this->assertNotFalse($rendererStart);
        $this->assertNotFalse($rendererEnd);
        $renderer = substr($source, $rendererStart, $rendererEnd - $rendererStart);
        $this->assertStringNotContainsString('content[key] =', $renderer);
        $this->assertStringNotContainsString('Object.assign', $renderer);

        $updatedRichText = $richText->content + [
            'section_presentation' => 'soft',
            'section_spacing' => 'compact',
            'content_alignment' => 'left',
        ];
        $this->actingAs($owner, 'admin')->putJson(route('reusable-blocks.update', $richText), [
            'expected_version' => 0,
            'name' => $richText->name,
            'locale' => 'en',
            'content_json' => json_encode($updatedRichText),
            'settings_json' => '[]',
            'is_enabled' => true,
            'library_editor' => true,
            'expected_page_uuids_json' => '[]',
            'expected_connected_page_ids_json' => '[]',
        ])->assertOk();
        $this->assertSame('soft', $richText->fresh()->content['section_presentation']);
        $this->assertSame('compact', $richText->fresh()->content['section_spacing']);
        $this->assertSame('left', $richText->fresh()->content['content_alignment']);

        $updatedCards = $cards->content + ['column_count' => '3'];
        $this->actingAs($owner, 'admin')->putJson(route('reusable-blocks.update', $cards), [
            'expected_version' => 0,
            'name' => $cards->name,
            'locale' => 'en',
            'content_json' => json_encode($updatedCards),
            'settings_json' => '[]',
            'is_enabled' => true,
            'library_editor' => true,
            'expected_page_uuids_json' => '[]',
            'expected_connected_page_ids_json' => '[]',
        ])->assertOk();
        $this->assertSame('3', $cards->fresh()->content['column_count']);
        $this->assertArrayNotHasKey('column_count', $layout->fresh()->content);
    }

    public function test_reusable_layout_media_and_icon_controls_follow_each_manifest_field_requirement(): void
    {
        $manifest = PageBuilderElementManifest::all();
        $optionalIcons = $manifest['card']['fields']['icon']['options'];
        $requiredIcons = $optionalIcons;
        unset($requiredIcons['']);

        $this->assertSame('No icon', $optionalIcons['']);
        $this->assertSame($requiredIcons, $manifest['icon']['fields']['icon']['options']);
        $this->assertSame($optionalIcons, $manifest['stat']['fields']['icon']['options']);
        $this->assertSame($optionalIcons, $manifest['callout']['fields']['icon']['options']);
        $this->assertSame($optionalIcons, $manifest['timeline']['fields']['items']['item_fields']['icon']['options']);
        $this->assertTrue($manifest['image']['fields']['path']['required']);
        $this->assertFalse($manifest['card']['fields']['image']['required']);
        $this->assertFalse($manifest['quote']['fields']['image']['required']);

        $source = file_get_contents(resource_path('views/admin/reusable-blocks/editor.blade.php'));
        $this->assertIsString($source);
        $this->assertStringContainsString('const choices = {...(definition?.options || {})};', $source);
        $this->assertStringContainsString("const clearableImage = kind === 'image' && definition?.required === false && ['card','quote'].includes(String(element.type || ''));", $source);
        $this->assertStringContainsString('canEdit && clearableImage && value', $source);
        $this->assertStringContainsString('data-layout-clear-image', $source);
        $this->assertStringContainsString("const placeholderDisabled = definition?.required === true || value !== '';", $source);
        $this->assertStringContainsString("\${definition?.required?' required':''}", $source);
        $this->assertStringContainsString("\${placeholderDisabled?' disabled':''}", $source);

        $clearStart = strpos($source, "querySelectorAll('[data-layout-clear-image]')");
        $clearEnd = strpos($source, "querySelectorAll('[data-layout-media]')", $clearStart ?: 0);
        $this->assertNotFalse($clearStart);
        $this->assertNotFalse($clearEnd);
        $clearHandler = substr($source, $clearStart, $clearEnd - $clearStart);
        $this->assertStringContainsString("!['card','quote'].includes(String(element.type || ''))", $clearHandler);
        $this->assertStringContainsString("definition?.kind !== 'managed_image'", $clearHandler);
        $this->assertStringContainsString('definition?.required !== false', $clearHandler);
        $this->assertStringContainsString("setValue(element,path,'');", $clearHandler);
        $this->assertStringContainsString('markDirty();', $clearHandler);
        $this->assertStringContainsString('renderAll();', $clearHandler);

        $mediaStart = strpos($source, "querySelectorAll('[data-layout-media]')");
        $mediaEnd = strpos($source, "querySelectorAll('[data-layout-new-element]')", $mediaStart ?: 0);
        $this->assertNotFalse($mediaStart);
        $this->assertNotFalse($mediaEnd);
        $mediaHandler = substr($source, $mediaStart, $mediaEnd - $mediaStart);
        $this->assertStringContainsString('const definition = layoutFieldDefinitionAtPath(element,path);', $mediaHandler);
        $this->assertStringContainsString('if (!asset) {', $mediaHandler);
        $this->assertStringContainsString('select.value = currentValue;', $mediaHandler);
        $this->assertStringContainsString("if (definition?.required === true) layoutStatus('This media is required.", $mediaHandler);
        $this->assertStringContainsString('setValue(element,path,String(asset.url));', $mediaHandler);
    }

    public function test_layout_library_editor_is_a_guided_row_column_builder_with_safe_media_choices(): void
    {
        $owner = $this->owner();
        $asset = MediaAsset::create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => 'media/reusable-layout-photo.jpg',
            'original_name' => 'Reusable layout photo.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'bytes' => 1200,
            'width' => 1200,
            'height' => 800,
            'alt_text' => 'Community volunteers',
            'locale' => '*',
        ]);
        $block = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared visual layout',
            'type' => 'layout',
            'locale' => 'en',
            'content' => [
                'rows' => [[
                    'id' => (string) Str::uuid(),
                    'layout' => 'halves',
                    'width' => 'wide',
                    'background' => 'soft',
                    'spacing' => 'standard',
                    'columns' => [
                        ['elements' => [[
                            'id' => (string) Str::uuid(),
                            'type' => 'heading',
                            'text' => 'Reusable heading',
                            'level' => 'h2',
                        ]]],
                        ['elements' => []],
                    ],
                ]],
            ],
            'settings' => [],
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($owner, 'admin')->get(route('reusable-blocks.edit', $block))
            ->assertOk()
            ->assertSee('data-e2e="reusable-layout-editor"', false)
            ->assertSee('Build with rows, columns, and visual elements')
            ->assertSee('machine IDs and code stay hidden.')
            ->assertSee('data-layout-row-action="duplicate"', false)
            ->assertSee('data-layout-element-action="left"', false)
            ->assertSee('data-layout-add-element', false)
            ->assertSee('data-layout-media=', false)
            ->assertSee($asset->original_name)
            ->assertDontSee('data-layout-row-field="id"', false)
            ->assertDontSee('data-layout-element-field="id"', false);

        foreach (['One column', 'Two equal columns', 'Three equal columns', 'Four equal columns', 'One third / two thirds', 'Two thirds / one third'] as $preset) {
            $response->assertSee($preset);
        }
        foreach (['heading', 'rich_text', 'image', 'video', 'button', 'divider', 'spacer'] as $elementType) {
            $response->assertSee($elementType);
        }
        $response->assertSee('window.crypto?.randomUUID', false)
            ->assertSee('column.id = newLayoutId()', false)
            ->assertSee('regenerateLayoutElementIds', false)
            ->assertSee('function safeLayoutRichHtml', false)
            ->assertSee('safeLayoutRichHtml(valueAt(element,path))', false);
    }

    public function test_layout_library_editor_applies_manifest_element_limits_to_every_column_mutation(): void
    {
        $source = file_get_contents(resource_path('views/admin/reusable-blocks/editor.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('function layoutColumnCapacityIssue(column,type,additional=1)', $source);
        $this->assertStringContainsString('max_instances_per_column', $source);
        $this->assertStringContainsString('const leftBlocked = !leftColumn || !layoutColumnCanAccept(leftColumn,type);', $source);
        $this->assertStringContainsString('const rightBlocked = !rightColumn || !layoutColumnCanAccept(rightColumn,type);', $source);
        $this->assertStringContainsString('const duplicateBlocked = !layoutColumnCanAccept({elements:columnElements},type);', $source);
        $this->assertStringContainsString('const issue = layoutColumnCapacityIssue(column,element.type);', $source);
        $this->assertStringContainsString('const issue = targetColumn ? layoutColumnCapacityIssue(targetColumn,type) : \'total\';', $source);
        $this->assertStringContainsString('layoutElementPickerHtml(column)', $source);

        $plannerStart = strpos($source, 'function planLayoutColumnCollapse(currentColumns,desiredCount)');
        $plannerEnd = strpos($source, 'const validLayoutId', $plannerStart ?: 0);
        $this->assertNotFalse($plannerStart);
        $this->assertNotFalse($plannerEnd);
        $planner = substr($source, $plannerStart, $plannerEnd - $plannerStart);
        $this->assertStringContainsString('const assignments=layoutCollapseAssignments(base,displaced);', $planner);
        $this->assertStringContainsString('if(!assignments)return null;', $planner);
        $this->assertStringContainsString('displaced.forEach((element,index)=>base[assignments[index]].elements.push(element));', $planner);
        $this->assertStringContainsString('const residual=Array.from', $source);
        $this->assertStringContainsString('if(flow!==displaced.length)return null;', $source);
        $this->assertStringContainsString('const plannedColumns = planLayoutColumnCollapse(current,desired);', $source);
        $this->assertStringContainsString('if (!plannedColumns) return false;', $source);
        $this->assertStringNotContainsString('const free = kept.reduce', $source);
    }

    public function test_layout_library_editor_locks_damaged_saved_content_without_rewriting_it(): void
    {
        $source = file_get_contents(resource_path('views/admin/reusable-blocks/editor.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('function inspectStoredLayout(value)', $source);
        $this->assertStringContainsString('const layoutStorageLimits = Object.freeze({', $source);
        $this->assertStringContainsString('payloadBytes:positiveLayoutLimit(layoutConfig.limits?.payload_bytes,524288)', $source);
        $this->assertStringContainsString('rows:positiveLayoutLimit(layoutConfig.limits?.rows,12)', $source);
        $this->assertStringContainsString('function layoutSerializedByteLength(value)', $source);
        $this->assertStringContainsString("serialized.replace(/\\u2028/g,'\\\\u2028').replace(/\\u2029/g,'\\\\u2029')", $source);
        $this->assertStringContainsString("if (typeof TextEncoder === 'function') return new TextEncoder().encode(serialized).length;", $source);
        $this->assertStringContainsString('for (const character of serialized)', $source);
        $this->assertStringContainsString('function layoutStoredBoundsIssue(value)', $source);
        $this->assertStringContainsString('value.rows.length > layoutStorageLimits.rows', $source);
        $this->assertStringContainsString('definition.safe_bounds?.max_instances_per_column', $source);
        $this->assertStringContainsString('definition.safe_bounds?.max_serialized_bytes', $source);
        $this->assertStringContainsString('layoutSerializedByteLength(value) > layoutStorageLimits.payloadBytes', $source);
        $this->assertStringContainsString('const boundsIssue = layoutStoredBoundsIssue(value);', $source);
        $this->assertStringContainsString('if (boundsIssue) return blocked(boundsIssue);', $source);
        $this->assertStringContainsString("if (hasVersion && version !== 1 && version !== 2)", $source);
        $this->assertStringContainsString('if (!legacy) return `${location} lost its stable identity.`;', $source);
        $this->assertStringContainsString('missingIdentities.push({owner,key,kind});', $source);
        $this->assertStringContainsString('if (!Array.isArray(row.columns))', $source);
        $this->assertStringContainsString('if (!Array.isArray(column.elements))', $source);
        $this->assertStringContainsString("uses a content type this editor does not support", $source);
        $this->assertStringContainsString('return guard.blocked ? [] : content.rows;', $source);
        $this->assertStringContainsString('This visual layout is locked to protect its content', $source);
        $this->assertStringContainsString('Nothing was changed.', $source);
        $this->assertStringContainsString('saveButton.disabled = layoutBlocked || busy', $source);
        $this->assertStringContainsString("if (layoutLoadGuard().blocked) return;", $source);

        $layoutRowsStart = strpos($source, 'function layoutRows()');
        $layoutRowsEnd = strpos($source, 'function layoutRepairNotice', $layoutRowsStart ?: 0);
        $this->assertNotFalse($layoutRowsStart);
        $this->assertNotFalse($layoutRowsEnd);
        $layoutRows = substr($source, $layoutRowsStart, $layoutRowsEnd - $layoutRowsStart);
        $this->assertStringNotContainsString('content.rows =', $layoutRows);
        $this->assertStringNotContainsString('.filter(', $layoutRows);
        $this->assertStringNotContainsString('.map(', $layoutRows);
    }

    public function test_layout_library_editor_round_trips_all_static_elements_and_nested_identities_without_exposing_managed_types(): void
    {
        $owner = $this->owner();
        Storage::fake('public');
        $image = $this->mediaAsset('media/reusable-static-image.jpg', 'Static image.jpg', 'image/jpeg', 'jpg');
        $video = $this->mediaAsset('media/reusable-static-video.mp4', 'Static video.mp4', 'video/mp4', 'mp4');
        $document = $this->mediaAsset('media/reusable-static-report.pdf', 'Static report.pdf', 'application/pdf', 'pdf');
        $this->mediaAsset('media/not-offered-vector.svg', 'Unsafe vector.svg', 'image/svg+xml', 'svg');
        $this->mediaAsset('media/not-offered-archive.zip', 'Unsafe archive.zip', 'application/zip', 'zip');
        $content = $this->allStaticLayoutContent($image, $video, $document);
        $block = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Every safe visual element',
            'type' => 'layout',
            'locale' => 'en',
            'content' => $content,
            'settings' => [],
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($owner, 'admin')->get(route('reusable-blocks.edit', $block))
            ->assertOk()
            ->assertSee('data-e2e="reusable-layout-editor"', false)
            ->assertSee('element.mode === \'static\'', false)
            ->assertSee('data-layout-repeater-action', false)
            ->assertSee($document->original_name)
            ->assertSee('Documents are uploaded and managed there.')
            ->assertDontSee('Unsafe vector.svg')
            ->assertDontSee('Unsafe archive.zip');

        foreach (PageBuilderElementManifest::all() as $type => $definition) {
            if ($definition['mode'] === 'static') {
                $response->assertSee('"type":"'.$type.'"', false);
            }
        }
        foreach (['content_feed', 'team', 'giving', 'managed_form'] as $managedType) {
            $response->assertDontSee('"type":"'.$managedType.'"', false);
        }

        $this->actingAs($owner, 'admin')->putJson(route('reusable-blocks.update', $block), [
            'expected_version' => 0,
            'name' => $block->name,
            'locale' => 'en',
            'content_json' => json_encode($content),
            'settings_json' => '[]',
            'is_enabled' => true,
            'library_editor' => true,
            'expected_page_uuids_json' => '[]',
            'expected_connected_page_ids_json' => '[]',
        ])->assertOk()->assertJsonPath('block.editor_version', 1);

        $this->assertSame($content, $block->fresh()->content);
    }

    public function test_library_editor_reuses_builder_validation_for_managed_content(): void
    {
        $owner = $this->owner();
        $block = ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Validated ways to give',
            'type' => 'ways_to_give',
            'locale' => 'en',
            'content' => [
                'heading' => 'Choose how to help',
                'layout' => 'card_grid',
                'selection_mode' => 'manual',
                'selected_items' => ['sponsor'],
            ],
            'settings' => [],
            'is_enabled' => true,
        ]);

        $this->actingAs($owner, 'admin')->putJson(route('reusable-blocks.update', $block), [
            'expected_version' => 0,
            'name' => $block->name,
            'locale' => 'en',
            'content_json' => json_encode([
                'heading' => 'Choose how to help',
                'layout' => 'card_grid',
                'selection_mode' => 'manual',
                'selected_items' => ['not-a-managed-destination'],
            ]),
            'settings_json' => '[]',
            'is_enabled' => true,
            'library_editor' => true,
            'expected_page_uuids_json' => '[]',
            'expected_connected_page_ids_json' => '[]',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('content.selected_items');

        $this->assertSame(['sponsor'], $block->fresh()->content['selected_items']);
    }

    private function libraryPayload(ReusableBlock $block, array $pages): array
    {
        return [
            'expected_version' => (int) $block->editor_version,
            'name' => $block->name,
            'locale' => 'en',
            'content_json' => json_encode([
                'heading' => 'Updated shared heading',
                'body' => '<p>Updated shared body.</p>',
                'section_presentation' => 'soft',
            ]),
            'settings_json' => '[]',
            'is_enabled' => true,
            'library_editor' => true,
            'expected_page_uuids_json' => json_encode(collect($pages)->pluck('uuid')->unique()->sort()->values()->all()),
            'expected_connected_page_ids_json' => json_encode(collect($pages)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all()),
        ];
    }

    private function mediaAsset(string $path, string $name, string $mime, string $extension): MediaAsset
    {
        Storage::disk('public')->put($path, 'test media contents');

        return MediaAsset::create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => $path,
            'original_name' => $name,
            'mime_type' => $mime,
            'extension' => $extension,
            'bytes' => 1200,
            'width' => str_starts_with($mime, 'image/') ? 1200 : null,
            'height' => str_starts_with($mime, 'image/') ? 800 : null,
            'alt_text' => str_starts_with($mime, 'image/') ? 'Community volunteers' : null,
            'locale' => '*',
        ]);
    }

    private function allStaticLayoutContent(MediaAsset $image, MediaAsset $video, MediaAsset $document): array
    {
        $paths = [
            'image' => '/storage/'.$image->path,
            'video' => '/storage/'.$video->path,
            'document' => '/storage/'.$document->path,
        ];
        $elements = collect(PageBuilderElementManifest::all())
            ->filter(fn (array $definition): bool => $definition['mode'] === 'static')
            ->map(function (array $definition, string $type) use ($paths): array {
                $element = ['id' => (string) Str::uuid()] + $definition['defaults'];
                if ($type === 'button') {
                    $element['url'] = '/about-us';
                } elseif ($type === 'image') {
                    $element['path'] = $paths['image'];
                    $element['alt'] = 'Community volunteers';
                } elseif ($type === 'video') {
                    $element['source'] = $paths['video'];
                    $element['title'] = 'Community work video';
                } elseif ($type === 'file') {
                    $element['path'] = $paths['document'];
                } elseif ($type === 'gallery') {
                    $element['items'] = [[
                        'id' => (string) Str::uuid(),
                        'path' => $paths['image'],
                        'alt' => 'Gallery volunteers',
                        'caption' => 'Working together',
                    ]];
                } elseif ($type === 'card') {
                    $element['image'] = $paths['image'];
                    $element['image_alt'] = 'Card volunteers';
                    $element['url'] = '/our-work';
                } elseif ($type === 'quote') {
                    $element['image'] = $paths['image'];
                    $element['image_alt'] = 'Quoted community member';
                } elseif (in_array($type, ['accordion', 'timeline'], true)) {
                    $element['items'][0]['id'] = (string) Str::uuid();
                }

                return $element;
            })->values()->all();

        return [
            'schema_version' => 2,
            'rows' => [[
                'id' => (string) Str::uuid(),
                'layout' => 'halves',
                'width' => 'wide',
                'background' => 'soft',
                'spacing' => 'standard',
                'columns' => [
                    ['id' => (string) Str::uuid(), 'elements' => array_slice($elements, 0, 8)],
                    ['id' => (string) Str::uuid(), 'elements' => array_slice($elements, 8)],
                ],
            ]],
        ];
    }

    private function owner(): Admin
    {
        $suffix = Str::lower(Str::random(10));
        $role = Role::create([
            'name' => 'Reusable library owner '.$suffix,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
            'is_owner' => true,
        ]);

        return Admin::create([
            'name' => 'Reusable Library Owner',
            'username' => 'reusable-owner-'.$suffix,
            'email' => 'reusable-owner-'.$suffix.'@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }

    private function viewer(): Admin
    {
        $menu = AuthMenu::where('link', 'reusable-blocks.index')->firstOrFail();
        $suffix = Str::lower(Str::random(10));
        $role = Role::create([
            'name' => 'Reusable library viewer '.$suffix,
            'permission' => (string) $menu->id,
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Reusable Library Viewer',
            'username' => 'reusable-viewer-'.$suffix,
            'email' => 'reusable-viewer-'.$suffix.'@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }

    private function reusableBlock(string $name): ReusableBlock
    {
        return ReusableBlock::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'type' => 'rich_text',
            'locale' => 'en',
            'content' => [
                'eyebrow' => 'Our work',
                'heading' => 'Shared impact introduction',
                'body' => '<p>Shared body copy.</p>',
            ],
            'settings' => [],
            'is_enabled' => true,
        ]);
    }

    private function page(string $name, string $slug): Page
    {
        return Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'sub_title' => $name.' introduction',
            'slug' => $slug,
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'language' => 'en',
        ]);
    }

    private function attach(Page $page, ReusableBlock $block): PageBlock
    {
        return PageBlock::create([
            'page_id' => $page->id,
            'reusable_block_id' => $block->id,
            'uuid' => (string) Str::uuid(),
            'type' => $block->type,
            'label' => $block->name,
            'content' => $block->content,
            'settings' => $block->settings,
            'sort_order' => 0,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
    }
}
