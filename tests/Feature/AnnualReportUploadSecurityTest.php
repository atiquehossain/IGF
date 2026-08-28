<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AnnualReport;
use App\Models\AuthMenu;
use App\Models\MediaAsset;
use App\Models\MenuAction;
use App\Models\Role;
use App\Services\MediaUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_admin_can_create_update_and_clear_public_report_fields_without_replacing_the_private_pdf(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = $this->authorizedAdmin();
        $firstCover = $this->imageAsset('media/reports/first-cover.webp', 'First cover.webp');
        $secondCover = $this->imageAsset('media/reports/second-cover.png', 'Second cover.png');

        $pdf = UploadedFile::fake()->createWithContent('annual.pdf', "%PDF-1.7\nreport");
        $this->actingAs($admin, 'admin')->post(route('annual.report.store'), [
            ...$this->payload($pdf, 'Community outcomes 2025'),
            'sub_title' => 'A year shaped with communities',
            'description' => 'Programs, governance, and audited stewardship.',
            'publisher_name' => 'Ignite Research Team',
            'url' => 'https://reports.example.test/community-outcomes',
            'cover_image_path' => $firstCover->path,
            'order_by' => 40,
        ])->assertRedirect(route('annual.report.index'));

        $report = AnnualReport::query()->firstOrFail();
        $privatePdf = $report->image_path;
        $this->assertSame($firstCover->path, $report->cover_image_path);
        $this->assertSame('A year shaped with communities', $report->sub_title);
        $this->assertSame('Programs, governance, and audited stewardship.', $report->description);
        $this->assertSame('Ignite Research Team', $report->publisher_name);
        $this->assertSame('https://reports.example.test/community-outcomes', $report->url);
        $this->assertSame(1, app(MediaUsageService::class)->references($firstCover)['annual_reports']);
        Storage::disk('local')->assertExists('annual-reports/' . $privatePdf);

        $this->actingAs($admin, 'admin')->put(route('annual.report.update'), [
            'id' => $report->id,
            'title' => $report->title,
            'published_at' => now()->format('Y-m-d'),
            'sub_title' => 'Updated subtitle',
            'description' => 'Updated public summary.',
            'publisher_name' => 'Impact and Learning Unit',
            'url' => 'https://reports.example.test/community-outcomes-v2',
            'cover_image_path' => $secondCover->path,
            'order_by' => 50,
        ])->assertRedirect(route('annual.report.index'));

        $report->refresh();
        $this->assertSame($privatePdf, $report->image_path);
        $this->assertSame($secondCover->path, $report->cover_image_path);
        $this->assertSame('Updated subtitle', $report->sub_title);
        Storage::disk('local')->assertExists('annual-reports/' . $privatePdf);

        $this->actingAs($admin, 'admin')->put(route('annual.report.update'), [
            'id' => $report->id,
            'title' => $report->title,
            'published_at' => now()->format('Y-m-d'),
            'sub_title' => '',
            'description' => '',
            'publisher_name' => '',
            'url' => '',
            'cover_image_path' => '',
        ])->assertRedirect(route('annual.report.index'));

        $report->refresh();
        $this->assertNull($report->cover_image_path);
        $this->assertNull($report->sub_title);
        $this->assertNull($report->description);
        $this->assertNull($report->publisher_name);
        $this->assertNull($report->url);
        $this->assertSame($privatePdf, $report->image_path);
        $this->assertArrayNotHasKey('annual_reports', app(MediaUsageService::class)->references($secondCover));
        Storage::disk('local')->assertExists('annual-reports/' . $privatePdf);
    }

    public function test_admin_create_and_edit_forms_expose_every_public_report_field_and_managed_cover_picker(): void
    {
        Storage::fake('public');
        $admin = $this->authorizedAdmin();
        $cover = $this->imageAsset('media/reports/form-cover.webp', 'Form cover.webp');
        $report = AnnualReport::create([
            'title' => 'Editable annual report',
            'slug' => 'editable-annual-report',
            'published_at' => now(),
            'language' => 'en',
            'image_path' => 'private-report.pdf',
            'cover_image_path' => $cover->path,
            'status' => 0,
        ]);

        foreach ([
            route('annual.report.create'),
            route('annual.report.edit', $report->id),
        ] as $url) {
            $this->actingAs($admin, 'admin')->get($url)
                ->assertOk()
                ->assertSee('name="sub_title"', false)
                ->assertSee('name="description"', false)
                ->assertSee('name="publisher_name"', false)
                ->assertSee('name="url"', false)
                ->assertSee('name="cover_image_path"', false)
                ->assertSee('Form cover.webp');
        }
    }

    public function test_legacy_admin_report_bookmark_redirects_to_a_useful_filtered_index(): void
    {
        $admin = $this->authorizedAdmin();
        $report = AnnualReport::create([
            'title' => 'Bookmarked accountability report',
            'slug' => 'bookmarked-accountability-report',
            'published_at' => now(),
            'language' => 'en',
            'image_path' => 'private-report.pdf',
            'status' => 0,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('annual.report.show', $report->id))
            ->assertRedirect(route('annual.report.index', ['search' => 'Bookmarked accountability report']));
        $this->actingAs($admin, 'admin')
            ->get(route('annual.report.show', 999999))
            ->assertNotFound();
    }

    public function test_admin_rejects_unmanaged_cover_paths_and_unsafe_source_urls_before_storing_a_pdf(): void
    {
        Storage::fake('local');
        $admin = $this->authorizedAdmin();
        $pdf = UploadedFile::fake()->createWithContent('annual.pdf', "%PDF-1.7\nreport");

        $this->actingAs($admin, 'admin')->post(route('annual.report.store'), [
            ...$this->payload($pdf),
            'cover_image_path' => '../../.env',
            'url' => 'http://reports.example.test/insecure-source',
        ])->assertSessionHasErrors(['cover_image_path', 'url']);

        $this->assertDatabaseCount('annual_reports', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('annual-reports'));
    }

    public function test_public_listing_and_detail_use_the_managed_cover_with_a_pdf_safe_fallback(): void
    {
        Storage::fake('local');
        Storage::fake('public', ['url' => url('/storage')]);
        $cover = $this->imageAsset('media/reports/public-cover.webp', 'Public cover.webp');
        Storage::disk('local')->put('annual-reports/public-report.pdf', "%PDF-1.7\nreport");
        $report = AnnualReport::create([
            'title' => 'Public impact report',
            'sub_title' => 'Public subtitle',
            'description' => 'A public, editable summary.',
            'slug' => 'public-impact-report',
            'publisher_name' => 'Impact Team',
            'published_at' => now()->subDay(),
            'language' => 'en',
            'url' => 'https://reports.example.test/public-impact',
            'image_path' => 'public-report.pdf',
            'cover_image_path' => $cover->path,
            'file_type' => 'application/pdf',
            'file_size' => 1024,
            'status' => 1,
        ]);
        $this->assertSame(url('/storage/' . $cover->path), Storage::disk('public')->url($cover->path));
        $coverUrl = '/storage/' . $cover->path;

        $this->get(route('frontend.annual_report.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('annual-report')
                ->where('data.items.0.title', 'Public impact report')
                ->where('data.items.0.sub_title', 'Public subtitle')
                ->where('data.items.0.summary', 'A public, editable summary.')
                ->where('data.items.0.publisher_name', 'Impact Team')
                ->where('data.items.0.image_url', $coverUrl)
            );

        $detail = $this->get(route('frontend.annual_report.show', ['slug' => $report->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('annual-report-detail')
                ->where('data.report.image_url', $coverUrl)
                ->where('data.report.source_url', 'https://reports.example.test/public-impact')
                ->where('contentSeo.og_image', url($coverUrl))
            );
        $reportNode = collect($detail->viewData('page')['props']['contentSeo']['schema_markup']['@graph'])
            ->firstWhere('@type', 'Report');
        $this->assertSame(url($coverUrl), $reportNode['image']);

        $report->update(['cover_image_path' => 'media/reports/missing.webp']);
        $this->get(route('frontend.annual_report.show', ['slug' => $report->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('data.report.image_url', null));

        $report->update([
            'cover_image_path' => null,
            'image_path' => 'https://tracking.example.test/legacy-cover.webp',
        ]);
        $this->get(route('frontend.annual_report.show', ['slug' => $report->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('data.report.image_url', null));
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
        $menu = AuthMenu::where('link', 'annual.report.index')->firstOrFail();
        $role->update([
            'permission' => (string) $menu->id,
            'actionPermission' => $actions->implode(','),
        ]);

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

    private function imageAsset(string $path, string $name): MediaAsset
    {
        Storage::disk('public')->put($path, 'image-bytes');

        return MediaAsset::create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => $path,
            'original_name' => $name,
            'mime_type' => str_ends_with($path, '.png') ? 'image/png' : 'image/webp',
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'bytes' => 11,
            'locale' => '*',
        ]);
    }
}
