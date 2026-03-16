<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsAdminController extends Controller
{
    public function __construct(protected StatsService $stats)
    {
    }

    /**
     * GET /api/admin/stats — global partner program stats
     */
    public function index(): JsonResponse
    {
        return response()->json($this->stats->globalStats());
    }

    /**
     * GET /api/admin/audit-log — global audit log
     */
    public function auditLog(Request $request): JsonResponse
    {
        $query = AuditLog::query();

        if ($request->filled('actor')) {
            $query->where('actor_firebase_id', $request->input('actor'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->input('action')}%");
        }

        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->input('resource_type'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $logs = $query->orderByDesc('created_at')
            ->cursorPaginate($perPage);

        return response()->json($logs);
    }
}
