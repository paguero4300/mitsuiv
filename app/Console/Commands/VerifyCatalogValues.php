<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyCatalogValues extends Command
{
    protected $signature = 'catalog:verify';
    protected $description = 'Verifica los valores de catálogo para los vehículos';

    public function handle()
    {
        $vehicle = DB::table('vehicles')->find(1);
        
        if (!$vehicle) {
            $this->error('No se encontró el vehículo con ID 1');
            return;
        }

        $this->info('IDs del vehículo:');
        $this->table(
            ['Campo', 'ID'],
            [
                ['transmission_id', $vehicle->transmission_id ?? 'null'],
                ['body_type_id', $vehicle->body_type_id ?? 'null'],
                ['cylinders_id', $vehicle->cylinders_id ?? 'null'],
                ['fuel_type_id', $vehicle->fuel_type_id ?? 'null'],
                ['doors_id', $vehicle->doors_id ?? 'null'],
                ['traction_id', $vehicle->traction_id ?? 'null'],
                ['color_id', $vehicle->color_id ?? 'null'],
                ['location_id', $vehicle->location_id ?? 'null'],
            ]
        );

        $ids = array_filter([
            $vehicle->transmission_id,
            $vehicle->body_type_id,
            $vehicle->cylinders_id,
            $vehicle->fuel_type_id,
            $vehicle->doors_id,
            $vehicle->traction_id,
            $vehicle->color_id,
            $vehicle->location_id,
        ]);

        if (empty($ids)) {
            $this->warn('No hay IDs para verificar');
            return;
        }

        $values = DB::table('catalog_values')
            ->whereIn('id', $ids)
            ->get();

        $this->info('Valores encontrados en catalog_values:');
        $this->table(
            ['ID', 'Tipo', 'Valor', 'Activo'],
            $values->map(function($value) {
                $type = DB::table('catalog_types')
                    ->where('id', $value->catalog_type_id)
                    ->value('name');
                    
                return [
                    $value->id,
                    $type ?? 'desconocido',
                    $value->value,
                    $value->active ? 'Sí' : 'No',
                ];
            })
        );

        // Verificar IDs que no existen
        $existingIds = $values->pluck('id')->toArray();
        $missingIds = array_diff($ids, $existingIds);

        if (!empty($missingIds)) {
            $this->error('IDs no encontrados en catalog_values:');
            $this->line(implode(', ', $missingIds));
        }

        // Mostrar todos los tipos de catálogo
        $types = DB::table('catalog_types')->get();
        $this->info('Tipos de catálogo disponibles:');
        $this->table(
            ['ID', 'Nombre', 'Descripción', 'Activo'],
            $types->map(function($type) {
                return [
                    $type->id,
                    $type->name,
                    $type->description,
                    $type->active ? 'Sí' : 'No',
                ];
            })
        );
    }
} 