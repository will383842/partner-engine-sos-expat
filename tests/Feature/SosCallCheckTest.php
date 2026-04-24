<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SosCallCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // --- /sos-call/check by code ---

    public function test_check_with_valid_code_returns_access_granted(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_axa',
            'partner_name' => 'AXA Expatriate',
            'status' => 'active',
            'sos_call_active' => true,
            'call_types_allowed' => 'both',
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
        ]);

        $subscriber = Subscriber::factory()->create([
            'partner_firebase_id' => 'partner_axa',
            'agreement_id' => $agreement->id,
            'email' => 'jean@example.com',
            'phone' => '+33612345678',
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'sos_call_code' => 'AXA-2026-X7K2P',
            'sos_call_activated_at' => now(),
            'sos_call_expires_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/sos-call/check', [
            'code' => 'AXA-2026-X7K2P',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'session_token',
                'partner_name',
                'call_types_allowed',
                'first_name',
            ])
            ->assertJson([
                'status' => 'access_granted',
                'partner_name' => 'AXA Expatriate',
                'call_types_allowed' => 'both',
                'first_name' => 'Jean',
            ]);

        $sessionToken = $response->json('session_token');
        $this->assertNotNull($sessionToken);
        $this->assertEquals(32, strlen($sessionToken));

        // Verify session was stored in cache
        $session = Cache::get("sos_call:session:{$sessionToken}");
        $this->assertNotNull($session);
        $this->assertEquals($subscriber->id, $session['subscriber_id']);
        $this->assertFalse($session['used']);
    }

    public function test_check_with_invalid_code_returns_not_found(): void
    {
        $response = $this->postJson('/api/sos-call/check', [
            'code' => 'INVALID-CODE-XXX',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'not_found']);
    }

    public function test_check_with_expired_subscriber_returns_expired(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_axa',
            'status' => 'active',
            'sos_call_active' => true,
        ]);

        Subscriber::factory()->create([
            'partner_firebase_id' => 'partner_axa',
            'agreement_id' => $agreement->id,
            'sos_call_code' => 'AXA-2026-EXPIR',
            'sos_call_expires_at' => now()->subDay(),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/sos-call/check', [
            'code' => 'AXA-2026-EXPIR',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'expired']);
    }

    public function test_check_with_sos_call_inactive_agreement(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_axa',
            'status' => 'active',
            'sos_call_active' => false, // ← Disabled
        ]);

        Subscriber::factory()->create([
            'partner_firebase_id' => 'partner_axa',
            'agreement_id' => $agreement->id,
            'sos_call_code' => 'AXA-2026-OFFS0',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/sos-call/check', [
            'code' => 'AXA-2026-OFFS0',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'agreement_inactive']);
    }

    // --- /sos-call/check by phone+email fallback ---

    public function test_check_with_phone_email_exact_match(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_axa',
            'partner_name' => 'AXA',
            'status' => 'active',
            'sos_call_active' => true,
            'call_types_allowed' => 'both',
        ]);

        Subscriber::factory()->create([
            'partner_firebase_id' => 'partner_axa',
            'agreement_id' => $agreement->id,
            'email' => 'jean@example.com',
            'phone' => '+33612345678',
            'sos_call_code' => 'AXA-2026-PHONE',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/sos-call/check', [
            'phone' => '+33612345678',
            'email' => 'jean@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'access_granted']);
    }

    public function test_check_phone_matches_but_email_mismatch(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_axa',
            'partner_name' => 'AXA Expatriate',
            'status' => 'active',
            'sos_call_active' => true,
        ]);

        Subscriber::factory()->create([
            'partner_firebase_id' => 'partner_axa',
            'agreement_id' => $agreement->id,
            'email' => 'jean@example.com',
            'phone' => '+33612345678',
            'sos_call_code' => 'AXA-2026-MISMT',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/sos-call/check', [
            'phone' => '+33612345678',
            'email' => 'wrong@email.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'partner_name', 'attempts_remaining'])
            ->assertJson([
                'status' => 'phone_match_email_mismatch',
                'partner_name' => 'AXA Expatriate',
            ]);
    }

    // --- Rate limiting ---

    public function test_check_blocks_after_max_attempts(): void
    {
        // 3 failed attempts with the same non-existent code
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/sos-call/check', [
                'code' => 'NONEXISTENT-CODE',
            ]);
        }

        // 4th attempt should be rate limited
        $response = $this->postJson('/api/sos-call/check', [
            'code' => 'NONEXISTENT-CODE',
        ]);

        $response->assertStatus(429)
            ->assertJson(['status' => 'rate_limited']);
    }

    // --- /sos-call/check-session ---

    public function test_check_session_validates_valid_token(): void
    {
        $sessionToken = bin2hex(random_bytes(16));
        Cache::put("sos_call:session:{$sessionToken}", [
            'subscriber_id' => 1,
            'partner_firebase_id' => 'partner_axa',
            'agreement_id' => 1,
            'call_types_allowed' => 'both',
            'used' => false,
            'created_at' => now()->timestamp,
        ], 900);

        $response = $this->postJson('/api/sos-call/check-session', [
            'session_token' => $sessionToken,
            'call_type' => 'lawyer',
        ], [
            'X-Engine-Secret' => config('services.engine_api_key'),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'valid' => true,
                'subscriber_id' => 1,
                'partner_firebase_id' => 'partner_axa',
            ]);
    }

    public function test_check_session_rejects_expired_token(): void
    {
        $response = $this->postJson('/api/sos-call/check-session', [
            'session_token' => str_repeat('a', 32),
            'call_type' => 'lawyer',
        ], [
            'X-Engine-Secret' => config('services.engine_api_key'),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'valid' => false,
                'reason' => 'session_not_found_or_expired',
            ]);
    }

    public function test_check_session_rejects_disallowed_call_type(): void
    {
        $sessionToken = bin2hex(random_bytes(16));
        Cache::put("sos_call:session:{$sessionToken}", [
            'subscriber_id' => 1,
            'partner_firebase_id' => 'partner_axa',
            'agreement_id' => 1,
            'call_types_allowed' => 'expat_only',
            'used' => false,
            'created_at' => now()->timestamp,
        ], 900);

        $response = $this->postJson('/api/sos-call/check-session', [
            'session_token' => $sessionToken,
            'call_type' => 'lawyer',
        ], [
            'X-Engine-Secret' => config('services.engine_api_key'),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'valid' => false,
                'reason' => 'call_type_not_allowed',
            ]);
    }

    // --- /sos-call/log ---

    public function test_log_increments_subscriber_counters(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_axa',
            'status' => 'active',
            'sos_call_active' => true,
        ]);

        $subscriber = Subscriber::factory()->create([
            'partner_firebase_id' => 'partner_axa',
            'agreement_id' => $agreement->id,
            'sos_call_code' => 'AXA-2026-LOG01',
            'calls_expert' => 0,
            'calls_lawyer' => 0,
            'total_calls' => 0,
            'status' => 'active',
        ]);

        $sessionToken = bin2hex(random_bytes(16));
        Cache::put("sos_call:session:{$sessionToken}", [
            'subscriber_id' => $subscriber->id,
            'partner_firebase_id' => 'partner_axa',
            'agreement_id' => $agreement->id,
            'call_types_allowed' => 'both',
            'used' => false,
            'created_at' => now()->timestamp,
        ], 900);

        $response = $this->postJson('/api/sos-call/log', [
            'session_token' => $sessionToken,
            'call_session_id' => 'call-sess-test-001',
            'call_type' => 'lawyer',
            'duration_seconds' => 300,
        ], [
            'X-Engine-Secret' => config('services.engine_api_key'),
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $subscriber->refresh();
        $this->assertEquals(1, $subscriber->calls_lawyer);
        $this->assertEquals(0, $subscriber->calls_expert);
        $this->assertEquals(1, $subscriber->total_calls);

        // Session should now be marked as used
        $session = Cache::get("sos_call:session:{$sessionToken}");
        $this->assertTrue($session['used']);

        // Activity should be logged
        $this->assertDatabaseHas('subscriber_activities', [
            'subscriber_id' => $subscriber->id,
            'call_session_id' => 'call-sess-test-001',
            'provider_type' => 'lawyer',
            'call_duration_seconds' => 300,
            'amount_paid_cents' => 0,
        ]);
    }

    public function test_log_rejects_already_used_session(): void
    {
        $sessionToken = bin2hex(random_bytes(16));
        Cache::put("sos_call:session:{$sessionToken}", [
            'subscriber_id' => 1,
            'partner_firebase_id' => 'partner_axa',
            'agreement_id' => 1,
            'call_types_allowed' => 'both',
            'used' => true, // ← Already used
            'created_at' => now()->timestamp,
        ], 900);

        $response = $this->postJson('/api/sos-call/log', [
            'session_token' => $sessionToken,
            'call_session_id' => 'call-sess-test-002',
            'call_type' => 'lawyer',
        ], [
            'X-Engine-Secret' => config('services.engine_api_key'),
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'reason' => 'session_not_found_or_used',
            ]);
    }

    // --- Webhook security ---

    public function test_check_session_requires_engine_secret(): void
    {
        $response = $this->postJson('/api/sos-call/check-session', [
            'session_token' => str_repeat('a', 32),
            'call_type' => 'lawyer',
        ]);

        // Without X-Engine-Secret header, middleware should block
        $response->assertStatus(401);
    }

    public function test_log_requires_engine_secret(): void
    {
        $response = $this->postJson('/api/sos-call/log', [
            'session_token' => str_repeat('a', 32),
            'call_session_id' => 'call-sess-test-003',
            'call_type' => 'lawyer',
        ]);

        $response->assertStatus(401);
    }
}
