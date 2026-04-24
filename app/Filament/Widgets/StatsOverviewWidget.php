<?php

namespace App\Filament\Widgets;

use App\Models\Agreement;
use App\Models\PartnerInvoice;
use App\Models\Subscriber;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $activePartners = Agreement::where('status', 'active')->count();
        $sosCallPartners = Agreement::where('sos_call_active', true)->where('status', 'active')->count();
        $activeSubscribers = Subscriber::where('status', 'active')->count();
        $pendingInvoicesAmount = PartnerInvoice::whereIn('status', ['pending', 'overdue'])->sum('total_amount');
        $overdueCount = PartnerInvoice::where('status', 'overdue')->count();

        // Revenue month-to-date (paid invoices this month)
        $revenueMTD = PartnerInvoice::where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('total_amount');

        // Revenue last month (for comparison)
        $revenueLM = PartnerInvoice::where('status', 'paid')
            ->whereMonth('paid_at', now()->subMonth()->month)
            ->whereYear('paid_at', now()->subMonth()->year)
            ->sum('total_amount');

        $revenueDelta = $revenueLM > 0 ? round((($revenueMTD - $revenueLM) / $revenueLM) * 100, 1) : null;
        $deltaLabel = $revenueDelta === null
            ? 'Premier mois'
            : ($revenueDelta >= 0 ? "+{$revenueDelta}% vs mois dernier" : "{$revenueDelta}% vs mois dernier");
        $deltaIcon = $revenueDelta === null
            ? 'heroicon-m-minus'
            : ($revenueDelta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down');

        // Call volume this month (SOS-Call free calls)
        $callsThisMonth = \App\Models\SubscriberActivity::where('type', 'call_completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Average invoice amount
        $avgInvoice = PartnerInvoice::where('status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->avg('total_amount');

        return [
            Stat::make('Partenaires actifs', $activePartners)
                ->description("{$sosCallPartners} en mode SOS-Call (forfait)")
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('success'),

            Stat::make('Clients actifs', number_format($activeSubscribers))
                ->description('Tous partenaires confondus')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Revenu du mois', '€' . number_format($revenueMTD, 2, ',', ' '))
                ->description($deltaLabel)
                ->descriptionIcon($deltaIcon)
                ->color($revenueDelta === null || $revenueDelta >= 0 ? 'success' : 'danger'),

            Stat::make('Factures impayées', '€' . number_format($pendingInvoicesAmount, 2, ',', ' '))
                ->description($overdueCount . ' en retard')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($overdueCount > 0 ? 'danger' : 'warning'),

            Stat::make('Appels ce mois', number_format($callsThisMonth))
                ->description('Tous SOS-Call confondus')
                ->descriptionIcon('heroicon-m-phone')
                ->color('primary'),

            Stat::make('Facture moyenne', '€' . number_format($avgInvoice ?? 0, 2, ',', ' '))
                ->description('Ce mois-ci')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('gray'),
        ];
    }
}
