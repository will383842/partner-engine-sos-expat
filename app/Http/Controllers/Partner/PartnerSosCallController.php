<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\PartnerInvoice;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Endpoints for the partner dashboard SOS-Call activity views:
 *
 *   GET  /partner/sos-call/activity/kpis            — KPIs du mois en cours
 *   GET  /partner/sos-call/activity/timeline        — Évolution 12 mois
 *   GET  /partner/sos-call/activity/breakdown       — Répartition types + pays
 *   GET  /partner/sos-call/activity/top-subscribers — Top 20 subscribers par usage
 *   GET  /partner/sos-call/activity/calls           — Historique paginé
 *   GET  /partner/sos-call/activity/export          — Export CSV
 *
 *   GET  /partner/sos-call/invoices                 — Liste factures
 *   GET  /partner/sos-call/invoices/{id}            — Détail facture
 *   GET  /partner/sos-call/invoices/{id}/pdf        — Télécharger PDF
 *
 * All endpoints require:
 *   - firebase.auth (valid Firebase ID token)
 *   - require.partner (role=partner)
 *   - throttle:partner (60/min per Firebase UID)
 */
class PartnerSosCallController extends Controller
{
    /**
     * GET /partner/sos-call/activity/kpis
     *
     * Returns KPIs for the current month.
     */
    public function kpis(Request $request): JsonResponse
    {
        $partnerFirebaseId = $request->attributes->get('partner_firebase_id');
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        // Active subscribers (SOS-Call activated this month or before, not expired)
        $activeSubscribers = Subscriber::where('partner_firebase_id', $partnerFirebaseId)
            ->where('status', 'active')
            ->whereNotNull('sos_call_code')
            ->where('sos_call_activated_at', '<=', $monthEnd)
            ->where(function ($q) use ($monthStart) {
                $q->whereNull('sos_call_expires_at')
                  ->orWhere('sos_call_expires_at', '>=', $monthStart);
            })
            ->count();

        // Calls this month (SOS-Call only)
        $callsQuery = SubscriberActivity::where('partner_firebase_id', $partnerFirebaseId)
            ->where('type', 'call_completed')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->whereRaw("(metadata->>'is_sos_call')::boolean = true");

        $callsExpert = (clone $callsQuery)->where('provider_type', 'expat')->count();
        $callsLawyer = (clone $callsQuery)->where('provider_type', 'lawyer')->count();
        $totalCalls = $callsExpert + $callsLawyer;

        // Usage rate = unique callers / active subscribers
        $uniqueCallers = (clone $callsQuery)->distinct('subscriber_id')->count('subscriber_id');
        $usageRate = $activeSubscribers > 0
            ? round(($uniqueCallers / $activeSubscribers) * 100, 2)
            : 0;

        // Estimated invoice for the month
        $agreement = Agreement::where('partner_firebase_id', $partnerFirebaseId)
            ->where('sos_call_active', true)
            ->where('status', 'active')
            ->latest()
            ->first();

        // Total = monthly flat fee + (subscribers × per-member rate)
        // Covers the 3 billing models: per-member only, flat fee only, hybrid.
        $estimatedInvoice = $agreement
            ? round(
                ((float) ($agreement->monthly_base_fee ?? 0))
                + ($activeSubscribers * (float) $agreement->billing_rate),
                2
            )
            : 0;

        return response()->json([
            'period' => now()->format('Y-m'),
            'active_subscribers' => $activeSubscribers,
            'calls_expert' => $callsExpert,
            'calls_lawyer' => $callsLawyer,
            'total_calls' => $totalCalls,
            'unique_callers' => $uniqueCallers,
            'usage_rate_percent' => $usageRate,
            'estimated_invoice' => $estimatedInvoice,
            'billing_currency' => $agreement?->billing_currency ?? 'EUR',
            'billing_rate' => (float) ($agreement?->billing_rate ?? 0),
            'monthly_base_fee' => (float) ($agreement?->monthly_base_fee ?? 0),
            'next_invoice_date' => now()->addMonth()->startOfMonth()->toDateString(),
        ]);
    }

    /**
     * GET /partner/sos-call/activity/timeline?months=12
     *
     * Returns a time series of monthly call counts for charting.
     */
    public function timeline(Request $request): JsonResponse
    {
        $partnerFirebaseId = $request->attributes->get('partner_firebase_id');
        $months = min(24, max(1, (int) $request->input('months', 12)));

        $startDate = now()->subMonths($months - 1)->startOfMonth();

        // Group by month and provider_type
        $rows = SubscriberActivity::where('partner_firebase_id', $partnerFirebaseId)
            ->where('type', 'call_completed')
            ->whereRaw("(metadata->>'is_sos_call')::boolean = true")
            ->where('created_at', '>=', $startDate)
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, provider_type, COUNT(*) as count")
            ->groupByRaw("to_char(created_at, 'YYYY-MM'), provider_type")
            ->orderByRaw("to_char(created_at, 'YYYY-MM') ASC")
            ->get();

        // Build a complete series with zero-fill
        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $month = now()->subMonths($months - 1 - $i)->format('Y-m');
            $series[$month] = ['month' => $month, 'expat' => 0, 'lawyer' => 0];
        }

        foreach ($rows as $row) {
            if (isset($series[$row->month])) {
                if ($row->provider_type === 'expat') {
                    $series[$row->month]['expat'] = (int) $row->count;
                } elseif ($row->provider_type === 'lawyer') {
                    $series[$row->month]['lawyer'] = (int) $row->count;
                }
            }
        }

        return response()->json([
            'months' => $months,
            'data' => array_values($series),
        ]);
    }

    /**
     * GET /partner/sos-call/activity/breakdown
     *
     * Returns breakdown by call type (pie) and top 10 countries (bar).
     */
    public function breakdown(Request $request): JsonResponse
    {
        $partnerFirebaseId = $request->attributes->get('partner_firebase_id');
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $base = SubscriberActivity::where('partner_firebase_id', $partnerFirebaseId)
            ->where('type', 'call_completed')
            ->whereRaw("(metadata->>'is_sos_call')::boolean = true")
            ->whereBetween('created_at', [$monthStart, $monthEnd]);

        // By call type (pie chart)
        $byType = (clone $base)
            ->selectRaw('provider_type, COUNT(*) as count')
            ->groupBy('provider_type')
            ->pluck('count', 'provider_type')
            ->toArray();

        // Top 10 countries (from subscriber country, joined)
        $byCountry = (clone $base)
            ->join('subscribers', 'subscriber_activities.subscriber_id', '=', 'subscribers.id')
            ->selectRaw('subscribers.country, COUNT(*) as count')
            ->groupBy('subscribers.country')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn($r) => ['country' => $r->country ?? 'XX', 'count' => (int) $r->count]);

        return response()->json([
            'period' => now()->format('Y-m'),
            'by_type' => [
                'expat' => (int) ($byType['expat'] ?? 0),
                'lawyer' => (int) ($byType['lawyer'] ?? 0),
            ],
            'top_countries' => $byCountry,
        ]);
    }

    /**
     * GET /partner/sos-call/activity/hierarchy?dimension=group_label
     *
     * Breakdown of subscribers + calls grouped by a hierarchy dimension:
     * group_label (cabinet), region, or department. Useful for big partners
     * (insurance companies, banks) with multiple sub-entities.
     *
     * Query params:
     *   - dimension: one of 'group_label' | 'region' | 'department' (default: 'group_label')
     *   - period: 'month' | '3months' | '12months' (default: 'month')
     */
    public function hierarchy(Request $request): JsonResponse
    {
        $partnerFirebaseId = $request->attributes->get('partner_firebase_id');
        $dimension = $request->input('dimension', 'group_label');
        $allowed = ['group_label', 'region', 'department'];
        if (!in_array($dimension, $allowed, true)) {
            return response()->json(['error' => 'invalid_dimension', 'allowed' => $allowed], 422);
        }

        $period = $request->input('period', 'month');
        $startDate = match ($period) {
            '3months' => now()->subMonths(3)->startOfMonth(),
            '12months' => now()->subMonths(12)->startOfMonth(),
            default => now()->startOfMonth(),
        };

        // Subscribers grouped by dimension
        $subs = Subscriber::where('partner_firebase_id', $partnerFirebaseId)
            ->whereNotNull('sos_call_code')
            ->selectRaw("COALESCE({$dimension}, '(non défini)') as label, COUNT(*) as total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active")
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();

        // Calls grouped by dimension (join subscribers)
        $driver = DB::connection()->getDriverName();
        $callsQuery = DB::table('subscriber_activities as sa')
            ->join('subscribers as s', 'sa.subscriber_id', '=', 's.id')
            ->where('sa.partner_firebase_id', $partnerFirebaseId)
            ->where('sa.type', 'call_completed')
            ->where('sa.created_at', '>=', $startDate);

        if ($driver === 'pgsql') {
            $callsQuery->whereRaw("(sa.metadata->>'is_sos_call')::boolean = true");
        } else {
            $callsQuery->where('sa.metadata', 'like', '%"is_sos_call":true%');
        }

        $calls = $callsQuery
            ->selectRaw("COALESCE(s.{$dimension}, '(non défini)') as label,
                         SUM(CASE WHEN sa.provider_type='expat' THEN 1 ELSE 0 END) as expat_calls,
                         SUM(CASE WHEN sa.provider_type='lawyer' THEN 1 ELSE 0 END) as lawyer_calls,
                         COUNT(*) as total_calls")
            ->groupBy('label')
            ->get()
            ->keyBy('label');

        // Merge
        $rows = $subs->map(function ($sub) use ($calls) {
            $c = $calls->get($sub->label);
            return [
                'label' => $sub->label,
                'subscribers_total' => (int) $sub->total,
                'subscribers_active' => (int) $sub->active,
                'calls_expert' => (int) ($c->expat_calls ?? 0),
                'calls_lawyer' => (int) ($c->lawyer_calls ?? 0),
                'calls_total' => (int) ($c->total_calls ?? 0),
            ];
        });

        return response()->json([
            'dimension' => $dimension,
            'period' => $period,
            'rows' => $rows,
            'total_subscribers' => $rows->sum('subscribers_total'),
            'total_calls' => $rows->sum('calls_total'),
        ]);
    }

    /**
     * GET /partner/sos-call/activity/top-subscribers?period=month&limit=20
     *
     * Returns subscribers with the most SOS-Call usage.
     */
    public function topSubscribers(Request $request): JsonResponse
    {
        $partnerFirebaseId = $request->attributes->get('partner_firebase_id');
        $period = $request->input('period', 'month');
        $limit = min(50, max(5, (int) $request->input('limit', 20)));

        $startDate = match ($period) {
            '3months' => now()->subMonths(3)->startOfMonth(),
            '12months' => now()->subMonths(12)->startOfMonth(),
            default => now()->startOfMonth(),
        };

        $topSubs = DB::table('subscribers')
            ->leftJoin('subscriber_activities', function ($j) use ($startDate) {
                $j->on('subscribers.id', '=', 'subscriber_activities.subscriber_id')
                  ->where('subscriber_activities.type', 'call_completed')
                  ->where('subscriber_activities.created_at', '>=', $startDate)
                  ->whereRaw("(subscriber_activities.metadata->>'is_sos_call')::boolean = true");
            })
            ->where('subscribers.partner_firebase_id', $partnerFirebaseId)
            ->whereNotNull('subscribers.sos_call_code')
            ->whereNull('subscribers.deleted_at')
            ->groupBy(
                'subscribers.id', 'subscribers.first_name', 'subscribers.last_name',
                'subscribers.email', 'subscribers.sos_call_code', 'subscribers.country'
            )
            ->selectRaw("
                subscribers.id,
                subscribers.first_name,
                subscribers.last_name,
                subscribers.email,
                subscribers.sos_call_code,
                subscribers.country,
                COUNT(CASE WHEN subscriber_activities.provider_type = 'expat' THEN 1 END) as calls_expert_period,
                COUNT(CASE WHEN subscriber_activities.provider_type = 'lawyer' THEN 1 END) as calls_lawyer_period,
                COUNT(subscriber_activities.id) as total_calls_period
            ")
            ->orderByDesc('total_calls_period')
            ->orderBy('subscribers.last_name')
            ->limit($limit)
            ->get();

        $grandTotal = $topSubs->sum('total_calls_period');

        return response()->json([
            'period' => $period,
            'total_calls' => $grandTotal,
            'subscribers' => $topSubs->map(function ($s) use ($grandTotal) {
                return [
                    'id' => $s->id,
                    'full_name' => trim("{$s->first_name} {$s->last_name}"),
                    'email' => $s->email,
                    'sos_call_code' => $s->sos_call_code,
                    'country' => $s->country,
                    'calls_expert' => (int) $s->calls_expert_period,
                    'calls_lawyer' => (int) $s->calls_lawyer_period,
                    'total_calls' => (int) $s->total_calls_period,
                    'percent_of_total' => $grandTotal > 0
                        ? round(($s->total_calls_period / $grandTotal) * 100, 1)
                        : 0,
                ];
            }),
        ]);
    }

    /**
     * GET /partner/sos-call/activity/calls?cursor=xxx&limit=50&type=lawyer&country=FR
     *
     * Paginated SOS-Call history with filters.
     */
    public function callsHistory(Request $request): JsonResponse
    {
        $partnerFirebaseId = $request->attributes->get('partner_firebase_id');
        $limit = min(100, max(10, (int) $request->input('limit', 50)));
        $cursor = $request->input('cursor'); // last call id

        $query = SubscriberActivity::with(['subscriber:id,first_name,last_name,email,sos_call_code,country'])
            ->where('partner_firebase_id', $partnerFirebaseId)
            ->where('type', 'call_completed')
            ->whereRaw("(metadata->>'is_sos_call')::boolean = true");

        if ($request->filled('type') && in_array($request->input('type'), ['lawyer', 'expat'])) {
            $query->where('provider_type', $request->input('type'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        if ($cursor) {
            $query->where('id', '<', (int) $cursor);
        }

        $calls = $query->orderByDesc('id')->limit($limit + 1)->get();

        $hasMore = $calls->count() > $limit;
        if ($hasMore) {
            $calls = $calls->take($limit);
        }

        $nextCursor = $hasMore ? (string) $calls->last()->id : null;

        return response()->json([
            'calls' => $calls->map(fn($c) => [
                'id' => $c->id,
                'created_at' => $c->created_at->toIso8601String(),
                'provider_type' => $c->provider_type,
                'duration_seconds' => $c->call_duration_seconds,
                'subscriber' => $c->subscriber ? [
                    'id' => $c->subscriber->id,
                    'full_name' => trim("{$c->subscriber->first_name} {$c->subscriber->last_name}"),
                    'email' => $c->subscriber->email,
                    'sos_call_code' => $c->subscriber->sos_call_code,
                    'country' => $c->subscriber->country,
                ] : null,
            ]),
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ]);
    }

    /**
     * GET /partner/sos-call/activity/export
     *
     * Streams a CSV of ALL SOS-Call activities for the partner (last 12 months).
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $partnerFirebaseId = $request->attributes->get('partner_firebase_id');
        $startDate = now()->subMonths(12);

        $filename = 'sos-call-activity-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($partnerFirebaseId, $startDate) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Date', 'Heure', 'Code SOS-Call', 'Email', 'Nom', 'Pays',
                'Type', 'Durée (s)',
            ]);

            SubscriberActivity::with('subscriber')
                ->where('partner_firebase_id', $partnerFirebaseId)
                ->where('type', 'call_completed')
                ->whereRaw("(metadata->>'is_sos_call')::boolean = true")
                ->where('created_at', '>=', $startDate)
                ->orderByDesc('created_at')
                ->chunk(1000, function ($chunk) use ($handle) {
                    foreach ($chunk as $activity) {
                        $sub = $activity->subscriber;
                        fputcsv($handle, [
                            $activity->created_at->format('Y-m-d'),
                            $activity->created_at->format('H:i:s'),
                            $sub?->sos_call_code ?? '',
                            $sub?->email ?? '',
                            trim("{$sub?->first_name} {$sub?->last_name}"),
                            $sub?->country ?? '',
                            $activity->provider_type ?? '',
                            $activity->call_duration_seconds ?? 0,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * GET /partner/sos-call/invoices
     *
     * Paginated list of invoices for this partner.
     */
    public function invoices(Request $request): JsonResponse
    {
        $partnerFirebaseId = $request->attributes->get('partner_firebase_id');
        $limit = min(50, max(10, (int) $request->input('limit', 20)));

        $query = PartnerInvoice::forPartner($partnerFirebaseId)
            ->orderByDesc('period');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('year')) {
            $query->where('period', 'like', $request->input('year') . '-%');
        }

        $invoices = $query->paginate($limit);

        // Summary stats
        $summary = [
            'total_invoices' => PartnerInvoice::forPartner($partnerFirebaseId)->count(),
            'paid' => PartnerInvoice::forPartner($partnerFirebaseId)->paid()->count(),
            'pending' => PartnerInvoice::forPartner($partnerFirebaseId)->pending()->count(),
            'overdue' => PartnerInvoice::forPartner($partnerFirebaseId)->overdue()->count(),
            'total_amount_paid' => PartnerInvoice::forPartner($partnerFirebaseId)->paid()->sum('total_amount'),
            'total_amount_pending' => PartnerInvoice::forPartner($partnerFirebaseId)->pending()->sum('total_amount'),
        ];

        return response()->json([
            'summary' => $summary,
            'invoices' => $invoices->items(),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /**
     * GET /partner/sos-call/invoices/{id}
     *
     * Detail of a single invoice.
     */
    public function showInvoice(Request $request, int $id): JsonResponse
    {
        $partnerFirebaseId = $request->attributes->get('partner_firebase_id');

        $invoice = PartnerInvoice::forPartner($partnerFirebaseId)->findOrFail($id);

        return response()->json([
            'invoice' => $invoice->load('agreement:id,partner_name,billing_rate,monthly_base_fee,billing_currency,payment_terms_days'),
        ]);
    }

    /**
     * GET /partner/sos-call/invoices/{id}/pdf
     *
     * Returns the generated invoice PDF.
     */
    public function downloadInvoicePdf(Request $request, int $id): Response|StreamedResponse
    {
        $partnerFirebaseId = $request->attributes->get('partner_firebase_id');

        $invoice = PartnerInvoice::forPartner($partnerFirebaseId)->findOrFail($id);

        if (!$invoice->pdf_path || !Storage::disk('local')->exists($invoice->pdf_path)) {
            return response()->json(['error' => 'PDF not available yet'], 404);
        }

        return Storage::disk('local')->download(
            $invoice->pdf_path,
            "facture-sos-call-{$invoice->invoice_number}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }
}
