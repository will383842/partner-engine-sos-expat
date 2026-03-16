<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSubscriber
{
    public function handle(Request $request, Closure $next): Response
    {
        // TODO Phase 2: Check firebase_uid exists in subscribers table
        return response()->json(['error' => 'Not implemented yet'], 501);
    }
}
