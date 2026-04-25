<?php

namespace App\Filament\Partner\Widgets;

use App\Models\Agreement;
use App\Models\PartnerInvoice;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

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

        // Branch managers see KPIs scoped to the cabinets they manage.
        // Group admins (role=partner) keep the partner-wide view.
        $isBranchManager = $user instanceof User && $user->isBranchManager();
        $managedLabels = $isBranchManager ? $user->getManagedGroupLabels() : null;

        // Pre-resolve the subscriber-id whitelist for branch managers so we can
        // filter SubscriberActivity (which has no group_label of its own) via
        // a single whereIn — cheaper and clearer than a JOIN per stat.
        $scopedSubscriberIds = null;
        if ($isBranchManager) {
            if (empty($managedLabels)) {
                // Fail-closed: branch manager with no cabinet → empty dashboard
                return [];
            }
            $scopedSubscriberIds = Subscriber::where('partner_firebase_id', $partnerId)
                ->whereIn('group_label', $managedLabels)
                ->pluck('id')
                ->all();
        }

        $applySubscriberScope = function (Builder $q) use ($isBranchManager, $partnerId, $managedLabels) {
            $q->where('partner_firebase_id', $partnerId);
            if ($isBranchManager) {
                $q->whereIn('group_label', $managedLabels);
            }
            return $q;
        };

        $applyActivityScope = function (Builder $q) use ($isBranchManager, $partnerId, $scopedSubscriberIds) {
            $q->where('partner_firebase_id', $partnerId);
            if ($isBranchManager) {
                if (empty($scopedSubscriberIds)) {
                    $q->whereRaw('1 = 0');
                } else {
                    $q->whereIn('subscriber_id', $scopedSubscriberIds);
                }
            }
            return $q;
        };

        $activeSubs = $applySubscriberScope(Subscriber::query())
            ->where('status', 'active')->count();
        $activeSubsLastMonth = $applySubscriberScope(Subscriber::query())
            ->where('status', 'active')
            ->where('created_at', '<=', $lastEnd)
            ->count();
        $subsDelta = $activeSubs - $activeSubsLastMonth;

        $expertCalls = $applyActivityScope(SubscriberActivity::query())
            ->where('type', 'call_completed')->where('provider_type', 'expat')
            ->whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $expertLast = $applyActivityScope(SubscriberActivity::query())
            ->where('type', 'call_completed')->where('provider_type', 'expat')
            ->whereBetween('created_at', [$lastStart, $lastEnd])->count();
        $expertDelta = $expertCalls - $expertLast;

        $lawyerCalls = $applyActivityScope(SubscriberActivity::query())
            ->where('type', 'call_completed')->where('provider_type', 'lawyer')
            ->whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $lawyerLast = $applyActivityScope(SubscriberActivity::query())
            ->where('type', 'call_completed')->where('provider_type', 'lawyer')
            ->whereBetween('created_at', [$lastStart, $lastEnd])->count();
        $lawyerDelta = $lawyerCalls - $lawyerLast;

        // Estimated invoice — semantics depend on the role:
        //   • Group admin (role=partner): full partner-level total, including
        //     the resolved base fee (flat or matched tier) plus per-member
        //     usage across the whole partner.
        //   • Branch manager: contribution of THEIR cabinet only, i.e. the
        //     per-member component for their managed group_labels. The
        //     partner-level flat / tier fee is the group admin's
        //     responsibility, so we don't show it under the branch manager's
        //     own KPIs (they'd otherwise see a number they don't owe).
        if ($isBranchManager) {
            $resolvedThisMonth = ['amount' => 0.0, 'tier' => null];
            $resolvedLastMonth = ['amount' => 0.0, 'tier' => null];
        } else {
            // Resolve against the partner-wide active sub count (used for
            // tier matching), not the scoped count, so the displayed tier
            // is the one that will actually price the invoice.
            $partnerWideActiveSubs = (int) Subscriber::where('partner_firebase_id', $partnerId)
                ->where('status', 'active')->count();
            $partnerWideLastMonth = (int) Subscriber::where('partner_firebase_id', $partnerId)
                ->where('status', 'active')
                ->where('created_at', '<=', $lastEnd)
                ->count();
            $resolvedThisMonth = $agreement
                ? $agreement->resolveBaseFee($partnerWideActiveSubs)
                : ['amount' => 0.0, 'tier' => null];
            $resolvedLastMonth = $agreement
                ? $agreement->resolveBaseFee($partnerWideLastMonth)
                : ['amount' => 0.0, 'tier' => null];
        }

        $monthlyBaseFee = (float) $resolvedThisMonth['amount'];
        $estimatedInvoice = $monthlyBaseFee + ($activeSubs * $billingRate);
        $estimatedLast = ((float) $resolvedLastMonth['amount']) + ($activeSubsLastMonth * $billingRate);
        $invoiceDelta = $estimatedInvoice - $estimatedLast;

        $totalCallsThisMonth = $expertCalls + $lawyerCalls;
        $totalSecondsThisMonth = (int) $applyActivityScope(SubscriberActivity::query())
            ->where('type', 'call_completed')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('call_duration_seconds');
        $avgMinutes = $totalCallsThisMonth > 0
            ? round(($totalSecondsThisMonth / $totalCallsThisMonth) / 60, 1)
            : 0;

        $usageRate = $activeSubs > 0
            ? round(($totalCallsThisMonth / $activeSubs) * 100, 1)
            : 0;

        $topCountry = $applyActivityScope(SubscriberActivity::query())
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
