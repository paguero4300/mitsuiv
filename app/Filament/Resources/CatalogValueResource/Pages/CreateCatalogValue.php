<?php

namespace App\Filament\Resources\CatalogValueResource\Pages;

use App\Filament\Resources\CatalogValueResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\CatalogValue;

class CreateCatalogValue extends CreateRecord
{
    protected static string $resource = CatalogValueResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Validar valor único por tipo de catálogo
        $exists = CatalogValue::where('catalog_type_id', $data['catalog_type_id'])
            ->where('value', $data['value'])
            ->exists();

        if ($exists) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Valor duplicado')
                ->body('Ya existe un valor con este nombre para el tipo de catálogo seleccionado.')
                ->send();

            $this->halt();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('saveAndCreateAnother')
                ->label('Guardar y Crear Otro')
                ->action(function () {
                    $this->create();
                    
                    // Redireccionar al formulario de creación
                    return redirect($this->getResource()::getUrl('create'));
                })
                ->color('success')
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
