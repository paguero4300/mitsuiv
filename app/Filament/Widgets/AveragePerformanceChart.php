<?php

namespace App\Filament\Widgets;
 
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\MaxWidth;

class AveragePerformanceChart extends ApexChartWidget
{
    protected static ?string $chartId = 'averagePerformanceChart';
    protected static ?string $heading = 'Performance de Venta Promedio ($)';
    
    protected static ?int $sort = 3;
    
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
                SUM(CASE WHEN status_id = 6 THEN current_price ELSE 0 END) as venta_total,
                COUNT(CASE WHEN status_id = 6 THEN 1 END) as subastas_adjudicadas
            ')
            ->whereYear('start_date', $selectedYear)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $promedios = array_fill(0, 12, 0);

        foreach ($monthlyData as $data) {
            $ventaTotal = $data->venta_total ?? 0;
            $subastasAdjudicadas = $data->subastas_adjudicadas ?? 1;
            $promedios[$data->month - 1] = $subastasAdjudicadas > 0 ? 
                round($ventaTotal / $subastasAdjudicadas, 2) : 0;
        }

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 300,
            ],
            'series' => [
                [
                    'name' => 'Venta Promedio',
                    'data' => $promedios,
                ],
            ],
            'xaxis' => [
                'categories' => ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                              'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                        'fontWeight' => 600,
                    ],
                ],
            ],
            'yaxis' => [
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
            'colors' => ['#ff7c43'],
            'plotOptions' => [
                'bar' => [
                    'borderRadius' => 3,
                    'horizontal' => false,
                ],
            ],
            'dataLabels' => [
                'enabled' => true,
                'offsetY' => -20,
                'style' => [
                    'fontSize' => '12px',
                    'colors' => ['#304758']
                ]
            ],
        ];
    }
}