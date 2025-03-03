<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Auction;
use App\Models\AuctionAdjudication;
use App\Models\Bid;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessAuctionAdjudicatedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $adjudicationId;
    public $tries = 3;
    public $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(int $adjudicationId)
    {
        $this->adjudicationId = $adjudicationId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Configurar zona horaria de Lima/Perú
        date_default_timezone_set('America/Lima');
        
        Log::info('=== INICIANDO NOTIFICACIÓN DE SUBASTA ADJUDICADA ===', [
            'adjudication_id' => $this->adjudicationId
        ]);

        try {
            // Obtener datos de la adjudicación
            $adjudication = AuctionAdjudication::with(['auction', 'auction.vehicle', 'auction.vehicle.brand', 'auction.vehicle.model', 'reseller'])
                ->findOrFail($this->adjudicationId);
                
            $auction = $adjudication->auction;
            $reseller = $adjudication->reseller;
            
            Log::info('Datos de adjudicación recuperados', [
                'adjudication_id' => $adjudication->id,
                'auction_id' => $auction->id,
                'reseller_id' => $reseller->id
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

            // Contar total de pujas
            $bidCount = Bid::where('auction_id', $auction->id)->count();
            
            // Calcular el incremento sobre el precio base
            $priceIncrement = 0;
            $priceIncrementPercentage = 0;
            
            if ($auction->base_price > 0 && $auction->current_price > 0) {
                $priceIncrement = $auction->current_price - $auction->base_price;
                $priceIncrementPercentage = round(($priceIncrement / $auction->base_price) * 100, 2);
            }

            // Preparar datos para el correo
            $vehicle = $auction->vehicle;
            $brand = $vehicle->brand ? $vehicle->brand->value : 'No especificado';
            $model = $vehicle->model ? $vehicle->model->value : 'No especificado';
            
            $emailData = [
                'subject' => 'Subasta Adjudicada: ' . $vehicle->plate,
                'auction' => [
                    'id' => $auction->id,
                    'placa' => $vehicle->plate,
                    'marca' => $brand,
                    'modelo' => $model,
                    'year' => $vehicle->year_made,
                    'fecha_inicio' => $auction->start_date->format('d/m/Y H:i'),
                    'fecha_fin' => $auction->end_date->format('d/m/Y H:i'),
                    'fecha_adjudicacion' => $adjudication->created_at->format('d/m/Y H:i'),
                    'precio_base' => number_format($auction->base_price, 2),
                    'precio_final' => number_format($auction->current_price, 2),
                    'incremento' => number_format($priceIncrement, 2),
                    'incremento_porcentaje' => $priceIncrementPercentage,
                    'total_pujas' => $bidCount
                ],
                'reseller' => [
                    'id' => $reseller->id,
                    'nombre' => $reseller->name,
                    'email' => $reseller->email
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
                $notificationSent = NotificationLog::where('event_type', 'subasta_adjudicada')
                    ->where('channel_type', 'email')
                    ->where('user_id', $appraiser->id)
                    ->where('reference_id', $this->adjudicationId)
                    ->exists();

                if ($notificationSent) {
                    Log::info('Notificación ya enviada a este tasador. Saltando.', [
                        'appraiser_id' => $appraiser->id
                    ]);
                    continue;
                }

                try {
                    // Enviar el correo
                    Mail::send('emails.auction-adjudicated', $emailData, function ($message) use ($appraiser, $emailData) {
                        $message->to($appraiser->email)
                            ->subject($emailData['subject']);
                    });

                    // Registrar la notificación como enviada
                    NotificationLog::create([
                        'event_type' => 'subasta_adjudicada',
                        'channel_type' => 'email',
                        'user_id' => $appraiser->id,
                        'reference_id' => (string) $this->adjudicationId,
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

            Log::info('=== RESUMEN DE NOTIFICACIONES DE SUBASTA ADJUDICADA ===', [
                'adjudication_id' => $this->adjudicationId,
                'enviadas' => $notificacionesEnviadas,
                'fallidas' => $notificacionesFallidas
            ]);

        } catch (\Exception $e) {
            Log::error('Error general al procesar notificación de subasta adjudicada', [
                'adjudication_id' => $this->adjudicationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
} 