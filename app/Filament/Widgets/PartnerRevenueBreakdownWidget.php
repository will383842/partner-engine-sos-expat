<?php

namespace App\Filament\Widgets;

use App\Models\PartnerInvoice;
use Filament\Widgets\ChartWidget;

/**
 * Pie chart: revenue contribution by top 8 partners (trailing 12 months).
 * Helps admin identify concentration risk.
 */
class PartnerRevenueBreakdownWidget extends ChartWidget
{
    protected static ?string $heading = 'Répartition du revenu par partenaire (12 mois)';
    protected static ?int $sort = 7;
    protected int|string|array $columnSpan = 'full';

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
            $labels = ['Aucune facture payée'];
            $data = [0];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenu (€)',
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
