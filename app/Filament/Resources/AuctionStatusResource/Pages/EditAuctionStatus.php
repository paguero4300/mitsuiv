<?php

namespace App\Filament\Resources\AuctionStatusResource\Pages;

use App\Filament\Resources\AuctionStatusResource;
use Filament\Resources\Pages\EditRecord;

class EditAuctionStatus extends EditRecord
{
    protected static string $resource = AuctionStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
