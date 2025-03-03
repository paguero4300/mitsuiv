<?php

namespace App\Observers;

use App\Models\AuctionAdjudication;
use App\Jobs\ProcessAuctionAdjudicatedNotification;
use Illuminate\Support\Facades\Log;

class AdjudicationObserver
{
    public function __construct()
    {
        Log::info('♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦ ADJUDICATION OBSERVER INSTANCIADO ♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦', [
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
        
        Log::info('AdjudicationObserver: Constructor ejecutado');
    }

    /**
     * Handle the AuctionAdjudication "created" event.
     */
    public function created(AuctionAdjudication $adjudication): void
    {
        Log::info('AdjudicationObserver: Método created llamado', [
            'adjudication_id' => $adjudication->id,
            'auction_id' => $adjudication->auction_id,
            'reseller_id' => $adjudication->reseller_id,
            'status' => $adjudication->status,
            'observer_class' => get_class($this),
            'model_class' => get_class($adjudication)
        ]);

        // Si se crea con estado accepted, procesamos la notificación
        if ($adjudication->status === 'accepted') {
            $this->processAcceptedAdjudication($adjudication);
        }
    }

    /**
     * Handle the AuctionAdjudication "updated" event.
     */
    public function updated(AuctionAdjudication $adjudication): void
    {
        Log::info('AdjudicationObserver: Método updated llamado', [
            'adjudication_id' => $adjudication->id,
            'auction_id' => $adjudication->auction_id,
            'reseller_id' => $adjudication->reseller_id,
            'old_status' => $adjudication->getOriginal('status'),
            'new_status' => $adjudication->status,
            'is_dirty_status' => $adjudication->isDirty('status') ? 'SÍ' : 'NO',
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);

        // Verificar si la adjudicación cambió a estado accepted
        if ($adjudication->isDirty('status') && $adjudication->status === 'accepted') {
            $this->processAcceptedAdjudication($adjudication);
        }
    }

    /**
     * Procesa una adjudicación aceptada
     */
    private function processAcceptedAdjudication(AuctionAdjudication $adjudication): void
    {
        Log::info('========== ADJUDICACIÓN ACEPTADA DETECTADA - INICIANDO PROCESO DE NOTIFICACIÓN ==========', [
            'adjudication_id' => $adjudication->id,
            'auction_id' => $adjudication->auction_id,
            'reseller_id' => $adjudication->reseller_id,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);

        try {
            // Despachar el job para notificar a los tasadores
            Log::info('AdjudicationObserver: Despachando job ProcessAuctionAdjudicatedNotification', [
                'adjudication_id' => $adjudication->id,
                'delay' => '15 segundos'
            ]);
            
            ProcessAuctionAdjudicatedNotification::dispatch(
                $adjudication->id
            )->delay(now()->addSeconds(15));
            
            Log::info('AdjudicationObserver: Job ProcessAuctionAdjudicatedNotification despachado correctamente');
            
        } catch (\Exception $e) {
            Log::error('========== ERROR EN PROCESAMIENTO DE NOTIFICACIÓN DE ADJUDICACIÓN ==========', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'adjudication_id' => $adjudication->id,
                'auction_id' => $adjudication->auction_id,
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->format('Y-m-d H:i:s.u')
            ]);
        }
    }
} 