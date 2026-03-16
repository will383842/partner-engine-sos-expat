<?php

namespace App\Http\Middleware;

use App\Services\FirebaseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class FirebaseAuth
{
    public function __construct(protected FirebaseService $firebase)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Unauthorized — missing Bearer token'], 401);
        }

        try {
            $claims = $this->firebase->verifyIdToken($token);

            // Attach Firebase user info to the request for downstream middleware/controllers
            $request->attributes->set('firebase_uid', $claims['uid']);
            $request->attributes->set('firebase_email', $claims['email'] ?? null);

            return $next($request);
        } catch (\Exception $e) {
            Log::warning('Firebase auth failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Unauthorized — invalid token'], 401);
        }
    }
}
