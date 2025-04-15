<?php

namespace App\Filament\Resources\CatalogTypeResource\Pages;

use App\Filament\Resources\CatalogTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCatalogTypes extends ListRecords
{
    protected static string $resource = CatalogTypeResource::class;
    protected function canCreate(): bool
    {
        return false; // Desactiva la opción de crear
    }
}
