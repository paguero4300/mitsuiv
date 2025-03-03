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

    /**
     * Envía notificación de subasta perdida a revendedores
     */
    public function sendLostAuctionNotification(array $auctionData): void
    {
        // Establecer zona horaria a Lima
        date_default_timezone_set('America/Lima');

        Log::info('========== INICIANDO ENVÍO DE NOTIFICACIONES A PERDEDORES DE SUBASTA ==========', [
            'auction_id' => $auctionData['id'],
            'vehiculo' => $auctionData['vehiculo'],
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);

        // Verificar si el canal WhatsApp está habilitado
        $whatsappEnabled = $this->isChannelEnabled('whatsapp');
        Log::info('Canal WhatsApp: ' . ($whatsappEnabled ? '✅ HABILITADO' : '❌ DESHABILITADO'));
        
        if (!$whatsappEnabled) {
            Log::warning('❌ Notificación cancelada: Canal WhatsApp no está habilitado');
            return;
        }

        // Verificar si las notificaciones de subasta fallida están habilitadas para revendedores
        $notificacionesHabilitadas = $this->isNotificationEnabled('revendedor', 'subasta_fallida', 'whatsapp');
        Log::info('Notificaciones subasta_fallida para revendedores: ' . 
            ($notificacionesHabilitadas ? '✅ HABILITADAS' : '❌ DESHABILITADAS'));
        
        if (!$notificacionesHabilitadas) {
            Log::warning('❌ Notificación cancelada: Las notificaciones de subasta fallida no están habilitadas para revendedores');
            return;
        }

        // Obtener los revendedores que perdieron la subasta
        Log::info('Buscando revendedores perdedores', [
            'auction_id' => $auctionData['id'],
            'winner_id' => $auctionData['winner_id']
        ]);
        
        $losers = $this->getAuctionLosers($auctionData['id'], $auctionData['winner_id']);
        
        Log::info('Revendedores perdedores encontrados: ' . $losers->count(), [
            'auction_id' => $auctionData['id'],
            'total_revendedores' => $losers->count(),
            'ids_revendedores' => $losers->pluck('id')->toArray()
        ]);

        if ($losers->isEmpty()) {
            Log::info('⚠️ No hay revendedores para notificar - Proceso finalizado');
            return;
        }

        $notificacionesEnviadas = 0;
        $notificacionesFallidas = 0;
        $numerosNotificados = [];

        Log::info('========== PROCESANDO ' . $losers->count() . ' REVENDEDORES PERDEDORES ==========');

        foreach ($losers as $index => $loser) {
            Log::info('------ Procesando revendedor ' . ($index + 1) . '/' . $losers->count() . ' ------', [
                'revendedor_id' => $loser->id,
                'revendedor_nombre' => $loser->name,
                'revendedor_email' => $loser->email
            ]);
            
            try {
                // Obtener número de teléfono del revendedor
                $customFields = json_decode($loser->custom_fields, true);
                $phone = $customFields['phone'] ?? null;
                
                Log::info('Teléfono del revendedor: ' . ($phone ?: 'NO CONFIGURADO'));
                
                if (empty($phone)) {
                    Log::warning('⏩ Revendedor sin número de teléfono configurado - Saltando', [
                        'revendedor_id' => $loser->id,
                        'revendedor_name' => $loser->name
                    ]);
                    continue;
                }

                // Evitar enviar a números ya notificados en esta ejecución
                if (in_array($phone, $numerosNotificados)) {
                    Log::info('⏩ Número ya notificado en esta ejecución - Saltando', [
                        'phone' => $phone
                    ]);
                    continue;
                }

                // Verificar si ya se envió notificación previamente
                $notificationSent = $this->isNotificationSent('subasta_fallida', 'whatsapp', $loser->id, $auctionData['id']);
                
                if ($notificationSent) {
                    Log::info('⏩ Notificación ya enviada previamente - Saltando', [
                        'user_id' => $loser->id,
                        'auction_id' => $auctionData['id']
                    ]);
                    continue;
                }

                // Preparar y enviar plantilla WhatsApp
                Log::info('Preparando plantilla WhatsApp para envío');
                
                $templateData = [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => 'lost_auction',
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

                Log::info('Enviando notificación WhatsApp a ' . $phone);

                $response = $this->metaWaService->sendTemplateMessage($phone, $templateData);

                // Registrar la notificación como enviada
                Log::info('Respuesta recibida del servicio WhatsApp', [
                    'message_id' => $response['messages'][0]['id'] ?? 'NO_ID'
                ]);
                
                $this->logNotification('subasta_fallida', 'whatsapp', $loser->id, $auctionData['id'], [
                    'template' => 'lost_auction',
                    'phone' => $phone,
                    'message_id' => $response['messages'][0]['id'] ?? null
                ]);

                // Agregar el número a la lista de notificados
                $numerosNotificados[] = $phone;
                $notificacionesEnviadas++;

                Log::info('✅ Notificación enviada exitosamente', [
                    'destinatario' => [
                        'id' => $loser->id,
                        'nombre' => $loser->name,
                        'telefono' => $phone
                    ],
                    'auction_id' => $auctionData['id'],
                    'message_id' => $response['messages'][0]['id'] ?? 'N/A'
                ]);

            } catch (\Exception $e) {
                $notificacionesFallidas++;
                
                Log::error('❌ Error al enviar notificación', [
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'destinatario' => [
                        'id' => $loser->id ?? 'N/A',
                        'nombre' => $loser->name ?? 'N/A'
                    ],
                    'auction_id' => $auctionData['id']
                ]);
            }
        }

        Log::info('========== PROCESO DE NOTIFICACIÓN A PERDEDORES COMPLETADO ==========', [
            'auction_id' => $auctionData['id'],
            'total_revendedores' => $losers->count(),
            'notificaciones_enviadas' => $notificacionesEnviadas,
            'notificaciones_fallidas' => $notificacionesFallidas,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
    }

    /**
     * Obtiene los revendedores que participaron en la subasta pero no ganaron
     */
    private function getAuctionLosers(int $auctionId, int $winnerId): Collection
    {
        Log::info('========== CONSULTANDO REVENDEDORES PERDEDORES DE SUBASTA ==========', [
            'auction_id' => $auctionId,
            'winner_id' => $winnerId,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);

        try {
            Log::info('Construyendo consulta SQL para encontrar perdedores');
            
            // Convertir el ID a string para comparar con reference_id
            $auctionIdStr = (string)$auctionId;
            
            $query = User::select('users.*')
                ->join('model_has_roles', function($join) {
                    $join->on('users.id', '=', 'model_has_roles.model_id')
                        ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
                })
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->join('bids', 'users.id', '=', 'bids.reseller_id')
                ->leftJoin('notification_logs', function($join) use ($auctionIdStr) {
                    $join->on('notification_logs.user_id', '=', 'users.id')
                        ->where('notification_logs.reference_id', '=', $auctionIdStr)
                        ->where('notification_logs.event_type', '=', 'subasta_fallida')
                        ->where('notification_logs.channel_type', '=', 'whatsapp');
                })
                ->where('roles.name', 'revendedor')
                ->where('bids.auction_id', $auctionId)
                ->where('users.id', '!=', $winnerId)
                ->whereNull('notification_logs.id')
                ->whereNotNull(DB::raw("JSON_EXTRACT(users.custom_fields, '$.phone')"))
                ->distinct();
            
            // Registrar la consulta SQL para diagnóstico
            $sql = $query->toSql();
            $bindings = $query->getBindings();
            
            Log::info('Consulta SQL para encontrar perdedores', [
                'sql' => $sql,
                'bindings' => $bindings
            ]);
            
            // Ejecutar la consulta
            $result = $query->get();
            
            Log::info('Consulta ejecutada con éxito', [
                'total_revendedores_encontrados' => $result->count(),
                'ids_encontrados' => $result->pluck('id')->toArray()
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('❌ Error al buscar revendedores perdedores', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'auction_id' => $auctionId,
                'trace' => $e->getTraceAsString()
            ]);
            
            // En caso de error, devolver colección vacía
            return collect();
        }
    }
} 