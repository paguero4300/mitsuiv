<?php

namespace App\Jobs;

use App\Models\Auction;
use App\Services\AuctionNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWinAuctionNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $auctionId;
    protected int $winnerId;
    
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
     * @param int $auctionId ID de la subasta adjudicada
     * @param int $winnerId ID del revendedor ganador
     * @return void
     */
    public function __construct(int $auctionId, int $winnerId)
    {
        $this->auctionId = $auctionId;
        $this->winnerId = $winnerId;
    }

    /**
     * Execute the job.
     *
     * @param AuctionNotificationService $notificationService
     * @return void
     */
    public function handle(AuctionNotificationService $notificationService): void
    {
        Log::info('========== PROCESO DE NOTIFICACIÓN SUBASTA GANADA INICIADO ==========', [
            'job_id' => isset($this->job) ? ($this->job->getJobId() ?? 'no_job_id') : 'job_no_disponible',
            'queue' => $this->queue ?? 'default',
            'auction_id' => $this->auctionId,
            'winner_id' => $this->winnerId,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);

        try {
            Log::info('ProcessWinAuctionNotification: Buscando subasta', [
                'auction_id' => $this->auctionId
            ]);
            
            // Comprobar si existe el modelo Auction
            if (!class_exists(Auction::class)) {
                Log::error('ProcessWinAuctionNotification: La clase Auction no existe');
                throw new \Exception('La clase Auction no existe');
            }

            // Cargar subasta con vehículo, adjudicación y oferta ganadora
            $auction = Auction::with(['vehicle', 'adjudications' => function($query) {
                $query->where('reseller_id', $this->winnerId)
                    ->where('status', 'accepted')
                    ->orderBy('created_at', 'desc');
            }])->findOrFail($this->auctionId);
            
            Log::info('ProcessWinAuctionNotification: Subasta encontrada', [
                'auction_id' => $auction->id,
                'status_id' => $auction->status_id,
                'vehicle_id' => $auction->vehicle_id ?? 'no_vehicle'
            ]);
            
            // Preparar los datos de la subasta
            $vehicle = $auction->vehicle;
            
            // Comprobar si el vehículo existe
            if (!$vehicle) {
                Log::warning('ProcessWinAuctionNotification: La subasta no tiene vehículo asociado', [
                    'auction_id' => $auction->id
                ]);
            }
            
            // Obtener información de la adjudicación
            $adjudication = $auction->adjudications->first();
            $winningAmount = $adjudication ? $adjudication->amount : $auction->current_price;
            $adjudicationDate = $adjudication ? $adjudication->created_at : now();
            
            // Formatear el monto ganador con dos decimales
            $formattedAmount = number_format($winningAmount, 2, '.', ',');
            
            $auctionData = [
                'id' => $auction->id,
                'vehiculo' => $vehicle ? ($vehicle->plate ?? 'Vehículo sin placa') : 'Vehículo sin placa',
                'modelo' => $vehicle && isset($vehicle->model) ? $vehicle->model->value : 'N/A',
                'anio' => $vehicle ? $vehicle->year : 'N/A',
                'placa' => $vehicle ? $vehicle->plate : 'N/A',
                'monto_ganador' => $formattedAmount,
                'fecha_adjudicacion' => $adjudicationDate->timezone('America/Lima')->format('d/m/Y H:i'),
                'winner_id' => $this->winnerId
            ];
            
            Log::info('ProcessWinAuctionNotification: Datos preparados', [
                'auction_data' => $auctionData
            ]);
            
            // Verificar que el service tenga el método
            if (!method_exists($notificationService, 'sendWinAuctionNotification')) {
                Log::error('ProcessWinAuctionNotification: El método sendWinAuctionNotification no existe en AuctionNotificationService');
                throw new \Exception('El método sendWinAuctionNotification no existe en AuctionNotificationService');
            }
            
            // Enviar la notificación
            Log::info('ProcessWinAuctionNotification: Iniciando envío de notificación al ganador');
            $notificationService->sendWinAuctionNotification($auctionData);
            
            Log::info('========== PROCESO DE NOTIFICACIÓN SUBASTA GANADA COMPLETADO ==========', [
                'auction_id' => $this->auctionId,
                'timestamp' => now()->format('Y-m-d H:i:s.u')
            ]);
            
        } catch (\Exception $e) {
            Log::error('========== ERROR EN PROCESO DE NOTIFICACIÓN SUBASTA GANADA ==========', [
                'auction_id' => $this->auctionId,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->format('Y-m-d H:i:s.u')
            ]);
            
            throw $e; // Relanzar la excepción para que el job pueda ser reintentado
        }
    }
} 