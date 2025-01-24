<?php

namespace App\Filament\Resources\CatalogValueResource\Pages;

use App\Filament\Resources\CatalogValueResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCatalogValues extends ListRecords
{
    protected static string $resource = CatalogValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
