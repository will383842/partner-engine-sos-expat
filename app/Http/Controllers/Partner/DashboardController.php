<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        // TODO Phase 4
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function earningsBreakdown(): JsonResponse
    {
        // TODO Phase 4
        return response()->json(['message' => 'Not implemented yet'], 501);
    }
}
