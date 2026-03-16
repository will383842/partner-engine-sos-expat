<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function callCompleted(Request $request): JsonResponse
    {
        // TODO Phase 2: Process call-completed webhook
        return response()->json(['status' => 'received'], 200);
    }

    public function subscriberRegistered(Request $request): JsonResponse
    {
        // TODO Phase 2: Process subscriber-registered webhook
        return response()->json(['status' => 'received'], 200);
    }
}
