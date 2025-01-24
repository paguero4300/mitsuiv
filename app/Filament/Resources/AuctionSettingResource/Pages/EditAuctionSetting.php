<?php
// app/Filament/Resources/AuctionSettingResource/Pages/EditAuctionSetting.php

namespace App\Filament\Resources\AuctionSettingResource\Pages;

use App\Filament\Resources\AuctionSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAuctionSetting extends EditRecord
{
    protected static string $resource = AuctionSettingResource::class;



    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
