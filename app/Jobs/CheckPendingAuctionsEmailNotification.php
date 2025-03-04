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

class CheckPendingAuctionsEmailNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de intentos máximos para este job
     */
    public $tries = 3;

    /**
     * Tiempo de espera entre reintentos (en segundos)
     */
    public $backoff = [30, 60, 120]; // 30s, 1min, 2min

    public function __construct()
    {
        //
    }

    public function handle(AuctionNotificationService $notificationService): void
    {
        Log::info('CheckPendingAuctionsEmailNotification: Iniciando verificación de subastas pendientes para notificación por email');

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
                // Evitar duplicados - verificamos notificaciones de email específicamente
                ->whereNotExists(function ($query) {
                    $query->select('id')
                        ->from('notification_logs')
                        ->whereColumn('reference_id', 'auctions.id')
                        ->where('event_type', 'nueva_subasta')
                        ->where('channel_type', 'email');
                });

            // Log del SQL exacto
            Log::info('SQL Query para notificaciones por email:', [
                'query' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'now_utc' => $now->format('Y-m-d H:i:s'),
                'window_start_utc' => $tenMinutesFromNow->format('Y-m-d H:i:s'),
                'window_end_utc' => $elevenMinutesFromNow->format('Y-m-d H:i:s')
            ]);

            $pendingAuctions = $query->get();

            Log::info('CheckPendingAuctionsEmailNotification: Subastas pendientes encontradas para notificación por email', [
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

                    Log::info('CheckPendingAuctionsEmailNotification: Notificando subasta por email', [
                        'auction_id' => $auction->id,
                        'minutos_para_inicio' => $timeToStart
                    ]);

                    // Llamar al nuevo método para envío de emails
                    $notificationService->sendNewAuctionEmailNotification($auctionData);

                } catch (\Exception $e) {
                    Log::error('CheckPendingAuctionsEmailNotification: Error al notificar subasta por email', [
                        'auction_id' => $auction->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            Log::info('CheckPendingAuctionsEmailNotification: Proceso de notificación por email completado', [
                'subastas_procesadas' => $pendingAuctions->count()
            ]);

        } catch (\Exception $e) {
            Log::error('CheckPendingAuctionsEmailNotification: Error general en el proceso de notificación por email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
} 