<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;
use App\Jobs\CheckPendingAuctionsEmailNotification;

class Kernel extends ConsoleKernel
{
    

   
    protected function schedule(Schedule $schedule): void
    {
        // Programar el job de notificación por email para que se ejecute cada minuto
        $schedule->job(new CheckPendingAuctionsEmailNotification())->everyMinute();
        
        require base_path('routes/console.php');
    }


    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

   
} 