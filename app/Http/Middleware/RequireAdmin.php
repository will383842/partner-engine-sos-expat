<?php

namespace App\Http\Middleware;

use App\Services\FirebaseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RequireAdmin
{
    public function __construct(protected FirebaseService $firebase)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $uid = $request->attributes->get('firebase_uid');

        if (!$uid) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Cache user doc for 5 minutes
        $user = Cache::remember("user:{$uid}", 300, function () use ($uid) {
            return $this->firebase->getDocument('users', $uid);
        });

        if (!$user || ($user['role'] ?? null) !== 'admin') {
            return response()->json(['error' => 'Forbidden — admin role required'], 403);
        }

        $request->attributes->set('admin_firebase_id', $uid);

        return $next($request);
    }
}
