<?php

namespace App\Filament\Partner\Widgets;

use App\Models\Agreement;
use App\Models\PartnerInvoice;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

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

        $now = now();
        $monthStart = (clone $now)->startOfMonth();
        $monthEnd   = (clone $now)->endOfMonth();
        $lastStart  = (clone $monthStart)->subMonthNoOverflow();
        $lastEnd    = (clone $lastStart)->endOfMonth();

        $activeSubs = Subscriber::where('partner_firebase_id', $partnerId)
            ->where('status', 'active')->count();
        $activeSubsLastMonth = Subscriber::where('partner_firebase_id', $partnerId)
            ->where('status', 'active')
            ->where('created_at', '<=', $lastEnd)
            ->count();
        $subsDelta = $activeSubs - $activeSubsLastMonth;

        $expertCalls = SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')->where('provider_type', 'expat')
            ->whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $expertLast = SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')->where('provider_type', 'expat')
            ->whereBetween('created_at', [$lastStart, $lastEnd])->count();
        $expertDelta = $expertCalls - $expertLast;

        $lawyerCalls = SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')->where('provider_type', 'lawyer')
            ->whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $lawyerLast = SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')->where('provider_type', 'lawyer')
            ->whereBetween('created_at', [$lastStart, $lastEnd])->count();
        $lawyerDelta = $lawyerCalls - $lawyerLast;

        // Total invoice = resolved base (flat OR matched tier) + per-member usage.
        // Covers all 5 billing model permutations including tiered pricing.
        $resolvedThisMonth = $agreement
            ? $agreement->resolveBaseFee($activeSubs)
            : ['amount' => 0.0, 'tier' => null];
        $resolvedLastMonth = $agreement
            ? $agreement->resolveBaseFee($activeSubsLastMonth)
            : ['amount' => 0.0, 'tier' => null];

        $monthlyBaseFee = (float) $resolvedThisMonth['amount'];
        $estimatedInvoice = $monthlyBaseFee + ($activeSubs * $billingRate);
        $estimatedLast = ((float) $resolvedLastMonth['amount']) + ($activeSubsLastMonth * $billingRate);
        $invoiceDelta = $estimatedInvoice - $estimatedLast;

        $totalCallsThisMonth = $expertCalls + $lawyerCalls;
        $totalSecondsThisMonth = (int) SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('call_duration_seconds');
        $avgMinutes = $totalCallsThisMonth > 0
            ? round(($totalSecondsThisMonth / $totalCallsThisMonth) / 60, 1)
            : 0;

        $usageRate = $activeSubs > 0
            ? round(($totalCallsThisMonth / $activeSubs) * 100, 1)
            : 0;

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
            ->first() ?? __('panel.common.dash');

        $pendingInvoices = PartnerInvoice::where('partner_firebase_id', $partnerId)
            ->whereIn('status', ['pending', 'overdue'])
            ->count();
        $overdueInvoices = PartnerInvoice::where('partner_firebase_id', $partnerId)
            ->where('status', 'overdue')
            ->count();

        return [
            Stat::make(__('panel.widget.stats.active_clients'), $activeSubs)
                ->description($this->formatDelta($subsDelta))
                ->descriptionIcon($subsDelta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($subsDelta >= 0 ? 'success' : 'warning'),

            Stat::make(__('panel.widget.stats.expert_calls'), $expertCalls)
                ->description($this->formatDelta($expertDelta))
                ->descriptionIcon($expertDelta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color('info'),

            Stat::make(__('panel.widget.stats.lawyer_calls'), $lawyerCalls)
                ->description($this->formatDelta($lawyerDelta))
                ->descriptionIcon($lawyerDelta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make(
                __('panel.widget.stats.estimated_invoice'),
                $currencySymbol . ' ' . number_format($estimatedInvoice, 2, ',', ' ')
            )
                ->description(
                    // Base component: tier label if a tier was matched, else flat amount.
                    ($monthlyBaseFee > 0
                        ? ($resolvedThisMonth['tier'] !== null
                            ? __('panel.widget.stats.tier_label', [
                                'min' => $resolvedThisMonth['tier']['min'],
                                'max' => $resolvedThisMonth['tier']['max'] ?? '∞',
                                'amount' => $currencySymbol . ' ' . number_format($monthlyBaseFee, 2, ',', ' '),
                            ])
                            : number_format($monthlyBaseFee, 2, ',', ' ') . $currencySymbol)
                            . ($billingRate > 0 ? ' + ' : '')
                        : '')
                    . ($billingRate > 0
                        ? $activeSubs . ' × ' . number_format($billingRate, 2, ',', ' ') . $currencySymbol
                        : '')
                    . ' · ' . $this->formatDelta($invoiceDelta, $currencySymbol)
                )
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make(
                __('panel.widget.stats.avg_duration'),
                $avgMinutes > 0 ? __('panel.widget.stats.minutes', ['m' => $avgMinutes]) : __('panel.common.dash')
            )
                ->description(__('panel.widget.stats.avg_duration_desc', ['count' => $totalCallsThisMonth]))
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),

            Stat::make(__('panel.widget.stats.usage_rate'), $usageRate . '%')
                ->description(__('panel.widget.stats.usage_rate_desc'))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($usageRate > 10 ? 'success' : 'gray'),

            Stat::make(__('panel.widget.stats.top_country'), $topCountry)
                ->description(__('panel.widget.stats.top_country_desc'))
                ->descriptionIcon('heroicon-m-globe-europe-africa')
                ->color('primary'),

            Stat::make(__('panel.widget.stats.invoices_todo'), $pendingInvoices)
                ->description($overdueInvoices > 0
                    ? __('panel.widget.stats.invoices_overdue', ['count' => $overdueInvoices])
                    : ($pendingInvoices > 0
                        ? __('panel.widget.stats.invoices_pending')
                        : __('panel.widget.stats.invoices_ok')))
                ->descriptionIcon($overdueInvoices > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overdueInvoices > 0 ? 'danger' : ($pendingInvoices > 0 ? 'warning' : 'success')),
        ];
    }

    protected function formatDelta(int|float $delta, string $currencySymbol = ''): string
    {
        $label = __('panel.widget.stats.delta_label_last');
        if ($delta === 0 || $delta === 0.0) {
            return __('panel.widget.stats.delta_equal', ['label' => $label]);
        }
        $sign = $delta > 0 ? '+' : '';
        $value = $currencySymbol
            ? $sign . $currencySymbol . ' ' . number_format(abs($delta), 2, ',', ' ')
            : $sign . (int) $delta;
        return $value . ' ' . __('panel.widget.stats.delta_vs', ['label' => $label]);
    }
}
