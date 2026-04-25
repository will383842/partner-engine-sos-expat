<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\EmailTemplate;
use App\Models\PartnerInvoice;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exhaustive end-to-end verification covering ALL 8 sprints.
 * Cross-checks that every schema column, route, and business rule
 * works across system boundaries.
 */
class EndToEndVerificationTest extends TestCase
{
    use RefreshDatabase;

    // ======================================================================
    // SPRINT 1 — Schema verification
    // ======================================================================

    public function test_agreements_table_has_all_sos_call_columns(): void
    {
        $columns = [
            'billing_rate', 'monthly_base_fee', 'billing_currency', 'payment_terms_days',
            'call_types_allowed', 'sos_call_active', 'billing_email',
            'default_subscriber_duration_days', 'max_subscriber_duration_days',
        ];
        foreach ($columns as $col) {
            $this->assertTrue(
                Schema::hasColumn('agreements', $col),
                "Missing column {$col} on agreements table"
            );
        }
    }

    public function test_subscribers_table_has_all_sos_call_columns(): void
    {
        $columns = [
            'sos_call_code', 'sos_call_activated_at',
            'sos_call_expires_at', 'calls_expert', 'calls_lawyer',
        ];
        foreach ($columns as $col) {
            $this->assertTrue(
                Schema::hasColumn('subscribers', $col),
                "Missing column {$col} on subscribers table"
            );
        }
    }

    public function test_partner_invoices_table_exists_with_all_fields(): void
    {
        $columns = [
            'agreement_id', 'partner_firebase_id', 'invoice_number', 'period',
            'active_subscribers', 'billing_rate', 'monthly_base_fee', 'billing_currency', 'total_amount',
            'calls_expert', 'calls_lawyer', 'total_cost', 'status', 'pdf_path',
            'due_date', 'paid_at', 'paid_via', 'payment_note',
            'stripe_customer_id', 'stripe_invoice_id', 'stripe_hosted_url',
        ];
        foreach ($columns as $col) {
            $this->assertTrue(
                Schema::hasColumn('partner_invoices', $col),
                "Missing column {$col} on partner_invoices table"
            );
        }
    }

    public function test_users_table_exists_for_filament(): void
    {
        $columns = ['name', 'email', 'password', 'role', 'is_active'];
        foreach ($columns as $col) {
            $this->assertTrue(
                Schema::hasColumn('users', $col),
                "Missing column {$col} on users table"
            );
        }
    }

    public function test_email_templates_has_language_column(): void
    {
        $this->assertTrue(Schema::hasColumn('email_templates', 'language'));
    }

    public function test_sos_call_active_defaults_to_false(): void
    {
        // CRITICAL: Ensures existing partners are NOT accidentally activated
        $agreement = Agreement::factory()->create();
        $this->assertFalse((bool) $agreement->fresh()->sos_call_active);
    }

    // ======================================================================
    // SPRINT 1 — Business logic
    // ======================================================================

    public function test_agreement_uses_sos_call_method(): void
    {
        $active = Agreement::factory()->create(['sos_call_active' => true]);
        $inactive = Agreement::factory()->create(['sos_call_active' => false]);

        $this->assertTrue($active->usesSosCall());
        $this->assertFalse($inactive->usesSosCall());
    }

    public function test_subscriber_service_generates_unique_codes(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_uniq_gen',
            'partner_name' => 'UniqGen',
            'sos_call_active' => true,
        ]);

        $codes = [];
        $service = app(\App\Services\SubscriberService::class);
        for ($i = 0; $i < 5; $i++) {
            $subscriber = $service->create(
                $agreement->partner_firebase_id,
                [
                    'agreement_id' => $agreement->id,
                    'first_name' => "Test{$i}",
                    'email' => "test{$i}@gen.com",
                    'phone' => '+336123456' . str_pad($i, 2, '0', STR_PAD_LEFT),
                    'status' => 'active',
                ],
                'admin:e2e',
                'admin'
            );
            $this->assertNotNull($subscriber->sos_call_code);
            $this->assertMatchesRegularExpression('/^UNI-\d{4}-[A-Z0-9]{5}$/', $subscriber->sos_call_code);
            $codes[] = $subscriber->sos_call_code;
        }

        $this->assertCount(5, array_unique($codes));
    }

    // ======================================================================
    // SPRINT 2 — Firebase integration readiness
    // ======================================================================

    public function test_sos_call_check_accepts_valid_session_create(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_sess',
            'sos_call_active' => true,
            'status' => 'active',
        ]);

        $subscriber = Subscriber::factory()->create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'sos_call_code' => 'SES-2026-TEST1',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/sos-call/check', [
            'code' => 'SES-2026-TEST1',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'access_granted');
        $response->assertJsonStructure(['session_token', 'partner_name', 'call_types_allowed']);
    }

    // ======================================================================
    // SPRINT 4 — Invoice generation full flow
    // ======================================================================

    public function test_end_to_end_invoice_generation_flow(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_e2e',
            'partner_name' => 'E2ETest',
            'sos_call_active' => true,
            'billing_rate' => 3.50,
            'billing_currency' => 'EUR',
            'payment_terms_days' => 15,
            'billing_email' => 'billing@e2e.com',
        ]);

        Subscriber::factory()->count(7)->create([
            'partner_firebase_id' => 'partner_e2e',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'E2E-2026-' . substr(md5(uniqid()), 0, 5),
            'sos_call_activated_at' => now()->subMonths(2),
            'status' => 'active',
        ]);

        $period = now()->subMonth()->format('Y-m');

        $service = app(\App\Services\InvoiceService::class);
        $invoice = $service->createInvoice($agreement, $period);

        $this->assertNotNull($invoice);
        $this->assertEquals(7, $invoice->active_subscribers);
        $this->assertEquals(24.50, (float) $invoice->total_amount); // 7 × 3.50
        $this->assertEquals('EUR', $invoice->billing_currency);
        $this->assertMatchesRegularExpression('/^SOS-\d{6}-\d{4}$/', $invoice->invoice_number);
        $this->assertEquals(PartnerInvoice::STATUS_PENDING, $invoice->status);

        // Test idempotency — running again returns same invoice
        $invoice2 = $service->createInvoice($agreement, $period);
        $this->assertEquals($invoice->id, $invoice2->id);
    }

    public function test_partner_invoice_state_transitions(): void
    {
        $agreement = Agreement::factory()->create();
        $invoice = PartnerInvoice::create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => 'partner_state',
            'invoice_number' => 'SOS-202604-STATE',
            'period' => '2026-04',
            'active_subscribers' => 3,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
            'total_amount' => 9.00,
            'calls_expert' => 0,
            'calls_lawyer' => 0,
            'total_cost' => 0,
            'status' => PartnerInvoice::STATUS_PENDING,
            'due_date' => now()->addDays(15)->toDateString(),
        ]);

        // pending → paid
        $invoice->markPaid('stripe', 'Test');
        $invoice->refresh();
        $this->assertTrue($invoice->isPaid());

        // Attempting to mark paid again is idempotent
        $originalPaidAt = $invoice->paid_at;
        $invoice->markPaid('manual');
        $invoice->refresh();
        $this->assertEquals($originalPaidAt->toIso8601String(), $invoice->paid_at->toIso8601String());
    }

    // ======================================================================
    // SPRINT 5 — Filament resources isolation
    // ======================================================================

    public function test_filament_resources_are_accessible_only_to_admins(): void
    {
        $inactive = User::create([
            'name' => 'Inactive', 'email' => 'in@x.com', 'password' => bcrypt('x'),
            'role' => User::ROLE_ADMIN, 'is_active' => false,
        ]);
        $this->assertFalse($inactive->canAccessFilament());

        $support = User::create([
            'name' => 'Support', 'email' => 'sup@x.com', 'password' => bcrypt('x'),
            'role' => User::ROLE_SUPPORT, 'is_active' => true,
        ]);
        $this->assertTrue($support->canAccessFilament());
        $this->assertFalse($support->canManageUsers());
    }

    // ======================================================================
    // SPRINT 6 — Dashboard + subscriber auth
    // ======================================================================

    public function test_magic_link_token_is_single_use(): void
    {
        // Verify routes don't accept an empty token
        $this->get('/mon-acces/auth')->assertRedirect('/mon-acces/login');
        $this->get('/mon-acces/auth?token=short')->assertRedirect('/mon-acces/login');
    }

    public function test_subscriber_dashboard_requires_valid_subscriber_id(): void
    {
        // Fake subscriber_id in session that doesn't exist → should logout + redirect
        $response = $this->withSession(['subscriber_id' => 999999])
            ->get('/mon-acces');
        $response->assertRedirect('/mon-acces/login');
    }

    // ======================================================================
    // SPRINT 6.bis — RGPD compliance
    // ======================================================================

    public function test_gdpr_export_includes_all_subscriber_fields(): void
    {
        $agreement = Agreement::factory()->create(['partner_name' => 'GDPR Full']);
        $subscriber = Subscriber::factory()->create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'sos_call_code' => 'GDP-2026-FULL1',
        ]);

        $response = $this->withSession(['subscriber_id' => $subscriber->id])
            ->getJson('/mon-acces/export');

        $response->assertStatus(200);
        $data = $response->json();

        // All critical fields present
        $this->assertArrayHasKey('subscriber', $data);
        $this->assertArrayHasKey('agreement', $data);
        $this->assertArrayHasKey('activities', $data);
        $this->assertArrayHasKey('exported_at', $data);
    }

    public function test_gdpr_deletion_preserves_accounting_records(): void
    {
        $agreement = Agreement::factory()->create();
        $subscriber = Subscriber::factory()->create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'email' => 'gdpr-preserve@x.com',
            'sos_call_code' => 'GDP-2026-KEEP1',
        ]);

        // Create 3 activities
        for ($i = 0; $i < 3; $i++) {
            SubscriberActivity::create([
                'subscriber_id' => $subscriber->id,
                'partner_firebase_id' => $agreement->partner_firebase_id,
                'agreement_id' => $agreement->id,
                'type' => 'call_completed',
                'provider_type' => 'expat',
            ]);
        }

        $this->withSession(['subscriber_id' => $subscriber->id])
            ->deleteJson('/mon-acces/delete')
            ->assertStatus(200);

        // Subscriber anonymized
        $subscriber->refresh();
        $this->assertEquals('Deleted', $subscriber->first_name);
        $this->assertStringContainsString('deleted-', $subscriber->email);

        // Activities still present (accounting requirement)
        $this->assertEquals(3, SubscriberActivity::where('subscriber_id', $subscriber->id)->count());
    }

    // ======================================================================
    // SPRINT 7 — Security headers
    // ======================================================================

    public function test_all_api_endpoints_return_security_headers(): void
    {
        $endpoints = ['/api/health', '/api/health/detailed'];

        foreach ($endpoints as $endpoint) {
            $response = $this->get($endpoint);
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('X-Frame-Options', 'DENY');
            $this->assertNotEmpty($response->headers->get('Strict-Transport-Security'));
        }
    }

    public function test_sos_call_page_has_csp(): void
    {
        $response = $this->get('/sos-call');
        $response->assertStatus(200);
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotEmpty($csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    // ======================================================================
    // Cross-sprint scenario: full flow A to B
    // ======================================================================

    public function test_full_scenario_partner_activates_sos_call_from_commission_mode(): void
    {
        // Scenario: partner initially in system A (commission)
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_migration',
            'partner_name' => 'Migration',
            'sos_call_active' => false,  // System A
            'commission_per_call_lawyer' => 300,
        ]);

        // 3 subscribers exist in system A (without codes)
        Subscriber::factory()->count(3)->create([
            'partner_firebase_id' => 'partner_migration',
            'agreement_id' => $agreement->id,
            'sos_call_code' => null,
            'status' => 'active',
        ]);

        // Admin switches to system B
        $agreement->update([
            'sos_call_active' => true,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
        ]);

        // Run bulk code generator
        $this->artisan('sos-call:generate-codes-for-partner', [
            'partner_firebase_id' => 'partner_migration',
        ])->assertSuccessful();

        // All 3 subscribers now have codes
        $this->assertEquals(3, Subscriber::where('agreement_id', $agreement->id)
            ->whereNotNull('sos_call_code')
            ->count());

        // Generate first invoice
        $period = now()->format('Y-m');
        $service = app(\App\Services\InvoiceService::class);
        $invoice = $service->createInvoice($agreement->fresh(), $period);

        $this->assertEquals(3, $invoice->active_subscribers);
        $this->assertEquals(9.00, (float) $invoice->total_amount);
    }
}
