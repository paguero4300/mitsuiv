<?php

namespace App\Filament\Resources\VehicleResource\Pages;

use App\Filament\Resources\VehicleResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Filament\Actions\Action;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getFormActions(): array
    {
        return [];
    }

    // Este método configura nuestros botones personalizados en el encabezado
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar cambios')
                ->icon('heroicon-o-check')
                ->action(fn() => $this->save()),
            Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-x-mark')
                ->url($this->getResource()::getUrl('index')),
        ];
    }

    // Esto asegura que no haya botones en el pie de página
    protected function getFooterActions(): array
    {
        return [];
    }

    /**
     * Este método se llama DESPUÉS de que se actualiza el registro principal,
     * y DESPUÉS de que Filament mueva los archivos (gracias a ->moveFiles()).
     */
    protected function afterSave(): void
    {
        $vehicle = $this->record;
        $data = $this->form->getState();

        Log::info('afterSave: Actualizando documentos', [
            'vehicle_id' => $vehicle->id,
            'soat_document' => $data['soat_document'] ?? null,
            'soat_expiry' => $data['soat_expiry'] ?? null,
            'revision_document' => $data['revision_document'] ?? null,
            'revision_expiry' => $data['revision_expiry'] ?? null,
            'tarjeta_document' => $data['tarjeta_document'] ?? null,
            'tarjeta_expiry' => $data['tarjeta_expiry'] ?? null,
        ]);

        // 1) Actualizar SOAT
        if (! empty($data['soat_document'])) {
            // Eliminar el archivo anterior si existe
            $oldDoc = $vehicle->documents()->where('type', 'soat')->first();
            if ($oldDoc && $oldDoc->path !== $data['soat_document']) {
                Storage::disk('public')->delete($oldDoc->path);
                Log::info('Revisión Técnica: Archivo anterior eliminado', ['old_path' => $oldDoc->path]);
            }

            $vehicle->documents()->updateOrCreate(
                ['type' => 'soat'],
                [
                    'path' => $data['soat_document'], // Ruta final
                    'expiry_date' => $data['soat_expiry'] ?? null,
                ]
            );

            Log::info('SOAT actualizado en BD', [
                'path' => $data['soat_document'],
                'expiry_date' => $data['soat_expiry'] ?? null,
            ]);
        }

        // 2) Actualizar Revisión Técnica
        if (! empty($data['revision_document'])) {
            // Eliminar el archivo anterior si existe
            $oldDoc = $vehicle->documents()->where('type', 'revision_tecnica')->first();
            if ($oldDoc && $oldDoc->path !== $data['revision_document']) {
                Storage::disk('public')->delete($oldDoc->path);
                Log::info('Revisión Técnica: Archivo anterior eliminado', ['old_path' => $oldDoc->path]);
            }

            $vehicle->documents()->updateOrCreate(
                ['type' => 'revision_tecnica'],
                [
                    'path' => $data['revision_document'],
                    'expiry_date' => $data['revision_expiry'] ?? null,
                ]
            );

            Log::info('Revisión Técnica actualizada en BD', [
                'path' => $data['revision_document'],
                'expiry_date' => $data['revision_expiry'] ?? null,
            ]);
        }

        // 3) Actualizar Tarjeta de Propiedad
        if (! empty($data['tarjeta_document'])) {
            // Eliminar el archivo anterior si existe
            $oldDoc = $vehicle->documents()->where('type', 'tarjeta_propiedad')->first();
            if ($oldDoc && $oldDoc->path !== $data['tarjeta_document']) {
                Storage::disk('public')->delete($oldDoc->path);
                Log::info('Tarjeta de Propiedad: Archivo anterior eliminado', ['old_path' => $oldDoc->path]);
            }

            $vehicle->documents()->updateOrCreate(
                ['type' => 'tarjeta_propiedad'],
                [
                    'path' => $data['tarjeta_document'],
                    'expiry_date' => $data['tarjeta_expiry'] ?? null,
                ]
            );

            Log::info('Tarjeta de Propiedad actualizada en BD', [
                'path' => $data['tarjeta_document'],
                'expiry_date' => $data['tarjeta_expiry'] ?? null,
            ]);
        }

        // Extraer solo los campos de equipamiento
        $equipmentData = collect($data)->only([
            'airbags_count',
            'air_conditioning',
            'alarm',
            'apple_carplay',
            'wheels',
            'alloy_wheels',
            'electric_seats',
            'leather_seats',
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
        ])->toArray();

        // Actualizar o crear el equipamiento
        $vehicle->equipment()->updateOrCreate(
            ['vehicle_id' => $vehicle->id],
            $equipmentData
        );

        Notification::make()
            ->success()
            ->title('Vehículo y documentos actualizados correctamente')
            ->send();
    }

    /**
     * Este método se llama ANTES de llenar el formulario con los datos del registro.
     * Aquí precargamos las rutas de los documentos existentes en los campos correspondientes.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Cargar los documentos existentes
        $documents = $this->record->documents()->get()->keyBy('type');

        // Precargar SOAT
        if ($documents->has('soat')) {
            $data['soat_document'] = $documents['soat']->path;
            $data['soat_expiry'] = $documents['soat']->expiry_date;
        }

        // Precargar Revisión Técnica
        if ($documents->has('revision_tecnica')) {
            $data['revision_document'] = $documents['revision_tecnica']->path;
            $data['revision_expiry'] = $documents['revision_tecnica']->expiry_date;
        }

        // Precargar Tarjeta de Propiedad
        if ($documents->has('tarjeta_propiedad')) {
            $data['tarjeta_document'] = $documents['tarjeta_propiedad']->path;
            $data['tarjeta_expiry'] = $documents['tarjeta_propiedad']->expiry_date;
        }

        // Precargar datos de equipamiento
        if ($equipment = $this->record->equipment) {
            $data = array_merge($data, $equipment->toArray());
        }

        Log::info('mutateFormDataBeforeFill: Precargando documentos y equipamiento', [
            'vehicle_id' => $this->record->id,
            'soat_document' => $data['soat_document'] ?? null,
            'soat_expiry' => $data['soat_expiry'] ?? null,
            'revision_document' => $data['revision_document'] ?? null,
            'revision_expiry' => $data['revision_expiry'] ?? null,
            'tarjeta_document' => $data['tarjeta_document'] ?? null,
            'tarjeta_expiry' => $data['tarjeta_expiry'] ?? null,
            'equipment' => $equipment ? true : false
        ]);

        return $data;
    }
}
