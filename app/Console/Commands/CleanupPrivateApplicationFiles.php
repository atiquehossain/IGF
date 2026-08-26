<?php

namespace App\Console\Commands;

use App\Services\PrivateFileCleanupService;
use Illuminate\Console\Command;

final class CleanupPrivateApplicationFiles extends Command
{
    protected $signature = 'applications:cleanup-private-files {--limit=100 : Maximum cleanup jobs to process}';
    protected $description = 'Retry durable deletion of superseded or privacy-removed applicant files';

    public function handle(PrivateFileCleanupService $cleanup): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000],
        ]);
        if ($limit === false) {
            $this->error('The limit must be an integer from 1 to 1000.');

            return self::INVALID;
        }

        $result = $cleanup->processPending($limit);
        $this->info("Claimed {$result['claimed']} cleanup job(s): {$result['deleted']} deleted, {$result['failed']} retained for retry.");

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
