<?php

namespace App\Filament\Resources\ResellerAuctionResource\Pages;

use App\Filament\Resources\ResellerAuctionResource;
use App\Models\AuctionStatus;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

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
                ->query(function (Builder $query): Builder {
                    $baseQuery = $query->getModel()->newQuery();
                    
                    $baseQuery->where('status_id', 2) // SIN_OFERTA
                        ->where('start_date', '<=', now())
                        ->where('end_date', '>', now());
                    
                    return $baseQuery;
                }),
            
            'ofertadas' => Tab::make('Ofertadas')
                ->query(function (Builder $query): Builder {
                    $baseQuery = $query->getModel()->newQuery();
                    
                    $baseQuery->where('status_id', 3) // EN_PROCESO
                        ->where('start_date', '<=', now())
                        ->where('end_date', '>', now())
                        ->whereHas('bids', function (Builder $query) {
                            $query->where('reseller_id', Auth::id());
                        });
                    
                    return $baseQuery;
                }),
            
            'finalizadas' => Tab::make('Finalizadas')
                ->query(function (Builder $query): Builder {
                    $baseQuery = $query->getModel()->newQuery();
                    $baseQuery->whereIn('status_id', [4, 5]) // 4=Fallida, 5=Ganada
                        ->whereHas('bids', function (Builder $query) {
                            $query->where('reseller_id', Auth::id());
                        });
                    
                    return $baseQuery;
                }),
            
            'adjudicadas' => Tab::make('Adjudicadas')
                ->query(function (Builder $query): Builder {
                    $baseQuery = $query->getModel()->newQuery();
                    $baseQuery->where('status_id', 6) // ADJUDICADA
                        ->whereHas('bids', function (Builder $query) {
                            $query->where('reseller_id', Auth::id())
                                ->whereRaw('bids.amount = (SELECT MAX(amount) FROM bids WHERE auction_id = auctions.id)');
                        });
                    
                    return $baseQuery;
                }),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
