<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected StatsService $stats)
    {
    }

    /**
     * GET /api/partner/dashboard — global stats
     */
    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');
        $dashboard = $this->stats->partnerDashboard($partnerId);

        return response()->json($dashboard);
    }

    /**
     * GET /api/partner/earnings/breakdown
     */
    public function earningsBreakdown(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');
        $breakdown = $this->stats->earningsBreakdown($partnerId);

        return response()->json($breakdown);
    }
}
