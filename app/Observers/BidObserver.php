<?php

namespace App\Observers;

use App\Models\Bid;
use App\Jobs\ProcessOutbidNotification;
use Illuminate\Support\Facades\Log;

class BidObserver
{
    public function __construct()
    {
        Log::info('♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦ BID OBSERVER INSTANCIADO ♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦', [
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
        
        Log::info('BidObserver: Constructor ejecutado');
    }

    /**
     * Handle the Bid "created" event.
     */
    public function created(Bid $bid): void
    {
        Log::info('BidObserver: Método created llamado', [
            'bid_id' => $bid->id,
            'auction_id' => $bid->auction_id,
            'reseller_id' => $bid->reseller_id,
            'amount' => $bid->amount,
            'observer_class' => get_class($this),
            'model_class' => get_class($bid)
        ]);

        try {
            Log::info('BidObserver: Procesando nueva puja para notificación de puja superada', [
                'bid_id' => $bid->id,
                'auction_id' => $bid->auction_id,
                'reseller_id' => $bid->reseller_id,
                'amount' => $bid->amount
            ]);
            
            // Despachar el job de notificación
            Log::info('BidObserver: Despachando ProcessOutbidNotification job');
            ProcessOutbidNotification::dispatch($bid->id);
            
            Log::info('BidObserver: Job de notificación despachado exitosamente', [
                'bid_id' => $bid->id
            ]);
            
        } catch (\Exception $e) {
            Log::error('BidObserver: Error al despachar notificación de puja superada', [
                'bid_id' => $bid->id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->format('Y-m-d H:i:s.u')
            ]);
        }
    }
} 