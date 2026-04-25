<?php

namespace App\Filament\Widgets;

use App\Models\PartnerInvoice;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    public function getHeading(): ?string
    {
        return __('admin.widget.revenue.heading');
    }

    protected function getData(): array
    {
        $labels = [];
        $paid = [];
        $pending = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $period = $month->format('Y-m');
            $labels[] = $month->locale(app()->getLocale())->isoFormat('MMM YY');

            $paid[] = (float) PartnerInvoice::where('period', $period)
                ->where('status', 'paid')
                ->sum('total_amount');

            $pending[] = (float) PartnerInvoice::where('period', $period)
                ->whereIn('status', ['pending', 'overdue'])
                ->sum('total_amount');
        }

        return [
            'datasets' => [
                [
                    'label' => __('admin.widget.revenue.series_paid'),
                    'data' => $paid,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.2)',
                ],
                [
                    'label' => __('admin.widget.revenue.series_pending'),
                    'data' => $pending,
                    'borderColor' => 'rgb(234, 179, 8)',
                    'backgroundColor' => 'rgba(234, 179, 8, 0.2)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
