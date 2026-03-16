<?php

namespace App\Jobs;

use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Writes partner commission data to Firestore with retry support.
 * Runs on high-priority queue to ensure commissions are visible quickly.
 */
class SyncPartnerCommissionToFirestore implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 10; // seconds between retries

    public function __construct(
        public string $partnerFirebaseId,
        public string $idempotencyKey,
        public array $commissionDoc,
        public int $commissionCents,
    ) {
        $this->onQueue('high');
    }

    public function handle(FirebaseService $firebase): void
    {
        try {
            // 1. Write commission document
            $firebase->setDocument('partner_commissions', $this->idempotencyKey, $this->commissionDoc);

            // 2. Increment partner balances atomically
            $firebase->incrementField('partners', $this->partnerFirebaseId, 'pendingBalance', $this->commissionCents);
            $firebase->incrementField('partners', $this->partnerFirebaseId, 'totalEarned', $this->commissionCents);

            Log::info('Partner commission synced to Firestore', [
                'partner_id' => $this->partnerFirebaseId,
                'idempotency_key' => $this->idempotencyKey,
                'commission_cents' => $this->commissionCents,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync partner commission to Firestore', [
                'partner_id' => $this->partnerFirebaseId,
                'idempotency_key' => $this->idempotencyKey,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);
            throw $e; // Retry via queue
        }
    }
}
