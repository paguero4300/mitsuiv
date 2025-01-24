<?php

namespace App\Filament\Resources\AuctionResource\Pages;

use App\Filament\Resources\AuctionResource;
use Filament\Actions;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use App\Models\AuctionStatus;


class ListAuctions extends ListRecords
{
   
    protected static string $resource = AuctionResource::class;

    public function getDefaultActiveTab(): string 
    {
        return 'open';
    }

    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Abiertas')
                ->query(fn (Builder $query) => $query->whereIn('status_id', [
                    AuctionStatus::SIN_OFERTA,
                    AuctionStatus::EN_PROCESO
                ])),
            'finished' => Tab::make('Finalizadas')
                ->query(fn (Builder $query) => $query->whereIn('status_id', [
                    AuctionStatus::FALLIDA,
                    AuctionStatus::GANADA
                ])),
            'adjudicated' => Tab::make('Adjudicadas')
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
