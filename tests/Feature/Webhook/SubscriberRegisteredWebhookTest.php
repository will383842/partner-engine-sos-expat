<?php

namespace Tests\Feature\Webhook;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Tests\TestCase;

class SubscriberRegisteredWebhookTest extends TestCase
{
    private Agreement $agreement;
    private Subscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_reg',
        ]);

        $this->subscriber = Subscriber::factory()->forAgreement($this->agreement)->create([
            'invite_token' => 'test_invite_token_abc',
            'status' => 'invited',
            'firebase_uid' => null,
        ]);
    }

    public function test_subscriber_registered_processes_correctly(): void
    {
        // Called by controller + SubscriberObserver (on status/firebase_uid change)
        $this->firebaseMock->shouldReceive('setDocument')
            ->atLeast()->once();

        $payload = [
            'firebaseUid' => 'new_uid_123',
            'email' => 'subscriber@example.com',
            'inviteToken' => 'test_invite_token_abc',
        ];

        $response = $this->postJson('/api/webhooks/subscriber-registered', $payload, $this->webhookHeaders());

        $response->assertStatus(200)
            ->assertJson(['status' => 'processed']);

        $this->subscriber->refresh();
        $this->assertEquals('registered', $this->subscriber->status);
        $this->assertEquals('new_uid_123', $this->subscriber->firebase_uid);
        $this->assertNotNull($this->subscriber->registered_at);

        // Activity created
        $this->assertDatabaseHas('subscriber_activities', [
            'subscriber_id' => $this->subscriber->id,
            'type' => 'registered',
        ]);
    }

    public function test_subscriber_registered_idempotent(): void
    {
        $this->subscriber->update([
            'firebase_uid' => 'already_set',
            'status' => 'registered',
        ]);

        $payload = [
            'firebaseUid' => 'another_uid',
            'email' => 'subscriber@example.com',
            'inviteToken' => 'test_invite_token_abc',
        ];

        $response = $this->postJson('/api/webhooks/subscriber-registered', $payload, $this->webhookHeaders());

        $response->assertStatus(200)
            ->assertJson(['status' => 'already_registered']);

        // Firebase UID not changed
        $this->subscriber->refresh();
        $this->assertEquals('already_set', $this->subscriber->firebase_uid);
    }

    public function test_subscriber_registered_unknown_token_ignored(): void
    {
        $payload = [
            'firebaseUid' => 'uid_xyz',
            'email' => 'unknown@example.com',
            'inviteToken' => 'nonexistent_token',
        ];

        $response = $this->postJson('/api/webhooks/subscriber-registered', $payload, $this->webhookHeaders());

        $response->assertStatus(200)
            ->assertJson(['status' => 'ignored', 'reason' => 'unknown_invite_token']);
    }

    public function test_subscriber_registered_validation_requires_fields(): void
    {
        $response = $this->postJson('/api/webhooks/subscriber-registered', [], $this->webhookHeaders());

        $response->assertStatus(422);
    }

    public function test_subscriber_registered_without_secret_returns_401(): void
    {
        $payload = [
            'firebaseUid' => 'uid',
            'email' => 'test@test.com',
            'inviteToken' => 'token',
        ];

        $response = $this->postJson('/api/webhooks/subscriber-registered', $payload);

        $response->assertStatus(401);
    }
}
