<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Auction;

class FailedAuctionsOverview extends BaseWidget
{
    protected static ?string $statsOverviewLayout = 'compact';
    protected function getStats(): array
    {
        return [
            Stat::make(
                'Sin Oferta', 
                Auction::where('status_id', 4)->count()
            )
        ];
    }
    
}
