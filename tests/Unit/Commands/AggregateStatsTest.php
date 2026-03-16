<?php

namespace Tests\Unit\Commands;

use App\Models\Agreement;
use App\Models\PartnerMonthlyStat;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Tests\TestCase;

class AggregateStatsTest extends TestCase
{
    public function test_aggregates_monthly_stats(): void
    {
        $agreement = Agreement::factory()->create(['partner_firebase_id' => 'p_stats']);

        Subscriber::factory()->forAgreement($agreement)->count(5)->create(['status' => 'invited']);
        $activeSub = Subscriber::factory()->forAgreement($agreement)->active()->create();

        // Activities this month
        SubscriberActivity::factory()->count(3)->create([
            'subscriber_id' => $activeSub->id,
            'partner_firebase_id' => 'p_stats',
            'type' => 'call_completed',
            'amount_paid_cents' => 5000,
            'commission_earned_cents' => 500,
            'discount_applied_cents' => 300,
            'created_at' => now(),
        ]);

        $this->artisan('stats:aggregate')
            ->assertSuccessful();

        $stat = PartnerMonthlyStat::where('partner_firebase_id', 'p_stats')
            ->where('month', now()->format('Y-m'))
            ->first();

        $this->assertNotNull($stat);
        $this->assertEquals(6, $stat->total_subscribers); // 5 + 1
        $this->assertEquals(1, $stat->active_subscribers);
        $this->assertEquals(3, $stat->total_calls);
        $this->assertEquals(15000, $stat->total_revenue_cents); // 3 * 5000
        $this->assertEquals(1500, $stat->total_commissions_cents); // 3 * 500
        $this->assertEquals(900, $stat->total_discounts_cents); // 3 * 300
    }

    public function test_updates_existing_stats(): void
    {
        $agreement = Agreement::factory()->create(['partner_firebase_id' => 'p_upd']);

        PartnerMonthlyStat::factory()->create([
            'partner_firebase_id' => 'p_upd',
            'month' => now()->format('Y-m'),
            'total_calls' => 999, // Old value
        ]);

        $this->artisan('stats:aggregate')
            ->assertSuccessful();

        // Should update, not create duplicate
        $count = PartnerMonthlyStat::where('partner_firebase_id', 'p_upd')
            ->where('month', now()->format('Y-m'))
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_handles_no_partners(): void
    {
        // No agreements/partners exist
        $this->artisan('stats:aggregate')
            ->assertSuccessful();
    }

    public function test_calculates_conversion_rate(): void
    {
        $agreement = Agreement::factory()->create(['partner_firebase_id' => 'p_conv']);

        Subscriber::factory()->forAgreement($agreement)->count(4)->create(['status' => 'invited']);
        Subscriber::factory()->forAgreement($agreement)->count(1)->active()->create();

        $this->artisan('stats:aggregate')
            ->assertSuccessful();

        $stat = PartnerMonthlyStat::where('partner_firebase_id', 'p_conv')->first();
        // 1 active / 5 total = 20%
        $this->assertEquals(20.00, (float) $stat->conversion_rate);
    }
}
