<?php

namespace App\Filament\Resources\AuctionResource\Pages;

use App\Filament\Resources\AuctionResource;
use App\Models\Auction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;

class ViewAuction extends ViewRecord
{
    protected static string $resource = AuctionResource::class;

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
