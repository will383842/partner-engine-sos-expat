<?php

namespace App\Observers;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Jobs\SyncSubscriberToFirestore;

class AgreementObserver
{
    /**
     * When agreement status changes, sync all linked subscribers to Firestore.
     * Note: Audit logging is handled in AgreementService, not here.
     */
    public function updated(Agreement $agreement): void
    {
        if ($agreement->wasChanged('status')) {
            $subscribers = Subscriber::where('agreement_id', $agreement->id)
                ->whereNull('deleted_at')
                ->get();

            foreach ($subscribers as $subscriber) {
                SyncSubscriberToFirestore::dispatch($subscriber, 'upsert');
            }
        }
    }
}
