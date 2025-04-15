<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Vehicle;
use App\Models\Auction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class CreateTestAuctionForEmailNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:create-auction-for-email-notification {minutes=10}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un vehículo y una subasta programada para iniciar en X minutos (10 por defecto) para probar las notificaciones por email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando creación de datos de prueba para notificación por email...');

        try {
            DB::beginTransaction();
            
            // Obtenemos los minutos desde la línea de comandos (10 por defecto)
            $minutes = (int) $this->argument('minutes');
            
            // 1. Crear vehículo de prueba
            $this->info('Creando vehículo de prueba...');
            
            // Generar una placa aleatoria
            $randomPlate = 'TEST' . rand(1000, 9999);
            
            // Valores por defecto para probar (ajusta estos valores según tus catálogos existentes)
            $defaultBrandId = DB::table('catalog_values')->where('catalog_type_id', 1)->value('id') ?? 1;
            $defaultModelId = DB::table('catalog_values')->where('catalog_type_id', 2)->value('id') ?? 1;
            $defaultTransmissionId = DB::table('catalog_values')->where('catalog_type_id', 3)->value('id') ?? 1;
            $defaultBodyTypeId = DB::table('catalog_values')->where('catalog_type_id', 4)->value('id') ?? 1;
            $defaultCylindersId = DB::table('catalog_values')->where('catalog_type_id', 5)->value('id') ?? 1;
            $defaultFuelTypeId = DB::table('catalog_values')->where('catalog_type_id', 6)->value('id') ?? 1;
            $defaultDoorsId = DB::table('catalog_values')->where('catalog_type_id', 7)->value('id') ?? 1;
            $defaultTractionId = DB::table('catalog_values')->where('catalog_type_id', 8)->value('id') ?? 1;
            $defaultColorId = DB::table('catalog_values')->where('catalog_type_id', 9)->value('id') ?? 1;
            $defaultLocationId = DB::table('catalog_values')->where('catalog_type_id', 10)->value('id') ?? 1;
            
            $vehicle = Vehicle::create([
                'plate' => $randomPlate,
                'brand_id' => $defaultBrandId,
                'model_id' => $defaultModelId,
                'version' => 'Test Version',
                'transmission_id' => $defaultTransmissionId,
                'body_type_id' => $defaultBodyTypeId,
                'year_made' => 2023,
                'model_year' => 2023,
                'engine_cc' => 2000,
                'cylinders_id' => $defaultCylindersId,
                'fuel_type_id' => $defaultFuelTypeId,
                'mileage' => 10000,
                'doors_id' => $defaultDoorsId,
                'traction_id' => $defaultTractionId,
                'color_id' => $defaultColorId,
                'location_id' => $defaultLocationId,
                'additional_description' => 'Vehículo de prueba para notificaciones por email'
            ]);
            
            $this->info("Vehículo creado con placa: {$vehicle->plate}");
            
            // 2. Calcular fechas para la subasta (hora Lima)
            $currentTime = Carbon::now('America/Lima');
            $startDate = $currentTime->copy()->addMinutes($minutes);
            $endDate = $startDate->copy()->addHours(2);
            
            $this->info("Configurando subasta para iniciar en {$minutes} minutos:");
            $this->info("- Hora actual: {$currentTime->format('Y-m-d H:i:s')}");
            $this->info("- Inicio: {$startDate->format('Y-m-d H:i:s')}");
            $this->info("- Fin: {$endDate->format('Y-m-d H:i:s')}");
            
            // Encontrar un usuario tasador
            $appraiserId = User::role('tasador')->value('id');
            if (!$appraiserId) {
                $this->warn('No se encontró un tasador. Usando el primer usuario admin como tasador.');
                $appraiserId = User::role('admin')->value('id') ?? 1;
            }
            
            // Obtener un estado válido para la subasta
            $statusId = DB::table('auction_statuses')->first();
            
            if (!$statusId) {
                $this->error('No hay estados de subasta configurados en la base de datos. Por favor, crea al menos uno.');
                return Command::FAILURE;
            }
            
            $statusId = $statusId->id;
            $this->info("Usando status_id: {$statusId}");
            
            // 3. Crear subasta
            $auction = Auction::create([
                'vehicle_id' => $vehicle->id,
                'appraiser_id' => $appraiserId,
                'status_id' => $statusId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'duration_hours' => 2,
                'base_price' => 5000.00,
                'current_price' => 5000.00,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $this->info("Subasta creada con ID: {$auction->id}");

            // 4. Verificar que existan revendedores con email
            $revendedores = User::role('revendedor')->count();
            
            if ($revendedores == 0) {
                $this->warn('¡ADVERTENCIA! No hay usuarios con rol "revendedor" en el sistema.');
                $this->warn('Creando un revendedor de prueba...');
                
                // Verificar si existe el rol
                $revendedorRole = Role::where('name', 'revendedor')->first();
                if (!$revendedorRole) {
                    $this->warn('Rol "revendedor" no encontrado. Creándolo...');
                    $revendedorRole = Role::create(['name' => 'revendedor', 'guard_name' => 'web']);
                }
                
                // Crear un revendedor de prueba si no existe ninguno
                $user = User::create([
                    'name' => 'Revendedor Prueba',
                    'email' => 'test.revendedor@example.com',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                    'custom_fields' => json_encode(['phone' => '51987654321'])
                ]);
                
                // Asignar rol de revendedor
                $user->assignRole('revendedor');
                
                $this->info("Revendedor de prueba creado: {$user->email}");
            } else {
                $this->info("Se encontraron {$revendedores} revendedores en el sistema que recibirán la notificación.");
            }
            
            DB::commit();
            
            $this->info('');
            $this->info('================================================');
            $this->info('¡DATOS DE PRUEBA CREADOS EXITOSAMENTE!');
            $this->info('================================================');
            $this->info("ID de subasta: {$auction->id}");
            $this->info("Placa del vehículo: {$vehicle->plate}");
            $this->info("Inicio programado para: {$startDate->format('Y-m-d H:i:s')} (Lima)");
            $this->info('');
            $this->info('Para probar el envío de notificaciones:');
            $this->info('1. Espere a que el job programado se ejecute');
            $this->info('2. O ejecute manualmente: php artisan queue:work');
            $this->info('3. Revise el log para ver el proceso de notificación');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error al crear datos de prueba: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            
            return Command::FAILURE;
        }
    }
} 