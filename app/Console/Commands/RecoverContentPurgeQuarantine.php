<?php

namespace App\Console\Commands;

use App\Services\ContentFileQuarantine;
use Illuminate\Console\Command;

final class RecoverContentPurgeQuarantine extends Command
{
    protected $signature = 'content:recover-purge-quarantine {--age=15 : Minimum age in minutes}';
    protected $description = 'Recover or discard media left in content purge quarantine after an interrupted request';

    public function handle(ContentFileQuarantine $quarantine): int
    {
        $age = filter_var($this->option('age'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1440],
        ]);
        if ($age === false) {
            $this->error('The age must be an integer from 1 to 1440 minutes.');

            return self::INVALID;
        }

        $result = $quarantine->recoverStale($age);
        $this->info("Recovered {$result['restored']} interrupted purge(s); discarded {$result['discarded']} committed purge(s).");

        return self::SUCCESS;
    }
}
