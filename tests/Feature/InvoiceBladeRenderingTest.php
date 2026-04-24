<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\PartnerInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Tests that the Blade templates for the monthly invoice PDF and the
 * invoice-related emails render without errors.
 *
 * These tests do NOT require dompdf — they just verify the Blade
 * templates are syntactically valid and all variables are correctly bound.
 */
class InvoiceBladeRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function buildInvoiceWithAgreement(array $invoiceOverrides = [], array $agreementOverrides = []): PartnerInvoice
    {
        $agreement = Agreement::factory()->create(array_merge([
            'partner_firebase_id' => 'partner_blade',
            'partner_name' => 'Test Partner Inc.',
            'billing_email' => 'billing@test.com',
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
            'payment_terms_days' => 15,
            'sos_call_active' => true,
        ], $agreementOverrides));

        $invoice = PartnerInvoice::create(array_merge([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'invoice_number' => 'SOS-202604-9999',
            'period' => '2026-04',
            'active_subscribers' => 10,
            'billing_rate' => 3.00,
            'billing_currency' => 'EUR',
            'total_amount' => 30.00,
            'calls_expert' => 2,
            'calls_lawyer' => 1,
            'total_cost' => 5.00,
            'status' => PartnerInvoice::STATUS_PENDING,
            'due_date' => now()->addDays(15)->toDateString(),
        ], $invoiceOverrides));

        return $invoice->fresh('agreement');
    }

    public function test_monthly_invoice_pdf_template_renders(): void
    {
        $invoice = $this->buildInvoiceWithAgreement();

        $html = View::make('invoices.sos_call_monthly', [
            'invoice' => $invoice,
            'agreement' => $invoice->agreement,
        ])->render();

        $this->assertStringContainsString('SOS-202604-9999', $html);
        $this->assertStringContainsString('Test Partner Inc.', $html);
        $this->assertStringContainsString('30', $html); // Total amount
        $this->assertStringContainsString('EUR', $html);
    }

    public function test_monthly_invoice_template_shows_stripe_url_when_present(): void
    {
        $invoice = $this->buildInvoiceWithAgreement([
            'stripe_hosted_url' => 'https://invoice.stripe.com/abc123',
        ]);

        $html = View::make('invoices.sos_call_monthly', [
            'invoice' => $invoice,
            'agreement' => $invoice->agreement,
        ])->render();

        $this->assertStringContainsString('invoice.stripe.com', $html);
    }

    public function test_monthly_invoice_template_shows_iban_sepa(): void
    {
        $invoice = $this->buildInvoiceWithAgreement();

        $html = View::make('invoices.sos_call_monthly', [
            'invoice' => $invoice,
            'agreement' => $invoice->agreement,
        ])->render();

        // IBAN/SEPA should always be available as fallback
        $this->assertStringContainsString('FR76', $html);
    }

    public function test_monthly_invoice_email_template_renders(): void
    {
        $invoice = $this->buildInvoiceWithAgreement();

        $html = View::make('emails.monthly_invoice.fr', [
            'partner_name' => $invoice->agreement->partner_name,
            'invoice_number' => $invoice->invoice_number,
            'period' => $invoice->period,
            'period_label' => 'avril 2026',
            'active_subscribers' => $invoice->active_subscribers,
            'billing_rate' => $invoice->billing_rate,
            'currency' => $invoice->billing_currency,
            'total_amount' => $invoice->total_amount,
            'calls_expert' => $invoice->calls_expert,
            'calls_lawyer' => $invoice->calls_lawyer,
            'due_date' => $invoice->due_date,
            'stripe_hosted_url' => null,
        ])->render();

        $this->assertStringContainsString('SOS-202604-9999', $html);
        $this->assertStringContainsString('avril 2026', $html);
    }

    public function test_invoice_overdue_email_template_renders(): void
    {
        $invoice = $this->buildInvoiceWithAgreement([
            'status' => PartnerInvoice::STATUS_OVERDUE,
        ]);

        $html = View::make('emails.invoice_overdue.fr', [
            'partner_name' => $invoice->agreement->partner_name,
            'invoice_number' => $invoice->invoice_number,
            'total_amount' => $invoice->total_amount,
            'currency' => $invoice->billing_currency,
            'due_date' => $invoice->due_date,
            'days_overdue' => 2,
            'suspended_count' => 5,
            'stripe_hosted_url' => 'https://invoice.stripe.com/test',
        ])->render();

        $this->assertStringContainsString('SOS-202604-9999', $html);
        $this->assertStringContainsString('suspend', strtolower($html));
    }

    public function test_sos_call_activation_email_template_renders(): void
    {
        $html = View::make('emails.sos_call_activation.fr', [
            'first_name' => 'Jean',
            'partner_name' => 'AXA Test',
            'sos_call_code' => 'AXA-2026-X7K2P',
            'expires_at' => null,
            'sos_call_url' => 'https://sos-call.sos-expat.com',
            'dashboard_url' => 'https://sos-call.sos-expat.com/mon-acces',
            'call_types_allowed' => 'both',
        ])->render();

        $this->assertStringContainsString('AXA-2026-X7K2P', $html);
        $this->assertStringContainsString('AXA Test', $html);
        $this->assertStringContainsString('Jean', $html);
    }

    public function test_blade_template_handles_null_stripe_url(): void
    {
        $invoice = $this->buildInvoiceWithAgreement([
            'stripe_hosted_url' => null,
        ]);

        // Should NOT throw when stripe_hosted_url is null
        $html = View::make('invoices.sos_call_monthly', [
            'invoice' => $invoice,
            'agreement' => $invoice->agreement,
        ])->render();

        $this->assertNotEmpty($html);
    }
}
