<?php

namespace App\Jobs;

use App\Services\AuctionNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAuctionNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Definimos el número de intentos y el tiempo de espera entre reintentos
    public $tries = 3;
    public $backoff = [30, 60, 120]; // Espera 30s, luego 60s, luego 120s entre reintentos

    protected $auctionData;
    protected $eventType;

    public function __construct(array $auctionData, string $eventType = 'nueva_subasta')
    {
        // Validamos que los datos requeridos estén presentes
        if (!isset($auctionData['id']) || !isset($auctionData['vehiculo'])) {
            throw new \InvalidArgumentException('Datos de subasta incompletos');
        }

        $this->auctionData = $auctionData;
        $this->eventType = $eventType;
    }

    public function handle(AuctionNotificationService $notificationService): void
    {
        Log::info('ProcessAuctionNotification: Iniciando procesamiento', [
            'intento_numero' => $this->attempts(),
            'auction_id' => $this->auctionData['id'],
            'tipo_evento' => $this->eventType,
            'datos_subasta' => [
                'vehiculo' => $this->auctionData['vehiculo'],
                'marca' => $this->auctionData['marca'] ?? 'No definida',
                'modelo' => $this->auctionData['modelo'] ?? 'No definida',
                'version' => $this->auctionData['version'] ?? 'No definida',
                'anio' => $this->auctionData['anio'] ?? 'No definida',
                'kilometraje' => $this->auctionData['kilometraje'] ?? 'No definida',
                'fecha_inicio' => $this->auctionData['fecha_inicio'] ?? 'No definida',
                'fecha_fin' => $this->auctionData['fecha_fin'] ?? 'No definida'
            ]
        ]);

        // Verificar si faltan datos importantes y registrarlos
        $camposFaltantes = [];
        foreach (['marca', 'modelo', 'version', 'anio', 'kilometraje'] as $campo) {
            if (!isset($this->auctionData[$campo]) || $this->auctionData[$campo] == 'N/A') {
                $camposFaltantes[] = $campo;
            }
        }
        
        if (!empty($camposFaltantes)) {
            Log::warning('ProcessAuctionNotification: Algunos campos importantes están faltando o son N/A', [
                'auction_id' => $this->auctionData['id'],
                'campos_faltantes' => $camposFaltantes
            ]);
        }

        try {
            // Validamos que el tipo de evento sea válido
            if (!in_array($this->eventType, ['nueva_subasta'])) {
                throw new \InvalidArgumentException("Tipo de evento no soportado: {$this->eventType}");
            }

            // Procesamos según el tipo de evento
            switch ($this->eventType) {
                case 'nueva_subasta':
                    $notificationService->sendNewAuctionNotification($this->auctionData);
                    break;
                default:
                    throw new \InvalidArgumentException("Tipo de evento no implementado: {$this->eventType}");
            }
            
            Log::info('ProcessAuctionNotification: Notificación procesada exitosamente', [
                'auction_id' => $this->auctionData['id'],
                'tipo_evento' => $this->eventType,
                'intento_numero' => $this->attempts()
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessAuctionNotification: Error al procesar notificación', [
                'auction_id' => $this->auctionData['id'],
                'tipo_evento' => $this->eventType,
                'intento_numero' => $this->attempts(),
                'error' => [
                    'mensaje' => $e->getMessage(),
                    'codigo' => $e->getCode(),
                    'archivo' => $e->getFile(),
                    'linea' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ]);
            
            // Si hemos alcanzado el máximo de intentos, notificamos al sistema
            if ($this->attempts() >= $this->tries) {
                Log::critical('ProcessAuctionNotification: Máximo de intentos alcanzado', [
                    'auction_id' => $this->auctionData['id'],
                    'intentos_realizados' => $this->attempts()
                ]);
            }
            
            throw $e; // Relanzamos la excepción para que Laravel maneje el reintento
        }
    }

    /**
     * Maneja el fallo del job después de agotar todos los intentos
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('ProcessAuctionNotification: Job fallido definitivamente', [
            'auction_id' => $this->auctionData['id'],
            'tipo_evento' => $this->eventType,
            'error' => [
                'mensaje' => $exception->getMessage(),
                'codigo' => $exception->getCode()
            ],
            'datos_subasta' => $this->auctionData
        ]);
        
        // Aquí podrías agregar lógica adicional como:
        // - Enviar una notificación al equipo técnico
        // - Marcar la subasta con un estado especial
        // - Crear un ticket en el sistema de soporte
    }
}