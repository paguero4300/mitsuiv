<?php

namespace App\Filament\Resources\ResellerAuctionResource\Pages;

use App\Filament\Resources\ResellerAuctionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditResellerAuction extends EditRecord
{
    protected static string $resource = ResellerAuctionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
