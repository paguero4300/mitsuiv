<?php

namespace App\Services;

use App\Models\User;
use App\Models\NotificationChannel;
use App\Models\NotificationSetting;
use App\Models\NotificationLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AuctionNotificationService
{
    protected $metaWaService;

    public function __construct(MetaWaService $metaWaService)
    {
        $this->metaWaService = $metaWaService;
    }

    /**
     * Verifica si un canal está activo
     */
    protected function isChannelEnabled(string $channelType): bool
    {
        return NotificationChannel::where('channel_type', $channelType)
            ->where('is_enabled', true)
            ->exists();
    }

    /**
     * Verifica si una notificación está habilitada para un rol
     */
    protected function isNotificationEnabled(string $roleType, string $eventType, string $channelType): bool
    {
        $channel = NotificationChannel::where('channel_type', $channelType)->first();
        if (!$channel) return false;

        return NotificationSetting::where('role_type', $roleType)
            ->where('event_type', $eventType)
            ->where('channel_id', $channel->id)
            ->where('is_enabled', true)
            ->exists();
    }

    /**
     * Obtiene los usuarios por rol que tienen WhatsApp configurado
     */
    protected function getUsersByRole(string $role): Collection
    {
        Log::info('Buscando usuarios con rol: ' . $role);

        $users = User::role($role)
            ->whereRaw("JSON_EXTRACT(custom_fields, '$.phone') IS NOT NULL")
            ->get();

        Log::info('Usuarios encontrados:', [
            'count' => $users->count(),
            'users' => $users->map(fn($user) => [
                'id' => $user->id,
                'email' => $user->email,
                'phone' => data_get($user->custom_fields, 'phone')
            ])->toArray()
        ]);

        return $users;
    }

    /**
     * Verifica si ya se envió una notificación
     */
    protected function isNotificationSent(string $eventType, string $channelType, int $userId, string $referenceId): bool
    {
        return NotificationLog::where('event_type', $eventType)
            ->where('channel_type', $channelType)
            ->where('user_id', $userId)
            ->where('reference_id', $referenceId)
            ->exists();
    }

    /**
     * Registra una notificación enviada
     */
    protected function logNotification(string $eventType, string $channelType, int $userId, string $referenceId, array $data = null): void
    {
        NotificationLog::create([
            'event_type' => $eventType,
            'channel_type' => $channelType,
            'user_id' => $userId,
            'reference_id' => $referenceId,
            'data' => $data,
            'sent_at' => now(),
        ]);
    }

    /**
     * Envía notificación de nueva subasta a revendedores
     */
    public function sendNewAuctionNotification(array $auctionData): void
    {
        // Configurar zona horaria de Lima/Perú
        date_default_timezone_set('America/Lima');
        
        Log::info('=== INICIANDO VALIDACIONES PARA NUEVA SUBASTA ===', [
            'auction_id' => $auctionData['id'],
            'vehiculo' => $auctionData['vehiculo']
        ]);

        // 1. Validar que el canal WhatsApp esté habilitado
        $whatsappEnabled = $this->isChannelEnabled('whatsapp');
        Log::info('1. Validación de canal WhatsApp:', [
            'habilitado' => $whatsappEnabled ? 'SÍ' : 'NO'
        ]);
        if (!$whatsappEnabled) {
            Log::warning('❌ Canal WhatsApp no está habilitado - Notificación cancelada');
            return;
        }
        Log::info('✅ Canal WhatsApp está habilitado');

        // 2. Validar que la notificación esté habilitada para revendedores
        $notificationEnabled = $this->isNotificationEnabled('revendedor', 'nueva_subasta', 'whatsapp');
        Log::info('2. Validación de notificación para revendedores:', [
            'habilitada' => $notificationEnabled ? 'SÍ' : 'NO',
            'rol' => 'revendedor',
            'evento' => 'nueva_subasta'
        ]);
        if (!$notificationEnabled) {
            Log::warning('❌ Notificación nueva_subasta no está habilitada para revendedores - Notificación cancelada');
            return;
        }
        Log::info('✅ Notificación nueva_subasta está habilitada para revendedores');

        // 3. Obtener usuarios revendedores
        $revendedores = $this->getUsersByRole('revendedor');
        Log::info('3. Búsqueda de revendedores:', [
            'total_encontrados' => $revendedores->count()
        ]);

        if ($revendedores->isEmpty()) {
            Log::warning('❌ No se encontraron revendedores con WhatsApp configurado - Notificación cancelada');
            return;
        }
        Log::info('✅ Se encontraron revendedores para notificar');

        $notificacionesEnviadas = 0;
        $notificacionesFallidas = 0;
        $numerosNotificados = [];

        foreach ($revendedores as $revendedor) {
            Log::info('=== Procesando revendedor ===', [
                'id' => $revendedor->id,
                'nombre' => $revendedor->name,
                'email' => $revendedor->email
            ]);

            $phone = data_get($revendedor->custom_fields, 'phone');
            
            // Formatear el número correctamente
            $phone = $phone ? preg_replace('/[^0-9]/', '', $phone) : null;

            if (!$phone) {
                Log::info('⏩ Revendedor sin teléfono configurado - Saltando', [
                    'id' => $revendedor->id,
                    'nombre' => $revendedor->name
                ]);
                continue;
            }

            // Validar si el número ya fue notificado
            if (in_array($phone, $numerosNotificados)) {
                Log::info('⏩ Número ya notificado anteriormente - Saltando', [
                    'telefono' => $phone,
                    'nombre' => $revendedor->name
                ]);
                continue;
            }

            // 5. Validar que no se haya enviado la notificación previamente
            $notificationSent = $this->isNotificationSent('nueva_subasta', 'whatsapp', $revendedor->id, $auctionData['id']);
            if ($notificationSent) {
                Log::info('⏩ Notificación ya enviada al revendedor - Saltando', [
                    'nombre' => $revendedor->name,
                    'email' => $revendedor->email
                ]);
                continue;
            }

            try {
                // Preparar template en formato Meta WhatsApp API
                $templateData = [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => 'new_auction',
                        'language' => [
                            'code' => 'es_PE'
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $auctionData['vehiculo']
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $auctionData['fecha_inicio']
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $auctionData['fecha_fin']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];

                Log::info('Enviando notificación WhatsApp', [
                    'destinatario' => $phone,
                    'template_data' => $templateData
                ]);

                $response = $this->metaWaService->sendTemplateMessage($phone, $templateData);

                // Registrar la notificación como enviada
                $this->logNotification('nueva_subasta', 'whatsapp', $revendedor->id, $auctionData['id'], [
                    'template' => 'new_auction',
                    'phone' => $phone,
                    'message_id' => $response['messages'][0]['id'] ?? null
                ]);

                // Agregar el número a la lista de notificados
                $numerosNotificados[] = $phone;
                $notificacionesEnviadas++;

                Log::info('✅ Notificación enviada exitosamente', [
                    'destinatario' => [
                        'id' => $revendedor->id,
                        'nombre' => $revendedor->name,
                        'telefono' => $phone
                    ],
                    'auction_id' => $auctionData['id'],
                    'message_id' => $response['messages'][0]['id'] ?? 'N/A'
                ]);

            } catch (\Exception $e) {
                $notificacionesFallidas++;
                Log::error('Error enviando notificación WhatsApp', [
                    'error' => $e->getMessage(),
                    'destinatario' => [
                        'id' => $revendedor->id,
                        'nombre' => $revendedor->name,
                        'telefono' => $phone
                    ],
                    'auction_id' => $auctionData['id']
                ]);
            }
        }

        Log::info('=== RESUMEN DEL PROCESO DE NOTIFICACIÓN ===', [
            'total_revendedores' => $revendedores->count(),
            'notificaciones_enviadas' => $notificacionesEnviadas,
            'notificaciones_fallidas' => $notificacionesFallidas,
            'subasta' => [
                'id' => $auctionData['id'],
                'vehiculo' => $auctionData['vehiculo'],
                'inicio' => $auctionData['fecha_inicio'],
                'fin' => $auctionData['fecha_fin']
            ]
        ]);
    }
} 