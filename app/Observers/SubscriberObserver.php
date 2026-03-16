<?php

namespace App\Observers;

use App\Models\Subscriber;
use App\Jobs\SyncSubscriberToFirestore;

class SubscriberObserver
{
    /**
     * Sync to Firestore on status/data changes.
     * Note: Audit logging is handled in SubscriberService, not here.
     * Note: We only dispatch if status or key fields changed, to avoid infinite loops.
     */
    public function updated(Subscriber $subscriber): void
    {
        $watchedFields = ['status', 'firebase_uid', 'email', 'first_name', 'last_name'];

        if ($subscriber->wasChanged($watchedFields)) {
            SyncSubscriberToFirestore::dispatch($subscriber, 'upsert');
        }
    }

    public function deleted(Subscriber $subscriber): void
    {
        SyncSubscriberToFirestore::dispatch($subscriber, 'delete');
    }
}
