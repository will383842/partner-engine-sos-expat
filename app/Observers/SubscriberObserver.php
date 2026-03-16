<?php

namespace App\Observers;

use App\Models\Subscriber;
use App\Jobs\SyncSubscriberToFirestore;
use App\Services\AuditService;

class SubscriberObserver
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function created(Subscriber $subscriber): void
    {
        SyncSubscriberToFirestore::dispatch($subscriber, 'upsert');
    }

    public function updated(Subscriber $subscriber): void
    {
        SyncSubscriberToFirestore::dispatch($subscriber, 'upsert');
    }

    public function deleted(Subscriber $subscriber): void
    {
        SyncSubscriberToFirestore::dispatch($subscriber, 'delete');
    }
}
