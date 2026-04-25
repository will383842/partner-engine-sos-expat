<?php

namespace Tests\Feature;

use App\Jobs\SuspendOnNonPayment;
use App\Models\Agreement;
use App\Models\PartnerInvoice;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tests for the GenerateMonthlyInvoices artisan command and the
 * InvoiceService used under the hood.
 */
class GenerateMonthlyInvoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Bus::fake();
    }

    public function test_creates_invoice_for_active_sos_call_agreement(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_active',
            'partner_name' => 'AXA Test',
            'status' => 'active',
            'sos_call_active' => true,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
            'payment_terms_days' => 15,
            'billing_email' => 'billing@axa-test.com',
        ]);

        Subscriber::factory()->count(5)->create([
            'partner_firebase_id' => 'partner_active',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'AXA-2026-' . substr(md5(uniqid()), 0, 5),
            'sos_call_activated_at' => now()->subMonths(2),
            'status' => 'active',
        ]);

        $period = now()->subMonth()->format('Y-m');

        $this->artisan('invoices:generate-monthly', ['--period' => $period])
            ->assertSuccessful();

        $invoice = PartnerInvoice::where('agreement_id', $agreement->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(5, $invoice->active_subscribers);
        $this->assertEquals(15.00, (float) $invoice->total_amount); // 5 × 3.00
        $this->assertEquals('EUR', $invoice->billing_currency);
        $this->assertEquals(PartnerInvoice::STATUS_PENDING, $invoice->status);
        $this->assertStringStartsWith('SOS-', $invoice->invoice_number);
    }

    public function test_skips_agreement_with_sos_call_inactive(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_inactive',
            'status' => 'active',
            'sos_call_active' => false, // disabled
        ]);

        Subscriber::factory()->count(3)->create([
            'partner_firebase_id' => 'partner_inactive',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'INA-2026-' . substr(md5(uniqid()), 0, 5),
            'sos_call_activated_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        $period = now()->subMonth()->format('Y-m');
        $this->artisan('invoices:generate-monthly', ['--period' => $period])
            ->assertSuccessful();

        $this->assertDatabaseCount('partner_invoices', 0);
    }

    public function test_skips_agreement_when_invoice_already_exists(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_dup',
            'status' => 'active',
            'sos_call_active' => true,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
        ]);

        Subscriber::factory()->count(2)->create([
            'partner_firebase_id' => 'partner_dup',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'DUP-2026-' . substr(md5(uniqid()), 0, 5),
            'sos_call_activated_at' => now()->subMonths(2),
            'status' => 'active',
        ]);

        $period = now()->subMonth()->format('Y-m');

        // Create first invoice
        $this->artisan('invoices:generate-monthly', ['--period' => $period]);
        $this->assertDatabaseCount('partner_invoices', 1);

        // Try again → idempotent
        $this->artisan('invoices:generate-monthly', ['--period' => $period]);
        $this->assertDatabaseCount('partner_invoices', 1);
    }

    public function test_dispatches_suspend_on_non_payment_job(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_job',
            'status' => 'active',
            'sos_call_active' => true,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
            'payment_terms_days' => 15,
            'billing_email' => 'billing@example.com',
        ]);

        Subscriber::factory()->count(2)->create([
            'partner_firebase_id' => 'partner_job',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'JOB-2026-' . substr(md5(uniqid()), 0, 5),
            'sos_call_activated_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        $period = now()->subMonth()->format('Y-m');
        $this->artisan('invoices:generate-monthly', ['--period' => $period]);

        Bus::assertDispatched(SuspendOnNonPayment::class);
    }

    public function test_sends_email_to_billing_email(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_email',
            'partner_name' => 'TestPartner',
            'status' => 'active',
            'sos_call_active' => true,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
            'billing_email' => 'billing@test-partner.com',
        ]);

        Subscriber::factory()->create([
            'partner_firebase_id' => 'partner_email',
            'agreement_id' => $agreement->id,
            'sos_call_code' => 'TES-2026-EMAI1',
            'sos_call_activated_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        $period = now()->subMonth()->format('Y-m');
        $this->artisan('invoices:generate-monthly', ['--period' => $period]);

        Mail::assertSent(\App\Mail\MonthlyInvoiceMail::class, function ($mail) {
            return $mail->hasTo('billing@test-partner.com');
        });
    }

    public function test_dry_run_does_not_create_invoices(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_dry',
            'status' => 'active',
            'sos_call_active' => true,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
        ]);

        Subscriber::factory()->count(10)->create([
            'partner_firebase_id' => 'partner_dry',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'DRY-2026-' . substr(md5(uniqid()), 0, 5),
            'sos_call_activated_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        $period = now()->subMonth()->format('Y-m');
        $this->artisan('invoices:generate-monthly', [
            '--period' => $period,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('partner_invoices', 0);
    }

    public function test_rejects_invalid_period_format(): void
    {
        $this->artisan('invoices:generate-monthly', ['--period' => 'invalid'])
            ->assertFailed();
    }

    public function test_skips_agreement_with_zero_subscribers(): void
    {
        Agreement::factory()->create([
            'partner_firebase_id' => 'partner_empty',
            'status' => 'active',
            'sos_call_active' => true,
            'billing_rate' => 3.00,
            'monthly_base_fee' => null, // Model (a): per-member only
            'billing_email' => 'billing@empty.com',
        ]);

        // No subscribers created

        $period = now()->subMonth()->format('Y-m');
        $this->artisan('invoices:generate-monthly', ['--period' => $period])
            ->assertSuccessful();

        // No invoice should be created for 0 subscribers AND no flat fee
        $this->assertDatabaseCount('partner_invoices', 0);
    }

    public function test_flat_fee_partner_with_zero_subscribers_still_invoiced(): void
    {
        // Model (b): partner pays a flat monthly fee even with 0 subscribers.
        // Without this, an insurance-style partner on a 500€/mo subscription
        // would silently miss billing for any month with zero active clients.
        Agreement::factory()->create([
            'partner_firebase_id' => 'partner_flat',
            'status' => 'active',
            'sos_call_active' => true,
            'billing_rate' => 0,
            'monthly_base_fee' => 500.00,
            'billing_currency' => 'EUR',
            'billing_email' => 'billing@flat.com',
        ]);

        // No subscribers created — but the flat fee is still due

        $period = now()->subMonth()->format('Y-m');
        $this->artisan('invoices:generate-monthly', ['--period' => $period])
            ->assertSuccessful();

        $this->assertDatabaseCount('partner_invoices', 1);
        $this->assertDatabaseHas('partner_invoices', [
            'partner_firebase_id' => 'partner_flat',
            'period' => $period,
            'active_subscribers' => 0,
            'total_amount' => 500.00,
            'status' => 'pending',
        ]);
    }

    public function test_hybrid_partner_with_zero_subscribers_invoiced_for_base_fee(): void
    {
        // Model (c) hybrid with 0 subs: total = base_fee + 0 = base_fee
        Agreement::factory()->create([
            'partner_firebase_id' => 'partner_hybrid_empty',
            'status' => 'active',
            'sos_call_active' => true,
            'billing_rate' => 2.00,
            'monthly_base_fee' => 200.00,
            'billing_currency' => 'EUR',
            'billing_email' => 'billing@hybrid.com',
        ]);

        $period = now()->subMonth()->format('Y-m');
        $this->artisan('invoices:generate-monthly', ['--period' => $period])
            ->assertSuccessful();

        $this->assertDatabaseHas('partner_invoices', [
            'partner_firebase_id' => 'partner_hybrid_empty',
            'period' => $period,
            'active_subscribers' => 0,
            'total_amount' => 200.00, // 200 + (0 × 2)
        ]);
    }

    public function test_only_processes_specified_agreement_when_flag_given(): void
    {
        $target = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_target',
            'status' => 'active',
            'sos_call_active' => true,
            'billing_rate' => 3.00,
            'billing_email' => 'target@example.com',
        ]);
        $other = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_other',
            'status' => 'active',
            'sos_call_active' => true,
            'billing_rate' => 5.00,
            'billing_email' => 'other@example.com',
        ]);

        Subscriber::factory()->create([
            'partner_firebase_id' => 'partner_target',
            'agreement_id' => $target->id,
            'sos_call_code' => 'TGT-2026-ONE01',
            'sos_call_activated_at' => now()->subMonth(),
            'status' => 'active',
        ]);
        Subscriber::factory()->create([
            'partner_firebase_id' => 'partner_other',
            'agreement_id' => $other->id,
            'sos_call_code' => 'OTH-2026-ONE01',
            'sos_call_activated_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        $period = now()->subMonth()->format('Y-m');
        $this->artisan('invoices:generate-monthly', [
            '--period' => $period,
            '--agreement' => $target->id,
        ]);

        $this->assertDatabaseCount('partner_invoices', 1);
        $this->assertDatabaseHas('partner_invoices', ['agreement_id' => $target->id]);
    }

    public function test_invoice_number_is_unique_and_well_formatted(): void
    {
        Agreement::factory()->count(3)->create([
            'status' => 'active',
            'sos_call_active' => true,
            'billing_rate' => 3.00,
            'billing_email' => fn() => 'billing' . uniqid() . '@x.com',
        ])->each(function ($ag, $i) {
            Subscriber::factory()->create([
                'partner_firebase_id' => $ag->partner_firebase_id,
                'agreement_id' => $ag->id,
                'sos_call_code' => 'UNI-2026-' . strtoupper(substr(md5($i . uniqid()), 0, 5)),
                'sos_call_activated_at' => now()->subMonth(),
                'status' => 'active',
            ]);
        });

        $period = now()->subMonth()->format('Y-m');
        $this->artisan('invoices:generate-monthly', ['--period' => $period]);

        $invoices = PartnerInvoice::all();
        $this->assertCount(3, $invoices);

        // All numbers must match SOS-YYYYMM-NNNN format
        foreach ($invoices as $inv) {
            $this->assertMatchesRegularExpression('/^SOS-\d{6}-\d{4}$/', $inv->invoice_number);
        }

        // All must be unique
        $this->assertCount(3, $invoices->pluck('invoice_number')->unique());
    }
}
