<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Models\Vehicle;
use App\Models\User;
use App\Models\CatalogValue;
use App\Models\CatalogType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TestCreateAuction extends Command
{
    protected $signature = 'test:create-auction';
    protected $description = 'Crea una subasta de prueba para testear notificaciones';

    public function handle()
    {
        $this->info('Creando subasta de prueba...');

        // Obtener un tasador
        $tasador = User::role('tasador')->first();
        if (!$tasador) {
            $this->error('No se encontró un tasador en el sistema.');
            return 1;
        }

        // Verificar tipos de catálogo disponibles
        $availableTypes = CatalogType::where('active', true)->get();
        $this->info('Tipos de catálogo disponibles:');
        $this->table(
            ['ID', 'Nombre', 'Descripción'],
            $availableTypes->map(fn($type) => [$type->id, $type->name, $type->description])->toArray()
        );

        // Obtener IDs de catálogo necesarios
        try {
            // Primero obtenemos los tipos de catálogo
            $typeMapping = [
                'Marca' => 'brand',
                'Modelo' => 'model',
                'transmision' => 'transmission',
                'carroceria' => 'body_type',
                'cilindros' => 'cylinders',
                'combustible' => 'fuel_type',
                'puertas' => 'doors',
                'traccion' => 'traction',
                'color' => 'color',
                'ubicacion' => 'location'
            ];

            $this->info('Verificando tipos requeridos...');

            $catalogTypes = CatalogType::whereIn('name', array_keys($typeMapping))
                ->where('active', true)
                ->get();

            // Verificar qué tipos faltan
            $missingTypes = array_diff(array_keys($typeMapping), $catalogTypes->pluck('name')->toArray());
            if (!empty($missingTypes)) {
                throw new \Exception('Faltan los siguientes tipos de catálogo: ' . implode(', ', $missingTypes));
            }

            // Crear mapeo de IDs
            $catalogValues = [];
            foreach ($catalogTypes as $type) {
                $value = CatalogValue::where('catalog_type_id', $type->id)
                    ->where('active', true)
                    ->first();
                
                if (!$value) {
                    throw new \Exception("No hay valores activos para el catálogo: {$type->name}");
                }
                
                $catalogValues[$typeMapping[$type->name]] = $value->id;
            }

            $this->info('Valores de catálogo obtenidos correctamente');

        } catch (\Exception $e) {
            $this->error('Error obteniendo valores del catálogo: ' . $e->getMessage());
            return 1;
        }

        // Crear un nuevo vehículo de prueba
        try {
            $vehicle = Vehicle::create([
                'plate' => 'TEST-' . rand(100, 999),
                'brand_id' => $catalogValues['brand'],
                'model_id' => $catalogValues['model'],
                'version' => 'Test Version',
                'transmission_id' => $catalogValues['transmission'],
                'body_type_id' => $catalogValues['body_type'],
                'year_made' => 2023,
                'model_year' => 2023,
                'engine_cc' => 2000,
                'cylinders_id' => $catalogValues['cylinders'],
                'fuel_type_id' => $catalogValues['fuel_type'],
                'mileage' => 0,
                'doors_id' => $catalogValues['doors'],
                'traction_id' => $catalogValues['traction'],
                'color_id' => $catalogValues['color'],
                'location_id' => $catalogValues['location'],
                'additional_description' => 'Vehículo de prueba para testing de notificaciones',
            ]);

            $this->info('Vehículo creado: Placa ' . $vehicle->plate);
            Log::info('Vehículo creado para subasta de prueba', ['vehicle_id' => $vehicle->id]);

        } catch (\Exception $e) {
            $this->error('Error creando vehículo: ' . $e->getMessage());
            return 1;
        }

        // Crear la subasta
        try {
            $duration_hours = 72; // 3 días en horas
            $start_date = now()->addHour();
            $end_date = $start_date->copy()->addHours($duration_hours);
            
            $auction = Auction::create([
                'vehicle_id' => $vehicle->id,
                'appraiser_id' => $tasador->id,
                'status_id' => 2, // 2 = Sin Oferta (estado inicial)
                'start_date' => $start_date,
                'end_date' => $end_date,
                'duration_hours' => $duration_hours,
                'base_price' => 25000.00,
                'current_price' => null, // Inicialmente null hasta que haya pujas
            ]);

            Log::info('Subasta creada exitosamente', [
                'auction_id' => $auction->id,
                'vehicle_id' => $vehicle->id,
                'appraiser_id' => $tasador->id
            ]);

            $this->info('Subasta creada con ID: ' . $auction->id);
            
            // Probar el servicio de notificación directamente
            try {
                $auctionData = [
                    'id' => $auction->id,
                    'vehiculo' => $vehicle->plate,
                    'fecha_inicio' => $auction->start_date->format('d/m/Y H:i'),
                    'fecha_fin' => $auction->end_date->format('d/m/Y H:i'),
                ];

                $notificationService = app(\App\Services\AuctionNotificationService::class);
                $notificationService->sendNewAuctionNotification($auctionData);
                
                $this->info('Notificación enviada directamente.');
            } catch (\Exception $e) {
                $this->error('Error enviando notificación: ' . $e->getMessage());
            }
            
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['ID', $auction->id],
                    ['Vehículo', 'Placa: ' . $vehicle->plate],
                    ['Tasador', $tasador->name],
                    ['Estado', 'Sin Oferta'],
                    ['Inicio', $auction->start_date->format('d/m/Y H:i')],
                    ['Fin', $auction->end_date->format('d/m/Y H:i')],
                    ['Duración', $auction->duration_hours . ' horas'],
                    ['Precio Base', '$' . number_format($auction->base_price, 2)],
                    ['Notificación', 'Se enviará a: 51933300793'],
                ]
            );

            $this->info('El observer debería haber disparado el job de notificación.');
            $this->info('Revisa los logs para ver el resultado del envío.');

        } catch (\Exception $e) {
            $this->error('Error creando la subasta: ' . $e->getMessage());
            Log::error('Error en creación de subasta', [
                'error' => $e->getMessage(),
                'vehicle_id' => $vehicle->id ?? null
            ]);
            return 1;
        }
    }
} 