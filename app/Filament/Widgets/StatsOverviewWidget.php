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
        // Only count subscribers belonging to an active agreement (a paused
        // partner's subscribers should not inflate the live KPI).
        $activeSubscribers = Subscriber::where('status', 'active')
            ->whereHas('agreement', fn($q) => $q->where('status', 'active'))
            ->count();
        $overdueCount = PartnerInvoice::where('status', 'overdue')->count();

        // Currency-aware aggregations: split by EUR vs USD so we never sum
        // €100 + $100 into "€200" in a KPI tile. Each tile shows its own
        // currency; if a currency has no activity that tile shows 0 in
        // the matching symbol.
        $sumByCurrency = function (callable $scope): array {
            $rows = $scope(PartnerInvoice::query())
                ->selectRaw('UPPER(billing_currency) as cur, SUM(total_amount) as total')
                ->groupBy('cur')
                ->pluck('total', 'cur');
            return [
                'eur' => (float) ($rows['EUR'] ?? 0),
                'usd' => (float) ($rows['USD'] ?? 0),
            ];
        };

        // Revenue MTD (paid invoices this month) split by currency
        $revenueMTD = $sumByCurrency(fn($q) => $q
            ->where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year));

        // Revenue last month split by currency (for delta vs current month)
        $revenueLM = $sumByCurrency(fn($q) => $q
            ->where('status', 'paid')
            ->whereMonth('paid_at', now()->subMonth()->month)
            ->whereYear('paid_at', now()->subMonth()->year));

        // Pending invoices split by currency
        $pendingByCur = $sumByCurrency(fn($q) => $q
            ->whereIn('status', ['pending', 'overdue']));

        $callsThisMonth = \App\Models\SubscriberActivity::where('type', 'call_completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Build a delta description for each currency
        $deltaFor = function (float $current, float $previous): array {
            if ($previous <= 0) {
                return [
                    'label' => __('admin.widget.stats.revenue_first'),
                    'icon'  => 'heroicon-m-minus',
                    'color' => 'success',
                ];
            }
            $pct = round((($current - $previous) / $previous) * 100, 1);
            return [
                'label' => $pct >= 0
                    ? __('admin.widget.stats.revenue_delta_up', ['pct' => $pct])
                    : __('admin.widget.stats.revenue_delta_down', ['pct' => $pct]),
                'icon'  => $pct >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down',
                'color' => $pct >= 0 ? 'success' : 'danger',
            ];
        };

        $deltaEur = $deltaFor($revenueMTD['eur'], $revenueLM['eur']);
        $deltaUsd = $deltaFor($revenueMTD['usd'], $revenueLM['usd']);

        return [
            Stat::make(__('admin.widget.stats.active_partners'), $activePartners)
                ->description(__('admin.widget.stats.active_partners_desc', ['count' => $sosCallPartners]))
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('success'),

            Stat::make(__('admin.widget.stats.active_subscribers'), number_format($activeSubscribers))
                ->description(__('admin.widget.stats.active_subscribers_desc'))
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            // Revenue MTD — EUR tile
            Stat::make(
                __('admin.widget.stats.revenue_mtd_eur'),
                '€' . number_format($revenueMTD['eur'], 2, ',', ' ')
            )
                ->description($deltaEur['label'])
                ->descriptionIcon($deltaEur['icon'])
                ->color($deltaEur['color']),

            // Revenue MTD — USD tile
            Stat::make(
                __('admin.widget.stats.revenue_mtd_usd'),
                '$' . number_format($revenueMTD['usd'], 2, ',', ' ')
            )
                ->description($deltaUsd['label'])
                ->descriptionIcon($deltaUsd['icon'])
                ->color($deltaUsd['color']),

            // Pending invoices — EUR tile
            Stat::make(
                __('admin.widget.stats.unpaid_invoices_eur'),
                '€' . number_format($pendingByCur['eur'], 2, ',', ' ')
            )
                ->description(__('admin.widget.stats.overdue_count', ['count' => $overdueCount]))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($overdueCount > 0 ? 'danger' : 'warning'),

            // Pending invoices — USD tile
            Stat::make(
                __('admin.widget.stats.unpaid_invoices_usd'),
                '$' . number_format($pendingByCur['usd'], 2, ',', ' ')
            )
                ->description(__('admin.widget.stats.overdue_count', ['count' => $overdueCount]))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($overdueCount > 0 ? 'danger' : 'warning'),

            Stat::make(__('admin.widget.stats.calls_this_month'), number_format($callsThisMonth))
                ->description(__('admin.widget.stats.calls_this_month_desc'))
                ->descriptionIcon('heroicon-m-phone')
                ->color('primary'),
        ];
    }
}
