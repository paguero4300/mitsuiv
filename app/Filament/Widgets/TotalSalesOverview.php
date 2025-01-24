<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TotalSalesOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Calcular la suma de los precios finales de subastas adjudicadas
        $totalSales = DB::table('auctions')
            ->where('status_id', 6) // Filtrar estado "adjudicada"
            ->sum('current_price');

        return [
            Stat::make('Venta Total', number_format($totalSales, 2) . ' USD')
                ->description('Suma de los precios finales de venta de las subastas adjudicadas.')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
        ];
    }
}
