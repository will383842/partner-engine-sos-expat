<?php

namespace Tests\Unit\Services;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Services\AgreementService;
use App\Jobs\SyncSubscriberToFirestore;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgreementServiceTest extends TestCase
{
    private AgreementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->service = app(AgreementService::class);
    }

    public function test_create_agreement(): void
    {
        $agreement = $this->service->create('partner_svc', [
            'name' => 'Test Accord',
            'status' => 'draft',
            'discount_type' => 'fixed',
            'discount_value' => 300,
        ], 'admin_uid', 'admin', '127.0.0.1');

        $this->assertInstanceOf(Agreement::class, $agreement);
        $this->assertEquals('Test Accord', $agreement->name);
        $this->assertEquals('draft', $agreement->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agreement.created',
            'actor_firebase_id' => 'admin_uid',
        ]);
    }

    public function test_update_agreement_creates_audit_log(): void
    {
        $agreement = Agreement::factory()->create();

        $updated = $this->service->update($agreement, [
            'name' => 'Renamed',
        ], 'admin_uid', 'admin');

        $this->assertEquals('Renamed', $updated->name);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agreement.updated',
        ]);
    }

    public function test_update_agreement_status_syncs_subscribers(): void
    {
        $agreement = Agreement::factory()->create(['status' => 'active']);
        Subscriber::factory()->forAgreement($agreement)->count(3)->create();

        $this->service->update($agreement, ['status' => 'paused'], 'admin', 'admin');

        // 3 from AgreementObserver + 3 from AgreementService.syncSubscribersOnStatusChange = 6
        Queue::assertPushed(SyncSubscriberToFirestore::class, 6);
    }

    public function test_update_agreement_to_expired_expires_subscribers(): void
    {
        $agreement = Agreement::factory()->create(['status' => 'active']);
        $sub = Subscriber::factory()->forAgreement($agreement)->create(['status' => 'active']);

        $this->service->update($agreement, ['status' => 'expired'], 'admin', 'admin');

        $sub->refresh();
        $this->assertEquals('expired', $sub->status);
    }

    public function test_delete_agreement_soft_deletes(): void
    {
        $agreement = Agreement::factory()->create();

        $this->service->delete($agreement, 'admin', 'admin');

        $this->assertSoftDeleted('agreements', ['id' => $agreement->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'agreement.deleted']);
    }

    public function test_renew_agreement(): void
    {
        $old = Agreement::factory()->create([
            'name' => 'Old Accord',
            'commission_per_call_lawyer' => 500,
        ]);

        $sub = Subscriber::factory()->forAgreement($old)->create();

        $new = $this->service->renew($old, [
            'name' => 'New Accord',
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
        ], 'admin', 'admin');

        // Old expired
        $old->refresh();
        $this->assertEquals('expired', $old->status);

        // New created with copied commission
        $this->assertEquals('New Accord', $new->name);
        $this->assertEquals(500, $new->commission_per_call_lawyer);

        // Subscriber migrated
        $sub->refresh();
        $this->assertEquals($new->id, $sub->agreement_id);

        $this->assertDatabaseHas('audit_logs', ['action' => 'agreement.renewed']);
    }
}
