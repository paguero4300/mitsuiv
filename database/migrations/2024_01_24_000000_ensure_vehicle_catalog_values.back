<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnsureVehicleCatalogValues extends Migration
{
    public function up()
    {
        // 1. Asegurar que existan los tipos de catálogo
        $types = [
            ['name' => 'marca', 'description' => 'Marca del vehículo'],
            ['name' => 'modelo', 'description' => 'Modelo del vehículo'],
            ['name' => 'transmision', 'description' => 'Tipo de transmisión'],
            ['name' => 'carroceria', 'description' => 'Tipo de carrocería'],
            ['name' => 'cilindros', 'description' => 'Número de cilindros'],
            ['name' => 'combustible', 'description' => 'Tipo de combustible'],
            ['name' => 'puertas', 'description' => 'Número de puertas'],
            ['name' => 'traccion', 'description' => 'Tipo de tracción'],
            ['name' => 'color', 'description' => 'Color del vehículo'],
            ['name' => 'ubicacion', 'description' => 'Ubicación del vehículo'],
        ];

        // Insertar o actualizar tipos de catálogo
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

        // 2. Obtener los IDs de los tipos
        $typeIds = [];
        foreach ($types as $type) {
            $typeIds[$type['name']] = DB::table('catalog_types')
                ->where('name', $type['name'])
                ->value('id');
        }

        // 3. Valores por defecto para cada tipo
        $defaultValues = [
            'transmision' => ['Automática', 'Manual', 'CVT', 'Secuencial'],
            'carroceria' => ['Sedán', 'Hatchback', 'SUV', 'Pickup', 'Van'],
            'cilindros' => ['3', '4', '6', '8', '12'],
            'combustible' => ['Gasolina', 'Diesel', 'GLP', 'GNV', 'Híbrido', 'Eléctrico'],
            'puertas' => ['2', '3', '4', '5'],
            'traccion' => ['4x2', '4x4', 'AWD', 'RWD', 'FWD'],
            'color' => ['Negro', 'Blanco', 'Rojo', 'Azul', 'Plata', 'Gris'],
            'ubicacion' => ['Lima', 'Arequipa', 'Trujillo', 'Cusco', 'Piura'],
        ];

        // 4. Insertar valores por defecto solo si no existen
        foreach ($defaultValues as $type => $values) {
            $typeId = $typeIds[$type];
            
            foreach ($values as $value) {
                // Verificar si ya existe un valor similar
                $exists = DB::table('catalog_values')
                    ->where('catalog_type_id', $typeId)
                    ->where('value', $value)
                    ->exists();

                if (!$exists) {
                    DB::table('catalog_values')->insert([
                        'catalog_type_id' => $typeId,
                        'value' => $value,
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // 5. Verificar y corregir los valores del vehículo existente
        $vehicle = DB::table('vehicles')->find(1);
        if ($vehicle) {
            // Mapear los IDs existentes con sus tipos
            $vehicleValues = [
                'transmission_id' => ['type' => 'transmision', 'id' => $vehicle->transmission_id],
                'body_type_id' => ['type' => 'carroceria', 'id' => $vehicle->body_type_id],
                'cylinders_id' => ['type' => 'cilindros', 'id' => $vehicle->cylinders_id],
                'fuel_type_id' => ['type' => 'combustible', 'id' => $vehicle->fuel_type_id],
                'doors_id' => ['type' => 'puertas', 'id' => $vehicle->doors_id],
                'traction_id' => ['type' => 'traccion', 'id' => $vehicle->traction_id],
                'color_id' => ['type' => 'color', 'id' => $vehicle->color_id],
                'location_id' => ['type' => 'ubicacion', 'id' => $vehicle->location_id],
            ];

            // Verificar cada valor y corregir si es necesario
            $updates = [];
            foreach ($vehicleValues as $field => $data) {
                if ($data['id']) {
                    // Verificar si el ID existe en catalog_values
                    $exists = DB::table('catalog_values')
                        ->where('id', $data['id'])
                        ->exists();

                    if (!$exists) {
                        // Si no existe, asignar el primer valor disponible del tipo correcto
                        $newId = DB::table('catalog_values')
                            ->where('catalog_type_id', $typeIds[$data['type']])
                            ->where('active', true)
                            ->value('id');

                        if ($newId) {
                            $updates[$field] = $newId;
                        }
                    }
                }
            }

            // Actualizar el vehículo si hay cambios necesarios
            if (!empty($updates)) {
                DB::table('vehicles')
                    ->where('id', $vehicle->id)
                    ->update($updates);
            }
        }
    }

    public function down()
    {
        // No es necesario un down() ya que solo estamos asegurando la existencia de datos
    }
} 