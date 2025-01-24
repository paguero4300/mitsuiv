<?php

namespace App\Filament\Widgets;

use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Illuminate\Support\Facades\DB;

class AuctionsPerformanceChart extends ApexChartWidget
{
    /**
     * Chart Id
     *
     * @var string
     */
    protected static ?string $chartId = 'auctionsPerformanceChart';

    /**
     * Widget Title
     *
     * @var string|null
     */
    protected static ?string $heading = 'Performance de Subastas';

    /**
     * Chart options (series, labels, types, size, animations...)
     * https://apexcharts.com/docs/options
     *
     * @return array
     */
    protected function getOptions(): array
    {
        // Obtener datos de subastas agrupados por mes
        $monthlyData = DB::table('auctions')
            ->selectRaw('
                MONTH(start_date) as month,
                COUNT(id) as total_subastas,
                SUM(CASE WHEN status_id = (SELECT id FROM auction_statuses WHERE slug = "adjudicada") THEN 1 ELSE 0 END) as adjudicadas
            ')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Datos por cada mes
        $categories = [];
        $subastasFinalizadas = [];
        $porcentajesCierre = [];

        foreach ($monthlyData as $data) {
            $categories[] = $this->getMonthName($data->month); // Convertir número de mes en nombre
            $subastasFinalizadas[] = $data->total_subastas;
            $porcentajesCierre[] = $data->total_subastas > 0
                ? round(($data->adjudicadas / $data->total_subastas) * 100, 2)
                : 0; // Calcular porcentaje
        }

        return [
            'chart' => [
                'type' => 'line',
                'height' => 300,
            ],
            'series' => [
                [
                    'name' => 'Subastas Finalizadas',
                    'data' => $subastasFinalizadas,
                    'type' => 'column',
                ],
                [
                    'name' => 'Porcentaje de Cierre',
                    'data' => $porcentajesCierre,
                    'type' => 'line',
                ],
            ],
            'xaxis' => [
                'categories' => $categories,
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
            'yaxis' => [
                [
                    'title' => ['text' => 'Subastas Finalizadas'],
                ],
                [
                    'opposite' => true,
                    'title' => ['text' => 'Porcentaje de Cierre'],
                ],
            ],
            'stroke' => [
                'width' => [0, 4], // Grosor de las líneas
            ],
            'legend' => [
                'labels' => [
                    'fontFamily' => 'inherit',
                ],
            ],
        ];
    }

    /**
     * Convertir número de mes a nombre.
     *
     * @param int $month
     * @return string
     */
    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        return $months[$month] ?? 'Desconocido';
    }
}
