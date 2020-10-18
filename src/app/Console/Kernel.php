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
     * @param Schedule $schedule The application's command schedule
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // This command imports countries and the current set of IPv4 and IPv6 networks allocated to countries.
        $schedule->command('data:import')->dailyAt('05:00');

        $schedule->command('wallet:charge')->dailyAt('00:00');
        $schedule->command('wallet:charge')->dailyAt('04:00');
        $schedule->command('wallet:charge')->dailyAt('08:00');
        $schedule->command('wallet:charge')->dailyAt('12:00');
        $schedule->command('wallet:charge')->dailyAt('16:00');
        $schedule->command('wallet:charge')->dailyAt('20:00');

        // this is a laravel 8-ism
        //$schedule->command('wallet:charge')->everyFourHours();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        if (\app('env') != 'production') {
            $this->load(__DIR__ . '/Development');
        }

        include base_path('routes/console.php');
    }
}
