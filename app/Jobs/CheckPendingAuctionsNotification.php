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
            // Todas las fechas en UTC
            $now = now()->timezone('UTC');
            
            // Definir ventanas de tiempo (en UTC)
            $tenMinutesFromNow = $now->copy()->addMinutes(10);
            $elevenMinutesFromNow = $now->copy()->addMinutes(11);
            $twentyFourHoursFromNow = $now->copy()->addHours(24);

            $query = Auction::query()
                // Subastas que no han pasado (convertir de Lima a UTC)
                ->whereRaw('CONVERT_TZ(start_date, "-05:00", "+00:00") > ?', [$now])
                // Subastas dentro de las próximas 24 horas
                ->whereRaw('CONVERT_TZ(start_date, "-05:00", "+00:00") <= ?', [$twentyFourHoursFromNow])
                // Subastas en la ventana de notificación (10-11 minutos) o menos de 10 minutos
                ->where(function ($query) use ($now, $tenMinutesFromNow, $elevenMinutesFromNow) {
                    $query->whereRaw('CONVERT_TZ(start_date, "-05:00", "+00:00") BETWEEN ? AND ?', 
                        [$tenMinutesFromNow, $elevenMinutesFromNow])
                        ->orWhereRaw('CONVERT_TZ(start_date, "-05:00", "+00:00") <= ?', [$tenMinutesFromNow]);
                })
                // Evitar duplicados
                ->whereNotExists(function ($query) {
                    $query->select('id')
                        ->from('notification_logs')
                        ->whereColumn('reference_id', 'auctions.id')
                        ->where('event_type', 'nueva_subasta')
                        ->where('channel_type', 'whatsapp');
                });

            // Log del SQL exacto
            Log::info('SQL Query:', [
                'query' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'now_utc' => $now->format('Y-m-d H:i:s'),
                'window_start_utc' => $tenMinutesFromNow->format('Y-m-d H:i:s'),
                'window_end_utc' => $elevenMinutesFromNow->format('Y-m-d H:i:s')
            ]);

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