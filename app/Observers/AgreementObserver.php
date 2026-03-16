<?php

namespace App\Observers;

use App\Models\Agreement;
use App\Services\AuditService;

class AgreementObserver
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function created(Agreement $agreement): void
    {
        // TODO: get actor from request context
        // $this->audit->log($actorId, $role, 'agreement.created', 'agreement', $agreement->id, [...]);
    }

    public function updated(Agreement $agreement): void
    {
        // $this->audit->log($actorId, $role, 'agreement.updated', 'agreement', $agreement->id, $agreement->getChanges());
    }

    public function deleted(Agreement $agreement): void
    {
        // $this->audit->log($actorId, $role, 'agreement.deleted', 'agreement', $agreement->id);
    }
}
