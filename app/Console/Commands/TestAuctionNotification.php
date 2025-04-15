<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Models\AuctionStatus;
use App\Models\CatalogValue;
use App\Models\Vehicle;
use App\Services\AuctionNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TestAuctionNotification extends Command
{
    protected $signature = 'auction:test-notification {event=nueva_subasta}';
    protected $description = 'Prueba el envío de notificaciones de subastas, creando registros reales en la BD';

    public function handle(AuctionNotificationService $notificationService)
    {
        $event = $this->argument('event');

        $this->info("Probando notificación de subasta: {$event}");

        // Generar datos aleatorios para el vehículo
        $marcas = ['Toyota', 'Nissan', 'Honda', 'Hyundai', 'Kia', 'Mazda', 'Suzuki', 'Mitsubishi', 'Ford', 'Chevrolet'];
        $modelos = [
            'Toyota' => ['Corolla', 'Yaris', 'RAV4', 'Hilux', 'Land Cruiser'],
            'Nissan' => ['Sentra', 'Versa', 'Kicks', 'X-Trail', 'Frontier'],
            'Honda' => ['Civic', 'CR-V', 'HR-V', 'Pilot', 'Accord'],
            'Hyundai' => ['Accent', 'Tucson', 'Santa Fe', 'Creta', 'Elantra'],
            'Kia' => ['Rio', 'Sportage', 'Seltos', 'Sorento', 'Picanto'],
            'Mazda' => ['Mazda3', 'CX-5', 'CX-30', 'CX-9', 'BT-50'],
            'Suzuki' => ['Swift', 'Vitara', 'S-Cross', 'Jimny', 'Baleno'],
            'Mitsubishi' => ['L200', 'Montero', 'ASX', 'Outlander', 'Mirage'],
            'Ford' => ['Ranger', 'EcoSport', 'Territory', 'Mustang', 'Explorer'],
            'Chevrolet' => ['Onix', 'Tracker', 'S10', 'Captiva', 'Spark']
        ];
        $versiones = ['LS', 'LT', 'LTZ', 'GLS', 'GLX', 'GT', 'XEi', 'XLi', 'XRS', 'EX', 'EXL', 'SX', 'DX'];
        $anios = ['2019', '2020', '2021', '2022', '2023', '2024'];
        
        // Seleccionar marca y modelo aleatorios
        $marcaNombre = $marcas[array_rand($marcas)];
        $modeloNombre = $modelos[$marcaNombre][array_rand($modelos[$marcaNombre])];
        $version = $versiones[array_rand($versiones)];
        $anio = $anios[array_rand($anios)];
        
        // Generar placa aleatoria (formato: ABC-123)
        $letras = chr(rand(65, 90)) . chr(rand(65, 90)) . chr(rand(65, 90));
        $numeros = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $placa = $letras . '-' . $numeros;
        
        // Generar kilometraje aleatorio (entre 0 y 100,000 km)
        $kilometraje = rand(0, 100000);
        
        $this->info("Creando registros en la base de datos...");
        
        try {
            DB::beginTransaction();
            
            // 1. Obtener o crear la marca
            $this->info("Buscando o creando marca: {$marcaNombre}");
            $marca = CatalogValue::where('value', $marcaNombre)
                ->where('catalog_type_id', 1) // 1 = marcas
                ->first();
                
            if (!$marca) {
                $marca = CatalogValue::create([
                    'value' => $marcaNombre,
                    'catalog_type_id' => 1,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $this->info("✅ Marca creada: {$marcaNombre} (ID: {$marca->id})");
            } else {
                $this->info("✅ Marca encontrada: {$marcaNombre} (ID: {$marca->id})");
            }
            
            // 2. Obtener o crear el modelo
            $this->info("Buscando o creando modelo: {$modeloNombre}");
            $modelo = CatalogValue::where('value', $modeloNombre)
                ->where('catalog_type_id', 2) // 2 = modelos
                ->first();
                
            if (!$modelo) {
                $modelo = CatalogValue::create([
                    'value' => $modeloNombre,
                    'catalog_type_id' => 2,
                    'brand_id' => $marca->id,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $this->info("✅ Modelo creado: {$modeloNombre} (ID: {$modelo->id})");
            } else {
                $this->info("✅ Modelo encontrado: {$modeloNombre} (ID: {$modelo->id})");
            }
            
            // 3. Crear vehículo
            $this->info("Creando vehículo con placa: {$placa}");
            
            // Obtener IDs necesarios para campos requeridos
            $transmissionId = CatalogValue::where('catalog_type_id', 3)->where('active', true)->inRandomOrder()->first()->id ?? 1;
            $bodyTypeId = CatalogValue::where('catalog_type_id', 4)->where('active', true)->inRandomOrder()->first()->id ?? 1;
            $cylindersId = CatalogValue::where('catalog_type_id', 5)->where('active', true)->inRandomOrder()->first()->id ?? 1;
            $fuelTypeId = CatalogValue::where('catalog_type_id', 6)->where('active', true)->inRandomOrder()->first()->id ?? 1;
            $doorsId = CatalogValue::where('catalog_type_id', 7)->where('active', true)->inRandomOrder()->first()->id ?? 1;
            $tractionId = CatalogValue::where('catalog_type_id', 8)->where('active', true)->inRandomOrder()->first()->id ?? 1;
            $colorId = CatalogValue::where('catalog_type_id', 9)->where('active', true)->inRandomOrder()->first()->id ?? 1;
            $locationId = CatalogValue::where('catalog_type_id', 10)->where('active', true)->inRandomOrder()->first()->id ?? 1;
            
            $vehiculo = Vehicle::create([
                'plate' => $placa,
                'brand_id' => $marca->id,
                'model_id' => $modelo->id,
                'version' => $version,
                'transmission_id' => $transmissionId,
                'body_type_id' => $bodyTypeId,
                'year_made' => $anio,
                'model_year' => $anio,
                'engine_cc' => rand(1000, 4000),
                'cylinders_id' => $cylindersId,
                'fuel_type_id' => $fuelTypeId,
                'mileage' => $kilometraje,
                'doors_id' => $doorsId,
                'traction_id' => $tractionId,
                'color_id' => $colorId,
                'location_id' => $locationId,
                'additional_description' => "Vehículo de prueba creado con el comando auction:test-notification",
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $this->info("✅ Vehículo creado: ID {$vehiculo->id}");
            
            // 4. Crear subasta
            $this->info("Creando subasta para el vehículo");
            $fechaInicio = Carbon::now();
            $fechaFin = Carbon::now()->addDays(3);
            
            // Calcular duración en horas
            $duracionHoras = $fechaInicio->diffInHours($fechaFin);
            $this->info("Duración calculada: {$duracionHoras} horas");
            
            // Obtener ID del estado "Sin Oferta"
            $statusId = AuctionStatus::where('slug', 'sin-oferta')->first()->id;
            
            // Obtener un tasador (usuario con rol de tasador)
            $appraiserId = DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('roles.name', 'tasador')
                ->select('users.id')
                ->first()?->id;
                
            if (!$appraiserId) {
                // Si no hay tasador, usar el primer usuario admin como fallback
                $appraiserId = DB::table('users')
                    ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->where('roles.name', 'admin')
                    ->select('users.id')
                    ->first()?->id ?? 1; // Usar ID 1 como último recurso
            }
            
            $this->info("Usando tasador con ID: {$appraiserId}");
            
            $subasta = Auction::create([
                'vehicle_id' => $vehiculo->id,
                'appraiser_id' => $appraiserId,
                'status_id' => $statusId,
                'start_date' => $fechaInicio,
                'end_date' => $fechaFin,
                'duration_hours' => $duracionHoras,
                'base_price' => rand(5000, 50000),
                'current_price' => rand(5000, 50000),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $this->info("✅ Subasta creada: ID {$subasta->id}");
            
            DB::commit();
            
            // Recargar la subasta con relaciones para asegurar que todos los datos estén correctos
            $subasta->load(['vehicle.brand', 'vehicle.model']);
            
            // Crear el array con los datos para la notificación
            $auctionData = [
                'id' => $subasta->id,
                'vehiculo' => $subasta->vehicle->plate,
                'marca' => $subasta->vehicle->brand->value,
                'modelo' => $subasta->vehicle->model->value,
                'version' => $subasta->vehicle->version,
                'anio' => $subasta->vehicle->year_made,
                'kilometraje' => $subasta->vehicle->mileage,
                'fecha_inicio' => $subasta->start_date->format('d/m/Y H:i'),
                'fecha_fin' => $subasta->end_date->format('d/m/Y H:i'),
            ];
            
            $this->info("Datos para notificación preparados");
            
            // Enviar la notificación
            $this->info("Enviando notificación...");
            
            switch ($event) {
                case 'nueva_subasta':
                    $notificationService->sendNewAuctionNotification($auctionData);
                    break;
                default:
                    $this->error("Evento no soportado: {$event}");
                    return;
            }

            $this->info('Proceso de notificación completado');
            
            // Mostrar resumen
            $this->table(['Campo', 'Valor'], [
                ['Evento', $event],
                ['Subasta ID', $subasta->id],
                ['Placa', $auctionData['vehiculo']],
                ['Marca', $auctionData['marca']],
                ['Modelo', $auctionData['modelo']],
                ['Versión', $auctionData['version']],
                ['Año', $auctionData['anio']],
                ['Kilometraje', $auctionData['kilometraje']],
                ['Inicio', $auctionData['fecha_inicio']],
                ['Fin', $auctionData['fecha_fin']],
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            Log::error("Error en comando test:auction-notification", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
} 