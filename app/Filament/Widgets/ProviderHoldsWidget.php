<?php

namespace App\Filament\Widgets;

use App\Models\PartnerInvoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Shows the total amount of provider payments currently ON HOLD because the
 * partner hasn't paid their monthly invoice yet.
 *
 * NOTE: the actual per-call provider holds live in Firestore (call_sessions),
 * we can only approximate here from unpaid invoices × average provider amount.
 * For precise figures, we'd need a sync job or a Firestore admin read.
 */
class ProviderHoldsWidget extends BaseWidget
{
    protected static ?int $sort = 8;

    protected function getStats(): array
    {
        // Unpaid invoices that will eventually release provider payments
        $pendingInvoices = PartnerInvoice::whereIn('status', ['pending', 'overdue'])->get();
        $totalOwedByPartners = $pendingInvoices->sum('total_amount');
        $totalCallsInUnpaid = $pendingInvoices->sum(fn($i) => $i->calls_expert + $i->calls_lawyer);
        $totalCostAtRisk = $pendingInvoices->sum('total_cost');

        return [
            Stat::make('Factures impayées (€)', '€' . number_format($totalOwedByPartners, 2, ',', ' '))
                ->description('Montant dû par partenaires')
                ->descriptionIcon('heroicon-m-clock')
                ->color($totalOwedByPartners > 0 ? 'warning' : 'gray'),

            Stat::make('Appels en hold', number_format($totalCallsInUnpaid))
                ->description('Providers en attente de paiement partenaire')
                ->descriptionIcon('heroicon-m-pause-circle')
                ->color('info'),

            Stat::make('Coût provider à débloquer', '€' . number_format($totalCostAtRisk, 2, ',', ' '))
                ->description('Montant provider libéré si factures payées')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('primary'),
        ];
    }
}
