<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SubscriberSelfController extends Controller
{
    public function me(): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function activity(): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }
}
