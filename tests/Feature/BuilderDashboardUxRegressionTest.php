<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\ContactMessage;
use App\Models\Role;
use App\Models\Sponsorship;
use App\Models\Volunteer;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertStringContainsString('const dirty = hasDirty();', $source);
        $this->assertStringContainsString(
            'button.disabled = !permissions.edit || !dirty || state.busy;',
            $source,
            'Save must only become available for an editable, dirty, idle state.'
        );
        $this->assertStringContainsString("if(!hasDirty())return notify('Everything is already saved.');", $source);
        $this->assertStringContainsString('finally{state.busy=false;updateSaveState()}', $source);
    }

    public function test_simple_builder_keeps_page_tools_and_preview_controls_available_on_mobile(): void
    {
        $source = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('<details class="simple-more">', $source);
        $this->assertStringContainsString('Preview page</a>', $source);
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
