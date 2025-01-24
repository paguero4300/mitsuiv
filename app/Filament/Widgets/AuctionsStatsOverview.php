<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Auction;
use Illuminate\Support\Facades\DB;

class AuctionsStatsOverview extends BaseWidget
{
    protected ?string $heading = 'Resumen de Subastas';
    protected ?string $description = 'Vista general de estadísticas clave.';

    protected function getStats(): array
    {
        // Total de subastas
        $totalAuctions = Auction::whereIn('status_id', [2, 3, 4, 5, 6])->count();
        $activeAuctions = Auction::whereIn('status_id', [2, 3])->count();
        $finishedAuctions = Auction::whereIn('status_id', [4, 5, 6])->count();

        // Subastas adjudicadas
        $adjudicatedAuctions = Auction::where('status_id', 6)->count();
        $percentage = $finishedAuctions > 0
            ? round(($adjudicatedAuctions / $finishedAuctions) * 100, 2)
            : 0;

        // Subastas sin oferta
        $failedAuctions = Auction::where('status_id', 4)->count();

        // Ventas totales y promedio
        $totalSales = DB::table('auctions')
            ->where('status_id', 6)
            ->sum('current_price');

        $averageSales = $adjudicatedAuctions > 0
            ? round($totalSales / $adjudicatedAuctions, 2)
            : 0;

        return [
            Stat::make('Total Subastas', $totalAuctions)
                ->description("Activas: $activeAuctions | Finalizadas: $finishedAuctions")
                ->icon('heroicon-m-shopping-bag')
                ->color('success'),

            Stat::make('Subastas Finalizadas', $finishedAuctions),

            Stat::make('Sin Oferta', $failedAuctions)
                ->color('danger'),

            Stat::make('Porcentaje de Cierre', $percentage . '%')
                ->description('Porcentaje de subastas finalizadas adjudicadas.')
                ->color('info'),

            Stat::make('Venta Total', number_format($totalSales, 2) . ' USD')
                ->description('Suma de los precios finales de subastas adjudicadas.')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Venta Promedio', number_format($averageSales, 2) . ' USD')
                ->description('Venta total dividida entre subastas adjudicadas.')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),
        ];
    }
}
