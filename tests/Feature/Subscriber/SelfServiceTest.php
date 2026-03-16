<?php

namespace Tests\Feature\Subscriber;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Tests\TestCase;

class SelfServiceTest extends TestCase
{
    private Subscriber $subscriber;
    private string $subscriberUid = 'sub_uid_selftest';

    protected function setUp(): void
    {
        parent::setUp();

        $agreement = Agreement::factory()->create([
            'discount_type' => 'fixed',
            'discount_value' => 300,
            'discount_label' => '-$3 sur chaque appel',
        ]);

        $this->subscriber = Subscriber::factory()->forAgreement($agreement)->registered($this->subscriberUid)->create();

        $this->actingAsSubscriberUser($this->subscriberUid);
    }

    public function test_subscriber_me_returns_profile(): void
    {
        $response = $this->getJson('/api/subscriber/me', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonFragment([
                'email' => $this->subscriber->email,
                'status' => 'registered',
            ]);
    }

    public function test_subscriber_sees_own_activity_only(): void
    {
        // Activity for this subscriber
        SubscriberActivity::factory()->create([
            'subscriber_id' => $this->subscriber->id,
            'partner_firebase_id' => $this->subscriber->partner_firebase_id,
        ]);

        // Activity for another subscriber
        $otherSub = Subscriber::factory()->active('other_uid')->create();
        SubscriberActivity::factory()->create([
            'subscriber_id' => $otherSub->id,
            'partner_firebase_id' => $otherSub->partner_firebase_id,
        ]);

        $response = $this->getJson('/api/subscriber/activity', $this->authHeaders());

        $response->assertStatus(200);
        $activities = $response->json('data');
        foreach ($activities as $activity) {
            $this->assertEquals($this->subscriber->id, $activity['subscriber_id']);
        }
    }

    public function test_subscriber_cannot_access_partner_endpoints(): void
    {
        // Subscriber token should not pass RequirePartner middleware
        $this->firebaseMock->shouldReceive('getDocument')
            ->with('partners', $this->subscriberUid)
            ->andReturn(null);

        $response = $this->getJson('/api/partner/dashboard', $this->authHeaders());
        $response->assertStatus(403);
    }
}
