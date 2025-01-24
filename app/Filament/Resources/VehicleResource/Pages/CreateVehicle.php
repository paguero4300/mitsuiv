<?php

namespace App\Filament\Resources\VehicleResource\Pages;

use App\Filament\Resources\VehicleResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;

    /**
     * Este método se llama DESPUÉS de que se crea el registro principal,
     * y DESPUÉS de que Filament mueva los archivos (gracias a ->moveFiles()).
     */
    protected function afterCreate(): void
    {
        $vehicle = $this->record;
        $data = $this->form->getState();

        Log::info('afterCreate: Guardando documentos', [
            'vehicle_id' => $vehicle->id,
            'soat_document' => $data['soat_document'] ?? null,
            'soat_expiry' => $data['soat_expiry'] ?? null,
            'revision_document' => $data['revision_document'] ?? null,
            'revision_expiry' => $data['revision_expiry'] ?? null,
            'tarjeta_document' => $data['tarjeta_document'] ?? null,
            'tarjeta_expiry' => $data['tarjeta_expiry'] ?? null,
        ]);

        // 1) Guardar SOAT
        if (! empty($data['soat_document'])) {
            $vehicle->documents()->updateOrCreate(
                ['type' => 'soat'],
                [
                    'path' => $data['soat_document'], // Ruta final
                    'expiry_date' => $data['soat_expiry'] ?? null,
                ]
            );

            Log::info('SOAT guardado en BD', [
                'path' => $data['soat_document'],
                'expiry_date' => $data['soat_expiry'] ?? null,
            ]);
        }

        // 2) Guardar Revisión Técnica
        if (! empty($data['revision_document'])) {
            $vehicle->documents()->updateOrCreate(
                ['type' => 'revision_tecnica'],
                [
                    'path' => $data['revision_document'],
                    'expiry_date' => $data['revision_expiry'] ?? null,
                ]
            );

            Log::info('Revisión Técnica guardada en BD', [
                'path' => $data['revision_document'],
                'expiry_date' => $data['revision_expiry'] ?? null,
            ]);
        }

        // 3) Guardar Tarjeta de Propiedad
        if (! empty($data['tarjeta_document'])) {
            $vehicle->documents()->updateOrCreate(
                ['type' => 'tarjeta_propiedad'],
                [
                    'path' => $data['tarjeta_document'],
                    'expiry_date' => $data['tarjeta_expiry'] ?? null,
                ]
            );

            Log::info('Tarjeta de Propiedad guardada en BD', [
                'path' => $data['tarjeta_document'],
                'expiry_date' => $data['tarjeta_expiry'] ?? null,
            ]);
        }

        Notification::make()
            ->success()
            ->title('Vehículo y documentos creados correctamente')
            ->send();
    }
}
