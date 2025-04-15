<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CheckPendingAuctionsEmailNotification;
use Illuminate\Support\Facades\Log;

class ProcessEmailNotificationsNow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:process-email-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa manualmente las notificaciones por email de subastas pendientes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando procesamiento manual de notificaciones por email de subastas pendientes...');
        
        try {
            Log::info('Comando test:process-email-notifications iniciado manualmente');
            
            // Crear y ejecutar el job directamente (sin ponerlo en cola)
            $job = new CheckPendingAuctionsEmailNotification();
            
            $this->info('Ejecutando job CheckPendingAuctionsEmailNotification...');
            $job->handle(app(\App\Services\AuctionNotificationService::class));
            
            $this->info('');
            $this->info('================================================');
            $this->info('PROCESO COMPLETADO EXITOSAMENTE');
            $this->info('================================================');
            $this->info('');
            $this->info('Para verificar los resultados, revise los logs del sistema');
            $this->info('Si no recibió notificaciones, verifique:');
            $this->info('1. Que existan subastas programadas para iniciar pronto');
            $this->info('2. Que existan revendedores con emails configurados');
            $this->info('3. Que los canales de notificación estén habilitados');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error al procesar notificaciones: {$e->getMessage()}");
            Log::error('Error en comando test:process-email-notifications: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
} 