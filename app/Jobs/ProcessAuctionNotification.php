<?php

namespace App\Jobs;

use App\Services\AuctionNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAuctionNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $auctionData;
    protected $eventType;

    public function __construct(array $auctionData, string $eventType = 'nueva_subasta')
    {
        $this->auctionData = $auctionData;
        $this->eventType = $eventType;
    }

    public function handle(AuctionNotificationService $notificationService): void
    {
        Log::info('ProcessAuctionNotification: Iniciando procesamiento', [
            'auction_id' => $this->auctionData['id'],
            'type' => $this->eventType
        ]);

        try {
            switch ($this->eventType) {
                case 'nueva_subasta':
                    $notificationService->sendNewAuctionNotification($this->auctionData);
                    break;
                // Aquí se pueden agregar más tipos de eventos
            }
            
            Log::info('ProcessAuctionNotification: Notificación procesada exitosamente', [
                'auction_id' => $this->auctionData['id'],
                'data' => $this->auctionData
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessAuctionNotification: Error al procesar notificación', [
                'error' => $e->getMessage(),
                'auction_id' => $this->auctionData['id']
            ]);
            
            throw $e;
        }
    }
} 