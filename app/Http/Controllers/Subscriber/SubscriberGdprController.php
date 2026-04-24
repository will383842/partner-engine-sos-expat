<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RGPD endpoints for subscribers — right to access + right to be forgotten.
 *
 * These endpoints are called from the /mon-acces Blade dashboard with a
 * valid session. All actions are logged in audit_logs for compliance.
 */
class SubscriberGdprController extends Controller
{
    /**
     * Export all data held about this subscriber (RGPD Article 15).
     */
    public function export(Request $request): JsonResponse
    {
        $subscriberId = $request->session()->get('subscriber_id');
        if (!$subscriberId) {
            return response()->json(['error' => 'not_authenticated'], 401);
        }

        $subscriber = Subscriber::with('agreement')->find($subscriberId);
        if (!$subscriber) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $activities = SubscriberActivity::where('subscriber_id', $subscriber->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($a) {
                return [
                    'type' => $a->type,
                    'provider_type' => $a->provider_type,
                    'duration_seconds' => $a->duration_seconds,
                    'created_at' => $a->created_at?->toIso8601String(),
                ];
            });

        AuditLog::create([
            'actor_firebase_id' => 'subscriber:' . $subscriber->id,
            'actor_role' => 'subscriber',
            'action' => 'gdpr_data_export',
            'resource_type' => 'subscriber',
            'resource_id' => (string) $subscriber->id,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        $data = [
            'exported_at' => now()->toIso8601String(),
            'subscriber' => [
                'id' => $subscriber->id,
                'first_name' => $subscriber->first_name,
                'last_name' => $subscriber->last_name,
                'email' => $subscriber->email,
                'phone' => $subscriber->phone,
                'country' => $subscriber->country,
                'language' => $subscriber->language,
                'sos_call_code' => $subscriber->sos_call_code,
                'sos_call_activated_at' => $subscriber->sos_call_activated_at?->toIso8601String(),
                'sos_call_expires_at' => $subscriber->sos_call_expires_at?->toIso8601String(),
                'calls_expert' => $subscriber->calls_expert,
                'calls_lawyer' => $subscriber->calls_lawyer,
                'status' => $subscriber->status,
                'created_at' => $subscriber->created_at?->toIso8601String(),
            ],
            'agreement' => $subscriber->agreement ? [
                'partner_name' => $subscriber->agreement->partner_name,
                'sos_call_active' => $subscriber->agreement->sos_call_active,
                'expires_at' => $subscriber->agreement->expires_at?->toIso8601String(),
            ] : null,
            'activities' => $activities,
        ];

        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="sos-expat-data-export.json"');
    }

    /**
     * Delete/anonymize subscriber data (RGPD Article 17 — right to be forgotten).
     * Activities are kept (anonymized) for accounting/audit purposes.
     */
    public function delete(Request $request): JsonResponse
    {
        $subscriberId = $request->session()->get('subscriber_id');
        if (!$subscriberId) {
            return response()->json(['error' => 'not_authenticated'], 401);
        }

        $subscriber = Subscriber::find($subscriberId);
        if (!$subscriber) {
            return response()->json(['error' => 'not_found'], 404);
        }

        DB::transaction(function () use ($subscriber, $request) {
            // Anonymize
            $originalEmail = $subscriber->email;
            $subscriber->update([
                'first_name' => 'Deleted',
                'last_name' => 'User',
                'email' => "deleted-{$subscriber->id}@deleted.local",
                'phone' => null,
                'status' => 'suspended',
            ]);

            AuditLog::create([
                'actor_firebase_id' => 'subscriber:' . $subscriber->id,
                'actor_role' => 'subscriber',
                'action' => 'gdpr_data_deletion',
                'resource_type' => 'subscriber',
                'resource_id' => (string) $subscriber->id,
                'details' => ['anonymized_email_hash' => sha1($originalEmail)],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            Log::info('[RGPD] Subscriber data anonymized', [
                'subscriber_id' => $subscriber->id,
            ]);
        });

        // Clear session
        $request->session()->flush();

        return response()->json([
            'deleted' => true,
            'message' => 'Vos données ont été anonymisées. Les historiques comptables sont conservés selon obligation légale.',
        ]);
    }
}
