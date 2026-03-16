<?php

namespace Tests\Feature\Webhook;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Tests\TestCase;

class CallCompletedWebhookTest extends TestCase
{
    private Agreement $agreement;
    private Subscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_abc',
            'commission_per_call_lawyer' => 500,
            'commission_per_call_expat' => 300,
            'commission_type' => 'fixed',
        ]);

        $this->subscriber = Subscriber::factory()->registered('client_uid_123')->forAgreement($this->agreement)->create();
    }

    public function test_webhook_without_secret_returns_401(): void
    {
        $response = $this->postJson('/api/webhooks/call-completed', []);

        $response->assertStatus(401);
    }

    public function test_webhook_with_invalid_secret_returns_401(): void
    {
        $response = $this->postJson('/api/webhooks/call-completed', [], [
            'X-Engine-Secret' => 'wrong-secret',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_with_empty_body_returns_422(): void
    {
        $response = $this->postJson('/api/webhooks/call-completed', [], $this->webhookHeaders());

        $response->assertStatus(422);
    }

    public function test_call_completed_creates_activity_and_commission(): void
    {
        $payload = [
            'callSessionId' => 'call_session_001',
            'clientUid' => 'client_uid_123',
            'providerType' => 'lawyer',
            'duration' => 120,
            'amountPaidCents' => 4900,
            'discountAppliedCents' => 300,
        ];

        $response = $this->postJson('/api/webhooks/call-completed', $payload, $this->webhookHeaders());

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'processed',
                'commission_cents' => 500,
            ]);

        // Verify activity created
        $this->assertDatabaseHas('subscriber_activities', [
            'subscriber_id' => $this->subscriber->id,
            'type' => 'call_completed',
            'call_session_id' => 'call_session_001',
            'provider_type' => 'lawyer',
            'commission_earned_cents' => 500,
            'amount_paid_cents' => 4900,
        ]);

        // Verify subscriber stats updated
        $this->subscriber->refresh();
        $this->assertEquals(1, $this->subscriber->total_calls);
        $this->assertEquals(4900, $this->subscriber->total_spent_cents);
        $this->assertEquals(300, $this->subscriber->total_discount_cents);
    }

    public function test_call_completed_for_expat_uses_expat_commission(): void
    {
        $payload = [
            'callSessionId' => 'call_session_expat',
            'clientUid' => 'client_uid_123',
            'providerType' => 'expat',
            'duration' => 60,
            'amountPaidCents' => 3000,
        ];

        $response = $this->postJson('/api/webhooks/call-completed', $payload, $this->webhookHeaders());

        $response->assertStatus(200)
            ->assertJson(['commission_cents' => 300]);
    }

    public function test_call_completed_idempotent_on_duplicate(): void
    {
        $payload = [
            'callSessionId' => 'call_session_dup',
            'clientUid' => 'client_uid_123',
            'providerType' => 'lawyer',
            'duration' => 120,
            'amountPaidCents' => 4900,
        ];

        // First call
        $this->postJson('/api/webhooks/call-completed', $payload, $this->webhookHeaders())
            ->assertStatus(200)
            ->assertJson(['status' => 'processed']);

        // Duplicate call — should return already_processed
        $this->postJson('/api/webhooks/call-completed', $payload, $this->webhookHeaders())
            ->assertStatus(200)
            ->assertJson(['status' => 'already_processed']);

        // Only one activity created
        $this->assertEquals(1, SubscriberActivity::where('call_session_id', 'call_session_dup')->count());
    }

    public function test_call_completed_unknown_subscriber_returns_ignored(): void
    {
        $payload = [
            'callSessionId' => 'call_unknown',
            'clientUid' => 'unknown_client_uid',
            'providerType' => 'lawyer',
            'duration' => 120,
            'amountPaidCents' => 4900,
        ];

        $response = $this->postJson('/api/webhooks/call-completed', $payload, $this->webhookHeaders());

        $response->assertStatus(200)
            ->assertJson(['status' => 'ignored', 'reason' => 'not_a_subscriber']);
    }

    public function test_call_completed_no_active_agreement_returns_ignored(): void
    {
        // Expire the agreement
        $this->agreement->update(['status' => 'expired']);

        $payload = [
            'callSessionId' => 'call_expired',
            'clientUid' => 'client_uid_123',
            'providerType' => 'lawyer',
            'duration' => 120,
            'amountPaidCents' => 4900,
        ];

        $response = $this->postJson('/api/webhooks/call-completed', $payload, $this->webhookHeaders());

        $response->assertStatus(200)
            ->assertJson(['status' => 'ignored', 'reason' => 'no_active_agreement']);
    }

    public function test_call_completed_respects_max_calls_per_subscriber(): void
    {
        $this->agreement->update(['max_calls_per_subscriber' => 2]);
        $this->subscriber->update(['total_calls' => 2]);

        $payload = [
            'callSessionId' => 'call_max_reached',
            'clientUid' => 'client_uid_123',
            'providerType' => 'lawyer',
            'duration' => 120,
            'amountPaidCents' => 4900,
        ];

        $response = $this->postJson('/api/webhooks/call-completed', $payload, $this->webhookHeaders());

        $response->assertStatus(200)
            ->assertJson(['status' => 'ignored', 'reason' => 'no_active_agreement']);
    }

    public function test_call_completed_percent_commission_with_cap(): void
    {
        $this->agreement->update([
            'commission_type' => 'percent',
            'commission_percent' => 80, // 80% — will be capped at 50%
        ]);

        $payload = [
            'callSessionId' => 'call_percent_cap',
            'clientUid' => 'client_uid_123',
            'providerType' => 'lawyer',
            'duration' => 120,
            'amountPaidCents' => 10000, // $100
        ];

        $response = $this->postJson('/api/webhooks/call-completed', $payload, $this->webhookHeaders());

        // 80% of 10000 = 8000, but capped at 50% = 5000
        $response->assertStatus(200)
            ->assertJson(['commission_cents' => 5000]);
    }

    public function test_call_completed_percent_commission_under_cap(): void
    {
        $this->agreement->update([
            'commission_type' => 'percent',
            'commission_percent' => 10,
        ]);

        $payload = [
            'callSessionId' => 'call_percent_ok',
            'clientUid' => 'client_uid_123',
            'providerType' => 'lawyer',
            'duration' => 120,
            'amountPaidCents' => 5000,
        ];

        $response = $this->postJson('/api/webhooks/call-completed', $payload, $this->webhookHeaders());

        // 10% of 5000 = 500, under 50% cap (2500)
        $response->assertStatus(200)
            ->assertJson(['commission_cents' => 500]);
    }

    public function test_call_completed_transitions_registered_to_active(): void
    {
        $this->assertEquals('registered', $this->subscriber->status);

        $payload = [
            'callSessionId' => 'call_transition',
            'clientUid' => 'client_uid_123',
            'providerType' => 'lawyer',
            'duration' => 120,
            'amountPaidCents' => 4900,
        ];

        $this->postJson('/api/webhooks/call-completed', $payload, $this->webhookHeaders())
            ->assertStatus(200);

        $this->subscriber->refresh();
        $this->assertEquals('active', $this->subscriber->status);
    }

    public function test_call_completed_multi_partner_prioritizes_referred_by(): void
    {
        $agreement2 = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_xyz',
            'discount_value' => 500, // Better discount
            'commission_per_call_lawyer' => 800,
        ]);

        Subscriber::factory()->registered('client_uid_123')->forAgreement($agreement2)->create([
            'email' => 'other@example.com',
        ]);

        $payload = [
            'callSessionId' => 'call_multi',
            'clientUid' => 'client_uid_123',
            'providerType' => 'lawyer',
            'duration' => 120,
            'amountPaidCents' => 4900,
            'partnerReferredBy' => 'partner_abc', // Explicitly referred by partner_abc
        ];

        $response = $this->postJson('/api/webhooks/call-completed', $payload, $this->webhookHeaders());

        // Should use partner_abc ($5 commission), NOT partner_xyz ($8)
        $response->assertStatus(200)
            ->assertJson(['commission_cents' => 500]);
    }

    public function test_call_completed_multi_partner_picks_best_discount(): void
    {
        $agreement2 = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_better',
            'discount_type' => 'fixed',
            'discount_value' => 1000, // Better discount than 300
            'commission_per_call_lawyer' => 800,
        ]);

        Subscriber::factory()->registered('client_uid_123')->forAgreement($agreement2)->create([
            'email' => 'better@example.com',
        ]);

        $payload = [
            'callSessionId' => 'call_best_discount',
            'clientUid' => 'client_uid_123',
            'providerType' => 'lawyer',
            'duration' => 120,
            'amountPaidCents' => 4900,
            // No partnerReferredBy — should pick best discount
        ];

        $response = $this->postJson('/api/webhooks/call-completed', $payload, $this->webhookHeaders());

        // Should pick partner_better with $8 commission (better discount)
        $response->assertStatus(200)
            ->assertJson(['commission_cents' => 800]);
    }

    public function test_call_completed_writes_to_firestore(): void
    {
        // Expect Firestore writes
        $this->firebaseMock->shouldReceive('setDocument')
            ->once()
            ->with('partner_commissions', \Mockery::type('string'), \Mockery::type('array'));

        $this->firebaseMock->shouldReceive('incrementField')
            ->once()
            ->with('partners', 'partner_abc', 'pendingBalance', 500);

        $this->firebaseMock->shouldReceive('incrementField')
            ->once()
            ->with('partners', 'partner_abc', 'totalEarned', 500);

        $payload = [
            'callSessionId' => 'call_firestore',
            'clientUid' => 'client_uid_123',
            'providerType' => 'lawyer',
            'duration' => 120,
            'amountPaidCents' => 4900,
        ];

        $this->postJson('/api/webhooks/call-completed', $payload, $this->webhookHeaders())
            ->assertStatus(200);
    }

    public function test_call_completed_validation_rejects_invalid_provider_type(): void
    {
        $payload = [
            'callSessionId' => 'call_invalid',
            'clientUid' => 'client_uid_123',
            'providerType' => 'doctor', // Invalid
            'duration' => 120,
            'amountPaidCents' => 4900,
        ];

        $response = $this->postJson('/api/webhooks/call-completed', $payload, $this->webhookHeaders());
        $response->assertStatus(422);
    }
}
