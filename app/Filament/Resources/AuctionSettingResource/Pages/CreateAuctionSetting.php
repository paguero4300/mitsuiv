<?php
// app/Filament/Resources/AuctionSettingResource/Pages/CreateAuctionSetting.php

namespace App\Filament\Resources\AuctionSettingResource\Pages;

use App\Filament\Resources\AuctionSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAuctionSetting extends CreateRecord
{
    protected static string $resource = AuctionSettingResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
