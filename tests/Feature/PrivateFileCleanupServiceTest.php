<?php

namespace Tests\Feature;

use App\Contracts\PrivateFileDeletion;
use App\Models\PrivateFileCleanupJob;
use App\Services\PrivateApplicationDocumentService;
use App\Services\PrivateFileCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ControllablePrivateFileDeletion;
use Tests\TestCase;

class PrivateFileCleanupServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(PrivateApplicationDocumentService::DISK);
    }

    public function test_failed_deletion_is_pii_free_durable_and_the_command_retries_it_successfully(): void
    {
        $path = 'documents/' . str_repeat('d', 48) . '.pdf';
        Storage::disk(PrivateApplicationDocumentService::DISK)->put($path, '%PDF-1.7 %%EOF');
        $deleter = new ControllablePrivateFileDeletion();
        $this->app->instance(PrivateFileDeletion::class, $deleter);
        $cleanup = app(PrivateFileCleanupService::class);

        $ids = DB::transaction(fn (): array => $cleanup->enqueueDocuments([
            ['disk' => PrivateApplicationDocumentService::DISK, 'path' => $path],
        ]));
        $result = $cleanup->processIds($ids);

        $this->assertSame(['claimed' => 1, 'deleted' => 0, 'failed' => 1], $result);
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertExists($path);
        $job = PrivateFileCleanupJob::query()->sole();
        $this->assertSame(1, $job->attempts);
        $this->assertNull($job->locked_at);
        $this->assertNotNull($job->last_failed_at);
        $this->assertSame(PrivateFileCleanupJob::ERROR_DELETE_FAILED, $job->last_error_code);
        $this->assertSame(
            ['id', 'disk', 'path', 'attempts', 'locked_at', 'last_failed_at', 'last_error_code', 'created_at', 'updated_at'],
            array_keys($job->getAttributes()),
            'Cleanup jobs must not gain applicant identity, original filenames, or raw exception text.',
        );

        $deleter->fail = false;
        $this->assertSame(0, Artisan::call('applications:cleanup-private-files', ['--limit' => 10]));
        $this->assertStringContainsString('1 deleted, 0 retained for retry', Artisan::output());
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertMissing($path);
        $this->assertDatabaseCount('private_file_cleanup_jobs', 0);
        $this->assertCount(2, $deleter->calls);
    }

    public function test_cleanup_command_rejects_an_unbounded_limit(): void
    {
        $this->assertSame(2, Artisan::call('applications:cleanup-private-files', ['--limit' => 1001]));
        $this->assertDatabaseCount('private_file_cleanup_jobs', 0);
    }

    public function test_physical_cleanup_waits_for_the_outermost_database_commit(): void
    {
        $path = 'documents/' . str_repeat('f', 48) . '.pdf';
        Storage::disk(PrivateApplicationDocumentService::DISK)->put($path, '%PDF-1.7 %%EOF');
        $deleter = new ControllablePrivateFileDeletion();
        $deleter->fail = false;
        $this->app->instance(PrivateFileDeletion::class, $deleter);
        $cleanup = app(PrivateFileCleanupService::class);

        DB::beginTransaction();
        try {
            $ids = $cleanup->enqueueDocuments([
                ['disk' => PrivateApplicationDocumentService::DISK, 'path' => $path],
            ]);
            $cleanup->processIdsAfterCommit($ids);

            Storage::disk(PrivateApplicationDocumentService::DISK)->assertExists($path);
            $this->assertDatabaseHas('private_file_cleanup_jobs', ['path' => $path, 'attempts' => 0]);
            $this->assertSame([], $deleter->calls);

            DB::commit();
        } catch (\Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $exception;
        }

        Storage::disk(PrivateApplicationDocumentService::DISK)->assertMissing($path);
        $this->assertDatabaseCount('private_file_cleanup_jobs', 0);
        $this->assertCount(1, $deleter->calls);
    }
}
