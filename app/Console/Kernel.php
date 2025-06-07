<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Update month options setiap bulan pada hari pertama jam 00:01
        $schedule->command('month:update')
                 ->monthlyOn(1, '00:01')
                 ->timezone('Asia/Kuala_Lumpur')
                 ->description('Auto update month options untuk filter');

        // Optional: Jalankan juga setiap hari untuk memastikan data terkini
        // $schedule->command('month:update')->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
