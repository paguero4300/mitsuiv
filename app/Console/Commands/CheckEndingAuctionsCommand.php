<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CheckEndingAuctionsNotification;
use Illuminate\Support\Facades\Log;

class CheckEndingAuctionsCommand extends Command
{
    protected $signature = 'auctions:check-ending';
    protected $description = 'Verifica y notifica subastas que están por terminar (15 minutos antes)';

    public function handle()
    {
        $this->info('Iniciando verificación de subastas por terminar...');
        Log::info('CheckEndingAuctionsCommand: Iniciando comando');
        
        try {
            // Crear y configurar el job
            $job = new CheckEndingAuctionsNotification();
            
            Log::info('CheckEndingAuctionsCommand: Preparando job para despachar', [
                'job_class' => get_class($job),
                'fecha_ejecucion' => now()->timezone('America/Lima')->format('Y-m-d H:i:s')
            ]);

            // Despachar el job
            dispatch($job);
            
            $this->info('Job de verificación de subastas por terminar despachado correctamente.');
            Log::info('CheckEndingAuctionsCommand: Job despachado correctamente', [
                'job_class' => get_class($job),
                'fecha_despacho' => now()->timezone('America/Lima')->format('Y-m-d H:i:s')
            ]);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error al despachar el job: ' . $e->getMessage());
            Log::error('CheckEndingAuctionsCommand: Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'fecha_error' => now()->timezone('America/Lima')->format('Y-m-d H:i:s')
            ]);
            
            return Command::FAILURE;
        }
    }
} 