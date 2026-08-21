<?php

namespace Tests\Feature;

use App\Models\AnnualReport;
use App\Models\NoticeBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicReleaseBoundaryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_future_events_and_reports_are_absent_from_every_public_discovery_and_detail_path(): void
    {
        Carbon::setTestNow('2026-08-21 12:00:00');

        $pastEvent = $this->event('Released boundary event', 'released-boundary-event', now()->subMinute());
        $futureEvent = $this->event('Scheduled boundary event', 'scheduled-boundary-event', now()->addHour());
        $pastReport = $this->report('Released boundary report', 'released-boundary-report', now()->subMinute());
        $futureReport = $this->report('Scheduled boundary report', 'scheduled-boundary-report', now()->addHour());

        $this->withHeaders($this->inertiaHeaders())->get(route('frontend.events'))
            ->assertOk()
            ->assertJsonPath('props.properties.total_count', 1)
            ->assertJsonCount(1, 'props.data.items')
            ->assertJsonPath('props.data.items.0.id', $pastEvent->id);
        $this->get(route('frontend.event', ['slug' => $futureEvent->slug]))->assertNotFound();

        $this->withHeaders($this->inertiaHeaders())->get(route('search', ['search' => 'boundary']))
            ->assertOk()
            ->assertJsonPath('props.properties.total_count', 2)
            ->assertJsonFragment(['name' => $pastEvent->title])
            ->assertJsonFragment(['name' => $pastReport->title])
            ->assertJsonMissing(['name' => $futureEvent->title])
            ->assertJsonMissing(['name' => $futureReport->title]);

        $this->withHeaders($this->inertiaHeaders())->get(route('frontend.annual_report.index'))
            ->assertOk()
            ->assertJsonPath('props.properties.total', 1)
            ->assertJsonCount(1, 'props.data.items')
            ->assertJsonPath('props.data.items.0.id', $pastReport->id);
        $this->get(route('frontend.annual_report.show', ['slug' => $futureReport->slug]))->assertNotFound();
        $this->get(route('frontend.annual_report.download', ['slug' => $futureReport->slug]))->assertNotFound();

        $this->assertSame([$pastEvent->id], NoticeBoard::query()->publiclyReleased()->pluck('id')->all());
        $this->assertSame([$pastReport->id], AnnualReport::query()->publiclyReleased()->pluck('id')->all());
    }

    public function test_notice_attachments_are_private_release_gated_and_never_cacheable(): void
    {
        Carbon::setTestNow('2026-08-21 12:00:00');
        Storage::fake('local');
        Storage::fake('public');

        $released = $this->event('Released attachment', 'released-attachment', now()->subMinute(), 'released.pdf');
        $scheduled = $this->event('Scheduled attachment', 'scheduled-attachment', now()->addHour(), 'scheduled.pdf');
        Storage::disk('local')->put('notice-attachments/released.pdf', '%PDF-released');
        Storage::disk('local')->put('notice-attachments/scheduled.pdf', '%PDF-scheduled');

        $response = $this->get(route('notice.download', ['filename' => $released->file_path]))->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->get(route('notice.pdfViewer', ['filename' => $released->file_path]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('notice.download', ['filename' => $scheduled->file_path]))->assertNotFound();
        $this->get(route('notice.pdfViewer', ['filename' => $scheduled->file_path]))->assertNotFound();
    }

    public function test_upgrade_migration_moves_existing_notice_attachments_off_the_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $notice = $this->event('Legacy attachment', 'legacy-attachment', now()->subMinute(), 'legacy.pdf');
        Storage::disk('public')->put('photos/1/notice_board/legacy.pdf', '%PDF-private-after-upgrade');

        $migration = require database_path('migrations/2026_08_21_125000_move_notice_attachments_to_private_storage.php');
        $migration->up();

        $this->assertSame('legacy.pdf', $notice->fresh()->file_path);
        Storage::disk('public')->assertMissing('photos/1/notice_board/legacy.pdf');
        Storage::disk('local')->assertExists('notice-attachments/legacy.pdf');
        $this->assertSame(
            '%PDF-private-after-upgrade',
            Storage::disk('local')->get('notice-attachments/legacy.pdf')
        );

        $migration->down();
        Storage::disk('public')->assertMissing('photos/1/notice_board/legacy.pdf');
        Storage::disk('local')->assertExists('notice-attachments/legacy.pdf');
    }

    private function event(string $title, string $slug, Carbon $publishedAt, ?string $filePath = null): NoticeBoard
    {
        return NoticeBoard::query()->create([
            'title' => $title,
            'slug' => $slug,
            'description' => $title . ' details',
            'notice_type' => 'notice-board',
            'language' => 'en',
            'published_at' => $publishedAt,
            'file_path' => $filePath,
            'order_by' => 10,
            'status' => 1,
        ]);
    }

    private function report(string $title, string $slug, Carbon $publishedAt): AnnualReport
    {
        return AnnualReport::query()->create([
            'title' => $title,
            'slug' => $slug,
            'description' => $title . ' details',
            'notice_type' => 'annual-report',
            'language' => 'en',
            'published_at' => $publishedAt,
            'order_by' => 9,
            'status' => 1,
        ]);
    }

    /** @return array<string, string> */
    private function inertiaHeaders(): array
    {
        $manifest = public_path('build/manifest.json');

        return array_filter([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : null,
        ]);
    }
}
