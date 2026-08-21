<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AnnualReport;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnualReportUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_upload_requires_a_bounded_real_pdf_and_uses_private_storage(): void
    {
        Storage::fake('local');
        $admin = $this->authorizedAdmin();

        $malicious = UploadedFile::fake()->createWithContent('shell.php', '<?php echo "owned";');
        $this->actingAs($admin, 'admin')->post(route('annual.report.store'), $this->payload($malicious))
            ->assertSessionHasErrors('annual_report_path');
        $this->assertDatabaseCount('annual_reports', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('annual-reports'));

        $oversize = UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf');
        $this->actingAs($admin, 'admin')->post(route('annual.report.store'), $this->payload($oversize, 'Large report'))
            ->assertSessionHasErrors('annual_report_path');

        $pdf = UploadedFile::fake()->createWithContent('reviewed.pdf', "%PDF-1.7\n1 0 obj\n<<>>\nendobj\n%%EOF");
        $this->actingAs($admin, 'admin')->post(route('annual.report.store'), $this->payload($pdf, 'Reviewed report'))
            ->assertRedirect(route('annual.report.index'));

        $report = \App\Models\AnnualReport::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}\.pdf$/', $report->image_path);
        Storage::disk('local')->assertExists('annual-reports/' . $report->image_path);
        $this->assertFalse(Storage::disk('public')->exists('photos/1/annual_report/' . $report->image_path));
    }

    public function test_replacing_a_legacy_shared_report_keeps_it_until_the_last_database_reference_changes(): void
    {
        Storage::fake('local');
        $admin = $this->authorizedAdmin();
        $sharedName = 'legacy-shared-report.pdf';
        Storage::disk('local')->put('annual-reports/' . $sharedName, "%PDF-1.7\nshared");
        $first = AnnualReport::create([
            'title' => 'First shared report',
            'slug' => 'first-shared-report',
            'published_at' => now()->format('Y-m-d'),
            'image_path' => $sharedName,
        ]);
        $second = AnnualReport::create([
            'title' => 'Second shared report',
            'slug' => 'second-shared-report',
            'published_at' => now()->format('Y-m-d'),
            'image_path' => $sharedName,
        ]);

        $replacement = UploadedFile::fake()->createWithContent('first-new.pdf', "%PDF-1.7\nfirst replacement");
        $this->actingAs($admin, 'admin')->put(route('annual.report.update'), [
            'id' => $first->id,
            'title' => 'First shared report updated',
            'published_at' => now()->format('Y-m-d'),
            'annual_report_path' => $replacement,
        ])->assertRedirect(route('annual.report.index'));

        Storage::disk('local')->assertExists('annual-reports/' . $sharedName);
        Storage::disk('local')->assertExists('annual-reports/' . $first->fresh()->image_path);
        $this->assertSame('first-shared-report', $first->fresh()->slug, 'Title edits must not bypass the managed permalink/redirect workflow.');

        $lastReplacement = UploadedFile::fake()->createWithContent('second-new.pdf', "%PDF-1.7\nsecond replacement");
        $this->actingAs($admin, 'admin')->put(route('annual.report.update'), [
            'id' => $second->id,
            'title' => 'Second shared report updated',
            'published_at' => now()->format('Y-m-d'),
            'annual_report_path' => $lastReplacement,
        ])->assertRedirect(route('annual.report.index'));

        Storage::disk('local')->assertMissing('annual-reports/' . $sharedName);
        Storage::disk('local')->assertExists('annual-reports/' . $second->fresh()->image_path);
        $this->assertSame('second-shared-report', $second->fresh()->slug);
    }

    private function payload(UploadedFile $file, string $title = 'Security report'): array
    {
        return [
            'title' => $title,
            'published_at' => now()->format('Y-m-d'),
            'annual_report_path' => $file,
        ];
    }

    private function authorizedAdmin(): Admin
    {
        $role = Role::create(['name' => 'Report editor', 'permission' => '', 'actionPermission' => '', 'serial' => '[]', 'status' => 1]);
        $actions = MenuAction::whereIn('link', ['annual.report.create', 'annual.report.edit'])
            ->pluck('id');
        $this->assertCount(2, $actions);
        $role->update(['actionPermission' => $actions->implode(',')]);

        return Admin::create([
            'name' => 'Report QA',
            'username' => 'report-qa',
            'email' => 'report-qa@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
