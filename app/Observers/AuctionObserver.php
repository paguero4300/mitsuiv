<?php

namespace App\Observers;

use App\Models\Auction;
use App\Jobs\ProcessAuctionNotification;
use Illuminate\Support\Facades\Log;

class AuctionObserver
{
    public function __construct()
    {
        Log::info('AuctionObserver: Constructor ejecutado');
    }

    /**
     * Handle the Auction "created" event.
     */
    public function created(Auction $auction): void
    {
        Log::info('AuctionObserver: Método created llamado', [
            'auction_id' => $auction->id,
            'vehicle_id' => $auction->vehicle_id,
            'observer_class' => get_class($this),
            'model_class' => get_class($auction)
        ]);

        try {
            // Preparar los datos de la subasta
            $auctionData = [
                'id' => $auction->id,
                'vehiculo' => $auction->vehicle->plate ?? 'N/A',
                'fecha_inicio' => $auction->start_date->format('d/m/Y H:i'),
                'fecha_fin' => $auction->end_date->format('d/m/Y H:i'),
            ];

            Log::info('AuctionObserver: Datos preparados para notificación', $auctionData);

            // Disparar el job de notificación
            ProcessAuctionNotification::dispatch($auctionData, 'nueva_subasta');

            Log::info('AuctionObserver: Job de notificación despachado');

        } catch (\Exception $e) {
            Log::error('AuctionObserver: Error al procesar notificación', [
                'error' => $e->getMessage(),
                'auction_id' => $auction->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Handle the Auction "updated" event.
     */
    public function updated(Auction $auction): void
    {
        Log::info('AuctionObserver: Método updated llamado', [
            'auction_id' => $auction->id
        ]);
    }
} 