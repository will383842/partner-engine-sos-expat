<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function __construct(protected StatsService $stats)
    {
    }

    /**
     * GET /api/partner/stats — monthly detailed stats
     */
    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');
        $months = (int) $request->input('months', 12);

        $monthlyStats = $this->stats->partnerMonthlyStats($partnerId, $months);

        return response()->json(['monthly' => $monthlyStats]);
    }
}
