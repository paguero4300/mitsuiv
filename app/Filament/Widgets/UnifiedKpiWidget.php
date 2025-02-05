<?php

namespace App\Filament\Widgets;

use App\Models\Auction;
use App\Models\AuctionAdjudication;
use App\Models\Vehicle;
use App\Models\User;
use App\Models\CatalogValue;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class UnifiedKpiWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;
    protected static bool $isLazy = false;
    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $brandKpis = $this->getKpisByBrand();
        $modelKpis = $this->getKpisByModel();
        $resellerKpis = $this->getKpisByReseller();

        return [
            $this->createKpiCard('Marcas', $brandKpis, 'success'),
            $this->createKpiCard('Modelos', $modelKpis, 'warning'),
            $this->createKpiCard('Revendedores', $resellerKpis, 'danger'),
        ];
    }

    protected function createKpiCard($title, $data, $color): Stat
    {
        $tableHtml = '
        <div class="text-xs">
            <style>
                .excel-table {
                    border-collapse: collapse;
                    width: 100%;
                }
                .excel-table th, .excel-table td {
                    border: 1px solid #e5e7eb;
                    padding: 4px 8px;
                }
                .excel-table th {
                    background-color: #f3f4f6;
                    font-weight: 600;
                }
                .excel-table tbody tr:nth-child(even) {
                    background-color: #f9fafb;
                }
                .excel-table tbody tr:hover {
                    background-color: #f3f4f6;
                }
            </style>
            <table class="excel-table">
                <thead>
                    <tr>
                        <th class="text-left">Nombre</th>
                        <th class="text-center">Fin.</th>
                        <th class="text-center">Adj.</th>
                        <th class="text-center">% Cierre</th>
                        <th class="text-right">Prom.</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($data as $row) {
            $tableHtml .= "
                <tr>
                    <td class='text-left'>{$row['name']}</td>
                    <td class='text-center'>{$row['finalized']}</td>
                    <td class='text-center'>{$row['adjudicated']}</td>
                    <td class='text-center'>{$row['closure_rate']}%</td>
                    <td class='text-right'>$" . number_format($row['average_sale'], 0) . "</td>
                    <td class='text-right'>$" . number_format($row['total_sales'], 0) . "</td>
                </tr>";
        }

        $tableHtml .= '</tbody></table></div>';

        return Stat::make($title, new HtmlString($tableHtml))
            ->description("Top 5 {$title} con mejor desempeño")
            ->descriptionIcon('heroicon-m-arrow-trending-up')
            ->chart($this->getChartData($data))
            ->color($color);
    }

    protected function getKpisByBrand()
    {
        return DB::table('auctions')
            ->join('vehicles', 'auctions.vehicle_id', '=', 'vehicles.id')
            ->join('catalog_values as brands', 'vehicles.brand_id', '=', 'brands.id')
            ->select(
                'brands.value as name',
                DB::raw('COUNT(*) as total_auctions'),
                DB::raw('COUNT(CASE WHEN auctions.status_id = 6 THEN 1 END) as adjudicated_auctions'),
                DB::raw('SUM(CASE WHEN auctions.status_id = 6 THEN auctions.current_price ELSE 0 END) as total_sales')
            )
            ->groupBy('brands.value')
            ->orderBy('total_auctions', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($item) => $this->formatKpiData($item));
    }

    protected function getKpisByModel()
    {
        return DB::table('auctions')
            ->join('vehicles', 'auctions.vehicle_id', '=', 'vehicles.id')
            ->join('catalog_values as models', 'vehicles.model_id', '=', 'models.id')
            ->select(
                'models.value as name',
                DB::raw('COUNT(*) as total_auctions'),
                DB::raw('COUNT(CASE WHEN auctions.status_id = 6 THEN 1 END) as adjudicated_auctions'),
                DB::raw('SUM(CASE WHEN auctions.status_id = 6 THEN auctions.current_price ELSE 0 END) as total_sales')
            )
            ->groupBy('models.value')
            ->orderBy('total_auctions', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($item) => $this->formatKpiData($item));
    }

    protected function getKpisByReseller()
    {
        return DB::table('auctions')
            ->join('auction_adjudications', 'auctions.id', '=', 'auction_adjudications.auction_id')
            ->join('users', 'auction_adjudications.reseller_id', '=', 'users.id')
            ->select(
                'users.name',
                DB::raw('COUNT(DISTINCT auctions.id) as total_auctions'),
                DB::raw('COUNT(CASE WHEN auctions.status_id = 6 THEN 1 END) as adjudicated_auctions'),
                DB::raw('SUM(CASE WHEN auctions.status_id = 6 THEN auctions.current_price ELSE 0 END) as total_sales')
            )
            ->groupBy('users.name')
            ->orderBy('total_auctions', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($item) => $this->formatKpiData($item));
    }

    protected function formatKpiData($item)
    {
        return [
            'name' => $item->name,
            'finalized' => $item->total_auctions,
            'adjudicated' => $item->adjudicated_auctions,
            'closure_rate' => $item->total_auctions > 0 
                ? round(($item->adjudicated_auctions / $item->total_auctions) * 100, 0) 
                : 0,
            'average_sale' => $item->adjudicated_auctions > 0 
                ? round($item->total_sales / $item->adjudicated_auctions, 0) 
                : 0,
            'total_sales' => $item->total_sales
        ];
    }

    protected function getChartData($data)
    {
        return collect($data)->pluck('closure_rate')->toArray();
    }
}