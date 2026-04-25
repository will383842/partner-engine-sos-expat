<?php

namespace App\Filament\Partner\Pages;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Filament\Pages\Page;

/**
 * Hierarchy management page — aggregated by dimension (cabinet / region /
 * department). Designed for group partners (e.g. AXA with 300+ cabinets).
 *
 * Shows per-group KPIs and lets the partner drill down + manage.
 */
class Hierarchy extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.partner.pages.hierarchy';

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.group_clients');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.nav.hierarchy');
    }

    public function getTitle(): string
    {
        return __('panel.hierarchy.title');
    }

    public string $dimension = 'group_label';
    public ?string $drillDown = null; // value being drilled into, e.g. 'Paris'

    public function mount(): void
    {
        if (!auth()->user()?->partner_firebase_id) {
            abort(403);
        }
        $this->dimension = request()->query('dimension', 'group_label');
        $this->drillDown = request()->query('drill') ?: null;
        if (!in_array($this->dimension, ['group_label', 'region', 'department'], true)) {
            $this->dimension = 'group_label';
        }
    }

    public function updatedDimension(): void
    {
        // Reset drill when dimension changes
        $this->drillDown = null;
    }

    public function getViewData(): array
    {
        $user = auth()->user();
        $partnerId = $user->partner_firebase_id;
        $agreement = Agreement::where('partner_firebase_id', $partnerId)->first();
        $billingRate = (float) ($agreement?->billing_rate ?? 0);
        $monthlyBaseFee = (float) ($agreement?->monthly_base_fee ?? 0);
        $currencySymbol = ($agreement?->billing_currency === 'USD') ? '$' : '€';

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $rows = [];

        if ($this->drillDown === null) {
            // Aggregated view across the chosen dimension
            $groupings = Subscriber::where('partner_firebase_id', $partnerId)
                ->whereNotNull($this->dimension)
                ->groupBy($this->dimension)
                ->selectRaw("{$this->dimension} as label, COUNT(*) as subs_total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as subs_active")
                ->orderByDesc('subs_total')
                ->get();

            foreach ($groupings as $g) {
                $subIds = Subscriber::where('partner_firebase_id', $partnerId)
                    ->where($this->dimension, $g->label)
                    ->pluck('id');
                $callsMonth = SubscriberActivity::where('partner_firebase_id', $partnerId)
                    ->whereIn('subscriber_id', $subIds)
                    ->where('type', 'call_completed')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count();
                $usage = $g->subs_active > 0 ? round(($callsMonth / $g->subs_active) * 100, 1) : 0;
                $estimatedInvoice = $g->subs_active * $billingRate;

                $rows[] = [
                    'label' => $g->label,
                    'subs_total' => (int) $g->subs_total,
                    'subs_active' => (int) $g->subs_active,
                    'calls_month' => $callsMonth,
                    'usage_pct' => $usage,
                    'estimated_invoice' => $estimatedInvoice,
                ];
            }

            // Append a "Non affectés" row for subscribers with null dimension
            $unassigned = Subscriber::where('partner_firebase_id', $partnerId)
                ->whereNull($this->dimension)
                ->count();
            if ($unassigned > 0) {
                $rows[] = [
                    'label' => __('panel.hierarchy.unassigned'),
                    'subs_total' => $unassigned,
                    'subs_active' => Subscriber::where('partner_firebase_id', $partnerId)
                        ->whereNull($this->dimension)->where('status', 'active')->count(),
                    'calls_month' => 0,
                    'usage_pct' => 0,
                    'estimated_invoice' => 0,
                    'is_unassigned' => true,
                ];
            }
        }

        $drillSubscribers = [];
        if ($this->drillDown !== null) {
            $drillSubscribers = Subscriber::where('partner_firebase_id', $partnerId)
                ->where($this->dimension, $this->drillDown)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(200)
                ->get()
                ->map(function ($s) use ($partnerId, $monthStart, $monthEnd) {
                    $calls = SubscriberActivity::where('partner_firebase_id', $partnerId)
                        ->where('subscriber_id', $s->id)
                        ->where('type', 'call_completed')
                        ->whereBetween('created_at', [$monthStart, $monthEnd])
                        ->count();
                    return [
                        'id' => $s->id,
                        'name' => trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? '')) ?: $s->email,
                        'email' => $s->email,
                        'phone' => $s->phone,
                        'code' => $s->sos_call_code,
                        'status' => $s->status,
                        'calls_month' => $calls,
                    ];
                })->toArray();
        }

        // Per-row estimated_invoice represents the per-member contribution of that group.
        // The partner's monthly_base_fee is added once at the partner level (banner in view),
        // not per-row, to avoid double-counting in the displayed totals.
        $totalEstimatedInvoice = $monthlyBaseFee + array_sum(array_column($rows, 'estimated_invoice'));

        return [
            'dimension' => $this->dimension,
            'drillDown' => $this->drillDown,
            'rows' => $rows,
            'drillSubscribers' => $drillSubscribers,
            'currencySymbol' => $currencySymbol,
            'monthlyBaseFee' => $monthlyBaseFee,
            'totalEstimatedInvoice' => $totalEstimatedInvoice,
            'dimensionLabels' => [
                'group_label' => __('panel.hierarchy.dim_cabinet'),
                'region'      => __('panel.hierarchy.dim_region'),
                'department'  => __('panel.hierarchy.dim_department'),
            ],
        ];
    }

    /**
     * Livewire action: enter drill-down for a specific value.
     */
    public function drillInto(string $value): void
    {
        $this->drillDown = $value;
    }

    public function exitDrill(): void
    {
        $this->drillDown = null;
    }
}
