<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditService
{
    /**
     * Log an auditable action.
     */
    public function log(
        string $actorFirebaseId,
        string $actorRole,
        string $action,
        ?string $resourceType = null,
        ?int $resourceId = null,
        array $details = [],
        ?string $ipAddress = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_firebase_id' => $actorFirebaseId,
            'actor_role' => $actorRole,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'details' => $details,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }
}
