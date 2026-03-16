<?php

namespace Tests;

use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected FirebaseService $firebaseMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock FirebaseService globally — no real Firebase calls in tests
        $this->firebaseMock = Mockery::mock(FirebaseService::class);
        $this->app->instance(FirebaseService::class, $this->firebaseMock);

        // Default: Firestore writes succeed silently
        $this->firebaseMock->shouldReceive('setDocument')->byDefault()->andReturnNull();
        $this->firebaseMock->shouldReceive('deleteDocument')->byDefault()->andReturnNull();
        $this->firebaseMock->shouldReceive('incrementField')->byDefault()->andReturnNull();

        // Set webhook secret for tests
        config(['services.engine_api_key' => 'test-secret-key']);
        config(['services.frontend_url' => 'https://test.sos-expat.com']);
        config(['services.telegram_engine.url' => null]); // Disable Telegram in tests
    }

    /**
     * Create webhook headers (X-Engine-Secret).
     */
    protected function webhookHeaders(): array
    {
        return [
            'X-Engine-Secret' => 'test-secret-key',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Simulate an authenticated partner request.
     * Mocks FirebaseAuth + RequirePartner middleware chain.
     */
    protected function actingAsPartner(string $uid = 'partner_test_uid', string $email = 'partner@test.com', array $partnerDoc = []): static
    {
        $this->firebaseMock->shouldReceive('verifyIdToken')
            ->andReturn(['uid' => $uid, 'email' => $email]);

        $defaultPartnerDoc = array_merge([
            'status' => 'active',
            'email' => $email,
            'companyName' => 'Test Partner Co',
        ], $partnerDoc);

        $this->firebaseMock->shouldReceive('getDocument')
            ->with('partners', $uid)
            ->andReturn($defaultPartnerDoc);

        return $this;
    }

    /**
     * Simulate an authenticated admin request.
     * Mocks FirebaseAuth + RequireAdmin middleware chain.
     */
    protected function actingAsAdmin(string $uid = 'admin_test_uid', string $email = 'admin@test.com'): static
    {
        $this->firebaseMock->shouldReceive('verifyIdToken')
            ->andReturn(['uid' => $uid, 'email' => $email]);

        $this->firebaseMock->shouldReceive('getDocument')
            ->with('users', $uid)
            ->andReturn(['role' => 'admin', 'email' => $email]);

        return $this;
    }

    /**
     * Simulate an authenticated subscriber request.
     * Mocks FirebaseAuth middleware (RequireSubscriber reads from DB, not Firestore).
     */
    protected function actingAsSubscriberUser(string $uid, string $email = 'subscriber@test.com'): static
    {
        $this->firebaseMock->shouldReceive('verifyIdToken')
            ->andReturn(['uid' => $uid, 'email' => $email]);

        return $this;
    }

    /**
     * Get default auth headers (Bearer token).
     */
    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer fake-firebase-token',
            'Accept' => 'application/json',
        ];
    }
}
