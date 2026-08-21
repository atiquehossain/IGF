<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // Recover media from interrupted permanent-delete transactions. The
        // command is idempotent and ignores fresh/incomplete manifests.
        $schedule->command('content:recover-purge-quarantine --age=15')
            ->everyTenMinutes()
            ->withoutOverlapping(30);

        // Retention remains fail-closed until both an approved per-record
        // period and the explicit automation switch are configured.
        $schedule->command('privacy:apply-retention --execute')
            ->dailyAt('02:10')
            ->withoutOverlapping(180)
            ->when(fn (): bool => (bool) config('privacy.automation_enabled'));

        $schedule->command('seo:audit')
            ->weeklyOn(1, '03:10')
            ->withoutOverlapping(180)
            ->when(fn (): bool => (bool) config('technical-seo.schedule_enabled'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
