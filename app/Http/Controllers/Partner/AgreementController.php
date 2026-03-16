<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AgreementController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }
}
