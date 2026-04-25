<?php

namespace App\Console\Commands;

use App\Jobs\SuspendOnNonPayment;
use App\Mail\MonthlyInvoiceMail;
use App\Models\Agreement;
use App\Models\PartnerInvoice;
use App\Services\AuditService;
use App\Services\InvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Generates monthly invoices for all active SOS-Call partners.
 *
 * Scheduled to run on the 1st of each month at 06:00 UTC
 * (see routes/console.php).
 *
 * For each agreement with `sos_call_active=true` and `status='active'`:
 *   1. Skip if invoice already exists for the period
 *   2. Count active subscribers + SOS-Call calls for the period
 *   3. Calculate total_amount = monthly_base_fee + (active_subscribers × billing_rate)
 *   4. Create PartnerInvoice with status=pending
 *   5. Generate PDF (via dompdf) and save to storage
 *   6. Create Stripe Invoicing invoice with hosted URL
 *   7. Send email to partner with PDF attached
 *   8. Dispatch SuspendOnNonPayment with delay = payment_terms_days
 *   9. Notify admin via Telegram
 *
 * Supports --period=YYYY-MM and --agreement={id} flags for manual generation.
 */
class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'invoices:generate-monthly
                            {--period= : Override period (YYYY-MM). Default: previous month.}
                            {--agreement= : Only process a specific agreement ID.}
                            {--dry-run : Compute totals without writing anything.}';

    protected $description = 'Generate monthly SOS-Call invoices for active partners.';

    public function __construct(
        protected InvoiceService $invoiceService,
        protected AuditService $audit,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $period = $this->option('period') ?: now()->subMonth()->format('Y-m');
        $agreementId = $this->option('agreement');
        $dryRun = (bool) $this->option('dry-run');

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $this->error("Invalid --period format. Use YYYY-MM (e.g. 2026-03).");
            return self::INVALID;
        }

        $this->info("🧾 Generating invoices for period: {$period}" . ($dryRun ? ' [DRY-RUN]' : ''));

        $query = Agreement::where('status', 'active')
            ->where('sos_call_active', true);

        if ($agreementId) {
            $query->where('id', $agreementId);
        }

        $agreements = $query->get();

        if ($agreements->isEmpty()) {
            $this->warn("No active SOS-Call agreements found.");
            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($agreements as $agreement) {
            try {
                $result = $this->processAgreement($agreement, $period, $dryRun);
                if ($result === 'created') $created++;
                elseif ($result === 'skipped') $skipped++;
                else $failed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('[GenerateMonthlyInvoices] Failed to process agreement', [
                    'agreement_id' => $agreement->id,
                    'partner_name' => $agreement->partner_name,
                    'period' => $period,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  ✗ Failed: {$agreement->partner_name} — {$e->getMessage()}");
            }
        }

        $this->info("");
        $this->info("📊 Summary:");
        $this->info("  ✓ Created: {$created}");
        $this->info("  ⚠ Skipped: {$skipped}");
        $this->info("  ✗ Failed:  {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function processAgreement(Agreement $agreement, string $period, bool $dryRun): string
    {
        // Skip if invoice already exists for this (agreement, period)
        $existing = PartnerInvoice::where('agreement_id', $agreement->id)
            ->where('period', $period)
            ->first();

        if ($existing) {
            $this->line("  → Skipping {$agreement->partner_name}: invoice already exists (#{$existing->invoice_number})");
            return 'skipped';
        }

        $data = $this->invoiceService->calculateInvoiceData($agreement, $period);

        $this->line(sprintf(
            "  → %s: %d subscribers × %.2f %s = %.2f %s (calls: %d expat, %d lawyer)",
            $agreement->partner_name,
            $data['active_subscribers'],
            $data['billing_rate'],
            $data['billing_currency'],
            $data['total_amount'],
            $data['billing_currency'],
            $data['calls_expert'],
            $data['calls_lawyer'],
        ));

        if ($dryRun) {
            return 'skipped';
        }

        // Skip if 0 subscribers (no one to bill for)
        if ($data['active_subscribers'] === 0) {
            $this->line("  ⚠ Skipping: 0 active subscribers");
            return 'skipped';
        }

        // 1. Create invoice record
        $invoice = $this->invoiceService->createInvoice($agreement, $period);

        // 2. Generate PDF
        $pdfPath = $this->invoiceService->generatePdf($invoice);
        if (!$pdfPath) {
            $this->warn("    ⚠ PDF generation skipped (dompdf not installed or error)");
        }

        // 3. Create Stripe invoice (non-blocking — invoice still valid without Stripe)
        $stripeUrl = $this->invoiceService->createStripeInvoice($invoice);
        if (!$stripeUrl) {
            $this->warn("    ⚠ Stripe invoice skipped (SDK not installed or error)");
        }

        // 4. Send email to partner
        try {
            $billingEmail = $agreement->billing_email ?: $agreement->contact_email;
            if ($billingEmail && filter_var($billingEmail, FILTER_VALIDATE_EMAIL)) {
                Mail::to($billingEmail)->send(new MonthlyInvoiceMail($invoice->fresh()));
                $this->line("    ✓ Email sent to {$billingEmail}");
            } else {
                $this->warn("    ⚠ No valid billing_email on agreement — email skipped");
            }
        } catch (\Throwable $e) {
            Log::error('[GenerateMonthlyInvoices] Email send failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }

        // 5. Dispatch SuspendOnNonPayment
        SuspendOnNonPayment::dispatch($invoice->id)
            ->delay(now()->addDays((int) ($agreement->payment_terms_days ?? 15)));

        // 6. Audit log
        $this->audit->log(
            'system',
            'system',
            'sos_call.invoice_generated',
            'partner_invoice',
            $invoice->id,
            [
                'agreement_id' => $agreement->id,
                'partner_firebase_id' => $agreement->partner_firebase_id,
                'period' => $period,
                'total_amount' => $data['total_amount'],
                'currency' => $data['billing_currency'],
                'active_subscribers' => $data['active_subscribers'],
            ],
        );

        // 7. Notify admin via Telegram
        $this->notifyTelegramAdmin($invoice);

        $this->info("    ✓ Created invoice #{$invoice->invoice_number}");
        return 'created';
    }

    protected function notifyTelegramAdmin(PartnerInvoice $invoice): void
    {
        try {
            $url = config('services.telegram_engine.url');
            $apiKey = config('services.telegram_engine.api_key');

            if (!$url || !$apiKey) {
                return;
            }

            Http::timeout(5)
                ->withHeaders(['X-Engine-Secret' => $apiKey])
                ->post("{$url}/api/events/sos-call-invoice-generated", [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'partner_firebase_id' => $invoice->partner_firebase_id,
                    'partner_name' => $invoice->agreement->partner_name ?? null,
                    'period' => $invoice->period,
                    'active_subscribers' => $invoice->active_subscribers,
                    'total_amount' => (float) $invoice->total_amount,
                    'currency' => $invoice->billing_currency,
                    'due_date' => $invoice->due_date->toDateString(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('[GenerateMonthlyInvoices] Telegram notification failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
