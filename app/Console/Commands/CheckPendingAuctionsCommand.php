<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CheckPendingAuctionsNotification;
use Illuminate\Support\Facades\Log;

class CheckPendingAuctionsCommand extends Command
{
    protected $signature = 'auctions:check-pending';
    protected $description = 'Verifica y notifica subastas que han iniciado recientemente';

    public function handle()
    {
        $this->info('Iniciando verificación de subastas pendientes...');
        Log::info('CheckPendingAuctionsCommand: Iniciando comando');
        
        try {
            // Crear y configurar el job
            $job = new CheckPendingAuctionsNotification();
            
            Log::info('CheckPendingAuctionsCommand: Preparando job para despachar', [
                'job_class' => get_class($job),
                'fecha_ejecucion' => now()->timezone('America/Lima')->format('Y-m-d H:i:s')
            ]);

            // Despachar el job
            dispatch($job);
            
            $this->info('Job de verificación despachado correctamente.');
            Log::info('CheckPendingAuctionsCommand: Job despachado correctamente', [
                'job_class' => get_class($job),
                'fecha_despacho' => now()->timezone('America/Lima')->format('Y-m-d H:i:s')
            ]);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error al despachar el job: ' . $e->getMessage());
            Log::error('CheckPendingAuctionsCommand: Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'fecha_error' => now()->timezone('America/Lima')->format('Y-m-d H:i:s')
            ]);
            
            return Command::FAILURE;
        }
    }
} 