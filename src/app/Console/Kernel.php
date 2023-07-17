<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule The application's command schedule
     */
    protected function schedule(Schedule $schedule): void
    {
        // This imports countries and the current set of IPv4 and IPv6 networks allocated to countries.
        $schedule->command('data:import')->dailyAt('05:00');

        // This notifies users about coming password expiration
        $schedule->command('password:retention')->dailyAt('06:00');

        // This applies wallet charges
        $schedule->command('wallet:charge')->everyFourHours();

        // This removes deleted storage files/file chunks from the filesystem
        $schedule->command('fs:expunge')->hourly();

        // This notifies users about an end of the trial period
        $schedule->command('wallet:trial-end')->dailyAt('07:00');

        // This collects some statistics into the database
        $schedule->command('data:stats:collector')->dailyAt('23:00');

        // https://laravel.com/docs/10.x/upgrade#redis-cache-tags
        $schedule->command('cache:prune-stale-tags')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        if (\app('env') == 'local') {
            $this->load(__DIR__ . '/Development');
        }

        include base_path('routes/console.php');
    }
}
