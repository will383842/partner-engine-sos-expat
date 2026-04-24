<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\AuditLog;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for RGPD/GDPR endpoints — Article 15 (data access) + Article 17 (right to be forgotten).
 */
class SubscriberGdprTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_requires_authenticated_session(): void
    {
        $response = $this->get('/mon-acces/export');
        $response->assertStatus(401);
        $response->assertJson(['error' => 'not_authenticated']);
    }

    public function test_export_returns_full_data_for_authenticated_subscriber(): void
    {
        $agreement = Agreement::factory()->create(['partner_name' => 'GDPR Test']);
        $subscriber = Subscriber::factory()->create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'first_name' => 'Alice',
            'email' => 'alice@gdpr.com',
            'sos_call_code' => 'GDP-2026-ALI01',
        ]);

        SubscriberActivity::create([
            'subscriber_id' => $subscriber->id,
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'agreement_id' => $agreement->id,
            'type' => 'call_completed',
            'provider_type' => 'lawyer',
            'duration_seconds' => 320,
        ]);

        $response = $this->withSession([
            'subscriber_id' => $subscriber->id,
        ])->get('/mon-acces/export');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'exported_at',
            'subscriber' => ['id', 'email', 'sos_call_code'],
            'agreement' => ['partner_name'],
            'activities' => [['type', 'provider_type']],
        ]);

        $response->assertJsonPath('subscriber.email', 'alice@gdpr.com');
        $response->assertJsonPath('agreement.partner_name', 'GDPR Test');
    }

    public function test_export_is_logged_in_audit(): void
    {
        $agreement = Agreement::factory()->create();
        $subscriber = Subscriber::factory()->create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'sos_call_code' => 'AUD-2026-LOG01',
        ]);

        $this->withSession([
            'subscriber_id' => $subscriber->id,
        ])->get('/mon-acces/export');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'gdpr_data_export',
            'resource_type' => 'subscriber',
            'resource_id' => (string) $subscriber->id,
        ]);
    }

    public function test_delete_anonymizes_subscriber_data(): void
    {
        $agreement = Agreement::factory()->create();
        $subscriber = Subscriber::factory()->create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+33612345678',
            'sos_call_code' => 'DEL-2026-JOH01',
            'status' => 'active',
        ]);

        $response = $this->withSession([
            'subscriber_id' => $subscriber->id,
        ])->delete('/mon-acces/delete');

        $response->assertStatus(200);
        $response->assertJson(['deleted' => true]);

        $subscriber->refresh();
        $this->assertEquals('Deleted', $subscriber->first_name);
        $this->assertEquals('User', $subscriber->last_name);
        $this->assertEquals("deleted-{$subscriber->id}@deleted.local", $subscriber->email);
        $this->assertNull($subscriber->phone);
        $this->assertEquals('suspended', $subscriber->status);
    }

    public function test_delete_logs_audit_entry(): void
    {
        $agreement = Agreement::factory()->create();
        $subscriber = Subscriber::factory()->create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'email' => 'audit@gdpr.com',
            'sos_call_code' => 'AUD-2026-DEL01',
        ]);

        $this->withSession([
            'subscriber_id' => $subscriber->id,
        ])->delete('/mon-acces/delete');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'gdpr_data_deletion',
            'resource_id' => (string) $subscriber->id,
        ]);
    }

    public function test_delete_keeps_activities_for_audit(): void
    {
        $agreement = Agreement::factory()->create();
        $subscriber = Subscriber::factory()->create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'sos_call_code' => 'KEE-2026-ACT01',
        ]);

        SubscriberActivity::create([
            'subscriber_id' => $subscriber->id,
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'agreement_id' => $agreement->id,
            'type' => 'call_completed',
            'provider_type' => 'expat',
        ]);

        $this->withSession([
            'subscriber_id' => $subscriber->id,
        ])->delete('/mon-acces/delete');

        // Activities MUST be preserved for accounting compliance
        $this->assertEquals(1, SubscriberActivity::where('subscriber_id', $subscriber->id)->count());
    }
}
