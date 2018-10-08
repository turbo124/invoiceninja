<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Utils;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $logFile = storage_path() . '/logs/cron.log';

        $schedule
            ->command('ninja:send-invoices')
            ->sendOutputTo($logFile)
            ->withoutOverlapping()
            ->hourly();

        $schedule
            ->command('ninja:send-reminders')
            ->sendOutputTo($logFile)
            ->daily();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
