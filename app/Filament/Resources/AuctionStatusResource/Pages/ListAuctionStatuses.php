<?php

namespace App\Filament\Resources\AuctionStatusResource\Pages;

use App\Filament\Resources\AuctionStatusResource;
use Filament\Resources\Pages\ListRecords;

class ListAuctionStatuses extends ListRecords
{
    protected static string $resource = AuctionStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
