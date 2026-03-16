<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\SyncPartnerCommissionToFirestore;
use App\Models\Agreement;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(protected FirebaseService $firebase)
    {
    }

    /**
     * POST /api/webhooks/call-completed
     *
     * Fired by Firebase when a paid call completes for a known subscriber.
     * Idempotent: uses callSessionId + unique index as deduplication key.
     */
    public function callCompleted(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'callSessionId' => 'required|string|max:128',
            'clientUid' => 'required|string|max:128',
            'providerType' => 'required|in:lawyer,expat',
            'duration' => 'required|integer|min:0',
            'amountPaidCents' => 'required|integer|min:0',
            'discountAppliedCents' => 'sometimes|integer|min:0',
            'partnerReferredBy' => 'nullable|string|max:128',
            'subscriberId' => 'nullable|string|max:128',
        ]);

        // Idempotency check (DB-level unique index also protects against race conditions)
        $existing = SubscriberActivity::where('call_session_id', $validated['callSessionId'])
            ->where('type', 'call_completed')
            ->exists();

        if ($existing) {
            return response()->json(['status' => 'already_processed'], 200);
        }

        // Find ALL subscribers for this firebase_uid (multi-partner support)
        $subscribers = Subscriber::where('firebase_uid', $validated['clientUid'])
            ->whereNull('deleted_at')
            ->with('agreement')
            ->get();

        if ($subscribers->isEmpty()) {
            return response()->json(['status' => 'ignored', 'reason' => 'not_a_subscriber'], 200);
        }

        // Find the best active subscriber/agreement pair
        $bestSubscriber = null;
        $bestAgreement = null;

        foreach ($subscribers as $sub) {
            $agreement = $sub->agreement;
            if (!$agreement || !$agreement->isActive()) {
                continue;
            }

            // Check max_calls_per_subscriber limit
            if ($agreement->max_calls_per_subscriber && $sub->total_calls >= $agreement->max_calls_per_subscriber) {
                continue;
            }

            // If partnerReferredBy matches, prioritize that partner
            if (!empty($validated['partnerReferredBy']) && $sub->partner_firebase_id === $validated['partnerReferredBy']) {
                $bestSubscriber = $sub;
                $bestAgreement = $agreement;
                break;
            }

            // Otherwise pick the one with best discount (or first active)
            if (!$bestAgreement || $this->getDiscountValue($agreement) > $this->getDiscountValue($bestAgreement)) {
                $bestSubscriber = $sub;
                $bestAgreement = $agreement;
            }
        }

        if (!$bestSubscriber || !$bestAgreement) {
            return response()->json(['status' => 'ignored', 'reason' => 'no_active_agreement'], 200);
        }

        $subscriber = $bestSubscriber;
        $agreement = $bestAgreement;

        // Calculate commission
        $commissionCents = $this->calculateCommission($agreement, $validated['providerType'], $validated['amountPaidCents']);

        try {
            DB::transaction(function () use ($subscriber, $agreement, $validated, $commissionCents) {
                // 1. Create subscriber activity (unique index on call_session_id prevents race condition)
                SubscriberActivity::create([
                    'subscriber_id' => $subscriber->id,
                    'partner_firebase_id' => $subscriber->partner_firebase_id,
                    'type' => 'call_completed',
                    'call_session_id' => $validated['callSessionId'],
                    'provider_type' => $validated['providerType'],
                    'call_duration_seconds' => $validated['duration'],
                    'amount_paid_cents' => $validated['amountPaidCents'],
                    'discount_applied_cents' => $validated['discountAppliedCents'] ?? 0,
                    'commission_earned_cents' => $commissionCents,
                    'created_at' => now(),
                ]);

                // 2. Update subscriber stats
                $subscriber->increment('total_calls');
                $subscriber->increment('total_spent_cents', $validated['amountPaidCents']);
                $subscriber->increment('total_discount_cents', $validated['discountAppliedCents'] ?? 0);
                $subscriber->update(['last_activity_at' => now()]);

                // 3. Transition from registered → active on first call
                if ($subscriber->status === 'registered') {
                    $subscriber->update(['status' => 'active']);
                }
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Race condition: another request already processed this call
            return response()->json(['status' => 'already_processed'], 200);
        }

        // 4. Write commission to Firestore via retryable job (outside transaction — Firestore is external)
        if ($commissionCents > 0) {
            $this->dispatchFirebaseCommission($subscriber, $agreement, $validated, $commissionCents);
        }

        // 5. Notify Telegram on first call
        if ($subscriber->total_calls === 1) {
            $this->notifyTelegram('partner-subscriber-first-call', [
                'partner_id' => $subscriber->partner_firebase_id,
                'subscriber_email' => $subscriber->email,
                'subscriber_name' => $subscriber->full_name,
                'provider_type' => $validated['providerType'],
                'amount_cents' => $validated['amountPaidCents'],
            ]);
        }

        Log::info('Webhook call-completed processed', [
            'call_session_id' => $validated['callSessionId'],
            'subscriber_id' => $subscriber->id,
            'partner_id' => $subscriber->partner_firebase_id,
            'commission_cents' => $commissionCents,
        ]);

        return response()->json([
            'status' => 'processed',
            'subscriber_id' => $subscriber->id,
            'commission_cents' => $commissionCents,
        ], 200);
    }

    /**
     * POST /api/webhooks/subscriber-registered
     */
    public function subscriberRegistered(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firebaseUid' => 'required|string|max:128',
            'email' => 'required|email|max:255',
            'inviteToken' => 'required|string|max:64',
        ]);

        $subscriber = Subscriber::where('invite_token', $validated['inviteToken'])
            ->whereNull('deleted_at')
            ->first();

        if (!$subscriber) {
            return response()->json(['status' => 'ignored', 'reason' => 'unknown_invite_token'], 200);
        }

        // Idempotency: already registered?
        if ($subscriber->firebase_uid !== null) {
            return response()->json(['status' => 'already_registered'], 200);
        }

        $subscriber->update([
            'firebase_uid' => $validated['firebaseUid'],
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        SubscriberActivity::create([
            'subscriber_id' => $subscriber->id,
            'partner_firebase_id' => $subscriber->partner_firebase_id,
            'type' => 'registered',
            'metadata' => ['firebase_uid' => $validated['firebaseUid']],
            'created_at' => now(),
        ]);

        // Update Firestore partner_subscribers doc
        $this->firebase->setDocument('partner_subscribers', $validated['inviteToken'], [
            'firebaseUid' => $validated['firebaseUid'],
            'status' => 'registered',
        ]);

        $this->notifyTelegram('partner-subscriber-registered', [
            'partner_id' => $subscriber->partner_firebase_id,
            'subscriber_email' => $subscriber->email,
            'subscriber_name' => $subscriber->full_name,
        ]);

        Log::info('Webhook subscriber-registered processed', [
            'invite_token' => $validated['inviteToken'],
            'subscriber_id' => $subscriber->id,
            'firebase_uid' => $validated['firebaseUid'],
        ]);

        return response()->json([
            'status' => 'processed',
            'subscriber_id' => $subscriber->id,
        ], 200);
    }

    /**
     * Calculate commission based on the agreement terms.
     */
    private function calculateCommission(Agreement $agreement, string $providerType, int $amountPaidCents): int
    {
        if ($agreement->commission_type === 'fixed') {
            return $providerType === 'lawyer'
                ? $agreement->commission_per_call_lawyer
                : $agreement->commission_per_call_expat;
        }

        if ($agreement->commission_type === 'percent' && $agreement->commission_percent > 0) {
            $commission = (int) round($amountPaidCents * ($agreement->commission_percent / 100));
            // Cap at 50% of call price to prevent abuse
            $maxCommission = (int) ($amountPaidCents * 0.5);
            return min($commission, $maxCommission);
        }

        return 0;
    }

    /**
     * Get effective discount value for comparison (multi-partner: best discount wins).
     */
    private function getDiscountValue(Agreement $agreement): int
    {
        if ($agreement->discount_type === 'fixed') {
            return $agreement->discount_value;
        }
        if ($agreement->discount_type === 'percent') {
            // Normalize to cents for comparison (assume $49 call = 4900 cents)
            return (int) ($agreement->discount_value * 49);
        }
        return 0;
    }

    /**
     * Dispatch a retryable job to write partner_commission to Firestore + increment partner balance.
     */
    private function dispatchFirebaseCommission(Subscriber $subscriber, Agreement $agreement, array $data, int $commissionCents): void
    {
        $idempotencyKey = hash('sha256', $data['callSessionId'] . $subscriber->partner_firebase_id . 'subscriber');

        $holdDays = 7;
        $holdUntil = now()->addDays($holdDays)->toDateTimeString();

        $commissionDoc = [
            'partnerId' => $subscriber->partner_firebase_id,
            'type' => 'subscriber_call',
            'source' => 'partner_engine',
            'status' => 'pending',
            'subscriberId' => $subscriber->id,
            'subscriberEmail' => $subscriber->email,
            'subscriberName' => $subscriber->full_name,
            'callSessionId' => $data['callSessionId'],
            'providerType' => $data['providerType'],
            'callDurationSeconds' => $data['duration'],
            'amountPaidByClientCents' => $data['amountPaidCents'],
            'discountAppliedCents' => $data['discountAppliedCents'] ?? 0,
            'commissionAmountCents' => $commissionCents,
            'commissionType' => $agreement->commission_type,
            'agreementId' => $agreement->id,
            'agreementName' => $agreement->name,
            'callCompletedAt' => now()->toDateTimeString(),
            'createdAt' => now()->toDateTimeString(),
            'holdUntil' => $holdUntil,
            'isIdempotent' => true,
            'idempotencyKey' => $idempotencyKey,
        ];

        SyncPartnerCommissionToFirestore::dispatch(
            partnerFirebaseId: $subscriber->partner_firebase_id,
            idempotencyKey: $idempotencyKey,
            commissionDoc: $commissionDoc,
            commissionCents: $commissionCents,
        );
    }

    private function notifyTelegram(string $eventSlug, array $data): void
    {
        $url = config('services.telegram_engine.url');
        $apiKey = config('services.telegram_engine.api_key');
        if (!$url || !$apiKey) return;

        try {
            Http::timeout(5)
                ->withHeaders(['X-Engine-Secret' => $apiKey])
                ->post("{$url}/api/events/{$eventSlug}", $data);
        } catch (\Exception $e) {
            Log::warning("Telegram notification failed for {$eventSlug}", ['error' => $e->getMessage()]);
        }
    }
}
