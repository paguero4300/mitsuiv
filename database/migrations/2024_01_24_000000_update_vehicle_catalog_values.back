<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateVehicleCatalogValues extends Migration
{
    public function up()
    {
        // Primero, asegurarnos que los catalog_types existen
        $types = [
            ['name' => 'transmision', 'description' => 'Tipo de transmisión'],
            ['name' => 'carroceria', 'description' => 'Tipo de carrocería'],
            ['name' => 'cilindros', 'description' => 'Número de cilindros'],
            ['name' => 'combustible', 'description' => 'Tipo de combustible'],
            ['name' => 'puertas', 'description' => 'Número de puertas'],
            ['name' => 'traccion', 'description' => 'Tipo de tracción'],
            ['name' => 'color', 'description' => 'Color del vehículo'],
            ['name' => 'ubicacion', 'description' => 'Ubicación del vehículo'],
        ];

        foreach ($types as $type) {
            DB::table('catalog_types')
                ->updateOrInsert(
                    ['name' => $type['name']],
                    [
                        'description' => $type['description'],
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
        }

        // Luego, verificar y corregir los catalog_values
        $vehicle = DB::table('vehicles')->find(1);
        if ($vehicle) {
            $values = [
                ['id' => $vehicle->transmission_id, 'type' => 'transmision', 'value' => 'Automática'],
                ['id' => $vehicle->body_type_id, 'type' => 'carroceria', 'value' => 'Hatchback'],
                ['id' => $vehicle->cylinders_id, 'type' => 'cilindros', 'value' => '4'],
                ['id' => $vehicle->fuel_type_id, 'type' => 'combustible', 'value' => 'Gasolina'],
                ['id' => $vehicle->doors_id, 'type' => 'puertas', 'value' => '5'],
                ['id' => $vehicle->traction_id, 'type' => 'traccion', 'value' => '4x2'],
                ['id' => $vehicle->color_id, 'type' => 'color', 'value' => 'Negro'],
                ['id' => $vehicle->location_id, 'type' => 'ubicacion', 'value' => 'Lima'],
            ];

            foreach ($values as $value) {
                $typeId = DB::table('catalog_types')
                    ->where('name', $value['type'])
                    ->value('id');

                if ($value['id']) {
                    DB::table('catalog_values')
                        ->updateOrInsert(
                            ['id' => $value['id']],
                            [
                                'catalog_type_id' => $typeId,
                                'value' => $value['value'],
                                'active' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                }
            }
        }
    }

    public function down()
    {
        // No es necesario un down() ya que solo estamos corrigiendo datos
    }
} 