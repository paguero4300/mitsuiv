<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Auction;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessAuctionClosedNoOffersNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $auctionId;
    public $tries = 3;
    public $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(int $auctionId)
    {
        $this->auctionId = $auctionId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Configurar zona horaria de Lima/Perú
        date_default_timezone_set('America/Lima');
        
        Log::info('=== INICIANDO NOTIFICACIÓN DE SUBASTA CERRADA SIN OFERTAS ===', [
            'auction_id' => $this->auctionId
        ]);

        try {
            // Obtener datos de la subasta
            $auction = Auction::with(['vehicle', 'vehicle.brand', 'vehicle.model'])
                ->findOrFail($this->auctionId);
                
            Log::info('Datos de subasta recuperados', [
                'auction_id' => $auction->id,
                'vehicle_id' => $auction->vehicle_id
            ]);

            // Verificar que el canal email esté habilitado
            $emailEnabled = NotificationChannel::where('channel_type', 'email')
                ->where('is_enabled', true)
                ->exists();

            if (!$emailEnabled) {
                Log::warning('Canal de email no está habilitado. Cancelando notificación.');
                return;
            }
            
            Log::info('Canal de email está habilitado');

            // Obtener todos los tasadores con email
            $appraisers = User::role('tasador')
                ->whereNotNull('email')
                ->get();

            if ($appraisers->isEmpty()) {
                Log::warning('No se encontraron tasadores con email. Cancelando notificación.');
                return;
            }

            Log::info('Se encontraron tasadores para notificar', [
                'count' => $appraisers->count()
            ]);

            // Preparar datos para el correo
            $vehicle = $auction->vehicle;
            $brand = $vehicle->brand ? $vehicle->brand->value : 'No especificado';
            $model = $vehicle->model ? $vehicle->model->value : 'No especificado';
            
            $emailData = [
                'subject' => 'Subasta Cerrada Sin Ofertas: ' . $vehicle->plate,
                'auction' => [
                    'id' => $auction->id,
                    'placa' => $vehicle->plate,
                    'marca' => $brand,
                    'modelo' => $model,
                    'year' => $vehicle->year_made,
                    'fecha_inicio' => $auction->start_date->format('d/m/Y H:i'),
                    'fecha_fin' => $auction->end_date->format('d/m/Y H:i'),
                    'precio_base' => number_format($auction->base_price, 2),
                ]
            ];

            $notificacionesEnviadas = 0;
            $notificacionesFallidas = 0;

            // Enviar notificación a cada tasador
            foreach ($appraisers as $appraiser) {
                Log::info('Procesando tasador para notificación', [
                    'appraiser_id' => $appraiser->id,
                    'email' => $appraiser->email
                ]);

                // Verificar si ya se envió esta notificación
                $notificationSent = NotificationLog::where('event_type', 'subasta_sin_ofertas')
                    ->where('channel_type', 'email')
                    ->where('user_id', $appraiser->id)
                    ->where('reference_id', $this->auctionId)
                    ->exists();

                if ($notificationSent) {
                    Log::info('Notificación ya enviada a este tasador. Saltando.', [
                        'appraiser_id' => $appraiser->id
                    ]);
                    continue;
                }

                try {
                    // Enviar el correo
                    Mail::send('emails.auction-closed-no-offers', $emailData, function ($message) use ($appraiser, $emailData) {
                        $message->to($appraiser->email)
                            ->subject($emailData['subject']);
                    });

                    // Registrar la notificación como enviada
                    NotificationLog::create([
                        'event_type' => 'subasta_sin_ofertas',
                        'channel_type' => 'email',
                        'user_id' => $appraiser->id,
                        'reference_id' => (string) $this->auctionId,
                        'data' => $emailData,
                        'sent_at' => now(),
                    ]);

                    Log::info('Notificación enviada exitosamente', [
                        'appraiser_id' => $appraiser->id,
                        'email' => $appraiser->email
                    ]);

                    $notificacionesEnviadas++;

                } catch (\Exception $e) {
                    Log::error('Error al enviar notificación', [
                        'appraiser_id' => $appraiser->id,
                        'email' => $appraiser->email,
                        'error' => $e->getMessage()
                    ]);

                    $notificacionesFallidas++;
                }
            }

            Log::info('=== RESUMEN DE NOTIFICACIONES DE SUBASTA SIN OFERTAS ===', [
                'auction_id' => $this->auctionId,
                'enviadas' => $notificacionesEnviadas,
                'fallidas' => $notificacionesFallidas
            ]);

        } catch (\Exception $e) {
            Log::error('Error general al procesar notificación de subasta sin ofertas', [
                'auction_id' => $this->auctionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
} 