<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tests for the /mon-acces subscriber dashboard (Blade + magic link auth).
 */
class SubscriberDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_login_page_renders(): void
    {
        $response = $this->get('/mon-acces/login');
        $response->assertStatus(200);
        $response->assertSee('Mon accès SOS-Call');
    }

    public function test_login_page_accessible_at_root(): void
    {
        // If not authenticated, /mon-acces redirects to /mon-acces/login
        $response = $this->get('/mon-acces');
        $response->assertRedirect('/mon-acces/login');
    }

    public function test_magic_link_form_requires_email(): void
    {
        $response = $this->post('/mon-acces/magic-link', []);
        $response->assertSessionHasErrors(['email']);
    }

    public function test_magic_link_form_validates_email_format(): void
    {
        $response = $this->post('/mon-acces/magic-link', ['email' => 'not-an-email']);
        $response->assertSessionHasErrors(['email']);
    }

    public function test_magic_link_form_accepts_valid_email_even_if_unknown(): void
    {
        // Don't reveal whether email exists
        $response = $this->post('/mon-acces/magic-link', [
            'email' => 'unknown@nobody.com',
        ]);

        // Should show "email sent" page regardless
        $response->assertStatus(200);
        $response->assertSee('Vérifiez votre boîte mail');
    }

    public function test_auth_endpoint_rejects_invalid_token(): void
    {
        $response = $this->get('/mon-acces/auth?token=invalid');
        $response->assertRedirect('/mon-acces/login');
    }

    public function test_auth_endpoint_rejects_missing_token(): void
    {
        $response = $this->get('/mon-acces/auth');
        $response->assertRedirect('/mon-acces/login');
    }

    public function test_dashboard_requires_authenticated_session(): void
    {
        // Without a session, dashboard redirects to login
        $response = $this->get('/mon-acces');
        $response->assertRedirect('/mon-acces/login');
    }

    public function test_dashboard_renders_with_simulated_session(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_name' => 'AXA Test',
            'sos_call_active' => true,
        ]);

        $subscriber = Subscriber::factory()->create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'first_name' => 'Jean',
            'email' => 'jean@test.com',
            'sos_call_code' => 'AXA-2026-DASH1',
            'sos_call_activated_at' => now(),
            'status' => 'active',
        ]);

        $response = $this->withSession([
            'subscriber_id' => $subscriber->id,
            'subscriber_email' => $subscriber->email,
        ])->get('/mon-acces');

        $response->assertStatus(200);
        $response->assertSee('AXA-2026-DASH1');
        $response->assertSee('AXA Test');
        $response->assertSee('Jean');
    }

    public function test_logout_clears_session(): void
    {
        $agreement = Agreement::factory()->create();
        $subscriber = Subscriber::factory()->create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'sos_call_code' => 'LOG-2026-OUT01',
        ]);

        $response = $this->withSession([
            'subscriber_id' => $subscriber->id,
        ])->post('/mon-acces/logout');

        $response->assertRedirect('/mon-acces/login');
    }
}
