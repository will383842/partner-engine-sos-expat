<?php

namespace App\Jobs;

use App\Mail\InvoiceOverdueMail;
use App\Models\PartnerInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Alerts the admin when an invoice goes past its due_date.
 *
 * NOTE (2026-04-24): This job NO LONGER auto-suspends subscribers.
 * Auto-suspension on a B2B partner (e.g. AXA, Visa, Revolut) would be
 * commercially dangerous — large corporations often have 30-45 day internal
 * payment cycles and would leave the platform if their users were blocked
 * without notice. The admin must decide case-by-case via the Filament console.
 *
 * What this job now does:
 *   1. Reloads the invoice to catch any recent payment
 *   2. If still unpaid → marks it OVERDUE (status change only)
 *   3. Sends a reminder email to the partner billing address
 *   4. Sends a Telegram alert to the admin with full context
 *
 * What this job does NOT do:
 *   - Does NOT change subscriber status (no suspension)
 *   - Does NOT deactivate SOS-Call codes
 *
 * To actually suspend a partner, the admin uses the Filament action
 * "Suspendre les clients de ce partenaire" on the PartnerResource or
 * PartnerInvoiceResource page.
 */
class SuspendOnNonPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 300;

    public function __construct(public int $invoiceId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $invoice = PartnerInvoice::with('agreement')->find($this->invoiceId);

        if (!$invoice) {
            Log::warning('[SuspendOnNonPayment] Invoice not found', ['invoice_id' => $this->invoiceId]);
            return;
        }

        $invoice->refresh();

        if ($invoice->status !== PartnerInvoice::STATUS_PENDING) {
            Log::info('[SuspendOnNonPayment] Invoice no longer pending — skipping', [
                'invoice_id' => $invoice->id,
                'status' => $invoice->status,
            ]);
            return;
        }

        // Mark overdue — NO subscriber suspension here. Admin decides manually.
        $invoice->markOverdue();

        $daysOverdue = now()->diffInDays($invoice->due_date);

        Log::warning('[SuspendOnNonPayment] Invoice marked OVERDUE (no auto-suspension)', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'agreement_id' => $invoice->agreement_id,
            'partner_name' => $invoice->agreement?->partner_name,
            'days_overdue' => $daysOverdue,
            'note' => 'Admin must suspend subscribers manually via Filament if needed',
        ]);

        // Reminder email to partner billing (informative, not a threat)
        try {
            $billingEmail = $invoice->agreement?->billing_email;
            if ($billingEmail && filter_var($billingEmail, FILTER_VALIDATE_EMAIL)) {
                // Note: suspendedCount=0 since we do not suspend automatically anymore.
                // The mail template should be updated to reflect "reminder" tone.
                Mail::to($billingEmail)->send(new InvoiceOverdueMail($invoice, 0));
            }
        } catch (\Throwable $e) {
            Log::error('[SuspendOnNonPayment] Failed to send overdue email', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Telegram alert to admin with enough context to decide
        $this->notifyTelegramAdmin($invoice, $daysOverdue);
    }

    protected function notifyTelegramAdmin(PartnerInvoice $invoice, int $daysOverdue): void
    {
        try {
            $url = config('services.telegram_engine.url');
            $apiKey = config('services.telegram_engine.api_key');

            if (!$url || !$apiKey) {
                return;
            }

            Http::timeout(5)
                ->withHeaders(['X-Engine-Secret' => $apiKey])
                ->post("{$url}/api/events/sos-call-invoice-overdue", [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'partner_firebase_id' => $invoice->partner_firebase_id,
                    'partner_name' => $invoice->agreement?->partner_name,
                    'period' => $invoice->period,
                    'total_amount' => (float) $invoice->total_amount,
                    'currency' => $invoice->billing_currency,
                    'due_date' => $invoice->due_date->toDateString(),
                    'days_overdue' => $daysOverdue,
                    'billing_email' => $invoice->agreement?->billing_email,
                    'admin_action_required' => 'Decide whether to suspend subscribers manually in Filament',
                ]);
        } catch (\Throwable $e) {
            Log::warning('[SuspendOnNonPayment] Telegram notification failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
