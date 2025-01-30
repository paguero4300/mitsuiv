<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CatalogType;
use App\Models\CatalogValue;

class CreateDefaultCatalogValues extends Command
{
    protected $signature = 'catalog:create-defaults';
    protected $description = 'Crea valores por defecto (Sin especificar) en los catálogos';

    public function handle()
    {
        $this->info('Creando valores por defecto en catálogos...');

        // Lista de tipos de catálogo que necesitan valor por defecto
        $catalogTypes = [
            'puertas',
            'color'
        ];

        foreach ($catalogTypes as $typeName) {
            $type = CatalogType::where('name', $typeName)->first();
            
            if (!$type) {
                $this->error("Tipo de catálogo '{$typeName}' no encontrado");
                continue;
            }

            // Verificar si ya existe el valor por defecto
            $exists = CatalogValue::where('catalog_type_id', $type->id)
                ->where('value', 'Sin especificar')
                ->exists();

            if ($exists) {
                $this->info("Valor por defecto para '{$typeName}' ya existe");
                continue;
            }

            // Crear el valor por defecto
            CatalogValue::create([
                'catalog_type_id' => $type->id,
                'value' => 'Sin especificar',
                'description' => 'Valor por defecto',
                'active' => true
            ]);

            $this->info("Valor por defecto creado para '{$typeName}'");
        }

        $this->info('¡Proceso completado!');
    }
} 