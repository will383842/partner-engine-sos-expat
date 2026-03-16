<?php

namespace App\Console\Commands;

use App\Models\Agreement;
use App\Models\PartnerMonthlyStat;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Illuminate\Console\Command;

class AggregateMonthlyStats extends Command
{
    protected $signature = 'stats:aggregate';
    protected $description = 'Aggregate monthly partner statistics';

    public function handle(): int
    {
        $month = now()->format('Y-m');
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $partnerIds = Agreement::whereNull('deleted_at')
            ->distinct()
            ->pluck('partner_firebase_id');

        $this->info("Aggregating stats for {$partnerIds->count()} partners, month: {$month}");

        foreach ($partnerIds as $partnerId) {
            $totalSubscribers = Subscriber::where('partner_firebase_id', $partnerId)
                ->whereNull('deleted_at')->count();

            $newSubscribers = Subscriber::where('partner_firebase_id', $partnerId)
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();

            $activeSubscribers = Subscriber::where('partner_firebase_id', $partnerId)
                ->where('status', 'active')->whereNull('deleted_at')->count();

            $callStats = SubscriberActivity::where('partner_firebase_id', $partnerId)
                ->where('type', 'call_completed')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->selectRaw('COUNT(*) as total_calls, COALESCE(SUM(amount_paid_cents), 0) as revenue, COALESCE(SUM(commission_earned_cents), 0) as commissions, COALESCE(SUM(discount_applied_cents), 0) as discounts')
                ->first();

            $conversionRate = $totalSubscribers > 0
                ? round(($activeSubscribers / $totalSubscribers) * 100, 2) : 0;

            PartnerMonthlyStat::updateOrCreate(
                ['partner_firebase_id' => $partnerId, 'month' => $month],
                [
                    'total_subscribers' => $totalSubscribers,
                    'new_subscribers' => $newSubscribers,
                    'active_subscribers' => $activeSubscribers,
                    'total_calls' => $callStats->total_calls ?? 0,
                    'total_revenue_cents' => $callStats->revenue ?? 0,
                    'total_commissions_cents' => $callStats->commissions ?? 0,
                    'total_discounts_cents' => $callStats->discounts ?? 0,
                    'conversion_rate' => $conversionRate,
                ],
            );
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
