<?php

namespace Tests\Unit\Commands;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Jobs\SyncSubscriberToFirestore;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExpireAgreementsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_expires_overdue_agreements(): void
    {
        $expired = Agreement::factory()->alreadyExpired()->create();
        $active = Agreement::factory()->create(['expires_at' => now()->addMonth()]);

        $this->artisan('agreements:expire')
            ->assertSuccessful();

        $expired->refresh();
        $active->refresh();

        $this->assertEquals('expired', $expired->status);
        $this->assertEquals('active', $active->status);
    }

    public function test_expires_linked_subscribers(): void
    {
        $agreement = Agreement::factory()->alreadyExpired()->create();
        $sub1 = Subscriber::factory()->forAgreement($agreement)->create(['status' => 'active']);
        $sub2 = Subscriber::factory()->forAgreement($agreement)->create(['status' => 'invited']);

        $this->artisan('agreements:expire')
            ->assertSuccessful();

        $sub1->refresh();
        $sub2->refresh();

        $this->assertEquals('expired', $sub1->status);
        $this->assertEquals('expired', $sub2->status);
    }

    public function test_syncs_expired_subscribers_to_firestore(): void
    {
        $agreement = Agreement::factory()->alreadyExpired()->create();
        Subscriber::factory()->forAgreement($agreement)->count(3)->create(['status' => 'active']);

        $this->artisan('agreements:expire')
            ->assertSuccessful();

        // 3 from AgreementObserver (status change) + 3 from SubscriberObserver (status change) + 3 explicit = 9
        Queue::assertPushed(SyncSubscriberToFirestore::class, 9);
    }

    public function test_does_not_touch_already_expired_agreements(): void
    {
        $alreadyExpired = Agreement::factory()->expired()->create();

        $this->artisan('agreements:expire')
            ->assertSuccessful();

        // Should not process already-expired agreements (they have status='expired', not 'active')
        $alreadyExpired->refresh();
        $this->assertEquals('expired', $alreadyExpired->status);
    }

    public function test_does_not_expire_agreements_without_expires_at(): void
    {
        $noExpiry = Agreement::factory()->create(['expires_at' => null]);

        $this->artisan('agreements:expire')
            ->assertSuccessful();

        $noExpiry->refresh();
        $this->assertEquals('active', $noExpiry->status);
    }
}
