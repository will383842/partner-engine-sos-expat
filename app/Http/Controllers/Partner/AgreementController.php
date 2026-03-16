<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgreementController extends Controller
{
    /**
     * GET /api/partner/agreement — active agreement for this partner
     */
    public function show(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');

        $agreement = Agreement::where('partner_firebase_id', $partnerId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->latest()
            ->first();

        if (!$agreement) {
            return response()->json(['message' => 'No active agreement'], 404);
        }

        $agreement->loadCount(['subscribers' => function ($q) {
            $q->whereNull('deleted_at');
        }]);

        return response()->json($agreement);
    }
}
