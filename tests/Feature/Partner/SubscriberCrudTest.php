<?php

namespace Tests\Feature\Partner;

use App\Models\Agreement;
use App\Models\AuditLog;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use App\Jobs\SyncSubscriberToFirestore;
use App\Jobs\SendSubscriberInvitation;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubscriberCrudTest extends TestCase
{
    private Agreement $agreement;
    private string $partnerId = 'partner_crud_test';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->actingAsPartner($this->partnerId);

        $this->agreement = Agreement::factory()->create([
            'partner_firebase_id' => $this->partnerId,
        ]);
    }

    // ── List ─────────────────────────────────────────────

    public function test_list_subscribers_empty(): void
    {
        $response = $this->getJson('/api/partner/subscribers', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_list_subscribers_with_status_filter(): void
    {
        Subscriber::factory()->forAgreement($this->agreement)->count(3)->create(['status' => 'invited']);
        Subscriber::factory()->forAgreement($this->agreement)->count(2)->registered()->create();

        $response = $this->getJson('/api/partner/subscribers?status=invited', $this->authHeaders());

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_list_subscribers_pagination(): void
    {
        Subscriber::factory()->forAgreement($this->agreement)->count(5)->create();

        $response = $this->getJson('/api/partner/subscribers?per_page=2', $this->authHeaders());

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertNotNull($response->json('next_cursor'));
    }

    public function test_partner_can_only_see_own_subscribers(): void
    {
        // Create subscriber for a different partner
        Subscriber::factory()->create(['partner_firebase_id' => 'other_partner']);

        // Create subscriber for this partner
        Subscriber::factory()->forAgreement($this->agreement)->create();

        $response = $this->getJson('/api/partner/subscribers', $this->authHeaders());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    // ── Create ───────────────────────────────────────────

    public function test_create_subscriber_succeeds(): void
    {
        $response = $this->postJson('/api/partner/subscribers', [
            'email' => 'new@subscriber.com',
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'country' => 'FR',
            'language' => 'fr',
        ], $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonFragment(['email' => 'new@subscriber.com']);

        $this->assertDatabaseHas('subscribers', [
            'email' => 'new@subscriber.com',
            'partner_firebase_id' => $this->partnerId,
            'status' => 'invited',
        ]);

        // Jobs dispatched
        Queue::assertPushed(SyncSubscriberToFirestore::class);
        Queue::assertPushed(SendSubscriberInvitation::class);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'subscriber.created',
            'resource_type' => 'subscriber',
        ]);
    }

    public function test_create_subscriber_generates_invite_token(): void
    {
        $response = $this->postJson('/api/partner/subscribers', [
            'email' => 'token@test.com',
        ], $this->authHeaders());

        $response->assertStatus(201);

        $subscriber = Subscriber::where('email', 'token@test.com')->first();
        $this->assertNotNull($subscriber->invite_token);
        $this->assertEquals(64, strlen($subscriber->invite_token));
    }

    public function test_duplicate_email_same_partner_rejected(): void
    {
        Subscriber::factory()->forAgreement($this->agreement)->create([
            'email' => 'duplicate@test.com',
        ]);

        $response = $this->postJson('/api/partner/subscribers', [
            'email' => 'duplicate@test.com',
        ], $this->authHeaders());

        $response->assertStatus(409);
    }

    public function test_create_subscriber_respects_max_limit(): void
    {
        $this->agreement->update(['max_subscribers' => 1]);

        Subscriber::factory()->forAgreement($this->agreement)->create();

        $response = $this->postJson('/api/partner/subscribers', [
            'email' => 'over_limit@test.com',
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    // ── Show ─────────────────────────────────────────────

    public function test_show_subscriber_with_activities(): void
    {
        $subscriber = Subscriber::factory()->forAgreement($this->agreement)->create();
        SubscriberActivity::factory()->create([
            'subscriber_id' => $subscriber->id,
            'partner_firebase_id' => $this->partnerId,
        ]);

        $response = $this->getJson("/api/partner/subscribers/{$subscriber->id}", $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure(['email', 'activities']);
    }

    // ── Update ───────────────────────────────────────────

    public function test_update_subscriber(): void
    {
        $subscriber = Subscriber::factory()->forAgreement($this->agreement)->create();

        $response = $this->putJson("/api/partner/subscribers/{$subscriber->id}", [
            'first_name' => 'Updated',
            'last_name' => 'Name',
        ], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonFragment(['first_name' => 'Updated']);
    }

    // ── Delete ───────────────────────────────────────────

    public function test_delete_subscriber_soft_deletes(): void
    {
        $subscriber = Subscriber::factory()->forAgreement($this->agreement)->create();

        $response = $this->deleteJson("/api/partner/subscribers/{$subscriber->id}", [], $this->authHeaders());

        $response->assertStatus(200);

        $this->assertSoftDeleted('subscribers', ['id' => $subscriber->id]);

        // Firestore delete dispatched
        Queue::assertPushed(SyncSubscriberToFirestore::class, function ($job) {
            return $job->action === 'delete';
        });
    }

    // ── Resend Invitation ────────────────────────────────

    public function test_resend_invitation_for_invited_status(): void
    {
        $subscriber = Subscriber::factory()->forAgreement($this->agreement)->create([
            'status' => 'invited',
        ]);

        $response = $this->postJson(
            "/api/partner/subscribers/{$subscriber->id}/resend-invitation",
            [],
            $this->authHeaders()
        );

        $response->assertStatus(200);
        Queue::assertPushed(SendSubscriberInvitation::class);
    }

    public function test_resend_invitation_fails_for_active_status(): void
    {
        $subscriber = Subscriber::factory()->forAgreement($this->agreement)->active()->create();

        $response = $this->postJson(
            "/api/partner/subscribers/{$subscriber->id}/resend-invitation",
            [],
            $this->authHeaders()
        );

        $response->assertStatus(422);
    }

    // ── Export ────────────────────────────────────────────

    public function test_export_subscribers(): void
    {
        Subscriber::factory()->forAgreement($this->agreement)->count(3)->create();

        $response = $this->getJson('/api/partner/subscribers/export', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure(['count', 'data']);
        $this->assertEquals(3, $response->json('count'));
    }
}
