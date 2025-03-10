<?php

namespace App\Services;

use App\Models\User;
use App\Models\NotificationChannel;
use App\Models\NotificationSetting;
use App\Models\NotificationLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AuctionNotificationService
{
    protected $metaWaService;

    public function __construct(MetaWaService $metaWaService)
    {
        $this->metaWaService = $metaWaService;
    }

    /**
     * Verifica si el canal de notificación está habilitado
     */
    protected function isChannelEnabled(string $channelType): bool
    {
        $channel = NotificationChannel::where('channel_type', $channelType)->first();
        return $channel && $channel->is_enabled;
    }

    /**
     * Verifica si la notificación está habilitada para un rol y canal específico
     */
    protected function isNotificationEnabled(string $roleType, string $eventType, string $channelType): bool
    {
        $channel = NotificationChannel::where('channel_type', $channelType)->first();
        
        if (!$channel) {
            return false;
        }
        
        $setting = NotificationSetting::where('role_type', $roleType)
            ->where('event_type', $eventType)
            ->where('channel_id', $channel->id)
            ->first();
        
        return $setting && $setting->is_enabled;
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
                        'name' => 'new_mitsui',
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
                                        'text' => $auctionData['marca'] ?? 'N/A'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $auctionData['modelo'] ?? 'N/A'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $auctionData['version'] ?? 'N/A'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $auctionData['anio'] ?? 'N/A'
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $auctionData['kilometraje'] ?? 'N/A'
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
                    'template' => 'new_mitsui',
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
                            'code' => 'es'
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

    /**
     * Envía notificación de subasta ganada al revendedor adjudicado
     */
    public function sendWinAuctionNotification(array $auctionData): void
    {
        // Establecer zona horaria a Lima
        date_default_timezone_set('America/Lima');

        Log::info('========== INICIANDO ENVÍO DE NOTIFICACIÓN AL GANADOR DE SUBASTA ==========', [
            'auction_id' => $auctionData['id'],
            'vehiculo' => $auctionData['vehiculo'],
            'winner_id' => $auctionData['winner_id'],
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);

        // Verificar si el canal WhatsApp está habilitado
        $whatsappEnabled = $this->isChannelEnabled('whatsapp');
        Log::info('Canal WhatsApp: ' . ($whatsappEnabled ? '✅ HABILITADO' : '❌ DESHABILITADO'));
        
        if (!$whatsappEnabled) {
            Log::warning('❌ Notificación cancelada: Canal WhatsApp no está habilitado');
            return;
        }

        // Verificar si las notificaciones de subasta ganada están habilitadas para revendedores
        $notificacionesHabilitadas = $this->isNotificationEnabled('revendedor', 'subasta_ganada', 'whatsapp');
        Log::info('Notificaciones subasta_ganada para revendedores: ' . 
            ($notificacionesHabilitadas ? '✅ HABILITADAS' : '❌ DESHABILITADAS'));
        
        if (!$notificacionesHabilitadas) {
            Log::warning('❌ Notificación cancelada: Las notificaciones de subasta ganada no están habilitadas para revendedores');
            return;
        }

        // Obtener el revendedor ganador
        $winnerId = $auctionData['winner_id'];
        
        Log::info('Buscando revendedor ganador', [
            'auction_id' => $auctionData['id'],
            'winner_id' => $winnerId
        ]);
        
        $winner = \App\Models\User::find($winnerId);
        
        if (!$winner) {
            Log::error('❌ Notificación cancelada: No se encontró al revendedor ganador', [
                'winner_id' => $winnerId
            ]);
            return;
        }
        
        Log::info('Revendedor ganador encontrado', [
            'id' => $winner->id,
            'nombre' => $winner->name,
            'email' => $winner->email
        ]);

        // Verificación de notificación previa
        $notificationSent = $this->isNotificationSent('subasta_ganada', 'whatsapp', $winner->id, $auctionData['id']);
        if ($notificationSent) {
            Log::info('⏩ Notificación ya enviada previamente - Cancelando', [
                'user_id' => $winner->id,
                'auction_id' => $auctionData['id']
            ]);
            return;
        }

        // Obtener número de teléfono del revendedor
        $customFields = json_decode($winner->custom_fields, true);
        $phone = $customFields['phone'] ?? null;
        
        Log::info('Teléfono del revendedor ganador: ' . ($phone ?: 'NO CONFIGURADO'));
        
        if (empty($phone)) {
            Log::warning('❌ Revendedor ganador sin número de teléfono configurado - Cancelando', [
                'revendedor_id' => $winner->id,
                'revendedor_name' => $winner->name
            ]);
            return;
        }

        // Formatear número de teléfono (eliminar caracteres no numéricos)
        $phone = preg_replace('/[^0-9]/', '', $phone);

        try {
            // Preparar plantilla WhatsApp
            Log::info('Preparando plantilla WhatsApp win_auction para envío');
            
            $templateData = [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'template' => [
                    'name' => 'win_auction',
                    'language' => [
                        'code' => 'es_PE'
                    ],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $auctionData['modelo']
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $auctionData['anio']
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $auctionData['placa']
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $auctionData['monto_ganador']
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $auctionData['fecha_adjudicacion']
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            Log::info('Enviando notificación WhatsApp al ganador', [
                'destinatario' => $phone,
                'template' => 'win_auction'
            ]);

            $response = $this->metaWaService->sendTemplateMessage($phone, $templateData);

            // Registrar la notificación como enviada
            Log::info('Respuesta recibida del servicio WhatsApp', [
                'message_id' => $response['messages'][0]['id'] ?? 'NO_ID'
            ]);
            
            $this->logNotification('subasta_ganada', 'whatsapp', $winner->id, $auctionData['id'], [
                'template' => 'win_auction',
                'phone' => $phone,
                'message_id' => $response['messages'][0]['id'] ?? null
            ]);

            Log::info('✅ Notificación enviada exitosamente al ganador', [
                'destinatario' => [
                    'id' => $winner->id,
                    'nombre' => $winner->name,
                    'telefono' => $phone
                ],
                'auction_id' => $auctionData['id'],
                'message_id' => $response['messages'][0]['id'] ?? 'N/A'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error al enviar notificación al ganador', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'destinatario' => [
                    'id' => $winner->id ?? 'N/A',
                    'nombre' => $winner->name ?? 'N/A'
                ],
                'auction_id' => $auctionData['id']
            ]);
            
            throw $e; // Relanzar la excepción para permitir reintentos
        }

        Log::info('========== PROCESO DE NOTIFICACIÓN AL GANADOR COMPLETADO ==========', [
            'auction_id' => $auctionData['id'],
            'winner_id' => $winnerId,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
    }

    /**
     * Envía notificación de subasta por terminar a revendedores
     */
    public function sendEndingAuctionNotification(array $auctionData): void
    {
        // Establecer zona horaria a Lima
        date_default_timezone_set('America/Lima');

        Log::info('========== INICIANDO ENVÍO DE NOTIFICACIONES DE SUBASTA POR TERMINAR ==========', [
            'auction_id' => $auctionData['id'],
            'placa' => $auctionData['placa'],
            'tiempo_restante' => $auctionData['minutos_restantes'] . ' minutos',
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);

        // Verificar si el canal WhatsApp está habilitado
        $whatsappEnabled = $this->isChannelEnabled('whatsapp');
        Log::info('Canal WhatsApp: ' . ($whatsappEnabled ? '✅ HABILITADO' : '❌ DESHABILITADO'));
        
        if (!$whatsappEnabled) {
            Log::warning('❌ Notificación cancelada: Canal WhatsApp no está habilitado');
            return;
        }

        // Verificar si las notificaciones de subasta por terminar están habilitadas para revendedores
        $notificacionesHabilitadas = $this->isNotificationEnabled('revendedor', 'subasta_por_terminar', 'whatsapp');
        Log::info('Notificaciones subasta_por_terminar para revendedores: ' . 
            ($notificacionesHabilitadas ? '✅ HABILITADAS' : '❌ DESHABILITADAS'));
        
        if (!$notificacionesHabilitadas) {
            Log::warning('❌ Notificación cancelada: Las notificaciones de subasta por terminar no están habilitadas para revendedores');
            return;
        }

        // Obtener revendedores con WhatsApp configurado
        $revendedores = $this->getUsersByRole('revendedor');
        Log::info('Revendedores encontrados: ' . $revendedores->count());

        if ($revendedores->isEmpty()) {
            Log::warning('❌ No hay revendedores para notificar - Cancelando');
            return;
        }

        // Variables para estadísticas
        $notificacionesEnviadas = 0;
        $notificacionesFallidas = 0;
        $numerosNotificados = [];

        Log::info('========== PROCESANDO ' . $revendedores->count() . ' REVENDEDORES ==========');

        foreach ($revendedores as $index => $revendedor) {
            Log::info('------ Procesando revendedor ' . ($index + 1) . '/' . $revendedores->count() . ' ------', [
                'revendedor_id' => $revendedor->id,
                'revendedor_nombre' => $revendedor->name,
                'revendedor_email' => $revendedor->email
            ]);
            
            try {
                // Obtener número de teléfono del revendedor
                $customFields = json_decode($revendedor->custom_fields, true);
                $phone = $customFields['phone'] ?? null;
                
                Log::info('Teléfono del revendedor: ' . ($phone ?: 'NO CONFIGURADO'));
                
                if (empty($phone)) {
                    Log::warning('⏩ Revendedor sin número de teléfono configurado - Saltando', [
                        'revendedor_id' => $revendedor->id,
                        'revendedor_name' => $revendedor->name
                    ]);
                    continue;
                }

                // Formatear número de teléfono (eliminar caracteres no numéricos)
                $phone = preg_replace('/[^0-9]/', '', $phone);

                // Verificar si ya se envió notificación previamente para esta subasta y este revendedor
                $notificationSent = $this->isNotificationSent('subasta_por_terminar', 'whatsapp', $revendedor->id, $auctionData['id']);
                
                if ($notificationSent) {
                    Log::info('⏩ Notificación ya enviada previamente - Saltando', [
                        'user_id' => $revendedor->id,
                        'auction_id' => $auctionData['id']
                    ]);
                    continue;
                }

                // Preparar y enviar plantilla WhatsApp
                Log::info('Preparando plantilla WhatsApp wait_auction para envío');
                
                $templateData = [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => 'wait_auction',
                        'language' => [
                            'code' => 'es_PE'
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $auctionData['placa']
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $auctionData['marca']
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $auctionData['modelo']
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $auctionData['oferta_actual']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];

                Log::info('Enviando notificación WhatsApp de subasta por terminar', [
                    'destinatario' => $phone,
                    'subasta_id' => $auctionData['id'],
                    'placa' => $auctionData['placa']
                ]);

                $response = $this->metaWaService->sendTemplateMessage($phone, $templateData);

                // Registrar la notificación como enviada
                Log::info('Respuesta recibida del servicio WhatsApp', [
                    'message_id' => $response['messages'][0]['id'] ?? 'NO_ID'
                ]);
                
                $this->logNotification('subasta_por_terminar', 'whatsapp', $revendedor->id, $auctionData['id'], [
                    'template' => 'wait_auction',
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
                
                Log::error('❌ Error al enviar notificación de subasta por terminar', [
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'destinatario' => [
                        'id' => $revendedor->id ?? 'N/A',
                        'nombre' => $revendedor->name ?? 'N/A'
                    ],
                    'auction_id' => $auctionData['id']
                ]);
            }
        }

        Log::info('========== PROCESO DE NOTIFICACIÓN DE SUBASTA POR TERMINAR COMPLETADO ==========', [
            'auction_id' => $auctionData['id'],
            'total_revendedores' => $revendedores->count(),
            'notificaciones_enviadas' => $notificacionesEnviadas,
            'notificaciones_fallidas' => $notificacionesFallidas,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
    }

    /**
     * Envía notificación cuando una puja ha sido superada por otra
     */
    public function sendOutbidNotification(array $notificationData): void
    {
        // Establecer zona horaria a Lima
        date_default_timezone_set('America/Lima');

        Log::info('========== INICIANDO ENVÍO DE NOTIFICACIÓN DE PUJA SUPERADA ==========', [
            'auction_id' => $notificationData['auction_id'],
            'bid_id' => $notificationData['bid_id'],
            'usuario_superado' => $notificationData['outbid_user_id'],
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);

        // Verificar si el canal WhatsApp está habilitado
        $whatsappEnabled = $this->isChannelEnabled('whatsapp');
        Log::info('Canal WhatsApp: ' . ($whatsappEnabled ? '✅ HABILITADO' : '❌ DESHABILITADO'));
        
        if (!$whatsappEnabled) {
            Log::warning('❌ Notificación cancelada: Canal WhatsApp no está habilitado');
            return;
        }

        // Verificar si las notificaciones de puja superada están habilitadas para revendedores
        $notificacionesHabilitadas = $this->isNotificationEnabled('revendedor', 'puja_superada', 'whatsapp');
        Log::info('Notificaciones puja_superada para revendedores: ' . 
            ($notificacionesHabilitadas ? '✅ HABILITADAS' : '❌ DESHABILITADAS'));
        
        if (!$notificacionesHabilitadas) {
            Log::warning('❌ Notificación cancelada: Las notificaciones de puja superada no están habilitadas para revendedores');
            return;
        }

        // Obtener el revendedor cuya puja fue superada
        $outbidUserId = $notificationData['outbid_user_id'];
        
        Log::info('Buscando revendedor superado', [
            'auction_id' => $notificationData['auction_id'],
            'user_id' => $outbidUserId
        ]);
        
        $outbidUser = \App\Models\User::find($outbidUserId);
        
        if (!$outbidUser) {
            Log::error('❌ Notificación cancelada: No se encontró al revendedor superado', [
                'user_id' => $outbidUserId
            ]);
            return;
        }
        
        Log::info('Revendedor superado encontrado', [
            'id' => $outbidUser->id,
            'nombre' => $outbidUser->name,
            'email' => $outbidUser->email
        ]);

        // Crear un ID de referencia único para evitar duplicados
        $referenceId = $notificationData['id'];
        
        // Verificación de notificación previa
        $notificationSent = $this->isNotificationSent('puja_superada', 'whatsapp', $outbidUser->id, $referenceId);
        if ($notificationSent) {
            Log::info('⏩ Notificación ya enviada previamente - Cancelando', [
                'user_id' => $outbidUser->id,
                'reference_id' => $referenceId
            ]);
            return;
        }

        // Obtener número de teléfono del revendedor
        $customFields = json_decode($outbidUser->custom_fields, true);
        $phone = $customFields['phone'] ?? null;
        
        Log::info('Teléfono del revendedor superado: ' . ($phone ?: 'NO CONFIGURADO'));
        
        if (empty($phone)) {
            Log::warning('❌ Revendedor superado sin número de teléfono configurado - Cancelando', [
                'revendedor_id' => $outbidUser->id,
                'revendedor_name' => $outbidUser->name
            ]);
            return;
        }

        // Formatear número de teléfono (eliminar caracteres no numéricos)
        $phone = preg_replace('/[^0-9]/', '', $phone);

        try {
            // Preparar plantilla WhatsApp
            Log::info('Preparando plantilla WhatsApp up_auction para envío');
            
            $templateData = [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'template' => [
                    'name' => 'up_auction',
                    'language' => [
                        'code' => 'es_PE'
                    ],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $notificationData['marca']
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $notificationData['modelo']
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '$' . $notificationData['outbid_amount']
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            Log::info('Enviando notificación WhatsApp al revendedor superado', [
                'destinatario' => $phone,
                'template' => 'up_auction'
            ]);

            $response = $this->metaWaService->sendTemplateMessage($phone, $templateData);

            // Registrar la notificación como enviada
            Log::info('Respuesta recibida del servicio WhatsApp', [
                'message_id' => $response['messages'][0]['id'] ?? 'NO_ID'
            ]);
            
            $this->logNotification('puja_superada', 'whatsapp', $outbidUser->id, $referenceId, [
                'template' => 'up_auction',
                'phone' => $phone,
                'auction_id' => $notificationData['auction_id'],
                'bid_id' => $notificationData['bid_id'],
                'new_bid_id' => $notificationData['new_bid_id'],
                'message_id' => $response['messages'][0]['id'] ?? null
            ]);

            Log::info('✅ Notificación enviada exitosamente al revendedor superado', [
                'destinatario' => [
                    'id' => $outbidUser->id,
                    'nombre' => $outbidUser->name,
                    'telefono' => $phone
                ],
                'auction_id' => $notificationData['auction_id'],
                'message_id' => $response['messages'][0]['id'] ?? 'N/A'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error al enviar notificación al revendedor superado', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'destinatario' => [
                    'id' => $outbidUser->id ?? 'N/A',
                    'nombre' => $outbidUser->name ?? 'N/A'
                ],
                'auction_id' => $notificationData['auction_id']
            ]);
            
            throw $e; // Relanzar la excepción para permitir reintentos
        }

        Log::info('========== PROCESO DE NOTIFICACIÓN DE PUJA SUPERADA COMPLETADO ==========', [
            'auction_id' => $notificationData['auction_id'],
            'outbid_user_id' => $outbidUserId,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
    }

    /**
     * Envía notificación por email de nueva subasta a revendedores
     */
    public function sendNewAuctionEmailNotification(array $auctionData): void
    {
        // Configurar zona horaria de Lima/Perú
        date_default_timezone_set('America/Lima');
        
        Log::info('=== INICIANDO VALIDACIONES PARA NUEVA SUBASTA POR EMAIL ===', [
            'auction_id' => $auctionData['id'],
            'vehiculo' => $auctionData['vehiculo']
        ]);

        // 1. Validar que el canal Email esté habilitado
        $emailEnabled = $this->isChannelEnabled('email');
        Log::info('1. Validación de canal Email:', [
            'habilitado' => $emailEnabled ? 'SÍ' : 'NO'
        ]);
        if (!$emailEnabled) {
            Log::warning('❌ Canal Email no está habilitado - Notificación cancelada');
            return;
        }
        Log::info('✅ Canal Email está habilitado');

        // 2. Validar que la notificación esté habilitada para revendedores
        $notificationEnabled = $this->isNotificationEnabled('revendedor', 'nueva_subasta', 'email');
        Log::info('2. Validación de notificación por email para revendedores:', [
            'habilitada' => $notificationEnabled ? 'SÍ' : 'NO',
            'rol' => 'revendedor',
            'evento' => 'nueva_subasta'
        ]);
        if (!$notificationEnabled) {
            Log::warning('❌ Notificación nueva_subasta por email no está habilitada para revendedores - Notificación cancelada');
            return;
        }
        Log::info('✅ Notificación nueva_subasta por email está habilitada para revendedores');

        // 3. Obtener usuarios revendedores
        $revendedores = $this->getUsersByRole('revendedor');
        Log::info('3. Búsqueda de revendedores:', [
            'total_encontrados' => $revendedores->count()
        ]);

        if ($revendedores->isEmpty()) {
            Log::warning('❌ No se encontraron revendedores con email configurado - Notificación cancelada');
            return;
        }
        Log::info('✅ Se encontraron revendedores para notificar');

        $notificacionesEnviadas = 0;
        $notificacionesFallidas = 0;
        $emailsNotificados = [];

        foreach ($revendedores as $revendedor) {
            Log::info('=== Procesando revendedor para email ===', [
                'id' => $revendedor->id,
                'nombre' => $revendedor->name,
                'email' => $revendedor->email
            ]);

            $email = $revendedor->email;

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::info('⏩ Revendedor sin email válido configurado - Saltando', [
                    'id' => $revendedor->id,
                    'nombre' => $revendedor->name,
                    'email' => $email
                ]);
                continue;
            }

            // Validar si el email ya fue notificado
            if (in_array($email, $emailsNotificados)) {
                Log::info('⏩ Email ya notificado anteriormente - Saltando', [
                    'email' => $email,
                    'nombre' => $revendedor->name
                ]);
                continue;
            }

            // Validar que no se haya enviado la notificación previamente
            $notificationSent = $this->isNotificationSent('nueva_subasta', 'email', $revendedor->id, $auctionData['id']);
            if ($notificationSent) {
                Log::info('⏩ Notificación por email ya enviada al revendedor - Saltando', [
                    'nombre' => $revendedor->name,
                    'email' => $revendedor->email
                ]);
                continue;
            }

            try {
                // Preparar datos para la vista del email
                $emailData = [
                    'vehiculo' => $auctionData['vehiculo'],
                    'fecha_inicio' => $auctionData['fecha_inicio'],
                    'fecha_fin' => $auctionData['fecha_fin']
                ];

                Log::info('Enviando notificación Email', [
                    'destinatario' => $email,
                    'email_data' => $emailData
                ]);

                // Enviar email
                Mail::send('emails.new-auction', $emailData, function ($message) use ($email, $revendedor) {
                    $message->to($email, $revendedor->name)
                            ->subject('Nueva Subasta Disponible - Mitsui Automotriz');
                });

                // Registrar la notificación como enviada
                $this->logNotification('nueva_subasta', 'email', $revendedor->id, $auctionData['id'], [
                    'template' => 'new-auction',
                    'email' => $email
                ]);

                // Agregar el email a la lista de notificados
                $emailsNotificados[] = $email;
                $notificacionesEnviadas++;

                Log::info('✅ Notificación por email enviada exitosamente', [
                    'destinatario' => [
                        'id' => $revendedor->id,
                        'nombre' => $revendedor->name,
                        'email' => $email
                    ],
                    'auction_id' => $auctionData['id']
                ]);

            } catch (\Exception $e) {
                $notificacionesFallidas++;
                Log::error('Error enviando notificación por Email', [
                    'error' => $e->getMessage(),
                    'destinatario' => [
                        'id' => $revendedor->id,
                        'nombre' => $revendedor->name,
                        'email' => $email
                    ],
                    'auction_id' => $auctionData['id']
                ]);
            }
        }

        Log::info('=== RESUMEN DEL PROCESO DE NOTIFICACIÓN POR EMAIL ===', [
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