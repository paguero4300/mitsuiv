<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleEquipment extends Model
{
    /**
     * Lista de atributos que se pueden asignar masivamente.
     * Incluye todos los campos de equipamiento del vehículo.
     */
    protected $fillable = [
        // Campo de identificación
        'vehicle_id',

        // Airbags y seguridad básica
        'airbags_count',
        'air_bags',
        'abs_ebs',
        'security_glass',
        'anti_collision',
        'alarm',
        'armored',

        // Sistema de climatización
        'air_conditioning',
        'mono_zone_ac',
        'multi_zone_ac',
        'bi_zone_ac',

        // Asientos y confort
        'electric_seats',
        'leather_seats',
        'cruise_control',
        'sunroof',
        'roof_rack',

        // Sistema de iluminación
        'front_fog_lights',
        'rear_fog_lights',
        'bi_led_lights',
        'halogen_lights',
        'led_lights',

        // Multimedia y conectividad
        'apple_carplay',
        'usb_ports',
        'steering_controls',
        'touch_screen',
        'speakers',
        'cd_player',
        'mp3_player',

        // Sistemas de asistencia y cámaras
        'parking_assistant',
        'front_camera',
        'right_camera',
        'left_camera',
        'rear_camera',
        'parking_sensors',
        'gps',
        'electric_mirrors',

        // Ruedas
        'wheels',
        'alloy_wheels',

        // Documentación y garantías
        'factory_warranty',
        'complete_documentation',
        'guaranteed_mileage',
        'part_payment',
        'financing'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     * Todos los campos booleanos se castean automáticamente a boolean.
     */
    protected $casts = [
        // Campos booleanos para características del vehículo
        'air_bags' => 'boolean',
        'air_conditioning' => 'boolean',
        'alarm' => 'boolean',
        'apple_carplay' => 'boolean',
        'wheels' => 'boolean',
        'alloy_wheels' => 'boolean',
        'electric_seats' => 'boolean',
        'leather_seats' => 'boolean',
        'parking_assistant' => 'boolean',
        'armored' => 'boolean',
        'front_camera' => 'boolean',
        'right_camera' => 'boolean',
        'left_camera' => 'boolean',
        'rear_camera' => 'boolean',
        'mono_zone_ac' => 'boolean',
        'multi_zone_ac' => 'boolean',
        'bi_zone_ac' => 'boolean',
        'usb_ports' => 'boolean',
        'steering_controls' => 'boolean',
        'front_fog_lights' => 'boolean',
        'rear_fog_lights' => 'boolean',
        'abs_ebs' => 'boolean',
        'security_glass' => 'boolean',
        'gps' => 'boolean',
        'bi_led_lights' => 'boolean',
        'halogen_lights' => 'boolean',
        'led_lights' => 'boolean',
        'touch_screen' => 'boolean',
        'speakers' => 'boolean',
        'cd_player' => 'boolean',
        'mp3_player' => 'boolean',
        'electric_mirrors' => 'boolean',
        'parking_sensors' => 'boolean',
        'anti_collision' => 'boolean',
        'sunroof' => 'boolean',
        'cruise_control' => 'boolean',
        'factory_warranty' => 'boolean',
        'complete_documentation' => 'boolean',
        'guaranteed_mileage' => 'boolean',
        'part_payment' => 'boolean',
        'financing' => 'boolean',
        'roof_rack' => 'boolean'
    ];

    /**
     * Define la relación con el modelo Vehicle.
     * Cada equipamiento pertenece a un único vehículo.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
