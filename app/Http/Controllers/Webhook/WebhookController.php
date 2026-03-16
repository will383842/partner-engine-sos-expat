<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
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
     * Idempotent: uses callSessionId as deduplication key.
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

        // Idempotency check: already processed this call?
        $existing = SubscriberActivity::where('call_session_id', $validated['callSessionId'])
            ->where('type', 'call_completed')
            ->exists();

        if ($existing) {
            return response()->json(['status' => 'already_processed'], 200);
        }

        // Find the subscriber by firebase_uid
        $subscriber = Subscriber::where('firebase_uid', $validated['clientUid'])
            ->whereNull('deleted_at')
            ->first();

        if (!$subscriber) {
            // Client is not a partner subscriber — ignore
            return response()->json(['status' => 'ignored', 'reason' => 'not_a_subscriber'], 200);
        }

        // Get the active agreement for this subscriber
        $agreement = $subscriber->agreement;

        if (!$agreement || !$agreement->isActive()) {
            // Agreement not active (paused/expired/draft) — log activity but no commission
            return response()->json(['status' => 'ignored', 'reason' => 'agreement_not_active'], 200);
        }

        // Calculate commission
        $commissionCents = $this->calculateCommission($agreement, $validated['providerType'], $validated['amountPaidCents']);

        DB::transaction(function () use ($subscriber, $agreement, $validated, $commissionCents) {
            // 1. Create subscriber activity
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

        // 4. Write commission to Firestore (outside transaction — Firestore is external)
        if ($commissionCents > 0) {
            $this->writeFirebaseCommission($subscriber, $agreement, $validated, $commissionCents);
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
     *
     * Fired by Firebase when a user registers with a partnerInviteToken.
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

        // Update subscriber
        $subscriber->update([
            'firebase_uid' => $validated['firebaseUid'],
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        // Create activity log
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

        // Notify Telegram
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
            return (int) round($amountPaidCents * ($agreement->commission_percent / 100));
        }

        return 0;
    }

    /**
     * Write a partner_commission doc to Firestore + increment partner balance.
     */
    private function writeFirebaseCommission(Subscriber $subscriber, Agreement $agreement, array $data, int $commissionCents): void
    {
        try {
            $idempotencyKey = hash('sha256', $data['callSessionId'] . $subscriber->partner_firebase_id . 'subscriber');

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
                'isIdempotent' => true,
                'idempotencyKey' => $idempotencyKey,
            ];

            // Write partner_commissions doc
            $this->firebase->setDocument('partner_commissions', $idempotencyKey, $commissionDoc);

            // Increment partner balance (atomic)
            $this->firebase->incrementField('partners', $subscriber->partner_firebase_id, 'pendingBalance', $commissionCents);
            $this->firebase->incrementField('partners', $subscriber->partner_firebase_id, 'totalEarned', $commissionCents);

        } catch (\Exception $e) {
            Log::error('Failed to write Firebase commission', [
                'error' => $e->getMessage(),
                'call_session_id' => $data['callSessionId'],
                'partner_id' => $subscriber->partner_firebase_id,
            ]);
        }
    }

    /**
     * Send a notification to the Telegram Engine.
     */
    private function notifyTelegram(string $eventSlug, array $data): void
    {
        $url = config('services.telegram_engine.url');
        $apiKey = config('services.telegram_engine.api_key');

        if (!$url || !$apiKey) {
            return;
        }

        try {
            Http::timeout(5)
                ->withHeaders(['X-Engine-Secret' => $apiKey])
                ->post("{$url}/api/events/{$eventSlug}", $data);
        } catch (\Exception $e) {
            Log::warning("Telegram notification failed for {$eventSlug}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
