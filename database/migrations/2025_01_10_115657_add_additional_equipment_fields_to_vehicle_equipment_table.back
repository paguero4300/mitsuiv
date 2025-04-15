<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicle_equipment', function (Blueprint $table) {
            // Añadimos los campos de cámaras
            $table->boolean('front_camera')->default(false)->after('leather_seats');
            $table->boolean('right_camera')->default(false)->after('front_camera');
            $table->boolean('left_camera')->default(false)->after('right_camera');
            $table->boolean('rear_camera')->default(false)->after('left_camera');

            // Añadimos los campos de climatización
            $table->boolean('mono_zone_ac')->default(false)->after('rear_camera');
            $table->boolean('multi_zone_ac')->default(false)->after('mono_zone_ac');
            $table->boolean('bi_zone_ac')->default(false)->after('multi_zone_ac');

            // Añadimos los campos de conectividad y controles
            $table->boolean('usb_ports')->default(false)->after('bi_zone_ac');
            $table->boolean('steering_controls')->default(false)->after('usb_ports');

            // Añadimos los campos de iluminación
            $table->boolean('front_fog_lights')->default(false)->after('steering_controls');
            $table->boolean('rear_fog_lights')->default(false)->after('front_fog_lights');
            $table->boolean('bi_led_lights')->default(false)->after('rear_fog_lights');
            $table->boolean('halogen_lights')->default(false)->after('bi_led_lights');
            $table->boolean('led_lights')->default(false)->after('halogen_lights');

            // Añadimos los campos de seguridad
            $table->boolean('abs_ebs')->default(false)->after('led_lights');
            $table->boolean('security_glass')->default(false)->after('abs_ebs');
            $table->boolean('anti_collision')->default(false)->after('security_glass');

            // Añadimos los campos de navegación y entretenimiento
            $table->boolean('gps')->default(false)->after('anti_collision');
            $table->boolean('touch_screen')->default(false)->after('gps');
            $table->boolean('speakers')->default(false)->after('touch_screen');
            $table->boolean('cd_player')->default(false)->after('speakers');
            $table->boolean('mp3_player')->default(false)->after('cd_player');

            // Añadimos los campos de confort y conveniencia
            $table->boolean('electric_mirrors')->default(false)->after('mp3_player');
            $table->boolean('parking_sensors')->default(false)->after('electric_mirrors');
            $table->boolean('sunroof')->default(false)->after('parking_sensors');
            $table->boolean('cruise_control')->default(false)->after('sunroof');
            $table->boolean('roof_rack')->default(false)->after('cruise_control');

            // Añadimos los campos de documentación y garantías
            $table->boolean('factory_warranty')->default(false)->after('roof_rack');
            $table->boolean('complete_documentation')->default(false)->after('factory_warranty');
            $table->boolean('guaranteed_mileage')->default(false)->after('complete_documentation');

            // Añadimos los campos de financiamiento
            $table->boolean('part_payment')->default(false)->after('guaranteed_mileage');
            $table->boolean('financing')->default(false)->after('part_payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_equipment', function (Blueprint $table) {
            // Eliminamos todos los campos añadidos
            $table->dropColumn([
                'front_camera',
                'right_camera',
                'left_camera',
                'rear_camera',
                'mono_zone_ac',
                'multi_zone_ac',
                'bi_zone_ac',
                'usb_ports',
                'steering_controls',
                'front_fog_lights',
                'rear_fog_lights',
                'bi_led_lights',
                'halogen_lights',
                'led_lights',
                'abs_ebs',
                'security_glass',
                'anti_collision',
                'gps',
                'touch_screen',
                'speakers',
                'cd_player',
                'mp3_player',
                'electric_mirrors',
                'parking_sensors',
                'sunroof',
                'cruise_control',
                'roof_rack',
                'factory_warranty',
                'complete_documentation',
                'guaranteed_mileage',
                'part_payment',
                'financing'
            ]);
        });
    }
};
