<?php

namespace App\Filament\Resources\CatalogValueResource\Pages;

use App\Filament\Resources\CatalogValueResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCatalogValue extends EditRecord
{
    protected static string $resource = CatalogValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
