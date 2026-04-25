<?php

namespace App\Filament\Partner\Widgets;

use App\Models\PartnerInvoice;
use Filament\Widgets\ChartWidget;

/**
 * Doughnut chart of the partner's invoice status distribution.
 * Quick "am I up to date?" check.
 */
class InvoiceStatusWidget extends ChartWidget
{
    protected static ?int $sort = 5;
    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    public function getHeading(): ?string
    {
        return __('panel.widget.invoice_status.heading');
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $partnerId = $user?->partner_firebase_id;
        if (!$partnerId) {
            return ['datasets' => [['data' => []]], 'labels' => []];
        }

        $counts = PartnerInvoice::where('partner_firebase_id', $partnerId)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        $labels = [
            'paid' => __('panel.common.paid'),
            'pending' => __('panel.common.pending'),
            'overdue' => __('panel.common.overdue'),
            'cancelled' => __('panel.common.cancelled'),
        ];
        $colors = [
            'paid' => 'rgb(16, 185, 129)',
            'pending' => 'rgb(245, 158, 11)',
            'overdue' => 'rgb(220, 38, 38)',
            'cancelled' => 'rgb(148, 163, 184)',
        ];

        $orderedLabels = [];
        $orderedValues = [];
        $orderedColors = [];
        foreach ($labels as $k => $l) {
            if (isset($counts[$k]) && $counts[$k] > 0) {
                $orderedLabels[] = $l;
                $orderedValues[] = $counts[$k];
                $orderedColors[] = $colors[$k];
            }
        }

        return [
            'datasets' => [[
                'data' => $orderedValues,
                'backgroundColor' => $orderedColors,
                'borderWidth' => 0,
            ]],
            'labels' => $orderedLabels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }
}
