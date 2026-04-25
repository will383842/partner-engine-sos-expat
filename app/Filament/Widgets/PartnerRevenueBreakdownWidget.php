<?php

namespace App\Filament\Widgets;

use App\Models\PartnerInvoice;
use Filament\Widgets\ChartWidget;

class PartnerRevenueBreakdownWidget extends ChartWidget
{
    protected static ?int $sort = 7;
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('admin.widget.breakdown.heading');
    }

    protected function getData(): array
    {
        $since = now()->subMonths(12);

        $rows = PartnerInvoice::query()
            ->selectRaw("COALESCE(NULLIF(partner_firebase_id, ''), 'unknown') as pid, SUM(total_amount) as total")
            ->where('status', 'paid')
            ->where('paid_at', '>=', $since)
            ->groupBy('pid')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $labels = [];
        $data = [];

        foreach ($rows as $row) {
            $name = \App\Models\Agreement::where('partner_firebase_id', $row->pid)->value('partner_name');
            $labels[] = $name ?: $row->pid;
            $data[] = (float) $row->total;
        }

        if (empty($data)) {
            $labels = [__('admin.widget.breakdown.empty')];
            $data = [0];
        }

        return [
            'datasets' => [
                [
                    'label' => __('admin.widget.breakdown.series_revenue'),
                    'data' => $data,
                    'backgroundColor' => [
                        '#3b82f6', '#10b981', '#f59e0b', '#ef4444',
                        '#8b5cf6', '#ec4899', '#14b8a6', '#f97316',
                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
