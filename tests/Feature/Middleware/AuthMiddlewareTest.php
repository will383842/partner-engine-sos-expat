<?php

namespace Tests\Feature\Middleware;

use App\Models\Subscriber;
use Tests\TestCase;

class AuthMiddlewareTest extends TestCase
{
    // ── FirebaseAuth ─────────────────────────────────────

    public function test_request_without_bearer_token_returns_401(): void
    {
        $response = $this->getJson('/api/partner/dashboard');
        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized — missing Bearer token']);
    }

    public function test_request_with_invalid_token_returns_401(): void
    {
        $this->firebaseMock->shouldReceive('verifyIdToken')
            ->andThrow(new \Exception('Token expired'));

        $response = $this->getJson('/api/partner/dashboard', $this->authHeaders());
        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized — invalid token']);
    }

    // ── RequirePartner ───────────────────────────────────

    public function test_valid_partner_token_passes(): void
    {
        $this->actingAsPartner();

        $response = $this->getJson('/api/partner/dashboard', $this->authHeaders());
        // Should not be 401/403 — it will be 200 or similar
        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    public function test_non_partner_user_returns_403(): void
    {
        $this->firebaseMock->shouldReceive('verifyIdToken')
            ->andReturn(['uid' => 'regular_user', 'email' => 'user@test.com']);

        // User exists but is NOT a partner (no partner doc)
        $this->firebaseMock->shouldReceive('getDocument')
            ->with('partners', 'regular_user')
            ->andReturn(null);

        $response = $this->getJson('/api/partner/dashboard', $this->authHeaders());
        $response->assertStatus(403)
            ->assertJson(['error' => 'Forbidden — not a partner']);
    }

    public function test_inactive_partner_returns_403(): void
    {
        $this->firebaseMock->shouldReceive('verifyIdToken')
            ->andReturn(['uid' => 'suspended_partner', 'email' => 'sus@test.com']);

        $this->firebaseMock->shouldReceive('getDocument')
            ->with('partners', 'suspended_partner')
            ->andReturn(['status' => 'suspended']);

        $response = $this->getJson('/api/partner/dashboard', $this->authHeaders());
        $response->assertStatus(403)
            ->assertJson(['error' => 'Forbidden — partner account not active']);
    }

    public function test_approved_partner_passes(): void
    {
        $this->actingAsPartner(partnerDoc: ['status' => 'approved']);

        $response = $this->getJson('/api/partner/dashboard', $this->authHeaders());
        $this->assertNotEquals(403, $response->status());
    }

    // ── RequireAdmin ─────────────────────────────────────

    public function test_admin_access_passes(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/stats', $this->authHeaders());
        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    public function test_non_admin_returns_403(): void
    {
        $this->firebaseMock->shouldReceive('verifyIdToken')
            ->andReturn(['uid' => 'not_admin', 'email' => 'user@test.com']);

        $this->firebaseMock->shouldReceive('getDocument')
            ->with('users', 'not_admin')
            ->andReturn(['role' => 'client']);

        $response = $this->getJson('/api/admin/stats', $this->authHeaders());
        $response->assertStatus(403)
            ->assertJson(['error' => 'Forbidden — admin role required']);
    }

    // ── RequireSubscriber ────────────────────────────────

    public function test_subscriber_access_passes(): void
    {
        $subscriber = Subscriber::factory()->registered('sub_uid_test')->create();

        $this->actingAsSubscriberUser('sub_uid_test');

        $response = $this->getJson('/api/subscriber/me', $this->authHeaders());
        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    public function test_non_subscriber_returns_403(): void
    {
        $this->actingAsSubscriberUser('not_a_subscriber_uid');

        $response = $this->getJson('/api/subscriber/me', $this->authHeaders());
        $response->assertStatus(403)
            ->assertJson(['error' => 'Forbidden — not a subscriber']);
    }

    // ── WebhookSecret ────────────────────────────────────

    public function test_webhook_without_secret_returns_401(): void
    {
        $response = $this->postJson('/api/webhooks/call-completed', []);
        $response->assertStatus(401);
    }

    public function test_webhook_with_valid_secret_passes(): void
    {
        // Will fail validation but NOT auth (422 not 401)
        $response = $this->postJson('/api/webhooks/call-completed', [], $this->webhookHeaders());
        $response->assertStatus(422); // Validation error, not 401
    }
}
