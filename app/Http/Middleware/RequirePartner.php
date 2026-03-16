<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePartner
{
    public function handle(Request $request, Closure $next): Response
    {
        // TODO Phase 2: Check partners/{uid} doc exists and is active in Firestore
        return response()->json(['error' => 'Not implemented yet'], 501);
    }
}
