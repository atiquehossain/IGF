<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\ContactMessage;
use App\Models\Donation;
use App\Models\Role;
use App\Models\Sponsorship;
use App\Models\SslCommerzTransaction;
use App\Models\Volunteer;
use App\Support\AdminUi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardPermissionIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_editor_never_receives_or_renders_unauthorized_operational_values(): void
    {
        $this->seedDistinctiveOperationalData();
        $admin = $this->adminWithMenus('Restricted page editor', ['page.index']);

        $response = $this->actingAs($admin, 'admin')->get(route('dashboard.index'));

        $response->assertOk()
            ->assertDontSee('9,876,543')
            ->assertDontSee(AdminUi::text('dashboard.donations_today'))
            ->assertDontSee(AdminUi::text('dashboard.successful_gifts'))
            ->assertDontSee(AdminUi::text('dashboard.pending_gateways'))
            ->assertDontSee(AdminUi::text('dashboard.revenue_trends'))
            ->assertDontSee(AdminUi::text('dashboard.volunteers'))
            ->assertDontSee('aria-label="'.AdminUi::text('dashboard.new_enquiries').'"', false);

        $this->assertSame([], $response->viewData('metrics'));
        $this->assertSame(0, $response->viewData('newEnquiries'));
        $this->assertTrue($response->viewData('monthlyRevenue')->isEmpty());
        $this->assertTrue($response->viewData('enquiryActions')->isEmpty());
        $this->assertFalse($response->viewData('recentActivity')->contains('type', 'donation'));
        $this->assertFalse($response->viewData('recentActivity')->contains('type', 'volunteer'));
    }

    public function test_authorized_reviewers_receive_only_their_own_dashboard_domain(): void
    {
        $this->seedDistinctiveOperationalData();

        $donationReviewer = $this->adminWithMenus('Donation reviewer', ['donations.index']);
        $donationResponse = $this->actingAs($donationReviewer, 'admin')->get(route('dashboard.index'));

        $donationResponse->assertOk()
            ->assertSee('9,876,543')
            ->assertSee(AdminUi::text('dashboard.revenue_trends'))
            ->assertDontSee(AdminUi::text('dashboard.volunteers'))
            ->assertDontSee('aria-label="'.AdminUi::text('dashboard.new_enquiries').'"', false);
        $this->assertSame(
            ['donations_today', 'donation_change', 'successful_gifts', 'successful_this_month', 'pending_gateways'],
            array_keys($donationResponse->viewData('metrics'))
        );
        $this->assertNotEmpty($donationResponse->viewData('monthlyRevenue'));
        $this->assertTrue($donationResponse->viewData('recentActivity')->contains('type', 'donation'));
        $this->assertFalse($donationResponse->viewData('recentActivity')->contains('type', 'volunteer'));
        $this->assertSame(0, $donationResponse->viewData('newEnquiries'));

        $volunteerReviewer = $this->adminWithMenus('Volunteer reviewer', ['volunteer.index']);
        $volunteerResponse = $this->actingAs($volunteerReviewer, 'admin')->get(route('dashboard.index'));

        $volunteerResponse->assertOk()
            ->assertSee(AdminUi::text('dashboard.volunteers'))
            ->assertSee('aria-label="'.AdminUi::text('dashboard.new_enquiries').'"', false)
            ->assertDontSee('9,876,543')
            ->assertDontSee(AdminUi::text('dashboard.revenue_trends'));
        $this->assertSame(['volunteers'], array_keys($volunteerResponse->viewData('metrics')));
        $this->assertSame(1, $volunteerResponse->viewData('newEnquiries'));
        $this->assertTrue($volunteerResponse->viewData('monthlyRevenue')->isEmpty());
        $this->assertTrue($volunteerResponse->viewData('recentActivity')->contains('type', 'volunteer'));
        $this->assertFalse($volunteerResponse->viewData('recentActivity')->contains('type', 'donation'));
        $this->assertSame(['volunteer.index'], $volunteerResponse->viewData('enquiryActions')->pluck('route')->all());
    }

    public function test_admin_without_dashboard_domains_gets_non_sensitive_guidance(): void
    {
        $this->seedDistinctiveOperationalData();
        $admin = $this->adminWithMenus('Restricted specialist', []);

        $response = $this->actingAs($admin, 'admin')->get(route('dashboard.index'));

        $response->assertOk()
            ->assertSee(AdminUi::text('dashboard.ask_teammate'))
            ->assertDontSee('9,876,543')
            ->assertDontSee(AdminUi::text('dashboard.revenue_trends'))
            ->assertDontSee(AdminUi::text('dashboard.volunteers'));
        $this->assertFalse($response->viewData('hasDashboardInsights'));
        $this->assertSame([], $response->viewData('metrics'));
        $this->assertSame(0, $response->viewData('newEnquiries'));
        $this->assertTrue($response->viewData('monthlyRevenue')->isEmpty());
        $this->assertTrue($response->viewData('recentActivity')->isEmpty());
    }

    private function seedDistinctiveOperationalData(): void
    {
        Donation::create([
            'donor_name' => 'Dashboard privacy donor',
            'email' => 'dashboard-privacy-donor@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'amount' => 9876543,
            'transaction_id' => 'DASHBOARD-PRIVACY-DONATION',
            'payment_status' => 'Success',
        ]);
        SslCommerzTransaction::create([
            'tran_id' => 'DASHBOARD-PRIVACY-PENDING',
            'status' => 'PENDING',
            'amount' => 7654321,
            'currency' => 'BDT',
        ]);
        Sponsorship::create([
            'name' => 'Dashboard privacy sponsor',
            'email' => 'dashboard-privacy-sponsor@example.test',
            'number_of_children' => 1,
            'contribution_interval' => 'monthly',
            'sponsorship_amount' => 654321,
            'transaction_id' => 'DASHBOARD-PRIVACY-SPONSOR',
            'workflow_status' => 'new',
        ]);
        Volunteer::create([
            'name' => 'Dashboard privacy volunteer',
            'email' => 'dashboard-privacy-volunteer@example.test',
            'status' => 1,
            'workflow_status' => 'new',
        ]);
        ContactMessage::create([
            'first_name' => 'Dashboard privacy contact',
            'email' => 'dashboard-privacy-contact@example.test',
            'message' => 'Distinctive private contact message.',
            'workflow_status' => 'new',
        ]);
    }

    /** @param list<string> $menuLinks */
    private function adminWithMenus(string $name, array $menuLinks): Admin
    {
        $menuIds = AuthMenu::query()
            ->whereIn('link', $menuLinks)
            ->where('status', 1)
            ->pluck('id');
        $this->assertCount(count($menuLinks), $menuIds, 'Every requested dashboard capability must exist.');

        $role = Role::create([
            'name' => $name.' role',
            'permission' => $menuIds->implode(','),
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $slug = Str::slug($name).'-'.Str::lower(Str::random(6));

        return Admin::create([
            'name' => $name,
            'username' => $slug,
            'email' => $slug.'@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
