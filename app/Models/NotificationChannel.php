<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationChannel extends Model
{
    protected $fillable = [
        'channel_type', // tipo de canal
        'is_enabled'    // está habilitado
    ];

    protected $casts = [
        'is_enabled' => 'boolean'
    ];

    /**
     * Prevenir que el modelo sea eliminado
     */
    public static function boot()
    {
        parent::boot();
        
        static::deleting(function ($model) {
            return false;
        });
    }

    /**
     * Obtiene las configuraciones de notificación asociadas
     */
    public function notificationSettings(): HasMany
    {
        return $this->hasMany(NotificationSetting::class, 'channel_id');
    }
}