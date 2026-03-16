<?php

namespace App\Services;

use App\Models\Agreement;

class AgreementService
{
    public function __construct(
        protected FirebaseService $firebase,
        protected AuditService $audit,
    ) {}

    // TODO Phase 3: create, update, delete, renew, expire agreements
    // TODO: sync Firestore partner_subscribers when agreement status changes
}
