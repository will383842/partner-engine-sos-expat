<?php

namespace App\Filament\Partner\Widgets;

use App\Models\SubscriberActivity;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

/**
 * 12-month evolution of SOS-Call call volume for the partner.
 * Two series (Expert / Avocat) for quick at-a-glance mix.
 */
class RevenueEvolutionWidget extends ChartWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('panel.widget.revenue.heading');
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $partnerId = $user?->partner_firebase_id;

        $labels = [];
        $expert = [];
        $lawyer = [];

        for ($i = 11; $i >= 0; $i--) {
            $monthStart = Carbon::now()->startOfMonth()->subMonths($i);
            $monthEnd = (clone $monthStart)->endOfMonth();
            $labels[] = $monthStart->isoFormat('MMM YY');

            if (!$partnerId) {
                $expert[] = 0;
                $lawyer[] = 0;
                continue;
            }

            $expert[] = SubscriberActivity::where('partner_firebase_id', $partnerId)
                ->where('type', 'call_completed')
                ->where('provider_type', 'expat')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            $lawyer[] = SubscriberActivity::where('partner_firebase_id', $partnerId)
                ->where('type', 'call_completed')
                ->where('provider_type', 'lawyer')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => __('panel.common.expert_expat'),
                    'data' => $expert,
                    'borderColor' => 'rgb(14, 165, 233)',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.1)',
                    'tension' => 0.3,
                    'fill' => true,
                ],
                [
                    'label' => __('panel.common.lawyer_local'),
                    'data' => $lawyer,
                    'borderColor' => 'rgb(220, 38, 38)',
                    'backgroundColor' => 'rgba(220, 38, 38, 0.1)',
                    'tension' => 0.3,
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
