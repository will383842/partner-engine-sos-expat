<?php

namespace App\Filament\Widgets;

use App\Models\PartnerInvoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProviderHoldsWidget extends BaseWidget
{
    protected static ?int $sort = 8;

    protected function getStats(): array
    {
        // Currency-aware aggregations: split EUR vs USD so we never sum
        // €100 + $100 into "€200" in a KPI tile.
        $rows = PartnerInvoice::query()
            ->whereIn('status', ['pending', 'overdue'])
            ->selectRaw('UPPER(billing_currency) as cur, SUM(total_amount) as owed, SUM(total_cost) as cost')
            ->groupBy('cur')
            ->get()
            ->keyBy('cur');

        $totalOwedEur = (float) ($rows['EUR']->owed ?? 0);
        $totalOwedUsd = (float) ($rows['USD']->owed ?? 0);
        $totalCostEur = (float) ($rows['EUR']->cost ?? 0);
        $totalCostUsd = (float) ($rows['USD']->cost ?? 0);

        $totalCallsInUnpaid = PartnerInvoice::whereIn('status', ['pending', 'overdue'])
            ->selectRaw('SUM(calls_expert + calls_lawyer) as total_calls')
            ->value('total_calls') ?? 0;

        return [
            Stat::make(
                __('admin.widget.holds.unpaid_invoices_eur'),
                '€' . number_format($totalOwedEur, 2, ',', ' ')
            )
                ->description(__('admin.widget.holds.unpaid_invoices_desc'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($totalOwedEur > 0 ? 'warning' : 'gray'),

            Stat::make(
                __('admin.widget.holds.unpaid_invoices_usd'),
                '$' . number_format($totalOwedUsd, 2, ',', ' ')
            )
                ->description(__('admin.widget.holds.unpaid_invoices_desc'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($totalOwedUsd > 0 ? 'warning' : 'gray'),

            Stat::make(__('admin.widget.holds.calls_on_hold'), number_format($totalCallsInUnpaid))
                ->description(__('admin.widget.holds.calls_on_hold_desc'))
                ->descriptionIcon('heroicon-m-pause-circle')
                ->color('info'),

            Stat::make(
                __('admin.widget.holds.cost_to_release_eur'),
                '€' . number_format($totalCostEur, 2, ',', ' ')
            )
                ->description(__('admin.widget.holds.cost_to_release_desc'))
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('primary'),

            Stat::make(
                __('admin.widget.holds.cost_to_release_usd'),
                '$' . number_format($totalCostUsd, 2, ',', ' ')
            )
                ->description(__('admin.widget.holds.cost_to_release_desc'))
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('primary'),
        ];
    }
}
