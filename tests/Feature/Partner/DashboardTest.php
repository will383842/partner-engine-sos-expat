<?php

namespace Tests\Feature\Partner;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use App\Models\PartnerMonthlyStat;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    private string $partnerId = 'partner_dash';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsPartner($this->partnerId);
    }

    public function test_dashboard_returns_correct_stats(): void
    {
        $agreement = Agreement::factory()->create(['partner_firebase_id' => $this->partnerId]);

        Subscriber::factory()->forAgreement($agreement)->count(5)->create(['status' => 'invited']);
        Subscriber::factory()->forAgreement($agreement)->count(3)->active()->create();

        // Create some call activities this month
        $activeSubscriber = Subscriber::factory()->forAgreement($agreement)->active()->create();
        SubscriberActivity::factory()->count(2)->create([
            'subscriber_id' => $activeSubscriber->id,
            'partner_firebase_id' => $this->partnerId,
            'type' => 'call_completed',
            'commission_earned_cents' => 500,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/partner/dashboard', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_subscribers',
                'active_subscribers',
                'new_this_month',
                'calls_this_month',
                'revenue_this_month_cents',
                'conversion_rate',
            ]);

        $data = $response->json();
        $this->assertEquals(9, $data['total_subscribers']); // 5 + 3 + 1
        $this->assertEquals(4, $data['active_subscribers']); // 3 + 1
        $this->assertEquals(2, $data['calls_this_month']);
        $this->assertEquals(1000, $data['revenue_this_month_cents']); // 2 * 500
    }

    public function test_earnings_breakdown(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => $this->partnerId,
            'name' => 'Accord Test',
        ]);

        $subscriber = Subscriber::factory()->forAgreement($agreement)->active()->create();

        SubscriberActivity::factory()->count(3)->create([
            'subscriber_id' => $subscriber->id,
            'partner_firebase_id' => $this->partnerId,
            'type' => 'call_completed',
            'commission_earned_cents' => 500,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/partner/earnings/breakdown', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'subscribers' => [
                    'total_cents',
                    'this_month_cents',
                    'by_agreement',
                ],
            ]);
    }

    public function test_partner_agreement_returns_active(): void
    {
        Agreement::factory()->expired()->create(['partner_firebase_id' => $this->partnerId]);
        $active = Agreement::factory()->create([
            'partner_firebase_id' => $this->partnerId,
            'name' => 'Active Agreement',
        ]);

        $response = $this->getJson('/api/partner/agreement', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Active Agreement']);
    }

    public function test_partner_stats_returns_monthly(): void
    {
        PartnerMonthlyStat::factory()->create([
            'partner_firebase_id' => $this->partnerId,
            'month' => now()->format('Y-m'),
        ]);

        $response = $this->getJson('/api/partner/stats', $this->authHeaders());

        $response->assertStatus(200);
    }

    public function test_partner_activity_returns_timeline(): void
    {
        $agreement = Agreement::factory()->create(['partner_firebase_id' => $this->partnerId]);
        $subscriber = Subscriber::factory()->forAgreement($agreement)->active()->create();

        SubscriberActivity::factory()->count(5)->create([
            'subscriber_id' => $subscriber->id,
            'partner_firebase_id' => $this->partnerId,
        ]);

        $response = $this->getJson('/api/partner/activity', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }
}
