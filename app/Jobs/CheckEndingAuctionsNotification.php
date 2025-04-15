<?php

namespace App\Jobs;

use App\Models\Auction;
use App\Models\NotificationLog;
use App\Services\AuctionNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckEndingAuctionsNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AuctionNotificationService $notificationService): void
    {
        Log::info('CheckEndingAuctionsNotification: Iniciando verificación de subastas próximas a finalizar');

        try {
            // Todas las fechas en UTC
            $now = now()->timezone('UTC');
            
            // Definir ventana de tiempo exacta (15-16 minutos antes del fin)
            $fifteenMinutesFromNow = $now->copy()->addMinutes(15);
            $sixteenMinutesFromNow = $now->copy()->addMinutes(16);

            Log::info('CheckEndingAuctionsNotification: Ventana de tiempo para notificaciones', [
                'now_utc' => $now->format('Y-m-d H:i:s'),
                'window_start' => $fifteenMinutesFromNow->format('Y-m-d H:i:s'),
                'window_end' => $sixteenMinutesFromNow->format('Y-m-d H:i:s')
            ]);
            
            // Buscar subastas activas que terminan en 15-16 minutos
            $query = Auction::query()
                // Subastas con estado "activa" (ID 3 para subastas activas)
                ->where('status_id', 3) 
                // Que aún no hayan terminado
                ->whereRaw('CONVERT_TZ(end_date, "-05:00", "+00:00") > ?', [$now])
                // Pero que terminen en 15-16 minutos
                ->whereRaw('CONVERT_TZ(end_date, "-05:00", "+00:00") BETWEEN ? AND ?', [
                    $fifteenMinutesFromNow, 
                    $sixteenMinutesFromNow
                ])
                // Evitar duplicados usando la tabla notification_logs
                ->whereNotExists(function ($query) {
                    $query->select('id')
                        ->from('notification_logs')
                        ->whereColumn('reference_id', 'auctions.id')
                        ->where('event_type', 'subasta_por_terminar')
                        ->where('channel_type', 'whatsapp');
                });

            // Log del SQL exacto
            Log::info('CheckEndingAuctionsNotification: SQL Query:', [
                'query' => $query->toSql(),
                'bindings' => $query->getBindings()
            ]);

            // Cargar las subastas con sus vehículos
            $endingAuctions = $query->with('vehicle.brand', 'vehicle.model')->get();

            Log::info('CheckEndingAuctionsNotification: Subastas próximas a finalizar encontradas', [
                'total' => $endingAuctions->count()
            ]);

            // Procesar cada subasta
            foreach ($endingAuctions as $auction) {
                try {
                    // Verificar si la subasta tiene vehículo asociado
                    if (!$auction->vehicle) {
                        Log::warning('CheckEndingAuctionsNotification: Subasta sin vehículo asociado - Saltando', [
                            'auction_id' => $auction->id
                        ]);
                        continue;
                    }

                    // Preparar datos para la notificación
                    $auctionData = [
                        'id' => $auction->id,
                        'placa' => $auction->vehicle->plate ?? 'N/A',
                        'marca' => $auction->vehicle->brand->value ?? 'N/A',
                        'modelo' => $auction->vehicle->model->value ?? 'N/A',
                        'oferta_actual' => number_format($auction->current_price ?? $auction->base_price, 2, '.', ','),
                        'minutos_restantes' => 15
                    ];

                    Log::info('CheckEndingAuctionsNotification: Notificando subasta por terminar', [
                        'auction_id' => $auction->id,
                        'vehicle' => $auction->vehicle->plate
                    ]);

                    // Enviar la notificación
                    $notificationService->sendEndingAuctionNotification($auctionData);

                } catch (\Exception $e) {
                    Log::error('CheckEndingAuctionsNotification: Error al notificar subasta', [
                        'auction_id' => $auction->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            Log::info('CheckEndingAuctionsNotification: Proceso completado', [
                'subastas_procesadas' => $endingAuctions->count()
            ]);

        } catch (\Exception $e) {
            Log::error('CheckEndingAuctionsNotification: Error general en el proceso', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
} 