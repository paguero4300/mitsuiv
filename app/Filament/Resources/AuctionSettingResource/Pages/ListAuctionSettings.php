<?php

namespace App\Filament\Resources\AuctionSettingResource\Pages;

use App\Filament\Resources\AuctionSettingResource;
use Filament\Resources\Pages\ListRecords;

class ListAuctionSettings extends ListRecords
{
    protected static string $resource = AuctionSettingResource::class;

    protected function getHeaderActions(): array
    {
        return []; // Quitamos la acción de crear
    }
}
