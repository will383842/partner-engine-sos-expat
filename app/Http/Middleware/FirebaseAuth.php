<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FirebaseAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // TODO Phase 2: Verify Firebase ID token via kreait/firebase-php
        // Extract Bearer token → verify → set request attributes (uid, email, role)
        return response()->json(['error' => 'Not implemented yet'], 501);
    }
}
