<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\Donation;
use App\Models\DonationAllocation;
use App\Models\DonationType;
use App\Models\Role;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonationReportingScalabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_history_is_aggregated_in_sql_without_hydrating_every_financial_record(): void
    {
        $admin = $this->reportViewer();
        $causes = collect(range(1, 3))->map(fn (int $index): DonationType => DonationType::create([
            'name' => "Scalable Cause {$index}",
            'description' => "Reviewed cause {$index}.",
            'destination_type' => 'restricted_fund',
            'destination_name' => "Scalable Fund {$index}",
            'status' => 1,
        ]))->values();
        $directProjectUuid = (string) Str::uuid();
        $allocatedProjectUuid = (string) Str::uuid();

        foreach (range(1, 240) as $index) {
            $cause = $causes[($index - 1) % $causes->count()];
            $isDirect = $index % 2 === 0;
            $donation = Donation::create([
                'donor_name' => "Scale donor {$index}",
                'email' => "scale-{$index}@example.test",
                'phone' => '+8801700000000',
                'address' => 'Dhaka',
                'payment_cause' => $cause->uuid,
                'cause_uuid_snapshot' => $cause->uuid,
                'cause_slug_snapshot' => $cause->slug,
                'cause_name_snapshot' => $cause->name,
                'destination_type_snapshot' => $isDirect ? 'page' : 'restricted_fund',
                'destination_uuid_snapshot' => $isDirect ? $directProjectUuid : null,
                'destination_name_snapshot' => $isDirect ? 'Direct Project' : $cause->destination_name,
                'project_uuid_snapshot' => $isDirect ? $directProjectUuid : null,
                'project_name_snapshot' => $isDirect ? 'Direct Project' : null,
                'amount' => '10.00',
                'transaction_id' => "SCALE-{$index}",
                'payment_status' => 'Success',
            ]);

            if (!$isDirect) {
                DonationAllocation::create([
                    'request_token' => (string) Str::uuid(),
                    'donation_id' => $donation->id,
                    'page_uuid' => $allocatedProjectUuid,
                    'page_name_snapshot' => 'Allocated Project',
                    'amount' => '4.00',
                    'note' => 'Approved scalability regression allocation.',
                    'allocated_by' => $admin->id,
                    'allocated_by_name_snapshot' => $admin->name,
                ]);
            }
        }

        $donationsRetrieved = 0;
        $allocationsRetrieved = 0;
        $queries = [];
        $donationEvent = 'eloquent.retrieved: ' . Donation::class;
        $allocationEvent = 'eloquent.retrieved: ' . DonationAllocation::class;
        Event::listen($donationEvent, function () use (&$donationsRetrieved): void {
            $donationsRetrieved++;
        });
        Event::listen($allocationEvent, function () use (&$allocationsRetrieved): void {
            $allocationsRetrieved++;
        });
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower((string) preg_replace('/\s+/', ' ', $query->sql));
        });

        try {
            $response = $this->actingAs($admin, 'admin')
                ->get(route('donations.index'))
                ->assertOk();
        } finally {
            Event::forget($donationEvent);
            Event::forget($allocationEvent);
        }

        $causeSummary = collect($response->viewData('causeAttribution'));
        $projectSummary = collect($response->viewData('projectAttribution'));
        $this->assertCount(3, $causeSummary);
        $this->assertSame(240, (int) $causeSummary->sum('donation_count'));
        $this->assertSame(
            '2400.00',
            number_format((float) $causeSummary->sum(fn (array $row): float => (float) $row['amount']), 2, '.', '')
        );
        $this->assertSame('1200.00', $projectSummary->firstWhere('uuid', $directProjectUuid)['direct_amount']);
        $this->assertSame('480.00', $projectSummary->firstWhere('uuid', $allocatedProjectUuid)['allocated_amount']);
        $this->assertLessThanOrEqual(
            20,
            $donationsRetrieved,
            'The report may hydrate only the current paginated donation records.'
        );
        $this->assertLessThanOrEqual(
            20,
            $allocationsRetrieved,
            'The report may hydrate allocations only for the current donation page.'
        );
        $financialQueries = collect($queries)->filter(
            fn (string $sql): bool => str_contains($sql, 'donations')
                || str_contains($sql, 'donation_allocations')
        );
        $this->assertLessThanOrEqual(25, $financialQueries->count());
        $aggregateQueries = collect($queries)->filter(
            fn (string $sql): bool => str_contains($sql, 'group by')
                && str_contains($sql, 'sum(')
                && (str_contains($sql, 'cause_uuid_snapshot') || str_contains($sql, 'project_uuid'))
        );
        $this->assertGreaterThanOrEqual(
            2,
            $aggregateQueries->count(),
            'Cause and project attribution must remain database-side grouped aggregates.'
        );
    }

    private function reportViewer(): Admin
    {
        $menu = AuthMenu::query()->where('link', 'donations.index')->firstOrFail();
        $role = Role::create([
            'name' => 'Scalable donation reporter ' . Str::random(8),
            'permission' => (string) $menu->id,
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Scalable Report Viewer',
            'username' => 'reporter-' . Str::random(10),
            'email' => 'reporter-' . Str::random(10) . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);
    }
}
