<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AverageSalesOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Calcular la Venta Total de subastas adjudicadas
        $totalSales = DB::table('auctions')
            ->where('status_id', 6) // Estado "adjudicada"
            ->sum('current_price');

        // Contar el número de subastas adjudicadas
        $adjudicatedCount = DB::table('auctions')
            ->where('status_id', 6) // Estado "adjudicada"
            ->count();

        // Calcular el promedio (evitar división por cero)
        $averageSales = $adjudicatedCount > 0 ? $totalSales / $adjudicatedCount : 0;

        return [
            Stat::make('Venta Promedio', number_format($averageSales, 2) . ' USD')
                ->description('Venta total dividida entre subastas adjudicadas.')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info'),
        ];
    }
}
