<?php
namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Auction;

class ActiveAuctionsOverview extends BaseWidget
{
    protected static ?string $statsOverviewLayout = 'compact';
    
    protected function getStats(): array
    {
        return [
            Stat::make(
                'Subastas Activas',
                Auction::whereIn('status_id', [2, 3])->count()
            )
        ];
    }
}