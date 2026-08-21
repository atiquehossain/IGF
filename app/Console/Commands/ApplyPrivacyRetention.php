<?php

namespace App\Console\Commands;

use App\Services\PrivacyRetentionService;
use Illuminate\Console\Command;

final class ApplyPrivacyRetention extends Command
{
    protected $signature = 'privacy:apply-retention {--execute : Apply enabled policies; without this flag the command is a read-only preview}';
    protected $description = 'Preview or apply explicitly configured privacy retention policies';

    public function handle(PrivacyRetentionService $retention): int
    {
        $execute = (bool) $this->option('execute');
        $results = $retention->run($execute);
        $this->table(['Policy', 'State', 'Eligible', 'Processed'], collect($results)->map(
            fn (array $result, string $policy) => [
                $policy,
                $result['enabled'] ? ($execute ? 'executed' : 'preview') : 'disabled (no valid policy)',
                $result['eligible'],
                $result['processed'],
            ]
        )->values()->all());

        if (!$execute) {
            $this->warn('Preview only. Nothing was changed. Add --execute only after the client approves every configured retention period.');
        }

        return self::SUCCESS;
    }
}
