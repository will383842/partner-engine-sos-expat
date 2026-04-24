<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\PartnerApiKey;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerApiKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_key_rejects_missing_token(): void
    {
        $response = $this->postJson('/api/v1/partner/subscribers', ['email' => 'x@y.com']);
        $response->assertStatus(401);
        $response->assertJson(['error' => 'missing_api_key']);
    }

    public function test_api_key_rejects_invalid_token(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer pk_live_invalidtokenaaaaaaaaaaaaaaaaa'])
            ->postJson('/api/v1/partner/subscribers', ['email' => 'x@y.com']);
        $response->assertStatus(401);
        $response->assertJson(['error' => 'invalid_api_key']);
    }

    public function test_api_key_rejects_revoked_key(): void
    {
        Agreement::factory()->create(['partner_firebase_id' => 'partner_rev']);
        $gen = PartnerApiKey::generate('partner_rev', 'test key');
        $gen['key']->revoke('admin');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $gen['plain']])
            ->postJson('/api/v1/partner/subscribers', ['email' => 'x@y.com']);
        $response->assertStatus(401);
    }

    public function test_api_key_create_subscriber_success(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_api',
            'sos_call_active' => true,
            'partner_name' => 'TestCorp',
        ]);
        $gen = PartnerApiKey::generate('partner_api', 'test key');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $gen['plain']])
            ->postJson('/api/v1/partner/subscribers', [
                'email' => 'jane@test.com',
                'first_name' => 'Jane',
                'phone' => '+33612345678',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.email', 'jane@test.com');
        $response->assertJsonPath('data.partner_firebase_id', 'partner_api');
        $this->assertNotNull($response->json('data.sos_call_code'));
    }

    public function test_api_key_create_subscriber_with_custom_expires_at(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_exp',
            'sos_call_active' => true,
            'partner_name' => 'ExpCorp',
            'default_subscriber_duration_days' => 365,
        ]);
        $gen = PartnerApiKey::generate('partner_exp', 'test key');
        $customExpiresAt = now()->addDays(90)->toIso8601String();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $gen['plain']])
            ->postJson('/api/v1/partner/subscribers', [
                'email' => 'short@test.com',
                'expires_at' => $customExpiresAt,
            ]);

        $response->assertStatus(201);
        // sos_call_expires_at should be roughly 90 days from now, NOT 365 (default override)
        $expiresAt = \Carbon\Carbon::parse($response->json('data.sos_call_expires_at'));
        $this->assertTrue(
            $expiresAt->between(now()->addDays(89), now()->addDays(91)),
            'expires_at should be ~90 days, got ' . $expiresAt
        );
    }

    public function test_api_key_bulk_create_subscribers(): void
    {
        Agreement::factory()->create([
            'partner_firebase_id' => 'partner_bulk',
            'sos_call_active' => true,
            'partner_name' => 'BulkCo',
        ]);
        $gen = PartnerApiKey::generate('partner_bulk', 'bulk key');

        $subscribers = [];
        for ($i = 0; $i < 5; $i++) {
            $subscribers[] = [
                'email' => "bulk{$i}@test.com",
                'first_name' => "User{$i}",
                'phone' => '+3361234567' . $i,
            ];
        }

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $gen['plain']])
            ->postJson('/api/v1/partner/subscribers/bulk', ['subscribers' => $subscribers]);

        $response->assertStatus(207);
        $response->assertJsonPath('summary.total', 5);
        $response->assertJsonPath('summary.created', 5);
        $response->assertJsonPath('summary.failed', 0);
    }

    public function test_api_key_scoped_denies_write_on_read_only(): void
    {
        Agreement::factory()->create(['partner_firebase_id' => 'partner_scope']);
        $gen = PartnerApiKey::generate('partner_scope', 'readonly', 'live', 'subscribers:read');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $gen['plain']])
            ->postJson('/api/v1/partner/subscribers', ['email' => 'x@y.com']);
        $response->assertStatus(403);
        $response->assertJson(['error' => 'insufficient_scope']);
    }

    public function test_api_key_tracks_last_used(): void
    {
        Agreement::factory()->create(['partner_firebase_id' => 'partner_track']);
        $gen = PartnerApiKey::generate('partner_track', 'tracked');

        $this->assertNull($gen['key']->last_used_at);

        $this->withHeaders(['Authorization' => 'Bearer ' . $gen['plain']])
            ->getJson('/api/v1/partner/subscribers');

        $gen['key']->refresh();
        $this->assertNotNull($gen['key']->last_used_at);
    }

    public function test_api_key_partner_only_sees_own_subscribers(): void
    {
        $a1 = Agreement::factory()->create(['partner_firebase_id' => 'a1']);
        $a2 = Agreement::factory()->create(['partner_firebase_id' => 'a2']);
        Subscriber::factory()->create(['partner_firebase_id' => 'a1', 'agreement_id' => $a1->id]);
        Subscriber::factory()->create(['partner_firebase_id' => 'a2', 'agreement_id' => $a2->id]);

        $gen = PartnerApiKey::generate('a1', 'a1 key');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $gen['plain']])
            ->getJson('/api/v1/partner/subscribers');

        $response->assertOk();
        $emails = collect($response->json('data'))->pluck('partner_firebase_id')->unique();
        $this->assertEquals(['a1'], $emails->values()->toArray());
    }

    public function test_plain_token_format(): void
    {
        Agreement::factory()->create(['partner_firebase_id' => 'p_fmt']);
        $live = PartnerApiKey::generate('p_fmt', 'live', 'live');
        $test = PartnerApiKey::generate('p_fmt', 'test', 'test');

        $this->assertMatchesRegularExpression('/^pk_live_[A-Za-z0-9]{28}$/', $live['plain']);
        $this->assertMatchesRegularExpression('/^pk_test_[A-Za-z0-9]{28}$/', $test['plain']);
    }
}
