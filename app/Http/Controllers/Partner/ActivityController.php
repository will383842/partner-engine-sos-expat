<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\SubscriberActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * GET /api/partner/activity — recent activity timeline
     */
    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');

        $query = SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->with('subscriber:id,email,first_name,last_name');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $activities = $query->orderByDesc('created_at')
            ->cursorPaginate($perPage);

        return response()->json($activities);
    }
}
