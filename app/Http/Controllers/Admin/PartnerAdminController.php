<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\AuditLog;
use App\Models\EmailTemplate;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerAdminController extends Controller
{
    public function __construct(protected AuditService $audit)
    {
    }

    /**
     * GET /api/admin/partners — list all partners with stats
     */
    public function index(Request $request): JsonResponse
    {
        // Get all distinct partner IDs from agreements
        $partners = Agreement::whereNull('deleted_at')
            ->select('partner_firebase_id', 'partner_name')
            ->groupBy('partner_firebase_id', 'partner_name')
            ->get()
            ->map(function ($agreement) {
                $partnerId = $agreement->partner_firebase_id;

                $activeAgreement = Agreement::where('partner_firebase_id', $partnerId)
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->first();

                $subscriberCount = Subscriber::where('partner_firebase_id', $partnerId)
                    ->whereNull('deleted_at')
                    ->count();

                $callsThisMonth = SubscriberActivity::where('partner_firebase_id', $partnerId)
                    ->where('type', 'call_completed')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count();

                $revenueThisMonth = SubscriberActivity::where('partner_firebase_id', $partnerId)
                    ->where('type', 'call_completed')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->sum('commission_earned_cents');

                return [
                    'partner_firebase_id' => $partnerId,
                    'partner_name' => $agreement->partner_name,
                    'agreement_status' => $activeAgreement?->status ?? 'none',
                    'agreement_name' => $activeAgreement?->name,
                    'subscribers_count' => $subscriberCount,
                    'calls_this_month' => $callsThisMonth,
                    'revenue_this_month_cents' => $revenueThisMonth,
                ];
            });

        return response()->json(['data' => $partners]);
    }

    /**
     * GET /api/admin/partners/{id} — partner detail with agreement + stats
     */
    public function show(string $id): JsonResponse
    {
        $agreements = Agreement::where('partner_firebase_id', $id)
            ->whereNull('deleted_at')
            ->withCount(['subscribers' => fn($q) => $q->whereNull('deleted_at')])
            ->orderByDesc('created_at')
            ->get();

        $subscriberCount = Subscriber::where('partner_firebase_id', $id)
            ->whereNull('deleted_at')
            ->count();

        $statusBreakdown = Subscriber::where('partner_firebase_id', $id)
            ->whereNull('deleted_at')
            ->groupBy('status')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->pluck('count', 'status');

        return response()->json([
            'partner_firebase_id' => $id,
            'agreements' => $agreements,
            'total_subscribers' => $subscriberCount,
            'subscribers_by_status' => $statusBreakdown,
        ]);
    }

    /**
     * GET /api/admin/partners/{id}/activity
     */
    public function activity(Request $request, string $id): JsonResponse
    {
        $query = SubscriberActivity::where('partner_firebase_id', $id)
            ->with('subscriber:id,email,first_name,last_name');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $activities = $query->orderByDesc('created_at')
            ->cursorPaginate($perPage);

        return response()->json($activities);
    }

    /**
     * GET /api/admin/partners/{id}/email-templates
     */
    public function emailTemplates(string $id): JsonResponse
    {
        $templates = EmailTemplate::where('partner_firebase_id', $id)->get();
        return response()->json(['data' => $templates]);
    }

    /**
     * PUT /api/admin/partners/{id}/email-templates/{type}
     */
    public function updateEmailTemplate(Request $request, string $id, string $type): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:500',
            'body_html' => 'required|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $template = EmailTemplate::updateOrCreate(
            ['partner_firebase_id' => $id, 'type' => $type],
            $validated,
        );

        $this->audit->log(
            $request->attributes->get('firebase_uid'),
            'admin',
            'email_template.updated',
            'email_template',
            $template->id,
            ['type' => $type, 'partner_firebase_id' => $id],
            $request->ip(),
        );

        return response()->json($template);
    }

    /**
     * DELETE /api/admin/partners/{id}/email-templates/{type}
     */
    public function deleteEmailTemplate(Request $request, string $id, string $type): JsonResponse
    {
        $deleted = EmailTemplate::where('partner_firebase_id', $id)
            ->where('type', $type)
            ->delete();

        if ($deleted) {
            $this->audit->log(
                $request->attributes->get('firebase_uid'),
                'admin',
                'email_template.deleted',
                'email_template',
                null,
                ['type' => $type, 'partner_firebase_id' => $id],
                $request->ip(),
            );
        }

        return response()->json(['message' => 'Template deleted (reverted to default)']);
    }

    /**
     * GET /api/admin/partners/{id}/audit-log
     */
    public function auditLog(Request $request, string $id): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);

        $logs = AuditLog::where(function ($q) use ($id) {
            $q->where('actor_firebase_id', $id)
              ->orWhere('details->partner_firebase_id', $id);
        })
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage);

        return response()->json($logs);
    }
}
