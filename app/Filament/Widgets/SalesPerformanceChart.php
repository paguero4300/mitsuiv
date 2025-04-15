<?php

namespace App\Filament\Widgets;

use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Illuminate\Support\Facades\DB;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\MaxWidth;

class SalesPerformanceChart extends ApexChartWidget
{
    use HasWidgetShield;
    protected static ?string $chartId = 'performanceDeVentasChart';
    protected static ?string $heading = 'Performance de Ventas ($)';
    
    protected static ?int $sort = 2;
    
    // Definir el ancho del formulario de filtro
    protected static MaxWidth|string $filterFormWidth = MaxWidth::Medium;

    protected string|array|int $columnSpan = 4;

    protected function getFormSchema(): array
    {
        // Obtenemos los años disponibles
        $years = DB::table('auctions')
            ->selectRaw('YEAR(start_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        return [
            Select::make('year')
                ->label('Año')
                ->options(array_combine($years, $years))
                ->default(date('Y'))
                ->live()
                ->afterStateUpdated(function () {
                    $this->updateChartOptions();
                }),
        ];
    }

    protected function getOptions(): array
    {
        $selectedYear = $this->filterFormData['year'] ?? date('Y');

        $monthlyData = DB::table('auctions')
            ->selectRaw('
                MONTH(start_date) as month,
                SUM(CASE WHEN status_id IN (4, 5, 6) THEN 1 ELSE 0 END) as subastas_finalizadas,
                SUM(CASE WHEN status_id = 6 THEN current_price ELSE 0 END) as venta_total
            ')
            ->whereYear('start_date', $selectedYear)
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    
        $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $finalizadas = [];
        $ventas = [];
    
        foreach ($months as $index => $month) {
            $data = $monthlyData->firstWhere('month', $index + 1);
            $finalizadas[] = $data->subastas_finalizadas ?? 0;
            $ventas[] = $data->venta_total ?? 0;
        }
    
        return [
            'chart' => [
                'type' => 'line', // Mixed chart
                'height' => 300, // Cambiado a 300
            ],
            'series' => [
                [
                    'name' => 'Venta Total',
                    'data' => $ventas,
                    'type' => 'column',
                ],
                [
                    'name' => 'Subastas Finalizadas',
                    'data' => $finalizadas,
                    'type' => 'line',
                ],
            ],
            'stroke' => [
                'width' => [0, 4],
            ],
            'xaxis' => [
                'categories' => $months,
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
            'yaxis' => [
                [
                    'title' => [
                        'text' => 'Cantidad ($)',
                    ],
                ],
                [
                    'opposite' => true,
                    'title' => [
                        'text' => 'Subastas Finalizadas',
                    ],
                ],
            ],
            'legend' => [
                'labels' => [
                    'fontFamily' => 'inherit',
                ],
            ],
            'tooltip' => [
                'y' => [
                    'formatter' => '{!! $this->getTooltipFormatter() !!}',
                ],
            ],
        ];
    }

    protected function getTooltipFormatter(): string
    {
        return "function (val) {
            return '$' + new Intl.NumberFormat('en-US').format(val);
        }";
    }
}
