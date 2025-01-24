<?php

namespace App\Filament\Resources\CatalogTypeResource\Pages;

use App\Filament\Resources\CatalogTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCatalogType extends EditRecord
{
    protected static string $resource = CatalogTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
