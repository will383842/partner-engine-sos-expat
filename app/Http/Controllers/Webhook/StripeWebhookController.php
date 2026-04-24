<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\PartnerInvoice;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles Stripe webhooks for SOS-Call invoices.
 *
 * Configured on Stripe dashboard → Webhooks → endpoint:
 *   https://partner-engine.sos-expat.com/api/webhooks/stripe/invoice-events
 *
 * Events subscribed:
 *   - invoice.paid          → Mark PartnerInvoice as paid, reactivate subscribers if needed
 *   - invoice.payment_failed → Log failure, notify admin
 *   - invoice.finalized     → Update hosted_invoice_url on our record
 *
 * Security: validates `Stripe-Signature` header using STRIPE_WEBHOOK_SECRET.
 * Falls back to no-op (HTTP 200) on unknown event types to avoid Stripe retries.
 */
class StripeWebhookController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        if (!$webhookSecret) {
            Log::error('[StripeWebhook] STRIPE_WEBHOOK_SECRET not configured');
            return response()->json(['error' => 'Webhook not configured'], 500);
        }

        if (!class_exists(\Stripe\Webhook::class)) {
            Log::error('[StripeWebhook] Stripe SDK not installed', [
                'hint' => 'Run: composer require stripe/stripe-php',
            ]);
            return response()->json(['error' => 'Stripe SDK not available'], 500);
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\UnexpectedValueException $e) {
            Log::warning('[StripeWebhook] Invalid payload', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('[StripeWebhook] Invalid signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventType = $event->type;
        $eventId = $event->id;

        Log::info('[StripeWebhook] Received event', [
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);

        try {
            switch ($eventType) {
                case 'invoice.paid':
                    return $this->handleInvoicePaid($event);
                case 'invoice.payment_failed':
                    return $this->handleInvoicePaymentFailed($event);
                case 'invoice.finalized':
                    return $this->handleInvoiceFinalized($event);
                default:
                    // Unknown events are acknowledged to prevent Stripe retries
                    return response()->json(['received' => true]);
            }
        } catch (\Throwable $e) {
            Log::error('[StripeWebhook] Handler error', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
            // Return 500 so Stripe retries
            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    protected function handleInvoicePaid($event): JsonResponse
    {
        $stripeInvoice = $event->data->object;
        $partnerInvoiceId = $stripeInvoice->metadata->partner_invoice_id ?? null;

        if (!$partnerInvoiceId) {
            Log::info('[StripeWebhook] invoice.paid has no partner_invoice_id metadata — ignoring');
            return response()->json(['received' => true]);
        }

        $invoice = PartnerInvoice::find($partnerInvoiceId);
        if (!$invoice) {
            Log::warning('[StripeWebhook] invoice.paid: PartnerInvoice not found', [
                'partner_invoice_id' => $partnerInvoiceId,
            ]);
            return response()->json(['received' => true]);
        }

        if ($invoice->status === PartnerInvoice::STATUS_PAID) {
            // Already paid — idempotent
            return response()->json(['received' => true]);
        }

        $invoice->markPaid(
            PartnerInvoice::PAID_VIA_STRIPE,
            "Auto-marked paid via Stripe webhook (invoice: {$stripeInvoice->id})"
        );

        $this->audit->log('system', 'system', 'sos_call.invoice_paid', 'partner_invoice', $invoice->id, [
            'stripe_invoice_id' => $stripeInvoice->id,
            'amount_paid' => $stripeInvoice->amount_paid ?? 0,
        ]);

        Log::info('[StripeWebhook] Invoice marked as paid', [
            'partner_invoice_id' => $invoice->id,
            'stripe_invoice_id' => $stripeInvoice->id,
        ]);

        return response()->json(['received' => true, 'processed' => true]);
    }

    protected function handleInvoicePaymentFailed($event): JsonResponse
    {
        $stripeInvoice = $event->data->object;
        $partnerInvoiceId = $stripeInvoice->metadata->partner_invoice_id ?? null;

        if (!$partnerInvoiceId) {
            return response()->json(['received' => true]);
        }

        $invoice = PartnerInvoice::find($partnerInvoiceId);
        if (!$invoice) {
            return response()->json(['received' => true]);
        }

        $this->audit->log('system', 'system', 'sos_call.invoice_payment_failed', 'partner_invoice', $invoice->id, [
            'stripe_invoice_id' => $stripeInvoice->id,
            'attempt_count' => $stripeInvoice->attempt_count ?? 0,
        ]);

        Log::warning('[StripeWebhook] Invoice payment failed', [
            'partner_invoice_id' => $invoice->id,
            'stripe_invoice_id' => $stripeInvoice->id,
            'attempt_count' => $stripeInvoice->attempt_count ?? 0,
        ]);

        return response()->json(['received' => true]);
    }

    protected function handleInvoiceFinalized($event): JsonResponse
    {
        $stripeInvoice = $event->data->object;
        $partnerInvoiceId = $stripeInvoice->metadata->partner_invoice_id ?? null;

        if (!$partnerInvoiceId) {
            return response()->json(['received' => true]);
        }

        $invoice = PartnerInvoice::find($partnerInvoiceId);
        if ($invoice && !$invoice->stripe_hosted_url) {
            $invoice->stripe_hosted_url = $stripeInvoice->hosted_invoice_url ?? null;
            $invoice->save();
        }

        return response()->json(['received' => true]);
    }
}
