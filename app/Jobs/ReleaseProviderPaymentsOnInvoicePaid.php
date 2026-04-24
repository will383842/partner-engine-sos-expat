<?php

namespace App\Jobs;

use App\Models\PartnerInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * When a partner pays their monthly invoice, this job notifies Firebase so the
 * provider payment holds for that period can be released.
 *
 * The actual Firestore update (flipping payment.status from
 * `pending_partner_invoice` to `captured_sos_call_free`) happens via a
 * Firebase callable, because call_sessions live in Firestore and need admin
 * SDK credentials Laravel does not have.
 *
 * NOTE: even after this job runs, the provider still has to wait for the
 * commercial delay (60 days from the call) before the money is withdrawable.
 * This is enforced by `payment.availableFromDate` written at call completion.
 */
class ReleaseProviderPaymentsOnInvoicePaid implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public int $invoiceId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $invoice = PartnerInvoice::with('agreement')->find($this->invoiceId);

        if (!$invoice || !$invoice->isPaid()) {
            Log::warning('[ReleaseProviderPayments] Invoice not found or not paid', [
                'invoice_id' => $this->invoiceId,
            ]);
            return;
        }

        $engineUrl = config('services.firebase_partner_bridge.url');
        $engineKey = config('services.firebase_partner_bridge.api_key');

        if (!$engineUrl || !$engineKey) {
            Log::warning('[ReleaseProviderPayments] Firebase bridge not configured — holds will not be released automatically', [
                'invoice_id' => $invoice->id,
                'hint' => 'Set FIREBASE_PARTNER_BRIDGE_URL + FIREBASE_PARTNER_BRIDGE_API_KEY',
            ]);
            return;
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-Engine-Secret' => $engineKey])
                ->post("{$engineUrl}/release-provider-payments", [
                    'partner_firebase_id' => $invoice->partner_firebase_id,
                    'agreement_id' => $invoice->agreement_id,
                    'period' => $invoice->period,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'paid_at' => $invoice->paid_at?->toIso8601String(),
                ]);

            if ($response->failed()) {
                Log::error('[ReleaseProviderPayments] Firebase bridge returned error', [
                    'invoice_id' => $invoice->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $this->release(60);
                return;
            }

            $data = $response->json();
            Log::info('[ReleaseProviderPayments] Holds released on Firebase', [
                'invoice_id' => $invoice->id,
                'released_count' => $data['released_count'] ?? null,
                'total_provider_amount' => $data['total_provider_amount'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('[ReleaseProviderPayments] Firebase bridge call failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            throw $e; // trigger retry
        }
    }
}
