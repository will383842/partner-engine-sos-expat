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
     * GET /api/admin/partners — list all partners with stats (optimized: no N+1)
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Get all distinct partners with their latest active agreement
        $partners = Agreement::whereNull('deleted_at')
            ->select('partner_firebase_id', 'partner_name')
            ->groupBy('partner_firebase_id', 'partner_name')
            ->get();

        if ($partners->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $partnerIds = $partners->pluck('partner_firebase_id')->toArray();

        // 2. Get active agreements keyed by partner_firebase_id (single query)
        $activeAgreements = Agreement::whereIn('partner_firebase_id', $partnerIds)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('partner_firebase_id');

        // 3. Subscriber counts per partner (single query)
        $subscriberCounts = Subscriber::whereIn('partner_firebase_id', $partnerIds)
            ->whereNull('deleted_at')
            ->groupBy('partner_firebase_id')
            ->select('partner_firebase_id', DB::raw('COUNT(*) as count'))
            ->pluck('count', 'partner_firebase_id');

        // 4. Calls this month per partner (single query)
        $callStats = SubscriberActivity::whereIn('partner_firebase_id', $partnerIds)
            ->where('type', 'call_completed')
            ->where('created_at', '>=', now()->startOfMonth())
            ->groupBy('partner_firebase_id')
            ->select(
                'partner_firebase_id',
                DB::raw('COUNT(*) as calls'),
                DB::raw('COALESCE(SUM(commission_earned_cents), 0) as revenue'),
            )
            ->get()
            ->keyBy('partner_firebase_id');

        // 5. Assemble results (no additional queries)
        $data = $partners->map(function ($partner) use ($activeAgreements, $subscriberCounts, $callStats) {
            $pid = $partner->partner_firebase_id;
            $activeAgreement = $activeAgreements->get($pid);
            $stats = $callStats->get($pid);

            return [
                'partner_firebase_id' => $pid,
                'partner_name' => $partner->partner_name,
                'agreement_status' => $activeAgreement?->status ?? 'none',
                'agreement_name' => $activeAgreement?->name,
                'subscribers_count' => $subscriberCounts->get($pid, 0),
                'calls_this_month' => $stats?->calls ?? 0,
                'revenue_this_month_cents' => $stats?->revenue ?? 0,
            ];
        });

        return response()->json(['data' => $data]);
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
