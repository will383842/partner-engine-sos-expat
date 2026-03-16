<?php

namespace App\Http\Middleware;

use App\Services\FirebaseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequirePartner
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

        // Cache partner doc for 5 minutes to avoid hammering Firestore
        $partner = Cache::remember("partner:{$uid}", 300, function () use ($uid) {
            return $this->firebase->getDocument('partners', $uid);
        });

        if (!$partner) {
            return response()->json(['error' => 'Forbidden — not a partner'], 403);
        }

        if (($partner['status'] ?? null) !== 'active' && ($partner['status'] ?? null) !== 'approved') {
            return response()->json(['error' => 'Forbidden — partner account not active'], 403);
        }

        // Attach partner data to request
        $request->attributes->set('partner_data', $partner);
        $request->attributes->set('partner_firebase_id', $uid);

        return $next($request);
    }
}
