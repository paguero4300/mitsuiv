<?php

namespace App\Observers;

use App\Models\Auction;
use App\Jobs\ProcessAuctionNotification;
use Illuminate\Support\Facades\Log;

class AuctionObserver
{
    public function __construct()
    {
        // Log de debug muy visible
        \Illuminate\Support\Facades\Log::channel('daily')->info('♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦ AUCTION OBSERVER INSTANCIADO ♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦', [
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
        
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
            'model_class' => get_class($auction),
            'start_date' => $auction->start_date->timezone('America/Lima')->format('Y-m-d H:i:s'),
            'end_date' => $auction->end_date->timezone('America/Lima')->format('Y-m-d H:i:s')
        ]);

        try {
            // Preparar los datos de la subasta
            $auctionData = [
                'id' => $auction->id,
                'vehiculo' => $auction->vehicle->plate ?? 'N/A',
                'fecha_inicio' => $auction->start_date->timezone('America/Lima')->format('d/m/Y H:i'),
                'fecha_fin' => $auction->end_date->timezone('America/Lima')->format('d/m/Y H:i'),
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
        // Log de debug muy visible
        \Illuminate\Support\Facades\Log::channel('daily')->info('♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦ AUCTION ACTUALIZADA ♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦♦', [
            'auction_id' => $auction->id,
            'old_status' => $auction->getOriginal('status_id'),
            'new_status' => $auction->status_id,
            'is_dirty' => $auction->isDirty('status_id'),
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
        
        Log::info('AuctionObserver: Método updated llamado', [
            'auction_id' => $auction->id,
            'is_dirty_status' => $auction->isDirty('status_id') ? 'SÍ' : 'NO',
            'old_status' => $auction->getOriginal('status_id'),
            'new_status' => $auction->status_id,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);

        // Verificar si la subasta cambió a estado ADJUDICADA
        if ($auction->isDirty('status_id') && $auction->status_id == \App\Models\AuctionStatus::ADJUDICADA) {
            Log::info('========== SUBASTA ADJUDICADA DETECTADA - INICIANDO PROCESO ==========', [
                'auction_id' => $auction->id,
                'old_status' => $auction->getOriginal('status_id'),
                'new_status' => $auction->status_id,
                'timestamp' => now()->format('Y-m-d H:i:s.u')
            ]);

            try {
                // Obtener la adjudicación para saber quién ganó
                Log::info('AuctionObserver: Buscando adjudicación aceptada', [
                    'auction_id' => $auction->id
                ]);
                
                $adjudication = $auction->adjudications()
                    ->where('status', 'accepted')
                    ->latest()
                    ->first();
                
                if ($adjudication && $adjudication->reseller_id) {
                    Log::info('AuctionObserver: Adjudicación encontrada y validada', [
                        'auction_id' => $auction->id,
                        'adjudication_id' => $adjudication->id,
                        'winner_id' => $adjudication->reseller_id,
                        'status' => $adjudication->status
                    ]);
                    
                    // Despachar el job para notificar a los perdedores
                    Log::info('AuctionObserver: Despachando job ProcessLostAuctionNotification', [
                        'auction_id' => $auction->id,
                        'winner_id' => $adjudication->reseller_id,
                        'delay' => '30 segundos'
                    ]);
                    
                    \App\Jobs\ProcessLostAuctionNotification::dispatch(
                        $auction->id,
                        $adjudication->reseller_id
                    )->delay(now()->addSeconds(30));
                    
                    Log::info('AuctionObserver: Job para perdedores despachado correctamente');
                    
                    // Despachar el job para notificar al ganador
                    Log::info('AuctionObserver: Despachando job ProcessWinAuctionNotification', [
                        'auction_id' => $auction->id,
                        'winner_id' => $adjudication->reseller_id,
                        'delay' => '30 segundos'
                    ]);
                    
                    \App\Jobs\ProcessWinAuctionNotification::dispatch(
                        $auction->id,
                        $adjudication->reseller_id
                    )->delay(now()->addSeconds(30));
                    
                    Log::info('AuctionObserver: Job para ganador despachado correctamente');
                } else {
                    Log::warning('AuctionObserver: No se encontró adjudicación válida o ganador para la subasta', [
                        'auction_id' => $auction->id,
                        'adjudication_exists' => $adjudication ? 'SÍ' : 'NO',
                        'reseller_id' => $adjudication ? $adjudication->reseller_id : null,
                        'status' => $adjudication ? $adjudication->status : null
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('========== ERROR EN PROCESAMIENTO DE ADJUDICACIÓN ==========', [
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'auction_id' => $auction->id,
                    'trace' => $e->getTraceAsString(),
                    'timestamp' => now()->format('Y-m-d H:i:s.u')
                ]);
            }
        }
    }
} 