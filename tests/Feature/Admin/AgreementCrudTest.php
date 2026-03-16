<?php

namespace Tests\Feature\Admin;

use App\Models\Agreement;
use App\Models\AuditLog;
use App\Models\Subscriber;
use App\Jobs\SyncSubscriberToFirestore;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgreementCrudTest extends TestCase
{
    private string $partnerId = 'partner_agr_test';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->actingAsAdmin();
    }

    public function test_create_agreement(): void
    {
        $response = $this->postJson("/api/admin/partners/{$this->partnerId}/agreements", [
            'name' => 'Accord Pro 2026',
            'status' => 'draft',
            'discount_type' => 'fixed',
            'discount_value' => 300,
            'discount_label' => '-$3',
            'commission_per_call_lawyer' => 500,
            'commission_per_call_expat' => 300,
            'commission_type' => 'fixed',
            'starts_at' => now()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
        ], $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Accord Pro 2026', 'status' => 'draft']);

        $this->assertDatabaseHas('agreements', [
            'partner_firebase_id' => $this->partnerId,
            'name' => 'Accord Pro 2026',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agreement.created',
            'resource_type' => 'agreement',
        ]);
    }

    public function test_create_agreement_with_percent_commission(): void
    {
        $response = $this->postJson("/api/admin/partners/{$this->partnerId}/agreements", [
            'name' => 'Accord Percent',
            'discount_type' => 'none',
            'commission_type' => 'percent',
            'commission_percent' => 15.50,
        ], $this->authHeaders());

        $response->assertStatus(201);

        $agreement = Agreement::where('name', 'Accord Percent')->first();
        $this->assertEquals('percent', $agreement->commission_type);
        $this->assertEquals('15.50', $agreement->commission_percent);
    }

    public function test_show_agreement_with_subscriber_count(): void
    {
        $agreement = Agreement::factory()->create(['partner_firebase_id' => $this->partnerId]);
        Subscriber::factory()->forAgreement($agreement)->count(5)->create();

        $response = $this->getJson(
            "/api/admin/partners/{$this->partnerId}/agreements/{$agreement->id}",
            $this->authHeaders()
        );

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $agreement->id]);
    }

    public function test_update_agreement(): void
    {
        $agreement = Agreement::factory()->create(['partner_firebase_id' => $this->partnerId]);

        $response = $this->putJson(
            "/api/admin/partners/{$this->partnerId}/agreements/{$agreement->id}",
            ['name' => 'Updated Name', 'discount_value' => 500],
            $this->authHeaders()
        );

        $response->assertStatus(200);

        $agreement->refresh();
        $this->assertEquals('Updated Name', $agreement->name);
        $this->assertEquals(500, $agreement->discount_value);
    }

    public function test_update_agreement_status_syncs_subscribers(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => $this->partnerId,
            'status' => 'active',
        ]);

        Subscriber::factory()->forAgreement($agreement)->count(3)->create();

        $response = $this->putJson(
            "/api/admin/partners/{$this->partnerId}/agreements/{$agreement->id}",
            ['status' => 'paused'],
            $this->authHeaders()
        );

        $response->assertStatus(200);

        // 3 from AgreementObserver + 3 from AgreementService.syncSubscribersOnStatusChange = 6
        Queue::assertPushed(SyncSubscriberToFirestore::class, 6);
    }

    public function test_update_agreement_to_expired_expires_subscribers(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => $this->partnerId,
            'status' => 'active',
        ]);

        $subscriber = Subscriber::factory()->forAgreement($agreement)->active()->create();

        $response = $this->putJson(
            "/api/admin/partners/{$this->partnerId}/agreements/{$agreement->id}",
            ['status' => 'expired'],
            $this->authHeaders()
        );

        $response->assertStatus(200);

        $subscriber->refresh();
        $this->assertEquals('expired', $subscriber->status);
    }

    public function test_delete_agreement_soft_deletes(): void
    {
        $agreement = Agreement::factory()->create(['partner_firebase_id' => $this->partnerId]);

        $response = $this->deleteJson(
            "/api/admin/partners/{$this->partnerId}/agreements/{$agreement->id}",
            [],
            $this->authHeaders()
        );

        $response->assertStatus(200);
        $this->assertSoftDeleted('agreements', ['id' => $agreement->id]);
    }

    public function test_renew_agreement(): void
    {
        $oldAgreement = Agreement::factory()->create([
            'partner_firebase_id' => $this->partnerId,
            'name' => 'Accord 2025',
            'commission_per_call_lawyer' => 500,
        ]);

        $subscriber = Subscriber::factory()->forAgreement($oldAgreement)->create();

        $response = $this->postJson(
            "/api/admin/partners/{$this->partnerId}/agreements/{$oldAgreement->id}/renew",
            [
                'name' => 'Accord 2026',
                'status' => 'active',
                'starts_at' => now()->toDateString(),
                'expires_at' => now()->addYear()->toDateString(),
            ],
            $this->authHeaders()
        );

        $response->assertStatus(201);

        // Old agreement expired
        $oldAgreement->refresh();
        $this->assertEquals('expired', $oldAgreement->status);

        // New agreement created
        $newAgreement = Agreement::where('name', 'Accord 2026')->first();
        $this->assertNotNull($newAgreement);
        $this->assertEquals(500, $newAgreement->commission_per_call_lawyer);

        // Subscriber migrated to new agreement
        $subscriber->refresh();
        $this->assertEquals($newAgreement->id, $subscriber->agreement_id);

        // Audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agreement.renewed',
        ]);
    }

    public function test_non_admin_cannot_access_agreement_endpoints(): void
    {
        // Create a fresh mock that returns non-admin role
        $freshMock = \Mockery::mock(\App\Services\FirebaseService::class);
        $this->app->instance(\App\Services\FirebaseService::class, $freshMock);

        $freshMock->shouldReceive('verifyIdToken')
            ->andReturn(['uid' => 'not_admin', 'email' => 'user@test.com']);
        $freshMock->shouldReceive('getDocument')
            ->with('users', 'not_admin')
            ->andReturn(['role' => 'client']);
        $freshMock->shouldReceive('setDocument')->byDefault()->andReturnNull();
        $freshMock->shouldReceive('deleteDocument')->byDefault()->andReturnNull();
        $freshMock->shouldReceive('incrementField')->byDefault()->andReturnNull();

        $response = $this->postJson("/api/admin/partners/x/agreements", [
            'name' => 'Hack',
            'discount_type' => 'none',
        ], $this->authHeaders());

        $response->assertStatus(403);
    }
}
