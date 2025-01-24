<?php

namespace App\Filament\Resources\ResellerAuctionResource\Pages;

use App\Filament\Resources\ResellerAuctionResource;
use App\Models\AuctionStatus;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListResellerAuctions extends ListRecords
{
    protected static string $resource = ResellerAuctionResource::class;

    public function getDefaultActiveTab(): string 
    {
        return 'sin_ofertas';
    }

    public function getTabs(): array
    {
        return [
            'sin_ofertas' => Tab::make('Sin ofertas')
                ->query(fn (Builder $query) => $query->where('status_id', AuctionStatus::SIN_OFERTA)),
            'ofertadas' => Tab::make('Ofertadas')
                ->query(fn (Builder $query) => $query->where('status_id', AuctionStatus::EN_PROCESO)),
            'finalizadas' => Tab::make('Finalizadas')
                ->query(fn (Builder $query) => $query->whereIn('status_id', [
                    AuctionStatus::FALLIDA,
                    AuctionStatus::GANADA
                ])),
            'adjudicadas' => Tab::make('Adjudicadas')
                ->query(fn (Builder $query) => $query->where('status_id', AuctionStatus::ADJUDICADA)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
