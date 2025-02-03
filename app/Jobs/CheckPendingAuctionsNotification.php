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

class CheckPendingAuctionsNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(AuctionNotificationService $notificationService): void
    {
        Log::info('CheckPendingAuctionsNotification: Iniciando verificación de subastas pendientes');

        try {
            $now = now()->timezone('America/Lima');
            
            // Definir ventanas de tiempo
            $tenMinutesFromNow = $now->copy()->addMinutes(10);
            $elevenMinutesFromNow = $now->copy()->addMinutes(11);
            $twentyFourHoursFromNow = $now->copy()->addHours(24);

            $query = Auction::query()
                // Subastas que no han pasado
                ->where('start_date', '>', $now)
                // Subastas dentro de las próximas 24 horas
                ->where('start_date', '<=', $twentyFourHoursFromNow)
                // Subastas en la ventana de notificación (10-11 minutos) o menos de 10 minutos
                ->where(function ($query) use ($now, $tenMinutesFromNow, $elevenMinutesFromNow) {
                    $query->whereBetween('start_date', [$tenMinutesFromNow, $elevenMinutesFromNow])
                          ->orWhere('start_date', '<=', $tenMinutesFromNow);
                })
                // Evitar duplicados
                ->whereNotExists(function ($query) {
                    $query->select('id')
                        ->from('notification_logs')
                        ->whereColumn('reference_id', 'auctions.id')
                        ->where('event_type', 'nueva_subasta')
                        ->where('channel_type', 'whatsapp');
                });

            // Log detallado solo para desarrollo
            // Log::info('CheckPendingAuctionsNotification: Consulta SQL a ejecutar', [
            //     'consulta_sql' => $query->toSql(),
            //     'parametros' => $query->getBindings(),
            //     'fecha_actual' => $now->format('Y-m-d H:i:s'),
            //     'ventana_10min' => $tenMinutesFromNow->format('Y-m-d H:i:s'),
            //     'ventana_11min' => $elevenMinutesFromNow->format('Y-m-d H:i:s'),
            //     'limite_24h' => $twentyFourHoursFromNow->format('Y-m-d H:i:s')
            // ]);

            $pendingAuctions = $query->get();

            Log::info('CheckPendingAuctionsNotification: Subastas pendientes encontradas', [
                'total' => $pendingAuctions->count()
            ]);

            foreach ($pendingAuctions as $auction) {
                try {
                    $timeToStart = $now->diffInMinutes($auction->start_date, false);
                    
                    $auctionData = [
                        'id' => $auction->id,
                        'vehiculo' => $auction->vehicle->plate ?? 'N/A',
                        'fecha_inicio' => $auction->start_date->timezone('America/Lima')->format('d/m/Y H:i'),
                        'fecha_fin' => $auction->end_date->timezone('America/Lima')->format('d/m/Y H:i'),
                    ];

                    Log::info('CheckPendingAuctionsNotification: Notificando subasta', [
                        'auction_id' => $auction->id,
                        'minutos_para_inicio' => $timeToStart
                    ]);

                    $notificationService->sendNewAuctionNotification($auctionData);

                } catch (\Exception $e) {
                    Log::error('CheckPendingAuctionsNotification: Error al notificar subasta', [
                        'auction_id' => $auction->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('CheckPendingAuctionsNotification: Proceso completado', [
                'subastas_procesadas' => $pendingAuctions->count()
            ]);

        } catch (\Exception $e) {
            Log::error('CheckPendingAuctionsNotification: Error general en el proceso', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
} 