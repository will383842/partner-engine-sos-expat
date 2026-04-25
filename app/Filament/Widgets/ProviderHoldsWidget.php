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
        $pendingInvoices = PartnerInvoice::whereIn('status', ['pending', 'overdue'])->get();
        $totalOwedByPartners = $pendingInvoices->sum('total_amount');
        $totalCallsInUnpaid = $pendingInvoices->sum(fn($i) => $i->calls_expert + $i->calls_lawyer);
        $totalCostAtRisk = $pendingInvoices->sum('total_cost');

        return [
            Stat::make(__('admin.widget.holds.unpaid_invoices'), '€' . number_format($totalOwedByPartners, 2, ',', ' '))
                ->description(__('admin.widget.holds.unpaid_invoices_desc'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($totalOwedByPartners > 0 ? 'warning' : 'gray'),

            Stat::make(__('admin.widget.holds.calls_on_hold'), number_format($totalCallsInUnpaid))
                ->description(__('admin.widget.holds.calls_on_hold_desc'))
                ->descriptionIcon('heroicon-m-pause-circle')
                ->color('info'),

            Stat::make(__('admin.widget.holds.cost_to_release'), '€' . number_format($totalCostAtRisk, 2, ',', ' '))
                ->description(__('admin.widget.holds.cost_to_release_desc'))
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('primary'),
        ];
    }
}
