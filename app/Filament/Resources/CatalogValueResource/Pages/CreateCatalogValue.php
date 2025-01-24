<?php

namespace App\Filament\Resources\CatalogValueResource\Pages;

use App\Filament\Resources\CatalogValueResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCatalogValue extends CreateRecord
{
    protected static string $resource = CatalogValueResource::class;

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
