<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $request->header('X-Engine-Secret');

        if (!$secret || !hash_equals((string) config('services.engine_api_key'), $secret)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
