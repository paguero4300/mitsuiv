<?php

namespace App\Filament\Widgets;

use App\Models\Auction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TotalAuctionsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Total de subastas (activas + finalizadas)
        $totalAuctions = Auction::whereIn('status_id', [2,3,4,5,6])->count();
        
        // Subtotal de subastas activas
        $activeAuctions = Auction::whereIn('status_id', [2,3])->count();
        
        // Subtotal de subastas finalizadas
        $finishedAuctions = Auction::whereIn('status_id', [4,5,6])->count();

        return [
            Stat::make('Total Subastas', $totalAuctions)
                ->description("Activas: $activeAuctions | Finalizadas: $finishedAuctions")
                ->icon('heroicon-m-shopping-bag')
                ->color('success'),
        ];
    }
}