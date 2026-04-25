<?php

namespace Tests\Unit\Services;

use App\Models\Agreement;
use App\Models\PartnerInvoice;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Unit tests for InvoiceService — calculateInvoiceData, generateInvoiceNumber,
 * createInvoice idempotency, and graceful degradation without dompdf/Stripe SDK.
 */
class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvoiceService();
    }

    public function test_calculates_active_subscribers_correctly(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_calc',
            'sos_call_active' => true,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
        ]);

        // Create 5 active subscribers with codes, activated last month
        Subscriber::factory()->count(5)->create([
            'partner_firebase_id' => 'partner_calc',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'CAL-2026-' . substr(md5(uniqid()), 0, 5),
            'sos_call_activated_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        $period = now()->subMonth()->format('Y-m');
        $data = $this->service->calculateInvoiceData($agreement, $period);

        $this->assertEquals(5, $data['active_subscribers']);
        $this->assertEquals(3.00, $data['billing_rate']);
        $this->assertEquals('EUR', $data['billing_currency']);
        $this->assertEquals(15.00, $data['total_amount']);
        $this->assertEquals(0, $data['calls_expert']);
        $this->assertEquals(0, $data['calls_lawyer']);
    }

    public function test_excludes_subscribers_without_sos_call_code(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_nocode',
            'sos_call_active' => true,
            'billing_rate' => 5.00,
        ]);

        // 3 with code, 2 without
        Subscriber::factory()->count(3)->create([
            'partner_firebase_id' => 'partner_nocode',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'NOC-2026-' . substr(md5(uniqid()), 0, 5),
            'sos_call_activated_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        Subscriber::factory()->count(2)->create([
            'partner_firebase_id' => 'partner_nocode',
            'agreement_id' => $agreement->id,
            'sos_call_code' => null,
            'status' => 'active',
        ]);

        $data = $this->service->calculateInvoiceData(
            $agreement,
            now()->subMonth()->format('Y-m')
        );

        $this->assertEquals(3, $data['active_subscribers']);
        $this->assertEquals(15.00, $data['total_amount']); // 3 × 5.00
    }

    public function test_excludes_expired_subscribers(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_exp',
            'sos_call_active' => true,
            'billing_rate' => 3.00,
        ]);

        // 2 active (no expiration), 1 expired before period start
        Subscriber::factory()->count(2)->create([
            'partner_firebase_id' => 'partner_exp',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'EXP-2026-' . substr(md5(uniqid()), 0, 5),
            'sos_call_activated_at' => now()->subMonths(2),
            'sos_call_expires_at' => null,
            'status' => 'active',
        ]);

        Subscriber::factory()->create([
            'partner_firebase_id' => 'partner_exp',
            'agreement_id' => $agreement->id,
            'sos_call_code' => 'EXP-2026-OLD01',
            'sos_call_activated_at' => now()->subMonths(3),
            'sos_call_expires_at' => now()->subMonths(2), // Expired before period
            'status' => 'active',
        ]);

        $data = $this->service->calculateInvoiceData(
            $agreement,
            now()->subMonth()->format('Y-m')
        );

        $this->assertEquals(2, $data['active_subscribers']);
    }

    public function test_generates_sequential_invoice_numbers_for_period(): void
    {
        $period = '2026-04';
        $expected1 = 'SOS-202604-0001';
        $expected2 = 'SOS-202604-0002';

        $num1 = $this->service->generateInvoiceNumber($period);
        $this->assertEquals($expected1, $num1);

        // Simulate first invoice created
        $agreement = Agreement::factory()->create();
        PartnerInvoice::create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => 'p1',
            'invoice_number' => $expected1,
            'period' => $period,
            'active_subscribers' => 1,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
            'total_amount' => 3.00,
            'calls_expert' => 0,
            'calls_lawyer' => 0,
            'total_cost' => 0,
            'status' => PartnerInvoice::STATUS_PENDING,
            'due_date' => now()->addDays(15)->toDateString(),
        ]);

        $num2 = $this->service->generateInvoiceNumber($period);
        $this->assertEquals($expected2, $num2);
    }

    public function test_invoice_numbers_reset_per_period(): void
    {
        $agreement = Agreement::factory()->create();

        // Existing invoice in April
        PartnerInvoice::create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => 'p1',
            'invoice_number' => 'SOS-202604-0001',
            'period' => '2026-04',
            'active_subscribers' => 1,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
            'total_amount' => 3.00,
            'calls_expert' => 0,
            'calls_lawyer' => 0,
            'total_cost' => 0,
            'status' => PartnerInvoice::STATUS_PENDING,
            'due_date' => now()->addDays(15)->toDateString(),
        ]);

        // May should start fresh at 0001
        $mayNum = $this->service->generateInvoiceNumber('2026-05');
        $this->assertEquals('SOS-202605-0001', $mayNum);
    }

    public function test_create_invoice_is_idempotent(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_idem',
            'sos_call_active' => true,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
            'payment_terms_days' => 15,
        ]);

        Subscriber::factory()->create([
            'partner_firebase_id' => 'partner_idem',
            'agreement_id' => $agreement->id,
            'sos_call_code' => 'IDE-2026-ONE01',
            'sos_call_activated_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        $period = now()->subMonth()->format('Y-m');

        $first = $this->service->createInvoice($agreement, $period);
        $second = $this->service->createInvoice($agreement, $period);

        $this->assertEquals($first->id, $second->id);
        $this->assertDatabaseCount('partner_invoices', 1);
    }

    public function test_create_invoice_sets_due_date_from_payment_terms(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_due',
            'sos_call_active' => true,
            'billing_rate' => 3.00,
            'payment_terms_days' => 30,
        ]);

        Subscriber::factory()->create([
            'partner_firebase_id' => 'partner_due',
            'agreement_id' => $agreement->id,
            'sos_call_code' => 'DUE-2026-ONE01',
            'sos_call_activated_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        $period = now()->subMonth()->format('Y-m');
        $invoice = $this->service->createInvoice($agreement, $period);

        $expectedDueDate = now()->addDays(30)->toDateString();
        $actualDueDate = $invoice->due_date instanceof \DateTimeInterface
            ? $invoice->due_date->format('Y-m-d')
            : (string) $invoice->due_date;
        $this->assertEquals($expectedDueDate, $actualDueDate);
    }

    public function test_generate_pdf_returns_null_when_dompdf_not_installed(): void
    {
        // dompdf is NOT installed in test env — service should degrade gracefully
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_pdf',
        ]);

        $invoice = PartnerInvoice::create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => 'partner_pdf',
            'invoice_number' => 'SOS-202604-7777',
            'period' => '2026-04',
            'active_subscribers' => 1,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
            'total_amount' => 3.00,
            'calls_expert' => 0,
            'calls_lawyer' => 0,
            'total_cost' => 0,
            'status' => PartnerInvoice::STATUS_PENDING,
            'due_date' => now()->addDays(15)->toDateString(),
        ]);

        // If dompdf class doesn't exist, it returns null (not throw)
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $result = $this->service->generatePdf($invoice);
            $this->assertNull($result);
        } else {
            $this->markTestSkipped('dompdf is installed, skipping graceful-degradation test');
        }
    }

    public function test_create_stripe_invoice_returns_null_when_sdk_not_installed(): void
    {
        $agreement = Agreement::factory()->create(['billing_email' => 'test@x.com']);
        $invoice = PartnerInvoice::create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => 'ptest',
            'invoice_number' => 'SOS-202604-6666',
            'period' => '2026-04',
            'active_subscribers' => 1,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
            'total_amount' => 3.00,
            'calls_expert' => 0,
            'calls_lawyer' => 0,
            'total_cost' => 0,
            'status' => PartnerInvoice::STATUS_PENDING,
            'due_date' => now()->addDays(15)->toDateString(),
        ]);

        if (!class_exists(\Stripe\StripeClient::class)) {
            $result = $this->service->createStripeInvoice($invoice);
            $this->assertNull($result);
        } else {
            // Stripe installed but no secret configured
            config(['services.stripe.secret' => null]);
            $result = $this->service->createStripeInvoice($invoice);
            $this->assertNull($result);
        }
    }

    public function test_calculates_zero_amount_for_zero_subscribers(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_zero',
            'sos_call_active' => true,
            'billing_rate' => 3.00,
        ]);

        // No subscribers

        $data = $this->service->calculateInvoiceData(
            $agreement,
            now()->subMonth()->format('Y-m')
        );

        $this->assertEquals(0, $data['active_subscribers']);
        $this->assertEquals(0.00, $data['total_amount']);
    }

    public function test_model_b_flat_monthly_fee_only(): void
    {
        // Model (b): monthly_base_fee > 0, billing_rate = 0
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_flat',
            'sos_call_active' => true,
            'billing_rate' => 0.00,
            'monthly_base_fee' => 500.00,
            'billing_currency' => 'EUR',
        ]);

        Subscriber::factory()->count(50)->create([
            'partner_firebase_id' => 'partner_flat',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'FLT-2026-' . substr(md5(uniqid()), 0, 5),
            'sos_call_activated_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        $data = $this->service->calculateInvoiceData(
            $agreement,
            now()->subMonth()->format('Y-m')
        );

        $this->assertEquals(50, $data['active_subscribers']);
        $this->assertEquals(500.00, $data['monthly_base_fee']);
        $this->assertEquals(0.00, $data['billing_rate']);
        $this->assertEquals(500.00, $data['total_amount']); // Flat fee, regardless of subscriber count
    }

    public function test_model_c_hybrid_flat_plus_per_member(): void
    {
        // Model (c): monthly_base_fee > 0 AND billing_rate > 0
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_hybrid',
            'sos_call_active' => true,
            'billing_rate' => 2.00,
            'monthly_base_fee' => 200.00,
            'billing_currency' => 'EUR',
        ]);

        Subscriber::factory()->count(200)->create([
            'partner_firebase_id' => 'partner_hybrid',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'HYB-2026-' . substr(md5(uniqid()), 0, 5),
            'sos_call_activated_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        $data = $this->service->calculateInvoiceData(
            $agreement,
            now()->subMonth()->format('Y-m')
        );

        $this->assertEquals(200, $data['active_subscribers']);
        $this->assertEquals(200.00, $data['monthly_base_fee']);
        $this->assertEquals(2.00, $data['billing_rate']);
        $this->assertEquals(600.00, $data['total_amount']); // 200 + (200 × 2)
    }

    public function test_model_a_per_member_only_unchanged_when_base_fee_null(): void
    {
        // Model (a): monthly_base_fee NULL (legacy), billing_rate > 0 — must behave as before
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_legacy',
            'sos_call_active' => true,
            'billing_rate' => 3.00,
            'monthly_base_fee' => null,
            'billing_currency' => 'EUR',
        ]);

        Subscriber::factory()->count(10)->create([
            'partner_firebase_id' => 'partner_legacy',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'LEG-2026-' . substr(md5(uniqid()), 0, 5),
            'sos_call_activated_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        $data = $this->service->calculateInvoiceData(
            $agreement,
            now()->subMonth()->format('Y-m')
        );

        $this->assertEquals(10, $data['active_subscribers']);
        $this->assertEquals(0.00, $data['monthly_base_fee']);
        $this->assertEquals(3.00, $data['billing_rate']);
        $this->assertEquals(30.00, $data['total_amount']); // Per-member only
    }

    public function test_create_invoice_snapshots_monthly_base_fee(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_snap',
            'sos_call_active' => true,
            'billing_rate' => 5.00,
            'monthly_base_fee' => 100.00,
            'billing_currency' => 'EUR',
            'payment_terms_days' => 15,
        ]);

        Subscriber::factory()->count(3)->create([
            'partner_firebase_id' => 'partner_snap',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'SNP-2026-' . substr(md5(uniqid()), 0, 5),
            'sos_call_activated_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        $invoice = $this->service->createInvoice(
            $agreement,
            now()->subMonth()->format('Y-m')
        );

        $this->assertEquals(100.00, (float) $invoice->monthly_base_fee);
        $this->assertEquals(5.00, (float) $invoice->billing_rate);
        $this->assertEquals(115.00, (float) $invoice->total_amount); // 100 + (3 × 5)
    }

    public function test_handles_decimal_billing_rates_precisely(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_dec',
            'sos_call_active' => true,
            'billing_rate' => 2.99,
            'billing_currency' => 'USD',
        ]);

        Subscriber::factory()->count(7)->create([
            'partner_firebase_id' => 'partner_dec',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'DEC-2026-' . substr(md5(uniqid()), 0, 5),
            'sos_call_activated_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        $data = $this->service->calculateInvoiceData(
            $agreement,
            now()->subMonth()->format('Y-m')
        );

        $this->assertEquals(7, $data['active_subscribers']);
        $this->assertEquals(20.93, $data['total_amount']); // 7 × 2.99 = 20.93
    }
}
