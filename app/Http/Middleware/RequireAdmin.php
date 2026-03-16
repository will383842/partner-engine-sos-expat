<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // TODO Phase 2: Check users/{uid}.role == 'admin' in Firestore
        return response()->json(['error' => 'Not implemented yet'], 501);
    }
}
