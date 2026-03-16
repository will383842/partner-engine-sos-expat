<?php

namespace App\Jobs;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSubscriberToFirestore implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Subscriber $subscriber,
        public string $action = 'upsert',
    ) {
        $this->onQueue('high');
    }

    public function handle(FirebaseService $firebase): void
    {
        $inviteToken = $this->subscriber->invite_token;

        if ($this->action === 'delete') {
            try {
                $firebase->deleteDocument('partner_subscribers', $inviteToken);
                Log::info('Deleted partner_subscribers from Firestore', [
                    'invite_token' => $inviteToken,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to delete partner_subscribers from Firestore', [
                    'invite_token' => $inviteToken,
                    'error' => $e->getMessage(),
                ]);
            }
            return;
        }

        // Upsert — build the Firestore document
        $agreement = $this->subscriber->agreement;

        $firestoreData = [
            'partnerFirebaseId' => $this->subscriber->partner_firebase_id,
            'subscriberEmail' => $this->subscriber->email,
            'firebaseUid' => $this->subscriber->firebase_uid,
            'discountType' => $agreement?->discount_type ?? 'none',
            'discountValue' => $agreement?->discount_value ?? 0,
            'discountMaxCents' => $agreement?->discount_max_cents,
            'discountLabel' => $agreement?->discount_label ?? '',
            'agreementId' => $agreement?->id,
            'status' => $this->subscriber->status,
            'agreementPaused' => $agreement?->status === 'paused',
            'expiresAt' => $agreement?->expires_at?->toDateTimeString(),
        ];

        try {
            $firebase->setDocument('partner_subscribers', $inviteToken, $firestoreData);
            Log::info('Synced partner_subscribers to Firestore', [
                'invite_token' => $inviteToken,
                'status' => $this->subscriber->status,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync partner_subscribers to Firestore', [
                'invite_token' => $inviteToken,
                'error' => $e->getMessage(),
            ]);
            throw $e; // Retry via queue
        }
    }
}
