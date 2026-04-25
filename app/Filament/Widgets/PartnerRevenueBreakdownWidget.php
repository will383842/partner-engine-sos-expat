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

        // Single batch lookup instead of N queries inside the loop.
        $partnerNames = \App\Models\Agreement::whereIn('partner_firebase_id', $rows->pluck('pid'))
            ->pluck('partner_name', 'partner_firebase_id');

        $labels = [];
        $data = [];

        foreach ($rows as $row) {
            $labels[] = $partnerNames[$row->pid] ?? $row->pid;
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
