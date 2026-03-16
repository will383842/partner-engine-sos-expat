<?php

namespace App\Services;

use App\Models\Subscriber;

class SubscriberService
{
    public function __construct(
        protected FirebaseService $firebase,
        protected AuditService $audit,
    ) {}

    // TODO Phase 3: create, update, delete, suspend, reactivate subscribers
    // TODO: sync Firestore partner_subscribers on each mutation
    // TODO: generate invite_token, dispatch invitation email
}
