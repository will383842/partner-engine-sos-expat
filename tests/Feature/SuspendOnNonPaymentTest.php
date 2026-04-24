<?php

namespace Tests\Feature;

use App\Jobs\SuspendOnNonPayment;
use App\Mail\InvoiceOverdueMail;
use App\Models\Agreement;
use App\Models\PartnerInvoice;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SuspendOnNonPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_marks_invoice_overdue_without_suspending_subscribers(): void
    {
        // Updated 2026-04-24: admin must decide suspension manually via Filament.
        // Auto-suspension was removed to avoid losing large B2B partners (AXA, Visa, etc.)
        // who may have 30-45 day internal payment cycles.
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_susp',
            'status' => 'active',
            'sos_call_active' => true,
            'billing_email' => 'billing@example.com',
        ]);

        Subscriber::factory()->count(5)->create([
            'partner_firebase_id' => 'partner_susp',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'SUS-2026-' . substr(md5(uniqid()), 0, 5),
            'status' => 'active',
        ]);

        $invoice = PartnerInvoice::create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => 'partner_susp',
            'invoice_number' => 'SOS-202604-0001',
            'period' => '2026-04',
            'active_subscribers' => 5,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
            'total_amount' => 15.00,
            'calls_expert' => 0,
            'calls_lawyer' => 0,
            'total_cost' => 0,
            'status' => PartnerInvoice::STATUS_PENDING,
            'due_date' => now()->subDay()->toDateString(),
        ]);

        (new SuspendOnNonPayment($invoice->id))->handle();

        $invoice->refresh();
        $this->assertEquals(PartnerInvoice::STATUS_OVERDUE, $invoice->status);

        // Subscribers must REMAIN ACTIVE — no auto-suspension
        $activeCount = Subscriber::where('agreement_id', $agreement->id)
            ->where('status', 'active')
            ->count();
        $this->assertEquals(5, $activeCount, 'Subscribers must not be auto-suspended');

        $suspendedCount = Subscriber::where('agreement_id', $agreement->id)
            ->where('status', 'suspended')
            ->count();
        $this->assertEquals(0, $suspendedCount, 'No subscriber should be auto-suspended');
    }

    public function test_does_nothing_when_invoice_already_paid(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_paid',
            'status' => 'active',
            'sos_call_active' => true,
        ]);

        Subscriber::factory()->create([
            'partner_firebase_id' => 'partner_paid',
            'agreement_id' => $agreement->id,
            'sos_call_code' => 'PAI-2026-DONE1',
            'status' => 'active',
        ]);

        $invoice = PartnerInvoice::create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => 'partner_paid',
            'invoice_number' => 'SOS-202604-0002',
            'period' => '2026-04',
            'active_subscribers' => 1,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
            'total_amount' => 3.00,
            'calls_expert' => 0,
            'calls_lawyer' => 0,
            'total_cost' => 0,
            'status' => PartnerInvoice::STATUS_PAID, // Already paid
            'due_date' => now()->subDay()->toDateString(),
            'paid_at' => now()->subHour(),
            'paid_via' => 'stripe',
        ]);

        (new SuspendOnNonPayment($invoice->id))->handle();

        // Subscriber should remain active
        $subCount = Subscriber::where('agreement_id', $agreement->id)
            ->where('status', 'active')
            ->count();
        $this->assertEquals(1, $subCount);

        // Invoice remains paid
        $invoice->refresh();
        $this->assertEquals(PartnerInvoice::STATUS_PAID, $invoice->status);
    }

    public function test_sends_overdue_email_to_billing_email(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_overdue',
            'partner_name' => 'Overdue Test',
            'status' => 'active',
            'sos_call_active' => true,
            'billing_email' => 'billing@overdue.com',
        ]);

        Subscriber::factory()->create([
            'partner_firebase_id' => 'partner_overdue',
            'agreement_id' => $agreement->id,
            'sos_call_code' => 'OVR-2026-MAIL1',
            'status' => 'active',
        ]);

        $invoice = PartnerInvoice::create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => 'partner_overdue',
            'invoice_number' => 'SOS-202604-0003',
            'period' => '2026-04',
            'active_subscribers' => 1,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
            'total_amount' => 3.00,
            'calls_expert' => 0,
            'calls_lawyer' => 0,
            'total_cost' => 0,
            'status' => PartnerInvoice::STATUS_PENDING,
            'due_date' => now()->subDays(2)->toDateString(),
        ]);

        (new SuspendOnNonPayment($invoice->id))->handle();

        Mail::assertSent(InvoiceOverdueMail::class, function ($mail) {
            return $mail->hasTo('billing@overdue.com');
        });
    }

    public function test_marks_invoice_overdue_before_suspending(): void
    {
        $agreement = Agreement::factory()->create([
            'partner_firebase_id' => 'partner_ord',
            'status' => 'active',
            'sos_call_active' => true,
            'billing_email' => 'x@y.com',
        ]);

        Subscriber::factory()->count(3)->create([
            'partner_firebase_id' => 'partner_ord',
            'agreement_id' => $agreement->id,
            'sos_call_code' => fn() => 'ORD-2026-' . substr(md5(uniqid()), 0, 5),
            'status' => 'active',
        ]);

        $invoice = PartnerInvoice::create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => 'partner_ord',
            'invoice_number' => 'SOS-202604-0004',
            'period' => '2026-04',
            'active_subscribers' => 3,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
            'total_amount' => 9.00,
            'calls_expert' => 0,
            'calls_lawyer' => 0,
            'total_cost' => 0,
            'status' => PartnerInvoice::STATUS_PENDING,
            'due_date' => now()->subDay()->toDateString(),
        ]);

        (new SuspendOnNonPayment($invoice->id))->handle();

        $invoice->refresh();
        $this->assertEquals(PartnerInvoice::STATUS_OVERDUE, $invoice->status);
    }
}
