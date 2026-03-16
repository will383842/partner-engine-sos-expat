<?php

namespace Tests\Unit\Services;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use App\Services\SubscriberService;
use App\Jobs\SyncSubscriberToFirestore;
use App\Jobs\SendSubscriberInvitation;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubscriberServiceTest extends TestCase
{
    private SubscriberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->service = app(SubscriberService::class);
    }

    public function test_create_subscriber(): void
    {
        $agreement = Agreement::factory()->create(['partner_firebase_id' => 'p_svc']);

        $sub = $this->service->create('p_svc', [
            'email' => 'new@test.com',
            'first_name' => 'Jean',
        ], 'actor_uid', 'partner');

        $this->assertDatabaseHas('subscribers', [
            'email' => 'new@test.com',
            'status' => 'invited',
            'partner_firebase_id' => 'p_svc',
        ]);

        $this->assertNotNull($sub->invite_token);
        $this->assertEquals(64, strlen($sub->invite_token));

        // Activity logged
        $this->assertDatabaseHas('subscriber_activities', [
            'subscriber_id' => $sub->id,
            'type' => 'invitation_sent',
        ]);

        // Jobs dispatched
        Queue::assertPushed(SyncSubscriberToFirestore::class);
        Queue::assertPushed(SendSubscriberInvitation::class);

        // Audit
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'subscriber.created',
        ]);
    }

    public function test_create_subscriber_enforces_max_limit(): void
    {
        $agreement = Agreement::factory()->withMaxSubscribers(1)->create(['partner_firebase_id' => 'p_max']);
        Subscriber::factory()->forAgreement($agreement)->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Maximum subscriber limit reached');

        $this->service->create('p_max', ['email' => 'over@test.com'], 'actor', 'partner');
    }

    public function test_update_subscriber(): void
    {
        $sub = Subscriber::factory()->create();

        $updated = $this->service->update($sub, ['first_name' => 'Updated'], 'actor', 'partner');

        $this->assertEquals('Updated', $updated->first_name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscriber.updated']);
    }

    public function test_delete_subscriber(): void
    {
        $sub = Subscriber::factory()->create();

        $this->service->delete($sub, 'actor', 'partner');

        $this->assertSoftDeleted('subscribers', ['id' => $sub->id]);
        Queue::assertPushed(SyncSubscriberToFirestore::class, fn ($job) => $job->action === 'delete');
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscriber.deleted']);
    }

    public function test_suspend_subscriber(): void
    {
        $sub = Subscriber::factory()->active()->create();

        $suspended = $this->service->suspend($sub, 'actor', 'admin');

        $this->assertEquals('suspended', $suspended->status);
        Queue::assertPushed(SyncSubscriberToFirestore::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscriber.suspended']);
    }

    public function test_reactivate_subscriber(): void
    {
        $sub = Subscriber::factory()->suspended()->create();

        $reactivated = $this->service->reactivate($sub, 'actor', 'admin');

        $this->assertEquals('active', $reactivated->status);
        Queue::assertPushed(SyncSubscriberToFirestore::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscriber.reactivated']);
    }

    public function test_resend_invitation_only_for_invited(): void
    {
        $sub = Subscriber::factory()->create(['status' => 'invited']);

        $this->service->resendInvitation($sub, 'actor', 'partner');

        Queue::assertPushed(SendSubscriberInvitation::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscriber.invitation_resent']);
    }

    public function test_resend_invitation_fails_for_non_invited(): void
    {
        $sub = Subscriber::factory()->active()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Can only resend invitation to subscribers with status "invited"');

        $this->service->resendInvitation($sub, 'actor', 'partner');
    }
}
