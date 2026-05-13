<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogService
{
    /**
     * Record an audit log entry for a sensitive action.
     */
    public function log(
        ?string $actorId,
        string $action,
        string $entityType,
        string $entityId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $context = null,
        ?Request $request = null
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'context' => $context,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? substr($request->userAgent(), 0, 500) : null,
        ]);
    }
}
