<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\Role;
use App\Models\Sponsorship;
use App\Models\Volunteer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BuilderDashboardUxRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_simple_builder_has_one_save_action_that_is_enabled_only_for_dirty_editable_state(): void
    {
        $source = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));

        $this->assertIsString($source);
        preg_match_all('/<button\b[^>]*\bdata-save-changes\b[^>]*>/i', $source, $saveButtons);

        $this->assertCount(1, $saveButtons[0], 'The simple builder must expose one Save changes action.');
        $this->assertStringContainsString('simple-btn--primary', $saveButtons[0][0]);
        $this->assertStringContainsString(' disabled', $saveButtons[0][0], 'The clean initial state must not look actionable.');
        $this->assertStringContainsString('const savable = hasSavableDirty();', $source);
        $this->assertStringContainsString(
            'button.disabled = !permissions.edit || !savable || state.busy;',
            $source,
            'Save must only become available for an editable, safely savable, idle state.'
        );
        $this->assertStringContainsString("if(!hasDirty())return notify('Everything is already saved.');", $source);
        $this->assertStringContainsString('finally{state.busy=false;updateSaveState()}', $source);
    }

    public function test_simple_builder_keeps_page_tools_and_preview_controls_available_on_mobile(): void
    {
        $source = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('<details class="simple-more">', $source);
        $this->assertStringContainsString('Preview draft</a>', $source);
        $this->assertStringContainsString('View live page</a>', $source);
        $this->assertStringContainsString('Search &amp; Sharing</a>', $source);
        $this->assertStringContainsString('Advanced editor</a>', $source);
        $this->assertStringContainsString('.simple-more__menu{position:fixed;top:78px;right:12px}', $source);
        $this->assertStringContainsString('.simple-actions{width:100%;margin-left:auto;flex-wrap:wrap;justify-content:flex-end}', $source);
        $this->assertStringContainsString('.simple-viewport{order:4;width:100%;justify-content:center}', $source);
        $this->assertStringContainsString("if(window.matchMedia('(max-width:520px)').matches)setPreviewViewport('mobile')", $source);
        $this->assertStringContainsString("else if(window.matchMedia('(max-width:880px)').matches)setPreviewViewport('tablet')", $source);

        preg_match('/@media\(max-width:520px\)\{(?<css>.*?)\}\s*<\/style>/s', $source, $mobileStyles);
        $this->assertArrayHasKey('css', $mobileStyles);
        $this->assertDoesNotMatchRegularExpression(
            '/\.simple-(?:actions|viewport|more)(?:__menu)?[^\{]*\{[^\}]*display\s*:\s*none/i',
            $mobileStyles['css'],
            'Primary actions, Page tools and preview controls must remain reachable at phone widths.'
        );
    }

    public function test_simple_builder_uses_the_full_workspace_for_its_preview(): void
    {
        $source = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('body.layout-wrapper .left-panel{display:none!important}', $source);
        $this->assertStringContainsString('body.layout-wrapper .right-panel{width:100%!important', $source);
        $this->assertStringContainsString('margin-left:0!important', $source);
        $this->assertStringContainsString('grid-template-columns:220px minmax(520px,1fr) 320px', $source);
        $this->assertStringContainsString('.simple-preview{width:min(100%,1050px)', $source);
        $this->assertStringContainsString('.simple-grid{display:flex;flex-direction:column}', $source);
    }

    public function test_simple_builder_can_reorder_hero_slides_independently_from_slide_navigation(): void
    {
        $source = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('aria-label="View previous slide"', $source);
        $this->assertStringContainsString('aria-label="View next slide"', $source);
        $this->assertStringContainsString('data-hero-move="earlier"', $source);
        $this->assertStringContainsString('data-hero-move="later"', $source);
        $this->assertStringContainsString("button.dataset.heroMove === 'earlier' ? state.heroSlide - 1 : state.heroSlide + 1", $source);
        $this->assertStringContainsString('[slides[state.heroSlide],slides[target]] = [slides[target],slides[state.heroSlide]];', $source);
        $this->assertStringContainsString('state.heroSlide = target;', $source);
        $this->assertStringContainsString('function syncHeroFirstSlide(block)', $source);
        $this->assertStringContainsString('heroSlideKeys.forEach', $source);
        $this->assertStringContainsString('syncHeroFirstSlide(block);', $source);
        $this->assertStringContainsString("markDirty('block'); renderAll();", $source);
    }

    public function test_manual_card_preview_selects_and_reveals_the_matching_card_editor(): void
    {
        $source = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));

        $this->assertIsString($source);
        $this->assertMatchesRegularExpression('/cardIndexes\s*:\s*\{\}/', $source);
        $this->assertStringContainsString('function selectedCardIndex(', $source);
        $this->assertStringContainsString('state.cardIndexes[block.uuid]', $source);

        preg_match('/function renderCardsEditor\(block\)\s*\{(?<body>.*?)function renderMediaTextEditor\(block\)/s', $source, $cardEditor);
        $this->assertArrayHasKey('body', $cardEditor);
        $this->assertMatchesRegularExpression('/selectedCardIndex\s*\(\s*block\s*,\s*items\s*\)/', $cardEditor['body']);
        $this->assertMatchesRegularExpression(
            '/\$\{\s*index\s*===\s*[A-Za-z][A-Za-z0-9_]*\s*\?\s*\'open\'\s*:\s*\'\'\s*\}/',
            $cardEditor['body'],
            'The selected card editor must be expanded automatically.'
        );

        $this->assertStringContainsString('data-card-editor-index="${index}"', $source);
        $this->assertStringContainsString('is-current-card', $source);
        $this->assertStringContainsString('data-preview-card-index="${index}"', $source);
        $this->assertStringContainsString('is-card-selected', $source);
        $this->assertStringContainsString('data-preview-card-select', $source);
        $this->assertStringContainsString('aria-pressed=', $source);
        $this->assertStringContainsString('Edit card', $source);

        $this->assertStringContainsString('function selectPreviewCard(', $source);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($source, 'selectPreviewCard('),
            'The selection helper must be called by the preview-card interaction, not merely declared.'
        );
        $this->assertMatchesRegularExpression(
            '/state\.cardIndexes\[uuid\]\s*=\s*index/',
            $source,
            'Selecting a preview card must remember the matching card index.'
        );
        $this->assertStringContainsString('[data-preview-card-index]', $source);
        $this->assertStringContainsString('[data-card-editor-index="${index}"]', $source);
        $this->assertStringContainsString('scrollIntoView(', $source);
    }

    public function test_simple_editor_guides_nontechnical_users_through_the_static_element_library(): void
    {
        $source = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("configuredLayoutOptions.element_catalog", $source);
        $this->assertStringContainsString("filter(element => element?.mode === 'static')", $source);
        $this->assertStringContainsString('id="element-picker-modal"', $source);
        $this->assertStringContainsString('data-layout-open-picker', $source);
        $this->assertStringContainsString('data-layout-pick-element="${escapeHtml(definition.type)}"', $source);
        $this->assertStringContainsString('id="simple-element-picker-select"', $source);
        $this->assertStringNotContainsString('data-layout-new-element', $source);
        $this->assertStringNotContainsString('data-layout-add-element', $source);

        $this->assertStringContainsString("clone(layoutElementCatalog[normalized]?.defaults", $source);
        $this->assertStringContainsString("item[String(definition?.item_identity || 'id')] = newLayoutId()", $source);
        $this->assertStringContainsString('ensureLayoutElementIds(copy,true)', $source);
        $this->assertStringContainsString("['gallery','accordion','timeline']", $source);
        $this->assertStringContainsString('if (!Array.isArray(element[field])) return;', $source);
        $this->assertStringContainsString('It has been left unchanged. Ask an administrator to repair it before saving this section.', $source);
        $this->assertStringNotContainsString('Array.isArray(element.items) ? element.items : (element.items=[])', $source);

        foreach (['icon', 'file', 'card', 'stat', 'quote', 'gallery', 'accordion', 'timeline', 'callout'] as $type) {
            $this->assertGreaterThanOrEqual(
                2,
                substr_count($source, "type === '{$type}'"),
                "The {$type} element needs both a friendly editor and an accurate preview."
            );
        }

        $this->assertStringContainsString('id="document-media-modal"', $source);
        $this->assertStringContainsString("form.append('media_kind','document')", $source);
        $this->assertStringContainsString('data-layout-item-action="up"', $source);
        $this->assertStringContainsString('data-layout-item-action="remove"', $source);
        $this->assertStringContainsString('data-layout-item-choose-image', $source);

        $this->assertStringContainsString('id="simple-help-modal"', $source);
        $this->assertStringContainsString('The everyday mode. It hides row and column controls', $source);
        $this->assertStringContainsString('Customize layout', $source);
        $this->assertStringContainsString('.simple-editor-mode{display:flex', $source);
        $this->assertStringContainsString('.simple-sections{overflow-x:hidden}', $source);
        $this->assertStringContainsString('.simple-section-row{display:grid', $source);
        $this->assertStringContainsString("grid-template-areas:'drag toggle select' 'actions actions actions'", $source);
        $this->assertStringContainsString('.simple-navigator-node{display:grid;width:100%', $source);
        $this->assertStringContainsString('.simple-layout-context-button{display:grid;width:100%', $source);
        $this->assertStringContainsString('.simple-layout-preset{display:grid', $source);
        $this->assertStringContainsString('.simple-layout-preview-inserter{display:inline-flex;width:100%', $source);
        $this->assertStringContainsString('.simple-layout-preview-element.is-layout-selected', $source);
        $this->assertStringContainsString('content:attr(data-node-label)', $source);
        $this->assertStringContainsString('error.validationErrors = validationErrors', $source);
        $this->assertStringContainsString('function focusLayoutValidationError(error)', $source);
        $this->assertStringContainsString('id="simple-validation-error"', $source);
        $this->assertStringContainsString('if(!focusLayoutValidationError(error))notify(error.message)', $source);
    }

    public function test_simple_layout_uses_context_specific_icon_choices_and_only_clears_optional_images(): void
    {
        $source = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("layoutManifestChoices('icon','icon',requiredLayoutIconFallback)", $source);
        $this->assertStringContainsString("layoutManifestChoices('card','icon',optionalLayoutIconFallback)", $source);
        $this->assertStringContainsString("layoutManifestChoices('stat','icon',optionalLayoutIconFallback)", $source);
        $this->assertStringContainsString("layoutManifestChoices('callout','icon',optionalLayoutIconFallback)", $source);
        $this->assertStringContainsString("layoutManifestRepeaterChoices('timeline','items','icon',optionalLayoutIconFallback)", $source);
        $this->assertStringContainsString("'':'No icon'", $source);
        $this->assertStringContainsString("filter(([value])=>value!=='')", $source);
        $this->assertStringContainsString("layoutChoice(element.icon,layoutCardIconChoices,'')", $source);
        $this->assertStringContainsString("layoutChoice(element.icon,layoutStatIconChoices,'')", $source);
        $this->assertStringContainsString("layoutChoice(element.icon,layoutCalloutIconChoices,'')", $source);
        $this->assertStringContainsString("item.icon||'',layoutTimelineIconChoices", $source);

        $this->assertStringContainsString("'Card image (optional)',element.image||'',{clearable:true}", $source);
        $this->assertStringContainsString("'Photograph (optional)',element.image||'',{clearable:true}", $source);
        $this->assertStringContainsString("'path','Image',element.path||'')", $source);
        $this->assertStringContainsString('options.clearable&&populated', $source);
        $this->assertStringContainsString('data-layout-clear-image', $source);
        $this->assertStringContainsString("if (!element || !['card','quote'].includes(layoutElementType(element)) || field !== 'image'", $source);
        $this->assertStringContainsString("element[field] = '';", $source);
        $this->assertStringContainsString("notify('Image removed. Use Undo if you need it back.')", $source);

        preg_match('/querySelectorAll\(\'\[data-layout-clear-image\]\'\).*?\}\)\);/s', $source, $clearHandler);
        $this->assertNotEmpty($clearHandler);
        $this->assertStringContainsString('recordHistory();', $clearHandler[0]);
        $this->assertStringContainsString("markDirty('block');", $clearHandler[0]);
        $this->assertStringContainsString('renderInspector();', $clearHandler[0]);
        $this->assertStringContainsString('renderPreview();', $clearHandler[0]);
    }

    public function test_simple_editor_never_repairs_or_submits_a_damaged_saved_layout_while_rendering(): void
    {
        $source = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('function inspectStoredLayout(content)', $source);
        $this->assertStringContainsString("if (hasVersion && version !== 1 && version !== 2)", $source);
        $this->assertStringContainsString('if (!legacy) return `${location} lost its stable identity.`;', $source);
        $this->assertStringContainsString("missingIdentities.push({owner,key,kind});", $source);
        $this->assertStringContainsString("if (!Array.isArray(row.columns))", $source);
        $this->assertStringContainsString("if (!Array.isArray(column.elements))", $source);
        $this->assertStringContainsString("if (!isStaticLayoutElementType(element.type))", $source);
        $this->assertStringContainsString("fieldDefinition?.kind !== 'repeater'", $source);
        $this->assertStringContainsString('function layoutSerializedByteLength(value)', $source);
        $this->assertStringContainsString('function layoutStoredBoundsIssue(value)', $source);
        $this->assertStringContainsString('value.rows.length>layoutStorageLimits.rows', $source);
        $this->assertStringContainsString('typeCounts[type]>instanceLimit', $source);
        $this->assertStringContainsString('layoutSerializedByteLength(element)>byteLimit', $source);
        $this->assertStringContainsString('layoutSerializedByteLength(value)>layoutStorageLimits.payloadBytes', $source);
        $this->assertStringContainsString('const boundsIssue=layoutStoredBoundsIssue(content);', $source);
        $this->assertStringContainsString("return guard.blocked ? [] : block.content.rows;", $source);
        $this->assertStringContainsString('This visual layout is locked to protect its content', $source);
        $this->assertStringContainsString('Nothing was changed.', $source);
        $this->assertStringContainsString("state.dirtyBlocks.has(block.uuid)&&!layoutIsBlocked(block)", $source);
        $this->assertStringContainsString('JSON.stringify({version:3,baseEditorVersion:editorVersion', $source);
        $this->assertStringContainsString('if (saved.version !== 3 || Number(saved.baseEditorVersion) !== editorVersion)', $source);
        $this->assertStringNotContainsString('JSON.stringify({version:2,baseEditorVersion:editorVersion', $source);

        $layoutRowsStart = strpos($source, 'function layoutRows(block)');
        $layoutRowsEnd = strpos($source, 'const newLayoutRow', $layoutRowsStart ?: 0);
        $this->assertNotFalse($layoutRowsStart);
        $this->assertNotFalse($layoutRowsEnd);
        $layoutRows = substr($source, $layoutRowsStart, $layoutRowsEnd - $layoutRowsStart);
        $this->assertStringNotContainsString('block.content =', $layoutRows);
        $this->assertStringNotContainsString('content.rows =', $layoutRows);
        $this->assertStringNotContainsString('.filter(', $layoutRows);
        $this->assertStringNotContainsString('.map(', $layoutRows);
    }

    public function test_simple_editor_applies_manifest_element_limits_to_every_column_mutation(): void
    {
        $source = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('function layoutColumnCapacityIssue(column,type,additional=1)', $source);
        $this->assertStringContainsString('max_instances_per_column', $source);
        $this->assertStringContainsString('const targetLeftBlocked = !targetLeft || !layoutColumnCanAccept(targetLeft,type);', $source);
        $this->assertStringContainsString('const targetRightBlocked = !targetRight || !layoutColumnCanAccept(targetRight,type);', $source);
        $this->assertStringContainsString('const duplicateDisabled = !layoutColumnCanAccept({elements:columnElements},type);', $source);
        $this->assertStringContainsString('const issue = layoutColumnCapacityIssue(column,type);', $source);
        $this->assertStringContainsString('const issue = targetColumn ? layoutColumnCapacityIssue(targetColumn,type) : \'total\';', $source);

        $plannerStart = strpos($source, 'function planLayoutColumnCollapse(currentColumns,desiredCount)');
        $plannerEnd = strpos($source, 'function elementPickerContext()', $plannerStart ?: 0);
        $this->assertNotFalse($plannerStart);
        $this->assertNotFalse($plannerEnd);
        $planner = substr($source, $plannerStart, $plannerEnd - $plannerStart);
        $this->assertStringContainsString('const assignments=layoutCollapseAssignments(base,displaced);', $planner);
        $this->assertStringContainsString('if(!assignments)return null;', $planner);
        $this->assertStringContainsString('displaced.forEach((element,index)=>base[assignments[index]].elements.push(element));', $planner);
        $this->assertStringContainsString('const residual=Array.from', $source);
        $this->assertStringContainsString('if(flow!==displaced.length)return null;', $source);
        $this->assertStringContainsString('const plannedColumns = planLayoutColumnCollapse(currentColumns,desiredCount);', $source);
        $this->assertStringContainsString('if (!plannedColumns) return false;', $source);
        $this->assertStringNotContainsString('const freeSlots = kept.reduce', $source);
    }

    public function test_dashboard_enquiry_links_are_actionable_and_limited_to_authorized_inboxes(): void
    {
        $this->makeNewEnquiries();
        $admin = $this->makeAdminWithMenuPermissions('Sponsorship reviewer', ['sponsorships.index']);

        $response = $this->actingAs($admin, 'admin')->get(route('dashboard.index'));

        $response
            ->assertOk()
            ->assertSee('aria-label="New public enquiries"', false)
            ->assertSee(
                '<a href="' . route('sponsorships.index') . '"><strong>1</strong>Sponsorships</a>',
                false
            )
            ->assertDontSee(
                '<a href="' . route('volunteer.index') . '"><strong>1</strong>Volunteer applications</a>',
                false
            )
            ->assertDontSee(
                '<a href="' . route('contact-message.index') . '"><strong>1</strong>Contact messages</a>',
                false
            );

        $this->get(route('sponsorships.index'))->assertOk();
        $this->get(route('volunteer.index'))->assertForbidden();
        $this->get(route('contact-message.index'))->assertForbidden();
    }

    public function test_dashboard_does_not_render_unauthorized_enquiry_links(): void
    {
        $this->makeNewEnquiries();
        $admin = $this->makeAdminWithMenuPermissions('Page editor', ['page.index']);

        $this->actingAs($admin, 'admin')
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('Ask an authorised teammate to open the enquiry inbox.')
            ->assertDontSee('<a href="' . route('sponsorships.index') . '"><strong>', false)
            ->assertDontSee('<a href="' . route('volunteer.index') . '"><strong>', false)
            ->assertDontSee('<a href="' . route('contact-message.index') . '"><strong>', false);
    }

    public function test_dashboard_ignores_legacy_activity_without_a_timestamp(): void
    {
        $page = Page::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Legacy timestamp-less page',
            'sub_title' => '',
            'slug' => 'legacy-timestamp-less-page',
            'status' => 1,
            'publication_status' => 'published',
            'language' => 'en',
        ]);
        DB::table('pages')->where('id', $page->id)->update([
            'created_at' => null,
            'updated_at' => null,
        ]);
        $admin = $this->makeAdminWithMenuPermissions('Legacy page reviewer', ['page.index']);

        $this->actingAs($admin, 'admin')
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertDontSee('Legacy timestamp-less page');
    }

    public function test_dashboard_action_links_keep_touch_sized_targets(): void
    {
        $source = file_get_contents(resource_path('views/admin/dashboard/index.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('.dash-enquiries a{display:inline-flex;min-height:44px', $source);
        $this->assertStringContainsString('.dash-health-item a{display:inline-flex;min-height:44px', $source);
    }

    private function makeNewEnquiries(): void
    {
        Sponsorship::create([
            'name' => 'New sponsorship request',
            'email' => 'sponsor-dashboard@example.test',
            'number_of_children' => 1,
            'contribution_interval' => 'monthly',
            'sponsorship_amount' => 1500,
            'transaction_id' => 'DASHBOARD-SPONSOR',
            'workflow_status' => 'new',
        ]);
        Volunteer::create([
            'name' => 'New volunteer application',
            'email' => 'volunteer-dashboard@example.test',
            'status' => 1,
            'workflow_status' => 'new',
        ]);
        ContactMessage::create([
            'first_name' => 'New contact message',
            'email' => 'contact-dashboard@example.test',
            'message' => 'Please contact me.',
            'workflow_status' => 'new',
        ]);
    }

    /**
     * @param  list<string>  $menuLinks
     */
    private function makeAdminWithMenuPermissions(string $name, array $menuLinks): Admin
    {
        $menuIds = AuthMenu::query()
            ->whereIn('link', $menuLinks)
            ->where('status', 1)
            ->pluck('id');

        $this->assertCount(count($menuLinks), $menuIds, 'Every requested dashboard capability must exist.');

        $role = Role::create([
            'name' => $name . ' role',
            'permission' => $menuIds->implode(','),
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $slug = Str::slug($name) . '-' . Str::lower(Str::random(6));

        return Admin::create([
            'name' => $name,
            'username' => $slug,
            'email' => $slug . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
