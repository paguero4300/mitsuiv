<?php

namespace App\Console\Commands;

use App\Services\AuctionNotificationService;
use Illuminate\Console\Command;

class TestAuctionNotification extends Command
{
    protected $signature = 'auction:test-notification {event=nueva_subasta}';
    protected $description = 'Prueba el envío de notificaciones de subastas';

    public function handle(AuctionNotificationService $notificationService)
    {
        $event = $this->argument('event');

        $this->info("Probando notificación de subasta: {$event}");

        // Datos de prueba
        $auctionData = [
            'id' => '123', // ID de la subasta
            'vehiculo' => 'Toyota Corolla 2023 (Placa: ABC-123)',
            'fecha_inicio' => now()->format('d/m/Y H:i'),
            'fecha_fin' => now()->addDays(3)->format('d/m/Y H:i'),
        ];

        try {
            switch ($event) {
                case 'nueva_subasta':
                    $notificationService->sendNewAuctionNotification($auctionData);
                    break;
                default:
                    $this->error("Evento no soportado: {$event}");
                    return;
            }

            $this->info('Proceso de notificación completado');
            
            // Mostrar resumen
            $this->table(['Campo', 'Valor'], [
                ['Evento', $event],
                ['Rol', 'revendedor'],
                ['Vehículo', $auctionData['vehiculo']],
                ['Inicio', $auctionData['fecha_inicio']],
                ['Fin', $auctionData['fecha_fin']],
            ]);
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }
    }
} 