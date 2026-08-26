<?php

namespace App\Services;

use App\Contracts\PrivateFileDeletion;
use App\Models\PrivateFileCleanupJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class PrivateFileCleanupService
{
    private const LEASE_MINUTES = 15;

    public function __construct(private PrivateFileDeletion $files)
    {
    }

    /**
     * Persist cleanup intent in the caller's transaction before document
     * metadata is removed.
     *
     * @param iterable<int, object|array<string,mixed>> $documents
     * @return list<int>
     */
    public function enqueueDocuments(iterable $documents): array
    {
        $ids = [];
        foreach ($documents as $document) {
            $disk = (string) data_get($document, 'disk');
            $path = (string) data_get($document, 'path');
            $this->assertContainedApplicantDocument($disk, $path);

            $job = PrivateFileCleanupJob::query()->firstOrCreate([
                'disk' => $disk,
                'path' => $path,
            ]);
            $ids[] = (int) $job->getKey();
        }

        return array_values(array_unique($ids));
    }

    /** @param list<int> $ids
     *  @return array{claimed:int,deleted:int,failed:int}
     */
    public function processIds(array $ids, int $limit = 100): array
    {
        if ($ids === []) {
            return ['claimed' => 0, 'deleted' => 0, 'failed' => 0];
        }

        return $this->process($limit, $ids);
    }

    /** @param list<int> $ids */
    public function processIdsAfterCommit(array $ids, int $limit = 100): void
    {
        if ($ids === []) {
            return;
        }

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn (): array => $this->processIds($ids, $limit));

            return;
        }

        $this->processIds($ids, $limit);
    }

    /** @return array{claimed:int,deleted:int,failed:int} */
    public function processPending(int $limit = 100): array
    {
        return $this->process($limit);
    }

    /** @param list<int>|null $ids
     *  @return array{claimed:int,deleted:int,failed:int}
     */
    private function process(int $limit, ?array $ids = null): array
    {
        $limit = max(1, min(1000, $limit));
        $claimedIds = DB::transaction(function () use ($limit, $ids): array {
            $query = PrivateFileCleanupJob::query()
                ->when($ids !== null, fn (Builder $query): Builder => $query->whereKey($ids))
                ->where(function (Builder $query): void {
                    $query->whereNull('locked_at')
                        ->orWhere('locked_at', '<=', now()->subMinutes(self::LEASE_MINUTES));
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->limit($limit);

            $jobs = $query->get();
            $lockedAt = now();
            foreach ($jobs as $job) {
                $job->forceFill([
                    'attempts' => (int) $job->attempts + 1,
                    'locked_at' => $lockedAt,
                    'last_error_code' => null,
                ])->save();
            }

            return $jobs->modelKeys();
        }, 3);

        $deleted = 0;
        $failed = 0;
        foreach ($claimedIds as $id) {
            $job = PrivateFileCleanupJob::query()->find($id);
            if (!$job) {
                continue;
            }

            try {
                $this->files->deleteStored((string) $job->disk, (string) $job->path);
                $job->delete();
                $deleted++;
            } catch (Throwable $exception) {
                $job->forceFill([
                    'locked_at' => null,
                    'last_failed_at' => now(),
                    'last_error_code' => PrivateFileCleanupJob::ERROR_DELETE_FAILED,
                ])->save();
                report($exception);
                $failed++;
            }
        }

        return ['claimed' => count($claimedIds), 'deleted' => $deleted, 'failed' => $failed];
    }

    private function assertContainedApplicantDocument(string $disk, string $path): void
    {
        if ($disk !== PrivateApplicationDocumentService::DISK
            || preg_match('#\Adocuments/[a-f0-9]{48}\.pdf\z#D', $path) !== 1) {
            throw new RuntimeException('The private cleanup target is invalid.');
        }
    }
}
