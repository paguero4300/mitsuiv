<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('auctions:update-statuses')->everyMinute();

        // Verificar subastas pendientes de notificación cada minuto
        $schedule->command('auctions:check-pending')
            ->everyMinute()
            ->withoutOverlapping()
            ->before(function () {
                Log::info('Scheduler: Iniciando verificación de subastas pendientes');
            })
            ->after(function () {
                Log::info('Scheduler: Finalizada verificación de subastas pendientes');
            })
            ->onFailure(function () {
                Log::error('Scheduler: Error al ejecutar verificación de subastas pendientes');
            });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    protected $commands = [
        Commands\TestCreateAuction::class,
        Commands\CreateDefaultCatalogValues::class,
        Commands\CheckPendingAuctionsCommand::class,
    ];
} 