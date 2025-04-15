<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

use App\Models\Auction;

class FinishedAuctionsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make(
                'Subastas Finalizadas',
                Auction::whereIn('status_id', [4, 5, 6])->count()
            )
        ];
    }
}
