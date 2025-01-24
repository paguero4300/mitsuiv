<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Auction;

class ClosingPercentageOverview extends BaseWidget
{
    protected static ?string $statsOverviewLayout = 'compact';
    
    protected function getStats(): array
    {
        $finishedAuctions = Auction::whereIn('status_id', [4, 5, 6])->count();
        $adjudicatedAuctions = Auction::where('status_id', 6)->count();
        
        $percentage = $finishedAuctions > 0 
            ? round(($adjudicatedAuctions / $finishedAuctions) * 100, 2) 
            : 0;

        return [
            Stat::make(
                'Porcentaje de Cierre',
                $percentage . '%'
            )
        ];
    }
}