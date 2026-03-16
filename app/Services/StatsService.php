<?php

namespace App\Services;

use App\Models\Agreement;
use App\Models\PartnerMonthlyStat;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Illuminate\Support\Facades\DB;

class StatsService
{
    /**
     * Get dashboard stats for a partner.
     */
    public function partnerDashboard(string $partnerFirebaseId): array
    {
        $totalSubscribers = Subscriber::where('partner_firebase_id', $partnerFirebaseId)
            ->whereNull('deleted_at')
            ->count();

        $activeSubscribers = Subscriber::where('partner_firebase_id', $partnerFirebaseId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->count();

        $thisMonth = now()->format('Y-m');
        $newThisMonth = Subscriber::where('partner_firebase_id', $partnerFirebaseId)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $callsThisMonth = SubscriberActivity::where('partner_firebase_id', $partnerFirebaseId)
            ->where('type', 'call_completed')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $revenueThisMonth = SubscriberActivity::where('partner_firebase_id', $partnerFirebaseId)
            ->where('type', 'call_completed')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('commission_earned_cents');

        $conversionRate = $totalSubscribers > 0
            ? round(($activeSubscribers / $totalSubscribers) * 100, 1)
            : 0;

        return [
            'total_subscribers' => $totalSubscribers,
            'active_subscribers' => $activeSubscribers,
            'new_this_month' => $newThisMonth,
            'calls_this_month' => $callsThisMonth,
            'revenue_this_month_cents' => $revenueThisMonth,
            'conversion_rate' => $conversionRate,
        ];
    }

    /**
     * Get monthly stats for a partner (for charts).
     */
    public function partnerMonthlyStats(string $partnerFirebaseId, int $months = 12): array
    {
        return PartnerMonthlyStat::where('partner_firebase_id', $partnerFirebaseId)
            ->orderByDesc('month')
            ->limit($months)
            ->get()
            ->toArray();
    }

    /**
     * Get earnings breakdown: affiliate vs subscriber commissions.
     */
    public function earningsBreakdown(string $partnerFirebaseId): array
    {
        $subscriberTotal = SubscriberActivity::where('partner_firebase_id', $partnerFirebaseId)
            ->where('type', 'call_completed')
            ->sum('commission_earned_cents');

        $subscriberThisMonth = SubscriberActivity::where('partner_firebase_id', $partnerFirebaseId)
            ->where('type', 'call_completed')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('commission_earned_cents');

        // Breakdown by agreement
        $byAgreement = SubscriberActivity::where('subscriber_activities.partner_firebase_id', $partnerFirebaseId)
            ->where('subscriber_activities.type', 'call_completed')
            ->join('subscribers', 'subscriber_activities.subscriber_id', '=', 'subscribers.id')
            ->join('agreements', 'subscribers.agreement_id', '=', 'agreements.id')
            ->groupBy('agreements.id', 'agreements.name')
            ->select(
                'agreements.id as agreement_id',
                'agreements.name as agreement_name',
                DB::raw('SUM(subscriber_activities.commission_earned_cents) as total_cents'),
                DB::raw('COUNT(*) as call_count'),
            )
            ->get()
            ->toArray();

        return [
            'subscribers' => [
                'total_cents' => $subscriberTotal,
                'this_month_cents' => $subscriberThisMonth,
                'by_agreement' => $byAgreement,
            ],
        ];
    }

    /**
     * Get global admin stats.
     */
    public function globalStats(): array
    {
        $activePartners = Agreement::where('status', 'active')
            ->whereNull('deleted_at')
            ->distinct('partner_firebase_id')
            ->count('partner_firebase_id');

        $totalSubscribers = Subscriber::whereNull('deleted_at')->count();

        $callsThisMonth = SubscriberActivity::where('type', 'call_completed')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $revenueThisMonth = SubscriberActivity::where('type', 'call_completed')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('commission_earned_cents');

        // Monthly evolution (last 12 months)
        $monthly = PartnerMonthlyStat::selectRaw("
                month,
                SUM(total_subscribers) as total_subscribers,
                SUM(total_calls) as total_calls,
                SUM(total_commissions_cents) as total_commissions_cents
            ")
            ->groupBy('month')
            ->orderByDesc('month')
            ->limit(12)
            ->get()
            ->toArray();

        // Top 10 partners
        $topPartners = Agreement::where('status', 'active')
            ->whereNull('deleted_at')
            ->withCount(['subscribers' => function ($q) {
                $q->whereNull('deleted_at');
            }])
            ->orderByDesc('subscribers_count')
            ->limit(10)
            ->get(['id', 'partner_firebase_id', 'partner_name', 'name'])
            ->toArray();

        return [
            'active_partners' => $activePartners,
            'total_subscribers' => $totalSubscribers,
            'calls_this_month' => $callsThisMonth,
            'revenue_this_month_cents' => $revenueThisMonth,
            'monthly' => $monthly,
            'top_partners' => $topPartners,
        ];
    }
}
