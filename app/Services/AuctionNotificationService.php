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

        $users = DB::table('users')
            ->select('users.*')
            ->join('model_has_roles', function($join) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
            })
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', '=', $role)
            ->whereRaw("JSON_EXTRACT(custom_fields, '$.phone') IS NOT NULL")
            ->get();

        Log::info('Usuarios encontrados:', [
            'count' => $users->count(),
            'users' => $users->map(fn($user) => [
                'id' => $user->id,
                'email' => $user->email,
                'phone' => json_decode($user->custom_fields)->phone ?? null
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

        // 3. Validar que la subasta haya empezado
        $start_date = \Carbon\Carbon::createFromFormat('d/m/Y H:i', $auctionData['fecha_inicio'])->tz('America/Lima');
        $now = now()->tz('America/Lima');
        $hasStarted = $start_date <= $now;
        
        Log::info('3. Validación de inicio de subasta:', [
            'fecha_inicio' => $start_date->format('Y-m-d H:i:s'),
            'ahora' => $now->format('Y-m-d H:i:s'),
            'zona_horaria' => 'America/Lima',
            'ha_comenzado' => $hasStarted ? 'SÍ' : 'NO'
        ]);
        if (!$hasStarted) {
            Log::warning('❌ La subasta aún no ha comenzado - Notificación cancelada');
            return;
        }
        Log::info('✅ La subasta ya ha comenzado');

        // 4. Obtener usuarios revendedores
        $revendedores = $this->getUsersByRole('revendedor');
        Log::info('4. Búsqueda de revendedores:', [
            'total_encontrados' => $revendedores->count()
        ]);
        if ($revendedores->isEmpty()) {
            Log::warning('❌ No se encontraron revendedores con WhatsApp configurado - Notificación cancelada');
            return;
        }
        Log::info('✅ Se encontraron revendedores para notificar');

        foreach ($revendedores as $revendedor) {
            Log::info('=== Procesando revendedor ===', [
                'user_id' => $revendedor->id,
                'email' => $revendedor->email
            ]);

            // 5. Validar que no se haya enviado la notificación previamente
            $notificationSent = $this->isNotificationSent('nueva_subasta', 'whatsapp', $revendedor->id, $auctionData['id']);
            Log::info('5. Validación de notificación previa:', [
                'ya_enviada' => $notificationSent ? 'SÍ' : 'NO',
                'user_id' => $revendedor->id
            ]);
            if ($notificationSent) {
                Log::info('⏩ Notificación ya enviada al revendedor - Saltando');
                continue;
            }

            $phone = data_get(json_decode($revendedor->custom_fields), 'phone');
            Log::info('6. Validación de teléfono:', [
                'tiene_telefono' => $phone ? 'SÍ' : 'NO',
                'telefono' => $phone ?? 'NO CONFIGURADO'
            ]);
            if (!$phone) {
                Log::warning('⚠️ Revendedor sin número de teléfono configurado - Saltando');
                continue;
            }

            $message = sprintf(
                "🔔 ¡Nueva Subasta Disponible!\n\n" .
                "📋 Detalles del Vehículo:\n" .
                "🚗 %s\n\n" .
                "⏰ Cronograma:\n" .
                "▪️ Inicio: %s\n" .
                "▪️ Fin: %s\n\n" .
                "💡 ¡No pierdas esta oportunidad! Ingresa ahora para hacer tu oferta.",
                $auctionData['vehiculo'],
                $auctionData['fecha_inicio'],
                $auctionData['fecha_fin']
            );

            try {
                Log::info('7. Intentando enviar mensaje:', [
                    'user_id' => $revendedor->id,
                    'phone' => $phone,
                    'message' => $message
                ]);

                $this->metaWaService->sendMessage($phone, $message);
                
                // Registrar la notificación enviada
                $this->logNotification(
                    'nueva_subasta',
                    'whatsapp',
                    $revendedor->id,
                    $auctionData['id'],
                    $auctionData
                );

                Log::info('✅ Notificación enviada exitosamente', [
                    'user_id' => $revendedor->id,
                    'auction_id' => $auctionData['id']
                ]);
            } catch (\Exception $e) {
                Log::error('❌ Error enviando notificación', [
                    'user_id' => $revendedor->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::info('=== FIN DEL PROCESO DE NOTIFICACIÓN ===');
    }
} 