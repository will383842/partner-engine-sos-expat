<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\SubscriberActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriberSelfController extends Controller
{
    /**
     * GET /api/subscriber/me — subscriber profile with discount info
     */
    public function me(Request $request): JsonResponse
    {
        $subscriber = $request->attributes->get('subscriber');
        $subscriber->load('agreement:id,name,discount_type,discount_value,discount_label,status');

        return response()->json([
            'id' => $subscriber->id,
            'email' => $subscriber->email,
            'first_name' => $subscriber->first_name,
            'last_name' => $subscriber->last_name,
            'status' => $subscriber->status,
            'affiliate_code' => $subscriber->affiliate_code,
            'total_calls' => $subscriber->total_calls,
            'total_discount_cents' => $subscriber->total_discount_cents,
            'agreement' => $subscriber->agreement ? [
                'name' => $subscriber->agreement->name,
                'discount_type' => $subscriber->agreement->discount_type,
                'discount_value' => $subscriber->agreement->discount_value,
                'discount_label' => $subscriber->agreement->discount_label,
                'is_active' => $subscriber->agreement->status === 'active',
            ] : null,
        ]);
    }

    /**
     * GET /api/subscriber/activity — own call history
     */
    public function activity(Request $request): JsonResponse
    {
        $subscriber = $request->attributes->get('subscriber');

        $perPage = min((int) $request->input('per_page', 20), 50);
        $activities = SubscriberActivity::where('subscriber_id', $subscriber->id)
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage);

        return response()->json($activities);
    }
}
