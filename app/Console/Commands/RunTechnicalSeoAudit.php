<?php

namespace App\Console\Commands;

use App\Services\TechnicalSeoAuditService;
use Illuminate\Console\Command;

final class RunTechnicalSeoAudit extends Command
{
    protected $signature = 'seo:audit';
    protected $description = 'Run the bounded, same-origin Technical SEO audit';

    public function handle(TechnicalSeoAuditService $audits): int
    {
        $run = $audits->run('command');
        $this->line("Status: {$run->status}; URLs: {$run->urls_checked}; findings: {$run->issues_found}");

        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
