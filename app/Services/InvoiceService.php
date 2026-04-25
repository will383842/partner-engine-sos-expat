<?php

namespace App\Services;

use App\Models\Agreement;
use App\Models\PartnerInvoice;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * Handles monthly invoice generation for SOS-Call B2B partners.
 *
 * Key responsibilities:
 *   - Calculate active_subscribers and call counts for a given (agreement, period)
 *   - Generate unique invoice_number (SOS-YYYYMM-NNNN format)
 *   - Generate PDF via dompdf (if available — installed via Sprint 4 composer require)
 *   - Create Stripe Invoicing invoice with hosted payment URL (if Stripe installed)
 *   - Store PDF file to local storage (storage/app/invoices/{partner_id}/{period}.pdf)
 *
 * Dependencies (to be installed via composer require):
 *   - barryvdh/laravel-dompdf  (for PDF generation)
 *   - stripe/stripe-php        (for Stripe Invoicing integration)
 *
 * Without these packages, PDF and Stripe steps are skipped gracefully (logged as warnings),
 * so the basic invoice record is still created and an admin can manually bill.
 */
class InvoiceService
{
    public const INTERNAL_COST_EXPAT_CENTS = 1000;   // 10€ per expert expat call
    public const INTERNAL_COST_LAWYER_CENTS = 3000;  // 30€ per lawyer call

    /**
     * Calculate invoice data for an agreement over a given month period (YYYY-MM).
     */
    public function calculateInvoiceData(Agreement $agreement, string $period): array
    {
        [$year, $month] = explode('-', $period);
        $startOfMonth = Carbon::create((int) $year, (int) $month, 1)->startOfMonth();
        $endOfMonth = (clone $startOfMonth)->endOfMonth();

        // Count active subscribers during the period
        // (activated before end of month AND not expired before start of month)
        $activeSubscribers = Subscriber::where('partner_firebase_id', $agreement->partner_firebase_id)
            ->where('status', 'active')
            ->whereNotNull('sos_call_code')
            ->where('sos_call_activated_at', '<=', $endOfMonth)
            ->where(function ($q) use ($startOfMonth) {
                $q->whereNull('sos_call_expires_at')
                  ->orWhere('sos_call_expires_at', '>=', $startOfMonth);
            })
            ->count();

        // Count SOS-Call completed calls during the period
        $callsQuery = SubscriberActivity::where('partner_firebase_id', $agreement->partner_firebase_id)
            ->where('type', 'call_completed')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            $callsQuery->whereRaw("(metadata->>'is_sos_call')::boolean = true");
        } else {
            // SQLite fallback for tests
            $callsQuery->where('metadata', 'like', '%"is_sos_call":true%');
        }

        $callsExpert = (clone $callsQuery)->where('provider_type', 'expat')->count();
        $callsLawyer = (clone $callsQuery)->where('provider_type', 'lawyer')->count();

        // Billing supports up to 5 model permutations, all driven by these
        // three columns on Agreement (resolved via Agreement::resolveBaseFee):
        //   (a) Per-member only       : tiers NULL, base 0,  rate > 0
        //   (b) Flat monthly fee      : tiers NULL, base > 0, rate = 0
        //   (c) Hybrid                : tiers NULL, base > 0, rate > 0
        //   (d) Tiered flat           : tiers set,           rate = 0
        //   (e) Tiered + per-member   : tiers set,           rate > 0  (rare)
        $billingRate = (float) $agreement->billing_rate;
        $base = $agreement->resolveBaseFee($activeSubscribers);
        $monthlyBaseFee = (float) $base['amount'];
        $totalAmount = round($monthlyBaseFee + ($activeSubscribers * $billingRate), 2);

        // Internal cost (informational — not billed to partner)
        $internalCostCents = ($callsExpert * self::INTERNAL_COST_EXPAT_CENTS)
                           + ($callsLawyer * self::INTERNAL_COST_LAWYER_CENTS);
        $totalCost = round($internalCostCents / 100, 2);

        return [
            'active_subscribers' => $activeSubscribers,
            'billing_rate' => $billingRate,
            'monthly_base_fee' => $monthlyBaseFee,
            'pricing_tier' => $base['tier'], // null when flat fee, snapshot when tier matched
            'billing_currency' => $agreement->billing_currency ?? 'EUR',
            'total_amount' => $totalAmount,
            'calls_expert' => $callsExpert,
            'calls_lawyer' => $callsLawyer,
            'total_cost' => $totalCost,
            'period_start' => $startOfMonth,
            'period_end' => $endOfMonth,
        ];
    }

    /**
     * Generate the next invoice number for a given period.
     * Format: SOS-YYYYMM-NNNN (NNNN is sequential within the period)
     */
    public function generateInvoiceNumber(string $period): string
    {
        $periodDash = str_replace('-', '', $period); // YYYYMM
        $count = PartnerInvoice::where('invoice_number', 'like', "SOS-{$periodDash}-%")->count();
        $sequence = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
        return "SOS-{$periodDash}-{$sequence}";
    }

    /**
     * Create a PartnerInvoice record for a given agreement and period.
     * If an invoice already exists for this (agreement, period), returns it unchanged.
     */
    public function createInvoice(Agreement $agreement, string $period): PartnerInvoice
    {
        $existing = PartnerInvoice::where('agreement_id', $agreement->id)
            ->where('period', $period)
            ->first();

        if ($existing) {
            Log::info('[InvoiceService] Invoice already exists, skipping creation', [
                'invoice_id' => $existing->id,
                'period' => $period,
            ]);
            return $existing;
        }

        $data = $this->calculateInvoiceData($agreement, $period);
        $invoiceNumber = $this->generateInvoiceNumber($period);
        $dueDate = now()->addDays((int) ($agreement->payment_terms_days ?? 15))->toDateString();

        $invoice = PartnerInvoice::create([
            'agreement_id' => $agreement->id,
            'partner_firebase_id' => $agreement->partner_firebase_id,
            'invoice_number' => $invoiceNumber,
            'period' => $period,
            'active_subscribers' => $data['active_subscribers'],
            'billing_rate' => $data['billing_rate'],
            'monthly_base_fee' => $data['monthly_base_fee'],
            'pricing_tier' => $data['pricing_tier'] ?? null,
            'billing_currency' => $data['billing_currency'],
            'total_amount' => $data['total_amount'],
            'calls_expert' => $data['calls_expert'],
            'calls_lawyer' => $data['calls_lawyer'],
            'total_cost' => $data['total_cost'],
            'status' => PartnerInvoice::STATUS_PENDING,
            'due_date' => $dueDate,
        ]);

        return $invoice;
    }

    /**
     * Generate the PDF for an invoice and save it to local storage.
     * Returns the storage path (relative to local disk) or null if dompdf isn't installed.
     */
    public function generatePdf(PartnerInvoice $invoice): ?string
    {
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            Log::warning('[InvoiceService] dompdf not installed — skipping PDF generation', [
                'invoice_id' => $invoice->id,
                'hint' => 'Run: composer require barryvdh/laravel-dompdf',
            ]);
            return null;
        }

        try {
            $invoice->loadMissing('agreement');
            $html = View::make('invoices.sos_call_monthly', [
                'invoice' => $invoice,
                'agreement' => $invoice->agreement,
            ])->render();

            $pdfClass = \Barryvdh\DomPDF\Facade\Pdf::class;
            $pdf = $pdfClass::loadHTML($html)
                ->setPaper('a4', 'portrait');

            $path = sprintf(
                'invoices/%s/%s.pdf',
                $invoice->partner_firebase_id,
                $invoice->invoice_number
            );

            Storage::disk('local')->put($path, $pdf->output());

            $invoice->pdf_path = $path;
            $invoice->save();

            Log::info('[InvoiceService] PDF generated', [
                'invoice_id' => $invoice->id,
                'path' => $path,
            ]);

            return $path;
        } catch (\Throwable $e) {
            Log::error('[InvoiceService] PDF generation failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create a Stripe Invoicing invoice with a hosted payment URL.
     * Returns the hosted URL or null on failure/missing package.
     *
     * Uses `services.stripe.secret` (loaded from env STRIPE_SECRET).
     * The partner's Stripe customer is created on the fly if it doesn't exist.
     */
    public function createStripeInvoice(PartnerInvoice $invoice): ?string
    {
        if (!class_exists(\Stripe\StripeClient::class)) {
            Log::warning('[InvoiceService] Stripe SDK not installed — skipping Stripe invoice', [
                'invoice_id' => $invoice->id,
                'hint' => 'Run: composer require stripe/stripe-php',
            ]);
            return null;
        }

        $secret = config('services.stripe.secret');
        if (!$secret) {
            Log::warning('[InvoiceService] STRIPE_SECRET not configured — skipping Stripe invoice', [
                'invoice_id' => $invoice->id,
            ]);
            return null;
        }

        try {
            $stripeClass = \Stripe\StripeClient::class;
            $stripe = new $stripeClass($secret);

            $agreement = $invoice->agreement;
            $customerId = $invoice->stripe_customer_id ?? $this->getOrCreateStripeCustomer($stripe, $agreement);

            // Build line items based on the agreement's billing model.
            //   - Flat monthly fee (if monthly_base_fee > 0)
            //   - Per-member usage (if active_subscribers × billing_rate > 0)
            // Both can coexist (hybrid model).
            $currency = strtolower($invoice->billing_currency);
            $currencyUpper = strtoupper($invoice->billing_currency);
            $baseFeeCents = (int) round(((float) $invoice->monthly_base_fee) * 100);
            $perMemberTotalCents = (int) round(((float) $invoice->billing_rate) * $invoice->active_subscribers * 100);

            $sharedItemMetadata = [
                'partner_invoice_id' => $invoice->id,
                'partner_firebase_id' => $invoice->partner_firebase_id,
                'period' => $invoice->period,
            ];

            if ($baseFeeCents > 0) {
                // If the base came from a matched tier, surface the bracket so
                // the partner can reconcile against their contract.
                $tier = $invoice->pricing_tier;
                $description = (is_array($tier) && isset($tier['min']))
                    ? sprintf(
                        'SOS-Call %s — Palier %d–%s clients : %s %s',
                        $invoice->period,
                        (int) $tier['min'],
                        $tier['max'] === null ? '∞' : (int) $tier['max'],
                        number_format((float) $invoice->monthly_base_fee, 2, '.', ''),
                        $currencyUpper
                    )
                    : sprintf(
                        'SOS-Call %s — Forfait mensuel %s %s',
                        $invoice->period,
                        number_format((float) $invoice->monthly_base_fee, 2, '.', ''),
                        $currencyUpper
                    );
                $stripe->invoiceItems->create([
                    'customer' => $customerId,
                    'amount' => $baseFeeCents,
                    'currency' => $currency,
                    'description' => $description,
                    'metadata' => $sharedItemMetadata + ['line_type' => is_array($tier) ? 'pricing_tier' : 'monthly_base_fee'],
                ]);
            }

            if ($perMemberTotalCents > 0) {
                $stripe->invoiceItems->create([
                    'customer' => $customerId,
                    'amount' => $perMemberTotalCents,
                    'currency' => $currency,
                    'description' => sprintf(
                        'SOS-Call %s — %d clients × %s %s',
                        $invoice->period,
                        $invoice->active_subscribers,
                        number_format((float) $invoice->billing_rate, 2, '.', ''),
                        $currencyUpper
                    ),
                    'metadata' => $sharedItemMetadata + ['line_type' => 'per_member'],
                ]);
            }

            // Edge case: agreement has both fees set to 0 — push a single zero-amount placeholder
            // so the Stripe invoice isn't empty (rare; happens for trial/test agreements).
            if ($baseFeeCents === 0 && $perMemberTotalCents === 0) {
                $stripe->invoiceItems->create([
                    'customer' => $customerId,
                    'amount' => 0,
                    'currency' => $currency,
                    'description' => sprintf('SOS-Call %s — Aucune facturation ce mois', $invoice->period),
                    'metadata' => $sharedItemMetadata + ['line_type' => 'zero'],
                ]);
            }

            // Create invoice with collection_method="send_invoice" (hosted page + email)
            $stripeInvoice = $stripe->invoices->create([
                'customer' => $customerId,
                'collection_method' => 'send_invoice',
                'days_until_due' => (int) ($agreement->payment_terms_days ?? 15),
                'description' => "SOS-Call invoice for {$invoice->period}",
                'metadata' => [
                    'partner_invoice_id' => $invoice->id,
                    'partner_invoice_number' => $invoice->invoice_number,
                ],
            ]);

            // Finalize and send
            $stripe->invoices->finalizeInvoice($stripeInvoice->id);
            $finalized = $stripe->invoices->retrieve($stripeInvoice->id);

            $invoice->stripe_customer_id = $customerId;
            $invoice->stripe_invoice_id = $finalized->id;
            $invoice->stripe_hosted_url = $finalized->hosted_invoice_url ?? null;
            $invoice->save();

            Log::info('[InvoiceService] Stripe invoice created', [
                'invoice_id' => $invoice->id,
                'stripe_invoice_id' => $finalized->id,
            ]);

            return $invoice->stripe_hosted_url;
        } catch (\Throwable $e) {
            Log::error('[InvoiceService] Stripe invoice creation failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get existing Stripe customer for this partner, or create one.
     */
    protected function getOrCreateStripeCustomer($stripe, Agreement $agreement): string
    {
        // Check if any previous invoice has a stripe_customer_id
        $previousInvoice = PartnerInvoice::where('partner_firebase_id', $agreement->partner_firebase_id)
            ->whereNotNull('stripe_customer_id')
            ->latest()
            ->first();

        if ($previousInvoice?->stripe_customer_id) {
            return $previousInvoice->stripe_customer_id;
        }

        $billingEmail = $agreement->billing_email ?: ($agreement->contact_email ?? null);
        if (!$billingEmail) {
            throw new \RuntimeException("Cannot create Stripe customer: no billing_email on agreement {$agreement->id}");
        }

        $customer = $stripe->customers->create([
            'email' => $billingEmail,
            'name' => $agreement->partner_name,
            'description' => "SOS-Expat Partner: {$agreement->partner_name}",
            'metadata' => [
                'partner_firebase_id' => $agreement->partner_firebase_id,
                'agreement_id' => $agreement->id,
            ],
        ]);

        return $customer->id;
    }
}
