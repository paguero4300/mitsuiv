<?php

namespace App\Filament\Resources\CatalogValueResource\Pages;

use App\Filament\Resources\CatalogValueResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\CatalogValue;

class EditCatalogValue extends EditRecord
{
    protected static string $resource = CatalogValueResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Validar valor único por tipo de catálogo, excluyendo el registro actual
        $exists = CatalogValue::where('catalog_type_id', $data['catalog_type_id'])
            ->where('value', $data['value'])
            ->where('id', '!=', $this->record->id)
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
            Actions\DeleteAction::make(),
        ];
    }
}
