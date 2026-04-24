<?php

namespace App\Filament\Partner\Widgets;

use App\Models\Agreement;
use App\Models\PartnerInvoice;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Partner dashboard KPIs — 8 tiles across 2 rows, every tile scoped to
 * the logged-in partner.
 *
 * Row 1 (base KPIs + MoM delta):
 *   1. Active clients + delta vs last month
 *   2. Expert calls this month + delta
 *   3. Lawyer calls this month + delta
 *   4. Estimated invoice + delta
 *
 * Row 2 (quality / forward-looking):
 *   5. Average call duration (min)
 *   6. Usage rate % (calls / active clients)
 *   7. Top country of intervention
 *   8. Pending or overdue invoices count
 */
class StatsPartnerWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';
    protected static ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 4;
    }

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

        // Windows
        $now = now();
        $monthStart = (clone $now)->startOfMonth();
        $monthEnd   = (clone $now)->endOfMonth();
        $lastStart  = (clone $monthStart)->subMonthNoOverflow();
        $lastEnd    = (clone $lastStart)->endOfMonth();

        // --- 1. Active clients ---
        $activeSubs = Subscriber::where('partner_firebase_id', $partnerId)
            ->where('status', 'active')->count();
        $activeSubsLastMonth = Subscriber::where('partner_firebase_id', $partnerId)
            ->where('status', 'active')
            ->where('created_at', '<=', $lastEnd)
            ->count();
        $subsDelta = $activeSubs - $activeSubsLastMonth;

        // --- 2. Expert calls this month vs last month ---
        $expertCalls = SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')->where('provider_type', 'expat')
            ->whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $expertLast = SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')->where('provider_type', 'expat')
            ->whereBetween('created_at', [$lastStart, $lastEnd])->count();
        $expertDelta = $expertCalls - $expertLast;

        // --- 3. Lawyer calls ---
        $lawyerCalls = SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')->where('provider_type', 'lawyer')
            ->whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $lawyerLast = SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')->where('provider_type', 'lawyer')
            ->whereBetween('created_at', [$lastStart, $lastEnd])->count();
        $lawyerDelta = $lawyerCalls - $lawyerLast;

        // --- 4. Estimated invoice ---
        $estimatedInvoice = $activeSubs * $billingRate;
        $estimatedLast = $activeSubsLastMonth * $billingRate;
        $invoiceDelta = $estimatedInvoice - $estimatedLast;

        // --- 5. Average call duration this month ---
        $totalCallsThisMonth = $expertCalls + $lawyerCalls;
        $totalSecondsThisMonth = (int) SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('call_duration_seconds');
        $avgMinutes = $totalCallsThisMonth > 0
            ? round(($totalSecondsThisMonth / $totalCallsThisMonth) / 60, 1)
            : 0;

        // --- 6. Usage rate % ---
        $usageRate = $activeSubs > 0
            ? round(($totalCallsThisMonth / $activeSubs) * 100, 1)
            : 0;

        // --- 7. Top country this month ---
        $topCountry = SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->whereNotNull('metadata')
            ->get()
            ->map(fn($r) => $r->metadata['country'] ?? null)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first() ?? '—';

        // --- 8. Pending / overdue invoices ---
        $pendingInvoices = PartnerInvoice::where('partner_firebase_id', $partnerId)
            ->whereIn('status', ['pending', 'overdue'])
            ->count();
        $overdueInvoices = PartnerInvoice::where('partner_firebase_id', $partnerId)
            ->where('status', 'overdue')
            ->count();

        return [
            // Row 1 — activity & billing
            Stat::make('Clients actifs', $activeSubs)
                ->description($this->formatDelta($subsDelta, 'mois-1'))
                ->descriptionIcon($subsDelta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($subsDelta >= 0 ? 'success' : 'warning'),

            Stat::make('Appels Expert (mois)', $expertCalls)
                ->description($this->formatDelta($expertDelta, 'mois-1'))
                ->descriptionIcon($expertDelta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color('info'),

            Stat::make('Appels Avocat (mois)', $lawyerCalls)
                ->description($this->formatDelta($lawyerDelta, 'mois-1'))
                ->descriptionIcon($lawyerDelta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make(
                'Facture estimée',
                $currencySymbol . ' ' . number_format($estimatedInvoice, 2, ',', ' ')
            )
                ->description($activeSubs . ' × ' . number_format($billingRate, 2, ',', ' ') . $currencySymbol
                    . ' · ' . $this->formatDelta($invoiceDelta, 'mois-1', $currencySymbol))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            // Row 2 — quality / forward-looking
            Stat::make('Durée moyenne appel', $avgMinutes > 0 ? $avgMinutes . ' min' : '—')
                ->description('Sur ' . $totalCallsThisMonth . ' appel' . ($totalCallsThisMonth > 1 ? 's' : '') . ' ce mois')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),

            Stat::make('Taux d\'usage', $usageRate . '%')
                ->description('Appels / clients actifs ce mois')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($usageRate > 10 ? 'success' : 'gray'),

            Stat::make('Top pays d\'intervention', $topCountry)
                ->description('Pays le plus sollicité ce mois')
                ->descriptionIcon('heroicon-m-globe-europe-africa')
                ->color('primary'),

            Stat::make('Factures à traiter', $pendingInvoices)
                ->description($overdueInvoices > 0
                    ? $overdueInvoices . ' en retard — à régler vite'
                    : ($pendingInvoices > 0 ? 'En attente de paiement' : 'Tout est à jour'))
                ->descriptionIcon($overdueInvoices > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overdueInvoices > 0 ? 'danger' : ($pendingInvoices > 0 ? 'warning' : 'success')),
        ];
    }

    protected function formatDelta(int|float $delta, string $label, string $currencySymbol = ''): string
    {
        if ($delta === 0 || $delta === 0.0) {
            return '= ' . $label;
        }
        $sign = $delta > 0 ? '+' : '';
        $value = $currencySymbol
            ? $sign . $currencySymbol . ' ' . number_format(abs($delta), 2, ',', ' ')
            : $sign . (int) $delta;
        return $value . ' vs ' . $label;
    }
}
