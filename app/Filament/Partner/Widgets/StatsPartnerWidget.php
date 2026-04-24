<?php

namespace App\Filament\Partner\Widgets;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPI row on the partner dashboard — always scoped to the logged-in partner.
 * Shows 4 numbers: active clients, expert calls, lawyer calls, estimated invoice.
 */
class StatsPartnerWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $user = auth()->user();
        $partnerId = $user?->partner_firebase_id;
        if (!$partnerId) {
            return [];
        }

        $agreement = Agreement::where('partner_firebase_id', $partnerId)->first();
        $billingRate = (float) ($agreement?->billing_rate ?? 0);
        $currencySymbol = ($agreement?->billing_currency === 'USD') ? '$' : '€';

        $activeSubs = Subscriber::where('partner_firebase_id', $partnerId)
            ->where('status', 'active')
            ->count();

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $expertCalls = SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')
            ->where('provider_type', 'expat')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        $lawyerCalls = SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')
            ->where('provider_type', 'lawyer')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        $estimatedInvoice = $activeSubs * $billingRate;

        return [
            Stat::make('Clients actifs', $activeSubs)
                ->description('Base couverte ce mois-ci')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Appels Expert', $expertCalls)
                ->description('Consultations expat ce mois')
                ->descriptionIcon('heroicon-m-user-circle')
                ->color('info'),

            Stat::make('Appels Avocat', $lawyerCalls)
                ->description('Consultations juridiques ce mois')
                ->descriptionIcon('heroicon-m-scale')
                ->color('danger'),

            Stat::make(
                'Facture estimée',
                $currencySymbol . ' ' . number_format($estimatedInvoice, 2, ',', ' ')
            )
                ->description($activeSubs . ' clients × ' . number_format($billingRate, 2, ',', ' ') . $currencySymbol)
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}
