<?php

namespace App\Http\Middleware;

use App\Models\Subscriber;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSubscriber
{
    public function handle(Request $request, Closure $next): Response
    {
        $uid = $request->attributes->get('firebase_uid');

        if (!$uid) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Find subscriber linked to this Firebase user
        $subscriber = Subscriber::where('firebase_uid', $uid)
            ->whereNull('deleted_at')
            ->first();

        if (!$subscriber) {
            return response()->json(['error' => 'Forbidden — not a subscriber'], 403);
        }

        $request->attributes->set('subscriber', $subscriber);
        $request->attributes->set('subscriber_id', $subscriber->id);

        return $next($request);
    }
}
