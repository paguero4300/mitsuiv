<?php

namespace App\Jobs;

use App\Models\Bid;
use App\Models\Auction;
use App\Services\AuctionNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOutbidNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $bidId;
    
    /**
     * Número de reintentos si falla el job
     */
    public $tries = 3;
    
    /**
     * Los tiempos de espera entre intentos (en segundos)
     */
    public $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     *
     * @param int $bidId ID de la puja que superó a otra
     * @return void
     */
    public function __construct(int $bidId)
    {
        $this->bidId = $bidId;
    }

    /**
     * Execute the job.
     *
     * @param AuctionNotificationService $notificationService
     * @return void
     */
    public function handle(AuctionNotificationService $notificationService): void
    {
        Log::info('========== PROCESO DE NOTIFICACIÓN PUJA SUPERADA INICIADO ==========', [
            'job_id' => isset($this->job) ? ($this->job->getJobId() ?? 'no_job_id') : 'job_no_disponible',
            'queue' => $this->queue ?? 'default',
            'bid_id' => $this->bidId,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);

        try {
            Log::info('ProcessOutbidNotification: Buscando puja', [
                'bid_id' => $this->bidId
            ]);
            
            // Cargar la puja con la relación de subasta y revendedor
            $bid = Bid::with(['auction', 'reseller'])->findOrFail($this->bidId);
            
            Log::info('ProcessOutbidNotification: Puja encontrada', [
                'bid_id' => $bid->id,
                'auction_id' => $bid->auction_id,
                'reseller_id' => $bid->reseller_id,
                'amount' => $bid->amount
            ]);
            
            // Buscar la puja anterior con mayor monto (la que fue superada)
            $previousBid = Bid::where('auction_id', $bid->auction_id)
                ->where('id', '<>', $bid->id)
                ->where('reseller_id', '<>', $bid->reseller_id) // Excluir pujas del mismo revendedor
                ->orderBy('amount', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();
            
            // Verificar si existe una puja anterior
            if (!$previousBid) {
                Log::info('ProcessOutbidNotification: No hay puja previa para superar - Cancelando', [
                    'auction_id' => $bid->auction_id
                ]);
                return;
            }
            
            Log::info('ProcessOutbidNotification: Puja previa encontrada', [
                'previous_bid_id' => $previousBid->id,
                'previous_bid_amount' => $previousBid->amount,
                'previous_reseller_id' => $previousBid->reseller_id
            ]);
            
            // Verificar que la nueva puja sea mayor que la anterior
            if ($bid->amount <= $previousBid->amount) {
                Log::warning('ProcessOutbidNotification: La nueva puja no supera a la anterior - Cancelando', [
                    'nueva_puja' => $bid->amount,
                    'puja_anterior' => $previousBid->amount
                ]);
                return;
            }
            
            // Cargar el vehículo de la subasta
            $auction = $bid->auction;
            $vehicle = $auction->vehicle;
            
            if (!$vehicle) {
                Log::warning('ProcessOutbidNotification: La subasta no tiene vehículo asociado', [
                    'auction_id' => $auction->id
                ]);
                return;
            }
            
            // Preparar los datos para la notificación
            $notificationData = [
                'id' => $auction->id . '_' . $previousBid->id, // ID único para la referencia
                'auction_id' => $auction->id,
                'bid_id' => $previousBid->id,
                'outbid_user_id' => $previousBid->reseller_id,
                'new_bid_id' => $bid->id,
                'new_bid_amount' => number_format($bid->amount, 2, '.', ','),
                'outbid_amount' => number_format($previousBid->amount, 2, '.', ','),
                'marca' => $vehicle->brand->value ?? 'N/A',
                'modelo' => $vehicle->model->value ?? 'N/A',
                'placa' => $vehicle->plate ?? 'N/A'
            ];
            
            Log::info('ProcessOutbidNotification: Datos preparados para notificación', [
                'notification_data' => $notificationData
            ]);
            
            // Verificar que el service tenga el método
            if (!method_exists($notificationService, 'sendOutbidNotification')) {
                Log::error('ProcessOutbidNotification: El método sendOutbidNotification no existe en AuctionNotificationService');
                throw new \Exception('El método sendOutbidNotification no existe en AuctionNotificationService');
            }
            
            // Enviar la notificación al usuario superado
            Log::info('ProcessOutbidNotification: Iniciando envío de notificación al usuario superado');
            $notificationService->sendOutbidNotification($notificationData);
            
            Log::info('========== PROCESO DE NOTIFICACIÓN PUJA SUPERADA COMPLETADO ==========', [
                'bid_id' => $this->bidId,
                'timestamp' => now()->format('Y-m-d H:i:s.u')
            ]);
            
        } catch (\Exception $e) {
            Log::error('========== ERROR EN PROCESO DE NOTIFICACIÓN PUJA SUPERADA ==========', [
                'bid_id' => $this->bidId,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->format('Y-m-d H:i:s.u')
            ]);
            
            throw $e; // Relanzar la excepción para que el job pueda ser reintentado
        }
    }
} 