<?php

namespace App\Console\Commands;

use App\Services\ProductionPreflightService;
use Illuminate\Console\Command;

class RunProductionPreflight extends Command
{
    protected $signature = 'igf:production-preflight';

    protected $description = 'Fail unless the cached application configuration and routes are safe for production';

    public function handle(ProductionPreflightService $preflight): int
    {
        $checks = $preflight->evaluate();
        $failures = 0;

        foreach ($checks as $check) {
            $status = $check['passed'] ? '<info>PASS</info>' : '<error>FAIL</error>';
            $this->line("{$status}  {$check['label']}: {$check['message']}");
            if (!$check['passed']) {
                $failures++;
            }
        }

        if ($failures > 0) {
            $this->newLine();
            $this->error("Production preflight failed with {$failures} blocking check(s). The application must remain offline.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Production preflight passed.');

        return self::SUCCESS;
    }
}
