<?php

namespace App\Filament\Partner\Widgets;

use App\Models\SubscriberActivity;
use Filament\Widgets\ChartWidget;

/**
 * Expert vs Lawyer split — donut chart showing the mix of call types
 * over the last 12 months. Useful to see whether a partner's clients
 * tend to use one type of help more than another.
 */
class ProviderSplitWidget extends ChartWidget
{
    protected static ?string $heading = 'Répartition Expert / Avocat (12 mois)';
    protected static ?int $sort = 6;
    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected function getData(): array
    {
        $user = auth()->user();
        $partnerId = $user?->partner_firebase_id;
        if (!$partnerId) {
            return ['datasets' => [['data' => []]], 'labels' => []];
        }

        $from = now()->subMonths(12);

        $expat = SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')
            ->where('provider_type', 'expat')
            ->where('created_at', '>=', $from)
            ->count();
        $lawyer = SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')
            ->where('provider_type', 'lawyer')
            ->where('created_at', '>=', $from)
            ->count();

        return [
            'datasets' => [[
                'data' => [$expat, $lawyer],
                'backgroundColor' => ['rgb(14, 165, 233)', 'rgb(220, 38, 38)'],
                'borderWidth' => 0,
            ]],
            'labels' => ['Expert expat', 'Avocat local'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }
}
