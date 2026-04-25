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
        $pendingInvoicesAmount = PartnerInvoice::whereIn('status', ['pending', 'overdue'])->sum('total_amount');
        $overdueCount = PartnerInvoice::where('status', 'overdue')->count();

        $revenueMTD = PartnerInvoice::where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('total_amount');

        $revenueLM = PartnerInvoice::where('status', 'paid')
            ->whereMonth('paid_at', now()->subMonth()->month)
            ->whereYear('paid_at', now()->subMonth()->year)
            ->sum('total_amount');

        $revenueDelta = $revenueLM > 0 ? round((($revenueMTD - $revenueLM) / $revenueLM) * 100, 1) : null;
        $deltaLabel = $revenueDelta === null
            ? __('admin.widget.stats.revenue_first')
            : ($revenueDelta >= 0
                ? __('admin.widget.stats.revenue_delta_up', ['pct' => $revenueDelta])
                : __('admin.widget.stats.revenue_delta_down', ['pct' => $revenueDelta]));
        $deltaIcon = $revenueDelta === null
            ? 'heroicon-m-minus'
            : ($revenueDelta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down');

        $callsThisMonth = \App\Models\SubscriberActivity::where('type', 'call_completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Average over invoices PAID this month (consistent with revenue_mtd).
        $avgInvoice = PartnerInvoice::where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->avg('total_amount');

        return [
            Stat::make(__('admin.widget.stats.active_partners'), $activePartners)
                ->description(__('admin.widget.stats.active_partners_desc', ['count' => $sosCallPartners]))
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('success'),

            Stat::make(__('admin.widget.stats.active_subscribers'), number_format($activeSubscribers))
                ->description(__('admin.widget.stats.active_subscribers_desc'))
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make(__('admin.widget.stats.revenue_mtd'), '€' . number_format($revenueMTD, 2, ',', ' '))
                ->description($deltaLabel)
                ->descriptionIcon($deltaIcon)
                ->color($revenueDelta === null || $revenueDelta >= 0 ? 'success' : 'danger'),

            Stat::make(__('admin.widget.stats.unpaid_invoices'), '€' . number_format($pendingInvoicesAmount, 2, ',', ' '))
                ->description(__('admin.widget.stats.overdue_count', ['count' => $overdueCount]))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($overdueCount > 0 ? 'danger' : 'warning'),

            Stat::make(__('admin.widget.stats.calls_this_month'), number_format($callsThisMonth))
                ->description(__('admin.widget.stats.calls_this_month_desc'))
                ->descriptionIcon('heroicon-m-phone')
                ->color('primary'),

            Stat::make(__('admin.widget.stats.avg_invoice'), '€' . number_format($avgInvoice ?? 0, 2, ',', ' '))
                ->description(__('admin.widget.stats.avg_invoice_desc'))
                ->descriptionIcon('heroicon-m-calculator')
                ->color('gray'),
        ];
    }
}
