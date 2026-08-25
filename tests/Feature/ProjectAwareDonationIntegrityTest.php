<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ContentTrashController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\PageController;
use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Category;
use App\Models\Donation;
use App\Models\DonationAllocation;
use App\Models\DonationType;
use App\Models\MediaAsset;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\PageTagModule;
use App\Models\Role;
use App\Models\SslCommerzTransaction;
use App\Models\Tag;
use App\Services\DonationDestinationService;
use App\Services\MediaUsageService;
use App\Services\PageRevisionService;
use App\Services\SSLCommerzService;
use App\Services\TranslationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProjectAwareDonationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_contract_resolves_deep_links_rejects_tampering_and_snapshots_intent(): void
    {
        DonationType::query()->forceDelete();

        $program = $this->category('Education', 'education');
        $otherProgram = $this->category('Health', 'health');
        $project = $this->page('Project Ankur', 'project-ankur', $program);
        $outside = $this->page('Health Clinic', 'health-clinic', $otherProgram);
        $cause = DonationType::create([
            'name' => 'Education',
            'description' => 'Support education projects.',
            'destination_type' => 'category',
            'destination_category_uuid' => $program->uuid,
            'status' => 1,
        ]);

        $legacyUrl = '/donate?cause=' . $cause->slug . '&project=' . $project->uuid;
        $canonicalUrl = '/donate/' . $cause->slug . '?project=' . $project->uuid;
        $this->get($legacyUrl)->assertRedirect($canonicalUrl);

        $this->get($canonicalUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.pageMode', 'detail')
                ->where('data.selectedUUID', $cause->uuid)
                ->where('data.selectedCauseSlug', $cause->slug)
                ->where('data.selectedProjectUUID', $project->uuid)
                ->where('data.donationTypes.0.destination_type', 'category')
                ->where('data.donationTypes.0.destination_uuid', $program->uuid)
                ->where('data.donationTypes.0.project_selection', 'optional')
                ->where('data.donationTypes.0.projects.0.uuid', $project->uuid)
                ->where('data.donationTypes.0.projects.0.category_uuid', $program->uuid)
                ->where('data.donationTypes.0.projects.0.is_zakat_eligible', false)
            );

        $outsideUrl = '/donate/' . $cause->slug . '?project=' . $outside->uuid;
        $this->get($outsideUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.selectedUUID', $cause->uuid)
                ->where('data.selectedProjectUUID', null)
                ->where('data.selection_warning', fn ($warning) => is_string($warning) && $warning !== '')
            );

        $this->configureGateway();
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/project-aware-session',
            ]),
        ]);

        $this->postJson('/donate', $this->checkoutPayload($cause, ['project_uuid' => $outside->uuid]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_uuid');

        $this->postJson('/donate', $this->checkoutPayload($cause, ['project_uuid' => $project->uuid]))
            ->assertOk();

        $this->assertDatabaseHas('donations', [
            'payment_cause' => $cause->uuid,
            'cause_uuid_snapshot' => $cause->uuid,
            'cause_slug_snapshot' => $cause->slug,
            'cause_name_snapshot' => 'Education',
            'destination_type_snapshot' => 'category',
            'destination_uuid_snapshot' => $program->uuid,
            'destination_name_snapshot' => 'Education',
            'project_uuid_snapshot' => $project->uuid,
            'project_name_snapshot' => 'Project Ankur',
            'payment_status' => 'Pending',
        ]);
        $this->assertDatabaseHas('ssl_commerz_transactions', [
            'opted_a' => $cause->uuid,
            'opted_c' => $project->uuid,
            'opted_d' => $cause->slug,
        ]);

        $broad = DonationType::create([
            'name' => 'General giving',
            'destination_type' => 'unrestricted',
            'status' => 1,
        ]);
        $this->postJson('/donate', $this->checkoutPayload($broad, ['project_uuid' => $project->uuid]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_uuid');
    }

    public function test_allocation_scope_accepts_only_real_projects_and_zakat_eligible_targets(): void
    {
        $projects = $this->category('Projects', 'projects');
        $general = $this->category('General pages', 'general-pages');
        $categoryProject = $this->page('Category Project', 'category-project', $projects);
        $taggedProject = $this->page('Tagged Project', 'tagged-project', $general, ['is_zakat_eligible' => true]);
        $unrelatedTagged = $this->page('Tagged News', 'tagged-news', $general, ['is_funding_project' => false]);
        $home = $this->page('Home', 'home', $general, ['is_funding_project' => false]);

        $projectTag = Tag::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Current Projects',
            'slug' => 'current-project',
            'status' => 1,
        ]);
        $newsTag = Tag::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'News',
            'slug' => 'news',
            'status' => 1,
        ]);
        $this->tagPage($taggedProject, $projectTag);
        $this->tagPage($unrelatedTagged, $newsTag);

        $donation = $this->donation([
            'destination_type_snapshot' => 'restricted_fund',
            'destination_name_snapshot' => 'Community Fund',
            'payment_status' => 'Success',
        ]);
        $uuids = app(DonationDestinationService::class)->allocationPages($donation)->pluck('uuid')->all();

        $this->assertContains($categoryProject->uuid, $uuids);
        $this->assertContains($taggedProject->uuid, $uuids);
        $this->assertNotContains($unrelatedTagged->uuid, $uuids);
        $this->assertNotContains($home->uuid, $uuids);

        $zakat = $this->donation([
            'purpose_key_snapshot' => 'zakat',
            'destination_type_snapshot' => 'restricted_fund',
            'destination_name_snapshot' => 'Zakat Fund',
            'payment_status' => 'Success',
        ]);
        $zakatUuids = app(DonationDestinationService::class)->allocationPages($zakat)->pluck('uuid')->all();
        $this->assertSame([$taggedProject->uuid], $zakatUuids);
    }

    public function test_only_explicitly_fundable_programs_and_projects_can_be_configured_as_destinations(): void
    {
        $program = $this->category('Programs', 'programs');
        $general = $this->category('General website pages', 'general-pages');
        $fundable = $this->page('Reviewed fundable program', 'reviewed-fundable', $program);
        $ordinary = $this->page('About the foundation', 'about-foundation', $general, [
            'is_funding_project' => false,
        ]);
        $this->page('General category child', 'general-child', $general, [
            'is_funding_project' => false,
        ]);

        $this->assertNull(app(DonationDestinationService::class)->preferredFundingPublicPage($ordinary->uuid));
        $this->assertSame($fundable->uuid, app(DonationDestinationService::class)
            ->preferredFundingPublicPage($fundable->uuid)?->uuid);

        $creator = $this->adminWith(['donationType.create'], 'donationType.index');
        $this->asAdmin($creator)->get(route('donationType.index'))
            ->assertOk()
            ->assertSee('Reviewed fundable program')
            ->assertDontSee('About the foundation');

        $invalidCause = DonationType::create([
            'name' => 'Unsafe page destination',
            'description' => 'This cause points to ordinary website content.',
            'destination_type' => 'page',
            'destination_page_uuid' => $ordinary->uuid,
            'status' => 1,
        ]);
        $this->assertFalse(app(DonationDestinationService::class)->isOperational($invalidCause));

        $base = [
            'description' => 'Visitor-ready wording for a managed donation cause.',
            'purpose_key' => '',
            'destination_name' => '',
            'destination_category_uuid' => '',
            'destination_page_uuid' => '',
            'image_media_uuid' => '',
        ];
        $this->post(route('donationType.store'), array_merge($base, [
            'name' => 'Ordinary page cause',
            'destination_type' => 'page',
            'destination_page_uuid' => $ordinary->uuid,
        ]))->assertSessionHasErrors('destination_page_uuid');
        $this->post(route('donationType.store'), array_merge($base, [
            'name' => 'Ordinary category cause',
            'destination_type' => 'category',
            'destination_category_uuid' => $general->uuid,
        ]))->assertSessionHasErrors('destination_category_uuid');
        $this->post(route('donationType.store'), array_merge($base, [
            'name' => 'Reviewed fundable cause',
            'destination_type' => 'page',
            'destination_page_uuid' => $fundable->uuid,
        ]))->assertRedirect()->assertSessionHas('alert-type', 'success');
        $this->assertDatabaseHas('donation_types', [
            'name' => 'Reviewed fundable cause',
            'destination_page_uuid' => $fundable->uuid,
            'status' => 0,
        ]);
    }

    public function test_donation_cause_presentation_fields_are_validated_persisted_and_exposed_for_editing(): void
    {
        $admin = $this->adminWith([
            'donationType.create',
            'donationType.edit',
        ], 'donationType.index');
        $validPayload = [
            'name' => 'Presentation-managed cause',
            'description' => 'Visitor-ready copy for a managed donation card.',
            'purpose_key' => '',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Presentation Fund',
            'destination_category_uuid' => '',
            'destination_page_uuid' => '',
            'image_media_uuid' => '',
            'display_order' => 37,
            'icon_key' => 'water',
        ];

        $this->asAdmin($admin)
            ->post(route('donationType.store'), $validPayload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $cause = DonationType::where('name', $validPayload['name'])->sole();
        $this->assertSame(37, $cause->display_order);
        $this->assertSame('water', $cause->icon_key);

        $this->put(route('donationType.update'), array_merge($validPayload, [
            'id' => $cause->id,
            'display_order' => 42,
            'icon_key' => 'children',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $cause->refresh();
        $this->assertSame(42, $cause->display_order);
        $this->assertSame('children', $cause->icon_key);

        $this->get(route('donationType.edit', $cause->id))
            ->assertOk()
            ->assertJsonPath('data.display_order', 42)
            ->assertJsonPath('data.icon_key', 'children');

        $this->post(route('donationType.store'), array_merge($validPayload, [
            'name' => 'Negative-order cause',
            'display_order' => -1,
        ]))->assertSessionHasErrors('display_order');
        $this->assertDatabaseMissing('donation_types', ['name' => 'Negative-order cause']);

        $this->post(route('donationType.store'), array_merge($validPayload, [
            'name' => 'Unsafe-icon cause',
            'display_order' => 50,
            'icon_key' => 'fa-solid fa-skull',
        ]))->assertSessionHasErrors('icon_key');
        $this->assertDatabaseMissing('donation_types', ['name' => 'Unsafe-icon cause']);
    }

    public function test_authorized_split_allocations_are_idempotent_report_exact_attribution_and_keep_actor_snapshot(): void
    {
        $projects = $this->category('Projects', 'projects');
        $firstProject = $this->page('Project One', 'project-one', $projects);
        $secondProject = $this->page('Project Two', 'project-two', $projects);
        $cause = DonationType::create([
            'name' => 'Community Fund',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Community Fund',
            'status' => 1,
        ]);
        $broad = $this->donation([
            'payment_cause' => $cause->uuid,
            'cause_uuid_snapshot' => $cause->uuid,
            'cause_name_snapshot' => $cause->name,
            'destination_type_snapshot' => 'restricted_fund',
            'destination_name_snapshot' => 'Community Fund',
            'amount' => '100.00',
            'payment_status' => 'Success',
        ]);
        $direct = $this->donation([
            'payment_cause' => $cause->uuid,
            'cause_uuid_snapshot' => $cause->uuid,
            'cause_name_snapshot' => $cause->name,
            'destination_type_snapshot' => 'page',
            'destination_uuid_snapshot' => $firstProject->uuid,
            'destination_name_snapshot' => $firstProject->name,
            'project_uuid_snapshot' => $firstProject->uuid,
            'project_name_snapshot' => $firstProject->name,
            'amount' => '100.00',
            'payment_status' => 'Success',
        ]);
        $allocator = $this->adminWith(['donations.allocate']);

        $page = $this->asAdmin($allocator)->get(route('donations.index'))->assertOk();
        $token = $this->allocationToken($page->getContent());
        $payload = [
            'request_token' => $token,
            'page_uuid' => $firstProject->uuid,
            'amount' => '30.00',
            'note' => 'Approved first project allocation.',
        ];
        $this->post(route('donations.allocate', $broad), $payload)
            ->assertSessionHasErrors('confirm_allocation');
        $this->assertDatabaseCount('donation_allocations', 0);
        $payload['confirm_allocation'] = '1';
        $this->post(route('donations.allocate', $broad), $payload)->assertRedirect();
        $this->post(route('donations.allocate', $broad), $payload)->assertRedirect();
        $this->assertDatabaseCount('donation_allocations', 1);

        $secondToken = $this->allocationToken(
            $this->get(route('donations.index'))->assertOk()->getContent()
        );
        $this->post(route('donations.allocate', $broad), [
            'request_token' => $secondToken,
            'page_uuid' => $secondProject->uuid,
            'amount' => '40.00',
            'note' => 'Approved second project allocation.',
            'confirm_allocation' => '1',
        ])->assertRedirect();
        $this->assertDatabaseCount('donation_allocations', 2);

        $thirdToken = $this->allocationToken(
            $this->get(route('donations.index'))->assertOk()->getContent()
        );
        $this->post(route('donations.allocate', $broad), [
            'request_token' => $thirdToken,
            'page_uuid' => $secondProject->uuid,
            'amount' => '30.01',
            'note' => 'This amount is over the remaining balance.',
            'confirm_allocation' => '1',
        ])->assertSessionHasErrors('amount');
        $this->assertSame(['30.00', '40.00'], DonationAllocation::query()->orderBy('id')->pluck('amount')->all());

        $otherCause = DonationType::create([
            'name' => 'Unrelated Fund',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Unrelated Fund',
            'status' => 1,
        ]);
        $this->donation([
            'payment_cause' => $otherCause->uuid,
            'cause_uuid_snapshot' => $otherCause->uuid,
            'cause_name_snapshot' => $otherCause->name,
            'destination_type_snapshot' => 'page',
            'destination_uuid_snapshot' => $secondProject->uuid,
            'destination_name_snapshot' => $secondProject->name,
            'project_uuid_snapshot' => $secondProject->uuid,
            'project_name_snapshot' => $secondProject->name,
            'amount' => '999.00',
            'payment_status' => 'Success',
        ]);

        $filtered = $this->get(route('donations.index', [
            'project_uuid' => $firstProject->uuid,
            'cause_uuid' => $cause->uuid,
            'status' => 'Success',
        ]))->assertOk();
        $filtered->assertSee('Amount attributed to selected project')
            ->assertSee('BDT 130.00')
            ->assertSee('Project One')
            ->assertDontSee('BDT 200.00')
            ->assertDontSee('BDT 999.00');

        $causeSummary = collect($filtered->viewData('causeAttribution'));
        $this->assertCount(1, $causeSummary);
        $selectedCause = $causeSummary->first();
        $this->assertSame([
            'key' => 'cause:' . strtolower((string) $cause->uuid),
            'name' => $cause->name,
            'amount' => '130.00',
            'donation_count' => 2,
            'percentage' => '100.00',
            'is_legacy' => false,
        ], $selectedCause);

        $this->assertSame([[
            'name' => $cause->name,
            'amount' => '130.00',
            'donation_count' => 2,
            'percentage' => '100.00',
        ]], $filtered->viewData('causeAttributionChart'));

        $allocation = DonationAllocation::query()->oldest()->firstOrFail();
        $this->assertSame($allocator->name, $allocation->allocated_by_name_snapshot);
        $allocatorName = $allocator->name;
        $allocator->delete();
        $viewer = $this->adminWith([]);
        $this->asAdmin($viewer)->get(route('donations.index'))
            ->assertOk()
            ->assertSee($allocatorName);

        $this->assertSame($direct->id, $direct->fresh()->id);
    }

    public function test_admin_cause_attribution_chart_uses_successful_snapshot_aggregates_without_donor_pii(): void
    {
        $privateName = 'Private Cause Report Donor';
        $privateEmail = 'private-cause-report@example.test';
        $privatePhone = '+8801799999999';
        $causes = collect();

        foreach (range(1, 9) as $index) {
            $snapshotName = 'Snapshot Cause ' . $index;
            $cause = DonationType::create([
                'name' => $snapshotName,
                'destination_type' => 'unrestricted',
                'status' => 1,
            ]);
            $causes->push($cause);

            $this->donation([
                'donor_name' => $privateName,
                'email' => $privateEmail,
                'phone' => $privatePhone,
                'payment_cause' => $cause->uuid,
                'cause_uuid_snapshot' => $cause->uuid,
                'cause_slug_snapshot' => $cause->slug,
                'cause_name_snapshot' => $snapshotName,
                'destination_type_snapshot' => 'unrestricted',
                'destination_name_snapshot' => $snapshotName,
                'amount' => number_format($index * 10, 2, '.', ''),
                'payment_status' => 'Success',
            ]);
        }

        $highestCause = $causes->last();
        $highestSnapshotName = 'Snapshot Cause 9';
        $this->donation([
            'donor_name' => $privateName,
            'email' => $privateEmail,
            'phone' => $privatePhone,
            'payment_cause' => $highestCause->uuid,
            'cause_uuid_snapshot' => $highestCause->uuid,
            'cause_slug_snapshot' => $highestCause->slug,
            'cause_name_snapshot' => $highestSnapshotName,
            'destination_type_snapshot' => 'unrestricted',
            'destination_name_snapshot' => $highestSnapshotName,
            'amount' => '999.00',
            'payment_status' => 'Pending',
        ]);
        $this->donation([
            'donor_name' => $privateName,
            'email' => $privateEmail,
            'phone' => $privatePhone,
            'cause_uuid_snapshot' => null,
            'cause_slug_snapshot' => 'unspecified-legacy-donation',
            'cause_name_snapshot' => 'Unspecified legacy donation',
            'destination_type_snapshot' => 'legacy_unspecified',
            'destination_name_snapshot' => 'Unresolved legacy designation — allocation blocked',
            'amount' => '5.00',
            'payment_status' => 'Success',
        ]);

        $highestCause->update(['name' => 'Renamed Current Cause']);

        $viewer = $this->adminWith([]);
        $response = $this->asAdmin($viewer)
            ->get(route('donations.index'))
            ->assertOk();

        $summary = collect($response->viewData('causeAttribution'));
        $this->assertCount(10, $summary);
        $this->assertSame(10, (int) $summary->sum('donation_count'));
        $this->assertSame(
            '455.00',
            number_format((float) $summary->sum(fn (array $row): float => (float) $row['amount']), 2, '.', '')
        );
        $this->assertFalse($summary->contains(
            fn (array $row): bool => $row['amount'] === '999.00'
        ));

        $highestRow = $summary->firstWhere('key', 'cause:' . strtolower((string) $highestCause->uuid));
        $this->assertNotNull($highestRow);
        $this->assertSame($highestSnapshotName, $highestRow['name']);
        $this->assertNotSame($highestCause->fresh()->name, $highestRow['name']);
        $this->assertSame('90.00', $highestRow['amount']);
        $this->assertSame(1, $highestRow['donation_count']);

        $legacyRow = $summary->firstWhere('key', 'legacy:unspecified-legacy-donation');
        $this->assertNotNull($legacyRow);
        $this->assertSame('Unresolved legacy designation', $legacyRow['name']);
        $this->assertSame('5.00', $legacyRow['amount']);
        $this->assertSame(1, $legacyRow['donation_count']);
        $this->assertTrue($legacyRow['is_legacy']);

        $chart = $response->viewData('causeAttributionChart');
        $this->assertIsArray($chart);
        $this->assertCount(9, $chart);
        $this->assertSame([
            'Snapshot Cause 9',
            'Snapshot Cause 8',
            'Snapshot Cause 7',
            'Snapshot Cause 6',
            'Snapshot Cause 5',
            'Snapshot Cause 4',
            'Snapshot Cause 3',
            'Snapshot Cause 2',
            'Other causes',
        ], array_column($chart, 'name'));
        $this->assertSame('15.00', $chart[8]['amount']);
        $this->assertSame(2, $chart[8]['donation_count']);

        foreach ($chart as $row) {
            $this->assertSame(
                ['name', 'amount', 'donation_count', 'percentage'],
                array_keys($row)
            );
        }
        $chartJson = json_encode($chart, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($privateName, $chartJson);
        $this->assertStringNotContainsString($privateEmail, $chartJson);
        $this->assertStringNotContainsString($privatePhone, $chartJson);

        $response
            ->assertSee('Successful giving by donor-selected cause')
            ->assertSee('Every donor-selected cause represented by successful gifts in the current filtered view.')
            ->assertSee('Donor-selected cause / accounting destination')
            ->assertSee('aria-describedby="cause-attribution-description cause-attribution-table-caption"', false)
            ->assertSee('admin-assets/assets/js/lib/chart-js/Chart.bundle.js', false);

        $pending = $this->get(route('donations.index', ['status' => 'Pending']))
            ->assertOk();
        $this->assertTrue(collect($pending->viewData('causeAttribution'))->isEmpty());
        $this->assertSame([], $pending->viewData('causeAttributionChart'));
        $pending
            ->assertSee('No successful donations match the current filters.')
            ->assertDontSee('id="cause-attribution-chart"', false);
    }

    public function test_attribution_is_immutable_and_media_and_destination_dependencies_are_protected(): void
    {
        $asset = MediaAsset::create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => 'media/cause-card.png',
            'original_name' => 'cause-card.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'bytes' => 100,
        ]);
        $program = $this->category('Education', 'education');
        $project = $this->page('Protected Project', 'protected-project', $program);
        $cause = DonationType::create([
            'name' => 'Protected cause',
            'destination_type' => 'page',
            'destination_page_uuid' => $project->uuid,
            'image_media_uuid' => $asset->uuid,
            'image' => $asset->url,
            'status' => 1,
        ]);
        $donation = $this->donation([
            'payment_cause' => $cause->uuid,
            'cause_uuid_snapshot' => $cause->uuid,
            'cause_name_snapshot' => $cause->name,
            'destination_type_snapshot' => 'page',
            'destination_uuid_snapshot' => $project->uuid,
            'destination_name_snapshot' => $project->name,
            'project_uuid_snapshot' => $project->uuid,
            'project_name_snapshot' => $project->name,
        ]);

        $references = app(MediaUsageService::class)->references($asset);
        $this->assertSame(1, $references['donation_causes']);
        $this->assertTrue(app(MediaUsageService::class)->inUse($asset));

        $legacyDirect = $this->donation([
            'destination_type_snapshot' => 'page',
            'destination_uuid_snapshot' => $project->uuid,
            'destination_name_snapshot' => $project->name,
            'project_uuid_snapshot' => null,
            'project_name_snapshot' => null,
        ]);
        (require database_path('migrations/2026_08_20_180600_backfill_direct_project_snapshots.php'))->up();
        $this->assertSame($project->uuid, $legacyDirect->fresh()->project_uuid_snapshot);
        $this->assertSame($project->name, $legacyDirect->fresh()->project_name_snapshot);

        $this->expectException(LogicException::class);
        try {
            $donation->update(['project_name_snapshot' => 'Rewritten project']);
        } finally {
            $request = Request::create('/admin/page/' . $project->uuid, 'DELETE');
            $request->setUserResolver(fn () => null);
            $this->assertSame(422, app(PageController::class)->destroy($project->uuid, $request)->getStatusCode());
        }
    }

    public function test_content_trash_retains_snapshot_referenced_causes_and_active_program_destinations(): void
    {
        $program = $this->category('Education', 'education');
        $cause = DonationType::create([
            'name' => 'Education Fund',
            'destination_type' => 'category',
            'destination_category_uuid' => $program->uuid,
            'status' => 1,
        ]);
        $this->donation([
            'payment_cause' => null,
            'cause_uuid_snapshot' => $cause->uuid,
            'cause_name_snapshot' => $cause->name,
            'destination_type_snapshot' => 'category',
            'destination_uuid_snapshot' => $program->uuid,
            'destination_name_snapshot' => $program->name,
            'payment_status' => 'Success',
        ]);

        $cause->delete();
        $trash = app(ContentTrashController::class);
        $this->assertSame(422, $trash->forceDestroy('donation-type', (string) $cause->id)->getStatusCode());

        $program->delete();
        $this->assertSame(422, $trash->forceDestroy('category', (string) $program->id)->getStatusCode());
    }

    public function test_gateway_rejects_unexpected_project_metadata_and_permission_id_is_collision_free(): void
    {
        $this->assertSame(232, MenuAction::where('link', 'donations.allocate')->value('id'));
        $this->assertSame(231, MenuAction::where('link', 'seo.canonical.external')->value('id'));

        $this->configureGateway();
        $transaction = SslCommerzTransaction::create([
            'tran_id' => 'UNEXPECTED-PROJECT-1',
            'status' => 'PENDING',
            'amount' => '100.00',
            'currency' => 'BDT',
            'requested_payment_method' => 'bkash',
            'opted_a' => 'cause-uuid',
            'opted_b' => 'bkash',
            'opted_c' => null,
            'opted_d' => 'cause-slug',
        ]);
        Http::fake([
            'sandbox.sslcommerz.com/validator/*' => Http::response([
                'status' => 'VALID',
                'tran_id' => $transaction->tran_id,
                'amount' => '100.00',
                'currency_type' => 'BDT',
                'value_a' => 'cause-uuid',
                'value_b' => 'bkash',
                'value_c' => (string) Str::uuid(),
                'value_d' => 'cause-slug',
            ]),
        ]);

        $this->assertNull(app(SSLCommerzService::class)->validateIpnAndVerify(
            $this->signedCallback($transaction->tran_id, 'VALIDATION-UNEXPECTED-PROJECT')
        ));
    }

    public function test_allocation_endpoint_denies_viewers_and_rejects_non_success_direct_and_ineligible_zakat_gifts(): void
    {
        $projects = $this->category('Projects', 'projects');
        $eligible = $this->page('Eligible Project', 'eligible-project', $projects, ['is_zakat_eligible' => true]);
        $notEligible = $this->page('Regular Project', 'regular-project', $projects);
        $broad = $this->donation([
            'destination_type_snapshot' => 'restricted_fund',
            'destination_name_snapshot' => 'General Fund',
            'payment_status' => 'Success',
        ]);
        $viewer = $this->adminWith([]);

        $this->asAdmin($viewer)->get(route('donations.index'))
            ->assertOk()
            ->assertDontSee('Record allocation');
        $this->post(route('donations.allocate', $broad), [
            'request_token' => $this->tokenFor($broad),
            'page_uuid' => $eligible->uuid,
            'amount' => '10.00',
            'note' => 'Viewer must not allocate this donation.',
            'confirm_allocation' => '1',
        ])->assertForbidden();

        $allocator = $this->adminWith(['donations.allocate']);
        $this->asAdmin($allocator);
        foreach (['Pending', 'Failed'] as $status) {
            $donation = $this->donation([
                'destination_type_snapshot' => 'restricted_fund',
                'destination_name_snapshot' => 'General Fund',
                'payment_status' => $status,
            ]);
            $this->post(route('donations.allocate', $donation), [
                'request_token' => $this->tokenFor($donation),
                'page_uuid' => $eligible->uuid,
                'amount' => '10.00',
                'note' => 'Payment state must be verified first.',
                'confirm_allocation' => '1',
            ])->assertSessionHasErrors('amount');
        }

        $direct = $this->donation([
            'destination_type_snapshot' => 'page',
            'destination_uuid_snapshot' => $eligible->uuid,
            'destination_name_snapshot' => $eligible->name,
            'project_uuid_snapshot' => $eligible->uuid,
            'project_name_snapshot' => $eligible->name,
            'payment_status' => 'Success',
        ]);
        $this->post(route('donations.allocate', $direct), [
            'request_token' => $this->tokenFor($direct),
            'page_uuid' => $eligible->uuid,
            'amount' => '10.00',
            'note' => 'Direct project gift must stay immutable.',
            'confirm_allocation' => '1',
        ])->assertSessionHasErrors('amount');

        $zakat = $this->donation([
            'purpose_key_snapshot' => 'zakat',
            'destination_type_snapshot' => 'restricted_fund',
            'destination_name_snapshot' => 'Zakat Fund',
            'payment_status' => 'Success',
        ]);
        $this->post(route('donations.allocate', $zakat), [
            'request_token' => $this->tokenFor($zakat),
            'page_uuid' => $notEligible->uuid,
            'amount' => '10.00',
            'note' => 'This project is not Zakat eligible.',
            'confirm_allocation' => '1',
        ])->assertSessionHasErrors('page_uuid');

        $this->assertDatabaseCount('donation_allocations', 0);

        $raceSafe = $this->donation([
            'purpose_key_snapshot' => 'zakat',
            'destination_type_snapshot' => 'restricted_fund',
            'destination_name_snapshot' => 'Zakat Fund',
            'payment_status' => 'Success',
        ]);
        $lockedDestinations = \Mockery::mock(DonationDestinationService::class)->makePartial();
        $lockedDestinations->shouldReceive('lockPageRows')
            ->once()
            ->with($eligible->uuid)
            ->ordered();
        $lockedDestinations->shouldReceive('allocationPages')
            ->once()
            ->with(\Mockery::on(fn (Donation $donation) => $donation->is($raceSafe)))
            ->ordered()
            ->andReturn(collect([$eligible]));
        $this->app->instance(DonationDestinationService::class, $lockedDestinations);
        app('router')->getRoutes()->getByName('donations.allocate')->flushController();
        $this->post(route('donations.allocate', $raceSafe), [
            'request_token' => $this->tokenFor($raceSafe),
            'page_uuid' => $eligible->uuid,
            'amount' => '10.00',
            'note' => 'Eligibility was revalidated under the project lock.',
            'confirm_allocation' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('donation_allocations', [
            'donation_id' => $raceSafe->id,
            'page_uuid' => $eligible->uuid,
            'amount' => '10.00',
        ]);
    }

    public function test_unresolved_legacy_gifts_are_visible_but_fail_closed_for_allocation(): void
    {
        $projects = $this->category('Projects', 'projects');
        $project = $this->page('Reviewed project', 'reviewed-project', $projects);
        $legacy = $this->donation([
            'cause_uuid_snapshot' => null,
            'cause_slug_snapshot' => 'unresolved-legacy-gift',
            'cause_name_snapshot' => 'Unresolved legacy gift',
            'destination_type_snapshot' => 'legacy_unspecified',
            'destination_name_snapshot' => 'Unresolved legacy designation — allocation blocked',
            'payment_status' => 'Success',
        ]);
        $sentinelWithRawPointer = $this->donation([
            'cause_uuid_snapshot' => (string) Str::uuid(),
            'cause_slug_snapshot' => 'unspecified-legacy-donation',
            'cause_name_snapshot' => 'Unspecified legacy donation',
            'destination_type_snapshot' => 'unrestricted',
            'destination_name_snapshot' => 'Where it is needed most',
            'payment_status' => 'Success',
        ]);
        $this->assertFalse(app(DonationDestinationService::class)->hasResolvedDesignation($legacy));
        $this->assertFalse(app(DonationDestinationService::class)->hasResolvedDesignation($sentinelWithRawPointer));
        $this->assertTrue(app(DonationDestinationService::class)->allocationPages($legacy)->isEmpty());

        $allocator = $this->adminWith(['donations.allocate']);
        $this->asAdmin($allocator)->get(route('donations.index'))
            ->assertOk()
            ->assertSee('Unresolved legacy gift')
            ->assertSee('Allocation blocked')
            ->assertDontSee(route('donations.allocate', $legacy), false);
        $this->post(route('donations.allocate', $legacy), [
            'request_token' => $this->tokenFor($legacy),
            'page_uuid' => $project->uuid,
            'amount' => '10.00',
            'note' => 'This must remain blocked without donor evidence.',
            'confirm_allocation' => '1',
        ])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('donation_allocations', 0);
    }

    public function test_active_destination_page_and_program_cannot_be_unpublished_or_deleted(): void
    {
        $program = $this->category('Education', 'education');
        $project = $this->page('Protected Project', 'protected-project', $program);
        DonationType::create([
            'name' => 'Program Fund',
            'description' => 'Visitor-ready program description.',
            'destination_type' => 'category',
            'destination_category_uuid' => $program->uuid,
            'status' => 1,
        ]);
        DonationType::create([
            'name' => 'Project Fund',
            'description' => 'Visitor-ready project description.',
            'destination_type' => 'page',
            'destination_page_uuid' => $project->uuid,
            'status' => 1,
        ]);

        $categoryStatus = Request::create('/admin/category/' . $program->uuid, 'PUT');
        $categoryStatus->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->assertSame(422, app(CategoryController::class)->status($categoryStatus, $program->uuid)->getStatusCode());
        $this->assertSame(422, app(CategoryController::class)->destroy(
            Request::create('/admin/category/' . $program->uuid, 'DELETE'),
            $program->uuid
        )->getStatusCode());

        $pageStatus = Request::create('/admin/page/' . $project->uuid, 'PUT');
        $pageStatus->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->assertSame(422, app(PageController::class)->status($pageStatus, $project->uuid)->getStatusCode());
        $this->assertSame(422, app(PageController::class)->destroy(
            $project->uuid,
            Request::create('/admin/page/' . $project->uuid, 'DELETE')
        )->getStatusCode());

        $this->assertTrue((bool) $program->fresh()->status);
        $this->assertTrue((bool) $project->fresh()->status);
    }

    public function test_create_only_cause_editor_has_guided_javascript_and_edit_only_role_cannot_publish_zakat(): void
    {
        $creator = $this->adminWith(['donationType.create'], 'donationType.index');
        $this->asAdmin($creator)->get(route('donationType.index'))
            ->assertOk()
            ->assertSee("destinationPrefixes.push('new_')", false)
            ->assertSee('function syncDestination', false)
            ->assertDontSee("destinationPrefixes.push('e_')", false);

        $draft = DonationType::create([
            'name' => 'Reviewed Zakat Candidate',
            'description' => 'Visitor-ready Zakat giving description.',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Zakat Fund',
            'status' => 0,
        ]);
        $editor = $this->adminWith(['donationType.edit'], 'donationType.index');
        $this->asAdmin($editor)->put(route('donationType.update'), [
            'id' => $draft->id,
            'name' => $draft->name,
            'description' => $draft->description,
            'purpose_key' => 'zakat',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Zakat Fund',
            'destination_category_uuid' => null,
            'destination_page_uuid' => null,
            'image_media_uuid' => null,
        ])->assertSessionHasErrors('purpose_key');
        $this->assertDatabaseHas('donation_types', [
            'id' => $draft->id,
            'purpose_key' => null,
            'status' => 0,
        ]);

        $placeholder = DonationType::create([
            'name' => 'Draft Placeholder',
            'description' => 'Draft giving option. Internal review needed.',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Draft Fund',
            'status' => 0,
        ]);
        $publisher = $this->adminWith(['donationType.status'], 'donationType.index');
        $this->asAdmin($publisher)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->putJson(route('donationType.status', $placeholder->id), ['id' => $placeholder->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'visitor-ready'));
        $this->assertFalse((bool) $placeholder->fresh()->status);
    }

    public function test_old_referenced_media_stays_in_picker_and_public_image_uses_authoritative_asset_url(): void
    {
        $assets = collect(range(1, 151))->map(fn (int $index) => MediaAsset::create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => 'media/cause-' . $index . '.png',
            'original_name' => 'cause-' . $index . '.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'bytes' => 100 + $index,
        ]));
        $oldest = $assets->first();
        $cause = DonationType::create([
            'name' => 'Media-safe Cause',
            'description' => 'A visitor-ready cause with managed media.',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Media-safe Fund',
            'image_media_uuid' => $oldest->uuid,
            'image' => 'https://old-storage.example/storage/' . $oldest->path,
            'status' => 1,
        ]);

        $editor = $this->adminWith(['donationType.edit'], 'donationType.index');
        $this->asAdmin($editor)->get(route('donationType.index'))
            ->assertOk()
            ->assertSee('value="' . $oldest->uuid . '"', false);

        $option = app(DonationDestinationService::class)->publicOption($cause->fresh('imageAsset'));
        $this->assertSame($oldest->fresh()->url, $option['image']);

        $legacy = DonationType::create([
            'name' => 'Legacy managed image',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Legacy Fund',
            'image_media_uuid' => null,
            'image' => 'https://former-host.example/storage/' . $oldest->path,
            'status' => 0,
        ]);
        $this->assertSame($oldest->url, app(DonationDestinationService::class)->causeImageUrl($legacy));

        $unsafe = DonationType::create([
            'name' => 'Unsafe image',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Unsafe Fund',
            'image' => 'https://attacker.example/not-managed.png',
            'status' => 0,
        ]);
        $this->assertSame('', app(DonationDestinationService::class)->causeImageUrl($unsafe));
    }

    public function test_translation_publication_sync_cannot_hide_the_last_public_fixed_destination(): void
    {
        $program = $this->category('Projects', 'projects');
        $source = $this->page('English funding draft', 'translation-funding-target', $program);
        $source->update(['status' => false, 'publication_status' => 'draft']);
        $target = $source->replicate();
        $target->language = 'bn';
        $target->name = 'প্রকাশিত অর্থায়ন প্রকল্প';
        $target->status = true;
        $target->publication_status = 'published';
        $target->visibility = 'public';
        $target->save();
        DonationType::create([
            'name' => 'Translation protected fund',
            'description' => 'A visitor-ready fixed destination.',
            'destination_type' => 'page',
            'destination_page_uuid' => $source->uuid,
            'status' => 1,
        ]);

        try {
            app(TranslationCenterService::class)->syncPublicationState('en', 'bn');
            $this->fail('Translation sync must not hide the final public version of a fixed funding destination.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('translations', $exception->errors());
            $this->assertStringContainsString('last public version', $exception->errors()['translations'][0]);
        }

        $this->assertTrue((bool) $target->fresh()->status);
        $this->assertSame('published', $target->fresh()->publication_status);
    }

    public function test_revision_restore_and_bulk_delete_cannot_bypass_active_fixed_destination_guard(): void
    {
        $program = $this->category('Projects', 'projects');
        $page = $this->page('Revision Protected Project', 'revision-protected', $program, [
            'is_zakat_eligible' => true,
        ]);
        $page->update(['status' => false, 'publication_status' => 'draft']);
        $draftRevision = app(PageRevisionService::class)->capture($page, 'Draft state before funding');
        $page->update([
            'status' => true,
            'publication_status' => 'published',
            'visibility' => 'public',
        ]);
        DonationType::create([
            'name' => 'Fixed Zakat Project',
            'description' => 'Visitor-ready fixed Zakat project description.',
            'purpose_key' => 'zakat',
            'destination_type' => 'page',
            'destination_page_uuid' => $page->uuid,
            'status' => 1,
        ]);

        try {
            app(PageRevisionService::class)->restore($page->fresh(), $draftRevision);
            $this->fail('A revision must not make an active fixed donation destination unavailable.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertStringContainsString('active donation destination', $exception->getMessage());
        }
        $this->assertSame('published', $page->fresh()->publication_status);
        $this->assertTrue((bool) $page->fresh()->status);

        $identityRevision = app(PageRevisionService::class)->capture($page->fresh(), 'Identity tamper regression');
        $snapshot = $identityRevision->snapshot;
        data_set($snapshot, 'page.uuid', (string) Str::uuid());
        data_set($snapshot, 'page.language', 'bn');
        data_set($snapshot, 'page.is_funding_project', false);
        data_set($snapshot, 'page.is_zakat_eligible', false);
        $identityRevision->update(['snapshot' => $snapshot]);
        app(PageRevisionService::class)->restore($page->fresh(), $identityRevision->fresh());
        $identitySafe = $page->fresh();
        $this->assertSame($page->uuid, $identitySafe->uuid);
        $this->assertSame('en', $identitySafe->language);
        $this->assertTrue((bool) $identitySafe->is_funding_project);
        $this->assertTrue((bool) $identitySafe->is_zakat_eligible);

        $destroyer = $this->adminWith(['page.destroy'], 'page.index');
        $this->asAdmin($destroyer)->deleteJson(route('page.bulk.destroy'), [
            'page_ids' => [$page->id],
        ])->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'active donation destination'));
        $this->assertDatabaseHas('pages', ['id' => $page->id, 'deleted_at' => null]);
    }

    public function test_bulk_duplicate_clears_funding_controls_while_translations_ignore_hidden_row_ids_and_keep_logical_controls(): void
    {
        $program = $this->category('Projects', 'projects');
        $source = $this->page('Zakat Project', 'zakat-project', $program, ['is_zakat_eligible' => true]);
        $editor = $this->adminWith(['page.edit'], 'page.index');

        $this->asAdmin($editor)->postJson(route('page.bulk.copy'), [
            'page_ids' => [$source->id],
            'action' => 'duplicate',
        ])->assertOk()->assertJsonPath('created', 1);
        $duplicate = Page::where('name', 'Zakat Project (Copy)')->firstOrFail();
        $this->assertNotSame($source->uuid, $duplicate->uuid);
        $this->assertFalse((bool) $duplicate->is_funding_project);
        $this->assertFalse((bool) $duplicate->is_zakat_eligible);

        $this->postJson(route('page.bulk.copy'), [
            'page_ids' => [$source->id],
            'action' => 'translate',
            'target_language' => 'bn',
        ])->assertOk()->assertJsonPath('created', 1);
        $translation = Page::where('uuid', $source->uuid)->where('language', 'bn')->firstOrFail();
        $this->assertTrue((bool) $translation->is_funding_project);
        $this->assertTrue((bool) $translation->is_zakat_eligible);

        $legacySource = $this->page('Legacy Eligible Project', 'legacy-eligible', $program, [
            'is_zakat_eligible' => true,
        ]);
        $unrelated = $this->page('Unrelated Bengali Page', 'unrelated-bengali', $program, [
            'language' => 'bn',
        ]);
        $this->put(route('page.update'), [
            'uuid' => $legacySource->uuid,
            'expected_version' => (int) $legacySource->fresh()->editor_version,
            'language' => ['en', 'bn'],
            'id' => ['en' => $legacySource->id, 'bn' => $unrelated->id],
            'name' => ['en' => $legacySource->name, 'bn' => 'যোগ্য প্রকল্প'],
            'sub_title' => ['en' => '', 'bn' => ''],
            'category_id' => ['en' => $program->id, 'bn' => $program->id],
            'description' => ['en' => '', 'bn' => ''],
            'inline_css' => ['en' => '', 'bn' => ''],
            'published_at' => ['en' => now()->toDateString(), 'bn' => now()->toDateString()],
            'name_enabled' => ['en' => 1, 'bn' => 1],
            'sub_title_enabled' => ['en' => 1, 'bn' => 1],
        ])->assertRedirect(route('page.index'))
            ->assertSessionHas('alert-type', 'success');
        $legacyTranslation = Page::where('uuid', $legacySource->uuid)->where('language', 'bn')->firstOrFail();
        $this->assertTrue((bool) $legacyTranslation->is_funding_project);
        $this->assertTrue((bool) $legacyTranslation->is_zakat_eligible);
        $this->assertDatabaseHas('pages', [
            'id' => $unrelated->id,
            'uuid' => $unrelated->uuid,
            'language' => 'bn',
            'name' => 'Unrelated Bengali Page',
        ]);
    }

    public function test_recognized_funding_category_backfill_repairs_all_locales_idempotently(): void
    {
        $categoryUuid = (string) Str::uuid();
        $englishCategory = Category::create([
            'uuid' => $categoryUuid,
            'name' => 'Our Causes',
            'slug' => 'our-causes',
            'language' => 'en',
            'status' => 1,
        ]);
        $banglaCategory = Category::create([
            'uuid' => $categoryUuid,
            'name' => 'আমাদের কার্যক্রম',
            'slug' => 'amader-karjokrom',
            'language' => 'bn',
            'status' => 1,
        ]);
        $ordinaryCategory = $this->category('About', 'about');
        $pageUuid = (string) Str::uuid();
        $englishPage = $this->page('Cause Project', 'cause-project', $englishCategory, [
            'uuid' => $pageUuid,
            'is_funding_project' => false,
        ]);
        $banglaPage = $this->page('কার্যক্রম', 'cause-project-bn', $banglaCategory, [
            'uuid' => $pageUuid,
            'language' => 'bn',
            'is_funding_project' => false,
        ]);
        $ordinaryPage = $this->page('About overview', 'about-overview', $ordinaryCategory, [
            'is_funding_project' => false,
        ]);
        $correction = require database_path('migrations/2026_08_20_181000_backfill_recognized_funding_category_pages.php');

        $correction->up();
        $correction->up();

        $this->assertTrue((bool) $englishPage->fresh()->is_funding_project);
        $this->assertTrue((bool) $banglaPage->fresh()->is_funding_project);
        $this->assertFalse((bool) $ordinaryPage->fresh()->is_funding_project);
    }

    public function test_financial_migrations_are_rerunnable_preserve_partial_history_and_refuse_destructive_rollbacks(): void
    {
        $guided = require database_path('migrations/2026_08_20_180000_add_guided_funding_destinations.php');
        $snapshots = require database_path('migrations/2026_08_20_180100_add_donation_attribution_snapshots.php');
        $allocationLedger = require database_path('migrations/2026_08_20_180200_create_donation_allocations.php');
        $actorSnapshots = require database_path('migrations/2026_08_20_180400_add_allocator_name_snapshot.php');
        $directBackfill = require database_path('migrations/2026_08_20_180600_backfill_direct_project_snapshots.php');
        $legacyCorrection = require database_path('migrations/2026_08_20_180700_fail_closed_unresolved_legacy_donations.php');
        $fundingClassification = require database_path('migrations/2026_08_20_180800_add_funding_project_classification.php');

        // Re-entry after MySQL auto-committed DDL must not duplicate columns,
        // indexes, suggestions, or overwrite an already-populated snapshot.
        $guided->up();
        $snapshots->up();
        $actorSnapshots->up();
        $fundingClassification->up();

        $program = $this->category('Projects', 'projects');
        $project = $this->page('Migration protected project', 'migration-protected', $program, [
            'is_zakat_eligible' => true,
        ]);
        $manual = $this->donation([
            'cause_uuid_snapshot' => (string) Str::uuid(),
            'cause_slug_snapshot' => 'manual-reconciliation',
            'cause_name_snapshot' => 'Documented historical fund',
            'destination_type_snapshot' => 'category',
            'destination_uuid_snapshot' => $program->uuid,
            'destination_name_snapshot' => 'Documented program',
            'project_uuid_snapshot' => null,
            'project_name_snapshot' => null,
        ]);
        $snapshots->up();
        $manual->refresh();
        $this->assertSame('manual-reconciliation', $manual->cause_slug_snapshot);
        $this->assertSame('category', $manual->destination_type_snapshot);
        $this->assertSame($program->uuid, $manual->destination_uuid_snapshot);

        $oldFallback = $this->donation([
            'payment_cause' => null,
            'cause_uuid_snapshot' => null,
            'cause_slug_snapshot' => 'unspecified-legacy-donation',
            'cause_name_snapshot' => 'Unspecified legacy donation',
            'purpose_key_snapshot' => null,
            'destination_type_snapshot' => 'unrestricted',
            'destination_uuid_snapshot' => null,
            'destination_name_snapshot' => 'Where it is needed most',
            'project_uuid_snapshot' => null,
            'project_name_snapshot' => null,
            'payment_status' => 'Success',
        ]);
        $nonlocalReconciled = $this->donation([
            'cause_uuid_snapshot' => (string) Str::uuid(),
            'cause_slug_snapshot' => 'unspecified-legacy-donation',
            'cause_name_snapshot' => 'Unspecified legacy donation',
            'destination_type_snapshot' => 'restricted_fund',
            'destination_name_snapshot' => 'Audited nonlocal designation',
        ]);
        $legacyCorrection->up();
        $this->assertSame('legacy_unspecified', $oldFallback->fresh()->destination_type_snapshot);
        $this->assertNull($oldFallback->fresh()->cause_uuid_snapshot);
        $this->assertSame('restricted_fund', $nonlocalReconciled->fresh()->destination_type_snapshot);
        $this->assertSame('Audited nonlocal designation', $nonlocalReconciled->fresh()->destination_name_snapshot);

        $direct = $this->donation([
            'destination_type_snapshot' => 'page',
            'destination_uuid_snapshot' => $project->uuid,
            'destination_name_snapshot' => 'Current project label',
            'project_uuid_snapshot' => null,
            'project_name_snapshot' => 'Previously reconciled project label',
        ]);
        $directBackfill->up();
        $this->assertSame($project->uuid, $direct->fresh()->project_uuid_snapshot);
        $this->assertSame('Previously reconciled project label', $direct->fresh()->project_name_snapshot);

        $allocation = DonationAllocation::create([
            'request_token' => (string) Str::uuid(),
            'donation_id' => $manual->id,
            'page_uuid' => $project->uuid,
            'page_name_snapshot' => $project->name,
            'amount' => '1.00',
            'note' => 'Migration rollback safety entry.',
            'allocated_by' => 1,
            'allocated_by_name_snapshot' => 'Migration safety administrator',
        ]);
        $this->assertNotNull($allocation->id);

        $this->assertRollbackRefused($guided, 'live financial configuration');
        $this->assertRollbackRefused($snapshots, 'financial history');
        $this->assertRollbackRefused($allocationLedger, 'allocation ledger');
        $this->assertRollbackRefused($actorSnapshots, 'audit trail');
        $this->assertRollbackRefused($fundingClassification, 'financial controls');
    }

    private function category(string $name, string $slug): Category
    {
        return Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'language' => 'en',
            'status' => 1,
        ]);
    }

    private function assertRollbackRefused(object $migration, string $messageFragment): void
    {
        try {
            $migration->down();
            $this->fail('A destructive financial rollback should have been refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($messageFragment, $exception->getMessage());
        }
    }

    private function page(string $name, string $slug, Category $category, array $overrides = []): Page
    {
        return Page::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'sub_title' => '',
            'slug' => $slug,
            'category_id' => $category->id,
            'language' => 'en',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->toDateString(),
            'is_funding_project' => true,
            'is_zakat_eligible' => false,
        ], $overrides));
    }

    private function tagPage(Page $page, Tag $tag): void
    {
        PageTagModule::create([
            'uuid' => (string) Str::uuid(),
            'page_id' => $page->id,
            'tag_id' => $tag->id,
        ]);
    }

    private function donation(array $overrides = []): Donation
    {
        return Donation::create(array_merge([
            'donor_name' => 'Audit Donor',
            'email' => 'audit-' . Str::random(8) . '@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'amount' => '100.00',
            'transaction_id' => 'AUDIT-' . Str::upper(Str::random(12)),
            'payment_status' => 'Pending',
            'cause_uuid_snapshot' => (string) Str::uuid(),
        ], $overrides));
    }

    private function configureGateway(): void
    {
        config()->set('sslcommerz.store_id', 'sandbox-store');
        config()->set('sslcommerz.store_password', 'sandbox-password');
        config()->set('sslcommerz.sandbox', true);
        config()->set('sslcommerz.payment_methods.bkash.enabled', true);
        config()->set('sslcommerz.payment_methods.bkash.gateway_filter', 'bkash');
        app()->forgetInstance(SSLCommerzService::class);
    }

    private function checkoutPayload(DonationType $cause, array $overrides = []): array
    {
        return array_merge([
            'amount' => '100.00',
            'donor_name' => 'Checkout Donor',
            'email' => 'checkout@example.test',
            'phone' => '+8801700000000',
            'address' => 'Dhaka',
            'payment_cause' => $cause->slug,
            'payment_method' => 'bkash',
            'checkout_key' => app(SSLCommerzService::class)->issueCheckoutKey(),
        ], $overrides);
    }

    private function adminWith(array $actionLinks, string $menuLink = 'donations.index'): Admin
    {
        $menu = AuthMenu::where('link', $menuLink)->firstOrFail();
        $actions = MenuAction::whereIn('link', $actionLinks)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $role = Role::create([
            'name' => 'Donation audit role ' . Str::random(8),
            'permission' => (string) $menu->id,
            'actionPermission' => implode(',', $actions),
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Allocator ' . Str::random(6),
            'username' => 'allocator-' . Str::random(10),
            'email' => 'allocator-' . Str::random(10) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);
    }

    private function asAdmin(Admin $admin): self
    {
        $this->actingAs($admin, 'admin');
        session()->put(Admin::SESSION_AUTH_VERSION, $admin->auth_version);

        return $this;
    }

    private function allocationToken(string $html): string
    {
        $this->assertSame(1, preg_match('/name="request_token" value="([^"]+)"/', $html, $matches));

        return html_entity_decode($matches[1], ENT_QUOTES);
    }

    private function tokenFor(Donation $donation): string
    {
        $nonce = (string) Str::uuid();

        return $nonce . '.' . hash_hmac('sha256', $donation->uuid . '|' . $nonce, (string) config('app.key'));
    }

    private function signedCallback(string $tranId, string $valId): array
    {
        $callback = [
            'tran_id' => $tranId,
            'val_id' => $valId,
            'status' => 'VALID',
            'verify_key' => 'status,tran_id,val_id',
        ];
        $hashData = [
            'status' => 'VALID',
            'tran_id' => $tranId,
            'val_id' => $valId,
            'store_passwd' => md5('sandbox-password'),
        ];
        ksort($hashData);
        $callback['verify_sign'] = md5(collect($hashData)
            ->map(fn ($value, $key) => $key . '=' . $value)
            ->implode('&'));

        return $callback;
    }
}
