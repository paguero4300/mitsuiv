<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    protected $fillable = [
        'role_type',    // tipo de rol
        'event_type',   // tipo de evento
        'channel_id',   // ID del canal
        'is_enabled'    // está habilitado
    ];

    protected $casts = [
        'is_enabled' => 'boolean'
    ];

    /**
     * Constantes para los tipos de eventos por rol
     */
    const EVENT_TYPES = [
        'revendedor' => [
            'nueva_subasta' => 'Nueva Subasta Disponible',
            'primera_puja' => 'Primera Puja Registrada',
            'puja_superada' => 'Puja Superada',
            'subasta_por_terminar' => 'Subasta Próxima a Terminar',
            'subasta_ganada' => 'Ganaste la Subasta',
            'subasta_adjudicada' => 'Subasta Adjudicada',
            'subasta_fallida' => 'Subasta Fallida'
        ],
        'tasador' => [
            'subasta_creada' => 'Subasta Creada Exitosamente',
            'nueva_puja' => 'Nueva Puja Recibida',
            'subasta_cerrada' => 'Cierre de Subasta',
            'recordatorio_adjudicacion' => 'Recordatorio para Adjudicar',
            'confirmacion_adjudicacion' => 'Confirmación de Adjudicación'
        ]
    ];

    /**
     * Obtiene el canal asociado a esta configuración
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class, 'channel_id');
    }

    /**
     * Obtiene los tipos de eventos para un rol específico
     */
    public static function getEventTypes(string $roleType): array
    {
        return self::EVENT_TYPES[$roleType] ?? [];
    }

    /**
     * Obtiene todos los tipos de eventos disponibles
     */
    public static function getAllEventTypes(): array
    {
        return self::EVENT_TYPES;
    }
}